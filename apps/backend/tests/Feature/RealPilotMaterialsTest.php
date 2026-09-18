<?php

namespace Tests\Feature;

use App\Filament\Pages\RealPilotMaterials;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

final class RealPilotMaterialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_pilot_materials_registry_is_discoverable_for_content_roles_only(): void
    {
        foreach (['admin', 'content_team'] as $role) {
            $user = User::factory()->create(['role' => $role, 'account_status' => 'active', 'locale' => 'en']);
            $this->actingAs($user);

            Livewire::test(RealPilotMaterials::class)
                ->assertOk()
                ->assertSee('Real Pilot Materials')
                ->assertSee('These are real sources, not student-published content yet')
                ->assertSee('Arabic Language - Grade 6 - Second Term - Part 1')
                ->assertSee('FANBOYS coordinating conjunctions - completed worksheet')
                ->assertSee('PII_REDACTION_REQUIRED')
                ->assertSee('Source segmentation');

            auth()->logout();
        }

        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($student);
        $this->assertFalse(RealPilotMaterials::canAccess());
        $this->get('/admin/real-pilot-materials')->assertForbidden();
    }

    public function test_registry_preserves_source_truth_and_blocks_student_delivery(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var RealPilotMaterials $page */
        $page = Livewire::test(RealPilotMaterials::class)->instance();
        $manifest = $page->manifest();
        $materials = $page->materials();
        $metrics = $page->metrics();

        $this->assertSame('modrik-real-pilot-sources-v1', $manifest['schema_version']);
        $this->assertCount(6, $materials);
        $this->assertSame(6, $metrics['total']);
        $this->assertSame(1, $metrics['segmented']);
        $this->assertSame(6, $metrics['rights_pending']);
        $this->assertSame(6, $metrics['mapping_required']);
        $this->assertSame(1, $metrics['pii_redaction_required']);
        $this->assertSame(0, $metrics['delivery_eligible']);

        foreach ($materials as $material) {
            $this->assertSame('pending_review', $material['rights']['status']);
            $this->assertFalse($material['delivery_eligible']);
            $this->assertSame('fingerprinted_owner_supplied_not_in_repo', $material['storage']['state']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $material['sha256']);
        }
    }

    public function test_grade6_arabic_book_is_segmented_from_source_verified_structure(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var RealPilotMaterials $page */
        $page = Livewire::test(RealPilotMaterials::class)->instance();
        $book = collect($page->materials())->firstWhere('source_id', 'pilot-y6-arabic-kuwait-2025-2026-t2-part1');

        $this->assertIsArray($book);
        $this->assertSame(
            '8753a45a324214b59f5688219189ef662c425636303a546a12f889f40a3c1a24',
            $book['sha256'],
        );
        $this->assertSame(157, $book['page_count']);
        $this->assertSame('Grade 6', $book['source_claims']['year_level']);
        $this->assertSame('Second term', $book['source_claims']['term']);
        $this->assertSame('Part 1', $book['source_claims']['part']);
        $this->assertSame('partial_source_verified', $book['curriculum_mapping']['status']);
        $this->assertNull($book['curriculum_mapping']['track_reference']);
        $this->assertGreaterThanOrEqual(30, count($book['segments']));
        $this->assertContains('reading', $book['learning_outcome_families']);
        $this->assertContains('grammar', $book['learning_outcome_families']);
        $this->assertContains('listening', $book['learning_outcome_families']);

        $firstTopic = collect($book['segments'])->firstWhere('segment_id', 'y6-ar-t2-u1-topic1-source');
        $this->assertSame('الوحدة الأولى', $firstTopic['unit']);
        $this->assertSame('آيات من سورة القصص', $firstTopic['topic']);
        $this->assertSame(18, $firstTopic['printed_page_start']);
        $this->assertSame(19, $firstTopic['pdf_page_start']);

        $secondUnitLetter = collect($book['segments'])->firstWhere('segment_id', 'y6-ar-t2-u2-topic1-writing-letter');
        $this->assertSame('الوحدة الثانية', $secondUnitLetter['unit']);
        $this->assertSame('هذي بلادي', $secondUnitLetter['topic']);
        $this->assertContains('letter_writing', $secondUnitLetter['skill_families']);
    }

    public function test_unknown_year_materials_are_not_silently_promoted_to_year6_or_year7(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var RealPilotMaterials $page */
        $page = Livewire::test(RealPilotMaterials::class)->instance();

        $fanboys = collect($page->materials())->firstWhere('source_id', 'pilot-en-fanboys-completed-worksheet');
        $this->assertSame('mapping_required', $fanboys['curriculum_mapping']['status']);
        $this->assertNull($fanboys['curriculum_mapping']['year_level']);
        $this->assertSame('pii_redaction_required', $fanboys['privacy']['status']);
        $this->assertArrayNotHasKey('student_name', $fanboys);

        $grade5 = collect($page->materials())->firstWhere('source_id', 'pilot-math-subtracting-integers-grade5');
        $this->assertSame('Grade 5', $grade5['content_summary']['source_grade_label']);
        $this->assertNull($grade5['curriculum_mapping']['year_level']);
        $this->assertSame('mapping_required', $grade5['curriculum_mapping']['status']);
    }

    public function test_navigation_label_is_localized(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        App::setLocale('en');
        $this->assertSame('Real Pilot Materials', RealPilotMaterials::getNavigationLabel());
        App::setLocale('fr');
        $this->assertSame('Matériaux pilotes réels', RealPilotMaterials::getNavigationLabel());
        App::setLocale('ar');
        $this->assertSame('مواد التجربة الحقيقية', RealPilotMaterials::getNavigationLabel());

        Livewire::test(RealPilotMaterials::class)->assertSee('dir="rtl"', false);
    }
}
