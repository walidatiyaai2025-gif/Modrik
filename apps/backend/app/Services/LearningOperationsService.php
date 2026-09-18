<?php

namespace App\Services;

use App\Exceptions\LearningOperationBlocked;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

final class LearningOperationsService
{
    /** @var array<string, array{default_state: string, scope_allowed: bool}> */
    private const FEATURES = [
        'student_quiz' => ['default_state' => 'enabled', 'scope_allowed' => true],
        'daily_plan' => ['default_state' => 'disabled', 'scope_allowed' => true],
        'diagnostic' => ['default_state' => 'enabled', 'scope_allowed' => true],
        'exam' => ['default_state' => 'enabled', 'scope_allowed' => true],
        'spaced_repetition' => ['default_state' => 'disabled', 'scope_allowed' => true],
        'mistake_notebook' => ['default_state' => 'disabled', 'scope_allowed' => true],
        'parent_dashboard' => ['default_state' => 'disabled', 'scope_allowed' => true],
        'template_questions' => ['default_state' => 'enabled', 'scope_allowed' => true],
        'content_import' => ['default_state' => 'enabled', 'scope_allowed' => false],
        'content_publish' => ['default_state' => 'enabled', 'scope_allowed' => false],
        'learning_notifications' => ['default_state' => 'disabled', 'scope_allowed' => true],
    ];

    /** @var array<string, array{schedule: string, dependency: string|null, runner: string|null}> */
    private const JOBS = [
        'mastery_recalculation' => ['schedule' => 'daily@01:00', 'dependency' => '#356 mastery engine', 'runner' => null],
        'daily_plan_generation' => ['schedule' => 'daily@03:00', 'dependency' => '#357 adaptive study', 'runner' => null],
        'revision_scheduling' => ['schedule' => 'hourly', 'dependency' => '#357 adaptive study', 'runner' => null],
        'question_statistics' => ['schedule' => 'daily@02:00', 'dependency' => null, 'runner' => 'runQuestionStatistics'],
        'progress_aggregation' => ['schedule' => 'daily@02:30', 'dependency' => null, 'runner' => 'runProgressAggregation'],
        'content_integrity' => ['schedule' => 'daily@04:00', 'dependency' => null, 'runner' => 'runContentIntegrity'],
        'runtime_cleanup' => ['schedule' => 'daily@05:00', 'dependency' => null, 'runner' => 'runRuntimeCleanup'],
        'notification_dispatch' => ['schedule' => 'hourly', 'dependency' => 'approved external delivery adapter', 'runner' => null],
    ];

    /** @return array<string, array{default_state: string, scope_allowed: bool}> */
    public function featureDefinitions(): array
    {
        return self::FEATURES;
    }

    /** @return array<string, array{schedule: string, dependency: string|null, runnable: bool}> */
    public function jobDefinitions(): array
    {
        $result = [];
        foreach (self::JOBS as $key => $definition) {
            $result[$key] = [
                'schedule' => $definition['schedule'],
                'dependency' => $definition['dependency'],
                'runnable' => is_string($definition['runner']),
            ];
        }

        return $result;
    }

