<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmailVerificationTokenNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify your MODRIK email')
            ->line('Use the one-time token below to verify your MODRIK email address.')
            ->line($this->token)
            ->line('If you did not request this, no action is required.');
    }
}
