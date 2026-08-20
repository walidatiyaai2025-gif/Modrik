<?php

namespace App\Services;

use App\Auth\ProviderIdentityClaims;
use App\Auth\ProviderIdentityVerifier;
use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Notifications\EmailVerificationTokenNotification;
use App\Notifications\PasswordRecoveryTokenNotification;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class AuthLifecycleService
{
    private static ?string $dummyPasswordHash = null;

    public function __construct(private readonly ProviderIdentityVerifier $providerVerifier) {}

    /** @return array{user: User, token: string, session: array<string, mixed>} */
    public function register(string $name, string $email, string $password, Request $request): array
    {
        $normalized = $this->normalizeEmail($email);
        $this->assertPassword($password);

        if (User::query()->where('email_normalized', $normalized)->exists()) {
            throw new ApiProblemException(409, 'EMAIL_UNAVAILABLE', 'Email unavailable', 'This email cannot be used for a new account.');
        }

        try {
            $user = DB::transaction(function () use ($name, $email, $normalized, $password): User {
                $user = User::query()->create([
                    'name' => trim($name),
                    'email' => trim($email),
                    'email_normalized' => $normalized,
                    'email_verified_at' => null,
                    'locale' => 'en',
                    'role' => 'student',
                    'account_status' => 'active',
                    'password' => $password,
                    'password_enabled' => true,
                ]);
                $this->recordEvent($user, 'account_registered');

                return $user;
            });
        } catch (QueryException) {
            throw new ApiProblemException(409, 'EMAIL_UNAVAILABLE', 'Email unavailable', 'This email cannot be used for a new account.');
        }

        $verificationToken = $this->replacePurposeToken($user, 'email_verification', $this->verificationTtlMinutes());
        $user->notify(new EmailVerificationTokenNotification($verificationToken));
        $session = $this->createSession($user, $request, 'registration');

        return ['user' => $user, 'token' => $session['token'], 'session' => $session['session']];
    }

    /** @return array{user: User, token: string, session: array<string, mixed>} */
    public function login(string $email, string $password, Request $request): array
    {
        $normalized = $this->normalizeEmail($email);
        $key = $this->rateKey('login', $normalized, $request);
        if (RateLimiter::tooManyAttempts($key, $this->loginMaxAttempts())) {
            throw new ApiProblemException(429, 'TOO_MANY_ATTEMPTS', 'Too many attempts', 'Authentication is temporarily unavailable for this client.');
        }

        $user = User::query()->where('email_normalized', $normalized)->first();
        $hash = $user instanceof User && (bool) $user->getAttribute('password_enabled')
            ? $user->getAuthPassword()
            : $this->dummyPasswordHash();
        $valid = is_string($hash) && Hash::check($password, $hash);
        $active = $user instanceof User && (string) $user->getAttribute('account_status') === 'active';

        if (! $valid || ! $active) {
            RateLimiter::hit($key, $this->loginDecaySeconds());
            throw new ApiProblemException(401, 'INVALID_CREDENTIALS', 'Invalid credentials', 'The supplied credentials are invalid.');
        }

        RateLimiter::clear($key);
        $session = $this->createSession($user, $request, 'password');
        $this->recordEvent($user, 'password_login', (string) $session['session']['id']);

        return ['user' => $user, 'token' => $session['token'], 'session' => $session['session']];
    }

    public function resendVerification(User $user, Request $request): void
    {
        if ($user->getAttribute('email_verified_at') !== null) {
            return;
        }
        $key = $this->rateKey('verification-resend', (string) $user->getKey(), $request);
        if (RateLimiter::tooManyAttempts($key, $this->resendMaxAttempts())) {
            throw new ApiProblemException(429, 'TOO_MANY_ATTEMPTS', 'Too many attempts', 'Verification delivery is temporarily rate limited.');
        }
        RateLimiter::hit($key, $this->resendDecaySeconds());
        $token = $this->replacePurposeToken($user, 'email_verification', $this->verificationTtlMinutes());
        $user->notify(new EmailVerificationTokenNotification($token));
        $this->recordEvent($user, 'email_verification_resent');
    }

    public function verifyEmail(string $rawToken): void
    {
        $row = $this->activeToken($rawToken, 'email_verification');
        if ($row === null) {
            throw $this->invalidToken();
        }

        DB::transaction(function () use ($row): void {
            $locked = DB::table('auth_tokens')->where('id', $row['id'])->lockForUpdate()->first();
            if ($locked === null) {
                throw $this->invalidToken();
            }
            /** @var array{id: string, user_id: string, consumed_at: ?string, revoked_at: ?string, expires_at: string} $token */
            $token = (array) $locked;
            if ($token['consumed_at'] !== null || $token['revoked_at'] !== null || Carbon::parse($token['expires_at'])->lte(now())) {
                throw $this->invalidToken();
            }
            $user = User::query()->lockForUpdate()->find($token['user_id']);
            if (! $user instanceof User || (string) $user->getAttribute('account_status') !== 'active') {
                throw $this->invalidToken();
            }
            DB::table('auth_tokens')->where('id', $token['id'])->update(['consumed_at' => now()]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $this->recordEvent($user, 'email_verified');
        });
    }

    public function requestPasswordRecovery(string $email, Request $request): void
    {
        $normalized = $this->normalizeEmail($email);
        $key = $this->rateKey('password-recovery', $normalized, $request);
        if (RateLimiter::tooManyAttempts($key, $this->recoveryMaxAttempts())) {
            return;
        }
        RateLimiter::hit($key, $this->recoveryDecaySeconds());

        $user = User::query()->where('email_normalized', $normalized)->first();
        if (! $user instanceof User
            || (string) $user->getAttribute('account_status') !== 'active'
            || $user->getAttribute('email_verified_at') === null) {
            return;
        }

        $token = $this->replacePurposeToken($user, 'password_reset', $this->recoveryTtlMinutes());
        $user->notify(new PasswordRecoveryTokenNotification($token));
        $this->recordEvent($user, 'password_recovery_requested');
    }

    public function resetPassword(string $rawToken, string $password): void
    {
        $this->assertPassword($password);
        $row = $this->activeToken($rawToken, 'password_reset');
        if ($row === null) {
            throw $this->invalidToken();
        }

        DB::transaction(function () use ($row, $password): void {
            $locked = DB::table('auth_tokens')->where('id', $row['id'])->lockForUpdate()->first();
            if ($locked === null) {
                throw $this->invalidToken();
            }
            /** @var array{id: string, user_id: string, consumed_at: ?string, revoked_at: ?string, expires_at: string} $token */
            $token = (array) $locked;
            if ($token['consumed_at'] !== null || $token['revoked_at'] !== null || Carbon::parse($token['expires_at'])->lte(now())) {
                throw $this->invalidToken();
            }
            $user = User::query()->lockForUpdate()->find($token['user_id']);
            if (! $user instanceof User || (string) $user->getAttribute('account_status') !== 'active') {
                throw $this->invalidToken();
            }
            $user->forceFill(['password' => $password, 'password_enabled' => true])->save();
            DB::table('auth_tokens')->where('id', $token['id'])->update(['consumed_at' => now()]);
            $this->revokePurposeTokens($user, 'password_reset', exceptId: $token['id']);
            $this->revokeAllSessions($user, 'password_reset');
            $this->recordEvent($user, 'password_reset');
        });
    }

    public function reauthenticate(User $user, string $sessionId, string $password): void
    {
        $hash = (bool) $user->getAttribute('password_enabled') ? $user->getAuthPassword() : null;
        if (! is_string($hash) || ! Hash::check($password, $hash)) {
            throw new ApiProblemException(401, 'INVALID_CREDENTIALS', 'Invalid credentials', 'The supplied credentials are invalid.');
        }
        DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['authenticated_at' => now(), 'last_used_at' => now(), 'updated_at' => now()]);
        $this->recordEvent($user, 'session_reauthenticated', $sessionId);
    }

    public function changePassword(User $user, string $sessionId, string $currentPassword, string $newPassword): void
    {
        $this->assertPassword($newPassword);
        $hash = (bool) $user->getAttribute('password_enabled') ? $user->getAuthPassword() : null;
        if (! is_string($hash) || ! Hash::check($currentPassword, $hash)) {
            throw new ApiProblemException(401, 'INVALID_CREDENTIALS', 'Invalid credentials', 'The supplied credentials are invalid.');
        }
        DB::transaction(function () use ($user, $sessionId, $newPassword): void {
            $locked = User::query()->lockForUpdate()->find((string) $user->getKey());
            if (! $locked instanceof User || (string) $locked->getAttribute('account_status') !== 'active') {
                throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
            }
            $locked->forceFill(['password' => $newPassword, 'password_enabled' => true])->save();
            $this->revokePurposeTokens($locked, 'password_reset');
            $this->revokeOtherSessions($locked, $sessionId, 'password_changed');
            DB::table('auth_sessions')->where('id', $sessionId)->update(['authenticated_at' => now(), 'updated_at' => now()]);
            $this->recordEvent($locked, 'password_changed', $sessionId);
        });
    }

    /** @return array<int, array<string, mixed>> */
    public function sessions(User $user, string $currentSessionId): array
    {
        $rows = DB::table('auth_sessions')
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('last_used_at')
            ->get(['id', 'name', 'authenticated_at', 'last_used_at', 'expires_at', 'created_at']);

        return $rows->map(static function (object $row) use ($currentSessionId): array {
            /** @var array{id: string, name: ?string, authenticated_at: string, last_used_at: string, expires_at: string, created_at: string} $session */
            $session = (array) $row;

            return [
                'id' => $session['id'],
                'name' => $session['name'],
                'authenticated_at' => Carbon::parse($session['authenticated_at'])->toIso8601String(),
                'last_used_at' => Carbon::parse($session['last_used_at'])->toIso8601String(),
                'expires_at' => Carbon::parse($session['expires_at'])->toIso8601String(),
                'created_at' => Carbon::parse($session['created_at'])->toIso8601String(),
                'is_current' => hash_equals($currentSessionId, $session['id']),
            ];
        })->values()->all();
    }

    public function revokeCurrentSession(User $user, string $sessionId): void
    {
        $this->revokeSessionById($user, $sessionId, 'logout');
        $this->recordEvent($user, 'session_revoked', $sessionId);
    }

    public function revokeOtherSessions(User $user, string $sessionId, string $reason = 'revoke_others'): void
    {
        DB::table('auth_sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $sessionId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason, 'updated_at' => now()]);
        $this->recordEvent($user, 'other_sessions_revoked', $sessionId);
    }

    public function revokeAllSessions(User $user, string $reason = 'revoke_all'): void
    {
        DB::table('auth_sessions')
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason, 'updated_at' => now()]);
        $this->recordEvent($user, 'all_sessions_revoked');
    }

    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $locked = User::query()->lockForUpdate()->find((string) $user->getKey());
            if (! $locked instanceof User || (string) $locked->getAttribute('account_status') !== 'active') {
                throw new ApiProblemException(409, 'ACCOUNT_NOT_ACTIVE', 'Account not active', 'The account is not in an active lifecycle state.');
            }
            $now = now();
            $tombstoneEmail = 'deleted+'.strtolower((string) $locked->getKey()).'@invalid.modrik.local';
            $locked->forceFill([
                'name' => 'Deleted account',
                'email' => $tombstoneEmail,
                'email_normalized' => $tombstoneEmail,
                'email_verified_at' => null,
                'password' => Str::random(64),
                'password_enabled' => false,
                'account_status' => 'deleted',
                'deleted_at' => $now,
            ])->save();

            DB::table('auth_sessions')->where('user_id', $locked->getKey())->whereNull('revoked_at')->update([
                'revoked_at' => $now,
                'revoke_reason' => 'account_deleted',
                'updated_at' => $now,
            ]);
            DB::table('auth_tokens')->where('user_id', $locked->getKey())->whereNull('revoked_at')->update(['revoked_at' => $now]);
            DB::table('auth_provider_intents')->where('user_id', $locked->getKey())->whereNull('consumed_at')->update(['consumed_at' => $now]);

            $identities = DB::table('auth_provider_identities')->where('user_id', $locked->getKey())->get(['id']);
            foreach ($identities as $identity) {
                /** @var array{id: string} $row */
                $row = (array) $identity;
                DB::table('auth_provider_identities')->where('id', $row['id'])->update([
                    'provider_subject' => 'deleted-'.$row['id'],
                    'provider_email_normalized' => null,
                    'provider_email_verified' => false,
                    'provider_email_is_relay' => false,
                    'revoked_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $this->recordEvent($locked, 'account_deleted');
        });
    }

    /** @return array{state: string, nonce: string, expires_at: string} */
    public function createProviderIntent(string $provider, string $purpose, ?User $user, Request $request): array
    {
        $provider = $this->provider($provider);
        if (! in_array($purpose, ['login', 'link'], true)) {
            throw $this->validation('/purpose', 'PROVIDER_PURPOSE_INVALID', 'Provider purpose must be login or link.');
        }
        if ($purpose === 'link' && ! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'Provider linking requires an authenticated production session.');
        }
        if ($purpose === 'login' && $user instanceof User) {
            throw $this->validation('/purpose', 'PROVIDER_PURPOSE_INVALID', 'Login provider intents must not bind an authenticated user.');
        }

        $key = $this->rateKey('provider-intent-'.$provider, $purpose.':'.($user?->getKey() ?? 'public'), $request);
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new ApiProblemException(429, 'TOO_MANY_ATTEMPTS', 'Too many attempts', 'Provider authentication is temporarily rate limited.');
        }
        RateLimiter::hit($key, 900);
        $state = 'modrik_o_'.bin2hex(random_bytes(32));
        $nonce = 'modrik_n_'.bin2hex(random_bytes(32));
        $expires = now()->addMinutes($this->providerIntentTtlMinutes());
        DB::table('auth_provider_intents')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user?->getKey(),
            'provider' => $provider,
            'purpose' => $purpose,
            'state_hash' => hash('sha256', $state),
            'nonce_hash' => hash('sha256', $nonce),
            'expires_at' => $expires,
            'consumed_at' => null,
            'created_at' => now(),
        ]);

        return ['state' => $state, 'nonce' => $nonce, 'expires_at' => $expires->toIso8601String()];
    }

    /** @return array{mode: string, user: User, token: ?string, session: ?array<string, mixed>, provider: string} */
    public function completeProviderIntent(string $provider, string $state, string $idToken, Request $request): array
    {
        $provider = $this->provider($provider);
        $intentObject = DB::table('auth_provider_intents')
            ->where('state_hash', hash('sha256', $state))
            ->where('provider', $provider)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first(['id', 'user_id', 'provider', 'purpose', 'nonce_hash', 'expires_at']);
        if ($intentObject === null) {
            throw new ApiProblemException(422, 'PROVIDER_INTENT_INVALID', 'Provider intent invalid', 'The provider authentication intent is invalid or expired.');
        }
        /** @var array{id: string, user_id: ?string, provider: string, purpose: string, nonce_hash: string, expires_at: string} $intent */
        $intent = (array) $intentObject;
        $claims = $this->providerVerifier->verify($provider, $idToken);
        $this->assertProviderClaims($provider, $intent['nonce_hash'], $claims);

        return DB::transaction(function () use ($intent, $claims, $request): array {
            $locked = DB::table('auth_provider_intents')->where('id', $intent['id'])->lockForUpdate()->first();
            if ($locked === null) {
                throw new ApiProblemException(422, 'PROVIDER_INTENT_INVALID', 'Provider intent invalid', 'The provider authentication intent is invalid or expired.');
            }
            /** @var array{id: string, user_id: ?string, purpose: string, consumed_at: ?string, expires_at: string} $fresh */
            $fresh = (array) $locked;
            if ($fresh['consumed_at'] !== null || Carbon::parse($fresh['expires_at'])->lte(now())) {
                throw new ApiProblemException(422, 'PROVIDER_INTENT_INVALID', 'Provider intent invalid', 'The provider authentication intent is invalid or expired.');
            }

            if ($fresh['purpose'] === 'link') {
                if (! is_string($fresh['user_id'])) {
                    throw new ApiProblemException(422, 'PROVIDER_INTENT_INVALID', 'Provider intent invalid', 'The provider authentication intent is invalid.');
                }
                $user = User::query()->lockForUpdate()->find($fresh['user_id']);
                if (! $user instanceof User || (string) $user->getAttribute('account_status') !== 'active') {
                    throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid account is required for provider linking.');
                }
                $this->linkProviderIdentity($user, $claims);
                DB::table('auth_provider_intents')->where('id', $fresh['id'])->update(['consumed_at' => now()]);
                $this->recordEvent($user, 'provider_linked', context: $claims->provider);

                return ['mode' => 'link', 'user' => $user, 'token' => null, 'session' => null, 'provider' => $claims->provider];
            }

            $user = $this->resolveProviderLogin($claims);
            DB::table('auth_provider_intents')->where('id', $fresh['id'])->update(['consumed_at' => now()]);
            $session = $this->createSession($user, $request, $claims->provider);
            $this->recordEvent($user, 'provider_login', (string) $session['session']['id'], $claims->provider);

            return ['mode' => 'login', 'user' => $user, 'token' => $session['token'], 'session' => $session['session'], 'provider' => $claims->provider];
        });
    }

    public function unlinkProvider(User $user, string $provider, string $currentSessionId): void
    {
        $provider = $this->provider($provider);
        DB::transaction(function () use ($user, $provider, $currentSessionId): void {
            $identityObject = DB::table('auth_provider_identities')
                ->where('user_id', $user->getKey())
                ->where('provider', $provider)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first(['id']);
            if ($identityObject === null) {
                throw new ApiProblemException(404, 'PROVIDER_IDENTITY_NOT_FOUND', 'Provider identity not found', 'No active linked identity exists for this provider.');
            }
            $otherProviderCount = DB::table('auth_provider_identities')
                ->where('user_id', $user->getKey())
                ->where('provider', '!=', $provider)
                ->whereNull('revoked_at')
                ->count();
            $hasPasswordRecovery = (bool) $user->getAttribute('password_enabled') && $user->getAttribute('email_verified_at') !== null;
            if (! $hasPasswordRecovery && $otherProviderCount === 0) {
                throw new ApiProblemException(409, 'LAST_RECOVERY_IDENTITY', 'Recovery identity required', 'Unlinking would leave the account without a usable recovery identity.');
            }
            /** @var array{id: string} $identity */
            $identity = (array) $identityObject;
            DB::table('auth_provider_identities')->where('id', $identity['id'])->update(['revoked_at' => now(), 'updated_at' => now()]);
            $this->revokeOtherSessions($user, $currentSessionId, 'provider_unlinked');
            $this->recordEvent($user, 'provider_unlinked', $currentSessionId, $provider);
        });
    }

    /** @return null|array{user: User, session_id: string} */
    public function authenticateProductionToken(?string $rawToken): ?array
    {
        if (! is_string($rawToken) || $rawToken === '') {
            return null;
        }
        $sessionObject = DB::table('auth_sessions')
            ->where('token_hash', hash('sha256', $rawToken))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first(['id', 'user_id', 'last_used_at']);
        if ($sessionObject === null) {
            return null;
        }
        /** @var array{id: string, user_id: string, last_used_at: string} $session */
        $session = (array) $sessionObject;
        $user = User::query()->find($session['user_id']);
        if (! $user instanceof User || (string) $user->getAttribute('account_status') !== 'active') {
            DB::table('auth_sessions')->where('id', $session['id'])->whereNull('revoked_at')->update([
                'revoked_at' => now(),
                'revoke_reason' => 'account_inactive',
                'updated_at' => now(),
            ]);

            return null;
        }
        if (Carbon::parse($session['last_used_at'])->lt(now()->subMinutes(5))) {
            DB::table('auth_sessions')->where('id', $session['id'])->update(['last_used_at' => now(), 'updated_at' => now()]);
        }

        return ['user' => $user, 'session_id' => $session['id']];
    }

    public function isRecentSession(User $user, string $sessionId): bool
    {
        $authenticatedAt = DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->value('authenticated_at');
        if (! is_string($authenticatedAt)) {
            return false;
        }

        return Carbon::parse($authenticatedAt)->gte(now()->subSeconds($this->recentSeconds()));
    }

    /** @return array{token: string, session: array<string, mixed>} */
    public function createSession(User $user, Request $request, string $name): array
    {
        $raw = 'modrik_s_'.bin2hex(random_bytes(32));
        $id = (string) Str::ulid();
        $now = now();
        $expires = $now->copy()->addMinutes($this->sessionTtlMinutes());
        DB::table('auth_sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'token_hash' => hash('sha256', $raw),
            'name' => mb_substr($name, 0, 80),
            'ip_hash' => $this->contextHash((string) ($request->ip() ?? 'unknown')),
            'user_agent_hash' => $this->contextHash((string) $request->userAgent()),
            'authenticated_at' => $now,
            'last_used_at' => $now,
            'expires_at' => $expires,
            'revoked_at' => null,
            'revoke_reason' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'token' => $raw,
            'session' => [
                'id' => $id,
                'name' => mb_substr($name, 0, 80),
                'authenticated_at' => $now->toIso8601String(),
                'last_used_at' => $now->toIso8601String(),
                'expires_at' => $expires->toIso8601String(),
                'is_current' => true,
            ],
        ];
    }

    public function normalizeEmail(string $email): string
    {
        $normalized = mb_strtolower(trim($email));
        if ($normalized === '' || strlen($normalized) > 255 || filter_var($normalized, FILTER_VALIDATE_EMAIL) === false) {
            throw $this->validation('/email', 'EMAIL_INVALID', 'email must be a valid address.');
        }

        return $normalized;
    }

    public function assertPassword(string $password): void
    {
        $length = mb_strlen($password);
        if ($length < 12 || $length > 128) {
            throw $this->validation('/password', 'PASSWORD_POLICY_FAILED', 'password must contain between 12 and 128 characters.');
        }
    }

    private function resolveProviderLogin(ProviderIdentityClaims $claims): User
    {
        $identityObject = DB::table('auth_provider_identities')
            ->where('provider', $claims->provider)
            ->where('provider_subject', $claims->subject)
            ->lockForUpdate()
            ->first(['id', 'user_id', 'revoked_at']);
        $providerEmail = $claims->emailVerified && is_string($claims->email) ? $this->normalizeEmail($claims->email) : null;
        if ($identityObject !== null) {
            /** @var array{id: string, user_id: string, revoked_at: ?string} $identity */
            $identity = (array) $identityObject;
            if ($identity['revoked_at'] !== null) {
                throw new ApiProblemException(409, 'PROVIDER_LINK_REQUIRED', 'Account linking required', 'Sign in to the existing account and explicitly link this provider.');
            }
            $user = User::query()->lockForUpdate()->find($identity['user_id']);
            if (! $user instanceof User || (string) $user->getAttribute('account_status') !== 'active') {
                throw new ApiProblemException(401, 'INVALID_CREDENTIALS', 'Invalid credentials', 'The supplied identity is invalid.');
            }
            DB::table('auth_provider_identities')->where('id', $identity['id'])->update([
                'provider_email_normalized' => $providerEmail,
                'provider_email_verified' => $claims->emailVerified,
                'provider_email_is_relay' => $this->isAppleRelay($claims->provider, $providerEmail),
                'last_seen_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        }

        if ($providerEmail !== null) {
            $collision = User::query()->where('email_normalized', $providerEmail)->where('account_status', 'active')->exists();
            if ($collision) {
                throw new ApiProblemException(409, 'PROVIDER_LINK_REQUIRED', 'Account linking required', 'Sign in to the existing account and explicitly link this provider.');
            }
        }

        $email = $providerEmail ?? 'provider-'.strtolower((string) Str::ulid()).'@accounts.invalid';
        try {
            $user = User::query()->create([
                'name' => 'MODRIK learner',
                'email' => $email,
                'email_normalized' => $email,
                'email_verified_at' => $providerEmail === null ? null : now(),
                'locale' => 'en',
                'role' => 'student',
                'account_status' => 'active',
                'password' => Str::random(64),
                'password_enabled' => false,
            ]);
        } catch (QueryException) {
            throw new ApiProblemException(409, 'PROVIDER_LINK_REQUIRED', 'Account linking required', 'Sign in to the existing account and explicitly link this provider.');
        }
        $this->insertProviderIdentity($user, $claims, $providerEmail);
        $this->recordEvent($user, 'provider_account_registered', context: $claims->provider);

        return $user;
    }

    private function linkProviderIdentity(User $user, ProviderIdentityClaims $claims): void
    {
        $identityObject = DB::table('auth_provider_identities')
            ->where('provider', $claims->provider)
            ->where('provider_subject', $claims->subject)
            ->lockForUpdate()
            ->first(['id', 'user_id', 'revoked_at']);
        $providerEmail = $claims->emailVerified && is_string($claims->email) ? $this->normalizeEmail($claims->email) : null;
        if ($identityObject !== null) {
            /** @var array{id: string, user_id: string, revoked_at: ?string} $identity */
            $identity = (array) $identityObject;
            if (! hash_equals((string) $user->getKey(), $identity['user_id'])) {
                throw new ApiProblemException(409, 'PROVIDER_IDENTITY_CONFLICT', 'Provider identity conflict', 'This provider identity is already bound to another account.');
            }
            DB::table('auth_provider_identities')->where('id', $identity['id'])->update([
                'provider_email_normalized' => $providerEmail,
                'provider_email_verified' => $claims->emailVerified,
                'provider_email_is_relay' => $this->isAppleRelay($claims->provider, $providerEmail),
                'last_seen_at' => now(),
                'revoked_at' => null,
                'updated_at' => now(),
            ]);

            return;
        }
        $this->insertProviderIdentity($user, $claims, $providerEmail);
    }

    private function insertProviderIdentity(User $user, ProviderIdentityClaims $claims, ?string $providerEmail): void
    {
        DB::table('auth_provider_identities')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'provider' => $claims->provider,
            'provider_subject' => $claims->subject,
            'provider_email_normalized' => $providerEmail,
            'provider_email_verified' => $claims->emailVerified,
            'provider_email_is_relay' => $this->isAppleRelay($claims->provider, $providerEmail),
            'linked_at' => now(),
            'last_seen_at' => now(),
            'revoked_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertProviderClaims(string $provider, string $nonceHash, ProviderIdentityClaims $claims): void
    {
        if (! hash_equals($provider, $claims->provider)
            || $claims->subject === ''
            || strlen($claims->subject) > 191
            || ! $claims->isCryptographicallyValid()
            || ! is_string($claims->nonce)
            || ! hash_equals($nonceHash, hash('sha256', $claims->nonce))) {
            throw new ApiProblemException(401, 'PROVIDER_ASSERTION_INVALID', 'Provider assertion invalid', 'The provider assertion failed cryptographic or nonce validation.');
        }
    }

    private function provider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, ['google', 'apple'], true)) {
            throw $this->validation('/provider', 'PROVIDER_INVALID', 'provider must be google or apple.');
        }

        return $provider;
    }

    private function isAppleRelay(string $provider, ?string $email): bool
    {
        return $provider === 'apple' && is_string($email) && str_ends_with($email, '@privaterelay.appleid.com');
    }

    /** @return null|array{id: string, user_id: string} */
    private function activeToken(string $rawToken, string $purpose): ?array
    {
        if ($rawToken === '') {
            return null;
        }
        $row = DB::table('auth_tokens')
            ->where('token_hash', hash('sha256', $rawToken))
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->first(['id', 'user_id']);
        if ($row === null) {
            return null;
        }
        /** @var array{id: string, user_id: string} $token */
        $token = (array) $row;

        return $token;
    }

    private function replacePurposeToken(User $user, string $purpose, int $ttlMinutes): string
    {
        $this->revokePurposeTokens($user, $purpose);
        $raw = match ($purpose) {
            'email_verification' => 'modrik_v_'.bin2hex(random_bytes(32)),
            'password_reset' => 'modrik_r_'.bin2hex(random_bytes(32)),
            default => 'modrik_t_'.bin2hex(random_bytes(32)),
        };
        DB::table('auth_tokens')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'consumed_at' => null,
            'revoked_at' => null,
            'created_at' => now(),
        ]);

        return $raw;
    }

    private function revokePurposeTokens(User $user, string $purpose, ?string $exceptId = null): void
    {
        $query = DB::table('auth_tokens')
            ->where('user_id', $user->getKey())
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->whereNull('revoked_at');
        if (is_string($exceptId)) {
            $query->where('id', '!=', $exceptId);
        }
        $query->update(['revoked_at' => now()]);
    }

    private function revokeSessionById(User $user, string $sessionId, string $reason): void
    {
        DB::table('auth_sessions')
            ->where('id', $sessionId)
            ->where('user_id', $user->getKey())
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'revoke_reason' => $reason, 'updated_at' => now()]);
    }

    private function recordEvent(User $user, string $eventType, ?string $sessionId = null, ?string $context = null): void
    {
        DB::table('auth_security_events')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $user->getKey(),
            'session_id' => $sessionId,
            'event_type' => $eventType,
            'context_hash' => $context === null ? null : $this->contextHash($context),
            'created_at' => now(),
        ]);
    }

    private function rateKey(string $scope, string $subject, Request $request): string
    {
        return 'modrik:auth:'.$scope.':'.$this->contextHash($subject.'|'.(string) ($request->ip() ?? 'unknown'));
    }

    private function contextHash(string $value): string
    {
        $secret = (string) config('modrik.auth.hash_secret');
        if ($secret === '') {
            return hash('sha256', $value);
        }

        return hash_hmac('sha256', $value, $secret);
    }

    private function dummyPasswordHash(): string
    {
        if (! is_string(self::$dummyPasswordHash)) {
            self::$dummyPasswordHash = Hash::make('modrik-enumeration-resistant-dummy-password');
        }

        return self::$dummyPasswordHash;
    }

    private function invalidToken(): ApiProblemException
    {
        return new ApiProblemException(422, 'TOKEN_INVALID_OR_EXPIRED', 'Token invalid or expired', 'The one-time token is invalid or expired.');
    }

    private function validation(string $pointer, string $code, string $detail): ApiProblemException
    {
        return new ApiProblemException(
            422,
            'VALIDATION_FAILED',
            'Request validation failed',
            $detail,
            errors: [['pointer' => $pointer, 'code' => $code, 'message' => $detail]],
        );
    }

    private function sessionTtlMinutes(): int
    {
        return max(5, (int) config('modrik.auth.session_ttl_minutes'));
    }

    private function recentSeconds(): int
    {
        return max(60, (int) config('modrik.auth.recent_seconds'));
    }

    private function verificationTtlMinutes(): int
    {
        return max(5, (int) config('modrik.auth.verification_ttl_minutes'));
    }

    private function recoveryTtlMinutes(): int
    {
        return max(5, (int) config('modrik.auth.recovery_ttl_minutes'));
    }

    private function providerIntentTtlMinutes(): int
    {
        return max(1, (int) config('modrik.auth.provider_intent_ttl_minutes'));
    }

    private function loginMaxAttempts(): int
    {
        return max(1, (int) config('modrik.auth.login_max_attempts'));
    }

    private function loginDecaySeconds(): int
    {
        return max(60, (int) config('modrik.auth.login_decay_seconds'));
    }

    private function resendMaxAttempts(): int
    {
        return max(1, (int) config('modrik.auth.resend_max_attempts'));
    }

    private function resendDecaySeconds(): int
    {
        return max(60, (int) config('modrik.auth.resend_decay_seconds'));
    }

    private function recoveryMaxAttempts(): int
    {
        return max(1, (int) config('modrik.auth.recovery_max_attempts'));
    }

    private function recoveryDecaySeconds(): int
    {
        return max(60, (int) config('modrik.auth.recovery_decay_seconds'));
    }
}
