<?php

namespace App\Support;

final class DiagnosticSanitizer
{
    /** @var list<string> */
    private const METADATA_ALLOWLIST = [
        'event_name',
        'http_method',
        'response_class',
        'exception_class',
        'fingerprint',
        'source',
        'event_count',
        'filter_count',
        'window_minutes',
        'export_bytes',
        'retention_configured',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, bool|float|int|string>
     */
    public static function metadata(array $metadata): array
    {
        $sanitized = [];

        foreach (self::METADATA_ALLOWLIST as $key) {
            if (! array_key_exists($key, $metadata)) {
                continue;
            }

            $value = $metadata[$key];
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $sanitized[$key] = $value;

                continue;
            }

            if (! is_string($value)) {
                continue;
            }

            $value = self::text($value, 256);
            if ($value !== null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    public static function text(mixed $value, int $maximumLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
            return null;
        }

        return mb_substr($value, 0, max(1, $maximumLength));
    }

    public static function stableCode(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/\A[A-Z0-9][A-Z0-9_.:-]{0,99}\z/D', $value) !== 1) {
            return null;
        }

        return $value;
    }
}
