<?php

namespace Tests\Feature;

use App\Filament\Pages\RuntimeInspector;
use App\Models\User;
use App\Services\RuntimeDiagnostics;
use App\Support\CorrelationId;
use App\Support\DiagnosticSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class RuntimeDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.observability.enabled' => true,
            'modrik.observability.storage_enabled' => true,
            'modrik.observability.inspector_enabled' => true,
            'modrik.observability.maximum_query_events' => 200,
            'modrik.observability.maximum_export_events' => 200,
            'modrik.observability.maximum_export_bytes' => 16_384,
            'modrik.observability.retention_days' => null,
        ]);
    }

    public function test_runtime_capture_keeps_only_allowlisted_diagnostic_fields(): void
    {
        $correlationId = '7a1c9b6e-7a8d-4f33-9a12-0123456789ab';
        $sentinels = [
            'bearer-token-sentinel',
            'cookie-secret-sentinel',
            'password-secret-sentinel',
            'learner-answer-sentinel',
            'question-text-sentinel',
            'content-body-sentinel',
        ];

        $response = $this->withHeaders([
            CorrelationId::HEADER => $correlationId,
            'Authorization' => 'Bearer '.$sentinels[0],
            'Cookie' => 'modrik_session='.$sentinels[1],
            'X-Password-Debug' => $sentinels[2],
        ])->postJson('/v1/auth/providers/google/login-intents', [
            'unexpected' => implode('|', array_slice($sentinels, 3)),
        ]);

        $response->assertStatus(422)
            ->assertHeader(CorrelationId::HEADER, $correlationId)
            ->assertJsonPath('request_id', $correlationId)
            ->assertJsonPath('code', 'VALIDATION_FAILED');

        $row = DB::table('runtime_diagnostic_events')->where('correlation_id', $correlationId)->first();
        $this->assertNotNull($row);
        $this->assertSame('application_log', $row->event_class);
        $this->assertSame('exception', $row->category);
        $this->assertSame('VALIDATION_FAILED', $row->stable_code);

        $stored = json_encode((array) $row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        foreach ($sentinels as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $stored);
        }

        $metadata = DiagnosticSanitizer::metadata([
            'event_name' => 'safe.event',
            'password' => $sentinels[2],
            'answer' => $sentinels[3],
            'question_text' => $sentinels[4],
            'content_body' => $sentinels[5],
        ]);
        $this->assertSame(['event_name' => 'safe.event'], $metadata);
    }

    public function test_diagnostic_storage_failure_never_breaks_the_business_request(): void
    {
        Schema::drop('runtime_diagnostic_events');

        $response = $this->getJson('/health');

        $response->assertOk();
        $correlationId = $response->headers->get(CorrelationId::HEADER);
        $this->assertIsString($correlationId);
        $this->assertTrue(CorrelationId::isValid($correlationId));
    }

    public function test_inspector_is_admin_only_even_though_content_team_can_use_the_admin_panel(): void
    {
        $contentTeam = User::factory()->create(['role' => 'content_team']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($contentTeam)
            ->get('/admin/runtime-inspector')
            ->assertForbidden();

        $this->actingAs($admin);
        $this->get('/admin/runtime-inspector')->assertOk();
        Livewire::test(RuntimeInspector::class)
            ->assertSee(__('observability.title'))
            ->assertSee(__('observability.privacy_note'));
    }

    public function test_sanitized_export_is_filterable_byte_bounded_and_audited_with_internal_actor_only(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'private-admin@example.test',
        ]);
        $correlationId = '2f1c9b6e-7a8d-4f33-9a12-0123456789ab';
        $diagnostics = app(RuntimeDiagnostics::class);
        $secret = 'password-answer-question-content-secret-sentinel';

        for ($index = 0; $index < 80; $index++) {
            $diagnostics->recordAudit('diagnostic_test', $correlationId, $admin, [
                'event_name' => 'diagnostic.test',
                'exception_class' => str_repeat('A', 256),
                'password' => $secret,
                'answer' => $secret,
            ]);
        }

        $json = $diagnostics->exportJson([
            'correlation_id' => $correlationId,
            'event_class' => 'durable_audit',
            'window_minutes' => 60,
        ], $correlationId, $admin);

        $this->assertLessThanOrEqual(16_384, strlen($json));
        $this->assertStringNotContainsString($secret, $json);
        $this->assertStringNotContainsString('private-admin@example.test', $json);
        $this->assertStringContainsString($correlationId, $json);

        $bundle = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($bundle);
        $this->assertTrue($bundle['truncated']);
        $this->assertNotEmpty($bundle['events']);
        foreach ($bundle['events'] as $event) {
            $this->assertSame($correlationId, $event['correlation_id']);
            $this->assertSame('durable_audit', $event['event_class']);
        }

        $audit = DB::table('runtime_diagnostic_events')
            ->where('category', 'diagnostic_export')
            ->where('correlation_id', $correlationId)
            ->latest('recorded_at')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame((string) $admin->getKey(), $audit->actor_id);
        $this->assertStringNotContainsString('private-admin@example.test', (string) $audit->metadata);
    }

    public function test_retention_is_inert_until_owner_configures_it(): void
    {
        $diagnostics = app(RuntimeDiagnostics::class);
        $correlationId = '9f1c9b6e-7a8d-4f33-9a12-0123456789ab';
        $diagnostics->recordAudit('retention_test', $correlationId, null, ['event_name' => 'retention.test']);

        DB::table('runtime_diagnostic_events')
            ->where('category', 'retention_test')
            ->update(['recorded_at' => now()->subDays(30)]);

        $this->artisan('modrik:diagnostics-prune')->assertSuccessful();
        $this->assertDatabaseHas('runtime_diagnostic_events', ['category' => 'retention_test']);

        config(['modrik.observability.retention_days' => 7]);
        $this->artisan('modrik:diagnostics-prune')->assertSuccessful();
        $this->assertDatabaseMissing('runtime_diagnostic_events', ['category' => 'retention_test']);
    }
}
