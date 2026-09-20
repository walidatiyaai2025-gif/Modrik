<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ParentAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ParentAnalyticsController extends Controller
{
    public function __construct(private readonly ParentAnalyticsService $analytics) {}

    public function children(Request $request): JsonResponse
    {
        return ApiResponse::success($request, [
            'children' => $this->analytics->children($this->user($request)),
        ]);
    }

    public function show(Request $request, string $childId): JsonResponse
    {
        if (! Str::isUlid($childId)) {
            throw new ApiProblemException(
                404,
                'RESOURCE_NOT_FOUND',
                'Resource not found',
                'The child analytics resource is unavailable.',
            );
        }

        return ApiResponse::success(
            $request,
            $this->analytics->snapshot($this->user($request), $childId),
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(
                401,
                'AUTHENTICATION_REQUIRED',
                'Authentication required',
                'A valid authenticated session is required.',
            );
        }

        return $user;
    }
}
