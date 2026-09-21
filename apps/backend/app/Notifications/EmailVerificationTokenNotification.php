<?php

namespace App\Notifications;

use App\Notifications\Channels\RotatingSmtpMailChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class EmailVerificationTokenNotification extends Notification
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
        $verificationUrl = $studentPortalUrl.'?verify_token='.rawurlencode($this->token);
        $ttlMinutes = max(5, (int) config('modrik.auth.verification_ttl_minutes', 60));

        return (new MailMessage)
            ->subject('Verify your MODRIK email')
            ->greeting('Welcome to MODRIK')
            ->line('Confirm your email address to finish setting up your MODRIK account.')
            ->action('Verify email address', $verificationUrl)
            ->line("This verification link expires in {$ttlMinutes} minutes and can be used only once.")
            ->line('If you did not create a MODRIK account, you can safely ignore this email.')
            ->salutation('MODRIK');
    }
}
