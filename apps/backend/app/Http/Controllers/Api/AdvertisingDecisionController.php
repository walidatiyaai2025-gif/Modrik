<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AdvertisingEligibilityService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvertisingDecisionController extends Controller
{
    public function __construct(private readonly AdvertisingEligibilityService $advertising) {}

    public function show(Request $request, string $placementCode): JsonResponse
    {
        if (preg_match('/^[a-z][a-z0-9_]{2,63}$/', $placementCode) !== 1) {
            throw new ApiProblemException(
                422,
                'VALIDATION_FAILED',
                'Request validation failed',
                'The advertising placement code is invalid.',
                errors: [[
                    'pointer' => '/placementCode',
                    'code' => 'ADVERTISING_PLACEMENT_INVALID',
                    'message' => 'Placement codes use 3–64 lowercase letters, digits, or underscores.',
                ]],
            );
        }

        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
        }

        return ApiResponse::success($request, $this->advertising->decide($user, $placementCode))
            ->header('Cache-Control', 'no-store, private');
    }
}
