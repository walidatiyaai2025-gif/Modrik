<?php

namespace App\Notifications\Channels;

use App\Services\SmtpProviderPoolService;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use RuntimeException;
use Throwable;

final class RotatingSmtpMailChannel extends MailChannel
{
    public function __construct(
        private readonly MailFactory $mailFactory,
        Markdown $markdown,
        private readonly SmtpProviderPoolService $providers,
    ) {
        parent::__construct($mailFactory, $markdown);
    }

    public function send($notifiable, Notification $notification)
    {
        if ($this->providers->enabledProviderCount() === 0) {
            return parent::send($notifiable, $notification);
        }

        if (! method_exists($notification, 'toMail')) {
            throw new RuntimeException('SMTP_PROVIDER_UNSUPPORTED_NOTIFICATION');
        }

        $message = $notification->toMail($notifiable);

        if ($message instanceof Mailable) {
            return parent::send($notifiable, $notification);
        }

        if (! $message instanceof MailMessage) {
            throw new RuntimeException('SMTP_PROVIDER_UNSUPPORTED_MAIL_MESSAGE');
        }

        if (! $notifiable->routeNotificationFor('mail', $notification)) {
            return null;
        }

        $candidates = $this->providers->deliveryCandidates();
        if ($candidates === []) {
            throw new RuntimeException('SMTP_PROVIDER_SECRET_UNAVAILABLE');
        }

        $lastException = new RuntimeException('SMTP_PROVIDER_DELIVERY_FAILED');

        foreach ($candidates as $provider) {
            $attempt = clone $message;
            $mailerName = $this->providers->configureMailer($provider);
            $attempt->mailer($mailerName);
            $attempt->from((string) $provider['from_address'], (string) $provider['from_name']);

            try {
                return $this->mailFactory->mailer($mailerName)->send(
                    $this->buildView($attempt),
                    array_merge($attempt->data(), $this->additionalMessageData($notification)),
                    $this->messageBuilder($notifiable, $notification, $attempt),
                );
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        throw $lastException;
    }
}
