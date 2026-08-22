<?php

namespace Tests\Feature;

use App\Services\MailTransportStatusService;
use Tests\TestCase;

final class MailTransportStatusTest extends TestCase
{
    public function test_log_and_array_mailers_are_never_reported_as_delivery_capable(): void
    {
        $service = app(MailTransportStatusService::class);

        config()->set('mail.default', 'log');
        self::assertSame(MailTransportStatusService::TEST_ONLY, $service->status());
        self::assertFalse($service->credentialsConfigured());

        config()->set('mail.default', 'array');
        self::assertSame(MailTransportStatusService::TEST_ONLY, $service->status());
        self::assertFalse($service->credentialsConfigured());
    }

    public function test_default_unconfigured_smtp_fails_closed(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'url' => null,
            'host' => '127.0.0.1',
            'port' => 2525,
            'username' => null,
            'password' => null,
        ]);

        $service = app(MailTransportStatusService::class);

        self::assertSame(MailTransportStatusService::CONFIGURATION_INCOMPLETE, $service->status());
        self::assertFalse($service->credentialsConfigured());
    }

    public function test_explicit_smtp_transport_is_available_without_network_traffic(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'url' => null,
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'mailer-password',
        ]);

        $service = app(MailTransportStatusService::class);

        self::assertSame(MailTransportStatusService::AVAILABLE, $service->status());
        self::assertTrue($service->credentialsConfigured());
    }

    public function test_credential_backed_transport_requires_its_required_configuration(): void
    {
        config()->set('mail.default', 'postmark');
        config()->set('services.postmark.key', null);

        $service = app(MailTransportStatusService::class);

        self::assertSame(MailTransportStatusService::CONFIGURATION_INCOMPLETE, $service->status());
        self::assertFalse($service->credentialsConfigured());

        config()->set('services.postmark.key', 'postmark-test-key');

        self::assertSame(MailTransportStatusService::AVAILABLE, $service->status());
        self::assertTrue($service->credentialsConfigured());
    }

    public function test_composite_mailer_with_test_only_fallback_fails_closed(): void
    {
        config()->set('mail.default', 'delivery_failover');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'mailer-password',
        ]);
        config()->set('mail.mailers.delivery_failover', [
            'transport' => 'failover',
            'mailers' => ['smtp', 'log'],
        ]);

        self::assertSame(
            MailTransportStatusService::TEST_ONLY,
            app(MailTransportStatusService::class)->status(),
        );
    }

    public function test_composite_mailer_is_available_only_when_every_child_is_delivery_capable(): void
    {
        config()->set('mail.default', 'delivery_roundrobin');
        config()->set('mail.mailers.smtp', [
            'transport' => 'smtp',
            'host' => 'smtp.example.test',
            'port' => 587,
            'username' => 'mailer-user',
            'password' => 'mailer-password',
        ]);
        config()->set('services.ses.key', 'ses-test-key');
        config()->set('services.ses.secret', 'ses-test-secret');
        config()->set('services.ses.region', 'us-east-1');
        config()->set('mail.mailers.delivery_roundrobin', [
            'transport' => 'roundrobin',
            'mailers' => ['smtp', 'ses'],
        ]);

        self::assertSame(
            MailTransportStatusService::AVAILABLE,
            app(MailTransportStatusService::class)->status(),
        );
    }

    public function test_missing_or_recursive_composite_configuration_fails_closed(): void
    {
        $service = app(MailTransportStatusService::class);

        config()->set('mail.default', 'missing');
        self::assertSame(MailTransportStatusService::CONFIGURATION_INCOMPLETE, $service->status());
        self::assertFalse($service->credentialsConfigured());

        config()->set('mail.default', 'loop');
        config()->set('mail.mailers.loop', [
            'transport' => 'failover',
            'mailers' => ['loop'],
        ]);
        self::assertSame(MailTransportStatusService::CONFIGURATION_INCOMPLETE, $service->status());
        self::assertFalse($service->credentialsConfigured());
    }
}
