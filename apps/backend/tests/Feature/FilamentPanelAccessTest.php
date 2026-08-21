<?php

namespace Tests\Feature;

use App\Models\User;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Mockery;
use Tests\TestCase;

final class FilamentPanelAccessTest extends TestCase
{
    public function test_active_admin_and_content_team_users_can_access_filament_panel(): void
    {
        $panel = Mockery::mock(Panel::class);

        foreach (['admin', 'content_team'] as $role) {
            $user = new User([
                'role' => $role,
                'account_status' => 'active',
                'deleted_at' => null,
            ]);

            $this->assertInstanceOf(FilamentUser::class, $user);
            $this->assertTrue($user->canAccessPanel($panel));
        }
    }

    public function test_non_admin_inactive_or_deleted_users_cannot_access_filament_panel(): void
    {
        $panel = Mockery::mock(Panel::class);

        $cases = [
            ['role' => 'learner', 'account_status' => 'active', 'deleted_at' => null],
            ['role' => 'admin', 'account_status' => 'suspended', 'deleted_at' => null],
            ['role' => 'content_team', 'account_status' => 'active', 'deleted_at' => now()],
        ];

        foreach ($cases as $attributes) {
            $user = new User($attributes);
            $this->assertFalse($user->canAccessPanel($panel));
        }
    }
}
