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
    public const ORDERING_ALGORITHM = AssessmentEngine::ALGORITHM;

    private readonly AssessmentEngine $engine;

    public function __construct(AssessmentEngine $engine)
    {
        $this->engine = $engine;
    }

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
                'quizzes.kind',
                'quizzes.blueprint_version',
                'quizzes.blueprint',
                'user_academic_contexts.id as academic_context_id',
            ])
            ->first();

        if ($quiz === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The published quiz is unavailable in the active academic context.');
        }

        /** @var array{id: string, curriculum_node_id: string, kind: string, blueprint_version: int, blueprint: ?string, academic_context_id: string} $quizRow */
        $quizRow = (array) $quiz;
        $sourceQuestions = array_values(DB::table('quiz_questions')
            ->join('questions', 'questions.id', '=', 'quiz_questions.question_id')
            ->where('quiz_questions.quiz_id', $quizId)
            ->where('questions.curriculum_node_id', $quizRow['curriculum_node_id'])
            ->where('questions.status', 'published')
            ->orderBy('quiz_questions.source_position')
            ->get([
                'questions.id',
                'questions.curriculum_node_id',
                'questions.content_version',
                'questions.type',
                'questions.prompt',
                'questions.options',
                'questions.answer_contract',
                'questions.maximum_score',
                'questions.assessment_metadata',
                'questions.option_shuffle_safe',
                'quiz_questions.source_position',
            ])
            ->map(function (object $question): array {
                /** @var array{id: string, curriculum_node_id: string, content_version: int, type: string, prompt: string, options: ?string, answer_contract: string, maximum_score: string|float|int, assessment_metadata: ?string, option_shuffle_safe: int|bool, source_position: int} $row */
                $row = (array) $question;
                $row['metadata'] = $row['assessment_metadata'] === null
                    ? []
                    : $this->decodeArray($row['assessment_metadata']);

                return $row;
            })
            ->values()
            ->all());

        if ($sourceQuestions === []) {
            throw new ApiProblemException(409, 'QUIZ_HAS_NO_QUESTIONS', 'Quiz is not ready', 'The published quiz has no eligible questions in the quiz scope.');
        }

        $previousQuestionIds = $this->previousQuestionIds(
            (string) $user->getKey(),
            $quizId,
            $quizRow['academic_context_id'],
        );
        $seed = random_bytes(32);
        $blueprint = $quizRow['blueprint'] === null ? null : $this->decodeArray($quizRow['blueprint']);
        $plan = $this->engine->buildPlan($sourceQuestions, $blueprint, $seed, $previousQuestionIds);
        $attemptId = (string) Str::ulid();
        $startedAt = now();
        $seedFingerprint = hash('sha256', $seed);
        $scopeSnapshot = [
            'curriculum_node_id' => $quizRow['curriculum_node_id'],
            'quiz_kind' => $quizRow['kind'],
            'blueprint_version' => (int) $quizRow['blueprint_version'],
            'blueprint' => $blueprint,
            'question_order_policy' => $plan['question_order_policy'],
            'selection_algorithm' => AssessmentEngine::SELECTION_ALGORITHM,
            'option_ordering_algorithm' => AssessmentEngine::OPTION_ORDERING_ALGORITHM,
        ];

        DB::table('attempts')->insert([
            'id' => $attemptId,
            'user_id' => $user->getKey(),
            'academic_context_id' => $quizRow['academic_context_id'],
            'quiz_id' => $quizId,
            'status' => 'in_progress',
            'seed_encrypted' => Crypt::encryptString(base64_encode($seed)),
            'seed_fingerprint' => $seedFingerprint,
            'blueprint_version' => $quizRow['blueprint_version'],
            'scope_snapshot' => $this->json($scopeSnapshot),
            'ordering_algorithm' => self::ORDERING_ALGORITHM,
            'started_at' => $startedAt,
            'created_at' => $startedAt,
            'updated_at' => $startedAt,
        ]);

        $selectedQuestionIds = [];
        foreach ($plan['questions'] as $index => $question) {
            $questionId = (string) $question['id'];
            $selectedQuestionIds[] = $questionId;
            /** @var array<string, mixed> $metadata */
            $metadata = is_array($question['metadata'] ?? null) ? $question['metadata'] : [];
            $options = $question['options'] === null ? [] : $this->decodeList($question['options']);
            $orderedOptions = $this->engine->orderOptions(
                $options,
                (bool) $question['option_shuffle_safe'],
                $metadata,
                $seed,
                $questionId,
            );
            $snapshot = [
                'schema_version' => 2,
                'source_question_id' => $questionId,
                'content_version' => (int) $question['content_version'],
                'type' => $question['type'],
                'prompt' => $this->decodeArray($question['prompt']),
                'response_contract' => $this->publicResponseContract((string) $question['type'], $orderedOptions),
                'grading_contract' => $this->decodeArray($question['answer_contract']),
                'maximum_score' => (float) $question['maximum_score'],
                'assessment_metadata' => $metadata,
                'option_shuffle_applied' => $this->optionIds($orderedOptions) !== $this->optionIds($options),
            ];

            DB::table('attempt_questions')->insert([
                'id' => (string) Str::ulid(),
                'attempt_id' => $attemptId,
                'question_id' => $questionId,
                'position' => $index + 1,
                'question_snapshot' => $this->json($snapshot),
                'created_at' => $startedAt,
                'updated_at' => $startedAt,
            ]);
        }

        $this->outbox('attempt', $attemptId, 'assessment.attempt_started', [
            'quiz_id' => $quizId,
            'blueprint_version' => (int) $quizRow['blueprint_version'],
            'question_count' => count($selectedQuestionIds),
            'selected_question_ids' => $selectedQuestionIds,
            'ordering_algorithm' => self::ORDERING_ALGORITHM,
            'selection_algorithm' => AssessmentEngine::SELECTION_ALGORITHM,
            'option_ordering_algorithm' => AssessmentEngine::OPTION_ORDERING_ALGORITHM,
            'question_order_policy' => $plan['question_order_policy'],
            'selection_varied_from_previous_attempt' => $plan['selection_varied'],
            'seed_fingerprint' => $seedFingerprint,
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
            ->where('attempt_id', $attemptId)
            ->orderBy('position')
            ->get(['id as attempt_question_id', 'question_snapshot']);

        $score = 0.0;
        $maxScore = 0.0;
        $answeredCount = 0;

        foreach ($questions as $question) {
            /** @var array{attempt_question_id: string, question_snapshot: string} $row */
            $row = (array) $question;
            $snapshot = $this->decodeArray($row['question_snapshot']);
            $type = $snapshot['type'] ?? null;
            $gradingContract = $snapshot['grading_contract'] ?? null;
            $maximumScore = $snapshot['maximum_score'] ?? null;
            if (is_string($type) === false || is_array($gradingContract) === false || (is_int($maximumScore) === false && is_float($maximumScore) === false)) {
                throw new ApiProblemException(500, 'QUESTION_SNAPSHOT_INVALID', 'Attempt cannot be graded', 'The immutable grading snapshot is invalid.');
            }

            $maximumScore = (float) $maximumScore;
            $maxScore += $maximumScore;
            $answer = DB::table('attempt_answers')
                ->where('attempt_question_id', $row['attempt_question_id'])
                ->orderByDesc('revision')
                ->value('value');

            if (is_string($answer)) {
                $answeredCount += 1;
                $value = json_decode($answer, true, flags: JSON_THROW_ON_ERROR);
                if ($this->isCorrect($type, $gradingContract, $value)) {
                    $score += $maximumScore;
                }
            }
        }

        $scopeJson = $attempt['scope_snapshot'] ?? null;
        if (is_string($scopeJson) === false) {
            throw new ApiProblemException(500, 'ATTEMPT_SCOPE_SNAPSHOT_MISSING', 'Attempt cannot be graded', 'The immutable attempt scope snapshot is unavailable.');
        }
        $scope = $this->decodeArray($scopeJson);
        $curriculumNodeId = $scope['curriculum_node_id'] ?? null;
        if (is_string($curriculumNodeId) === false || Str::isUlid($curriculumNodeId) === false) {
            throw new ApiProblemException(500, 'ATTEMPT_SCOPE_SNAPSHOT_INVALID', 'Attempt cannot be graded', 'The immutable attempt scope snapshot is invalid.');
        }

        $completedAt = now();
        DB::table('attempts')->where('id', $attemptId)->update([
            'status' => 'graded',
            'score' => $score,
            'max_score' => $maxScore,
            'completed_at' => $completedAt,
            'updated_at' => $completedAt,
        ]);

        $mastery = $maxScore > 0 ? $score / $maxScore : 0.0;
        $academicContextId = $attempt['academic_context_id'];
        if (is_string($academicContextId) === false) {
            throw new ApiProblemException(500, 'ATTEMPT_CONTEXT_MISSING', 'Attempt cannot update progress', 'The attempt academic context is unavailable.');
        }
        $sourceVersion = (int) $attempt['blueprint_version'];
        $progressScope = [
            'user_id' => $user->getKey(),
            'academic_context_id' => $academicContextId,
            'curriculum_node_id' => $curriculumNodeId,
            'source_version' => $sourceVersion,
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
            'score' => $score,
            'max_score' => $maxScore,
            'blueprint_version' => $sourceVersion,
        ]);
        $this->outbox('progress_snapshot', $progressId, 'progress.snapshot_updated', [
            'curriculum_node_id' => $curriculumNodeId,
            'source_version' => $sourceVersion,
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

    /** @return list<string> */
    private function previousQuestionIds(string $userId, string $quizId, string $academicContextId): array
    {
        $previousAttemptId = DB::table('attempts')
            ->where('user_id', $userId)
            ->where('quiz_id', $quizId)
            ->where('academic_context_id', $academicContextId)
            ->orderByDesc('started_at')
            ->orderByDesc('id')
            ->value('id');
        if (is_string($previousAttemptId) === false) {
            return [];
        }

        /** @var list<string> $ids */
        $ids = DB::table('attempt_questions')
            ->where('attempt_id', $previousAttemptId)
            ->orderBy('position')
            ->pluck('question_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();

        return $ids;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return array<string, mixed>
     */
    private function publicResponseContract(string $type, array $options): array
    {
        if ($type === 'single_choice') {
            return ['kind' => 'single_choice', 'options' => $options];
        }
        if ($type === 'multiple_choice') {
            return ['kind' => 'multiple_choice', 'options' => $options];
        }

        return ['kind' => 'short_text', 'max_length' => 200];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function validateAnswerValue(array $snapshot, mixed $value): void
    {
        $contract = $snapshot['response_contract'] ?? null;
        if (is_array($contract) === false) {
            throw new ApiProblemException(500, 'QUESTION_SNAPSHOT_INVALID', 'Question unavailable', 'The stored response contract is invalid.');
        }

        if (($contract['kind'] ?? null) === 'single_choice') {
            $optionIds = [];
            foreach (($contract['options'] ?? []) as $option) {
                if (is_array($option) && is_string($option['id'] ?? null)) {
                    $optionIds[] = $option['id'];
                }
            }
            if (is_string($value) === false || in_array($value, $optionIds, true) === false) {
                throw $this->invalidAnswer('Value must be one of the published option identifiers.');
            }

            return;
        }

        if (($contract['kind'] ?? null) === 'multiple_choice') {
            if (is_array($value) === false || $value === []) {
                throw $this->invalidAnswer('Value must contain one or more published option identifiers.');
            }
            $allowed = [];
            foreach (($contract['options'] ?? []) as $option) {
                if (is_array($option) && is_string($option['id'] ?? null)) {
                    $allowed[] = $option['id'];
                }
            }
            foreach ($value as $optionId) {
                if (is_string($optionId) === false || in_array($optionId, $allowed, true) === false) {
                    throw $this->invalidAnswer('Value must contain only published option identifiers.');
                }
            }

            return;
        }

        if (($contract['kind'] ?? null) === 'short_text'
            && (is_string($value) === false || trim($value) === '' || mb_strlen($value) > 200)) {
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

    /** @param  array<string, mixed>  $contract */
    private function isCorrect(string $type, array $contract, mixed $value): bool
    {
        if ($type === 'single_choice') {
            return is_string($value) && hash_equals((string) ($contract['correct_option_id'] ?? ''), $value);
        }

        if ($type === 'multiple_choice' && is_array($value)) {
            $correct = $contract['correct_option_ids'] ?? [];
            if (is_array($correct) === false) {
                return false;
            }
            $candidate = array_values(array_filter($value, 'is_string'));
            $expected = array_values(array_filter($correct, 'is_string'));
            sort($candidate);
            sort($expected);

            return $candidate === $expected;
        }

        if ($type === 'short_text' && is_string($value)) {
            $caseSensitive = (bool) ($contract['case_sensitive'] ?? false);
            $candidate = trim($value);
            foreach (($contract['accepted_answers'] ?? []) as $accepted) {
                if (is_string($accepted) === false) {
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
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (is_array($decoded) === false || array_is_list($decoded)) {
            throw new ApiProblemException(500, 'ASSESSMENT_JSON_INVALID', 'Assessment data is invalid', 'Expected a JSON object in the assessment contract.');
        }

        return $decoded;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws JsonException
     */
    private function decodeList(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (is_array($decoded) === false || array_is_list($decoded) === false) {
            throw new ApiProblemException(500, 'ASSESSMENT_JSON_INVALID', 'Assessment data is invalid', 'Expected a JSON list in the assessment contract.');
        }

        $result = [];
        foreach ($decoded as $item) {
            if (is_array($item) === false) {
                throw new ApiProblemException(500, 'ASSESSMENT_JSON_INVALID', 'Assessment data is invalid', 'Assessment option entries must be objects.');
            }
            $result[] = $item;
        }

        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $options
     * @return list<string>
     */
    private function optionIds(array $options): array
    {
        return array_values(array_map(static fn (array $option): string => (string) ($option['id'] ?? ''), $options));
    }

    /** @throws JsonException */
    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
