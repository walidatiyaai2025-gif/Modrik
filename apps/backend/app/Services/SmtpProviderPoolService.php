<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use JsonException;
use Random\Randomizer;
use Throwable;

final class SmtpProviderPoolService
{
    /** @return array<int, array<string, mixed>> */
    public function providers(): array
    {
        return DB::table('smtp_providers')
            ->orderByDesc('is_enabled')
            ->orderBy('name')
            ->get()
            ->map(fn (object $row): array => $this->safeProvider((array) $row))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function audits(): array
    {
        return DB::table('smtp_provider_audits')
            ->leftJoin('users', 'users.id', '=', 'smtp_provider_audits.actor_id')
            ->orderByDesc('smtp_provider_audits.occurred_at')
            ->limit(50)
            ->get([
                'smtp_provider_audits.smtp_provider_id',
                'smtp_provider_audits.action',
                'smtp_provider_audits.reason',
                'smtp_provider_audits.occurred_at',
                'users.email as actor_email',
            ])
            ->map(static fn (object $row): array => [
                'provider_id' => (string) $row->smtp_provider_id,
                'action' => (string) $row->action,
                'reason' => (string) $row->reason,
                'occurred_at' => (string) $row->occurred_at,
                'actor' => is_string($row->actor_email) ? $row->actor_email : null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, host: string, port: int, scheme: ?string, username: ?string, password: ?string, from_address: string, from_name: string, is_enabled: bool, reason: string}  $input
     */
    public function save(User $actor, array $input, ?string $providerId = null): string
    {
        return DB::transaction(function () use ($actor, $input, $providerId): string {
            $existing = $providerId === null
                ? null
                : DB::table('smtp_providers')->where('id', $providerId)->lockForUpdate()->first();

            if ($providerId !== null && $existing === null) {
                abort(404);
            }

            $id = $providerId ?? (string) Str::ulid();
            $before = $existing === null ? null : $this->safeProvider((array) $existing);
            $password = $input['password'];

            if ($existing === null && (is_string($password) === false || $password === '')) {
                throw new \InvalidArgumentException('SMTP password is required when creating a provider.');
            }

            $payload = [
                'name' => trim($input['name']),
                'host' => trim($input['host']),
                'port' => $input['port'],
                'scheme' => $input['scheme'],
                'username' => $this->nullable(trim((string) ($input['username'] ?? ''))),
                'from_address' => trim($input['from_address']),
                'from_name' => trim($input['from_name']),
                'is_enabled' => $input['is_enabled'],
                'updated_at' => now(),
            ];

            if (is_string($password) && $password !== '') {
                $payload['password_ciphertext'] = Crypt::encryptString($password);
            }

            if ($existing === null) {
                DB::table('smtp_providers')->insert($payload + [
                    'id' => $id,
                    'last_tested_at' => null,
                    'last_test_status' => null,
                    'last_error_code' => null,
                    'created_at' => now(),
                ]);
            } else {
                DB::table('smtp_providers')->where('id', $id)->update($payload);
            }

            $afterRow = DB::table('smtp_providers')->where('id', $id)->first();
            $after = $afterRow === null ? null : $this->safeProvider((array) $afterRow);
            $this->audit($actor, $id, $existing === null ? 'created' : 'updated', $before, $after, $input['reason']);

            $this->purgeMailer($id);

            return $id;
        });
    }

    public function setEnabled(User $actor, string $providerId, bool $enabled, string $reason): void
    {
        DB::transaction(function () use ($actor, $providerId, $enabled, $reason): void {
            $row = DB::table('smtp_providers')->where('id', $providerId)->lockForUpdate()->first();
            if ($row === null) {
                abort(404);
            }

            $before = $this->safeProvider((array) $row);
            DB::table('smtp_providers')->where('id', $providerId)->update([
                'is_enabled' => $enabled,
                'updated_at' => now(),
            ]);
            $afterRow = DB::table('smtp_providers')->where('id', $providerId)->first();
            $after = $afterRow === null ? null : $this->safeProvider((array) $afterRow);
            $this->audit($actor, $providerId, $enabled ? 'enabled' : 'disabled', $before, $after, $reason);
            $this->purgeMailer($providerId);
        });
    }

    /** @return array{ok: bool, code: string, message: string, detail: ?string} */
    public function testProvider(User $actor, string $providerId, string $recipient, string $reason): array
    {
        $provider = $this->providerForDelivery($providerId);
        if ($provider === null) {
            abort(404);
        }

        $result = $this->sendTest($provider, $recipient);
        $this->recordTest(
            $actor,
            $providerId,
            $result['ok'] ? 'success' : 'failed',
            $result['ok'] ? null : $result['code'],
            $reason,
        );

        return $result;
    }

    /**
     * Test unsaved form values without mutating the provider tables.
     *
     * @param  array{host: string, port: int, scheme: ?string, username: ?string, password: ?string, from_address: string, from_name: string}  $input
     * @return array{ok: bool, code: string, message: string, detail: ?string}
     */
    public function testConfiguration(array $input, string $recipient, ?string $providerId = null): array
    {
        $password = $input['password'];
        if ((! is_string($password) || $password === '') && $providerId !== null) {
            $saved = $this->providerForDelivery($providerId);
            if ($saved === null) {
                return [
                    'ok' => false,
                    'code' => 'SAVED_PASSWORD_UNAVAILABLE',
                    'message' => 'The saved SMTP credential could not be read. Enter the password again and retry.',
                    'detail' => null,
                ];
            }
            $password = (string) $saved['password'];
        }

        if (! is_string($password) || $password === '') {
            return [
                'ok' => false,
                'code' => 'SMTP_PASSWORD_REQUIRED',
                'message' => 'An SMTP password is required before this configuration can be tested.',
                'detail' => null,
            ];
        }

        return $this->sendTest([
            'id' => 'preview-current-settings',
            'name' => 'Current SMTP settings',
            'host' => trim($input['host']),
            'port' => (int) $input['port'],
            'scheme' => $this->normalizeScheme($input['scheme']),
            'username' => $this->nullable((string) ($input['username'] ?? '')),
            'password' => $password,
            'from_address' => trim($input['from_address']),
            'from_name' => trim($input['from_name']),
        ], $recipient);
    }

    /**
     * Convert transport exceptions into stable, operator-safe diagnostics.
     *
     * @param  list<string>  $sensitiveValues
     * @return array{ok: false, code: string, message: string, detail: ?string}
     */
    public function diagnoseFailure(Throwable $exception, array $sensitiveValues = []): array
    {
        $raw = trim($exception->getMessage());
        $haystack = Str::lower(class_basename($exception).' '.$raw);

        [$code, $message] = match (true) {
            str_contains($haystack, 'getaddrinfo'),
            str_contains($haystack, 'php_network_getaddresses'),
            str_contains($haystack, 'name or service not known'),
            str_contains($haystack, 'nodename nor servname') => ['DNS_LOOKUP_FAILED', 'DNS lookup failed for the SMTP host. Check the host name and DNS.'],

            str_contains($haystack, 'timed out'),
            str_contains($haystack, 'timeout') => ['CONNECTION_TIMEOUT', 'The SMTP connection timed out. Check the host, port, firewall, and provider availability.'],

            str_contains($haystack, 'connection refused'),
            str_contains($haystack, 'actively refused') => ['CONNECTION_REFUSED', 'The SMTP server refused the connection. Check the host, port, and whether SMTP is enabled.'],

            str_contains($haystack, 'certificate'),
            str_contains($haystack, 'starttls'),
            str_contains($haystack, 'tls'),
            str_contains($haystack, 'ssl'),
            str_contains($haystack, 'crypto') => ['TLS_FAILURE', 'TLS negotiation failed. Check STARTTLS/SMTPS selection, port, and the server certificate.'],

            str_contains($haystack, 'authentication'),
            str_contains($haystack, 'authenticator'),
            str_contains($haystack, '535 '),
            str_contains($haystack, '530 ') => ['AUTH_REJECTED', 'SMTP authentication was rejected. Check the username, password, and account permissions.'],

            str_contains($haystack, 'recipient'),
            str_contains($haystack, 'rcpt to'),
            str_contains($haystack, '5.1.1') => ['RECIPIENT_REJECTED', 'The SMTP server rejected the test recipient address.'],

            str_contains($haystack, 'sender'),
            str_contains($haystack, 'mail from'),
            str_contains($haystack, 'from address') => ['SENDER_REJECTED', 'The SMTP server rejected the sender address. Check From address and sender authorization.'],

            default => ['TRANSPORT_EXCEPTION', 'The SMTP transport failed. Review the safe technical detail and Runtime Inspector.'],
        };

        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'detail' => $this->safeTechnicalDetail($raw, $sensitiveValues),
        ];
    }

    public function enabledProviderCount(): int
    {
        return DB::table('smtp_providers')->where('is_enabled', true)->count();
    }

    /** @return array<int, array<string, mixed>> */
    public function deliveryCandidates(): array
    {
        $providers = DB::table('smtp_providers')
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get()
            ->map(fn (object $row): ?array => $this->internalProvider((array) $row))
            ->filter()
            ->values()
            ->all();

        if (count($providers) < 2) {
            return $providers;
        }

        return (new Randomizer)->shuffleArray($providers);
    }

    /** @param  array<string, mixed>  $provider */
    public function configureMailer(array $provider): string
    {
        $name = $this->mailerName((string) $provider['id']);
        config()->set('mail.mailers.'.$name, [
            'transport' => 'smtp',
            'scheme' => $provider['scheme'],
            'url' => null,
            'host' => $provider['host'],
            'port' => $provider['port'],
            'username' => $provider['username'],
            'password' => $provider['password'],
            'timeout' => 10,
            'local_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
        ]);
        $this->mailManager()->purge($name);

        return $name;
    }

    /** @return array<string, mixed>|null */
    public function safeProviderById(string $providerId): ?array
    {
        $row = DB::table('smtp_providers')->where('id', $providerId)->first();

        return $row === null ? null : $this->safeProvider((array) $row);
    }

    /**
     * @param  array<string, mixed>  $provider
     * @return array{ok: bool, code: string, message: string, detail: ?string}
     */
    private function sendTest(array $provider, string $recipient): array
    {
        try {
            $mailer = $this->configureMailer($provider);
            $this->mailManager()->mailer($mailer)->raw(
                'MODRIK SMTP provider test. If you received this message, outbound email transport is working.',
                function ($message) use ($provider, $recipient): void {
                    $message->to($recipient)
                        ->from((string) $provider['from_address'], (string) $provider['from_name'])
                        ->subject('MODRIK SMTP test');
                },
            );

            return [
                'ok' => true,
                'code' => 'SMTP_TEST_SENT',
                'message' => 'The SMTP server accepted the test message.',
                'detail' => null,
            ];
        } catch (Throwable $exception) {
            return $this->diagnoseFailure($exception, [
                (string) ($provider['password'] ?? ''),
                (string) ($provider['username'] ?? ''),
            ]);
        } finally {
            $this->purgeMailer((string) $provider['id']);
        }
    }

    /** @return array<string, mixed>|null */
    private function providerForDelivery(string $providerId): ?array
    {
        $row = DB::table('smtp_providers')->where('id', $providerId)->first();

        return $row === null ? null : $this->internalProvider((array) $row);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    private function internalProvider(array $row): ?array
    {
        try {
            $password = Crypt::decryptString((string) ($row['password_ciphertext'] ?? ''));
        } catch (Throwable) {
            return null;
        }

        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'host' => (string) $row['host'],
            'port' => (int) $row['port'],
            'scheme' => $this->normalizeScheme($row['scheme'] ?? null),
            'username' => $this->nullable((string) ($row['username'] ?? '')),
            'password' => $password,
            'from_address' => (string) $row['from_address'],
            'from_name' => (string) $row['from_name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function safeProvider(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'host' => (string) $row['host'],
            'port' => (int) $row['port'],
            'scheme' => $this->normalizeScheme($row['scheme'] ?? null),
            'username' => $this->nullable((string) ($row['username'] ?? '')),
            'from_address' => (string) $row['from_address'],
            'from_name' => (string) $row['from_name'],
            'is_enabled' => (bool) $row['is_enabled'],
            'password_set' => (string) ($row['password_ciphertext'] ?? '') !== '',
            'last_tested_at' => isset($row['last_tested_at']) ? (string) $row['last_tested_at'] : null,
            'last_test_status' => $this->nullable((string) ($row['last_test_status'] ?? '')),
            'last_error_code' => $this->nullable((string) ($row['last_error_code'] ?? '')),
            'created_at' => isset($row['created_at']) ? (string) $row['created_at'] : null,
            'updated_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : null,
        ];
    }

    private function recordTest(User $actor, string $providerId, string $status, ?string $errorCode, string $reason): void
    {
        DB::transaction(function () use ($actor, $providerId, $status, $errorCode, $reason): void {
            $row = DB::table('smtp_providers')->where('id', $providerId)->lockForUpdate()->first();
            if ($row === null) {
                return;
            }

            $before = $this->safeProvider((array) $row);
            DB::table('smtp_providers')->where('id', $providerId)->update([
                'last_tested_at' => now(),
                'last_test_status' => $status,
                'last_error_code' => $errorCode,
                'updated_at' => now(),
            ]);
            $afterRow = DB::table('smtp_providers')->where('id', $providerId)->first();
            $after = $afterRow === null ? null : $this->safeProvider((array) $afterRow);
            $this->audit($actor, $providerId, $status === 'success' ? 'test_success' : 'test_failed', $before, $after, $reason);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    private function audit(User $actor, string $providerId, string $action, ?array $before, ?array $after, string $reason): void
    {
        DB::table('smtp_provider_audits')->insert([
            'id' => (string) Str::ulid(),
            'smtp_provider_id' => $providerId,
            'actor_id' => $actor->getKey(),
            'action' => $action,
            'before_state' => $this->encodeNullable($before),
            'after_state' => $this->encodeNullable($after),
            'reason' => trim($reason),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>|null  $value */
    private function encodeNullable(?array $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /** @param list<string> $sensitiveValues */
    private function safeTechnicalDetail(string $message, array $sensitiveValues): ?string
    {
        $message = trim((string) preg_replace('/[\r\n\t]+/', ' ', $message));
        $message = (string) preg_replace('/\s{2,}/', ' ', $message);
        $message = (string) preg_replace('/\b(password|passwd|secret|token)\s*[=:]\s*\S+/i', '$1=[redacted]', $message);
        $message = (string) preg_replace('#(smtps?://)[^@\s]+@#i', '$1[redacted]@', $message);

        foreach ($sensitiveValues as $sensitive) {
            if ($sensitive !== '' && mb_strlen($sensitive) >= 3) {
                $message = str_ireplace($sensitive, '[redacted]', $message);
            }
        }

        $message = mb_substr($message, 0, 320);

        return $message === '' ? null : $message;
    }

    private function normalizeScheme(mixed $scheme): ?string
    {
        return is_string($scheme) && Str::lower(trim($scheme)) === 'smtps' ? 'smtps' : null;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function mailerName(string $providerId): string
    {
        return 'modrik_smtp_'.Str::lower(preg_replace('/[^A-Za-z0-9]/', '', $providerId) ?? $providerId);
    }

    private function purgeMailer(string $providerId): void
    {
        $this->mailManager()->purge($this->mailerName($providerId));
    }

    private function mailManager(): MailManager
    {
        $manager = app('mail.manager');
        if (($manager instanceof MailManager) === false) {
            throw new \RuntimeException('Mail manager is unavailable.');
        }

        return $manager;
    }
}
