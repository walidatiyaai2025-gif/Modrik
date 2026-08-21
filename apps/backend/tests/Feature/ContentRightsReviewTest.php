<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use App\Services\ContentPreparationService;
use App\Services\ContentRightsReviewService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

final class ContentRightsReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
        ]);
        $this->seed(LearningSliceSeeder::class);
        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update(['role' => 'content_team']);
        $operator = User::query()->findOrFail(LearningSliceSeeder::USER_ID);
        $this->operator = $operator;
    }

    public function test_non_synthetic_archive_stages_into_rights_review_instead_of_being_rejected(): void
    {
        $preparation = app(ContentPreparationService::class)->create($this->operator, $this->requestPayload());
        $archive = $this->realRightsArchive(
            (string) $preparation['preparation_request_id'],
            (string) $preparation['settings_hash'],
        );

        $result = app(ContentPreparationService::class)->stage($this->operator, $archive);

        $this->assertTrue($result['accepted']);
        $this->assertSame('rights_review', $result['data']['status']);
        $importId = (string) $result['data']['preparation_import_id'];
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'rights_review',
            'rights_status' => 'pending_review',
            'rights_review_status' => 'pending',
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'rights_review_required',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $importId,
            'event_type' => 'content.rights_review_required',
        ]);
        $eventPayload = DB::table('outbox_events')
            ->where('aggregate_id', $importId)
            ->where('event_type', 'content.rights_review_required')
            ->value('payload');
        $this->assertIsString($eventPayload);
        $this->assertStringContainsString('KW-MOE:ARABIC:GRADE-6:2025-2026-T1-P1', $eventPayload);
        $this->assertDatabaseMissing('outbox_events', [
            'aggregate_id' => $importId,
            'event_type' => 'content.preparation_import_rejected',
        ]);

        try {
            app(ContentAdminWorkflowService::class)->dryRun($this->operator, $importId);
            $this->fail('Rights-pending content must not reach dry-run.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_WORKFLOW_STATE_INVALID', $exception->problemCode);
        }
    }

    public function test_rights_approval_requires_basis_and_evidence_and_unblocks_staging_with_audit(): void
    {
        $importId = $this->pendingRightsImport();
        $rights = app(ContentRightsReviewService::class);

        try {
            $rights->review($this->operator, $importId, 'approved', 'rights-evidence://documented-reference', 'Looks plausible.');
            $this->fail('Rights approval without an explicit basis must fail closed.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_RIGHTS_BASIS_REQUIRED', $exception->problemCode);
        }

        try {
            $rights->review($this->operator, $importId, 'approved', null, 'Looks plausible.', 'licensed');
            $this->fail('Rights approval without evidence must fail closed.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_RIGHTS_EVIDENCE_REQUIRED', $exception->problemCode);
        }

        $result = $rights->review(
            $this->operator,
            $importId,
            'approved',
            'rights-evidence://documented-owner-or-license-reference',
            'Evidence reviewed for the declared content scope.',
            'licensed',
        );

        $this->assertSame('approved', $result['rights_review_status']);
        $this->assertSame('licensed', $result['rights_basis']);
        $this->assertSame('staged', $result['status']);
        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'staged',
            'rights_review_status' => 'approved',
            'rights_basis' => 'licensed',
            'operation_state' => 'ready',
            'operation_checkpoint' => 'rights_review_approved',
        ]);
        $this->assertDatabaseHas('content_workflow_audits', [
            'preparation_import_id' => $importId,
            'action' => 'rights_review_approved',
            'from_status' => 'rights_review',
            'to_status' => 'staged',
        ]);
        $this->assertDatabaseHas('outbox_events', [
            'aggregate_id' => $importId,
            'event_type' => 'content.rights_reviewed',
        ]);
    }

    public function test_rights_rejection_requires_reason_and_stays_blocked(): void
    {
        $importId = $this->pendingRightsImport();
        $rights = app(ContentRightsReviewService::class);

        try {
            $rights->review($this->operator, $importId, 'rejected', null, null);
            $this->fail('Rights rejection without a reason must fail closed.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_RIGHTS_REJECTION_REASON_REQUIRED', $exception->problemCode);
        }

        $rights->review($this->operator, $importId, 'rejected', null, 'No documented permission for this source.');

        $this->assertDatabaseHas('preparation_imports', [
            'id' => $importId,
            'status' => 'rights_review',
            'rights_review_status' => 'rejected',
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'rights_review_rejected',
        ]);

        try {
            app(ContentAdminWorkflowService::class)->dryRun($this->operator, $importId);
            $this->fail('Rights-rejected content must remain blocked from dry-run.');
        } catch (ApiProblemException $exception) {
            $this->assertSame('CONTENT_WORKFLOW_STATE_INVALID', $exception->problemCode);
        }
    }

    /** @return array<string, mixed> */
    private function requestPayload(): array
    {
        $json = file_get_contents(base_path('../../tests/fixtures/content-pack/v1/valid/preparation-settings.json'));
        $this->assertIsString($json);
        $settings = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($settings);

        return ['schema_version' => '1.0.0', 'settings' => $settings];
    }

    private function realRightsArchive(string $requestId, string $settingsHash): UploadedFile
    {
        $manifest = $this->fixtureJson('manifest.json');
        $pack = $this->fixtureJson('content-pack.json');
        $manifest['preparation_request_id'] = $requestId;
        $manifest['settings_hash'] = $settingsHash;
        $manifest['provenance'] = [
            'rights_status' => 'pending_review',
            'contains_student_pii' => false,
            'source_references' => ['KW-MOE:ARABIC:GRADE-6:2025-2026-T1-P1'],
        ];
        $content = json_encode($pack, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $manifest['archive_limits']['declared_file_count'] = 1;
        $manifest['archive_limits']['declared_uncompressed_bytes'] = strlen($content);
        $manifest['files'] = [[
            'path' => 'content-pack.json',
            'media_type' => 'application/json',
            'sha256' => hash('sha256', $content),
            'bytes' => strlen($content),
        ]];

        $path = tempnam(sys_get_temp_dir(), 'modrik-rights-');
        $this->assertIsString($path);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $zip->addFromString('content-pack.json', $content);
        $zip->close();

        return new UploadedFile($path, 'returned-content.zip', 'application/zip', null, true);
    }

    /** @return array<string, mixed> */
    private function fixtureJson(string $name): array
    {
        $json = file_get_contents(base_path('../../tests/fixtures/content-pack/v1/valid/'.$name));
        $this->assertIsString($json);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($decoded);

        return $decoded;
    }

    private function pendingRightsImport(): string
    {
        $preparation = app(ContentPreparationService::class)->create($this->operator, $this->requestPayload());
        $id = '01J11111111111111111111111';
        DB::table('preparation_imports')->insert([
            'id' => $id,
            'uploaded_by' => (string) $this->operator->getKey(),
            'preparation_request_id' => (string) $preparation['preparation_request_id'],
            'claimed_preparation_request_id' => (string) $preparation['preparation_request_id'],
            'archive_hash' => str_repeat('a', 64),
            'status' => 'rights_review',
            'rights_status' => 'pending_review',
            'rights_review_status' => 'pending',
            'validation_summary' => json_encode(['valid' => true, 'errors' => []], JSON_THROW_ON_ERROR),
            'imported_file_count' => 1,
            'imported_record_count' => 1,
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'rights_review_required',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }
}
