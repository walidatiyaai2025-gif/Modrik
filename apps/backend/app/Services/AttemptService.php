<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class AttemptService
{
    public const ORDERING_ALGORITHM = 'modrik-fy-v1';

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function start(User $user, string $quizId): array
    {
        $lockedUser = DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first(['id']);
        if ($lockedUser === null) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'The authenticated user is unavailable.');
        }

        $quiz = DB::table('quizzes')
            ->join('curriculum_nodes', 'curriculum_nodes.id', '=', 'quizzes.curriculum_node_id')
            ->join('user_academic_contexts', function ($join) use ($user): void {
                $join->on('user_academic_contexts.academic_track_id', '=', 'curriculum_nodes.academic_track_id')
                    ->where('user_academic_contexts.user_id', '=', $user->getKey())
                    ->where('user_academic_contexts.status', '=', 'active');
            })
            ->where('quizzes.id', $quizId)
            ->where('quizzes.status', 'published')
            ->select([
                'quizzes.id',
                'quizzes.curriculum_node_id',
                'quizzes.blueprint_version',
                'user_academic_contexts.id as academic_context_id',
            ])
            ->first();

        if ($quiz === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The published quiz is unavailable in the active academic context.');
        }

        /** @var array{id: string, curriculum_node_id: string, blueprint_version: int, academic_context_id: string} $quizRow */
        $quizRow = (array) $quiz;
        $sourceQuestions = array_values(DB::table('quiz_questions')
            ->join('questions', 'questions.id', '=', 'quiz_questions.question_id')
            ->where('quiz_questions.quiz_id', $quizId)
            ->where('questions.status', 'published')
            ->orderBy('quiz_questions.source_position')
            ->get([
                'questions.id',
                'questions.type',
                'questions.prompt',
                'questions.options',
                'questions.maximum_score',
            ])
            ->map(function (object $question): array {
                /** @var array{id: string, type: string, prompt: string, options: ?string, maximum_score: string|float|int} $row */
                $row = (array) $question;

                return $row;
            })
            ->values()
            ->all());

        if ($sourceQuestions === []) {
            throw new ApiProblemException(409, 'QUIZ_HAS_NO_QUESTIONS', 'Quiz is not ready', 'The published quiz has no eligible questions.');
        }

        $seed = random_bytes(32);
        $orderedQuestions = $this->orderQuestions($sourceQuestions, $seed);
        $attemptId = (string) Str::ulid();
        $startedAt = now();

        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => $user->getKey(),
            'academic_context_id' => $quizRow['academic_context_id'],
            'quiz_id' => $quizId,
            'status' => 'in_progress',
            'seed_encrypted' => Crypt::encryptString(base64_encode($seed)),
            'blueprint_version' => $quizRow['blueprint_version'],
            'ordering_algorithm' => self::ORDERING_ALGORITHM,
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        foreach ($orderedQuestions as $index => $question) {
            $snapshot = [
                'type' => $question['type'],
                'prompt' => $this->decodeArray($question['prompt']),
                'response_contract' => $this->publicResponseContract($question),
            ];

            DB::table('attempt_questions')->insert([
                'id' => (string) Str::ulid(),
                'attempt_id' => $attemptId,
                'question_id' => $question['id'],
                'position' => $index + 1,
                'question_snapshot' => $this->json($snapshot),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
        }

        $this->outbox('attempt', $attemptId, 'assessment.attempt_started', [
            'quiz_id' => $quizId,
            'question_count' => count($orderedQuestions),
            'ordering_algorithm' => self::ORDERING_ALGORITHM,
        ]);

        return $this->attempt($user, $attemptId);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function attempt(User $user, string $attemptId): array
    {
        $attempt = $this->ownedAttempt($user, $attemptId);
        $questions = DB::table('attempt_questions')
            ->where('attempt_id', $attemptId)
            ->orderBy('position')
            ->get(['id', 'position', 'question_snapshot'])
            ->map(function (object $question): array {
                /** @var array{id: string, position: int, question_snapshot: string} $row */
                $row = (array) $question;
                $snapshot = $this->decodeArray($row['question_snapshot']);
                $answer = DB::table('attempt_answers')
                    ->where('attempt_question_id', $row['id'])
                    ->orderByDesc('revision')
                    ->first(['revision', 'value', 'answered_at']);

                return [
                    'attempt_question_id' => $row['id'],
                    'position' => (int) $row['position'],
                    'type' => $snapshot['type'],
                    'prompt' => $snapshot['prompt'],
                    'response_contract' => $snapshot['response_contract'],
                    'current_answer' => $answer === null ? null : $this->answerData((array) $answer),
                ];
            })
            ->values()
            ->all();

        return [
            'id' => $attempt['id'],
            'academic_context_id' => $attempt['academic_context_id'],
            'quiz_id' => $attempt['quiz_id'],
            'status' => $attempt['status'],
            'blueprint_version' => (int) $attempt['blueprint_version'],
            'ordering_algorithm' => $attempt['ordering_algorithm'],
            'started_at' => CarbonImmutable::parse($attempt['started_at'])->toIso8601String(),
            'completed_at' => $attempt['completed_at'] === null ? null : CarbonImmutable::parse($attempt['completed_at'])->toIso8601String(),
            'archived_at' => $attempt['archived_at'] === null ? null : CarbonImmutable::parse($attempt['archived_at'])->toIso8601String(),
            'questions' => $questions,
        ];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function recordAnswer(User $user, string $attemptId, string $attemptQuestionId, int $expectedRevision, mixed $value): array
    {
        $attempt = $this->ownedAttempt($user, $attemptId, lock: true);
        if ($attempt['status'] !== 'in_progress') {
            throw new ApiProblemException(409, 'ATTEMPT_NOT_EDITABLE', 'Attempt is not editable', 'Answers cannot change after an attempt is submitted.');
        }

        $question = DB::table('attempt_questions')
            ->where('id', $attemptQuestionId)
            ->where('attempt_id', $attemptId)
            ->lockForUpdate()
            ->first(['id', 'question_snapshot']);

        if ($question === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The attempt question is unavailable.');
        }

        /** @var array{id: string, question_snapshot: string} $questionRow */
        $questionRow = (array) $question;
        $snapshot = $this->decodeArray($questionRow['question_snapshot']);
        $this->validateAnswerValue($snapshot, $value);

        $latestRevision = (int) (DB::table('attempt_answers')
            ->where('attempt_question_id', $attemptQuestionId)
            ->max('revision') ?? 0);

        if ($latestRevision !== $expectedRevision) {
            throw new ApiProblemException(409, 'ANSWER_REVISION_CONFLICT', 'Answer revision conflict', 'The answer changed since the supplied expected revision.');
        }

        $answeredAt = now();
        $revision = $latestRevision + 1;
        DB::table('attempt_answers')->insert([
            'id' => (string) Str::ulid(),
            'attempt_question_id' => $attemptQuestionId,
            'revision' => $revision,
            'value' => $this->json($value),
            'answered_at' => $answeredAt,
            'created_at' => $answeredAt,
            'updated_at' => $answeredAt,
        ]);

        $this->outbox('attempt', $attemptId, 'assessment.answer_recorded', [
            'attempt_question_id' => $attemptQuestionId,
            'revision' => $revision,
        ]);

        return [
            'revision' => $revision,
            'value' => $value,
            'answered_at' => $answeredAt->toIso8601String(),
        ];
    }

    /**
     * @return array{attempt: array<string, mixed>, score: float, max_score: float}
     *
     * @throws JsonException
     */
    public function submit(User $user, string $attemptId): array
    {
        $attempt = $this->ownedAttempt($user, $attemptId, lock: true);
        if ($attempt['status'] !== 'in_progress') {
            throw new ApiProblemException(409, 'ATTEMPT_ALREADY_SUBMITTED', 'Attempt already submitted', 'Only an in-progress attempt can be submitted.');
        }

        $questions = DB::table('attempt_questions')
            ->join('questions', 'questions.id', '=', 'attempt_questions.question_id')
            ->where('attempt_questions.attempt_id', $attemptId)
            ->orderBy('attempt_questions.position')
            ->get([
                'attempt_questions.id as attempt_question_id',
                'questions.type',
                'questions.answer_contract',
                'questions.maximum_score',
            ]);

        $score = 0.0;
        $maxScore = 0.0;
        $answeredCount = 0;

        foreach ($questions as $question) {
            /** @var array{attempt_question_id: string, type: string, answer_contract: string, maximum_score: string|float|int} $row */
            $row = (array) $question;
            $maximumScore = (float) $row['maximum_score'];
            $maxScore += $maximumScore;
            $answer = DB::table('attempt_answers')
                ->where('attempt_question_id', $row['attempt_question_id'])
                ->orderByDesc('revision')
                ->value('value');

            if (is_string($answer)) {
                $answeredCount++;
                $value = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
                if ($this->isCorrect($row['type'], $this->decodeArray($row['answer_contract']), $value)) {
                    $score += $maximumScore;
                }
            }
        }

        $completedAt = now();
        DB::table('attempts')->where('id', $attemptId)->update([
            'status' => 'graded',
            'score' => $score,
            'max_score' => $maxScore,
            'completed_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        $quiz = DB::table('quizzes')->where('id', $attempt['quiz_id'])->first(['curriculum_node_id', 'blueprint_version']);
        if ($quiz === null) {
            throw new ApiProblemException(500, 'ATTEMPT_QUIZ_MISSING', 'Attempt cannot be graded', 'The snapshotted quiz reference is unavailable.');
        }

        /** @var array{curriculum_node_id: string, blueprint_version: int} $quizRow */
        $quizRow = (array) $quiz;
        $mastery = $maxScore > 0 ? $score / $maxScore : 0.0;
        $academicContextId = $attempt['academic_context_id'];
        if (! is_string($academicContextId)) {
            throw new ApiProblemException(500, 'ATTEMPT_CONTEXT_MISSING', 'Attempt cannot update progress', 'The attempt academic context is unavailable.');
        }
        $progressScope = [
            'user_id' => $user->getKey(),
            'academic_context_id' => $academicContextId,
            'curriculum_node_id' => $quizRow['curriculum_node_id'],
            'source_version' => (int) $quizRow['blueprint_version'],
        ];
        $progressValues = [
            'mastery' => $mastery,
            'calculated_at' => $completedAt,
            'updated_at' => $completedAt,
        ];
        $progressId = DB::table('progress_snapshots')->where($progressScope)->value('id');
        if (is_string($progressId)) {
            DB::table('progress_snapshots')->where('id', $progressId)->update($progressValues);
        } else {
            $progressId = (string) Str::ulid();
            DB::table('progress_snapshots')->insert([
                'id' => $progressId,
                ...$progressScope,
                ...$progressValues,
                'created_at' => $completedAt,
            ]);
        }

        $this->outbox('attempt', $attemptId, 'assessment.attempt_submitted', [
            'submitted_at' => $completedAt->toIso8601String(),
            'answered_count' => $answeredCount,
        ]);
        $this->outbox('progress_snapshot', $progressId, 'progress.snapshot_updated', [
            'curriculum_node_id' => $quizRow['curriculum_node_id'],
            'source_version' => (int) $quizRow['blueprint_version'],
        ]);

        return [
            'attempt' => $this->attempt($user, $attemptId),
            'score' => $score,
            'max_score' => $maxScore,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ownedAttempt(User $user, string $attemptId, bool $lock = false): array
    {
        $query = DB::table('attempts')->where('id', $attemptId)->where('user_id', $user->getKey());
        if ($lock) {
            $query->lockForUpdate();
        }

        $attempt = $query->first();
        if ($attempt === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The attempt is unavailable.');
        }

        /** @var array<string, mixed> $row */
        $row = (array) $attempt;

        return $row;
    }

    /**
     * @param  list<array{id: string, type: string, prompt: string, options: ?string, maximum_score: string|float|int}>  $questions
     * @return list<array{id: string, type: string, prompt: string, options: ?string, maximum_score: string|float|int}>
     */
    private function orderQuestions(array $questions, string $seed): array
    {
        $ordered = $questions;
        $counter = 0;
        for ($index = count($ordered) - 1; $index > 0; $index--) {
            $bytes = hash_hmac('sha256', pack('N', $counter++), $seed, true);
            $unpacked = unpack('Nvalue', substr($bytes, 0, 4));
            $random = is_array($unpacked) ? (int) $unpacked['value'] : 0;
            $swapIndex = $random % ($index + 1);
            [$ordered[$index], $ordered[$swapIndex]] = [$ordered[$swapIndex], $ordered[$index]];
        }

        if (count($ordered) > 1
            && array_column($ordered, 'id') === array_column($questions, 'id')) {
            $rotation = (ord($seed[0]) % (count($ordered) - 1)) + 1;
            $ordered = [...array_slice($ordered, $rotation), ...array_slice($ordered, 0, $rotation)];
        }

        return $ordered;
    }

    /**
     * @param  array{id: string, type: string, prompt: string, options: ?string, maximum_score: string|float|int}  $question
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function publicResponseContract(array $question): array
    {
        if ($question['type'] === 'single_choice') {
            return [
                'kind' => 'single_choice',
                'options' => $question['options'] === null ? [] : $this->decodeArray($question['options']),
            ];
        }

        return ['kind' => 'short_text', 'max_length' => 200];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validateAnswerValue(array $snapshot, mixed $value): void
    {
        $contract = $snapshot['response_contract'] ?? null;
        if (! is_array($contract)) {
            throw new ApiProblemException(500, 'QUESTION_SNAPSHOT_INVALID', 'Question unavailable', 'The stored response contract is invalid.');
        }

        if (($contract['kind'] ?? null) === 'single_choice') {
            $optionIds = [];
            foreach (($contract['options'] ?? []) as $option) {
                if (is_array($option) && is_string($option['id'] ?? null)) {
                    $optionIds[] = $option['id'];
                }
            }
            if (! is_string($value) || ! in_array($value, $optionIds, true)) {
                throw $this->invalidAnswer('Value must be one of the published option identifiers.');
            }

            return;
        }

        if (($contract['kind'] ?? null) === 'short_text'
            && (! is_string($value) || trim($value) === '' || mb_strlen($value) > 200)) {
            throw $this->invalidAnswer('Value must be non-empty text no longer than 200 characters.');
        }
    }

    private function invalidAnswer(string $detail): ApiProblemException
    {
        return new ApiProblemException(
            422,
            'ANSWER_VALUE_INVALID',
            'Answer value is invalid',
            $detail,
            errors: [['pointer' => '/value', 'code' => 'ANSWER_VALUE_INVALID', 'message' => $detail]],
        );
    }

    /** @param array<string, mixed> $contract */
    private function isCorrect(string $type, array $contract, mixed $value): bool
    {
        if ($type === 'single_choice') {
            return is_string($value) && hash_equals((string) ($contract['correct_option_id'] ?? ''), $value);
        }

        if ($type === 'short_text' && is_string($value)) {
            $caseSensitive = (bool) ($contract['case_sensitive'] ?? false);
            $candidate = trim($value);
            foreach (($contract['accepted_answers'] ?? []) as $accepted) {
                if (! is_string($accepted)) {
                    continue;
                }
                if ($caseSensitive ? hash_equals($accepted, $candidate) : mb_strtolower($accepted) === mb_strtolower($candidate)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{revision: int, value: mixed, answered_at: string}
     *
     * @throws JsonException
     */
    private function answerData(array $answer): array
    {
        return [
            'revision' => (int) $answer['revision'],
            'value' => json_decode((string) $answer['value'], true, flags: JSON_THROW_ON_ERROR),
            'answered_at' => CarbonImmutable::parse((string) $answer['answered_at'])->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    private function outbox(string $aggregateType, string $aggregateId, string $eventType, array $payload): void
    {
        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'event_type' => $eventType,
            'payload' => $this->json($payload),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function decodeArray(string $json): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @throws JsonException */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
