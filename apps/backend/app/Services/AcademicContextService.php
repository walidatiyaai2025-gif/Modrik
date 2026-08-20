<?php

namespace App\Services;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;

final class AcademicContextService
{
    /** @return array<string, mixed> */
    public function activate(User $user, string $academicTrackId): array
    {
        return DB::transaction(function () use ($user, $academicTrackId): array {
            $this->lockUser($user);
            $track = $this->track($academicTrackId);
            $current = $this->activeContext($user);
            if ($current !== null) {
                $code = $current['academic_track_id'] === $academicTrackId
                    ? 'ACADEMIC_CONTEXT_ALREADY_ACTIVE'
                    : 'ACADEMIC_CONTEXT_RESET_REQUIRED';
                $detail = $current['academic_track_id'] === $academicTrackId
                    ? 'The requested academic track is already active.'
                    : 'An active academic context can only be changed through the full reset flow.';
                throw new ApiProblemException(409, $code, 'Academic context cannot be activated', $detail);
            }

            return $this->createContext($user, $track, null, 'activated', 0, 0);
        });
    }

    /** @return array<string, mixed> */
    public function reset(User $user, string $academicTrackId): array
    {
        return DB::transaction(function () use ($user, $academicTrackId): array {
            $this->lockUser($user);
            $track = $this->track($academicTrackId);
            $current = $this->activeContext($user);
            if ($current === null) {
                throw new ApiProblemException(409, 'ACADEMIC_CONTEXT_ONBOARDING_REQUIRED', 'Academic context reset unavailable', 'Activate an academic context before requesting a reset.');
            }
            if ($current['academic_track_id'] === $academicTrackId) {
                throw new ApiProblemException(409, 'ACADEMIC_CONTEXT_UNCHANGED', 'Academic context unchanged', 'A full reset requires a different academic track.');
            }

            $occurredAt = now();
            $attemptCount = DB::table('attempts')
                ->where('academic_context_id', $current['id'])
                ->whereNull('archived_at')
                ->count();
            $progressCount = DB::table('progress_snapshots')
                ->where('academic_context_id', $current['id'])
                ->whereNull('archived_at')
                ->count();

            DB::table('attempts')
                ->where('academic_context_id', $current['id'])
                ->where('status', 'in_progress')
                ->update([
                    'status' => 'abandoned',
                    'completed_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);
            DB::table('attempts')
                ->where('academic_context_id', $current['id'])
                ->whereNull('archived_at')
                ->update(['archived_at' => $occurredAt, 'updated_at' => $occurredAt]);
            DB::table('progress_snapshots')
                ->where('academic_context_id', $current['id'])
                ->whereNull('archived_at')
                ->update(['archived_at' => $occurredAt, 'updated_at' => $occurredAt]);
            DB::table('user_academic_contexts')
                ->where('id', $current['id'])
                ->update([
                    'status' => 'archived',
                    'archived_at' => $occurredAt,
                    'updated_at' => $occurredAt,
                ]);

            return $this->createContext(
                $user,
                $track,
                $current['id'],
                'reset',
                $attemptCount,
                $progressCount,
                $occurredAt,
            );
        });
    }

    private function lockUser(User $user): void
    {
        $locked = DB::table('users')->where('id', $user->getKey())->lockForUpdate()->first(['id']);
        if ($locked === null) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'The authenticated user is unavailable.');
        }
    }

    /** @return array{id: string, year_level: string} */
    private function track(string $academicTrackId): array
    {
        $track = DB::table('academic_tracks')->where('id', $academicTrackId)->first(['id', 'year_level']);
        if ($track === null) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The requested academic track is unavailable.');
        }

        /** @var array{id: string, year_level: string} $row */
        $row = (array) $track;

        return $row;
    }

    /** @return null|array{id: string, academic_track_id: string} */
    private function activeContext(User $user): ?array
    {
        $context = DB::table('user_academic_contexts')
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->lockForUpdate()
            ->first(['id', 'academic_track_id']);

        if ($context === null) {
            return null;
        }

        /** @var array{id: string, academic_track_id: string} $row */
        $row = (array) $context;

        return $row;
    }

    /**
     * @param  array{id: string, year_level: string}  $track
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    private function createContext(
        User $user,
        array $track,
        ?string $fromContextId,
        string $action,
        int $archivedAttemptCount,
        int $archivedProgressCount,
        ?Carbon $occurredAt = null,
    ): array {
        $occurredAt ??= now();
        $contextId = (string) Str::ulid();
        $transitionId = (string) Str::ulid();
        DB::table('user_academic_contexts')->insert([
            'id' => $contextId,
            'user_id' => $user->getKey(),
            'academic_track_id' => $track['id'],
            'status' => 'active',
            'activated_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);
        DB::table('academic_context_transitions')->insert([
            'id' => $transitionId,
            'user_id' => $user->getKey(),
            'from_context_id' => $fromContextId,
            'to_context_id' => $contextId,
            'action' => $action,
            'archived_attempt_count' => $archivedAttemptCount,
            'archived_progress_count' => $archivedProgressCount,
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        $eventType = $action === 'reset' ? 'academic.context_reset' : 'academic.context_activated';
        $payload = [
            'academic_track_id' => $track['id'],
            'transition_id' => $transitionId,
        ];
        if ($action === 'reset') {
            $payload += [
                'previous_context_id' => $fromContextId,
                'archived_attempt_count' => $archivedAttemptCount,
                'archived_progress_count' => $archivedProgressCount,
            ];
        }
        DB::table('outbox_events')->insert([
            'id' => (string) Str::ulid(),
            'aggregate_type' => 'academic_context',
            'aggregate_id' => $contextId,
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $occurredAt,
            'created_at' => $occurredAt,
            'updated_at' => $occurredAt,
        ]);

        return [
            'state' => 'active',
            'context_id' => $contextId,
            'academic_track_id' => $track['id'],
            'year_level' => $track['year_level'],
            'activated_at' => $occurredAt->toIso8601String(),
        ];
    }
}
