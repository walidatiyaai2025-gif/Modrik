<?php

namespace Tests\Feature;

use App\Exceptions\ApiProblemException;
use App\Filament\Pages\QuestionBankWorkbench;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use App\Services\QuestionBankWorkbenchService;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class QuestionBankWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private QuestionBankWorkbenchService $workbench;

    /** @var array<string, mixed> */
    private array $preparation;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.idempotency.secret' => 'question-bank-workbench-test-secret',
        ]);
        $this->seed(LearningSliceSeeder::class);

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);
        $this->workbench = app(QuestionBankWorkbenchService::class);
        $this->preparation = app(ContentAdminWorkflowService::class)->createRequest(
            $this->admin,
            [
                'schema_version' => '1.0.0',
                'settings' => $this->preparationSettings(),
            ],
        );

        DB::table('curriculum_nodes')->insert([
            'id' => '01J00000000000000000000035',
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'parent_id' => LearningSliceSeeder::TOPIC_NODE_ID,
            'code' => 'FIXTURE:SKILL:ADDITION',
            'type' => 'skill',
            'title' => json_encode([
                'en' => 'Addition',
                'ar' => 'الجمع',
                'fr' => 'Addition',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_workbench_is_discoverable_localized_and_restricted_to_content_roles(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/question-bank-workbench')
            ->assertOk()
            ->assertSee('Question Bank Workbench')
            ->assertSee('modrik-question-bank-v1')
            ->assertSee('data-testid="modrik-question-bank-workbench"', false);

        $contentTeam = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $this->actingAs($contentTeam)->get('/admin/question-bank-workbench')->assertOk();

        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);
        $this->actingAs($student)->get('/admin/question-bank-workbench')->assertForbidden();

        App::setLocale('ar');
        self::assertSame('منضدة بنك الأسئلة', QuestionBankWorkbench::getNavigationLabel());
        App::setLocale('fr');
        self::assertSame('Atelier banque de questions', QuestionBankWorkbench::getNavigationLabel());
    }

    public function test_valid_manual_pack_is_bound_mapped_rights_gated_and_published_to_canonical_question_authority(): void
    {
        $stage = $this->workbench->stage($this->admin, $this->encodeJson($this->validPack()));
        self::assertSame('needs_review', $stage['import']['status']);
        self::assertSame('mapped', $stage['items'][0]['scope_state']);
        self::assertSame('pending', $stage['sources'][0]['rights_status']);

        $importId = (string) $stage['import']['id'];
        $this->workbench->review($this->admin, $importId, 'approved', 'Reviewed against the supplied source and curriculum mapping.');

        $this->expectProblem(
            fn (): array => $this->workbench->publish($this->admin, $importId),
            'QUESTION_BANK_RIGHTS_APPROVAL_REQUIRED',
        );

        $sourceId = (string) $stage['sources'][0]['id'];
        $this->workbench->updateSourceMaterial(
            $this->admin,
            $sourceId,
            'pdf',
            'library://fixture/addition-source.pdf',
            'approved',
            'Owner-authorized source for the controlled Question Bank test.',
        );

        $published = $this->workbench->publish($this->admin, $importId);
        self::assertSame('published', $published['import']['status']);
        self::assertNotNull($published['items'][0]['canonical_question_id']);

        $questionId = (string) $published['items'][0]['canonical_question_id'];
        $this->assertDatabaseHas('questions', [
            'id' => $questionId,
            'curriculum_node_id' => '01J00000000000000000000035',
            'type' => 'multiple_choice',
            'difficulty' => 'Easy',
            'status' => 'published',
            'review_state' => 'approved',
        ]);
        $this->assertDatabaseHas('learning_objectives', [
            'skill_node_id' => '01J00000000000000000000035',
            'status' => 'published',
        ]);

        $provenance = DB::table('questions')->where('id', $questionId)->value('source_provenance');
        self::assertIsString($provenance);
        self::assertStringContainsString('"source_page":12', $provenance);
        self::assertStringContainsString('"question_bank_import_id":"'.$importId.'"', $provenance);
        $this->assertDatabaseHas('question_bank_workflow_audits', [
            'question_bank_import_id' => $importId,
            'action' => 'published',
            'to_status' => 'published',
        ]);
    }

    public function test_revoking_source_rights_auto_suspends_student_delivery_and_republish_requires_rights_again(): void
    {
        $stage = $this->publishReadyPack();
        $importId = (string) $stage['import']['id'];
        $sourceId = (string) $stage['sources'][0]['id'];
        $questionId = (string) $stage['items'][0]['canonical_question_id'];

        $this->workbench->updateSourceMaterial(
            $this->admin,
            $sourceId,
            'book',
            'library://fixture/book-1',
            'rejected',
            'Rights were withdrawn for this source.',
        );

        $this->assertDatabaseHas('question_bank_imports', ['id' => $importId, 'status' => 'suspended']);
        $this->assertDatabaseHas('questions', ['id' => $questionId, 'status' => 'suspended']);
        $this->assertDatabaseHas('question_bank_workflow_audits', [
            'question_bank_import_id' => $importId,
            'action' => 'rights_auto_suspended',
            'to_status' => 'suspended',
        ]);

        $this->expectProblem(
            fn (): array => $this->workbench->publish($this->admin, $importId),
            'QUESTION_BANK_RIGHTS_APPROVAL_REQUIRED',
        );

        $this->workbench->updateSourceMaterial(
            $this->admin,
            $sourceId,
            'book',
            'library://fixture/book-1',
            'approved',
            'Rights evidence was renewed and approved.',
        );
        $republished = $this->workbench->publish($this->admin, $importId);
        self::assertSame('published', $republished['import']['status']);
        $this->assertDatabaseHas('questions', ['id' => $questionId, 'status' => 'published']);
    }

    public function test_unpublish_and_archive_preserve_history_but_remove_question_from_deliverable_state(): void
    {
        $published = $this->publishReadyPack();
        $importId = (string) $published['import']['id'];
        $questionId = (string) $published['items'][0]['canonical_question_id'];

        $unpublished = $this->workbench->unpublish(
            $this->admin,
            $importId,
            'Return this pack to approved review state for controlled correction.',
        );
        self::assertSame('approved', $unpublished['import']['status']);
        $this->assertDatabaseHas('questions', ['id' => $questionId, 'status' => 'draft']);

        $republished = $this->workbench->publish($this->admin, $importId);
        self::assertSame($questionId, $republished['items'][0]['canonical_question_id']);

        $archived = $this->workbench->archive(
            $this->admin,
            $importId,
            'Archive this pack while retaining provenance and audit history.',
        );
        self::assertSame('archived', $archived['import']['status']);
        $this->assertDatabaseHas('questions', ['id' => $questionId, 'status' => 'archived']);
        self::assertGreaterThanOrEqual(
            1,
            DB::table('question_bank_workflow_audits')->where('question_bank_import_id', $importId)->count(),
        );
    }

    public function test_duplicate_questions_and_binding_mismatches_fail_closed_with_persisted_evidence(): void
    {
        $first = $this->workbench->stage($this->admin, $this->encodeJson($this->validPack()));
        self::assertSame('needs_review', $first['import']['status']);

        $duplicate = $this->validPack(packId: (string) Str::ulid());
        $second = $this->workbench->stage($this->admin, $this->encodeJson($duplicate));
        self::assertSame('rejected', $second['import']['status']);
        $summary = json_decode((string) $second['import']['validation_summary'], true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($summary);
        self::assertContains('QUESTION_DUPLICATE_EXISTING', array_column($summary['errors'], 'code'));
        self::assertSame([], $second['items']);

        $mismatch = $this->validPack(packId: (string) Str::ulid());
        $mismatch['settings_hash'] = str_repeat('f', 64);
        $this->expectProblem(
            fn (): array => $this->workbench->stage($this->admin, $this->encodeJson($mismatch)),
            'QUESTION_BANK_SETTINGS_MISMATCH',
        );
    }

    public function test_superseded_preparation_and_invalid_json_are_rejected_before_mutation(): void
    {
        $requestId = (string) $this->preparation['preparation_request_id'];
        DB::table('preparation_requests')->where('id', $requestId)->update([
            'status' => 'superseded',
            'superseded_by_request_id' => (string) Str::ulid(),
            'superseded_at' => now(),
        ]);

        $this->expectProblem(
            fn (): array => $this->workbench->stage($this->admin, $this->encodeJson($this->validPack())),
            'PREPARATION_REGENERATION_REQUIRED',
        );
        self::assertSame(0, DB::table('question_bank_imports')->count());

        $this->expectProblem(
            fn (): array => $this->workbench->stage($this->admin, '{invalid-json'),
            'QUESTION_BANK_JSON_INVALID',
        );
        self::assertSame(0, DB::table('question_bank_imports')->count());
    }

    public function test_manual_mapping_reopens_review_and_bulk_actions_are_reason_audited(): void
    {
        $pack = $this->validPack();
        $pack['items'][0]['academic_scope']['skill'] = null;
        $pack['items'][0]['academic_scope']['topic'] = null;
        $pack['items'][0]['academic_scope']['unit'] = null;
        $pack['items'][0]['academic_scope']['subject'] = null;
        $pack['items'][0]['learning_objective'] = null;

        $stage = $this->workbench->stage($this->admin, $this->encodeJson($pack));
        self::assertSame('mapping_required', $stage['items'][0]['scope_state']);

        $itemId = (string) $stage['items'][0]['id'];
        $mapped = $this->workbench->reclassifyItem(
            $this->admin,
            $itemId,
            'Medium',
            LearningSliceSeeder::TOPIC_NODE_ID,
            'Map the question to the verified fixture topic and adjust difficulty.',
        );
        self::assertSame('mapped', $mapped['items'][0]['scope_state']);
        self::assertSame('Medium', $mapped['items'][0]['difficulty']);

        $importId = (string) $mapped['import']['id'];
        $bulk = $this->workbench->bulk(
            $this->admin,
            [$importId],
            'approve',
            'Bulk approval after verified curriculum mapping and source review.',
        );
        self::assertSame(1, $bulk['count']);
        $this->assertDatabaseHas('question_bank_workflow_audits', [
            'question_bank_import_id' => $importId,
            'action' => 'bulk_approve',
        ]);
        self::assertSame(
            'Bulk approval after verified curriculum mapping and source review.',
            DB::table('question_bank_workflow_audits')
                ->where('question_bank_import_id', $importId)
                ->where('action', 'bulk_approve')
                ->value('reason'),
        );
    }

    public function test_json_and_csv_exports_are_traceable_and_csv_never_exposes_answer_keys(): void
    {
        $stage = $this->workbench->stage($this->admin, $this->encodeJson($this->validPack()));
        $importId = (string) $stage['import']['id'];

        $json = $this->workbench->exportJson($importId);
        self::assertSame('modrik-question-bank-v1', $json['schema_version']);
        self::assertArrayHasKey('answer_contract', $json['items'][0]);

        $rows = $this->workbench->exportCsvRows($importId);
        self::assertCount(1, $rows);
        self::assertArrayHasKey('source_key', $rows[0]);
        self::assertArrayNotHasKey('answer_contract', $rows[0]);
        self::assertArrayNotHasKey('question_text', $rows[0]);
    }

    public function test_livewire_upload_uses_same_authoritative_service(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(QuestionBankWorkbench::class)
            ->set('questionBankJson', UploadedFile::fake()->createWithContent(
                'question-bank.json',
                $this->encodeJson($this->validPack()),
            ))
            ->call('upload')
            ->assertHasNoErrors()
            ->assertSet('questionBankJson', null);

        $this->assertDatabaseHas('question_bank_imports', ['status' => 'needs_review']);
    }

    /** @return array<string, mixed> */
    private function publishReadyPack(): array
    {
        $stage = $this->workbench->stage($this->admin, $this->encodeJson($this->validPack()));
        $importId = (string) $stage['import']['id'];
        $this->workbench->review(
            $this->admin,
            $importId,
            'approved',
            'Reviewed against the supplied source and curriculum mapping.',
        );
        $sourceId = (string) $stage['sources'][0]['id'];
        $this->workbench->updateSourceMaterial(
            $this->admin,
            $sourceId,
            'pdf',
            'library://fixture/source.pdf',
            'approved',
            'Rights evidence approved for the controlled test.',
        );

        return $this->workbench->publish($this->admin, $importId);
    }

    /** @return array<string, mixed> */
    private function validPack(?string $packId = null): array
    {
        return [
            'schema_version' => 'modrik-question-bank-v1',
            'preparation_request_id' => (string) $this->preparation['preparation_request_id'],
            'settings_hash' => (string) $this->preparation['settings_hash'],
            'pack_id' => $packId ?? (string) Str::ulid(),
            'generated_at' => '2026-09-18T06:00:00Z',
            'prompt' => [
                'id' => 'MODRIK_QUESTION_BANK_MASTER_V1',
                'version' => '1.0.0',
            ],
            'sources' => [[
                'source_id' => 'owner-source-addition',
                'source_name' => 'Owner Addition Source',
            ]],
            'items' => [[
                'id' => 'addition-q-001',
                'academic_scope' => [
                    'academic_year' => 'FIXTURE-YEAR-6-7',
                    'track_reference' => 'FIXTURE:TRACK:PENDING-BOARD',
                    'subject' => 'FIXTURE:SUBJECT:GENERAL-STUDY',
                    'unit' => null,
                    'topic' => 'FIXTURE:TOPIC:STUDY-PLAN',
                    'skill' => 'FIXTURE:SKILL:ADDITION',
                ],
                'learning_objective' => 'Add two one-digit numbers accurately.',
                'type' => 'multiple_choice',
                'language' => 'en',
                'question_text' => 'What is 2 + 3?',
                'options' => [
                    ['id' => 'a', 'text' => '5'],
                    ['id' => 'b', 'text' => '6'],
                ],
                'answer_contract' => ['correct_option_id' => 'a'],
                'explanation' => 'Two plus three equals five.',
                'hints' => ['Count on three from two.'],
                'difficulty' => 'Easy',
                'source' => [
                    'source_id' => 'owner-source-addition',
                    'source_page' => 12,
                    'notes' => 'Mapped to the owner-provided source page.',
                ],
                'provenance' => [
                    'derivation' => 'generated_derivative',
                    'confidence' => null,
                    'generation_notes' => 'Generated manually from the owner-provided source using the governed prompt.',
                ],
            ]],
            'warnings' => [],
            'unknowns' => [],
        ];
    }

    /** @return array<string, mixed> */
    private function preparationSettings(): array
    {
        return [
            'locales' => ['en', 'ar', 'fr'],
            'academic_scope' => [
                'track_reference' => 'FIXTURE:TRACK:PENDING-BOARD',
                'board_reference' => null,
                'syllabus_version' => null,
                'year_level' => 'FIXTURE-YEAR-6-7',
                'subject_references' => ['FIXTURE:SUBJECT:GENERAL-STUDY'],
            ],
            'content_types' => ['lesson', 'practice_quiz'],
            'generation' => [
                'include_answer_explanations' => true,
                'maximum_questions_per_quiz' => 20,
                'paid_ai_required' => false,
            ],
        ];
    }

    /**
     * @param  callable(): array<string, mixed>  $callback
     */
    private function expectProblem(callable $callback, string $code): void
    {
        try {
            $callback();
            self::fail('Expected API problem '.$code.'.');
        } catch (ApiProblemException $exception) {
            self::assertSame($code, $exception->problemCode);
        }
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
