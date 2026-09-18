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

    public function __construct(
        private readonly AssessmentEngine $engine,
        private readonly AssessmentModePolicy $modePolicy,
        private readonly DeterministicQuestionTemplateService $templates,
    ) {}

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
        $modePolicy = $this->modePolicy->forQuizKind((string) $quizRow['kind']);

        $sourceQuestions = array_values(DB::table('quiz_questions')
            ->join('questions', 'questions.id', '=', 'quiz_questions.question_id')
            ->join('curriculum_nodes as question_nodes', 'question_nodes.id', '=', 'questions.curriculum_node_id')
            ->leftJoin('learning_objectives', 'learning_objectives.id', '=', 'questions.learning_objective_id')
            ->where('quiz_questions.quiz_id', $quizId)
            ->where('questions.curriculum_node_id', $quizRow['curriculum_node_id'])
            ->where('questions.status', 'published')
            ->orderBy('quiz_questions.source_position')
            ->get([
                'questions.id',
                'questions.curriculum_node_id',
                'questions.content_version',
                'questions.learning_objective_id',
                'questions.type',
                'questions.difficulty',
                'questions.generation_kind',
                'questions.template_contract',
                'questions.prompt',
                'questions.options',
                'questions.answer_contract',
                'questions.explanation',
                'questions.maximum_score',
                'questions.assessment_metadata',
                'questions.option_shuffle_safe',
                'question_nodes.type as curriculum_node_type',
                'learning_objectives.skill_node_id as objective_skill_node_id',
                'quiz_questions.source_position',
            ])
            ->map(function (object $question): array {
                /** @var array<string, mixed> $row */
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
            'mode' => $modePolicy['mode'],
            'hints_allowed' => $modePolicy['hints_allowed'],
            'reveal_policy' => $modePolicy['reveal_policy'],
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
            $options = $question['options'] === null ? [] : $this->decodeList((string) $question['options']);
            $orderedOptions = $this->engine->orderOptions(
                $options,
                (bool) $question['option_shuffle_safe'],
                $metadata,
                $seed,
                $questionId,
            );
            $prompt = $this->decodeArray((string) $question['prompt']);
            $gradingContract = $this->decodeArray((string) $question['answer_contract']);
            $templateInstance = null;
            if ((string) ($question['generation_kind'] ?? 'static') === 'template') {
                if (! is_string($question['template_contract'] ?? null)) {
                    throw new ApiProblemException(409, 'QUESTION_TEMPLATE_INVALID', 'Question template is invalid', 'The published template question has no template contract.');
                }
                $materialized = $this->templates->materialize(
                    $this->decodeArray((string) $question['template_contract']),
                    $prompt,
                    $seed,
                    $questionId,
                );
                $prompt = $materialized['prompt'];
                $gradingContract = $materialized['grading_contract'];
                $templateInstance = $materialized['instance'];
            }
            $skillNodeId = is_string($question['objective_skill_node_id'] ?? null)
                ? (string) $question['objective_skill_node_id']
                : ((string) ($question['curriculum_node_type'] ?? '') === 'skill' ? (string) $question['curriculum_node_id'] : null);
            $hints = is_array($metadata['hints'] ?? null)
                ? array_values(array_filter($metadata['hints'], 'is_string'))
                : [];
            $snapshot = [
                'schema_version' => 3,
                'source_question_id' => $questionId,
                'content_version' => (int) $question['content_version'],
                'learning_objective_id' => is_string($question['learning_objective_id'] ?? null) ? $question['learning_objective_id'] : null,
                'skill_node_id' => $skillNodeId,
                'difficulty' => is_string($question['difficulty'] ?? null) ? $question['difficulty'] : null,
                'generation_kind' => (string) ($question['generation_kind'] ?? 'static'),
                'template_instance' => $templateInstance,
                'type' => $question['type'],
                'prompt' => $prompt,
                'response_contract' => $this->publicResponseContract((string) $question['type'], $orderedOptions, $gradingContract),
                'grading_contract' => $gradingContract,
                'explanation' => $this->decodeArray((string) $question['explanation']),
                'hints' => $hints,
                'mode_policy' => $modePolicy,
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
                    ->first(['revision', 'value', 'duration_ms', 'hint_count', 'answered_at']);

                $policy = is_array($snapshot['mode_policy'] ?? null) ? $snapshot['mode_policy'] : [];
                $hintsAllowed = ($policy['hints_allowed'] ?? false) === true;

                return [
                    'attempt_question_id' => $row['id'],
                    'position' => (int) $row['position'],
                    'type' => $snapshot['type'],
                    'difficulty' => $snapshot['difficulty'] ?? null,
                    'skill_node_id' => $snapshot['skill_node_id'] ?? null,
                    'prompt' => $snapshot['prompt'],
                    'response_contract' => $snapshot['response_contract'],
                    'hints' => $hintsAllowed ? ($snapshot['hints'] ?? []) : [],
                    'current_answer' => $answer === null ? null : $this->answerData((array) $answer),
                ];
            })
            ->values()
            ->all();

        $scope = is_string($attempt['scope_snapshot'] ?? null)
            ? $this->decodeArray((string) $attempt['scope_snapshot'])
            : [];

        return [
            'id' => $attempt['id'],
            'academic_context_id' => $attempt['academic_context_id'],
            'quiz_id' => $attempt['quiz_id'],
            'mode' => (string) ($scope['mode'] ?? $scope['quiz_kind'] ?? 'practice'),
            'hints_allowed' => ($scope['hints_allowed'] ?? false) === true,
            'reveal_policy' => (string) ($scope['reveal_policy'] ?? 'after_submit'),
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
    public function recordAnswer(
        User $user,
        string $attemptId,
        string $attemptQuestionId,
        int $expectedRevision,
        mixed $value,
        int $durationMs = 0,
        int $hintCount = 0,
    ): array
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
        if ($durationMs < 0 || $durationMs > 3_600_000) {
            throw $this->invalidAnswer('duration_ms must be between 0 and 3600000.');
        }
        if ($hintCount < 0 || $hintCount > 20) {
            throw $this->invalidAnswer('hint_count must be between 0 and 20.');
        }
        $policy = is_array($snapshot['mode_policy'] ?? null) ? $snapshot['mode_policy'] : [];
        if ($hintCount > 0 && ($policy['hints_allowed'] ?? false) !== true) {
            throw new ApiProblemException(422, 'HINTS_NOT_ALLOWED', 'Hints are not allowed', 'The assessment mode does not permit hints.');
        }

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
            'duration_ms' => $durationMs,
            'hint_count' => $hintCount,
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
            'duration_ms' => $durationMs,
            'hint_count' => $hintCount,
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
                ->first(['id', 'value']);

            if ($answer !== null && is_string($answer->value)) {
                $answeredCount += 1;
                $value = json_decode($answer->value, true, flags: JSON_THROW_ON_ERROR);
                $correct = $this->isCorrect($type, $gradingContract, $value);
                $awarded = $correct ? $maximumScore : 0.0;
                if ($correct) {
                    $score += $maximumScore;
                }
                DB::table('attempt_answers')->where('id', $answer->id)->update([
                    'is_correct' => $correct,
                    'awarded_score' => $awarded,
                    'graded_at' => now(),
                    'updated_at' => now(),
                ]);
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

        return $this->result($user, $attemptId);
    }

    /**
     * @return array{attempt: array<string, mixed>, score: float, max_score: float, review: list<array<string, mixed>>}
     */
    public function result(User $user, string $attemptId): array
    {
        $attempt = $this->ownedAttempt($user, $attemptId);
        if ($attempt['status'] !== 'graded') {
            throw new ApiProblemException(409, 'ATTEMPT_RESULT_NOT_READY', 'Attempt result is not ready', 'Submit the attempt before requesting answer review.');
        }

        $scope = is_string($attempt['scope_snapshot'] ?? null)
            ? $this->decodeArray((string) $attempt['scope_snapshot'])
            : [];
        $reveal = (string) ($scope['reveal_policy'] ?? 'after_submit') === 'after_submit';
        $review = [];

        foreach (DB::table('attempt_questions')->where('attempt_id', $attemptId)->orderBy('position')->get(['id', 'position', 'question_snapshot']) as $question) {
            $snapshot = $this->decodeArray((string) $question->question_snapshot);
            $answer = DB::table('attempt_answers')
                ->where('attempt_question_id', $question->id)
                ->orderByDesc('revision')
                ->first(['revision', 'value', 'duration_ms', 'hint_count', 'is_correct', 'awarded_score', 'answered_at']);

            $review[] = [
                'attempt_question_id' => (string) $question->id,
                'position' => (int) $question->position,
                'skill_node_id' => $snapshot['skill_node_id'] ?? null,
                'difficulty' => $snapshot['difficulty'] ?? null,
                'content_version' => (int) ($snapshot['content_version'] ?? 1),
                'current_answer' => $answer === null ? null : $this->answerData((array) $answer),
                'correct' => $answer === null || $answer->is_correct === null ? null : (bool) $answer->is_correct,
                'awarded_score' => $answer === null || $answer->awarded_score === null ? 0.0 : (float) $answer->awarded_score,
                'maximum_score' => (float) ($snapshot['maximum_score'] ?? 0),
                'correct_answer' => $reveal ? ($snapshot['grading_contract'] ?? null) : null,
                'explanation' => $reveal ? ($snapshot['explanation'] ?? null) : null,
            ];
        }

        $fresh = $this->ownedAttempt($user, $attemptId);

        return [
            'attempt' => $this->attempt($user, $attemptId),
            'score' => (float) ($fresh['score'] ?? 0),
            'max_score' => (float) ($fresh['max_score'] ?? 0),
            'review' => $review,
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
    /**
     * @param list<array<string, mixed>> $options
     * @param array<string, mixed> $gradingContract
     * @return array<string, mixed>
     */
    private function publicResponseContract(string $type, array $options, array $gradingContract): array
    {
        if (in_array($type, ['single_choice', 'multiple_choice'], true) || array_key_exists('correct_option_id', $gradingContract)) {
            return ['kind' => 'single_choice', 'options' => $options];
        }
        if ($type === 'multi_select' || array_key_exists('correct_option_ids', $gradingContract)) {
            return ['kind' => 'multi_select', 'options' => $options];
        }
        if ($type === 'true_false' || array_key_exists('correct', $gradingContract)) {
            return ['kind' => 'boolean'];
        }
        if ($type === 'numeric' || array_key_exists('value', $gradingContract)) {
            return ['kind' => 'numeric'];
        }
        if ($type === 'ordering' || array_key_exists('correct_order', $gradingContract)) {
            return ['kind' => 'ordering', 'option_ids' => $this->optionIds($options)];
        }
        if ($type === 'matching' || array_key_exists('correct_pairs', $gradingContract)) {
            return ['kind' => 'matching'];
        }

        return ['kind' => 'short_text', 'max_length' => 5000];
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function validateAnswerValue(array $snapshot, mixed $value): void
    {
        $contract = $snapshot['response_contract'] ?? null;
        if (! is_array($contract)) {
            throw new ApiProblemException(500, 'QUESTION_SNAPSHOT_INVALID', 'Question unavailable', 'The stored response contract is invalid.');
        }

        $kind = $contract['kind'] ?? null;
        if ($kind === 'single_choice') {
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

        if ($kind === 'multi_select') {
            if (! is_array($value) || $value === [] || ! array_is_list($value)) {
                throw $this->invalidAnswer('Value must contain one or more published option identifiers.');
            }
            $allowed = [];
            foreach (($contract['options'] ?? []) as $option) {
                if (is_array($option) && is_string($option['id'] ?? null)) {
                    $allowed[] = $option['id'];
                }
            }
            $seen = [];
            foreach ($value as $optionId) {
                if (! is_string($optionId) || ! in_array($optionId, $allowed, true) || isset($seen[$optionId])) {
                    throw $this->invalidAnswer('Value must contain unique published option identifiers.');
                }
                $seen[$optionId] = true;
            }

            return;
        }

        if ($kind === 'boolean') {
            if (! is_bool($value)) {
                throw $this->invalidAnswer('Value must be true or false.');
            }

            return;
        }

        if ($kind === 'numeric') {
            if (! is_int($value) && ! is_float($value)) {
                throw $this->invalidAnswer('Value must be numeric.');
            }

            return;
        }

        if ($kind === 'ordering') {
            if (! is_array($value) || ! array_is_list($value) || $value === []) {
                throw $this->invalidAnswer('Value must be an ordered list of option identifiers.');
            }
            $allowed = is_array($contract['option_ids'] ?? null) ? $contract['option_ids'] : [];
            if (count($value) !== count($allowed) || count(array_unique($value, SORT_REGULAR)) !== count($value)) {
                throw $this->invalidAnswer('Ordering must contain every option exactly once.');
            }
            foreach ($value as $optionId) {
                if (! is_string($optionId) || ! in_array($optionId, $allowed, true)) {
                    throw $this->invalidAnswer('Ordering contains an unknown option identifier.');
                }
            }

            return;
        }

        if ($kind === 'matching') {
            if (! is_array($value) || ! array_is_list($value) || $value === []) {
                throw $this->invalidAnswer('Value must be a non-empty list of matching pairs.');
            }
            foreach ($value as $pair) {
                if (! is_array($pair) || ! is_string($pair['left_id'] ?? null) || ! is_string($pair['right_id'] ?? null)) {
                    throw $this->invalidAnswer('Each matching pair requires left_id and right_id.');
                }
            }

            return;
        }

        if ($kind === 'short_text' && (! is_string($value) || trim($value) === '' || mb_strlen($value) > 5000)) {
            throw $this->invalidAnswer('Value must be non-empty text no longer than 5000 characters.');
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
        if (is_string($contract['correct_option_id'] ?? null)) {
            return is_string($value) && hash_equals((string) $contract['correct_option_id'], $value);
        }

        if (is_array($contract['correct_option_ids'] ?? null) && is_array($value)) {
            $candidate = array_values(array_filter($value, 'is_string'));
            $expected = array_values(array_filter($contract['correct_option_ids'], 'is_string'));
            sort($candidate);
            sort($expected);

            return $candidate === $expected;
        }

        if (is_bool($contract['correct'] ?? null)) {
            return is_bool($value) && $value === $contract['correct'];
        }

        if ((is_int($contract['value'] ?? null) || is_float($contract['value'] ?? null))
            && (is_int($value) || is_float($value))) {
            $tolerance = $contract['tolerance'] ?? 0;
            if (! is_int($tolerance) && ! is_float($tolerance)) {
                return false;
            }

            return abs((float) $value - (float) $contract['value']) <= (float) $tolerance;
        }

        if (is_array($contract['accepted_answers'] ?? null) && is_string($value)) {
            $caseSensitive = (bool) ($contract['case_sensitive'] ?? false);
            $candidate = trim($value);
            foreach ($contract['accepted_answers'] as $accepted) {
                if (! is_string($accepted)) {
                    continue;
                }
                if ($caseSensitive ? hash_equals($accepted, $candidate) : mb_strtolower($accepted) === mb_strtolower($candidate)) {
                    return true;
                }
            }

            return false;
        }

        if (is_array($contract['correct_order'] ?? null) && is_array($value)) {
            return array_values($value) === array_values($contract['correct_order']);
        }

        if (is_array($contract['correct_pairs'] ?? null) && is_array($value)) {
            $normalize = static function (array $pairs): array {
                $normalized = [];
                foreach ($pairs as $pair) {
                    if (is_array($pair) && is_string($pair['left_id'] ?? null) && is_string($pair['right_id'] ?? null)) {
                        $normalized[] = $pair['left_id'].'='.$pair['right_id'];
                    }
                }
                sort($normalized);

                return $normalized;
            };

            return $normalize($value) === $normalize($contract['correct_pairs']);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{revision: int, value: mixed, duration_ms: int, hint_count: int, answered_at: string}
     *
     * @throws JsonException
     */
    private function answerData(array $answer): array
    {
        return [
            'revision' => (int) $answer['revision'],
            'value' => json_decode((string) $answer['value'], true, flags: JSON_THROW_ON_ERROR),
            'duration_ms' => (int) ($answer['duration_ms'] ?? 0),
            'hint_count' => (int) ($answer['hint_count'] ?? 0),
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
