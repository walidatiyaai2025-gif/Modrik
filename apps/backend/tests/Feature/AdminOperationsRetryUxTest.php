<?php

namespace Tests\Feature;

use App\Filament\Pages\OperationsControlCenter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminOperationsRetryUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_control_center_exposes_safe_health_retry_without_generic_recovery_controls(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(OperationsControlCenter::class)
            ->assertSee('Retry health checks')
            ->assertDontSee('Run shell')
            ->assertDontSee('Execute SQL')
            ->assertDontSee('Edit queue payload');
    }
}
