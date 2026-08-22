<?php

namespace Tests\Feature;

use App\Filament\Pages\FirebaseRuntimeIntegrations;
use App\Models\User;
use App\Services\AdvertisingEligibilityService;
use App\Services\IntegrationStatusService;
use App\Services\SystemSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminIntegrationSettingsSurfacesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_discover_all_domain_grouped_settings_and_integration_surfaces(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);
        $this->actingAs($admin);

        $this->get('/admin/authentication-providers')->assertOk()->assertSee('data-testid="modrik-auth-providers"', false);
        $this->get('/admin/notification-settings')->assertOk()->assertSee('data-testid="modrik-notification-settings"', false);
        $this->get('/admin/firebase-runtime')->assertOk()->assertSee('data-testid="modrik-firebase-runtime"', false);
        $this->get('/admin/advertising-safety')->assertOk()->assertSee('data-testid="modrik-advertising-safety"', false);
    }

    public function test_student_cannot_access_integration_admin_surfaces(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $this->actingAs($student);

        foreach ([
            '/admin/authentication-providers',
            '/admin/notification-settings',
            '/admin/firebase-runtime',
            '/admin/advertising-safety',
        ] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_authentication_status_never_discloses_provider_secrets_or_private_keys(): void
    {
        config()->set('modrik.auth.providers.google', [
            'client_id' => 'google-client-reference-1234567890',
            'client_secret' => 'GOOGLE-SUPER-SECRET-VALUE',
            'callback_url' => 'https://example.test/google/callback',
        ]);
        config()->set('modrik.auth.providers.apple', [
            'client_id' => 'apple-client-reference-1234567890',
            'team_id' => 'TEAMREFERENCE1234',
            'key_id' => 'KEYREFERENCE1234',
            'private_key' => 'APPLE-PRIVATE-KEY-MATERIAL',
            'callback_url' => 'https://example.test/apple/callback',
        ]);

        $status = app(IntegrationStatusService::class)->authentication(app()->environment());
        $serialized = json_encode($status, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('GOOGLE-SUPER-SECRET-VALUE', $serialized);
        $this->assertStringNotContainsString('APPLE-PRIVATE-KEY-MATERIAL', $serialized);
        $this->assertTrue($status['google']['secret_set']);
        $this->assertTrue($status['apple']['secret_set']);
        $this->assertSame('pending', $status['google']['transport_status']);
        $this->assertSame('pending', $status['apple']['transport_status']);
    }

    public function test_firebase_test_push_accepts_only_designated_references_and_never_claims_send_without_adapter(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        Livewire::test(FirebaseRuntimeIntegrations::class)
            ->set('targetType', 'raw_registration_token')
            ->set('targetReference', 'raw-token-material')
            ->call('testPush')
            ->assertHasErrors(['targetReference']);

        $settings = app(SystemSettingsRegistry::class);
        $settings->update(
            'firebase.fcm.enabled',
            app()->environment(),
            true,
            0,
            'Enable FCM only for the controlled integration-boundary test.',
            (string) $admin->id,
        );
        config()->set('modrik.firebase.project_id', 'fixture-project-reference');
        config()->set('modrik.firebase.credentials_reference', 'external-secret-reference');

        Livewire::test(FirebaseRuntimeIntegrations::class)
            ->set('targetType', 'test_device')
            ->set('targetReference', 'QA-DEVICE-01')
            ->call('testPush')
            ->assertHasNoErrors()
            ->assertSet('lastTestCode', 'FCM_TRANSPORT_PENDING');
    }

    public function test_operator_ads_switch_is_restrictive_only_and_immutable_no_ad_zone_still_wins(): void
    {
        $user = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);
        $service = app(AdvertisingEligibilityService::class);

        $general = $service->decide($user, 'dashboard_sidebar');
        $this->assertFalse($general['advertising_allowed']);
        $this->assertSame('GLOBAL_KILL_SWITCH', $general['reason_code']);

        $immutable = $service->decide($user, 'lesson_inline');
        $this->assertFalse($immutable['advertising_allowed']);
        $this->assertSame('NO_AD_ZONE', $immutable['reason_code']);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        app(SystemSettingsRegistry::class)->update(
            'ads.global.enabled',
            app()->environment(),
            true,
            0,
            'Allow policy evaluation while preserving all immutable safety gates.',
            (string) $admin->id,
        );

        $afterEnable = $service->decide($user, 'dashboard_sidebar');
        $this->assertFalse($afterEnable['advertising_allowed']);
        $this->assertSame('CONFIG_MISSING', $afterEnable['reason_code']);
    }
}
