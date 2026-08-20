<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuthLifecycleService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProviderAuthController extends Controller
{
    public function __construct(private readonly AuthLifecycleService $auth) {}

    public function loginIntent(Request $request, string $provider): JsonResponse
    {
        $this->assertEmptyBody($request);

        return ApiResponse::success($request, $this->auth->createProviderIntent($provider, 'login', null, $request), 201);
    }

    public function linkIntent(Request $request, string $provider): JsonResponse
    {
        $this->assertEmptyBody($request);

        return ApiResponse::success($request, $this->auth->createProviderIntent($provider, 'link', $this->user($request), $request), 201);
    }

    public function callback(Request $request, string $provider): JsonResponse
    {
        $payload = $request->json()->all();
        $unexpected = array_values(array_diff(array_keys($payload), ['state', 'id_token']));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation('/'.$field, 'FIELD_NOT_ALLOWED', $field.' is not accepted by this endpoint.');
        }
        $state = $this->requiredString($payload, 'state', 32, 160);
        $idToken = $this->requiredString($payload, 'id_token', 16, 16_384);
        $result = $this->auth->completeProviderIntent($provider, $state, $idToken, $request);

        if ($result['mode'] === 'link') {
            return ApiResponse::success($request, [
                'provider' => $result['provider'],
                'linked' => true,
                'account_id' => (string) $result['user']->getKey(),
            ]);
        }

        return ApiResponse::success($request, [
            'account' => $this->account($result['user']),
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'session' => $result['session'],
            'provider' => $result['provider'],
        ]);
    }

    public function unlink(Request $request, string $provider): Response
    {
        $this->assertEmptyBody($request);
        $this->auth->unlinkProvider($this->user($request), $provider, $this->sessionId($request));

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function account(User $user): array
    {
        $email = (string) $user->getAttribute('email');
        if (str_ends_with($email, '@accounts.invalid')) {
            $email = '';
        }

        return [
            'id' => (string) $user->getKey(),
            'email' => $email === '' ? null : $email,
            'email_verified' => $user->getAttribute('email_verified_at') !== null,
            'password_enabled' => (bool) $user->getAttribute('password_enabled'),
            'status' => (string) $user->getAttribute('account_status'),
        ];
    }

    private function assertEmptyBody(Request $request): void
    {
        if ($request->json()->all() !== []) {
            throw $this->validation('/', 'BODY_NOT_ALLOWED', 'This endpoint does not accept a request body.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $field, int $minimum, int $maximum): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value) || strlen($value) < $minimum || strlen($value) > $maximum) {
            throw $this->validation('/'.$field, 'FIELD_INVALID', $field.' has an invalid value.');
        }

        return $value;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid production account session is required.');
        }

        return $user;
    }

    private function sessionId(Request $request): string
    {
        $sessionId = $request->attributes->get('auth_session_id');
        if (! is_string($sessionId)) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid production account session is required.');
        }

        return $sessionId;
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
}
