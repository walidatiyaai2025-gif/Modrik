<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use JsonException;
use Tests\TestCase;
use ZipArchive;

class ContentAdminPublicationWorkflowTest extends TestCase
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

    public function test_only_admin_or_content_team_can_operate_official_content_workflow(): void
    {
        $student = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $workflow = app(ContentAdminWorkflowService::class);

        try {
            $workflow->createRequest($student, $this->requestPayload());
            $this->fail('Student must not create an official preparation workflow.');
        } catch (ApiProblemException $exception) {
            $this->assertSame(403, $exception->status);
            $this->assertSame('CONTENT_ROLE_REQUIRED', $exception->problemCode);
        }

        $this->assertDatabaseCount('preparation_requests', 0);
        $this->assertDatabaseCount('content_publications', 0);
    }

    public function test_valid_synthetic_fixture_moves_through_staged_validated_reviewed_imported_and_published_idempotently(): void
    {
        $operator = $this->operator('content_team');
        $workflow = app(ContentAdminWorkflowService::class);
        $created = $workflow->createRequest($operator, $this->requestPayload());
        $requestId = (string) $created['preparation_request_id'];
        $stage = $workflow->stageReturnedArchive(
            $operator,
            $requestId,
            $this->archiveUpload($requestId, (string) $created['settings_hash']),
        );

        $this->assertTrue($stage['accepted']);
        $importId = (string) $stage['data']['preparation_import_id'];
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'staged',
            'operation_checkpoint' => 'archive_staged',
        ]);
        $this->assertDatabaseHas('content_workflow_audits', [
            'preparation_import_id' => $importId,
            'action' => 'archive_staged',
            'to_status' => 'staged',
        ]);

        $dryRun = $workflow->dryRun($operator, $importId);
        $this->assertTrue($dryRun['publishable']);
        $this->assertSame([], $dryRun['blocking_codes']);
        $this->assertSame('validated', $dryRun['status']);
        $this->assertSame(2, $dryRun['counts']['curriculum_nodes']['reuse']);
        $this->assertSame(1, $dryRun['counts']['lessons']['reuse']);
        $this->assertSame(3, $dryRun['counts']['questions']['reuse']);
        $this->assertSame(1, $dryRun['counts']['quizzes']['reuse']);

        $review = $workflow->review($operator, $importId, 'approved', 'Synthetic fixture verified for publication workflow coverage.');
        $this->assertSame('reviewed', $review['status']);
        $this->assertSame('approved', $review['decision']);

        $curriculumCounts = $this->curriculumCounts();
        $imported = $workflow->importReviewed($operator, $importId);
        $this->assertSame('imported', $imported['status']);
        $this->assertFalse($imported['replayed']);
        $this->assertSame($curriculumCounts, $this->curriculumCounts(), 'Reused fixture rows must not be duplicated by canonical import.');
        $this->assertDatabaseHas('preparation_imports', ['id' => $importId, 'status' => 'imported']);
        $this->assertSame(7, DB::table('content_publication_items')->where('content_publication_id', $imported['publication_id'])->count());

        $published = $workflow->publish($operator, $importId);
        $this->assertSame('published', $published['status']);
        $this->assertFalse($published['replayed']);
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'published',
            'operation_state' => 'succeeded',
            'operation_checkpoint' => 'official_content_published',
        ]);
        $this->assertDatabaseHas('content_publications', [
            'id' => $published['publication_id'],
            'preparation_import_id' => $importId,
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('content_workflow_audits', [
            'preparation_import_id' => $importId,
            'action' => 'canonical_draft_imported',
            'from_status' => 'reviewed',
            'to_status' => 'imported',
        ]);
        $this->assertDatabaseHas('content_workflow_audits', [
            'preparation_import_id' => $importId,
            'action' => 'official_content_published',
            'from_status' => 'imported',
            'to_status' => 'published',
        ]);
        $this->assertSame(1, DB::table('outbox_events')->where('aggregate_id', $importId)->where('event_type', 'content.official_content_published')->count());

        $replayed = $workflow->publish($operator, $importId);
        $this->assertTrue($replayed['replayed']);
        $this->assertSame($published['publication_id'], $replayed['publication_id']);
        $this->assertSame(1, DB::table('content_publications')->where('preparation_import_id', $importId)->count());
        $this->assertSame(1, DB::table('outbox_events')->where('aggregate_id', $importId)->where('event_type', 'content.official_content_published')->count());
    }

    public function test_returned_zip_must_be_uploaded_to_its_originating_request(): void
    {
        $operator = $this->operator('admin');
        $workflow = app(ContentAdminWorkflowService::class);
        $origin = $workflow->createRequest($operator, $this->requestPayload());
        $otherPayload = $this->requestPayload();
        $otherPayload['settings']['generation']['maximum_questions_per_quiz'] = 9;
        $other = $workflow->createRequest($operator, $otherPayload);

        try {
            $workflow->stageReturnedArchive(
                $operator,
                (string) $origin['preparation_request_id'],
                $this->archiveUpload((string) $other['preparation_request_id'], (string) $other['settings_hash']),
            );
            $this->fail('A returned archive from another request must fail before staging.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_ORIGIN_REQUEST_MISMATCH', $exception->problemCode);
        }

        $this->assertDatabaseCount('preparation_imports', 0);
        $this->assertDatabaseCount('content_workflow_audits', 0);
    }

    public function test_changed_settings_supersede_nonpublished_work_and_make_old_request_visibly_stale(): void
    {
        $operator = $this->operator('content_team');
        $workflow = app(ContentAdminWorkflowService::class);
        $created = $workflow->createRequest($operator, $this->requestPayload());
        $requestId = (string) $created['preparation_request_id'];
        $stage = $workflow->stageReturnedArchive(
            $operator,
            $requestId,
            $this->archiveUpload($requestId, (string) $created['settings_hash']),
        );
        $importId = (string) $stage['data']['preparation_import_id'];

        $changed = $this->requestPayload();
        $changed['settings']['generation']['maximum_questions_per_quiz'] = 9;
        $replacement = $workflow->regenerateRequest($operator, $requestId, $changed);

        $this->assertNotSame($requestId, $replacement['preparation_request_id']);
        $this->assertNotSame($created['settings_hash'], $replacement['settings_hash']);
        $this->assertDatabaseHas('preparation_requests', [
            'id' => $requestId,
            'status' => 'superseded',
            'superseded_by_request_id' => $replacement['preparation_request_id'],
        ]);
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'superseded',
            'operation_state' => 'stale',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $requestId,
            'event_type' => 'content.preparation_superseded',
        ]);

        try {
            $workflow->dryRun($operator, $importId);
            $this->fail('A stale import must require regeneration.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('PREPARATION_REGENERATION_REQUIRED', $exception->problemCode);
        }
    }

    public function test_reject_and_request_fix_require_operator_reason_and_cannot_publish(): void
    {
        $operator = $this->operator('admin');
        $workflow = app(ContentAdminWorkflowService::class);
        [$importId] = $this->stagedAndValidatedImport($workflow, $operator);

        try {
            $workflow->review($operator, $importId, 'request_fix');
            $this->fail('Request-fix requires an operator reason.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_REVIEW_REASON_REQUIRED', $exception->problemCode);
        }

        $workflow->review($operator, $importId, 'request_fix', 'Correct the synthetic fixture metadata before returning a new ZIP.');
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'reviewed',
            'review_decision' => 'request_fix',
        ]);

        try {
            $workflow->importReviewed($operator, $importId);
            $this->fail('Request-fix imports must not enter canonical draft import.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_APPROVAL_REQUIRED', $exception->problemCode);
        }
        $this->assertDatabaseCount('content_publication_items', 0);
        $this->assertDatabaseCount('content_publications', 1);
        $this->assertDatabaseHas('content_publications', ['preparation_import_id' => $importId, 'status' => 'failed']);
    }

    public function test_failed_operation_records_sanitized_checkpoint_and_can_retry_after_safe_repair(): void
    {
        $operator = $this->operator('content_team');
        $workflow = app(ContentAdminWorkflowService::class);
        [$importId] = $this->stagedAndValidatedImport($workflow, $operator);
        $workflow->review($operator, $importId, 'approved');
        $originalHash = (string) DB::table('preparation_imports')->where('id', $importId)->value('content_hash');

        DB::table('preparation_imports')->where('id', $importId)->update(['content_hash' => str_repeat('0', 64)]);
        try {
            $workflow->importReviewed($operator, $importId);
            $this->fail('A changed validated snapshot hash must fail closed.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_SNAPSHOT_HASH_MISMATCH', $exception->problemCode);
        }

        $failed = DB::table('content_publications')->where('preparation_import_id', $importId)->first();
        $this->assertNotNull($failed);
        $this->assertSame('failed', $failed->status);
        $this->assertSame('CONTENT_SNAPSHOT_HASH_MISMATCH', $failed->last_error_code);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $failed->last_error_fingerprint);
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'reviewed',
            'operation_state' => 'failed',
            'operation_checkpoint' => 'import_failed',
            'last_error_code' => 'CONTENT_SNAPSHOT_HASH_MISMATCH',
        ]);
        $this->assertSame(0, DB::table('content_publication_items')->count());

        DB::table('preparation_imports')->where('id', $importId)->update(['content_hash' => $originalHash]);
        $retried = $workflow->importReviewed($operator, $importId);
        $this->assertSame('imported', $retried['status']);
        $this->assertGreaterThanOrEqual(2, $retried['attempt_count']);
        $this->assertNull($retried['last_error_code']);
    }

    public function test_arbitrary_or_ugc_identifier_has_no_path_to_official_publication(): void
    {
        $operator = $this->operator('admin');
        $workflow = app(ContentAdminWorkflowService::class);

        try {
            $workflow->publish($operator, '01J00000000000000000000999');
            $this->fail('Only a preparation import may be published.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_CANONICAL_IMPORT_REQUIRED', $exception->problemCode);
        }

        $this->assertDatabaseCount('content_publications', 0);
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

    /** @param array<string, string> $entries */
    private function zipBytes(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'modrik-admin-content-');
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

    /** @return array<string, int> */
    private function curriculumCounts(): array
    {
        return [
            'tracks' => DB::table('academic_tracks')->count(),
            'nodes' => DB::table('curriculum_nodes')->count(),
            'lessons' => DB::table('lessons')->count(),
            'questions' => DB::table('questions')->count(),
            'quizzes' => DB::table('quizzes')->count(),
        ];
    }

    /** @throws JsonException */
    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
