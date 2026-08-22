<?php

namespace App\Services;

final class MailTransportStatusService
{
    public const AVAILABLE = 'available';

    public const TEST_ONLY = 'test_only';

    public const CONFIGURATION_INCOMPLETE = 'configuration_incomplete';

    /**
     * Resolve whether the configured default Laravel mailer can truthfully
     * represent an external delivery attempt. No network request is made.
     */
    public function status(): string
    {
        $default = trim((string) config('mail.default', ''));

        if ($default === '') {
            return self::CONFIGURATION_INCOMPLETE;
        }

        return $this->mailerStatus($default, []);
    }

    /** @param array<string, true> $seen */
    private function mailerStatus(string $mailer, array $seen): string
    {
        if (isset($seen[$mailer])) {
            return self::CONFIGURATION_INCOMPLETE;
        }

        $configuration = config("mail.mailers.{$mailer}");
        if (! is_array($configuration)) {
            return self::CONFIGURATION_INCOMPLETE;
        }

        $transport = strtolower(trim((string) ($configuration['transport'] ?? '')));
        if ($transport === '') {
            return self::CONFIGURATION_INCOMPLETE;
        }

        if (in_array($transport, ['log', 'array'], true)) {
            return self::TEST_ONLY;
        }

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? null;
            if (! is_array($children) || $children === []) {
                return self::CONFIGURATION_INCOMPLETE;
            }

            $seen[$mailer] = true;
            $statuses = [];

            foreach ($children as $child) {
                if (! is_string($child) || trim($child) === '') {
                    return self::CONFIGURATION_INCOMPLETE;
                }

                $statuses[] = $this->mailerStatus(trim($child), $seen);
            }

            // A composite mailer is delivery-truthful only when every possible
            // transport is delivery-capable. A log/array fallback can otherwise
            // make Laravel report success after no external delivery occurred.
            if (in_array(self::TEST_ONLY, $statuses, true)) {
                return self::TEST_ONLY;
            }

            return in_array(self::CONFIGURATION_INCOMPLETE, $statuses, true)
                ? self::CONFIGURATION_INCOMPLETE
                : self::AVAILABLE;
        }

        return in_array($transport, [
            'smtp',
            'sendmail',
            'mailgun',
            'ses',
            'ses-v2',
            'postmark',
            'resend',
        ], true)
            ? self::AVAILABLE
            : self::CONFIGURATION_INCOMPLETE;
    }
}
