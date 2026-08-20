<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordRecoveryTokenNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your MODRIK password')
            ->line('Use the one-time token below to reset your MODRIK password.')
            ->line($this->token)
            ->line('If you did not request a reset, no action is required.');
    }
}
