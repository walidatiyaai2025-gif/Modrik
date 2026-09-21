<?php

namespace App\Notifications;

use App\Notifications\Channels\RotatingSmtpMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PasswordRecoveryTokenNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly string $token) {}

    /** @return list<class-string> */
    public function via(object $notifiable): array
    {
        return [RotatingSmtpMailChannel::class];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentPortalUrl = rtrim((string) config('modrik.web.student_portal_url'), '/');
        $resetUrl = $studentPortalUrl.'?reset_token='.rawurlencode($this->token);
        $ttlMinutes = max(5, (int) config('modrik.auth.recovery_ttl_minutes', 30));

        return (new MailMessage)
            ->subject('Reset your MODRIK password')
            ->greeting('MODRIK password reset')
            ->line('Use the button below to choose a new password for your MODRIK account.')
            ->action('Reset password', $resetUrl)
            ->line("This reset link expires in {$ttlMinutes} minutes and can be used only once.")
            ->line('If you did not request a password reset, you can safely ignore this email.')
            ->salutation('MODRIK');
    }
}
