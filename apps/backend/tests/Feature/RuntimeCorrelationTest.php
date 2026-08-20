<?php

namespace Tests\Feature;

use App\Support\CorrelationId;
use Tests\TestCase;

class RuntimeCorrelationTest extends TestCase
{
    public function test_server_generates_and_echoes_a_canonical_correlation_id(): void
    {
        $response = $this->getJson('/health');

        $response->assertOk();
        $correlationId = $response->headers->get(CorrelationId::HEADER);

        $this->assertIsString($correlationId);
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $correlationId);
    }

    public function test_valid_client_uuid_is_preserved_as_diagnostic_only_correlation_id(): void
    {
        $correlationId = '2f1c9b6e-7a8d-4f33-9a12-0123456789ab';

        $this->withHeader(CorrelationId::HEADER, $correlationId)
            ->getJson('/health')
            ->assertOk()
            ->assertHeader(CorrelationId::HEADER, $correlationId);
    }

    public function test_invalid_client_correlation_value_is_replaced_not_reflected(): void
    {
        $unsafe = 'not-a-safe correlation id bearer-secret-sentinel';
        $response = $this->withHeader(CorrelationId::HEADER, $unsafe)->getJson('/health');

        $response->assertOk();
        $replacement = $response->headers->get(CorrelationId::HEADER);

        $this->assertIsString($replacement);
        $this->assertNotSame($unsafe, $replacement);
        $this->assertMatchesRegularExpression('/\A[0-9A-HJKMNP-TV-Z]{26}\z/', $replacement);
    }

    public function test_rfc9457_failure_uses_the_same_safe_support_reference_and_response_header(): void
    {
        $correlationId = '018f47ac-badc-4e11-9abc-0123456789ab';

        $this->withHeader(CorrelationId::HEADER, $correlationId)
            ->postJson('/v1/auth/providers/google/login-intents', ['unexpected' => 'sensitive-sentinel'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertHeader(CorrelationId::HEADER, $correlationId)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('request_id', $correlationId);
    }
}
