<?php

namespace Tests\Feature;

use App\Auth\ProviderIdentityClaims;
use App\Auth\ProviderIdentityVerifier;
use App\Models\User;
use App\Notifications\EmailVerificationTokenNotification;
use App\Notifications\PasswordRecoveryTokenNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'modrik.fixture.enabled' => false,
            'modrik.auth.login_max_attempts' => 8,
            'modrik.auth.login_decay_seconds' => 900,
            'modrik.auth.resend_max_attempts' => 3,
            'modrik.auth.resend_decay_seconds' => 900,
            'modrik.auth.recovery_max_attempts' => 3,
            'modrik.auth.recovery_decay_seconds' => 900,
            'modrik.auth.recent_seconds' => 600,
        ]);
    }

    public function test_password_registration_verification_and_session_revocation_are_backend_owned(): void
    {
        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Alice Learner',
            'email' => 'Alice@example.test',
            'password' => 'correct horse battery staple',
        ])->assertCreated()
            ->assertJsonPath('data.account.email', 'Alice@example.test')
            ->assertJsonPath('data.account.email_verified', false)
            ->assertJsonPath('data.token_type', 'Bearer');

        $registrationToken = (string) $registration->json('data.access_token');
        $user = User::query()->where('email_normalized', 'alice@example.test')->firstOrFail();
        $this->assertNotSame($registrationToken, DB::table('auth_sessions')->value('token_hash'));
        $this->assertSame(hash('sha256', $registrationToken), DB::table('auth_sessions')->value('token_hash'));

        $this->withToken($registrationToken)->postJson('/v1/attempts', [])->assertForbidden()
            ->assertJsonPath('code', 'EMAIL_VERIFICATION_REQUIRED');

        $verificationToken = $this->verificationToken($user);
        $this->assertNotSame($verificationToken, DB::table('auth_tokens')->where('purpose', 'email_verification')->value('token_hash'));
        $this->postJson('/v1/auth/email/verify', ['token' => $verificationToken])->assertNoContent();
        $this->assertNotNull($user->fresh()?->email_verified_at);

        $this->withToken($registrationToken)->getJson('/v1/session')->assertOk()
            ->assertJsonPath('data.user_id', (string) $user->getKey());

        $login = $this->postJson('/v1/auth/login', [
            'email' => 'alice@example.test',
            'password' => 'correct horse battery staple',
        ])->assertOk();
        $loginToken = (string) $login->json('data.access_token');

        $this->withToken($loginToken)->getJson('/v1/auth/sessions')->assertOk()->assertJsonCount(2, 'data.sessions');
        $this->withToken($loginToken)->deleteJson('/v1/auth/sessions/others')->assertNoContent();
        $this->withToken($registrationToken)->getJson('/v1/session')->assertUnauthorized();
        $this->withToken($loginToken)->getJson('/v1/session')->assertOk();

        $this->withToken($loginToken)->deleteJson('/v1/auth/sessions/current')->assertNoContent();
        $this->withToken($loginToken)->getJson('/v1/session')->assertUnauthorized();
    }

    public function test_fixture_token_is_never_a_production_authentication_fallback(): void
    {
        config([
            'modrik.fixture.enabled' => false,
            'modrik.fixture.bearer_token' => 'fixture-secret',
        ]);

        $this->withToken('fixture-secret')->getJson('/v1/session')->assertUnauthorized();
        $this->withToken('fixture-secret')->getJson('/v1/auth/sessions')->assertUnauthorized();
    }

    public function test_login_and_recovery_are_enumeration_resistant_and_rate_limited(): void
    {
        [$user] = $this->registerVerified('known@example.test', 'known-password-value');

        $known = $this->postJson('/v1/auth/login', [
            'email' => 'known@example.test',
            'password' => 'definitely-wrong-password',
        ])->assertUnauthorized();
        $unknown = $this->postJson('/v1/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'definitely-wrong-password',
        ])->assertUnauthorized();
        foreach (['code', 'title', 'detail'] as $path) {
            $this->assertSame($known->json($path), $unknown->json($path));
        }

        config(['modrik.auth.login_max_attempts' => 2]);
        $this->postJson('/v1/auth/login', ['email' => 'rate@example.test', 'password' => 'wrong-password-value'])->assertUnauthorized();
        $this->postJson('/v1/auth/login', ['email' => 'rate@example.test', 'password' => 'wrong-password-value'])->assertUnauthorized();
        $this->postJson('/v1/auth/login', ['email' => 'rate@example.test', 'password' => 'wrong-password-value'])
            ->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

        Notification::fake();
        config(['modrik.auth.recovery_max_attempts' => 1]);
        $knownRecovery = $this->postJson('/v1/auth/password/recovery', ['email' => 'known@example.test'])
            ->assertAccepted()->json('data');
        $unknownRecovery = $this->postJson('/v1/auth/password/recovery', ['email' => 'nobody@example.test'])
            ->assertAccepted()->json('data');
        $this->assertSame($knownRecovery, $unknownRecovery);
        $this->postJson('/v1/auth/password/recovery', ['email' => 'known@example.test'])->assertAccepted();
        Notification::assertSentToTimes($user, PasswordRecoveryTokenNotification::class, 1);
    }

    public function test_verification_resend_revokes_old_token_and_enforces_authenticated_rate_limit(): void
    {
        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Resend Learner',
            'email' => 'resend@example.test',
            'password' => 'resend-password-value',
        ])->assertCreated();
        $sessionToken = (string) $registration->json('data.access_token');
        $user = User::query()->where('email_normalized', 'resend@example.test')->firstOrFail();
        $oldToken = $this->verificationToken($user);

        Notification::fake();
        config(['modrik.auth.resend_max_attempts' => 1]);
        $this->withToken($sessionToken)->postJson('/v1/auth/email/verification')->assertAccepted();
        $newToken = $this->verificationToken($user);
        $this->assertNotSame($oldToken, $newToken);
        $this->withToken($sessionToken)->postJson('/v1/auth/email/verification')
            ->assertStatus(429)->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

        $this->postJson('/v1/auth/email/verify', ['token' => $oldToken])
            ->assertUnprocessable()->assertJsonPath('code', 'TOKEN_INVALID_OR_EXPIRED');
        $this->postJson('/v1/auth/email/verify', ['token' => $newToken])->assertNoContent();
    }

    public function test_password_reset_is_one_time_and_revokes_every_existing_session(): void
    {
        [$user, $firstToken] = $this->registerVerified('reset@example.test', 'before-reset-password');
        $secondLogin = $this->postJson('/v1/auth/login', [
            'email' => 'reset@example.test',
            'password' => 'before-reset-password',
        ])->assertOk();
        $secondToken = (string) $secondLogin->json('data.access_token');

        Notification::fake();
        $this->postJson('/v1/auth/password/recovery', ['email' => 'reset@example.test'])->assertAccepted();
        $resetToken = $this->recoveryToken($user);
        $this->assertNotSame($resetToken, DB::table('auth_tokens')->where('purpose', 'password_reset')->value('token_hash'));

        $this->postJson('/v1/auth/password/reset', [
            'token' => $resetToken,
            'password' => 'after-reset-password-value',
        ])->assertNoContent();
        $this->postJson('/v1/auth/password/reset', [
            'token' => $resetToken,
            'password' => 'another-reset-password',
        ])->assertUnprocessable()->assertJsonPath('code', 'TOKEN_INVALID_OR_EXPIRED');

        $this->withToken($firstToken)->getJson('/v1/session')->assertUnauthorized();
        $this->withToken($secondToken)->getJson('/v1/session')->assertUnauthorized();
        $this->postJson('/v1/auth/login', ['email' => 'reset@example.test', 'password' => 'before-reset-password'])->assertUnauthorized();
        $this->postJson('/v1/auth/login', ['email' => 'reset@example.test', 'password' => 'after-reset-password-value'])->assertOk();
    }

    public function test_sensitive_changes_require_recent_auth_and_deletion_anonymizes_identity_and_revokes_sessions(): void
    {
        [$user, $token] = $this->registerVerified('delete@example.test', 'delete-account-password');
        config(['modrik.auth.recent_seconds' => 60]);
        $this->travel(61)->seconds();

        $this->withToken($token)->deleteJson('/v1/auth/account', ['confirmation' => 'DELETE'])
            ->assertForbidden()->assertJsonPath('code', 'RECENT_AUTHENTICATION_REQUIRED');
        $this->withToken($token)->postJson('/v1/auth/reauthenticate', ['password' => 'delete-account-password'])->assertNoContent();
        $this->withToken($token)->deleteJson('/v1/auth/account', ['confirmation' => 'DELETE'])->assertNoContent();

        $fresh = $user->fresh();
        $this->assertInstanceOf(User::class, $fresh);
        $this->assertSame('deleted', $fresh->getAttribute('account_status'));
        $this->assertSame('Deleted account', $fresh->name);
        $this->assertStringContainsString('@invalid.modrik.local', $fresh->email);
        $this->assertDatabaseHas('auth_security_events', ['user_id' => $user->getKey(), 'event_type' => 'account_deleted']);
        $this->withToken($token)->getJson('/v1/session')->assertUnauthorized();
        $this->postJson('/v1/auth/login', ['email' => 'delete@example.test', 'password' => 'delete-account-password'])->assertUnauthorized();
    }

    public function test_provider_email_collision_requires_explicit_link_and_stable_subject_preserves_one_profile(): void
    {
        [$firstUser, $firstToken] = $this->registerVerified('linked@example.test', 'linked-account-password');
        [$secondUser, $secondToken] = $this->registerVerified('second@example.test', 'second-account-password');
        $fake = new FakeProviderIdentityVerifier;
        $this->app->instance(ProviderIdentityVerifier::class, $fake);

        $loginIntent = $this->postJson('/v1/auth/providers/google/login-intents')->assertCreated();
        $loginState = (string) $loginIntent->json('data.state');
        $loginNonce = (string) $loginIntent->json('data.nonce');
        $fake->claims['google-collision'] = $this->claims('google', 'google-subject-1', $loginNonce, 'linked@example.test', true);
        $this->postJson('/v1/auth/providers/google/callback', ['state' => $loginState, 'id_token' => 'google-collision'])
            ->assertConflict()->assertJsonPath('code', 'PROVIDER_LINK_REQUIRED');
        $this->assertSame(2, User::query()->count());

        $linkIntent = $this->withToken($firstToken)->postJson('/v1/auth/providers/google/link-intents')->assertCreated();
        $linkState = (string) $linkIntent->json('data.state');
        $linkNonce = (string) $linkIntent->json('data.nonce');
        $fake->claims['google-link'] = $this->claims('google', 'google-subject-1', $linkNonce, 'linked@example.test', true);
        $this->postJson('/v1/auth/providers/google/callback', ['state' => $linkState, 'id_token' => 'google-link'])
            ->assertOk()->assertJsonPath('data.account_id', (string) $firstUser->getKey());

        $appleLinkIntent = $this->withToken($firstToken)->postJson('/v1/auth/providers/apple/link-intents')->assertCreated();
        $appleState = (string) $appleLinkIntent->json('data.state');
        $appleNonce = (string) $appleLinkIntent->json('data.nonce');
        $fake->claims['apple-link'] = $this->claims('apple', 'apple-stable-subject', $appleNonce, 'relay-token@privaterelay.appleid.com', true);
        $this->postJson('/v1/auth/providers/apple/callback', ['state' => $appleState, 'id_token' => 'apple-link'])->assertOk();
        $this->assertDatabaseHas('auth_provider_identities', [
            'user_id' => $firstUser->getKey(),
            'provider' => 'apple',
            'provider_subject' => 'apple-stable-subject',
            'provider_email_is_relay' => true,
        ]);

        $appleLogin = $this->postJson('/v1/auth/providers/apple/login-intents')->assertCreated();
        $fake->claims['apple-hidden-later'] = $this->claims(
            'apple',
            'apple-stable-subject',
            (string) $appleLogin->json('data.nonce'),
            null,
            false,
        );
        $this->postJson('/v1/auth/providers/apple/callback', [
            'state' => (string) $appleLogin->json('data.state'),
            'id_token' => 'apple-hidden-later',
        ])->assertOk()->assertJsonPath('data.account.id', (string) $firstUser->getKey());
        $this->assertSame(2, User::query()->count());

        $secondLink = $this->withToken($secondToken)->postJson('/v1/auth/providers/apple/link-intents')->assertCreated();
        $fake->claims['apple-conflict'] = $this->claims(
            'apple',
            'apple-stable-subject',
            (string) $secondLink->json('data.nonce'),
            'second-relay@privaterelay.appleid.com',
            true,
        );
        $this->postJson('/v1/auth/providers/apple/callback', [
            'state' => (string) $secondLink->json('data.state'),
            'id_token' => 'apple-conflict',
        ])->assertConflict()->assertJsonPath('code', 'PROVIDER_IDENTITY_CONFLICT');
        $this->assertSame((string) $firstUser->getKey(), (string) DB::table('auth_provider_identities')->where('provider', 'apple')->value('user_id'));
        $this->assertNotSame((string) $firstUser->getKey(), (string) $secondUser->getKey());
    }

    public function test_provider_only_account_cannot_unlink_its_last_recovery_identity(): void
    {
        $fake = new FakeProviderIdentityVerifier;
        $this->app->instance(ProviderIdentityVerifier::class, $fake);
        $intent = $this->postJson('/v1/auth/providers/apple/login-intents')->assertCreated();
        $fake->claims['apple-provider-only'] = $this->claims(
            'apple',
            'provider-only-subject',
            (string) $intent->json('data.nonce'),
            null,
            false,
        );
        $login = $this->postJson('/v1/auth/providers/apple/callback', [
            'state' => (string) $intent->json('data.state'),
            'id_token' => 'apple-provider-only',
        ])->assertOk();
        $token = (string) $login->json('data.access_token');
        $accountId = (string) $login->json('data.account.id');

        $this->withToken($token)->deleteJson('/v1/auth/providers/apple')
            ->assertConflict()->assertJsonPath('code', 'LAST_RECOVERY_IDENTITY');
        $this->assertDatabaseHas('auth_provider_identities', [
            'user_id' => $accountId,
            'provider' => 'apple',
            'revoked_at' => null,
        ]);
    }

    public function test_provider_callback_fails_closed_until_external_verifier_is_configured(): void
    {
        $intent = $this->postJson('/v1/auth/providers/google/login-intents')->assertCreated();
        $this->postJson('/v1/auth/providers/google/callback', [
            'state' => (string) $intent->json('data.state'),
            'id_token' => 'synthetic-unverified-provider-token',
        ])->assertStatus(503)->assertJsonPath('code', 'PROVIDER_CONFIGURATION_PENDING');
        $this->assertDatabaseHas('auth_provider_intents', ['consumed_at' => null]);
    }

    /** @return array{User, string} */
    private function registerVerified(string $email, string $password): array
    {
        Notification::fake();
        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Verified Learner',
            'email' => $email,
            'password' => $password,
        ])->assertCreated();
        $user = User::query()->where('email_normalized', strtolower($email))->firstOrFail();
        $verificationToken = $this->verificationToken($user);
        $this->postJson('/v1/auth/email/verify', ['token' => $verificationToken])->assertNoContent();

        return [$user->fresh() ?? $user, (string) $registration->json('data.access_token')];
    }

    private function verificationToken(User $user): string
    {
        $notification = Notification::sent($user, EmailVerificationTokenNotification::class)->first();
        $this->assertInstanceOf(EmailVerificationTokenNotification::class, $notification);

        return $notification->token;
    }

    private function recoveryToken(User $user): string
    {
        $notification = Notification::sent($user, PasswordRecoveryTokenNotification::class)->first();
        $this->assertInstanceOf(PasswordRecoveryTokenNotification::class, $notification);

        return $notification->token;
    }

    private function claims(
        string $provider,
        string $subject,
        string $nonce,
        ?string $email,
        bool $emailVerified,
    ): ProviderIdentityClaims {
        return new ProviderIdentityClaims(
            provider: $provider,
            subject: $subject,
            issuer: 'synthetic-test-issuer',
            audience: 'synthetic-test-audience',
            expiresAt: time() + 3600,
            nonce: $nonce,
            email: $email,
            emailVerified: $emailVerified,
            signatureValidated: true,
            issuerValidated: true,
            audienceValidated: true,
        );
    }
}

final class FakeProviderIdentityVerifier implements ProviderIdentityVerifier
{
    /** @var array<string, ProviderIdentityClaims> */
    public array $claims = [];

    public function verify(string $provider, string $idToken): ProviderIdentityClaims
    {
        $claims = $this->claims[$idToken] ?? null;
        if (! $claims instanceof ProviderIdentityClaims) {
            return new ProviderIdentityClaims(
                provider: $provider,
                subject: 'invalid',
                issuer: 'invalid',
                audience: 'invalid',
                expiresAt: 0,
                nonce: null,
                email: null,
                emailVerified: false,
                signatureValidated: false,
                issuerValidated: false,
                audienceValidated: false,
            );
        }

        return $claims;
    }
}
