<?php

namespace Tests\Feature\Observability;

use App\Exceptions\ApiProblemException;
use App\Support\ApiResponse;
use App\Support\CorrelationId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CorrelationIdTest extends TestCase
{
    use RefreshDatabase;

    public function test_safe_client_correlation_id_is_echoed_on_response(): void
    {
        $correlationId = 'web-01J6MODRIK1234567890';

        $response = $this->withHeader(CorrelationId::HEADER, $correlationId)->get('/health');

        $response->assertOk();
        $response->assertHeader(CorrelationId::HEADER, $correlationId);
        self::assertTrue(CorrelationId::isSafeClientValue($correlationId));
    }

    public function test_invalid_client_correlation_id_is_replaced_not_reflected(): void
    {
        $unsafe = str_repeat('x', CorrelationId::MAX_LENGTH + 1);

        $response = $this->withHeader(CorrelationId::HEADER, $unsafe)->get('/health');

        $response->assertOk();
        $resolved = (string) $response->headers->get(CorrelationId::HEADER);

        self::assertNotSame($unsafe, $resolved);
        self::assertTrue(CorrelationId::isValid($resolved));
        self::assertTrue(CorrelationId::isSafeClientValue($resolved));
        self::assertFalse(CorrelationId::isValid("unsafe\r\nheader"));
        self::assertFalse(CorrelationId::isValid('unsafe value with spaces'));
    }

    public function test_syntactically_valid_secret_shaped_client_correlation_is_not_safe_for_acceptance(): void
    {
        $unsafe = 'SENTINEL-password-value';

        self::assertTrue(CorrelationId::isValid($unsafe));
        self::assertFalse(CorrelationId::isSafeClientValue($unsafe));

        foreach ([
            'authorization-SENTINEL-94',
            'bearer-SENTINEL-94',
            'cookie-SENTINEL-94',
            'provider-secret-SENTINEL-94',
            'session-SENTINEL-94',
            'access-token-SENTINEL-94',
        ] as $candidate) {
            self::assertTrue(CorrelationId::isValid($candidate), $candidate);
            self::assertFalse(CorrelationId::isSafeClientValue($candidate), $candidate);
        }
    }

    public function test_secret_shaped_client_correlation_is_replaced_before_durable_and_structured_diagnostics(): void
    {
        Route::get('/__test/observability/correlation-privacy', fn () => response()->json(['ok' => true]))
            ->name('observability.correlation-privacy');

        $sentinels = [
            'SENTINEL-password-value',
            'Bearer-SENTINEL-BACKEND-94',
            'session-SENTINEL-COOKIE-94',
            'provider-secret-SENTINEL-94',
            'access-token-SENTINEL-94',
        ];
        $logContexts = [];

        Log::shouldReceive('log')
            ->andReturnUsing(static function (string $level, string $message, array $context) use (&$logContexts): void {
                if ($message === 'modrik.runtime') {
                    $logContexts[] = $context;
                }
            });

        $resolvedIds = [];
        foreach ($sentinels as $sentinel) {
            $response = $this->withHeader(CorrelationId::HEADER, $sentinel)
                ->getJson('/__test/observability/correlation-privacy');

            $response->assertOk();
            $resolved = (string) $response->headers->get(CorrelationId::HEADER);
            $resolvedIds[] = $resolved;

            self::assertNotSame($sentinel, $resolved);
            self::assertTrue(CorrelationId::isValid($resolved));
            self::assertTrue(CorrelationId::isSafeClientValue($resolved));
            self::assertFalse(DB::table('runtime_diagnostic_events')->where('correlation_id', $sentinel)->exists());
            self::assertTrue(DB::table('runtime_diagnostic_events')
                ->where('correlation_id', $resolved)
                ->where('route', 'observability.correlation-privacy')
                ->exists());
        }

        self::assertNotEmpty($logContexts);
        $serializedLogs = json_encode($logContexts, JSON_THROW_ON_ERROR);

        foreach ($sentinels as $sentinel) {
            self::assertStringNotContainsString($sentinel, $serializedLogs);
        }
        foreach ($resolvedIds as $resolved) {
            self::assertStringContainsString($resolved, $serializedLogs);
        }
    }

    public function test_correlation_survives_auth_learning_and_admin_request_paths(): void
    {
        $cases = [
            ['POST', '/v1/auth/login'],
            ['GET', '/v1/session'],
            ['POST', '/v1/admin/preparation-requests'],
        ];

        foreach ($cases as $index => [$method, $uri]) {
            $correlationId = sprintf('path-%02d-01J6MODRIK123456789', $index);
            $response = $this->withHeader(CorrelationId::HEADER, $correlationId)
                ->json($method, $uri, []);

            self::assertLessThan(500, $response->getStatusCode(), $uri);
            $response->assertHeader(CorrelationId::HEADER, $correlationId);
        }
    }

    public function test_rfc9457_problem_keeps_request_id_and_exposes_safe_correlation_header(): void
    {
        $request = Request::create('/v1/example', 'GET');
        $request->headers->set(CorrelationId::HEADER, 'mobile-01J6MODRIK123456789');
        $problem = new ApiProblemException(409, 'EXAMPLE_CONFLICT', 'Conflict', 'Safe detail');

        $response = ApiResponse::problem($request, $problem);
        $body = $response->getData(true);

        self::assertSame('mobile-01J6MODRIK123456789', $body['request_id']);
        self::assertSame('mobile-01J6MODRIK123456789', $response->headers->get(CorrelationId::HEADER));
        self::assertSame('EXAMPLE_CONFLICT', $body['code']);
        self::assertSame(409, $body['status']);
    }
}
