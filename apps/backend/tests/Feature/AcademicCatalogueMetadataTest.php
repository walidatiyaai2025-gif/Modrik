<?php

namespace Tests\Feature;

use App\Filament\Pages\AcademicCatalogueMetadata;
use App\Models\User;
use App\Services\AcademicTrackCatalogueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AcademicCatalogueMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_uses_operator_year_labels_and_curated_order_without_changing_wire_shape(): void
    {
        config(['modrik.fixture.enabled' => true]);
        $now = now();
        $firstId = '01J00000000000000000000071';
        $secondId = '01J00000000000000000000072';
        $thirdId = '01J00000000000000000000073';

        foreach ([
            [$firstId, 'CATALOGUE-META-A', 'YEAR-A', 20],
            [$secondId, 'CATALOGUE-META-B', 'YEAR-B', 10],
            [$thirdId, 'CATALOGUE-META-C', 'YEAR-B', 5],
        ] as [$id, $code, $yearLevel, $displayOrder]) {
            $this->insertTrack($id, $code, $yearLevel, $displayOrder);
        }

        DB::table('academic_year_metadata')->insert([
            [
                'year_level' => 'YEAR-A',
                'labels' => json_encode(['ar' => 'السنة أ', 'en' => 'Year A', 'fr' => 'Année A'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'display_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'year_level' => 'YEAR-B',
                'labels' => json_encode(['ar' => 'السنة ب', 'en' => 'Year B', 'fr' => 'Année B'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'display_order' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        App::setLocale('fr');
        $catalogue = app(AcademicTrackCatalogueService::class)->catalogue();

        $this->assertSame([$thirdId, $secondId, $firstId], array_column($catalogue, 'id'));
        $this->assertSame(['key', 'label'], array_keys($catalogue[0]['year']));
        $this->assertSame('YEAR-B', $catalogue[0]['year']['key']);
        $this->assertSame('Année B', $catalogue[0]['year']['label']);
    }

    public function test_catalogue_keeps_readable_fallback_when_operator_metadata_is_not_configured(): void
    {
        config(['modrik.fixture.enabled' => true]);
        $id = '01J00000000000000000000074';
        $this->insertTrack($id, 'CATALOGUE-META-FALLBACK', 'YEAR:GRADE-9:ABCDEF12', 0);

        App::setLocale('ar');
        $catalogue = app(AcademicTrackCatalogueService::class)->catalogue();

        $track = collect($catalogue)->firstWhere('id', $id);
        $this->assertIsArray($track);
        $this->assertSame('Grade 9', $track['year']['label']);
    }

    public function test_metadata_admin_surface_is_admin_only_and_year_changes_require_reason_and_are_audited(): void
    {
        $trackId = '01J00000000000000000000075';
        $this->insertTrack($trackId, 'CATALOGUE-META-AUDIT-YEAR', 'YEAR-AUDIT', 0);

        $contentUser = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $this->actingAs($contentUser);
        $this->assertFalse(AcademicCatalogueMetadata::canAccess());

        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);
        $this->assertTrue(AcademicCatalogueMetadata::canAccess());

        $page = new AcademicCatalogueMetadata;
        $page->beginYear('YEAR-AUDIT');
        $page->yearLabelAr = 'السنة التجريبية';
        $page->yearLabelEn = 'Audit Year';
        $page->yearLabelFr = 'Année audit';
        $page->yearDisplayOrder = 7;

        try {
            $page->saveYear();
            $this->fail('Metadata mutation must require an operator reason.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('yearReason', $exception->errors());
        }

        $page->yearReason = 'Approved localized year metadata and ordering.';
        $page->saveYear();

        $this->assertDatabaseHas('academic_year_metadata', ['year_level' => 'YEAR-AUDIT', 'display_order' => 7]);
        $this->assertDatabaseHas('academic_catalogue_metadata_audits', [
            'target_type' => 'year',
            'target_key' => 'YEAR-AUDIT',
            'actor_id' => $admin->id,
            'action' => 'metadata_updated',
            'reason' => 'Approved localized year metadata and ordering.',
        ]);
    }

    public function test_track_order_change_requires_reason_and_persists_audit_evidence(): void
    {
        $trackId = '01J00000000000000000000076';
        $this->insertTrack($trackId, 'CATALOGUE-META-AUDIT-TRACK', 'YEAR-TRACK-AUDIT', 1);

        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        $page = new AcademicCatalogueMetadata;
        $page->beginTrack($trackId);
        $page->trackDisplayOrder = 42;
        $page->trackReason = 'Curated operator-approved display ordering.';
        $page->saveTrack();

        $this->assertDatabaseHas('academic_tracks', ['id' => $trackId, 'display_order' => 42]);
        $this->assertDatabaseHas('academic_catalogue_metadata_audits', [
            'target_type' => 'track',
            'target_key' => $trackId,
            'actor_id' => $admin->id,
            'action' => 'display_order_updated',
            'reason' => 'Curated operator-approved display ordering.',
        ]);
    }

    private function insertTrack(string $id, string $code, string $yearLevel, int $displayOrder): void
    {
        $now = now();
        DB::table('academic_tracks')->insert([
            'id' => $id,
            'code' => $code,
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => $yearLevel,
            'title' => json_encode([
                'ar' => 'مسار '.$code,
                'en' => 'Track '.$code,
                'fr' => 'Parcours '.$code,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => true,
            'availability_state' => 'published',
            'display_order' => $displayOrder,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
