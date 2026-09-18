<?php

namespace App\Services;

use App\Domain\Learning\AdaptiveLearningContract;
use App\Exceptions\ApiProblemException;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class MasteryEngine
{
    public const ALGORITHM_VERSION = 'mastery-v1';

    private const RECENT_WINDOW = 5;

    private const CONFIDENCE_TARGET = 8;

    /** @var array<string, float> */
    private const DIFFICULTY_WEIGHTS = [
        'Easy' => 0.85,
        'Revision' => 0.95,
        'Medium' => 1.00,
        'Hard' => 1.15,
        'Exam-style' => 1.25,
    ];

    public function __construct(private readonly MasteryBandPolicy $bands) {}

    /**
     * @return array{
     *   algorithm_version:string,
     *   user_id:string,
     *   academic_context_id:string,
     *   skill_node_id:string,
     *   evidence_count:int,
     *   score_percent:float,
     *   score_ratio:float,
     *   confidence:float,
     *   last_evidence_at:string|null,
     *   input_fingerprint:string,
     *   factors:array<string,float>
     * }
     */
    public function calculate(User $user, string $academicContextId, string $skillNodeId): array
    {
        $this->requireOwnedActiveSkillScope($user, $academicContextId, $skillNodeId);
        $evidence = $this->evidence($user, $academicContextId, $skillNodeId);

        if ($evidence === []) {
            return [
                'algorithm_version' => self::ALGORITHM_VERSION,
                'user_id' => (string) $user->getKey(),
                'academic_context_id' => $academicContextId,
                'skill_node_id' => $skillNodeId,
                'evidence_count' => 0,
                'score_percent' => 0.0,
                'score_ratio' => 0.0,
                'confidence' => 0.0,
                'last_evidence_at' => null,
                'input_fingerprint' => $this->fingerprint([]),
                'factors' => [
                    'overall_accuracy' => 0.0,
                    'recent_accuracy' => 0.0,
                    'difficulty_performance' => 0.0,
                    'hint_independence' => 0.0,
                    'duration_performance' => 0.0,
                    'revision_independence' => 0.0,
                    'recency_performance' => 0.0,
                    'consistency_performance' => 0.0,
                ],
            ];
        }

        $count = count($evidence);
        $correct = array_sum(array_map(static fn (array $row): int => $row['correct'] ? 1 : 0, $evidence));
        $overall = $correct / $count;

        $recent = array_slice($evidence, -self::RECENT_WINDOW);
        $recentCorrect = array_sum(array_map(static fn (array $row): int => $row['correct'] ? 1 : 0, $recent));
        $recentAccuracy = $recentCorrect / count($recent);

        $difficultyDenominator = array_sum(array_column($evidence, 'difficulty_weight'));
        $difficultyNumerator = array_sum(array_map(
            static fn (array $row): float => $row['correct'] ? $row['difficulty_weight'] : 0.0,
            $evidence,
        ));
        $difficultyPerformance = $difficultyDenominator <= 0.0 ? $overall : $difficultyNumerator / $difficultyDenominator;

        $hintPerformance = $this->average(array_map(
            static fn (array $row): float => $row['correct'] ? $row['hint_factor'] : 0.0,
            $evidence,
        ));
        $durationPerformance = $this->average(array_map(
            static fn (array $row): float => $row['correct'] ? $row['duration_factor'] : 0.0,
            $evidence,
        ));
        $revisionPerformance = $this->average(array_map(
            static fn (array $row): float => $row['correct'] ? $row['revision_factor'] : 0.0,
            $evidence,
        ));
        $recencyDenominator = array_sum(array_column($evidence, 'recency_weight'));
        $recencyNumerator = array_sum(array_map(
            static fn (array $row): float => $row['correct'] ? $row['recency_weight'] : 0.0,
            $evidence,
        ));
        $recencyPerformance = $recencyDenominator <= 0.0 ? $overall : $recencyNumerator / $recencyDenominator;
        $consistency = 1.0 - abs($overall - $recentAccuracy);
        $consistencyPerformance = $overall * $consistency;

        $ratio = $this->clamp(
            (0.30 * $overall)
            + (0.20 * $recentAccuracy)
            + (0.15 * $difficultyPerformance)
            + (0.10 * $hintPerformance)
            + (0.05 * $durationPerformance)
            + (0.05 * $revisionPerformance)
            + (0.10 * $recencyPerformance)
            + (0.05 * $consistencyPerformance),
        );
        $confidence = $this->clamp(
            min(1.0, $count / self::CONFIDENCE_TARGET) * (0.80 + (0.20 * $consistency)),
        );
        $latest = $evidence[array_key_last($evidence)];

        return [
            'algorithm_version' => self::ALGORITHM_VERSION,
            'user_id' => (string) $user->getKey(),
            'academic_context_id' => $academicContextId,
            'skill_node_id' => $skillNodeId,
            'evidence_count' => $count,
            'score_percent' => round($ratio * 100, 2),
            'score_ratio' => round($ratio, 4),
            'confidence' => round($confidence, 4),
            'last_evidence_at' => $latest['graded_at']->toIso8601String(),
            'input_fingerprint' => $this->fingerprint($evidence),
            'factors' => [
                'overall_accuracy' => round($overall * 100, 2),
                'recent_accuracy' => round($recentAccuracy * 100, 2),
                'difficulty_performance' => round($difficultyPerformance * 100, 2),
                'hint_independence' => round($hintPerformance * 100, 2),
                'duration_performance' => round($durationPerformance * 100, 2),
                'revision_independence' => round($revisionPerformance * 100, 2),
                'recency_performance' => round($recencyPerformance * 100, 2),
                'consistency_performance' => round($consistencyPerformance * 100, 2),
            ],
        ];
    }

    /**
     * Recalculates one canonical Student x Skill state from all authoritative evidence.
     *
     * @return array<string, mixed>
     */
    public function recalculate(User $user, string $academicContextId, string $skillNodeId): array
    {
        return DB::transaction(function () use ($user, $academicContextId, $skillNodeId): array {
            $calculation = $this->calculate($user, $academicContextId, $skillNodeId);
            $row = DB::table('student_skill_mastery_states')
                ->where('academic_context_id', $academicContextId)
                ->where('skill_node_id', $skillNodeId)
                ->lockForUpdate()
                ->first();

            if ($row !== null && (string) $row->user_id !== (string) $user->getKey()) {
                throw new ApiProblemException(
                    500,
                    'MASTERY_SCOPE_INTEGRITY_VIOLATION',
                    'Mastery state scope is invalid',
                    'The persisted mastery state does not belong to the academic-context owner.',
                );
            }
            if ($row !== null && $row->archived_at !== null) {
                throw new ApiProblemException(
                    409,
                    'MASTERY_STATE_ARCHIVED',
                    'Mastery state is archived',
                    'Archived mastery history cannot be mutated.',
                );
            }

            $now = now();
            $lastEvidenceAt = $calculation['last_evidence_at'] === null
                ? null
                : CarbonImmutable::parse($calculation['last_evidence_at']);
            $values = [
                'algorithm_version' => self::ALGORITHM_VERSION,
                'mastery_score' => $calculation['score_ratio'],
                'confidence' => $calculation['confidence'],
                'evidence_count' => $calculation['evidence_count'],
                'last_evidence_at' => $lastEvidenceAt,
                'calculated_at' => $now,
                'updated_at' => $now,
            ];

            if ($row === null) {
                $stateId = (string) Str::ulid();
                $stateVersion = 1;
                DB::table('student_skill_mastery_states')->insert([
                    'id' => $stateId,
                    'user_id' => $user->getKey(),
                    'academic_context_id' => $academicContextId,
                    'skill_node_id' => $skillNodeId,
                    ...$values,
                    'state_version' => $stateVersion,
                    'archived_at' => null,
                    'created_at' => $now,
                ]);
                $changed = true;
                $previous = null;
            } else {
                $stateId = (string) $row->id;
                $stateVersion = (int) $row->state_version;
                $changed = ! $this->sameState($row, $calculation);
                $previous = [
                    'state_version' => $stateVersion,
                    'algorithm_version' => $row->algorithm_version === null ? null : (string) $row->algorithm_version,
                    'score_ratio' => $row->mastery_score === null ? null : (float) $row->mastery_score,
                    'confidence' => $row->confidence === null ? null : (float) $row->confidence,
                    'evidence_count' => (int) $row->evidence_count,
                ];

                if ($changed) {
                    $stateVersion++;
                    DB::table('student_skill_mastery_states')->where('id', $stateId)->update([
                        ...$values,
                        'state_version' => $stateVersion,
                    ]);
                }
            }

            if ($changed) {
                $this->outbox($stateId, 'mastery.state_recalculated', [
                    'user_id' => (string) $user->getKey(),
                    'academic_context_id' => $academicContextId,
                    'skill_node_id' => $skillNodeId,
                    'algorithm_version' => self::ALGORITHM_VERSION,
                    'state_version' => $stateVersion,
                    'score_percent' => $calculation['score_percent'],
                    'score_ratio' => $calculation['score_ratio'],
                    'confidence' => $calculation['confidence'],
                    'evidence_count' => $calculation['evidence_count'],
                    'last_evidence_at' => $calculation['last_evidence_at'],
                    'input_fingerprint' => $calculation['input_fingerprint'],
                    'factors' => $calculation['factors'],
                    'previous' => $previous,
                ], $now);
            }

            return $this->state($user, $academicContextId, $skillNodeId) + [
                'changed' => $changed,
                'input_fingerprint' => $calculation['input_fingerprint'],
                'factors' => $calculation['factors'],
            ];
        }, 3);
    }

    /**
     * Incremental hook: only skills touched by one newly graded attempt are recalculated.
     * Each affected skill is still derived from all authoritative evidence, making this
     * path converge exactly with full context recalculation.
     *
     * @return list<array<string, mixed>>
     */
    public function recalculateForAttempt(User $user, string $attemptId): array
    {
        $attempt = DB::table('attempts')
            ->where('id', $attemptId)
            ->where('user_id', $user->getKey())
            ->where('status', 'graded')
            ->whereNull('archived_at')
            ->first(['academic_context_id']);
        if ($attempt === null || ! is_string($attempt->academic_context_id)) {
            throw new ApiProblemException(
                404,
                'RESOURCE_NOT_FOUND',
                'Resource not found',
                'The graded attempt is unavailable for mastery recalculation.',
            );
        }

        $skillIds = [];
        foreach (DB::table('attempt_questions')->where('attempt_id', $attemptId)->get(['question_snapshot']) as $question) {
            $snapshot = $this->snapshot((string) $question->question_snapshot);
            $skillId = $snapshot['skill_node_id'] ?? null;
            if (is_string($skillId) && $skillId !== '') {
                $skillIds[$skillId] = true;
            }
        }
        ksort($skillIds, SORT_STRING);

        $states = [];
        foreach (array_keys($skillIds) as $skillId) {
            $states[] = $this->recalculate($user, (string) $attempt->academic_context_id, $skillId);
        }

        return $states;
    }

    /** @return list<array<string, mixed>> */
    public function recalculateContext(User $user, string $academicContextId): array
    {
        $this->requireOwnedActiveContext($user, $academicContextId);
        $skillIds = [];

        foreach (DB::table('attempt_questions as questions')
            ->join('attempts', 'attempts.id', '=', 'questions.attempt_id')
            ->where('attempts.user_id', $user->getKey())
            ->where('attempts.academic_context_id', $academicContextId)
            ->where('attempts.status', 'graded')
            ->whereNull('attempts.archived_at')
            ->get(['questions.question_snapshot']) as $question) {
            $snapshot = $this->snapshot((string) $question->question_snapshot);
            $skillId = $snapshot['skill_node_id'] ?? null;
            if (is_string($skillId) && $skillId !== '') {
                $skillIds[$skillId] = true;
            }
        }

        foreach (DB::table('student_skill_mastery_states')
            ->where('user_id', $user->getKey())
            ->where('academic_context_id', $academicContextId)
            ->whereNull('archived_at')
            ->pluck('skill_node_id') as $skillId) {
            $skillIds[(string) $skillId] = true;
        }
        ksort($skillIds, SORT_STRING);

        $states = [];
        foreach (array_keys($skillIds) as $skillId) {
            $states[] = $this->recalculate($user, $academicContextId, $skillId);
        }

        return $states;
    }

    /** @return array<string, mixed> */
    public function state(User $user, string $academicContextId, string $skillNodeId, ?string $environment = null): array
    {
        $this->requireOwnedContext($user, $academicContextId);
        $row = DB::table('student_skill_mastery_states')
            ->where('user_id', $user->getKey())
            ->where('academic_context_id', $academicContextId)
            ->where('skill_node_id', $skillNodeId)
            ->first();

        if ($row === null) {
            return [
                'state' => 'cold_start',
                'academic_context_id' => $academicContextId,
                'skill_node_id' => $skillNodeId,
                'algorithm_version' => self::ALGORITHM_VERSION,
                'state_version' => 0,
                'score_percent' => 0.0,
                'confidence' => 0.0,
                'evidence_count' => 0,
                'last_evidence_at' => null,
                'calculated_at' => null,
                'archived_at' => null,
                'display_band' => $this->bands->band(0.0, $environment ?? (string) app()->environment()),
            ];
        }
        if ((string) $row->user_id !== (string) $user->getKey()) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The mastery state is unavailable.');
        }

        $scorePercent = round(((float) ($row->mastery_score ?? 0.0)) * 100, 2);

        return [
            'state' => $row->archived_at === null ? 'active' : 'archived',
            'academic_context_id' => (string) $row->academic_context_id,
            'skill_node_id' => (string) $row->skill_node_id,
            'algorithm_version' => $row->algorithm_version === null ? null : (string) $row->algorithm_version,
            'state_version' => (int) $row->state_version,
            'score_percent' => $scorePercent,
            'confidence' => round((float) ($row->confidence ?? 0.0), 4),
            'evidence_count' => (int) $row->evidence_count,
            'last_evidence_at' => $row->last_evidence_at === null ? null : CarbonImmutable::parse((string) $row->last_evidence_at)->toIso8601String(),
            'calculated_at' => $row->calculated_at === null ? null : CarbonImmutable::parse((string) $row->calculated_at)->toIso8601String(),
            'archived_at' => $row->archived_at === null ? null : CarbonImmutable::parse((string) $row->archived_at)->toIso8601String(),
            'display_band' => $this->bands->band($scorePercent, $environment ?? (string) app()->environment()),
        ];
    }

    /** @return list<array<string, mixed>> */
    public function history(User $user, string $academicContextId, string $skillNodeId, int $limit = 50): array
    {
        $this->requireOwnedContext($user, $academicContextId);
        $stateId = DB::table('student_skill_mastery_states')
            ->where('user_id', $user->getKey())
            ->where('academic_context_id', $academicContextId)
            ->where('skill_node_id', $skillNodeId)
            ->value('id');
        if (! is_string($stateId)) {
            return [];
        }

        return DB::table('outbox_events')
            ->where('aggregate_type', 'student_skill_mastery')
            ->where('aggregate_id', $stateId)
            ->whereIn('event_type', ['mastery.state_recalculated', 'mastery.state_archived'])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(max(1, min(200, $limit)))
            ->get(['event_type', 'payload', 'occurred_at'])
            ->map(function (object $event): array {
                $payload = json_decode((string) $event->payload, true, flags: JSON_THROW_ON_ERROR);

                return [
                    'event_type' => (string) $event->event_type,
                    'occurred_at' => CarbonImmutable::parse((string) $event->occurred_at)->toIso8601String(),
                    'payload' => is_array($payload) ? $payload : [],
                ];
            })
            ->all();
    }

    /**
     * @return list<array{
     *   attempt_question_id:string,
     *   revision:int,
     *   correct:bool,
     *   difficulty:string,
     *   difficulty_weight:float,
     *   hint_factor:float,
     *   duration_factor:float,
     *   revision_factor:float,
     *   recency_weight:float,
     *   graded_at:CarbonImmutable
     * }>
     */
    private function evidence(User $user, string $academicContextId, string $skillNodeId): array
    {
        $rows = DB::table('attempt_answers as answers')
            ->join('attempt_questions as questions', 'questions.id', '=', 'answers.attempt_question_id')
            ->join('attempts', 'attempts.id', '=', 'questions.attempt_id')
            ->where('attempts.user_id', $user->getKey())
            ->where('attempts.academic_context_id', $academicContextId)
            ->where('attempts.status', 'graded')
            ->whereNull('attempts.archived_at')
            ->orderBy('questions.id')
            ->orderBy('answers.revision')
            ->get([
                'questions.id as attempt_question_id',
                'questions.question_snapshot',
                'answers.revision',
                'answers.duration_ms',
                'answers.hint_count',
                'answers.is_correct',
                'answers.graded_at',
            ]);

        /** @var array<string, array<string, mixed>> $latest */
        $latest = [];
        foreach ($rows as $row) {
            $record = (array) $row;
            $snapshot = $this->snapshot((string) $record['question_snapshot']);
            if (($snapshot['skill_node_id'] ?? null) !== $skillNodeId) {
                continue;
            }
            $record['snapshot'] = $snapshot;
            $latest[(string) $record['attempt_question_id']] = $record;
        }

        if ($latest === []) {
            return [];
        }

        $latestTimestamp = null;
        foreach ($latest as $record) {
            if ($record['graded_at'] === null || $record['is_correct'] === null) {
                throw $this->invalidEvidence('A latest answer revision in a graded attempt is not authoritatively graded.');
            }
            $timestamp = CarbonImmutable::parse((string) $record['graded_at']);
            $latestTimestamp = $latestTimestamp === null || $timestamp->greaterThan($latestTimestamp) ? $timestamp : $latestTimestamp;
        }
        if (! $latestTimestamp instanceof CarbonImmutable) {
            throw $this->invalidEvidence('Mastery evidence has no valid grading timestamp.');
        }

        $evidence = [];
        foreach ($latest as $record) {
            /** @var array<string, mixed> $snapshot */
            $snapshot = $record['snapshot'];
            $difficulty = $snapshot['difficulty'] ?? 'Medium';
            if ($difficulty === null) {
                $difficulty = 'Medium';
            }
            if (! is_string($difficulty)
                || ! in_array($difficulty, AdaptiveLearningContract::QUESTION_DIFFICULTIES, true)
                || ! array_key_exists($difficulty, self::DIFFICULTY_WEIGHTS)) {
                throw $this->invalidEvidence('A mastery evidence snapshot has an unsupported difficulty.');
            }

            $revision = (int) $record['revision'];
            if ($revision < 1) {
                throw $this->invalidEvidence('A mastery evidence revision is invalid.');
            }
            $hintCount = max(0, (int) $record['hint_count']);
            $durationMs = max(0, (int) $record['duration_ms']);
            $gradedAt = CarbonImmutable::parse((string) $record['graded_at']);
            $ageDays = max(0, (int) floor($gradedAt->diffInSeconds($latestTimestamp) / 86400));
            $metadata = is_array($snapshot['assessment_metadata'] ?? null) ? $snapshot['assessment_metadata'] : [];
            $durationTarget = $metadata['mastery_duration_target_ms'] ?? null;

            $evidence[] = [
                'attempt_question_id' => (string) $record['attempt_question_id'],
                'revision' => $revision,
                'correct' => (bool) $record['is_correct'],
                'difficulty' => $difficulty,
                'difficulty_weight' => self::DIFFICULTY_WEIGHTS[$difficulty],
                'hint_factor' => round(max(0.50, 1.0 / (1.0 + (0.25 * $hintCount))), 4),
                'duration_factor' => $this->durationFactor($durationMs, $durationTarget),
                'revision_factor' => round(max(0.65, 1.0 / (1.0 + (0.15 * ($revision - 1)))), 4),
                'recency_weight' => match (true) {
                    $ageDays <= 7 => 1.0,
                    $ageDays <= 30 => 0.85,
                    default => 0.70,
                },
                'graded_at' => $gradedAt,
            ];
        }

        usort($evidence, static function (array $left, array $right): int {
            $time = $left['graded_at']->getTimestamp() <=> $right['graded_at']->getTimestamp();

            return $time !== 0 ? $time : strcmp($left['attempt_question_id'], $right['attempt_question_id']);
        });

        return $evidence;
    }

    private function durationFactor(int $durationMs, mixed $target): float
    {
        if (! is_int($target) || $target <= 0 || $target > 3_600_000 || $durationMs <= 0) {
            return 1.0;
        }

        return match (true) {
            $durationMs <= $target => 1.0,
            $durationMs <= (int) round($target * 1.5) => 0.95,
            $durationMs <= ($target * 2) => 0.85,
            default => 0.70,
        };
    }

    private function requireOwnedActiveSkillScope(User $user, string $academicContextId, string $skillNodeId): void
    {
        $this->requireOwnedActiveContext($user, $academicContextId);
        $exists = DB::table('curriculum_nodes as skills')
            ->join('user_academic_contexts as contexts', 'contexts.academic_track_id', '=', 'skills.academic_track_id')
            ->where('contexts.id', $academicContextId)
            ->where('contexts.user_id', $user->getKey())
            ->where('skills.id', $skillNodeId)
            ->where('skills.type', 'skill')
            ->exists();

        if (! $exists) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The Skill is unavailable in the learner academic context.');
        }
    }

    private function requireOwnedActiveContext(User $user, string $academicContextId): void
    {
        $owned = DB::table('user_academic_contexts')
            ->where('id', $academicContextId)
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereNull('archived_at')
            ->exists();
        if (! $owned) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The active academic context is unavailable.');
        }
    }

    private function requireOwnedContext(User $user, string $academicContextId): void
    {
        $owned = DB::table('user_academic_contexts')
            ->where('id', $academicContextId)
            ->where('user_id', $user->getKey())
            ->exists();
        if (! $owned) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The academic context is unavailable.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshot(string $json): array
    {
        try {
            $snapshot = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw $this->invalidEvidence('An immutable assessment question snapshot could not be decoded.');
        }
        if (! is_array($snapshot) || array_is_list($snapshot)) {
            throw $this->invalidEvidence('An immutable assessment question snapshot is not a JSON object.');
        }

        return $snapshot;
    }

    /** @param array<int, float> $values */
    private function average(array $values): float
    {
        return $values === [] ? 0.0 : array_sum($values) / count($values);
    }

    private function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }

    /** @param list<array<string, mixed>> $evidence */
    private function fingerprint(array $evidence): string
    {
        $canonical = array_map(static fn (array $row): array => [
            'attempt_question_id' => $row['attempt_question_id'] ?? null,
            'revision' => $row['revision'] ?? null,
            'correct' => $row['correct'] ?? null,
            'difficulty' => $row['difficulty'] ?? null,
            'hint_factor' => $row['hint_factor'] ?? null,
            'duration_factor' => $row['duration_factor'] ?? null,
            'revision_factor' => $row['revision_factor'] ?? null,
            'recency_weight' => $row['recency_weight'] ?? null,
            'graded_at' => ($row['graded_at'] ?? null) instanceof CarbonImmutable
                ? $row['graded_at']->toIso8601String()
                : null,
        ], $evidence);

        return hash('sha256', json_encode([
            'algorithm_version' => self::ALGORITHM_VERSION,
            'evidence' => $canonical,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function sameState(object $row, array $calculation): bool
    {
        $lastEvidence = $row->last_evidence_at === null ? null : CarbonImmutable::parse((string) $row->last_evidence_at)->toIso8601String();

        return (string) ($row->algorithm_version ?? '') === self::ALGORITHM_VERSION
            && round((float) ($row->mastery_score ?? 0.0), 4) === $calculation['score_ratio']
            && round((float) ($row->confidence ?? 0.0), 4) === $calculation['confidence']
            && (int) $row->evidence_count === $calculation['evidence_count']
            && $lastEvidence === $calculation['last_evidence_at'];
    }

    /** @param array<string, mixed> $payload */
    private function outbox(string $stateId, string $eventType, array $payload, mixed $occurredAt): void
    {
        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'aggregate_type' => 'student_skill_mastery',
            'aggregate_id' => $stateId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
    }

    private function invalidEvidence(string $detail): ApiProblemException
    {
        return new ApiProblemException(
            500,
            'MASTERY_EVIDENCE_INVALID',
            'Mastery evidence is invalid',
            $detail,
        );
    }
}
