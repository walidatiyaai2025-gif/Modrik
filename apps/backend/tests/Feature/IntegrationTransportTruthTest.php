<?php

namespace Tests\Feature;

use App\Services\IntegrationStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IntegrationTransportTruthTest extends TestCase
{
    use RefreshDatabase;

    public function test_log_mailer_is_reported_as_test_only_not_available(): void
    {
        config()->set('mail.default', 'log');
        config()->set('mail.mailers.log.transport', 'log');

        $service = app(IntegrationStatusService::class);
        $authentication = $service->authentication(app()->environment());
        $notifications = $service->notifications(app()->environment());

        $this->assertSame('test_only', $authentication['email']['transport_status']);
        $this->assertSame('test_only', $notifications['email_verification_channel']);
        $this->assertSame('test_only', $notifications['password_recovery_channel']);
    }

    public function test_array_mailer_is_reported_as_test_only_not_available(): void
    {
        config()->set('mail.default', 'array');
        config()->set('mail.mailers.array.transport', 'array');

        $status = app(IntegrationStatusService::class)->authentication(app()->environment());

        $this->assertSame('test_only', $status['email']['transport_status']);
    }

    public function test_configured_external_mailer_is_not_claimed_available_without_validation(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.transport', 'smtp');
        config()->set('mail.mailers.smtp.host', 'mail.example.test');
        config()->set('mail.mailers.smtp.port', 587);

        $service = app(IntegrationStatusService::class);
        $authentication = $service->authentication(app()->environment());
        $notifications = $service->notifications(app()->environment());

        $this->assertSame('configured_not_validated', $authentication['email']['transport_status']);
        $this->assertSame('configured_not_validated', $notifications['email_verification_channel']);
        $this->assertSame('configured_not_validated', $notifications['password_recovery_channel']);
    }

    public function test_missing_mail_transport_fails_closed_as_configuration_incomplete(): void
    {
        config()->set('mail.default', 'broken');
        config()->set('mail.mailers.broken', []);

        $status = app(IntegrationStatusService::class)->authentication(app()->environment());

        $this->assertSame('configuration_incomplete', $status['email']['transport_status']);
        $this->assertFalse($status['email']['secret_set']);
    }
}
