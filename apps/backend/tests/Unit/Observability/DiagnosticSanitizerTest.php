<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\DiagnosticSanitizer;
use PHPUnit\Framework\TestCase;

final class DiagnosticSanitizerTest extends TestCase
{
    public function test_metadata_is_allowlist_first_and_sensitive_fields_never_survive(): void
    {
        $sanitizer = new DiagnosticSanitizer;
        $sentinel = 'SENTINEL_DO_NOT_CAPTURE_94';

        $metadata = $sanitizer->metadata([
            'method' => 'POST',
            'status' => 422,
            'response_class' => '4xx',
            'exception_class' => 'RuntimeException',
            'authorization' => $sentinel,
            'cookie' => $sentinel,
            'password' => $sentinel,
            'token' => $sentinel,
            'answer' => $sentinel,
            'question_text' => $sentinel,
            'content_body' => $sentinel,
            'email' => $sentinel,
            'arbitrary' => $sentinel,
        ]);

        self::assertSame('POST', $metadata['method']);
        self::assertSame(422, $metadata['status']);
        self::assertSame('4xx', $metadata['response_class']);
        self::assertSame('RuntimeException', $metadata['exception_class']);
        self::assertStringNotContainsString($sentinel, json_encode($metadata, JSON_THROW_ON_ERROR));
    }

    public function test_correlation_filter_text_is_never_diagnostic_audit_metadata(): void
    {
        $sanitizer = new DiagnosticSanitizer;

        self::assertSame([], $sanitizer->metadata([
            'filter_correlation_id' => 'filter-01J6MODRIK1234567890',
        ]));
        self::assertSame([], $sanitizer->metadata([
            'filter_correlation_id' => 'SENTINEL-password-value',
        ]));
        self::assertSame([], $sanitizer->metadata([
            'filter_correlation_id' => str_repeat('x', 97),
        ]));
    }

    public function test_strings_and_codes_are_deterministically_bounded(): void
    {
        $sanitizer = new DiagnosticSanitizer;
        $metadata = $sanitizer->metadata(['source' => str_repeat('x', 400)]);

        self::assertSame(256, strlen((string) $metadata['source']));
        self::assertSame('SAFE_CODE:01', $sanitizer->safeCode('SAFE_CODE:01'));
        self::assertNull($sanitizer->safeCode("unsafe code\n"));
        self::assertNull($sanitizer->safeCode('contains spaces'));
    }
}
