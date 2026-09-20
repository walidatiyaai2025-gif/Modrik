<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentAdaptiveStudyReadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StudentAdaptiveStudyController extends Controller
{
    public function __construct(private readonly StudentAdaptiveStudyReadService $adaptiveStudy) {}

    public function show(Request $request): JsonResponse
    {
        if ($request->query('user_id') !== null || $request->query('academic_context_id') !== null) {
            throw new ApiProblemException(
                422,
                'VALIDATION_FAILED',
                'Request validation failed',
                'Student adaptive scope is derived from the authenticated user and active academic context.',
                false,
                [[
                    'pointer' => '/query',
                    'code' => 'BACKEND_OWNED_SCOPE',
                    'message' => 'user_id and academic_context_id are not accepted.',
                ]],
            );
        }

        return ApiResponse::success($request, $this->adaptiveStudy->snapshot($this->user($request)));
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(
                401,
                'AUTHENTICATION_REQUIRED',
                'Authentication required',
                'A valid session is required.',
            );
        }

        return $user;
    }
}
