<?php

namespace Tests\Feature;

use App\Models\User;
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

    public function test_catalogue_requires_authentication_and_can_be_explicitly_empty(): void
    {
        $this->getJson('/v1/academic-tracks')->assertUnauthorized()
            ->assertJsonPath('code', 'AUTHENTICATION_REQUIRED');

        DB::table('academic_track_authorizations')
            ->where('user_id', LearningSliceSeeder::USER_ID)
            ->delete();

        $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('data.tracks', []);
    }

    public function test_catalogue_returns_only_current_learner_authorizations_in_deterministic_order(): void
    {
        $firstTrackId = '01J00000000000000000000050';
        $secondTrackId = '01J00000000000000000000051';
        $unauthorizedTrackId = '01J00000000000000000000052';
        $otherLearnerTrackId = '01J00000000000000000000053';

        $this->createFixtureTrack($firstTrackId, 'FIXTURE:CATALOGUE:A', [
            'ar' => 'المسار التجريبي أ',
            'en' => 'Synthetic catalogue A',
            'fr' => 'Catalogue synthétique A',
        ]);
        $this->createFixtureTrack($secondTrackId, 'FIXTURE:CATALOGUE:B', [
            'ar' => 'المسار التجريبي ب',
            'en' => 'Synthetic catalogue B',
            'fr' => 'Catalogue synthétique B',
        ]);
        $this->createFixtureTrack($unauthorizedTrackId, 'FIXTURE:CATALOGUE:UNAUTHORIZED', [
            'ar' => 'مسار غير مصرح',
            'en' => 'Unauthorized synthetic track',
            'fr' => 'Parcours synthétique non autorisé',
        ]);
        $this->createFixtureTrack($otherLearnerTrackId, 'FIXTURE:CATALOGUE:OTHER', [
            'ar' => 'مسار مستخدم آخر',
            'en' => 'Other learner synthetic track',
            'fr' => 'Parcours synthétique d’un autre apprenant',
        ]);

        $this->authorize(LearningSliceSeeder::USER_ID, $firstTrackId, '01J00000000000000000000060', 20);
        $this->authorize(LearningSliceSeeder::USER_ID, $secondTrackId, '01J00000000000000000000061', 10);

        $otherLearner = User::factory()->create();
        $this->authorize((string) $otherLearner->getKey(), $otherLearnerTrackId, '01J00000000000000000000062', 1);

        $response = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');

        $tracks = $response->json('data.tracks');
        $this->assertIsArray($tracks);
        $this->assertSame(
            [$secondTrackId, $firstTrackId, LearningSliceSeeder::TRACK_ID],
            array_column($tracks, 'id'),
        );
        $this->assertSame(['id', 'labels'], array_keys($tracks[0]));
        $this->assertSame([
            'ar' => 'المسار التجريبي ب',
            'en' => 'Synthetic catalogue B',
            'fr' => 'Catalogue synthétique B',
        ], $tracks[0]['labels']);
        $this->assertNotContains($unauthorizedTrackId, array_column($tracks, 'id'));
        $this->assertNotContains($otherLearnerTrackId, array_column($tracks, 'id'));

        foreach ($tracks as $track) {
            $this->assertSame(['id', 'labels'], array_keys($track));
            $this->assertSame(['ar', 'en', 'fr'], array_keys($track['labels']));
        }
    }

    public function test_incomplete_or_unsafe_localization_fails_closed(): void
    {
        $missingLocaleId = '01J00000000000000000000054';
        $markupId = '01J00000000000000000000055';
        $controlId = '01J00000000000000000000056';

        $this->createFixtureTrack($missingLocaleId, 'FIXTURE:CATALOGUE:MISSING', [
            'ar' => 'مسار ناقص',
            'en' => 'Missing localization',
        ]);
        $this->createFixtureTrack($markupId, 'FIXTURE:CATALOGUE:MARKUP', [
            'ar' => 'مسار آمن',
            'en' => '<b>Unsafe markup</b>',
            'fr' => 'Balisage non sûr',
        ]);
        $this->createFixtureTrack($controlId, 'FIXTURE:CATALOGUE:CONTROL', [
            'ar' => 'مسار تحكم',
            'en' => "Unsafe\u{0007}control",
            'fr' => 'Contrôle non sûr',
        ]);

        $this->authorize(LearningSliceSeeder::USER_ID, $missingLocaleId, '01J00000000000000000000063', 1);
        $this->authorize(LearningSliceSeeder::USER_ID, $markupId, '01J00000000000000000000064', 2);
        $this->authorize(LearningSliceSeeder::USER_ID, $controlId, '01J00000000000000000000065', 3);

        $tracks = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->json('data.tracks');

        $this->assertIsArray($tracks);
        $this->assertSame([LearningSliceSeeder::TRACK_ID], array_column($tracks, 'id'));
        $this->assertSame([
            'ar' => 'مسار تجريبي اصطناعي',
            'en' => 'Synthetic fixture track',
            'fr' => 'Parcours synthétique de test',
        ], $tracks[0]['labels']);
    }

    public function test_unauthorized_and_revoked_tracks_are_concealed_from_catalogue_and_mutations(): void
    {
        $unauthorizedTrackId = '01J00000000000000000000057';
        $revokedTrackId = '01J00000000000000000000058';
        $labels = [
            'ar' => 'مسار مخفي',
            'en' => 'Hidden synthetic track',
            'fr' => 'Parcours synthétique masqué',
        ];
        $this->createFixtureTrack($unauthorizedTrackId, 'FIXTURE:CATALOGUE:HIDDEN', $labels);
        $this->createFixtureTrack($revokedTrackId, 'FIXTURE:CATALOGUE:REVOKED', $labels);
        $this->authorize(LearningSliceSeeder::USER_ID, $revokedTrackId, '01J00000000000000000000066', 1, now());

        $tracks = $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->json('data.tracks');
        $this->assertIsArray($tracks);
        $this->assertNotContains($unauthorizedTrackId, array_column($tracks, 'id'));
        $this->assertNotContains($revokedTrackId, array_column($tracks, 'id'));

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'aaaaaaaaaaaaaaaaaaaa')
            ->postJson('/v1/academic-context/reset', ['academic_track_id' => $unauthorizedTrackId])
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        DB::table('user_academic_contexts')
            ->where('id', LearningSliceSeeder::CONTEXT_ID)
            ->update(['status' => 'archived', 'archived_at' => now(), 'updated_at' => now()]);

        $this->withToken(self::TOKEN)
            ->withHeader('Idempotency-Key', 'bbbbbbbbbbbbbbbbbbbb')
            ->postJson('/v1/academic-context/activate', ['academic_track_id' => $revokedTrackId])
            ->assertNotFound()
            ->assertJsonPath('code', 'RESOURCE_NOT_FOUND');

        $this->assertDatabaseCount('academic_context_transitions', 0);
        $this->assertSame(0, DB::table('outbox_events')->whereIn('event_type', [
            'academic.context_activated',
            'academic.context_reset',
        ])->count());
    }

    public function test_fixture_catalogue_is_not_visible_when_fixture_mode_is_disabled(): void
    {
        config(['modrik.fixture.enabled' => false]);

        $this->withToken(self::TOKEN)
            ->getJson('/v1/academic-tracks')
            ->assertUnauthorized();

        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Catalogue Production Boundary Learner',
            'email' => 'catalogue-production-boundary@example.test',
            'password' => 'catalogue-production-boundary-password',
        ])->assertCreated();
        $token = (string) $registration->json('data.access_token');
        $user = User::query()
            ->where('email_normalized', 'catalogue-production-boundary@example.test')
            ->firstOrFail();

        $this->authorize(
            (string) $user->getKey(),
            LearningSliceSeeder::TRACK_ID,
            '01J00000000000000000000067',
            1,
        );

        $this->withToken($token)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertJsonPath('data.tracks', []);
    }

    /** @param array<string, string> $labels */
    private function createFixtureTrack(string $id, string $code, array $labels): void
    {
        $now = now();
        DB::table('academic_tracks')->insert([
            'id' => $id,
            'code' => $code,
            'board_reference' => null,
            'syllabus_version' => null,
            'year_level' => 'FIXTURE-YEAR',
            'title' => json_encode($labels, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function authorize(
        string $userId,
        string $trackId,
        string $authorizationId,
        int $sortOrder,
        mixed $revokedAt = null,
    ): void {
        $now = now();
        DB::table('academic_track_authorizations')->insert([
            'id' => $authorizationId,
            'user_id' => $userId,
            'academic_track_id' => $trackId,
            'sort_order' => $sortOrder,
            'authorized_at' => $now,
            'revoked_at' => $revokedAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
