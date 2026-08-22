<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPreparationWizard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminHumanReadableInputContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_preparation_uses_persisted_track_lookup_and_ignores_forged_browser_references(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'locale' => 'en']);
        $trackId = '01J00000000000000000000990';
        $now = now();

        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'TRACK:KUWAIT-GRADE-6:CANONICAL01',
            'board_reference' => 'BOARD:KUWAIT-MOE:CANONICAL01',
            'syllabus_version' => 'SYLLABUS:NATIONAL-2026:CANONICAL01',
            'year_level' => 'YEAR:GRADE-6:CANONICAL01',
            'title' => json_encode([
                'en' => 'Kuwait Grade 6 National Curriculum',
                'ar' => 'المنهج الوطني الكويتي للصف السادس',
                'fr' => 'Programme national du Koweït — 6e année',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin);
        Livewire::test(ContentPreparationWizard::class)
            ->set('academicTrackId', $trackId)
            ->set('subjectNames', "Mathematics\nScience")
            ->set('trackReference', 'FORGED:TRACK')
            ->set('boardReference', 'FORGED:BOARD')
            ->set('syllabusVersion', 'FORGED:SYLLABUS')
            ->set('yearLevel', 'FORGED:YEAR')
            ->call('generate')
            ->assertHasNoErrors();

        $settingsJson = DB::table('preparation_requests')->orderByDesc('created_at')->value('normalized_settings');
        $this->assertIsString($settingsJson);
        $settings = json_decode($settingsJson, true, flags: JSON_THROW_ON_ERROR);
        $this->assertIsArray($settings);
        $scope = $settings['academic_scope'];

        $this->assertSame('TRACK:KUWAIT-GRADE-6:CANONICAL01', $scope['track_reference']);
        $this->assertSame('BOARD:KUWAIT-MOE:CANONICAL01', $scope['board_reference']);
        $this->assertSame('SYLLABUS:NATIONAL-2026:CANONICAL01', $scope['syllabus_version']);
        $this->assertSame('YEAR:GRADE-6:CANONICAL01', $scope['year_level']);
        $this->assertNotContains('FORGED:TRACK', $scope);
        $this->assertCount(2, $scope['subject_references']);
        $this->assertStringStartsWith('SUBJECT:MATHEMATICS:', $scope['subject_references'][0]);
        $this->assertStringStartsWith('SUBJECT:SCIENCE:', $scope['subject_references'][1]);
    }

    public function test_preparation_ui_has_no_operator_editable_academic_reference_fields(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/content-preparation-wizard.blade.php'));

        $this->assertStringContainsString('wire:model.live="academicTrackId"', $view);
        $this->assertStringContainsString('wire:model="subjectNames"', $view);
        $this->assertStringNotContainsString('wire:model="trackReference"', $view);
        $this->assertStringNotContainsString('wire:model="boardReference"', $view);
        $this->assertStringNotContainsString('wire:model="syllabusVersion"', $view);
        $this->assertStringNotContainsString('wire:model="yearLevel"', $view);
        $this->assertStringNotContainsString('wire:model="subjectReferences"', $view);
        $this->assertStringContainsString('The server re-loads the selected track from the database', $view);
        $this->assertStringContainsString('Content publication journey', $view);
    }

    public function test_rights_review_keeps_internal_enum_values_behind_human_labels_and_guided_inputs(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/content-rights-review.blade.php'));

        $this->assertStringContainsString('Original content created by the owner', $view);
        $this->assertStringContainsString('Content covered by a license or permission', $view);
        $this->assertStringContainsString('Verified public-domain content', $view);
        $this->assertStringNotContainsString('>owner_created</option>', $view);
        $this->assertStringNotContainsString('>licensed</option>', $view);
        $this->assertStringNotContainsString('>public_domain</option>', $view);
        $this->assertStringContainsString('license agreement LIC-2026-014', $view);
        $this->assertStringContainsString('Technical traceability', $view);
        $this->assertStringContainsString('Content publication journey', $view);
    }

    public function test_admin_ux_contract_rejects_free_text_database_references(): void
    {
        $contract = (string) file_get_contents(base_path('../../docs/brand/ADMIN_UX_SYSTEM.md'));

        $this->assertStringContainsString('A database-backed reference is never a free-text field', $contract);
        $this->assertStringContainsString('every operator-entered field has nearby guidance', mb_strtolower($contract));
        $this->assertStringContainsString('selecting an approved Academic Track is the authority', $contract);
        $this->assertStringContainsString('Content Preparation flow must derive that scope from the selected track', $contract);
        $this->assertStringContainsString('Register or select the Academic Track', $contract);
        $this->assertStringContainsString('Import canonical draft and publish', $contract);
    }
}
