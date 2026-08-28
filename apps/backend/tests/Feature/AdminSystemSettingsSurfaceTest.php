<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemSettingsRegistry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminSystemSettingsSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_discover_grouped_non_secret_system_settings_surface(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->get('/admin/system-settings')
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Authentication')
            ->assertSee('Notifications')
            ->assertSee('Firebase / runtime')
            ->assertSee('Advertising & safety')
            ->assertSee('OAuth/Firebase secrets are never stored here')
            ->assertSee('data-testid="modrik-system-settings"', false);
    }

    public function test_non_admin_cannot_access_system_settings_surface(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'account_status' => 'active',
        ]);

        $this->actingAs($student)
            ->get('/admin/system-settings')
            ->assertForbidden();
    }

    public function test_admin_change_creates_versioned_setting_and_audit_without_secret_material(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        Livewire::test(SystemSettingsRegistry::class)
            ->set('values.auth__google__enabled', true)
            ->set('reasons.auth__google__enabled', 'Enable the approved Google sign-in switch for this environment.')
            ->call('saveSetting', 'auth.google.enabled')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('system_settings', [
            'key' => 'auth.google.enabled',
            'environment' => app()->environment(),
            'version' => 1,
        ]);
        $this->assertDatabaseHas('system_setting_audits', [
            'action' => 'updated',
            'from_version' => null,
            'to_version' => 1,
            'actor_id' => $admin->id,
        ]);

        $serialized = json_encode([
            DB::table('system_settings')->get()->toArray(),
            DB::table('system_setting_audits')->get()->toArray(),
        ], JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('client_secret', $serialized);
        $this->assertStringNotContainsString('private_key', $serialized);
        $this->assertStringNotContainsString('password', $serialized);
    }

    public function test_save_button_uses_visible_confirmation_and_persists_after_confirmation(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        $component = Livewire::test(SystemSettingsRegistry::class)
            ->set('values.ads__global__enabled', false)
            ->set('reasons.ads__global__enabled', 'Disable advertising during the controlled acceptance window.')
            ->call('requestSave', 'ads.global.enabled')
            ->assertSet('pendingSaveKey', 'ads.global.enabled')
            ->assertSee('Confirm saving this change?')
            ->call('confirmSave')
            ->assertSet('pendingSaveKey', '')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('system_settings', [
            'key' => 'ads.global.enabled',
            'environment' => app()->environment(),
            'version' => 1,
            'value' => 'false',
        ]);
        $this->assertDatabaseHas('system_setting_audits', [
            'action' => 'updated',
            'to_version' => 1,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_save_button_shows_reason_error_instead_of_silently_doing_nothing(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        Livewire::test(SystemSettingsRegistry::class)
            ->set('values.auth__apple__enabled', true)
            ->set('reasons.auth__apple__enabled', 'short')
            ->call('requestSave', 'auth.apple.enabled')
            ->assertSet('pendingSaveKey', '')
            ->assertHasErrors(['reasons.auth__apple__enabled'])
            ->assertSee('Enter a change reason between 8 and 500 characters before saving.');

        $this->assertDatabaseMissing('system_settings', [
            'key' => 'auth.apple.enabled',
            'environment' => app()->environment(),
        ]);
    }

    public function test_restore_from_ui_creates_new_version_instead_of_rewriting_history(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        $component = Livewire::test(SystemSettingsRegistry::class)
            ->set('values.notifications__enabled', false)
            ->set('reasons.notifications__enabled', 'Disable notifications for a controlled operational test.')
            ->call('saveSetting', 'notifications.enabled')
            ->set('values.notifications__enabled', true)
            ->set('reasons.notifications__enabled', 'Re-enable notifications after the operational test.')
            ->call('saveSetting', 'notifications.enabled')
            ->call('selectHistory', 'notifications.enabled')
            ->set('reasons.notifications__enabled', 'Restore the prior disabled state as a new audited version.')
            ->call('restoreSelected', 1)
            ->assertHasNoErrors();

        $component->assertSet('versions.notifications__enabled', 3);
        $this->assertSame(3, DB::table('system_setting_audits')->count());
        $this->assertDatabaseHas('system_setting_audits', [
            'action' => 'restored',
            'from_version' => 2,
            'to_version' => 3,
        ]);
    }
}
