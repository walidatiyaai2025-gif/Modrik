<?php

namespace App\Support\Observability;

use App\Support\CorrelationId;

final class DiagnosticSanitizer
{
    /** @var list<string> */
    private const ALLOWED_METADATA_KEYS = [
        'method',
        'status',
        'response_class',
        'exception_class',
        'exception_fingerprint',
        'report_reference',
        'event_count',
        'source',
        'connectivity',
        'retryable',
        'replayed',
        'operation_reference',
        'filter_correlation_id',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, bool|float|int|string|null>
     */
    public function metadata(array $metadata): array
    {
        $sanitized = [];

        foreach (self::ALLOWED_METADATA_KEYS as $key) {
            if (! array_key_exists($key, $metadata) || $this->isSensitiveKey($key)) {
                continue;
            }

            $value = $metadata[$key];
            if ($key === 'filter_correlation_id') {
                if (is_string($value) && CorrelationId::isValid($value)) {
                    $sanitized[$key] = $value;
                }

                continue;
            }

            if (is_string($value)) {
                $sanitized[$key] = mb_substr($value, 0, 256);
            } elseif (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    public function safeCode(?string $value, int $maxLength = 96): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/D', $value) !== 1) {
            return null;
        }

        return mb_substr($value, 0, $maxLength);
    }

    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/password|token|authorization|cookie|secret|answer|question|content|email|phone|address/i', $key) === 1;
    }
}
