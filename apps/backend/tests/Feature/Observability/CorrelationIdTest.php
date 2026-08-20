<?php

namespace Tests\Feature\Observability;

use App\Exceptions\ApiProblemException;
use App\Support\ApiResponse;
use App\Support\CorrelationId;
use Illuminate\Http\Request;
use Tests\TestCase;

final class CorrelationIdTest extends TestCase
{
    public function test_safe_client_correlation_id_is_echoed_on_response(): void
    {
        $correlationId = 'web-01J6MODRIK1234567890';

        $response = $this->withHeader(CorrelationId::HEADER, $correlationId)->get('/health');

        $response->assertOk();
        $response->assertHeader(CorrelationId::HEADER, $correlationId);
    }

    public function test_invalid_client_correlation_id_is_replaced_not_reflected(): void
    {
        $unsafe = str_repeat('x', CorrelationId::MAX_LENGTH + 1);

        $response = $this->withHeader(CorrelationId::HEADER, $unsafe)->get('/health');

        $response->assertOk();
        $resolved = (string) $response->headers->get(CorrelationId::HEADER);

        self::assertNotSame($unsafe, $resolved);
        self::assertTrue(CorrelationId::isValid($resolved));
        self::assertFalse(CorrelationId::isValid("unsafe\r\nheader"));
        self::assertFalse(CorrelationId::isValid('unsafe value with spaces'));
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
