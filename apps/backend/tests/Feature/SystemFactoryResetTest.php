<?php

namespace Tests\Feature;

use App\Filament\Pages\SystemFactoryReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class SystemFactoryResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_reset_page_is_admin_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/system-factory-reset')
            ->assertOk()
            ->assertSee('System Factory Reset')
            ->assertSee('data-testid="modrik-system-factory-reset"', false);

        $this->actingAs($student)
            ->get('/admin/system-factory-reset')
            ->assertForbidden();
    }

    public function test_factory_reset_removes_application_data_and_preserves_installation_state(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'email' => 'owner@modrik.test',
        ]);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $trackId = (string) Str::ulid();
        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'TRACK:RESET-TEST',
            'board_reference' => 'BOARD:TEST',
            'syllabus_version' => 'SYLLABUS:TEST',
            'year_level' => 'YEAR:6',
            'title' => json_encode(['en' => 'Reset test', 'ar' => 'اختبار'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => false,
            'availability_state' => 'published',
            'display_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('preparation_requests')->insert([
            'id' => (string) Str::ulid(),
            'created_by' => $admin->getKey(),
            'schema_version' => '1.0.0',
            'settings_hash' => str_repeat('a', 64),
            'normalized_settings' => json_encode(['test' => true], JSON_THROW_ON_ERROR),
            'prompt' => 'old content request',
            'status' => 'ready',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_settings')->insert([
            'id' => (string) Str::ulid(),
            'key' => 'test.preserved',
            'environment' => 'testing',
            'value_type' => 'boolean',
            'value' => json_encode(true, JSON_THROW_ON_ERROR),
            'version' => 1,
            'updated_by' => $admin->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('system_update_history')->insert([
            'id' => (string) Str::ulid(),
            'initiated_by' => $admin->getKey(),
            'from_version' => '0.1.0',
            'to_version' => '0.1.1',
            'release_sha' => str_repeat('b', 40),
            'status' => 'SUCCESS',
            'package_storage_key' => null,
            'safe_details' => null,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(SystemFactoryReset::class)
            ->set('confirmationEmail', 'owner@modrik.test')
            ->set('confirmationPhrase', 'RESET MODRIK')
            ->set('currentPassword', 'password')
            ->set('acknowledgePermanentDeletion', true)
            ->call('factoryReset')
            ->assertHasNoErrors();

        self::assertSame(1, User::query()->count());
        self::assertTrue(User::query()->whereKey($admin->getKey())->exists());
        self::assertFalse(User::query()->whereKey($student->getKey())->exists());

        self::assertSame(0, DB::table('academic_tracks')->count());
        self::assertSame(0, DB::table('preparation_requests')->count());

        self::assertSame(1, DB::table('system_settings')->count());
        self::assertSame(1, DB::table('system_update_history')->count());
        self::assertSame(1, DB::table('system_factory_reset_audits')->count());

        $audit = DB::table('system_factory_reset_audits')->first();
        self::assertNotNull($audit);
        self::assertSame((string) $admin->getKey(), (string) $audit->actor_id);
        self::assertGreaterThanOrEqual(2, (int) $audit->rows_deleted);
    }
}
