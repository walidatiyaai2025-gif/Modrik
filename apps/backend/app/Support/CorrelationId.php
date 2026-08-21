<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public const ATTRIBUTE = 'modrik_request_id';

    public const MAX_LENGTH = 96;

    /** @var list<string> */
    private const SENSITIVE_CLIENT_MARKERS = [
        'authorization',
        'bearer',
        'cookie',
        'password',
        'secret',
        'session',
        'token',
    ];

    public static function forRequest(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);
        if (is_string($existing) && self::isValid($existing)) {
            return $existing;
        }

        $incoming = $request->headers->get(self::HEADER);
        $correlationId = is_string($incoming) && self::isSafeClientValue($incoming)
            ? $incoming
            : (string) Str::ulid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);

        return $correlationId;
    }

    public static function isValid(string $value): bool
    {
        $length = strlen($value);

        return $length >= 16
            && $length <= self::MAX_LENGTH
            && preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $value) === 1;
    }

    public static function isSafeClientValue(string $value): bool
    {
        if (! self::isValid($value)) {
            return false;
        }

        $normalized = strtolower($value);

        foreach (self::SENSITIVE_CLIENT_MARKERS as $marker) {
            if (str_contains($normalized, $marker)) {
                return false;
            }
        }

        return true;
    }
}
