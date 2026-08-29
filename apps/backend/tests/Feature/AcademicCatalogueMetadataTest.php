<?php

namespace Tests\Feature;

use App\Services\AcademicTrackCatalogueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
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
        $now = now();
        $id = '01J00000000000000000000074';

        DB::table('academic_tracks')->insert([
            'id' => $id,
            'code' => 'CATALOGUE-META-FALLBACK',
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => 'YEAR:GRADE-9:ABCDEF12',
            'title' => json_encode(['ar' => 'مسار', 'en' => 'Track', 'fr' => 'Parcours'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => true,
            'availability_state' => 'published',
            'display_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        App::setLocale('ar');
        $catalogue = app(AcademicTrackCatalogueService::class)->catalogue();

        $track = collect($catalogue)->firstWhere('id', $id);
        $this->assertIsArray($track);
        $this->assertSame('Grade 9', $track['year']['label']);
    }
}
