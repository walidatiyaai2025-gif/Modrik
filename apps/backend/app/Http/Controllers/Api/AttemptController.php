<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AttemptService;
use App\Services\IdempotencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttemptController extends Controller
{
    public function __construct(
        private readonly AttemptService $attempts,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function start(Request $request): JsonResponse
    {
        $payload = $this->payload($request, ['quiz_id']);
        $quizId = $payload['quiz_id'] ?? null;
        if (! is_string($quizId) || ! Str::isUlid($quizId)) {
            throw $this->validation('/quiz_id', 'QUIZ_ID_INVALID', 'quiz_id must be a valid ULID.');
        }

        return $this->idempotency->execute($request, 'attempt.start', function () use ($request, $quizId): array {
            $attempt = $this->attempts->start($this->user($request), $quizId);

            return [
                'status' => 201,
                'body' => ApiResponse::body($request, $attempt),
                'headers' => ['Location' => '/v1/attempts/'.$attempt['id']],
            ];
        });
    }

    public function show(Request $request, string $attemptId): JsonResponse
    {
        $this->assertUlid($attemptId, 'attempt');

        return ApiResponse::success($request, $this->attempts->attempt($this->user($request), $attemptId));
    }

    public function answer(Request $request, string $attemptId, string $attemptQuestionId): JsonResponse
    {
        $this->assertUlid($attemptId, 'attempt');
        $this->assertUlid($attemptQuestionId, 'attempt question');
        $payload = $this->payload($request, ['expected_revision', 'value']);
        $expectedRevision = $payload['expected_revision'] ?? null;
        if (! is_int($expectedRevision) || $expectedRevision < 0) {
            throw $this->validation('/expected_revision', 'EXPECTED_REVISION_INVALID', 'expected_revision must be an integer of zero or greater.');
        }
        if (! array_key_exists('value', $payload)) {
            throw $this->validation('/value', 'ANSWER_VALUE_REQUIRED', 'value is required.');
        }

        return $this->idempotency->execute($request, 'attempt.answer', function () use ($request, $attemptId, $attemptQuestionId, $expectedRevision, $payload): array {
            $answer = $this->attempts->recordAnswer(
                $this->user($request),
                $attemptId,
                $attemptQuestionId,
                $expectedRevision,
                $payload['value'],
            );

            return ['status' => 200, 'body' => ApiResponse::body($request, $answer)];
        });
    }

    public function submit(Request $request, string $attemptId): JsonResponse
    {
        $this->assertUlid($attemptId, 'attempt');
        $this->payload($request, []);

        return $this->idempotency->execute($request, 'attempt.submit', function () use ($request, $attemptId): array {
            $result = $this->attempts->submit($this->user($request), $attemptId);

            return ['status' => 200, 'body' => ApiResponse::body($request, $result)];
        });
    }

    /**
     * @param  list<string>  $allowedKeys
     * @return array<string, mixed>
     */
    private function payload(Request $request, array $allowedKeys): array
    {
        $payload = $request->json()->all();
        $unexpected = array_values(array_diff(array_keys($payload), $allowedKeys));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation('/'.$field, 'FIELD_NOT_ALLOWED', "{$field} is not client writable.");
        }

        return $payload;
    }

    private function assertUlid(string $value, string $label): void
    {
        if (! Str::isUlid($value)) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', "The {$label} identifier is invalid.");
        }
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

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
        }

        return $user;
    }
}
