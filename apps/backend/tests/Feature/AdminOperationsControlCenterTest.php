<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsControlCenter;
use App\Models\User;
use App\Services\AdminOperationsOverviewService;
use App\Services\SystemSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminOperationsControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_access_operations_control_center(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $content = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)->get('/admin/operations-control-center')
            ->assertOk()
            ->assertSee('data-testid="modrik-operations-control-center"', false);

        $this->actingAs($content)->get('/admin/operations-control-center')->assertForbidden();
        $this->actingAs($student)->get('/admin/operations-control-center')->assertForbidden();
    }

    public function test_overview_reports_only_contract_backed_safe_health_and_marks_missing_scheduler_heartbeat_unobservable(): void
    {
        config()->set('modrik.auth.providers.google.client_secret', 'GOOGLE-SECRET-MUST-NOT-LEAK');
        config()->set('modrik.auth.providers.apple.private_key', 'APPLE-PRIVATE-KEY-MUST-NOT-LEAK');
        config()->set('modrik.firebase.credentials_reference', 'external-firebase-credential-reference');

        $overview = app(AdminOperationsOverviewService::class)->overview();
        $serialized = json_encode($overview, JSON_THROW_ON_ERROR);

        $this->assertSame('healthy', $overview['backend']['status']);
        $this->assertSame('not_observable', $overview['scheduler']['status']);
        $this->assertArrayHasKey('queued', $overview['queue']);
        $this->assertArrayHasKey('failed', $overview['queue']);
        $this->assertArrayHasKey('diagnostic_events', $overview['runtime']);
        $this->assertArrayHasKey('firebase_credentials_reference_set', $overview['integrations']);
        $this->assertStringNotContainsString('GOOGLE-SECRET-MUST-NOT-LEAK', $serialized);
        $this->assertStringNotContainsString('APPLE-PRIVATE-KEY-MUST-NOT-LEAK', $serialized);
        $this->assertStringNotContainsString('external-firebase-credential-reference', $serialized);
    }

    public function test_enabled_firebase_without_transport_adapter_is_never_reported_healthy(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        app(SystemSettingsRegistry::class)->update(
            'firebase.fcm.enabled',
            app()->environment(),
            true,
            0,
            'Enable FCM for the truthful runtime-status regression test.',
            (string) $admin->id,
        );
        config()->set('modrik.firebase.project_id', 'configured-project-reference');
        config()->set('modrik.firebase.credentials_reference', 'external-credential-reference');

        $overview = app(AdminOperationsOverviewService::class)->overview();

        $this->assertSame('pending_adapter', $overview['integrations']['firebase_fcm_status']);
        $this->assertSame('attention', $overview['integrations']['status']);
        $this->assertStringContainsString('not available', $overview['integrations']['detail']);
    }

    public function test_operations_page_links_existing_authorized_surfaces_instead_of_exposing_shell_or_sql_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(OperationsControlCenter::class)
            ->assertSee('/admin/system-capabilities', false)
            ->assertSee('/admin/account-operations', false)
            ->assertSee('/admin/content-ingestion-operations', false)
            ->assertSee('/admin/system-settings', false)
            ->assertDontSee('shell command')
            ->assertDontSee('SQL console')
            ->assertDontSee('payload editor');
    }

    public function test_arabic_operations_surface_is_rtl_and_french_label_is_available(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        App::setLocale('ar');
        Livewire::test(OperationsControlCenter::class)
            ->assertSee('مركز التحكم التشغيلي')
            ->assertSee('dir="rtl"', false);

        App::setLocale('fr');
        $this->assertSame('Centre de contrôle opérationnel', OperationsControlCenter::getNavigationLabel());
    }
}
