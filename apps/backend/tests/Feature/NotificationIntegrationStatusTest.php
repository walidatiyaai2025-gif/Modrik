<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\IntegrationStatusService;
use App\Services\SystemSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class NotificationIntegrationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_party_notification_center_status_is_present_independently_of_fcm_transport(): void
    {
        $environment = app()->environment();
        $service = app(IntegrationStatusService::class);

        $disabled = $service->notifications($environment);

        $this->assertSame('present', $disabled['student_notification_center']);
        $this->assertSame('disabled', $disabled['push_channel']);

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);

        app(SystemSettingsRegistry::class)->update(
            'firebase.fcm.enabled',
            $environment,
            true,
            0,
            'Verify that auxiliary FCM readiness does not redefine first-party inbox availability.',
            (string) $admin->id,
        );

        $enabled = $service->notifications($environment);

        $this->assertSame('present', $enabled['student_notification_center']);
        $this->assertSame('enabled_pending_transport', $enabled['push_channel']);
        $this->assertNotSame($enabled['student_notification_center'], $enabled['push_channel']);
    }
}
