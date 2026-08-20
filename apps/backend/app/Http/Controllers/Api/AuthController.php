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

final class AuthController extends Controller
{
    public function __construct(private readonly AuthLifecycleService $auth) {}

    public function register(Request $request): JsonResponse
    {
        $payload = $this->payload($request, ['name', 'email', 'password']);
        $name = $this->requiredString($payload, 'name', 2, 100);
        $email = $this->requiredString($payload, 'email', 3, 255);
        $password = $this->requiredString($payload, 'password', 1, 128);
        $result = $this->auth->register($name, $email, $password, $request);

        return ApiResponse::success($request, $this->authPayload($result['user'], $result['token'], $result['session']), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $payload = $this->payload($request, ['email', 'password']);
        $email = $this->requiredString($payload, 'email', 3, 255);
        $password = $this->requiredString($payload, 'password', 1, 128);
        $result = $this->auth->login($email, $password, $request);

        return ApiResponse::success($request, $this->authPayload($result['user'], $result['token'], $result['session']));
    }

    public function verifyEmail(Request $request): Response
    {
        $payload = $this->payload($request, ['token']);
        $this->auth->verifyEmail($this->requiredString($payload, 'token', 16, 160));

        return response()->noContent();
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $this->payload($request, []);
        $this->auth->resendVerification($this->user($request), $request);

        return ApiResponse::success($request, ['status' => 'accepted'], 202);
    }

    public function requestRecovery(Request $request): JsonResponse
    {
        $payload = $this->payload($request, ['email']);
        $email = $this->requiredString($payload, 'email', 3, 255);
        $this->auth->requestPasswordRecovery($email, $request);

        return ApiResponse::success($request, ['status' => 'accepted'], 202);
    }

    public function resetPassword(Request $request): Response
    {
        $payload = $this->payload($request, ['token', 'password']);
        $token = $this->requiredString($payload, 'token', 16, 160);
        $password = $this->requiredString($payload, 'password', 1, 128);
        $this->auth->resetPassword($token, $password);

        return response()->noContent();
    }

    public function reauthenticate(Request $request): Response
    {
        $payload = $this->payload($request, ['password']);
        $password = $this->requiredString($payload, 'password', 1, 128);
        $this->auth->reauthenticate($this->user($request), $this->sessionId($request), $password);

        return response()->noContent();
    }

    public function changePassword(Request $request): Response
    {
        $payload = $this->payload($request, ['current_password', 'new_password']);
        $current = $this->requiredString($payload, 'current_password', 1, 128);
        $new = $this->requiredString($payload, 'new_password', 1, 128);
        $this->auth->changePassword($this->user($request), $this->sessionId($request), $current, $new);

        return response()->noContent();
    }

    public function sessions(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'sessions' => $this->auth->sessions($this->user($request), $this->sessionId($request)),
        ]);
    }

    public function logoutCurrent(Request $request): Response
    {
        $this->auth->revokeCurrentSession($this->user($request), $this->sessionId($request));

        return response()->noContent();
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $this->auth->revokeOtherSessions($this->user($request), $this->sessionId($request));

        return response()->noContent();
    }

    public function revokeAllSessions(Request $request): Response
    {
        $this->auth->revokeAllSessions($this->user($request));

        return response()->noContent();
    }

    public function deleteAccount(Request $request): Response
    {
        $payload = $this->payload($request, ['confirmation']);
        if (($payload['confirmation'] ?? null) !== 'DELETE') {
            throw $this->validation('/confirmation', 'DELETION_CONFIRMATION_REQUIRED', 'confirmation must equal DELETE.');
        }
        $this->auth->deleteAccount($this->user($request));

        return response()->noContent();
    }

    /** @param array{user: User, token: string, session: array<string, mixed>} $result */
    public function unusedCompatibility(array $result): void
    {
        // This method intentionally has no route; it keeps the response shape annotation close to the controller contract.
    }

    /**
     * @param  array<string, mixed>  $session
     * @return array<string, mixed>
     */
    private function authPayload(User $user, string $token, array $session): array
    {
        $email = (string) $user->getAttribute('email');
        if (str_ends_with($email, '@accounts.invalid')) {
            $email = '';
        }

        return [
            'account' => [
                'id' => (string) $user->getKey(),
                'email' => $email === '' ? null : $email,
                'email_verified' => $user->getAttribute('email_verified_at') !== null,
                'password_enabled' => (bool) $user->getAttribute('password_enabled'),
                'status' => (string) $user->getAttribute('account_status'),
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'session' => $session,
        ];
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $allowed): array
    {
        $payload = $request->json()->all();
        $unexpected = array_values(array_diff(array_keys($payload), $allowed));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation('/'.$field, 'FIELD_NOT_ALLOWED', $field.' is not accepted by this endpoint.');
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function requiredString(array $payload, string $field, int $minimum, int $maximum): string
    {
        $value = $payload[$field] ?? null;
        if (! is_string($value) || mb_strlen($value) < $minimum || mb_strlen($value) > $maximum) {
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