    /** @return array<string, array<string, mixed>> */
    public function features(): array
    {
        $rows = DB::table('learning_feature_controls')->get()->keyBy('feature_key');
        $result = [];

        foreach (self::FEATURES as $key => $definition) {
            $row = $rows->get($key);
            $result[$key] = [
                'feature_key' => $key,
                'state' => $row === null ? $definition['default_state'] : (string) $row->state,
                'scope' => $row === null || $row->scope === null ? null : $this->decodeObject((string) $row->scope),
                'version' => $row === null ? 0 : (int) $row->version,
                'persisted' => $row !== null,
                'scope_allowed' => $definition['scope_allowed'],
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed>|null $scope
     * @return array<string, mixed>
     */
    public function updateFeature(
        string $featureKey,
        string $state,
        ?array $scope,
        int $expectedVersion,
        string $reason,
        string $actorId,
    ): array {
        $definition = self::FEATURES[$featureKey] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unknown learning feature.');
        }
        if (! in_array($state, ['disabled', 'enabled', 'pilot', 'admin_only'], true)) {
            throw new InvalidArgumentException('Invalid learning feature state.');
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A change reason between 8 and 500 characters is required.');
        }
        if (!$definition['scope_allowed'] && $scope !== null && $scope !== []) {
            throw new InvalidArgumentException('This feature does not support a scoped rollout.');
        }
        $scope = $this->normalizeScope($scope);

        DB::transaction(function () use ($featureKey, $state, $scope, $expectedVersion, $reason, $actorId): void {
            $row = DB::table('learning_feature_controls')->where('feature_key', $featureKey)->lockForUpdate()->first();
            $currentVersion = $row === null ? 0 : (int) $row->version;
            if ($currentVersion !== $expectedVersion) {
                throw new InvalidArgumentException('The learning feature changed after it was loaded.');
            }

            $id = $row === null ? (string) Str::ulid() : (string) $row->id;
            $nextVersion = $currentVersion + 1;
            $now = now();
            $before = $row === null ? null : [
                'state' => (string) $row->state,
                'scope' => $row->scope === null ? null : $this->decodeObject((string) $row->scope),
                'version' => $currentVersion,
            ];
            $after = ['state' => $state, 'scope' => $scope, 'version' => $nextVersion];

            if ($row === null) {
                DB::table('learning_feature_controls')->insert([
                    'id' => $id,
                    'feature_key' => $featureKey,
                    'state' => $state,
                    'scope' => $scope === null ? null : $this->json($scope),
                    'version' => $nextVersion,
                    'updated_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('learning_feature_controls')->where('id', $id)->update([
                    'state' => $state,
                    'scope' => $scope === null ? null : $this->json($scope),
                    'version' => $nextVersion,
                    'updated_by' => $actorId,
                    'updated_at' => $now,
                ]);
            }

            DB::table('learning_feature_control_audits')->insert([
                'id' => (string) Str::ulid(),
                'feature_control_id' => $id,
                'actor_id' => $actorId,
                'from_version' => $row === null ? null : $currentVersion,
                'to_version' => $nextVersion,
                'before' => $before === null ? null : $this->json($before),
                'after' => $this->json($after),
                'reason' => $reason,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $this->features()[$featureKey];
    }

    /** @return array<string, array<string, mixed>> */
    public function jobs(): array
    {
        $rows = DB::table('learning_job_controls')->get()->keyBy('job_key');
        $result = [];

        foreach (self::JOBS as $key => $definition) {
            $row = $rows->get($key);
            $result[$key] = [
                'job_key' => $key,
                'schedule' => $definition['schedule'],
                'dependency' => $definition['dependency'],
                'availability' => $definition['runner'] === null ? 'blocked_dependency' : 'ready',
                'paused' => $row === null ? false : (bool) $row->paused,
                'version' => $row === null ? 0 : (int) $row->version,
                'last_run_at' => $row?->last_run_at,
                'last_status' => $row?->last_status,
                'last_duration_ms' => $row?->last_duration_ms === null ? null : (int) $row->last_duration_ms,
                'last_counts' => $row === null || $row->last_counts === null ? null : $this->decodeObject((string) $row->last_counts),
                'next_run_at' => $row?->next_run_at,
            ];
        }

        return $result;
    }

    /** @return array<string, mixed> */
    public function setJobPaused(string $jobKey, bool $paused, int $expectedVersion, string $reason, string $actorId): array
    {
        $this->jobDefinition($jobKey);
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A change reason between 8 and 500 characters is required.');
        }

        DB::transaction(function () use ($jobKey, $paused, $expectedVersion, $reason, $actorId): void {
            $row = $this->lockJobControl($jobKey);
            $currentVersion = (int) $row->version;
            if ($currentVersion !== $expectedVersion) {
                throw new InvalidArgumentException('The learning job control changed after it was loaded.');
            }
            $nextVersion = $currentVersion + 1;
            $now = now();

            DB::table('learning_job_controls')->where('id', $row->id)->update([
                'paused' => $paused,
                'version' => $nextVersion,
                'updated_by' => $actorId,
                'updated_at' => $now,
            ]);
            DB::table('learning_job_control_audits')->insert([
                'id' => (string) Str::ulid(),
                'learning_job_control_id' => (string) $row->id,
                'actor_id' => $actorId,
                'action' => $paused ? 'paused' : 'resumed',
                'from_version' => $currentVersion,
                'to_version' => $nextVersion,
                'before_paused' => (bool) $row->paused,
                'after_paused' => $paused,
                'reason' => $reason,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $this->jobs()[$jobKey];
    }

    /** @return array<string, mixed> */
    public function runNow(string $jobKey, string $actorId): array
    {
        $definition = $this->jobDefinition($jobKey);
        if ($definition['runner'] === null) {
            throw new LearningOperationBlocked(
                'LEARNING_JOB_DEPENDENCY_BLOCKED',
                'This job is unavailable until '.$definition['dependency'].' is integrated.',
            );
        }

        $control = $this->ensureJobControl($jobKey);
        if ((bool) $control->paused) {
            throw new LearningOperationBlocked('LEARNING_JOB_PAUSED', 'This job is paused.');
        }

        $runId = (string) Str::ulid();
        $started = now();
        DB::table('learning_job_runs')->insert([
            'id' => $runId,
            'learning_job_control_id' => (string) $control->id,
            'actor_id' => $actorId,
            'trigger' => 'run_now',
            'status' => 'running',
            'started_at' => $started,
            'created_at' => $started,
            'updated_at' => $started,
        ]);

        try {
            $counts = $this->{$definition['runner']}();
            $finished = now();
            $durationMs = max(0, (int) round($started->diffInMilliseconds($finished)));

            DB::transaction(function () use ($runId, $control, $finished, $durationMs, $counts): void {
                DB::table('learning_job_runs')->where('id', $runId)->update([
                    'status' => 'success',
                    'counts' => $this->json($counts),
                    'finished_at' => $finished,
                    'duration_ms' => $durationMs,
                    'updated_at' => $finished,
                ]);
                DB::table('learning_job_controls')->where('id', $control->id)->update([
                    'last_run_at' => $finished,
                    'last_status' => 'success',
                    'last_duration_ms' => $durationMs,
                    'last_counts' => $this->json($counts),
                    'next_run_at' => $finished->copy()->addDay(),
                    'updated_at' => $finished,
                ]);
            });
        } catch (Throwable $exception) {
            $finished = now();
            $durationMs = max(0, (int) round($started->diffInMilliseconds($finished)));
            DB::table('learning_job_runs')->where('id', $runId)->update([
                'status' => 'failed',
                'error_code' => 'LEARNING_JOB_FAILED',
                'finished_at' => $finished,
                'duration_ms' => $durationMs,
                'updated_at' => $finished,
            ]);
            DB::table('learning_job_controls')->where('id', $control->id)->update([
                'last_run_at' => $finished,
                'last_status' => 'failed',
                'last_duration_ms' => $durationMs,
                'last_counts' => null,
                'updated_at' => $finished,
            ]);

            throw $exception;
        }

        return DB::table('learning_job_runs')->where('id', $runId)->first() !== null
            ? (array) DB::table('learning_job_runs')->where('id', $runId)->first()
            : [];
    }

    /** @return list<array<string, mixed>> */
    public function jobHistory(string $jobKey, int $limit = 20): array
    {
        $control = DB::table('learning_job_controls')->where('job_key', $jobKey)->first();
        if ($control === null) {
            return [];
        }

        return DB::table('learning_job_runs')
            ->where('learning_job_control_id', $control->id)
            ->orderByDesc('started_at')
            ->limit(max(1, min(100, $limit)))
            ->get()
            ->map(static fn (object $row): array => (array) $row)
            ->all();
    }

    /** @return array{schedule: string, dependency: string|null, runner: string|null} */
    private function jobDefinition(string $jobKey): array
    {
        $definition = self::JOBS[$jobKey] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unknown learning job.');
        }

        return $definition;
    }

    private function ensureJobControl(string $jobKey): object
    {
        $existing = DB::table('learning_job_controls')->where('job_key', $jobKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $id = (string) Str::ulid();
        $now = now();
        DB::table('learning_job_controls')->insert([
            'id' => $id,
            'job_key' => $jobKey,
            'paused' => false,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('learning_job_controls')->where('id', $id)->firstOrFail();
    }

    private function lockJobControl(string $jobKey): object
    {
        $row = DB::table('learning_job_controls')->where('job_key', $jobKey)->lockForUpdate()->first();
        if ($row !== null) {
            return $row;
        }

        $id = (string) Str::ulid();
        $now = now();
        DB::table('learning_job_controls')->insert([
            'id' => $id,
            'job_key' => $jobKey,
            'paused' => false,
            'version' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return DB::table('learning_job_controls')->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    /** @param array<string, mixed>|null $scope */
    private function normalizeScope(?array $scope): ?array
    {
        if ($scope === null || $scope === []) {
            return null;
        }

        $allowed = ['user_ids', 'academic_years', 'subject_codes'];
        if (array_diff(array_keys($scope), $allowed) !== []) {
            throw new InvalidArgumentException('Unknown learning feature scope field.');
        }

        $normalized = [];
        foreach ($scope as $key => $values) {
            if (! is_array($values) || ! array_is_list($values) || count($values) > 100) {
                throw new InvalidArgumentException('Learning feature scopes must be bounded lists.');
            }
            $items = [];
            foreach ($values as $value) {
                if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 160) {
                    throw new InvalidArgumentException('Learning feature scope values must be bounded strings.');
                }
                if ($key === 'user_ids' && ! Str::isUlid($value)) {
                    throw new InvalidArgumentException('Learning feature user scope values must be ULIDs.');
                }
                $items[] = trim($value);
            }
            $normalized[$key] = array_values(array_unique($items));
        }

        return $normalized === [] ? null : $normalized;
    }

    /** @return array<string, int> */
    private function runQuestionStatistics(): array
    {
        return [
            'questions' => DB::table('questions')->count(),
            'published_questions' => DB::table('questions')->where('status', 'published')->count(),
            'attempted_questions' => DB::table('attempt_questions')->distinct()->count('question_id'),
            'answer_revisions' => DB::table('attempt_answers')->count(),
        ];
    }

    /** @return array<string, int> */
    private function runProgressAggregation(): array
    {
        return [
            'active_progress_rows' => DB::table('progress_snapshots')->whereNull('archived_at')->count(),
            'users_with_progress' => DB::table('progress_snapshots')->whereNull('archived_at')->distinct()->count('user_id'),
            'skill_mastery_rows' => DB::table('student_skill_mastery_states')->whereNull('archived_at')->count(),
        ];
    }

    /** @return array<string, int> */
    private function runContentIntegrity(): array
    {
        return [
            'published_questions' => DB::table('questions')->where('status', 'published')->count(),
            'published_without_provenance' => DB::table('questions')->where('status', 'published')->whereNull('source_provenance')->count(),
            'published_without_objective' => DB::table('questions')->where('status', 'published')->whereNull('learning_objective_id')->count(),
            'unreviewed_questions' => DB::table('questions')->whereNotIn('review_state', ['approved'])->count(),
        ];
    }

    /** @return array<string, int> */
    private function runRuntimeCleanup(): array
    {
        $expiredIdempotency = DB::table('idempotency_keys')->where('expires_at', '<', now())->delete();

        return ['expired_idempotency_rows_deleted' => $expiredIdempotency];
    }

    /** @return array<string, mixed> */
    private function decodeObject(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('Stored learning operations JSON is invalid.');
        }

        return $decoded;
    }

    private function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
