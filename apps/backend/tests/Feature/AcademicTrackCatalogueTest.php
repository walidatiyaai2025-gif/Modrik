<?php

namespace Tests\Feature;

use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicTrackCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'modrik-academic-catalogue-fixture-token';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'modrik.fixture.enabled' => true,
            'modrik.fixture.bearer_token' => self::TOKEN,
            'modrik.fixture.user_id' => LearningSliceSeeder::USER_ID,
            'modrik.idempotency.secret' => 'test-only-idempotency-secret',
        ]);
        $this->seed(LearningSliceSeeder::class);
    }

    public function test_catalogue_requires_authentication_and_does_not_require_per_user_assignment(): void
    {
        $this->getJson('/v1/academic-tracks')->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        DB::table('academic_track_authorizations')->delete();

        $sameYearId = '01J00000000000000000000050';
        $otherYearId = '01J00000000000000000000051';
        $this->createTrack($sameYearId, 'FIXTURE:CATALOGUE:SAME-YEAR', 'FIXTURE-YEAR-6-7', [
            'ar' => 'مسار ثانٍ لنفس السنة',
            'en' => 'Second track for same year',
            'fr' => 'Deuxième parcours de la même année',
        ]);
        $this->createTrack($otherYearId, 'FIXTURE:CATALOGUE:OTHER-YEAR', 'FIXTURE-YEAR-8', [
            'ar' => 'مسار سنة أخرى',
            'en' => 'Another year track',
            'fr' => 'Parcours d’une autre année',
        ]);

        $response = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $tracks = $response->json('data.tracks');
        $this->assertIsArray($tracks);
        $ids = array_column($tracks, 'id');
        $this->assertContains(LearningSliceSeeder::TRACK_ID, $ids);
        $this->assertContains($sameYearId, $ids);
        $this->assertContains($otherYearId, $ids);

        $sameYear = collect($tracks)->firstWhere('id', $sameYearId);
        $this->assertIsArray($sameYear);
        $this->assertSame(['id', 'year', 'labels'], array_keys($sameYear));
        $this->assertSame(['key', 'label'], array_keys($sameYear['year']));
        $this->assertSame('FIXTURE-YEAR-6-7', $sameYear['year']['key']);
        $this->assertSame('Fixture Year 6 7', $sameYear['year']['label']);
        $this->assertSame('Second track for same year', $sameYear['labels']['en']);
    }

    public function test_catalogue_exposes_shared_year_scope_for_all_tracks_in_that_year(): void
    {
        DB::table('academic_track_authorizations')->delete();
        $firstId = '01J00000000000000000000052';
        $secondId = '01J00000000000000000000053';
        foreach ([
            [$firstId, 'A'],
            [$secondId, 'B'],
        ] as [$id, $suffix]) {
            $this->createTrack($id, 'FIXTURE:YEAR:SCOPE:'.$suffix, 'YEAR:GRADE-6:ABCDEF12', [
                'ar' => 'مسار السنة '.$suffix,
                'en' => 'Year track '.$suffix,
                'fr' => 'Parcours année '.$suffix,
            ]);
        }

        $tracks = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->json('data.tracks');

        $this->assertIsArray($tracks);
        $scoped = collect($tracks)->whereIn('id', [$firstId, $secondId])->values();
        $this->assertCount(2, $scoped);
        $this->assertSame(['YEAR:GRADE-6:ABCDEF12'], $scoped->pluck('year.key')->unique()->values()->all());
        $this->assertSame(['Grade 6'], $scoped->pluck('year.label')->unique()->values()->all());
    }

    public function test_display_invalid_track_or_year_fails_closed(): void
    {
        DB::table('academic_track_authorizations')->delete();
        $missingLocaleId = '01J00000000000000000000054';
        $unsafeYearId = '01J00000000000000000000055';
        $markupId = '01J00000000000000000000056';

        $this->createTrack($missingLocaleId, 'FIXTURE:INVALID:MISSING', 'FIXTURE-YEAR', [
            'ar' => 'مسار ناقص',
            'en' => 'Missing localization',
        ]);
        $this->createTrack($unsafeYearId, 'FIXTURE:INVALID:YEAR', "YEAR:GRADE-6\u{0007}", [
            'ar' => 'مسار سنة غير آمنة',
            'en' => 'Unsafe year',
            'fr' => 'Année non sûre',
        ]);
        $this->createTrack($markupId, 'FIXTURE:INVALID:MARKUP', 'FIXTURE-YEAR', [
            'ar' => 'مسار آمن',
            'en' => '<b>Unsafe markup</b>',
            'fr' => 'Balisage non sûr',
        ]);

        $tracks = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->json('data.tracks');

        $this->assertIsArray($tracks);
        $ids = array_column($tracks, 'id');
        $this->assertNotContains($missingLocaleId, $ids);
        $this->assertNotContains($unsafeYearId, $ids);
        $this->assertNotContains($markupId, $ids);
        $this->assertContains(LearningSliceSeeder::TRACK_ID, $ids);
    }

    public function test_reset_accepts_a_display_safe_track_without_an_assignment_row(): void
    {
        DB::table('academic_track_authorizations')->delete();
        $targetId = '01J00000000000000000000057';
        $this->createTrack($targetId, 'FIXTURE:SELF-SELECT:TARGET', 'YEAR:GRADE-7:1234ABCD', [
            'ar' => 'مسار يختاره الطالب',
            'en' => 'Learner selected track',
            'fr' => 'Parcours choisi par l’apprenant',
        ]);

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'year-self-select-reset-0001')
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => $targetId])
            ->assertOk()
            ->assertJsonPath('data.academic_track_id', $targetId)
            ->assertJsonPath('data.year_level', 'YEAR:GRADE-7:1234ABCD');

        $this->assertDatabaseHas('user_academic_contexts', [
            'user_id' => LearningSliceSeeder::USER_ID,
            'academic_track_id' => $targetId,
            'status' => 'active',
        ]);
    }

    public function test_draft_and_retired_tracks_are_hidden_and_rejected_by_selection_authority(): void
    {
        DB::table('academic_track_authorizations')->delete();
        $draftId = '01J00000000000000000000059';
        $retiredId = '01J00000000000000000000060';
        $this->createTrack($draftId, 'FIXTURE:AVAILABILITY:DRAFT', 'YEAR:GRADE-8:DRAFT001', [
            'ar' => 'مسار مسودة',
            'en' => 'Draft track',
            'fr' => 'Parcours brouillon',
        ], true, 'draft');
        $this->createTrack($retiredId, 'FIXTURE:AVAILABILITY:RETIRED', 'YEAR:GRADE-8:RETIRE01', [
            'ar' => 'مسار متقاعد',
            'en' => 'Retired track',
            'fr' => 'Parcours retiré',
        ], true, 'retired');

        $tracks = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->json('data.tracks');
        $this->assertIsArray($tracks);
        $ids = array_column($tracks, 'id');
        $this->assertNotContains($draftId, $ids);
        $this->assertNotContains($retiredId, $ids);

        foreach ([$draftId, $retiredId] as $index => $trackId) {
            $idempotencyKey = sprintf('track-reset-rejection-%03d', $index);
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->postJson('/v1/academic-context/reset', ['academic_track_id' => $trackId])
                ->assertNotFound()
                ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
        }

        DB::table('user_academic_contexts')
            ->where('user_id', LearningSliceSeeder::USER_ID)
            ->where('status', 'active')
            ->update(['status' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);

        foreach ([$draftId, $retiredId] as $index => $trackId) {
            $idempotencyKey = sprintf('track-activate-rejection-%03d', $index);
            $this->withToken(self::TOKEN)
                ->withHeader('Idempotency-Key', $idempotencyKey)
                ->postJson('/v1/academic-context/activate', ['academic_track_id' => $trackId])
                ->assertNotFound()
                ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');
        }
    }

    public function test_fixture_tracks_remain_hidden_when_fixture_mode_is_disabled_without_assignment_logic(): void
    {
        config(['modrik.fixture.enabled' => false]);

        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Year Scope Production Boundary Learner',
            'email' => 'year-scope-production-boundary@example.test',
            'password' => 'year-scope-production-boundary-password',
        ])->assertCreated();
        $token = (string) $registration->json('data.access_token');

        $this->withToken($token)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertJsonPath('data.tracks', []);

        $realTrackId = '01J00000000000000000000058';
        $this->createTrack($realTrackId, 'TEST:NONFIXTURE:YEAR-SCOPE', 'YEAR:GRADE-6:87654321', [
            'ar' => 'مسار اختبار غير تجريبي',
            'en' => 'Non-fixture test track',
            'fr' => 'Parcours de test non-fixture',
        ], false);

        $this->withToken($token)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertJsonPath('data.tracks.0.id', $realTrackId)
            ->assertJsonPath('data.tracks.0.year.label', 'Grade 6');
    }

    /** @param array<string, string> $labels */
    private function createTrack(
        string $id,
        string $code,
        string $yearLevel,
        array $labels,
        bool $fixture = true,
        string $availabilityState = 'published',
    ): void {
        $now = now();
        DB::table('academic_tracks')->insert([
            'id' => $id,
            'code' => $code,
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => $yearLevel,
            'title' => json_encode($labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => $fixture,
            'availability_state' => $availabilityState,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
