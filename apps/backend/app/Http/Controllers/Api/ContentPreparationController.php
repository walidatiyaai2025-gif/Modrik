<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ContentPreparationService;
use App\Services\IdempotencyService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class ContentPreparationController extends Controller
{
    public function __construct(
        private readonly ContentPreparationService $preparation,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function create(Request $request): JsonResponse
    {
        return $this->idempotency->execute($request, 'content-preparation.create', function () use ($request): array {
            $created = $this->preparation->create($this->user($request), $request->json()->all());

            return [
                'status' => 201,
                'body' => ApiResponse::body($request, $created),
                'headers' => ['Location' => '/v1/admin/preparation-requests/'.$created['preparation_request_id']],
            ];
        });
    }

    public function validateImport(Request $request): JsonResponse
    {
        if ($request->request->all() !== [] || count($request->allFiles()) !== 1) {
            throw new ApiProblemException(422, 'VALIDATION_FAILED', 'Request validation failed', 'The multipart request must contain only the archive file.');
        }
        $archive = $request->file('archive');
        if (! $archive instanceof UploadedFile || ! $archive->isValid()) {
            throw new ApiProblemException(422, 'VALIDATION_FAILED', 'Request validation failed', 'A valid archive upload is required.');
        }

        return $this->idempotency->execute($request, 'content-preparation.validate-import', function () use ($request, $archive): array {
            $result = $this->preparation->stage($this->user($request), $archive);
            if ($result['accepted']) {
                return ['status' => 202, 'body' => ApiResponse::body($request, $result['data'])];
            }

            $problem = new ApiProblemException(
                422,
                'CONTENT_PREPARATION_IMPORT_REJECTED',
                'Content preparation import rejected',
                'The returned archive failed validation and no official content was published.',
                errors: $result['errors'],
            );

            return [
                'status' => 422,
                'body' => ApiResponse::problemBody($request, $problem),
                'headers' => ['Content-Type' => 'application/problem+json'],
            ];
        });
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
