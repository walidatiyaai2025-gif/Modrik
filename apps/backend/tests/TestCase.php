<?php

namespace Tests;

use App\Models\User;
use App\Services\AuthLifecycleService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\Request;
use ReflectionMethod;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** @var array<string, string> */
    private array $productionSessionTokens = [];

    /**
     * Legacy feature tests historically supplied a fixture bearer token to the
     * production middleware. Runtime fixture authentication no longer exists.
     * While those tests are migrated incrementally, translate that test-only
     * token into a real persisted AuthLifecycleService session for the seeded
     * user. No application middleware, config, route, or deployed environment
     * knows about this compatibility path.
     */
    public function withToken($token, $type = 'Bearer')
    {
        if (
            is_string($token)
            && str_contains($token, 'fixture-token')
            && config('modrik.fixture.enabled') === true
        ) {
            $userId = config('modrik.fixture.user_id');
            if (! is_string($userId) || $userId === '') {
                throw new RuntimeException('Legacy authenticated feature test must identify its seeded user.');
            }

            $token = $this->productionSessionTokenFor($userId);
        }

        return parent::withToken($token, $type);
    }

    protected function productionSessionTokenFor(string $userId): string
    {
        if (isset($this->productionSessionTokens[$userId])) {
            return $this->productionSessionTokens[$userId];
        }

        $user = User::query()->find($userId);
        if (! $user instanceof User) {
            throw new RuntimeException('Cannot issue a production-shaped test session for a missing user.');
        }

        if ((string) $user->getAttribute('account_status') !== 'active') {
            throw new RuntimeException('Cannot issue a production-shaped test session for an inactive user.');
        }

        $request = Request::create('/v1/auth/login', 'POST');
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $auth = app(AuthLifecycleService::class);
        $createSession = new ReflectionMethod($auth, 'createSession');
        $session = $createSession->invoke($auth, $user, $request, 'password');
        if (! is_array($session)) {
            throw new RuntimeException('Production session issuance returned an invalid test result.');
        }

        $rawToken = $session['token'] ?? null;
        if (! is_string($rawToken) || $rawToken === '') {
            throw new RuntimeException('Production session issuance did not return a bearer token.');
        }

        return $this->productionSessionTokens[$userId] = $rawToken;
    }
}
