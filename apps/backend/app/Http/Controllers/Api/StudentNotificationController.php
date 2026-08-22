<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiProblemException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\StudentNotificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class StudentNotificationController extends Controller
{
    public function __construct(private readonly StudentNotificationService $notifications) {}

    public function index(Request $request): JsonResponse
    {
        return ApiResponse::success($request, $this->notifications->inbox($this->user($request)));
    }

    public function read(Request $request, string $notificationId): JsonResponse
    {
        if (! Str::isUlid($notificationId)) {
            throw $this->notFound();
        }

        $notification = $this->notifications->markRead($this->user($request), $notificationId);
        if ($notification === null) {
            throw $this->notFound();
        }

        return ApiResponse::success($request, $notification);
    }

    public function readAll(Request $request): JsonResponse
    {
        $updatedCount = $this->notifications->markAllRead($this->user($request));

        return ApiResponse::success($request, [
            'updated_count' => $updatedCount,
            'unread_count' => 0,
        ]);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ApiProblemException(401, 'AUTHENTICATION_REQUIRED', 'Authentication required', 'A valid session is required.');
        }

        return $user;
    }

    private function notFound(): ApiProblemException
    {
        return new ApiProblemException(404, 'RESOURCE_NOT_FOUND', 'Resource not found', 'The notification was not found.');
    }
}
