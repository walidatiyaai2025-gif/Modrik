<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPreparationWizard;
use App\Filament\Pages\ContentReviewQueue;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

class ContentAdminDestructiveConfirmationTest extends TestCase
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

    public function test_regeneration_first_click_and_cancel_do_not_mutate_and_confirm_runs_once(): void
    {
        $operator = $this->operator('admin');
        $workflow = app(ContentAdminWorkflowService::class);
        $payload = $this->requestPayload();
        $settings = $payload['settings'];
        $created = $workflow->createRequest($operator, $payload);
        $requestId = (string) $created['preparation_request_id'];
        $initialStatus = DB::table('preparation_requests')->where('id', $requestId)->value('status');
        $initialRequestCount = DB::table('preparation_requests')->count();
        $scope = $settings['academic_scope'];
        $generation = $settings['generation'];

        $this->actingAs($operator);
        $component = Livewire::test(ContentPreparationWizard::class)
            ->set('preparationRequestId', $requestId)
            ->set('requestResult', $created)
            ->set('locales', $settings['locales'])
            ->set('trackReference', $scope['track_reference'])
            ->set('boardReference', $scope['board_reference'] ?? '')
            ->set('syllabusVersion', $scope['syllabus_version'] ?? '')
            ->set('yearLevel', $scope['year_level'])
            ->set('subjectReferences', implode("\n", $scope['subject_references']))
            ->set('contentTypes', $settings['content_types'])
            ->set('includeAnswerExplanations', $generation['include_answer_explanations'])
            ->set('maximumQuestionsPerQuiz', 11);

        $component->call('requestRegeneration')
            ->assertSet('pendingRegenerationRequestId', $requestId);

        $this->assertSame($initialRequestCount, DB::table('preparation_requests')->count());
        $this->assertSame($initialStatus, DB::table('preparation_requests')->where('id', $requestId)->value('status'));

        $component->call('cancelRegeneration')
            ->assertSet('pendingRegenerationRequestId', null);

        $this->assertSame($initialRequestCount, DB::table('preparation_requests')->count());
        $this->assertSame($initialStatus, DB::table('preparation_requests')->where('id', $requestId)->value('status'));

        $component->call('requestRegeneration')
            ->call('confirmRegeneration')
            ->assertSet('pendingRegenerationRequestId', null);

        $this->assertSame($initialRequestCount + 1, DB::table('preparation_requests')->count());
        $this->assertSame('superseded', DB::table('preparation_requests')->where('id', $requestId)->value('status'));
        $replacementId = DB::table('preparation_requests')->where('id', $requestId)->value('superseded_by_request_id');
        $this->assertIsString($replacementId);
        $this->assertNotSame('', $replacementId);

        $component->call('confirmRegeneration');

        $this->assertSame($initialRequestCount + 1, DB::table('preparation_requests')->count());
        $this->assertSame($replacementId, DB::table('preparation_requests')->where('id', $requestId)->value('superseded_by_request_id'));
    }

    public function test_publication_first_click_and_cancel_do_not_mutate_and_confirm_publishes_once(): void
    {
        $operator = $this->operator('content_team');
        $workflow = app(ContentAdminWorkflowService::class);
        [$importId] = $this->stagedAndValidatedImport($workflow, $operator);
        $workflow->review($operator, $importId, 'approved');
        $imported = $workflow->importReviewed($operator, $importId);
        $publicationId = (string) $imported['publication_id'];
        $initialPublicationEvents = DB::table('outbox_events')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.official_content_published')
            ->count();

        $this->actingAs($operator);
        $component = Livewire::test(ContentReviewQueue::class);

        $component->call('requestPublication', $importId)
            ->assertSet('pendingPublicationImportId', $importId);

        $this->assertDatabaseHas('preparation_imports', ['id' => $importId, 'status' => 'imported']);
        $this->assertDatabaseHas('content_publications', ['id' => $publicationId, 'status' => 'imported']);
        $this->assertSame($initialPublicationEvents, DB::table('outbox_events')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.official_content_published')
            ->count());

        $component->call('cancelPublication')
            ->assertSet('pendingPublicationImportId', null);

        $this->assertDatabaseHas('preparation_imports', ['id' => $importId, 'status' => 'imported']);
        $this->assertDatabaseHas('content_publications', ['id' => $publicationId, 'status' => 'imported']);

        $component->call('requestPublication', $importId)
            ->call('confirmPublication')
            ->assertSet('pendingPublicationImportId', null);

        $this->assertDatabaseHas('preparation_imports', ['id' => $importId, 'status' => 'published']);
        $this->assertDatabaseHas('content_publications', ['id' => $publicationId, 'status' => 'published']);
        $this->assertSame($initialPublicationEvents + 1, DB::table('outbox_events')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.official_content_published')
            ->count());
        $this->assertSame(1, DB::table('content_workflow_audits')
            ->where('preparation_import_id', $importId)
            ->where('action', 'official_content_published')
            ->count());

        $component->call('confirmPublication');

        $this->assertSame($initialPublicationEvents + 1, DB::table('outbox_events')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.official_content_published')
            ->count());
        $this->assertSame(1, DB::table('content_publications')->where('preparation_import_id', $importId)->count());
    }

    /** @return array{0: string, 1: string} */
    private function stagedAndValidatedImport(ContentAdminWorkflowService $workflow, User $operator): array
    {
        $created = $workflow->createRequest($operator, $this->requestPayload());
        $requestId = (string) $created['preparation_request_id'];
        $stage = $workflow->stageReturnedArchive(
            $operator,
            $requestId,
            $this->archiveUpload($requestId, (string) $created['settings_hash']),
        );
        $importId = (string) $stage['data']['preparation_import_id'];
        $workflow->dryRun($operator, $importId);

        return [$importId, $requestId];
    }

    private function operator(string $role): User
    {
        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update(['role' => $role]);

        return User::query()->findOrFail(LearningSliceSeeder::USER_ID);
    }

    /** @return array<string, mixed> */
    private function requestPayload(): array
    {
        return [
            'schema_version' => '1.0.0',
            'settings' => $this->fixtureJson('valid/preparation-settings.json'),
        ];
    }

    private function archiveUpload(string $requestId, string $settingsHash): UploadedFile
    {
        $manifest = $this->fixtureJson('valid/manifest.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $pack = $this->fixtureJson('valid/content-pack.json');
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
        $path = tempnam(sys_get_temp_dir(), 'modrik-admin-confirm-');
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
