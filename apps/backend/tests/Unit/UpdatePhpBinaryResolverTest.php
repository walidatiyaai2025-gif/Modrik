<?php

namespace Tests\Unit;

use App\Services\Updates\UpdatePhpBinaryResolver;
use RuntimeException;
use Tests\TestCase;

final class UpdatePhpBinaryResolverTest extends TestCase
{
    public function test_explicit_compatible_php_binary_is_selected(): void
    {
        config([
            'updates.php_binary' => PHP_BINARY,
            'updates.minimum_php_version' => PHP_VERSION,
        ]);

        $this->assertSame(PHP_BINARY, app(UpdatePhpBinaryResolver::class)->resolve());
    }

    public function test_explicit_incompatible_php_binary_fails_closed(): void
    {
        config([
            'updates.php_binary' => PHP_BINARY,
            'updates.minimum_php_version' => '99.0.0',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('update_php_binary_incompatible');

        app(UpdatePhpBinaryResolver::class)->resolve();
    }
}
