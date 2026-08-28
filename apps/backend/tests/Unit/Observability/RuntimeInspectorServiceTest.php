<?php

namespace Tests\Unit\Observability;

use App\Support\Observability\RuntimeInspectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class RuntimeInspectorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_summary_detects_staged_laravel_view_path(): void
    {
        config([
            'view.paths' => [
                '/home/example/public_html/.modrik-updates/releases/release-123/payload/backend/resources/views',
            ],
        ]);

        $summary = app(RuntimeInspectorService::class)->runtimeSummary();

        $this->assertTrue($summary['stale_view_path']);
        $this->assertSame('fail', $summary['view_path_status']);
        $this->assertSame('fail', $summary['runtime_status']);
        $this->assertContains(
            'Laravel view.paths still references a staged .modrik-updates release.',
            $summary['runtime_reasons'],
        );
    }

    public function test_runtime_summary_accepts_live_resource_view_path(): void
    {
        config(['view.paths' => [resource_path('views')]]);

        $summary = app(RuntimeInspectorService::class)->runtimeSummary();

        $this->assertFalse($summary['stale_view_path']);
        $this->assertSame('ok', $summary['view_path_status']);
        $this->assertSame([resource_path('views')], $summary['view_paths']);
    }

    public function test_runtime_summary_exposes_safe_smtp_state_without_credentials(): void
    {
        DB::table('smtp_providers')->insert([
            'id' => '01JSMTPINSPECTOR00000000001',
            'name' => 'Primary SMTP',
            'host' => 'mail.modrik.org',
            'port' => 587,
            'scheme' => null,
            'username' => 'study@modrik.org',
            'password_ciphertext' => Crypt::encryptString('never-show-this-secret'),
            'from_address' => 'study@modrik.org',
            'from_name' => 'MODRIK',
            'is_enabled' => true,
            'last_tested_at' => now(),
            'last_test_status' => 'failed',
            'last_error_code' => 'AUTH_REJECTED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $summary = app(RuntimeInspectorService::class)->runtimeSummary();
        $encoded = json_encode($summary, JSON_THROW_ON_ERROR);

        $this->assertSame('managed_smtp_provider_pool', $summary['mail_source']);
        $this->assertSame(1, $summary['enabled_smtp_providers']);
        $this->assertIsArray($summary['active_smtp_provider']);
        $this->assertSame('mail.modrik.org', $summary['active_smtp_provider']['host']);
        $this->assertSame('AUTH_REJECTED', $summary['active_smtp_provider']['last_error_code']);
        $this->assertStringNotContainsString('never-show-this-secret', $encoded);
        $this->assertStringNotContainsString('password_ciphertext', $encoded);
    }
}
