<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class CorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public const ATTRIBUTE = 'modrik_request_id';

    private const MAXIMUM_LENGTH = 64;

    public static function assign(Request $request): string
    {
        $existing = $request->attributes->get(self::ATTRIBUTE);
        if (is_string($existing) && self::isValid($existing)) {
            return $existing;
        }

        $candidate = $request->headers->get(self::HEADER);
        $correlationId = is_string($candidate) && self::isValid($candidate)
            ? $candidate
            : (string) Str::ulid();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);

        return $correlationId;
    }

    public static function isValid(string $value): bool
    {
        $length = strlen($value);
        if ($length < 26 || $length > self::MAXIMUM_LENGTH) {
            return false;
        }

        $isUlid = preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/iD', $value) === 1;
        if ($isUlid) {
            return true;
        }

        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/iD', $value) === 1;
    }
}
