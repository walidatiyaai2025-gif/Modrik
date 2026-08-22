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

    /**
     * Report whether the configured mailer has a secret/credential reference
     * where that transport uses credentials. This never returns secret values.
     */
    public function credentialsConfigured(): bool
    {
        $default = trim((string) config('mail.default', ''));

        if ($default === '') {
            return false;
        }

        return $this->mailerCredentialsConfigured($default, []);
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

        return match ($transport) {
            'smtp' => $this->smtpStatus($configuration),
            'sendmail' => $this->sendmailStatus($configuration),
            'mailgun' => $this->requiredConfigStatus([
                config('services.mailgun.secret'),
                config('services.mailgun.domain'),
            ]),
            'ses', 'ses-v2' => $this->requiredConfigStatus([
                config('services.ses.key'),
                config('services.ses.secret'),
                config('services.ses.region'),
            ]),
            'postmark' => $this->requiredConfigStatus([config('services.postmark.key')]),
            'resend' => $this->requiredConfigStatus([config('services.resend.key')]),
            default => self::CONFIGURATION_INCOMPLETE,
        };
    }

    /** @param array<string, mixed> $configuration */
    private function smtpStatus(array $configuration): string
    {
        $url = trim((string) ($configuration['url'] ?? ''));
        if ($url !== '') {
            $parts = parse_url($url);
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            $host = is_array($parts) ? trim((string) ($parts['host'] ?? '')) : '';

            return in_array($scheme, ['smtp', 'smtps'], true) && $host !== ''
                ? self::AVAILABLE
                : self::CONFIGURATION_INCOMPLETE;
        }

        $host = strtolower(trim((string) ($configuration['host'] ?? '')));
        $port = filter_var(
            $configuration['port'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 65535]],
        );

        if ($host === '' || $port === false) {
            return self::CONFIGURATION_INCOMPLETE;
        }

        // Laravel's stock config falls back to 127.0.0.1:2525. Treat that
        // loopback shape as incomplete unless explicit credentials were also
        // supplied, so merely selecting MAIL_MAILER=smtp cannot manufacture
        // an externally-delivery-capable status.
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            $username = trim((string) ($configuration['username'] ?? ''));
            $password = trim((string) ($configuration['password'] ?? ''));

            if ($username === '' || $password === '') {
                return self::CONFIGURATION_INCOMPLETE;
            }
        }

        return self::AVAILABLE;
    }

    /** @param array<string, mixed> $configuration */
    private function sendmailStatus(array $configuration): string
    {
        $command = trim((string) ($configuration['path'] ?? ''));
        if ($command === '') {
            return self::CONFIGURATION_INCOMPLETE;
        }

        $parts = preg_split('/\s+/', $command);
        $binary = is_array($parts) ? (string) ($parts[0] ?? '') : '';

        return $binary !== '' && is_executable($binary)
            ? self::AVAILABLE
            : self::CONFIGURATION_INCOMPLETE;
    }

    /** @param array<int, mixed> $values */
    private function requiredConfigStatus(array $values): string
    {
        foreach ($values as $value) {
            if (trim((string) $value) === '') {
                return self::CONFIGURATION_INCOMPLETE;
            }
        }

        return self::AVAILABLE;
    }

    /** @param array<string, true> $seen */
    private function mailerCredentialsConfigured(string $mailer, array $seen): bool
    {
        if (isset($seen[$mailer])) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}");
        if (! is_array($configuration)) {
            return false;
        }

        $transport = strtolower(trim((string) ($configuration['transport'] ?? '')));
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = $configuration['mailers'] ?? null;
            if (! is_array($children) || $children === []) {
                return false;
            }

            $seen[$mailer] = true;

            foreach ($children as $child) {
                if (! is_string($child) || trim($child) === '') {
                    return false;
                }
                if (! $this->mailerCredentialsConfigured(trim($child), $seen)) {
                    return false;
                }
            }

            return true;
        }

        return match ($transport) {
            'smtp' => $this->smtpCredentialsConfigured($configuration),
            'mailgun' => trim((string) config('services.mailgun.secret')) !== '',
            'ses', 'ses-v2' => trim((string) config('services.ses.key')) !== ''
                && trim((string) config('services.ses.secret')) !== '',
            'postmark' => trim((string) config('services.postmark.key')) !== '',
            'resend' => trim((string) config('services.resend.key')) !== '',
            default => false,
        };
    }

    /** @param array<string, mixed> $configuration */
    private function smtpCredentialsConfigured(array $configuration): bool
    {
        $url = trim((string) ($configuration['url'] ?? ''));
        if ($url !== '') {
            $parts = parse_url($url);
            if (is_array($parts) && trim((string) ($parts['user'] ?? '')) !== '') {
                return trim((string) ($parts['pass'] ?? '')) !== '';
            }
        }

        return trim((string) ($configuration['username'] ?? '')) !== ''
            && trim((string) ($configuration['password'] ?? '')) !== '';
    }
}
