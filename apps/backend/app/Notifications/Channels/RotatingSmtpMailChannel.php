<?php

namespace App\Notifications\Channels;

use App\Services\SmtpProviderPoolService;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use RuntimeException;
use Throwable;

final class RotatingSmtpMailChannel extends MailChannel
{
    public function __construct(
        MailFactory $mailer,
        Markdown $markdown,
        private readonly SmtpProviderPoolService $providers,
    ) {
        parent::__construct($mailer, $markdown);
    }

    public function send($notifiable, Notification $notification)
    {
        if ($this->providers->enabledProviderCount() === 0) {
            return parent::send($notifiable, $notification);
        }

        $message = $notification->toMail($notifiable);

        if ($message instanceof Mailable) {
            return parent::send($notifiable, $notification);
        }

        if (! $notifiable->routeNotificationFor('mail', $notification)) {
            return null;
        }

        $candidates = $this->providers->deliveryCandidates();
        if ($candidates === []) {
            throw new RuntimeException('SMTP_PROVIDER_SECRET_UNAVAILABLE');
        }

        $lastException = null;

        foreach ($candidates as $provider) {
            $attempt = clone $message;
            $attempt->mailer($this->providers->configureMailer($provider));
            $attempt->from((string) $provider['from_address'], (string) $provider['from_name']);

            try {
                return $this->mailer->mailer($attempt->mailer)->send(
                    $this->buildView($attempt),
                    array_merge($attempt->data(), $this->additionalMessageData($notification)),
                    $this->messageBuilder($notifiable, $notification, $attempt),
                );
            } catch (Throwable $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException instanceof Throwable) {
            throw $lastException;
        }

        throw new RuntimeException('SMTP_PROVIDER_DELIVERY_FAILED');
    }
}
