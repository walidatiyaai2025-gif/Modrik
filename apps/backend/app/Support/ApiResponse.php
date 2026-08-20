<?php

namespace App\Support;

use App\Exceptions\ApiProblemException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ApiResponse
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     */
    public static function success(Request $request, array $data, int $status = 200): JsonResponse
    {
        return response()->json(self::body($request, $data), $status);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @return array{data: array<string, mixed>|list<mixed>, meta: array{request_id: string}}
     */
    public static function body(Request $request, array $data): array
    {
        return [
            'data' => $data,
            'meta' => ['request_id' => self::requestId($request)],
        ];
    }

    public static function problem(Request $request, ApiProblemException $exception): JsonResponse
    {
        return response()->json(
            self::problemBody($request, $exception),
            $exception->status,
            ['Content-Type' => 'application/problem+json'],
        );
    }

    /** @return array<string, mixed> */
    public static function problemBody(Request $request, ApiProblemException $exception): array
    {
        $body = [
            'type' => 'https://modrik.org/problems/'.strtolower($exception->problemCode),
            'title' => $exception->problemTitle,
            'status' => $exception->status,
            'detail' => $exception->getMessage(),
            'instance' => '/'.$request->path(),
            'code' => $exception->problemCode,
            'request_id' => self::requestId($request),
            'retryable' => $exception->retryable,
        ];

        if ($exception->errors !== []) {
            $body['errors'] = $exception->errors;
        }

        return $body;
    }

    public static function requestId(Request $request): string
    {
        return CorrelationId::assign($request);
    }
}
