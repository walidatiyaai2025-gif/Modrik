<?php

namespace Tests\Feature\Observability;

use App\Filament\Pages\RuntimeInspector;
use App\Models\User;
use App\Support\CorrelationId;
use App\Support\Observability\DatabaseDiagnosticSink;
use App\Support\Observability\DiagnosticSanitizer;
use App\Support\Observability\DiagnosticSink;
use App\Support\Observability\RuntimeInspectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class RuntimeObservabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'observability.enabled' => true,
            'observability.inspector_enabled' => true,
            'observability.max_events' => 10,
            'observability.query_limit' => 20,
            'observability.export_max_events' => 3,
            'observability.export_max_bytes' => 4096,
        ]);
    }

    public function test_runtime_event_never_captures_sensitive_request_material(): void
    {
        Route::post('/__test/observability/privacy', fn () => response()->json(['ok' => true], 202))
            ->name('observability.privacy');

        $sentinels = [
            'SENTINEL_AUTH_94',
            'SENTINEL_COOKIE_94',
            'SENTINEL_PASSWORD_94',
            'SENTINEL_ANSWER_94',
            'SENTINEL_QUESTION_94',
            'SENTINEL_CONTENT_94',
        ];
        $correlationId = 'privacy-01J6MODRIK123456789';

        $response = $this
            ->withHeader(CorrelationId::HEADER, $correlationId)
            ->withHeader('Authorization', 'Bearer '.$sentinels[0])
            ->withCookie('modrik_session', $sentinels[1])
            ->postJson('/__test/observability/privacy', [
                'password' => $sentinels[2],
                'answer' => $sentinels[3],
                'question_text' => $sentinels[4],
                'content_body' => $sentinels[5],
            ]);

        $response->assertStatus(202)->assertHeader(CorrelationId::HEADER, $correlationId);
        $row = DB::table('runtime_diagnostic_events')->where('correlation_id', $correlationId)->first();
        self::assertNotNull($row);
        $serialized = json_encode((array) $row, JSON_THROW_ON_ERROR);
        foreach ($sentinels as $sentinel) {
            self::assertStringNotContainsString($sentinel, $serialized);
        }
        self::assertSame('application_log', $row->data_class);
        self::assertSame('observability.privacy', $row->route);
    }

    public function test_unhandled_exception_is_fingerprinted_without_persisting_message(): void
    {
        $sentinel = 'SENTINEL_EXCEPTION_MESSAGE_94';
        $correlationId = 'exception-01J6MODRIK12345678';
        Route::get('/__test/observability/exception', static function () use ($sentinel): never {
            throw new RuntimeException($sentinel);
        })->name('observability.exception');

        $response = $this->withHeader(CorrelationId::HEADER, $correlationId)
            ->getJson('/__test/observability/exception');

        $response->assertStatus(500)->assertHeader(CorrelationId::HEADER, $correlationId);
        $rows = DB::table('runtime_diagnostic_events')->where('correlation_id', $correlationId)->get();
        self::assertGreaterThanOrEqual(1, $rows->count());
        $serialized = json_encode($rows->map(static fn (object $row): array => (array) $row)->all(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($sentinel, $serialized);

        $exceptionRow = $rows->firstWhere('category', 'unhandled_exception');
        self::assertNotNull($exceptionRow);
        $metadata = json_decode((string) $exceptionRow->metadata, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('RuntimeException', $metadata['exception_class']);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', (string) $metadata['exception_fingerprint']);
    }

    public function test_throwing_diagnostic_sink_does_not_change_business_result(): void
    {
        $this->app->instance(DiagnosticSink::class, new class implements DiagnosticSink
        {
            public function write(array $event): void
            {
                throw new RuntimeException('diagnostic sink unavailable');
            }
        });

        Route::get('/__test/observability/fail-open', fn () => response()->json([
            'result' => 'business-result-preserved',
        ], 207))->name('observability.fail-open');

        $response = $this->getJson('/__test/observability/fail-open');

        $response->assertStatus(207)->assertJson(['result' => 'business-result-preserved']);
        self::assertTrue(CorrelationId::isValid((string) $response->headers->get(CorrelationId::HEADER)));
    }

    public function test_database_sink_is_bounded_and_keeps_newest_events(): void
    {
        $sink = new DatabaseDiagnosticSink(new DiagnosticSanitizer);

        for ($index = 0; $index < 12; $index++) {
            $sink->write($this->eventRow($index));
        }

        self::assertSame(10, DB::table('runtime_diagnostic_events')->count());
        self::assertFalse(DB::table('runtime_diagnostic_events')->where('stable_code', 'EVENT_00')->exists());
        self::assertFalse(DB::table('runtime_diagnostic_events')->where('stable_code', 'EVENT_01')->exists());
        self::assertTrue(DB::table('runtime_diagnostic_events')->where('stable_code', 'EVENT_11')->exists());
    }

    public function test_inspector_export_is_bounded_sanitized_and_correlation_filterable(): void
    {
        $sink = new DatabaseDiagnosticSink(new DiagnosticSanitizer);
        $targetCorrelation = 'export-01J6MODRIK1234567890';
        $otherCorrelation = 'other-01J6MODRIK12345678901';

        for ($index = 0; $index < 5; $index++) {
            $row = $this->eventRow($index);
            $row['correlation_id'] = $targetCorrelation;
            $row['metadata'] = [
                'source' => str_repeat('safe', 50),
                'password' => 'SENTINEL_EXPORT_PASSWORD_94',
            ];
            $sink->write($row);
        }
        $other = $this->eventRow(20);
        $other['correlation_id'] = $otherCorrelation;
        $sink->write($other);

        $bundle = app(RuntimeInspectorService::class)->exportBundle([
            'correlation_id' => $targetCorrelation,
            'hours' => 24,
        ]);
        $decoded = json_decode($bundle['json'], true, flags: JSON_THROW_ON_ERROR);

        self::assertLessThanOrEqual(3, $bundle['event_count']);
        self::assertLessThanOrEqual(4096, strlen($bundle['json']));
        self::assertStringNotContainsString('SENTINEL_EXPORT_PASSWORD_94', $bundle['json']);
        foreach ($decoded['events'] as $event) {
            self::assertSame($targetCorrelation, $event['correlation_id']);
        }
    }

    public function test_malicious_admin_export_filter_is_not_persisted_in_diagnostic_audit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $sentinel = 'SENTINEL-password-value';
        $page = app(RuntimeInspector::class);
        $page->correlationId = $sentinel;

        $page->downloadDiagnosticBundle();

        $audit = DB::table('runtime_diagnostic_events')
            ->where('data_class', 'audit')
            ->where('stable_code', 'DIAGNOSTIC_EXPORT')
            ->latest('occurred_at')
            ->first();
        self::assertNotNull($audit);
        $metadata = json_decode((string) $audit->metadata, true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('filter_correlation_id', $metadata);
        self::assertStringNotContainsString($sentinel, json_encode((array) $audit, JSON_THROW_ON_ERROR));
    }

    public function test_runtime_inspector_is_admin_only_and_feature_gated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $contentTeam = User::factory()->create(['role' => 'content_team']);
        $student = User::factory()->create(['role' => 'student']);

        $this->actingAs($student);
        self::assertFalse(RuntimeInspector::canAccess());

        $this->actingAs($contentTeam);
        self::assertFalse(RuntimeInspector::canAccess());

        $this->actingAs($admin);
        self::assertTrue(RuntimeInspector::canAccess());

        config(['observability.inspector_enabled' => false]);
        self::assertFalse(RuntimeInspector::canAccess());
    }

    /** @return array<string, mixed> */
    private function eventRow(int $index): array
    {
        $timestamp = now('UTC')->addMilliseconds($index);

        return [
            'id' => (string) Str::ulid(),
            'occurred_at' => $timestamp,
            'correlation_id' => sprintf('event-%02d-01J6MODRIK1234567', $index),
            'data_class' => 'application_log',
            'severity' => 'info',
            'surface' => 'backend',
            'category' => 'test_event',
            'stable_code' => sprintf('EVENT_%02d', $index),
            'route' => 'observability.test',
            'action' => null,
            'duration_ms' => $index,
            'environment' => 'testing',
            'build_identity' => null,
            'actor_id' => null,
            'metadata' => ['source' => 'test'],
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
    }
}
