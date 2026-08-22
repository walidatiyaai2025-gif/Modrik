<?php

namespace Tests\Feature;

use App\Filament\Pages\AcademicCatalogue;
use App\Filament\Pages\ContentReviewQueue;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class AdminAcademicCatalogueRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.idempotency.secret' => 'test-only-idempotency-secret',
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_missing_track_blocker_links_to_prefilled_catalogue_and_clears_after_admin_registration(): void
    {
        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update(['role' => 'admin']);
        $admin = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $workflow = app(ContentAdminWorkflowService::class);

        $settings = $this->fixtureJson('valid/preparation-settings.json');
        $settings['academic_scope']['track_reference'] = 'OWNER:TRACK:CATALOGUE-GATE';
        $settings['academic_scope']['board_reference'] = 'OWNER-BOARD-CATALOGUE-GATE';
        $settings['academic_scope']['syllabus_version'] = 'OWNER-SYLLABUS-CATALOGUE-GATE';
        $settings['academic_scope']['year_level'] = 'OWNER-YEAR-CATALOGUE-GATE';

        $created = $workflow->createRequest($admin, [
            'schema_version' => '1.0.0',
            'settings' => $settings,
        ]);
        $requestId = (string) $created['preparation_request_id'];

        $pack = $this->fixtureJson('valid/content-pack.json');
        $pack['academic_scope'] = $settings['academic_scope'];
        $stage = $workflow->stageReturnedArchive(
            $admin,
            $requestId,
            $this->archiveUpload($requestId, (string) $created['settings_hash'], $pack),
        );
        $importId = (string) $stage['data']['preparation_import_id'];

        $blocked = $workflow->dryRun($admin, $importId);
        $this->assertFalse($blocked['publishable']);
        $this->assertContains('CONTENT_TARGET_TRACK_MISSING', $blocked['blocking_codes']);

        $this->actingAs($admin);
        $catalogueUrl = AcademicCatalogue::getUrl(['request' => $requestId]);
        /** @var class-string<Component> $reviewQueueComponent */
        $reviewQueueComponent = ContentReviewQueue::class;
        Livewire::test($reviewQueueComponent)
            ->assertSee('CONTENT_TARGET_TRACK_MISSING')
            ->assertSee(AcademicCatalogue::getNavigationLabel())
            ->assertSeeHtml('href="'.e($catalogueUrl).'"');

        /** @var class-string<Component> $catalogueComponent */
        $catalogueComponent = AcademicCatalogue::class;
        Livewire::withQueryParams(['request' => $requestId])
            ->test($catalogueComponent)
            ->assertSet('sourceRequestId', $requestId)
            ->assertSet('form.code', 'OWNER:TRACK:CATALOGUE-GATE')
            ->assertSet('form.board_reference', 'OWNER-BOARD-CATALOGUE-GATE')
            ->assertSet('form.syllabus_version', 'OWNER-SYLLABUS-CATALOGUE-GATE')
            ->assertSet('form.year_level', 'OWNER-YEAR-CATALOGUE-GATE')
            ->set('form.title_en', 'Owner approved catalogue gate track')
            ->set('form.title_ar', 'مسار معتمد لمعالجة بوابة الكتالوج')
            ->set('form.title_fr', 'Parcours approuvé pour le catalogue')
            ->set('form.reason', 'Register owner-approved track required by preparation request.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('academic_tracks', [
            'code' => 'OWNER:TRACK:CATALOGUE-GATE',
            'board_reference' => 'OWNER-BOARD-CATALOGUE-GATE',
            'syllabus_version' => 'OWNER-SYLLABUS-CATALOGUE-GATE',
            'year_level' => 'OWNER-YEAR-CATALOGUE-GATE',
            'is_fixture' => false,
        ]);

        $afterRegistration = $workflow->dryRun($admin, $importId);
        $this->assertNotContains('CONTENT_TARGET_TRACK_MISSING', $afterRegistration['blocking_codes']);
    }

    /** @param array<string, mixed> $pack */
    private function archiveUpload(string $requestId, string $settingsHash, array $pack): UploadedFile
    {
        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $packJson = $this->encodeJson($pack);
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

        return UploadedFile::fake()->createWithContent('returned-content.zip', $this->zipBytes([
            'manifest.json' => $this->encodeJson($manifest),
            'content-pack.json' => $packJson,
        ]));
    }

    /** @return array<string, mixed> */
    private function fixtureJson(string $relativePath): array
    {
        $path = base_path('../../tests/fixtures/content-pack/v1/'.$relativePath);
        $json = file_get_contents($path);
        $this->assertIsString($json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    /** @param array<string, mixed> $payload */
    private function encodeJson(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, string> $entries */
    private function zipBytes(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'modrik-academic-remediation-');
        $this->assertIsString($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        foreach ($entries as $name => $contents) {
            $this->assertTrue($zip->addFromString($name, $contents));
        }
        $this->assertTrue($zip->close());
        $bytes = file_get_contents($path);
        $this->assertIsString($bytes);
        $this->assertTrue(unlink($path));

        return $bytes;
    }
}
