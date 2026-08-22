<?php

namespace App\Services;

use App\Exceptions\StaleSystemSettingVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SystemSettingsRegistry
{
    /**
     * Only non-secret, operator-manageable settings belong here.
     * OAuth/Firebase/API credentials remain external runtime secret material.
     *
     * @var array<string, array{type: 'boolean'|'integer'|'string', default: bool|int|string, group: string, rollback: bool}>
     */
    private const DEFINITIONS = [
        'auth.email.enabled' => ['type' => 'boolean', 'default' => true, 'group' => 'auth', 'rollback' => true],
        'auth.google.enabled' => ['type' => 'boolean', 'default' => false, 'group' => 'auth', 'rollback' => true],
        'auth.apple.enabled' => ['type' => 'boolean', 'default' => false, 'group' => 'auth', 'rollback' => true],
        'notifications.enabled' => ['type' => 'boolean', 'default' => true, 'group' => 'notifications', 'rollback' => true],
        'notifications.quiet_hours.enabled' => ['type' => 'boolean', 'default' => false, 'group' => 'notifications', 'rollback' => true],
        'notifications.quiet_hours.start' => ['type' => 'string', 'default' => '22:00', 'group' => 'notifications', 'rollback' => true],
        'notifications.quiet_hours.end' => ['type' => 'string', 'default' => '07:00', 'group' => 'notifications', 'rollback' => true],
        'firebase.fcm.enabled' => ['type' => 'boolean', 'default' => false, 'group' => 'firebase', 'rollback' => true],
        'firebase.remote_config.enabled' => ['type' => 'boolean', 'default' => false, 'group' => 'firebase', 'rollback' => true],
        'ads.global.enabled' => ['type' => 'boolean', 'default' => true, 'group' => 'ads', 'rollback' => true],
        'ads.test_mode.enabled' => ['type' => 'boolean', 'default' => true, 'group' => 'ads', 'rollback' => true],
    ];

    /**
     * @return array<string, array{type: 'boolean'|'integer'|'string', default: bool|int|string, group: string, rollback: bool}>
     */
    public function definitions(): array
    {
        return self::DEFINITIONS;
    }

    /**
     * @return array{key: string, environment: string, value_type: string, value: bool|int|string, version: int, persisted: bool, rollback_allowed: bool}
     */
    public function current(string $key, string $environment): array
    {
        $definition = $this->definition($key);
        $environment = $this->normalizeEnvironment($environment);
        $row = DB::table('system_settings')
            ->where('key', $key)
            ->where('environment', $environment)
            ->first();

        if ($row === null) {
            return [
                'key' => $key,
                'environment' => $environment,
                'value_type' => $definition['type'],
                'value' => $definition['default'],
                'version' => 0,
                'persisted' => false,
                'rollback_allowed' => $definition['rollback'],
            ];
        }

        return [
            'key' => $key,
            'environment' => $environment,
            'value_type' => (string) $row->value_type,
            'value' => $this->decodeValue((string) $row->value, $definition['type']),
            'version' => (int) $row->version,
            'persisted' => true,
            'rollback_allowed' => $definition['rollback'],
        ];
    }

    /**
     * @return array{key: string, environment: string, value_type: string, value: bool|int|string, version: int, persisted: bool, rollback_allowed: bool}
     */
    public function update(
        string $key,
        string $environment,
        bool|int|string $value,
        int $expectedVersion,
        string $reason,
        ?string $actorId,
    ): array {
        return $this->write($key, $environment, $value, $expectedVersion, $reason, $actorId, 'updated');
    }

    /**
     * @return array{key: string, environment: string, value_type: string, value: bool|int|string, version: int, persisted: bool, rollback_allowed: bool}
     */
    public function restore(
        string $key,
        string $environment,
        int $targetVersion,
        int $expectedVersion,
        string $reason,
        ?string $actorId,
    ): array {
        $definition = $this->definition($key);
        if (! $definition['rollback']) {
            throw new InvalidArgumentException('This setting does not permit rollback.');
        }

        $environment = $this->normalizeEnvironment($environment);
        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->where('environment', $environment)
            ->first(['id']);

        if ($setting === null) {
            throw new InvalidArgumentException('Cannot restore a setting that has no persisted history.');
        }

        $audit = DB::table('system_setting_audits')
            ->where('system_setting_id', (string) $setting->id)
            ->where('to_version', $targetVersion)
            ->orderByDesc('occurred_at')
            ->first(['after']);

        if ($audit === null) {
            throw new InvalidArgumentException('Requested setting version does not exist.');
        }

        $snapshot = json_decode((string) $audit->after, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($snapshot) || ! array_key_exists('value', $snapshot)) {
            throw new InvalidArgumentException('Requested setting version has an invalid audit snapshot.');
        }

        $value = $this->normalizeValue($snapshot['value'], $definition['type'], $key);

        return $this->write(
            $key,
            $environment,
            $value,
            $expectedVersion,
            $reason,
            $actorId,
            'restored',
        );
    }

    /**
     * @return array<int, array{action: string, from_version: int|null, to_version: int, before: mixed, after: mixed, reason: string, actor_id: string|null, occurred_at: string}>
     */
    public function history(string $key, string $environment, int $limit = 30): array
    {
        $this->definition($key);
        $environment = $this->normalizeEnvironment($environment);

        $setting = DB::table('system_settings')
            ->where('key', $key)
            ->where('environment', $environment)
            ->first(['id']);

        if ($setting === null) {
            return [];
        }

        return DB::table('system_setting_audits')
            ->where('system_setting_id', (string) $setting->id)
            ->orderByDesc('occurred_at')
            ->limit(max(1, min($limit, 100)))
            ->get(['action', 'from_version', 'to_version', 'before', 'after', 'reason', 'actor_id', 'occurred_at'])
            ->map(static fn (object $row): array => [
                'action' => (string) $row->action,
                'from_version' => $row->from_version === null ? null : (int) $row->from_version,
                'to_version' => (int) $row->to_version,
                'before' => $row->before === null ? null : json_decode((string) $row->before, true, 512, JSON_THROW_ON_ERROR),
                'after' => json_decode((string) $row->after, true, 512, JSON_THROW_ON_ERROR),
                'reason' => (string) $row->reason,
                'actor_id' => $row->actor_id === null ? null : (string) $row->actor_id,
                'occurred_at' => (string) $row->occurred_at,
            ])
            ->all();
    }

    /**
     * @return array{key: string, environment: string, value_type: string, value: bool|int|string, version: int, persisted: bool, rollback_allowed: bool}
     */
    private function write(
        string $key,
        string $environment,
        bool|int|string $value,
        int $expectedVersion,
        string $reason,
        ?string $actorId,
        string $action,
    ): array {
        $definition = $this->definition($key);
        $environment = $this->normalizeEnvironment($environment);
        $value = $this->normalizeValue($value, $definition['type'], $key);
        $reason = trim($reason);

        if ($expectedVersion < 0) {
            throw new InvalidArgumentException('Expected version cannot be negative.');
        }
        if (mb_strlen($reason) < 8 || mb_strlen($reason) > 500) {
            throw new InvalidArgumentException('A change reason between 8 and 500 characters is required.');
        }

        DB::transaction(function () use ($key, $environment, $definition, $value, $expectedVersion, $reason, $actorId, $action): void {
            $row = DB::table('system_settings')
                ->where('key', $key)
                ->where('environment', $environment)
                ->lockForUpdate()
                ->first();

            $currentVersion = $row === null ? 0 : (int) $row->version;
            if ($currentVersion !== $expectedVersion) {
                throw new StaleSystemSettingVersion($key, $expectedVersion, $currentVersion);
            }

            $now = now();
            $newVersion = $currentVersion + 1;
            $encodedValue = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $settingId = $row === null ? (string) Str::ulid() : (string) $row->id;
            $beforeValue = $row === null ? null : $this->decodeValue((string) $row->value, $definition['type']);

            if ($row === null) {
                DB::table('system_settings')->insert([
                    'id' => $settingId,
                    'key' => $key,
                    'environment' => $environment,
                    'value_type' => $definition['type'],
                    'value' => $encodedValue,
                    'version' => $newVersion,
                    'updated_by' => $actorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('system_settings')->where('id', $settingId)->update([
                    'value_type' => $definition['type'],
                    'value' => $encodedValue,
                    'version' => $newVersion,
                    'updated_by' => $actorId,
                    'updated_at' => $now,
                ]);
            }

            DB::table('system_setting_audits')->insert([
                'id' => (string) Str::ulid(),
                'system_setting_id' => $settingId,
                'actor_id' => $actorId,
                'action' => $action,
                'from_version' => $row === null ? null : $currentVersion,
                'to_version' => $newVersion,
                'before' => $row === null ? null : json_encode(['version' => $currentVersion, 'value' => $beforeValue], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'after' => json_encode(['version' => $newVersion, 'value' => $value], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'occurred_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        return $this->current($key, $environment);
    }

    /**
     * @return array{type: 'boolean'|'integer'|'string', default: bool|int|string, group: string, rollback: bool}
     */
    private function definition(string $key): array
    {
        if (! array_key_exists($key, self::DEFINITIONS)) {
            throw new InvalidArgumentException('Unknown or non-manageable system setting key.');
        }

        return self::DEFINITIONS[$key];
    }

    private function normalizeEnvironment(string $environment): string
    {
        $environment = strtolower(trim($environment));
        if ($environment === '' || strlen($environment) > 32 || preg_match('/^[a-z0-9][a-z0-9_-]*$/', $environment) !== 1) {
            throw new InvalidArgumentException('Invalid system setting environment scope.');
        }

        return $environment;
    }

    /** @param 'boolean'|'integer'|'string' $type */
    private function normalizeValue(mixed $value, string $type, string $key): bool|int|string
    {
        $normalized = match ($type) {
            'boolean' => is_bool($value) ? $value : null,
            'integer' => is_int($value) ? $value : null,
            'string' => is_string($value) ? trim($value) : null,
        };

        if ($normalized === null) {
            throw new InvalidArgumentException(sprintf('Invalid value type for system setting "%s".', $key));
        }

        if (is_string($normalized) && mb_strlen($normalized) > 255) {
            throw new InvalidArgumentException(sprintf('System setting "%s" exceeds the maximum string length.', $key));
        }

        if (in_array($key, ['notifications.quiet_hours.start', 'notifications.quiet_hours.end'], true)
            && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', (string) $normalized) !== 1) {
            throw new InvalidArgumentException('Quiet-hours values must use HH:MM 24-hour format.');
        }

        return $normalized;
    }

    /** @param 'boolean'|'integer'|'string' $type */
    private function decodeValue(string $json, string $type): bool|int|string
    {
        return $this->normalizeValue(json_decode($json, true, 512, JSON_THROW_ON_ERROR), $type, 'persisted-setting');
    }
}
