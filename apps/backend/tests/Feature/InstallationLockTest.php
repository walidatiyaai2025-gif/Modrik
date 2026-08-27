<?php

namespace Tests\Feature;

use App\Services\InstallationStateService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class InstallationLockTest extends TestCase
{
    public function test_installer_is_not_publicly_reenterable_after_lock(): void
    {
        $state = app(InstallationStateService::class);
        @unlink($state->lockPath());
        Route::middleware('install.uninstalled')->get('/_test-installer', fn () => 'installer');
        $this->get('/_test-installer')->assertOk();
        $state->lock(str_repeat('b', 40));
        $this->get('/_test-installer')->assertNotFound();
        @unlink($state->lockPath());
    }

    public function test_finish_screen_requires_completed_lock_and_one_time_session_handoff(): void
    {
        $path = sys_get_temp_dir().'/modrik-install-lock-'.bin2hex(random_bytes(8));
        config(['installer.lock_path' => $path]);
        $state = app(InstallationStateService::class);
        $state->lock(str_repeat('c', 40));
        $token = $state->issueCompletionToken();

        $this->get('/install/finish?token='.$token)
            ->assertOk()
            ->assertSee('Finish · Step 8 of 8');
        $this->get('/install/finish?token='.$token)->assertRedirect('/admin/login');
        @unlink($path);
    }
}
