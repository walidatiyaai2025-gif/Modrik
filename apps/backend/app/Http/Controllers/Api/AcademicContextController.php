<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AcademicContextService;
use App\Services\IdempotencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AcademicContextController extends Controller
{
    public function __construct(
        private readonly AcademicContextService $contexts,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function activate(Request $request): JsonResponse
    {
        $trackId = $this->trackId($request);

        return $this->idempotency->execute($request, 'academic-context.activate', function () use ($request, $trackId): array {
            $context = $this->contexts->activate($this->user($request), $trackId);

            return [
                'status' => 201,
                'body' => ApiResponse::body($request, $context),
                'headers' => ['Location' => '/v1/academic-context'],
            ];
        });
    }

    public function reset(Request $request): JsonResponse
    {
        $trackId = $this->trackId($request);

        return $this->idempotency->execute($request, 'academic-context.reset', function () use ($request, $trackId): array {
            $context = $this->contexts->reset($this->user($request), $trackId);

            return ['status' => 200, 'body' => ApiResponse::body($request, $context)];
        });
    }

    private function trackId(Request $request): string
    {
        $payload = $request->json()->all();
        $unexpected = array_values(array_diff(array_keys($payload), ['academic_track_id']));
        if ($unexpected !== []) {
            $field = (string) $unexpected[0];
            throw $this->validation('/'.$field, 'FIELD_NOT_ALLOWED', "{$field} is not client writable.");
        }

        $trackId = $payload['academic_track_id'] ?? null;
        if (! is_string($trackId) || ! Str::isUlid($trackId)) {
            throw $this->validation('/academic_track_id', 'ACADEMIC_TRACK_ID_INVALID', 'academic_track_id must be a valid ULID.');
        }

        return $trackId;
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
