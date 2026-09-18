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
            $rights = $material['rights'] ?? null;
            $storage = $material['storage'] ?? null;
            $this->assertIsArray($rights);
            $this->assertIsArray($storage);
            $this->assertSame('pending_review', $rights['status'] ?? null);
            $this->assertFalse((bool) ($material['delivery_eligible'] ?? true));
            $this->assertSame('fingerprinted_owner_supplied_not_in_repo', $storage['state'] ?? null);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) ($material['sha256'] ?? ''));
        }
    }

    public function test_grade6_arabic_book_is_segmented_from_source_verified_structure(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var RealPilotMaterials $page */
        $page = Livewire::test(RealPilotMaterials::class)->instance();
        $book = $this->materialById($page, 'pilot-y6-arabic-kuwait-2025-2026-t2-part1');

        $sourceClaims = $book['source_claims'] ?? null;
        $mapping = $book['curriculum_mapping'] ?? null;
        $segments = $book['segments'] ?? null;
        $outcomes = $book['learning_outcome_families'] ?? null;
        $this->assertIsArray($sourceClaims);
        $this->assertIsArray($mapping);
        $this->assertIsArray($segments);
        $this->assertIsArray($outcomes);

        $this->assertSame(
            '8753a45a324214b59f5688219189ef662c425636303a546a12f889f40a3c1a24',
            $book['sha256'] ?? null,
        );
        $this->assertSame(157, $book['page_count'] ?? null);
        $this->assertSame('Grade 6', $sourceClaims['year_level'] ?? null);
        $this->assertSame('Second term', $sourceClaims['term'] ?? null);
        $this->assertSame('Part 1', $sourceClaims['part'] ?? null);
        $this->assertSame('partial_source_verified', $mapping['status'] ?? null);
        $this->assertNull($mapping['track_reference'] ?? null);
        $this->assertGreaterThanOrEqual(30, count($segments));
        $this->assertContains('reading', $outcomes);
        $this->assertContains('grammar', $outcomes);
        $this->assertContains('listening', $outcomes);

        $firstTopic = $this->segmentById($segments, 'y6-ar-t2-u1-topic1-source');
        $this->assertSame('الوحدة الأولى', $firstTopic['unit'] ?? null);
        $this->assertSame('آيات من سورة القصص', $firstTopic['topic'] ?? null);
        $this->assertSame(18, $firstTopic['printed_page_start'] ?? null);
        $this->assertSame(19, $firstTopic['pdf_page_start'] ?? null);

        $secondUnitLetter = $this->segmentById($segments, 'y6-ar-t2-u2-topic1-writing-letter');
        $skillFamilies = $secondUnitLetter['skill_families'] ?? null;
        $this->assertIsArray($skillFamilies);
        $this->assertSame('الوحدة الثانية', $secondUnitLetter['unit'] ?? null);
        $this->assertSame('هذي بلادي', $secondUnitLetter['topic'] ?? null);
        $this->assertContains('letter_writing', $skillFamilies);
    }

    public function test_unknown_year_materials_are_not_silently_promoted_to_year6_or_year7(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var RealPilotMaterials $page */
        $page = Livewire::test(RealPilotMaterials::class)->instance();

        $fanboys = $this->materialById($page, 'pilot-en-fanboys-completed-worksheet');
        $fanboysMapping = $fanboys['curriculum_mapping'] ?? null;
        $fanboysPrivacy = $fanboys['privacy'] ?? null;
        $this->assertIsArray($fanboysMapping);
        $this->assertIsArray($fanboysPrivacy);
        $this->assertSame('mapping_required', $fanboysMapping['status'] ?? null);
        $this->assertNull($fanboysMapping['year_level'] ?? null);
        $this->assertSame('pii_redaction_required', $fanboysPrivacy['status'] ?? null);
        $this->assertArrayNotHasKey('student_name', $fanboys);

        $grade5 = $this->materialById($page, 'pilot-math-subtracting-integers-grade5');
        $grade5Summary = $grade5['content_summary'] ?? null;
        $grade5Mapping = $grade5['curriculum_mapping'] ?? null;
        $this->assertIsArray($grade5Summary);
        $this->assertIsArray($grade5Mapping);
        $this->assertSame('Grade 5', $grade5Summary['source_grade_label'] ?? null);
        $this->assertNull($grade5Mapping['year_level'] ?? null);
        $this->assertSame('mapping_required', $grade5Mapping['status'] ?? null);
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

    /** @return array<string, mixed> */
    private function materialById(RealPilotMaterials $page, string $sourceId): array
    {
        foreach ($page->materials() as $material) {
            if (($material['source_id'] ?? null) === $sourceId) {
                return $material;
            }
        }

        self::fail('Missing pilot material '.$sourceId);

        return [];
    }

    /**
     * @param  array<mixed>  $segments
     * @return array<string, mixed>
     */
    private function segmentById(array $segments, string $segmentId): array
    {
        foreach ($segments as $segment) {
            if (is_array($segment) && ($segment['segment_id'] ?? null) === $segmentId) {
                return $segment;
            }
        }

        self::fail('Missing pilot source segment '.$segmentId);

        return [];
    }
}
