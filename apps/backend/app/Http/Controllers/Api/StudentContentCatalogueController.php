<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentContentCatalogueService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentContentCatalogueController extends Controller
{
    public function __construct(private readonly StudentContentCatalogueService $catalogue) {}

    public function index(Request $request): JsonResponse
    {
        $subjectReference = $request->query('subject_reference');
        if ($subjectReference !== null
            && (! is_string($subjectReference)
                || preg_match('/^[A-Z0-9][A-Z0-9._:-]{2,99}$/', $subjectReference) !== 1)) {
            throw new ApiProblemException(
                422,
                'VALIDATION_FAILED',
                'Request validation failed',
                'subject_reference has an invalid value.',
                false,
                [['pointer' => '/subject_reference', 'code' => 'FIELD_INVALID', 'message' => 'subject_reference has an invalid value.']],
            );
        }

        return ApiResponse::success(
            $request,
            $this->catalogue->catalogue($this->user($request), $subjectReference),
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
