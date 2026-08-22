<?php

namespace Tests\Feature;

use Tests\TestCase;

final class AdminReleaseIdentityTest extends TestCase
{
    public function test_admin_login_renders_the_persisted_deployed_release_identity(): void
    {
        $release = '0123456789abcdef0123456789abcdef01234567';
        $path = storage_path('app/modrik-release.txt');
        $directory = dirname($path);
        $hadExisting = is_file($path);
        $existing = $hadExisting ? file_get_contents($path) : null;

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $release."\n");

        try {
            $this->get('/admin/login')
                ->assertOk()
                ->assertSee('data-testid="modrik-release-badge"', false)
                ->assertSee('MODRIK deployed release: '.$release, false)
                ->assertSee('Build '.substr($release, 0, 12));
        } finally {
            if ($hadExisting && is_string($existing)) {
                file_put_contents($path, $existing);
            } else {
                @unlink($path);
            }
        }
    }
}
