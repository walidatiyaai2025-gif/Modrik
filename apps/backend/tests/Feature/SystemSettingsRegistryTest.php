<?php

namespace Tests\Feature;

use App\Exceptions\StaleSystemSettingVersion;
use App\Services\SystemSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

final class SystemSettingsRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_setting_has_typed_default_until_first_persisted_version(): void
    {
        $current = app(SystemSettingsRegistry::class)->current('auth.email.enabled', 'demo');

        $this->assertSame(true, $current['value']);
        $this->assertSame('boolean', $current['value_type']);
        $this->assertSame(0, $current['version']);
        $this->assertFalse($current['persisted']);
    }

    public function test_update_creates_new_version_and_immutable_audit_snapshot(): void
    {
        $registry = app(SystemSettingsRegistry::class);

        $current = $registry->update(
            'auth.email.enabled',
            'demo',
            false,
            0,
            'Disable email login for controlled Demo verification.',
            null,
        );

        $this->assertSame(false, $current['value']);
        $this->assertSame(1, $current['version']);
        $this->assertTrue($current['persisted']);
        $this->assertDatabaseHas('system_settings', [
            'key' => 'auth.email.enabled',
            'environment' => 'demo',
            'version' => 1,
        ]);
        $this->assertDatabaseHas('system_setting_audits', [
            'action' => 'updated',
            'from_version' => null,
            'to_version' => 1,
        ]);

        $history = $registry->history('auth.email.enabled', 'demo');
        $this->assertSame(1, $history[0]['to_version']);
        $this->assertSame(false, $history[0]['after']['value']);
    }

    public function test_stale_expected_version_fails_closed_without_new_audit(): void
    {
        $registry = app(SystemSettingsRegistry::class);
        $registry->update('notifications.enabled', 'demo', false, 0, 'Initial controlled notification setting change.', null);

        try {
            $registry->update('notifications.enabled', 'demo', true, 0, 'Attempt a stale settings overwrite from another operator.', null);
            $this->fail('Expected stale settings update to fail.');
        } catch (StaleSystemSettingVersion $exception) {
            $this->assertSame('notifications.enabled', $exception->settingKey);
            $this->assertSame(0, $exception->expectedVersion);
            $this->assertSame(1, $exception->currentVersion);
        }

        $this->assertSame(1, DB::table('system_setting_audits')->count());
        $this->assertSame(false, $registry->current('notifications.enabled', 'demo')['value']);
    }

    public function test_restore_creates_a_new_version_without_rewriting_history(): void
    {
        $registry = app(SystemSettingsRegistry::class);
        $registry->update('firebase.fcm.enabled', 'demo', true, 0, 'Enable FCM for a controlled Demo integration test.', null);
        $registry->update('firebase.fcm.enabled', 'demo', false, 1, 'Disable FCM after the controlled integration test.', null);

        $restored = $registry->restore(
            'firebase.fcm.enabled',
            'demo',
            1,
            2,
            'Restore the previously validated FCM Demo configuration.',
            null,
        );

        $this->assertSame(true, $restored['value']);
        $this->assertSame(3, $restored['version']);
        $this->assertSame(3, DB::table('system_setting_audits')->count());
        $this->assertDatabaseHas('system_setting_audits', [
            'action' => 'restored',
            'from_version' => 2,
            'to_version' => 3,
        ]);
    }

    public function test_unknown_or_secret_like_keys_cannot_enter_normal_settings_rows(): void
    {
        $registry = app(SystemSettingsRegistry::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown or non-manageable');

        try {
            $registry->update(
                'auth.google.client_secret',
                'production',
                'must-never-be-persisted',
                0,
                'Attempt to persist a provider secret through normal settings.',
                null,
            );
        } finally {
            $this->assertDatabaseCount('system_settings', 0);
            $this->assertDatabaseCount('system_setting_audits', 0);
        }
    }

    public function test_quiet_hours_are_typed_and_validated(): void
    {
        $registry = app(SystemSettingsRegistry::class);
        $saved = $registry->update(
            'notifications.quiet_hours.start',
            'demo',
            '21:30',
            0,
            'Set a valid quiet-hours start for Demo notification testing.',
            null,
        );

        $this->assertSame('21:30', $saved['value']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('HH:MM');
        $registry->update(
            'notifications.quiet_hours.end',
            'demo',
            '27:99',
            0,
            'Reject an invalid quiet-hours end time.',
            null,
        );
    }
}
