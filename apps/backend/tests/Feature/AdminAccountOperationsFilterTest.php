<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminAccountOperationsFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_and_session_filters_are_database_backed_and_composable(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $googleUser = User::factory()->create([
            'name' => 'Google Filter Learner',
            'role' => 'student',
            'account_status' => 'active',
            'password_enabled' => false,
        ]);
        $passwordUser = User::factory()->create([
            'name' => 'Password Filter Learner',
            'role' => 'student',
            'account_status' => 'active',
            'password_enabled' => true,
        ]);
        $now = now();

        DB::table('auth_provider_identities')->insert([
            'id' => (string) Str::ulid(),
            'user_id' => $googleUser->id,
            'provider' => 'google',
            'provider_subject' => 'google-filter-subject',
            'provider_email_normalized' => null,
            'provider_email_verified' => true,
            'provider_email_is_relay' => false,
            'linked_at' => $now,
            'last_seen_at' => $now,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('auth_sessions')->insert([
            [
                'id' => (string) Str::ulid(),
                'user_id' => $googleUser->id,
                'token_hash' => hash('sha256', 'google-filter-session'),
                'name' => 'google',
                'ip_hash' => null,
                'user_agent_hash' => null,
                'authenticated_at' => $now,
                'last_used_at' => $now,
                'expires_at' => $now->copy()->addHour(),
                'revoked_at' => null,
                'revoke_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => (string) Str::ulid(),
                'user_id' => $passwordUser->id,
                'token_hash' => hash('sha256', 'password-filter-session'),
                'name' => 'password',
                'ip_hash' => null,
                'user_agent_hash' => null,
                'authenticated_at' => $now,
                'last_used_at' => $now,
                'expires_at' => $now->copy()->addHour(),
                'revoked_at' => $now,
                'revoke_reason' => 'test_fixture',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(AccountOperations::class)
            ->set('providerFilter', 'google')
            ->set('sessionFilter', 'active')
            ->assertSee('Google Filter Learner')
            ->assertDontSee('Password Filter Learner')
            ->set('providerFilter', 'password')
            ->set('sessionFilter', 'revoked')
            ->assertSee('Password Filter Learner')
            ->assertDontSee('Google Filter Learner');
    }
}
