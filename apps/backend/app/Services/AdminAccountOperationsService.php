<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AdminAccountOperationsService
{
    public function __construct(private readonly AuthLifecycleService $authLifecycle) {}

    /** @return list<array<string, mixed>> */
    public function accounts(
        string $search = '',
        string $role = 'all',
        string $status = 'all',
        string $provider = 'all',
        string $sessionState = 'all',
    ): array {
        // Account deletion is a persisted Auth tombstone, not Eloquent SoftDeletes.
        $query = User::query()->orderByDesc('updated_at')->limit(250);
        $search = trim($search);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $needle = '%'.$search.'%';
                $builder->where('name', 'like', $needle)->orWhere('email', 'like', $needle);
            });
        }

        if (in_array($role, ['admin', 'content_team', 'student'], true)) {
            $query->where('role', $role);
        }

        if (in_array($status, ['active', 'deleted'], true)) {
            $query->where('account_status', $status);
        }

        if ($provider === 'password') {
            $query->where('password_enabled', true);
        } elseif (in_array($provider, ['google', 'apple'], true)) {
            $query->whereExists(function (QueryBuilder $subquery) use ($provider): void {
                $subquery->selectRaw('1')
                    ->from('auth_provider_identities')
                    ->whereColumn('auth_provider_identities.user_id', 'users.id')
                    ->where('auth_provider_identities.provider', $provider)
                    ->whereNull('auth_provider_identities.revoked_at');
            });
        }

        if (in_array($sessionState, ['active', 'revoked', 'expired'], true)) {
            $query->whereExists(function (QueryBuilder $subquery) use ($sessionState): void {
                $subquery->selectRaw('1')
                    ->from('auth_sessions')
                    ->whereColumn('auth_sessions.user_id', 'users.id');

                match ($sessionState) {
                    'active' => $subquery->whereNull('auth_sessions.revoked_at')->where('auth_sessions.expires_at', '>', now()),
                    'revoked' => $subquery->whereNotNull('auth_sessions.revoked_at'),
                    default => $subquery->whereNull('auth_sessions.revoked_at')->where('auth_sessions.expires_at', '<=', now()),
                };
            });
        } elseif ($sessionState === 'none') {
            $query->whereNotExists(function (QueryBuilder $subquery): void {
                $subquery->selectRaw('1')
                    ->from('auth_sessions')
                    ->whereColumn('auth_sessions.user_id', 'users.id');
            });
        }

        $users = $query->get([
            'id', 'name', 'email', 'email_verified_at', 'locale', 'role', 'account_status',
            'password_enabled', 'deleted_at', 'created_at', 'updated_at',
        ]);

        if ($users->isEmpty()) {
            return [];
        }

        /** @var list<string> $userIds */
        $userIds = $users->pluck('id')->map(static fn (mixed $id): string => (string) $id)->values()->all();

        /** @var array<string, array{session_count: int, last_used_at: string|null}> $activeSessions */
        $activeSessions = [];
        $activeSessionRows = DB::table('auth_sessions')
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->selectRaw('user_id, COUNT(*) as session_count, MAX(last_used_at) as last_used_at')
            ->groupBy('user_id')
            ->get();

        foreach ($activeSessionRows as $row) {
            $activeSessions[(string) $row->user_id] = [
                'session_count' => is_numeric($row->session_count) ? (int) $row->session_count : 0,
                'last_used_at' => is_string($row->last_used_at) ? $row->last_used_at : null,
            ];
        }

        $providers = DB::table('auth_provider_identities')
            ->whereIn('user_id', $userIds)
            ->whereNull('revoked_at')
            ->get(['user_id', 'provider'])
            ->groupBy('user_id');

        /** @var list<array<string, mixed>> $accounts */
        $accounts = [];
        foreach ($users as $user) {
            $session = $activeSessions[(string) $user->getKey()] ?? null;
            $providerRows = $providers->get((string) $user->getKey(), collect());
            $accounts[] = [
                'id' => (string) $user->getKey(),
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'role' => (string) $user->role,
                'account_status' => (string) $user->account_status,
                'verified' => $user->email_verified_at !== null,
                'password_enabled' => (bool) $user->password_enabled,
                'locale' => (string) $user->locale,
                'deleted' => $user->deleted_at !== null,
                'providers' => $providerRows->pluck('provider')
                    ->map(static fn (mixed $name): string => (string) $name)
                    ->unique()->values()->all(),
                'active_session_count' => $session['session_count'] ?? 0,
                'last_activity_at' => $session === null || $session['last_used_at'] === null
                    ? null
                    : Carbon::parse($session['last_used_at'])->toIso8601String(),
                'created_at' => Carbon::parse((string) $user->created_at)->toIso8601String(),
                'updated_at' => Carbon::parse((string) $user->updated_at)->toIso8601String(),
            ];
        }

        return $accounts;
    }

    /** @return array<string, mixed>|null */
    public function account(string $userId): ?array
    {
        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            return null;
        }

        $providers = DB::table('auth_provider_identities')
            ->where('user_id', $user->getKey())
            ->orderBy('provider')
            ->get(['provider', 'linked_at', 'last_seen_at', 'revoked_at'])
            ->map(static fn (object $row): array => [
                'provider' => (string) $row->provider,
                'status' => $row->revoked_at === null ? 'linked' : 'revoked',
                'linked_at' => Carbon::parse((string) $row->linked_at)->toIso8601String(),
                'last_seen_at' => Carbon::parse((string) $row->last_seen_at)->toIso8601String(),
            ])->values()->all();

        $sessions = DB::table('auth_sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_used_at')
            ->limit(100)
            ->get(['id', 'name', 'authenticated_at', 'last_used_at', 'expires_at', 'revoked_at', 'revoke_reason', 'created_at'])
            ->map(static function (object $row): array {
                $state = $row->revoked_at !== null
                    ? 'revoked'
                    : (Carbon::parse((string) $row->expires_at)->lte(now()) ? 'expired' : 'active');

                return [
                    'id' => (string) $row->id,
                    'name' => $row->name === null ? null : (string) $row->name,
                    'state' => $state,
                    'authenticated_at' => Carbon::parse((string) $row->authenticated_at)->toIso8601String(),
                    'last_used_at' => Carbon::parse((string) $row->last_used_at)->toIso8601String(),
                    'expires_at' => Carbon::parse((string) $row->expires_at)->toIso8601String(),
                    'revoked_at' => $row->revoked_at === null ? null : Carbon::parse((string) $row->revoked_at)->toIso8601String(),
                    'revoke_reason' => $row->revoke_reason === null ? null : (string) $row->revoke_reason,
                    'created_at' => Carbon::parse((string) $row->created_at)->toIso8601String(),
                ];
            })->values()->all();

        $securityEvents = DB::table('auth_security_events')
            ->where('user_id', $user->getKey())
            ->orderByDesc('created_at')
            ->limit(25)
            ->get(['event_type', 'created_at'])
            ->map(static fn (object $row): array => [
                'event_type' => (string) $row->event_type,
                'created_at' => Carbon::parse((string) $row->created_at)->toIso8601String(),
            ])->values()->all();

        return [
            'id' => (string) $user->getKey(),
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'role' => (string) $user->role,
            'account_status' => (string) $user->account_status,
            'verified' => $user->email_verified_at !== null,
            'password_enabled' => (bool) $user->password_enabled,
            'locale' => (string) $user->locale,
            'deleted_at' => $user->deleted_at === null ? null : Carbon::parse((string) $user->deleted_at)->toIso8601String(),
            'created_at' => Carbon::parse((string) $user->created_at)->toIso8601String(),
            'updated_at' => Carbon::parse((string) $user->updated_at)->toIso8601String(),
            'providers' => $providers,
            'sessions' => $sessions,
            'security_events' => $securityEvents,
        ];
    }

    /** @return list<array{role: string, admin_panel: bool, content_operations: bool, student_learning: bool, notes: string}> */
    public function roleMatrix(): array
    {
        return [
            ['role' => 'admin', 'admin_panel' => true, 'content_operations' => true, 'student_learning' => false, 'notes' => 'Fixed administrative role. Authorization remains server-side.'],
            ['role' => 'content_team', 'admin_panel' => true, 'content_operations' => true, 'student_learning' => false, 'notes' => 'Fixed content-operations role. Structural/security authority remains restricted.'],
            ['role' => 'student', 'admin_panel' => false, 'content_operations' => false, 'student_learning' => true, 'notes' => 'Learner role. No Admin access.'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function audits(string $targetUserId = ''): array
    {
        $query = DB::table('admin_account_operation_audits as audits')
            ->leftJoin('users as actors', 'actors.id', '=', 'audits.actor_id')
            ->join('users as targets', 'targets.id', '=', 'audits.target_user_id')
            ->orderByDesc('audits.occurred_at')
            ->limit(100);

        if ($targetUserId !== '') {
            $query->where('audits.target_user_id', $targetUserId);
        }

        $rows = $query->get([
            'audits.id', 'audits.action', 'audits.reason', 'audits.occurred_at',
            'actors.name as actor_name', 'targets.name as target_name',
        ]);

        /** @var list<array<string, mixed>> $audits */
        $audits = [];
        foreach ($rows as $row) {
            $audits[] = [
                'id' => (string) $row->id,
                'action' => (string) $row->action,
                'reason' => (string) $row->reason,
                'occurred_at' => Carbon::parse((string) $row->occurred_at)->toIso8601String(),
                'actor_name' => $row->actor_name === null ? 'Deleted operator' : (string) $row->actor_name,
                'target_name' => (string) $row->target_name,
            ];
        }

        return $audits;
    }

    /** @return array{revoked_sessions: int, before_active_sessions: int, after_active_sessions: int} */
    public function revokeAllSessions(User $actor, string $targetUserId, string $reason): array
    {
        $this->assertAdmin($actor);
        $reason = trim($reason);
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'revokeReason' => 'A specific reason between 8 and 500 characters is required.',
            ]);
        }

        return DB::transaction(function () use ($actor, $targetUserId, $reason): array {
            $target = User::query()->lockForUpdate()->find($targetUserId);
            if (! $target instanceof User) {
                throw ValidationException::withMessages(['selectedUserId' => 'The selected account no longer exists.']);
            }

            $beforeActive = $this->activeSessionCount($target);
            $before = $this->safeAuditState($target, $beforeActive);

            $this->authLifecycle->revokeAllSessions($target, 'admin_security_recovery');

            $afterActive = $this->activeSessionCount($target);
            $after = $this->safeAuditState($target, $afterActive);
            $now = now();

            DB::table('admin_account_operation_audits')->insert([
                'id' => (string) Str::ulid(),
                'actor_id' => $actor->getKey(),
                'target_user_id' => $target->getKey(),
                'action' => 'sessions.revoke_all',
                'reason' => $reason,
                'before' => json_encode($before, JSON_THROW_ON_ERROR),
                'after' => json_encode($after, JSON_THROW_ON_ERROR),
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return [
                'revoked_sessions' => max(0, $beforeActive - $afterActive),
                'before_active_sessions' => $beforeActive,
                'after_active_sessions' => $afterActive,
            ];
        });
    }

    private function activeSessionCount(User $user): int
    {
        return DB::table('auth_sessions')
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->count();
    }

    private function assertAdmin(User $actor): void
    {
        if ((string) $actor->role !== 'admin' || (string) $actor->account_status !== 'active' || $actor->deleted_at !== null) {
            throw new AuthorizationException('Only an active Admin account may perform sensitive account operations.');
        }
    }

    /** @return array{account_status: string, role: string, verified: bool, password_enabled: bool, active_session_count: int} */
    private function safeAuditState(User $user, int $activeSessions): array
    {
        return [
            'account_status' => (string) $user->account_status,
            'role' => (string) $user->role,
            'verified' => $user->email_verified_at !== null,
            'password_enabled' => (bool) $user->password_enabled,
            'active_session_count' => $activeSessions,
        ];
    }
}
