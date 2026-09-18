<?php

namespace Tests\Feature;

use App\Exceptions\ContentValidationException;
use App\Services\ContentPackArchiveValidator;
use Tests\TestCase;
use ZipArchive;

class ContentPackShortTextValidationTest extends TestCase
{
    public function test_whitespace_only_short_text_accepted_answer_is_rejected(): void
    {
        $pack = $this->fixtureJson('valid/content-pack.json');
        $pack['questions'][2]['answer_contract']['accepted_answers'] = [" \t "];

        $manifest = $this->fixtureJson('valid/manifest.json');
        $archivePath = $this->archivePath($manifest, $pack);

        try {
            app(ContentPackArchiveValidator::class)->validate($archivePath);
            $this->fail('Whitespace-only short-text accepted answers must fail closed.');
        } catch (ContentValidationException $exception) {
            $this->assertSame('CONTENT_SCHEMA_INVALID', $exception->errors[0]['code'] ?? null);
            $this->assertSame('/questions/2/answer_contract/accepted_answers', $exception->errors[0]['pointer'] ?? null);
        } finally {
            if (is_file($archivePath)) {
                $this->assertTrue(unlink($archivePath));
            }
        }
    }

    /** @return array<string, mixed> */
    private function fixtureJson(string $relativePath): array
    {
        $json = file_get_contents(base_path('../../tests/fixtures/content-pack/v1/'.$relativePath));
        $this->assertIsString($json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $pack
     */
    private function archivePath(array $manifest, array $pack): string
    {
        $packJson = json_encode($pack, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $manifest['archive_limits'] = [
            'declared_uncompressed_bytes' => strlen($packJson),
            'declared_file_count' => 1,
        ];
        $manifest['files'] = [[
            'path' => 'content-pack.json',
            'media_type' => 'application/json',
            'sha256' => hash('sha256', $packJson),
            'bytes' => strlen($packJson),
        ]];
        $manifestJson = json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $path = tempnam(sys_get_temp_dir(), 'modrik-short-text-');
        $this->assertIsString($path);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($zip->addFromString('manifest.json', $manifestJson));
        $this->assertTrue($zip->addFromString('content-pack.json', $packJson));
        $this->assertTrue($zip->close());

        return $path;
    }
}
