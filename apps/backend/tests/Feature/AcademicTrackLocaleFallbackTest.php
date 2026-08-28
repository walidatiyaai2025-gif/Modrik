<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicTrackLocaleFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_published_track_with_missing_locale_remains_visible_to_learner(): void
    {
        config(['modrik.fixture.enabled' => false]);

        $registration = $this->postJson('/v1/auth/register', [
            'name' => 'Locale Fallback Learner',
            'email' => 'locale-fallback@example.test',
            'password' => 'locale-fallback-password-value',
        ])->assertCreated();
        $token = (string) $registration->json('data.access_token');

        $trackId = (string) Str::ulid();
        $now = now();
        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'TRACK:LOCALE-FALLBACK:REAL',
            'board_reference' => 'BOARD:TEST',
            'syllabus_version' => 'SYLLABUS:TEST',
            'year_level' => 'YEAR:GRADE-6:ABCDEF12',
            'title' => json_encode([
                'ar' => 'لغة عربية',
                'en' => 'Arabic Language',
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => false,
            'availability_state' => 'published',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->withToken($token)
            ->getJson('/v1/academic-tracks')
            ->assertOk()
            ->assertJsonPath('data.tracks.0.id', $trackId)
            ->assertJsonPath('data.tracks.0.labels.ar', 'لغة عربية')
            ->assertJsonPath('data.tracks.0.labels.en', 'Arabic Language')
            ->assertJsonPath('data.tracks.0.labels.fr', 'Arabic Language');
    }
}
