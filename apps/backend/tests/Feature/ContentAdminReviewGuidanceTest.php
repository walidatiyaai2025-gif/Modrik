<?php

namespace Tests\Feature;

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

class ContentAdminReviewGuidanceTest extends TestCase
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

    public function test_review_actions_gate_reject_and_request_fix_until_reason_while_approve_stays_optional(): void
    {
        $operator = $this->operator('admin');
        $workflow = app(ContentAdminWorkflowService::class);
        [$guardedImportId] = $this->stagedAndValidatedImport($workflow, $operator, 11);

        $this->actingAs($operator);
        $component = Livewire::test(ContentReviewQueue::class)
            ->assertSee(__('admin.review.reason_requirement'))
            ->assertSee(__('admin.review.current_step', ['status' => __('admin.status.validated')]))
            ->assertSee(__('admin.review.next_step', ['status' => __('admin.status.reviewed')]))
            ->assertSeeHtml('aria-disabled="true"');

        $component
            ->set('reasons.'.$guardedImportId, '   ')
            ->assertSeeHtml('aria-disabled="true"')
            ->set('reasons.'.$guardedImportId, 'Correct the returned metadata before sending a replacement ZIP.')
            ->assertSeeHtml('aria-disabled="false"')
            ->call('requestFix', $guardedImportId);

        $this->assertDatabaseHas('preparation_imports', [
            'id' => $guardedImportId,
            'status' => 'reviewed',
            'review_decision' => 'request_fix',
            'review_reason' => 'Correct the returned metadata before sending a replacement ZIP.',
        ]);

        [$approvedImportId] = $this->stagedAndValidatedImport($workflow, $operator, 12);

        Livewire::test(ContentReviewQueue::class)
            ->call('approve', $approvedImportId);

        $this->assertDatabaseHas('preparation_imports', [
            'id' => $approvedImportId,
            'status' => 'reviewed',
            'review_decision' => 'approved',
            'review_reason' => null,
        ]);
    }

    public function test_lifecycle_imported_draft_boundary_and_stable_code_remediation_are_visible_and_localized(): void
    {
        $operator = $this->operator('content_team');
        $workflow = app(ContentAdminWorkflowService::class);
        [$importedId] = $this->stagedAndValidatedImport($workflow, $operator, 13);
        $workflow->review($operator, $importedId, 'approved');
        $workflow->importReviewed($operator, $importedId);
        DB::table('preparation_imports')->where('id', $importedId)->update([
            'operation_state' => 'failed',
            'operation_checkpoint' => 'publish_failed',
            'last_error_code' => 'CONTENT_SNAPSHOT_HASH_MISMATCH',
            'last_error_at' => now(),
            'updated_at' => now(),
        ]);

        [$blockedId] = $this->stagedImport($workflow, $operator, 14);
        DB::table('preparation_imports')->where('id', $blockedId)->update([
            'dry_run_summary' => json_encode([
                'publishable' => false,
                'counts' => [],
                'blocking_codes' => ['CONTENT_TARGET_TRACK_MISSING'],
            ], JSON_THROW_ON_ERROR),
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'dry_run_blocked',
            'updated_at' => now(),
        ]);

        $englishImportedTitle = trans('admin.review.imported_draft_title', [], 'en');
        $englishSnapshotGuidance = trans('admin.review.remediation.CONTENT_SNAPSHOT_HASH_MISMATCH', [], 'en');
        $englishTrackGuidance = trans('admin.review.remediation.CONTENT_TARGET_TRACK_MISSING', [], 'en');
        $arabicImportedTitle = trans('admin.review.imported_draft_title', [], 'ar');
        $frenchImportedTitle = trans('admin.review.imported_draft_title', [], 'fr');

        $this->actingAs($operator);
        $component = Livewire::test(ContentReviewQueue::class)
            ->assertSee(__('admin.review.lifecycle_title'))
            ->assertSee(__('admin.review.current_step', ['status' => __('admin.status.imported')]))
            ->assertSee(__('admin.review.next_step', ['status' => __('admin.status.published')]))
            ->assertSee($englishImportedTitle)
            ->assertSee('CONTENT_SNAPSHOT_HASH_MISMATCH')
            ->assertSee($englishSnapshotGuidance)
            ->assertSee('CONTENT_TARGET_TRACK_MISSING')
            ->assertSee($englishTrackGuidance)
            ->assertSeeHtml('dir="ltr"');

        $component
            ->call('setLocale', 'ar')
            ->assertSee($arabicImportedTitle)
            ->assertSeeHtml('dir="rtl"')
            ->call('setLocale', 'fr')
            ->assertSee($frenchImportedTitle)
            ->assertSeeHtml('dir="ltr"');
    }

    public function test_known_remediation_codes_have_ar_en_fr_copy(): void
    {
        $codes = [
            'PREPARATION_REGENERATION_REQUIRED',
            'CONTENT_TARGET_TRACK_MISSING',
            'CONTENT_TARGET_TRACK_SCOPE_MISMATCH',
            'CONTENT_TARGET_NODE_AMBIGUOUS',
            'CONTENT_IMMUTABLE_REFERENCE_CONFLICT',
            'CONTENT_IMMUTABLE_ID_CONFLICT',
            'CONTENT_SCHEMA_VERSION_MISMATCH',
            'CONTENT_SETTINGS_HASH_MISSING',
            'CONTENT_SNAPSHOT_HASH_MISMATCH',
            'CONTENT_VALIDATED_SNAPSHOT_REQUIRED',
            'CONTENT_DRY_RUN_REQUIRED',
            'CONTENT_DRY_RUN_STALE',
            'CONTENT_APPROVAL_REQUIRED',
            'CONTENT_CANONICAL_IMPORT_REQUIRED',
            'CONTENT_PUBLICATION_STATE_INVALID',
            'CONTENT_REFERENCE_INVALID',
            'CONTENT_NODE_DEPENDENCY_INVALID',
            'CONTENT_REVIEW_REASON_REQUIRED',
            'CONTENT_REVIEW_REASON_TOO_LONG',
        ];

        foreach (['ar', 'en', 'fr'] as $locale) {
            foreach ($codes as $code) {
                $key = 'admin.review.remediation.'.$code;
                $this->assertNotSame($key, trans($key, [], $locale), $locale.' missing '.$code);
            }
        }
    }

    /** @return array{0: string, 1: string} */
    private function stagedAndValidatedImport(ContentAdminWorkflowService $workflow, User $operator, int $maximumQuestions): array
    {
        [$importId, $requestId] = $this->stagedImport($workflow, $operator, $maximumQuestions);
        $workflow->dryRun($operator, $importId);

        return [$importId, $requestId];
    }

    /** @return array{0: string, 1: string} */
    private function stagedImport(ContentAdminWorkflowService $workflow, User $operator, int $maximumQuestions): array
    {
        $created = $workflow->createRequest($operator, $this->requestPayload($maximumQuestions));
        $requestId = (string) $created['preparation_request_id'];
        $stage = $workflow->stageReturnedArchive(
            $operator,
            $requestId,
            $this->archiveUpload($requestId, (string) $created['settings_hash']),
        );

        return [(string) $stage['data']['preparation_import_id'], $requestId];
    }

    private function operator(string $role): User
    {
        DB::table('users')->where('id', LearningSliceSeeder::USER_ID)->update(['role' => $role]);

        return User::query()->findOrFail(LearningSliceSeeder::USER_ID);
    }

    /** @return array<string, mixed> */
    private function requestPayload(int $maximumQuestions): array
    {
        $settings = $this->fixtureJson('valid/preparation-settings.json');
        $settings['generation']['maximum_questions_per_quiz'] = $maximumQuestions;

        return [
            'schema_version' => '1.0.0',
            'settings' => $settings,
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
        $path = tempnam(sys_get_temp_dir(), 'modrik-admin-guidance-');
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
