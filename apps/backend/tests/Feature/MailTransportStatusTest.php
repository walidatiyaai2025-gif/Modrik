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

        config()->set('mail.default', 'array');
        self::assertSame(MailTransportStatusService::TEST_ONLY, $service->status());
    }

    public function test_real_transport_is_reported_available_without_sending_external_traffic(): void
    {
        config()->set('mail.default', 'smtp');

        self::assertSame(
            MailTransportStatusService::AVAILABLE,
            app(MailTransportStatusService::class)->status(),
        );
    }

    public function test_composite_mailer_with_test_only_fallback_fails_closed(): void
    {
        config()->set('mail.default', 'delivery_failover');
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

        config()->set('mail.default', 'loop');
        config()->set('mail.mailers.loop', [
            'transport' => 'failover',
            'mailers' => ['loop'],
        ]);
        self::assertSame(MailTransportStatusService::CONFIGURATION_INCOMPLETE, $service->status());
    }
}
