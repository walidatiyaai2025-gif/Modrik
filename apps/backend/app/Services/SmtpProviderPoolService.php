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
    /** @return list<array<string, mixed>> */
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

    /** @return list<array<string, mixed>> */
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

    /** @return array{ok: bool, code: string} */
    public function testProvider(User $actor, string $providerId, string $recipient, string $reason): array
    {
        $provider = $this->providerForDelivery($providerId);
        if ($provider === null) {
            abort(404);
        }

        try {
            $mailer = $this->configureMailer($provider);
            $this->mailManager()->mailer($mailer)->raw(
                'MODRIK SMTP provider test. If you received this message, outbound email transport is working.',
                function ($message) use ($provider, $recipient): void {
                    $message->to($recipient)
                        ->from($provider['from_address'], $provider['from_name'])
                        ->subject('MODRIK SMTP test');
                },
            );
            $this->recordTest($actor, $providerId, 'success', null, $reason);

            return ['ok' => true, 'code' => 'SMTP_TEST_SENT'];
        } catch (Throwable $exception) {
            $code = $this->safeExceptionCode($exception);
            $this->recordTest($actor, $providerId, 'failed', $code, $reason);

            return ['ok' => false, 'code' => $code];
        }
    }

    public function enabledProviderCount(): int
    {
        return DB::table('smtp_providers')->where('is_enabled', true)->count();
    }

    /** @return list<array<string, mixed>> */
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

    /** @param array<string, mixed> $provider */
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

    /** @return null|array<string, mixed> */
    public function safeProviderById(string $providerId): ?array
    {
        $row = DB::table('smtp_providers')->where('id', $providerId)->first();

        return $row === null ? null : $this->safeProvider((array) $row);
    }

    private function providerForDelivery(string $providerId): ?array
    {
        $row = DB::table('smtp_providers')->where('id', $providerId)->first();

        return $row === null ? null : $this->internalProvider((array) $row);
    }

    /** @param array<string, mixed> $row */
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

    /** @param array<string, mixed> $row */
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
     * @param  null|array<string, mixed>  $before
     * @param  null|array<string, mixed>  $after
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

    /** @param null|array<string, mixed> $value */
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

    private function safeExceptionCode(Throwable $exception): string
    {
        return Str::upper(Str::snake(class_basename($exception)));
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
