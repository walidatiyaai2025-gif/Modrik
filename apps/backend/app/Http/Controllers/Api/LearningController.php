<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LearningReadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LearningController extends Controller
{
    public function __construct(private readonly LearningReadService $learning) {}

    public function session(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->learning->session($this->user($request)));
    }

    public function academicContext(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->learning->academicContext($this->user($request)));
    }

    public function lesson(Request $request, string $lessonId): JsonResponse
    {
        if (! Str::isUlid($lessonId)) {
            throw new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The lesson identifier is invalid.');
        }

        return ApiResponse::success($request, $this->learning->lesson($this->user($request), $lessonId));
    }

    public function progress(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->learning->progress($this->user($request)));
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
