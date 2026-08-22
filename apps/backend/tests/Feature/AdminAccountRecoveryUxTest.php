<?php

namespace Tests\Feature;

use App\Filament\Pages\AccountOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminAccountRecoveryUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_recovery_requires_visible_reason_and_confirmation_contract(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $target = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin);

        Livewire::test(AccountOperations::class)
            ->call('selectAccount', (string) $target->id)
            ->assertSee('Specific reason (required)')
            ->assertSee('Confirm revocation of every active session for this account?')
            ->assertSee('Revoke all sessions');
    }
}
