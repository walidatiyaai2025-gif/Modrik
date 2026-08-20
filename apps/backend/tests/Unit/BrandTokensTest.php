<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BrandTokensTest extends TestCase
{
    public function test_canonical_brand_tokens_are_readable(): void
    {
        $path = dirname(__DIR__, 4).'/packages/design-tokens/tokens.json';

        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);
        $tokens = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('MODRIK | مُدرك', $tokens['meta']['brand']);
        self::assertSame('#00BFA6', $tokens['color']['brand']['teal']['$value']);
    }
}
