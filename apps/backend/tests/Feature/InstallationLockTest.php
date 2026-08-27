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
}
