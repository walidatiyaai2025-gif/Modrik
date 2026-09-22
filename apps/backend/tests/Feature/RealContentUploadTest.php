<?php

namespace Tests\Feature;

use App\Filament\Pages\RealContentUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class RealContentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_chatgpt_preparation_page_is_available_to_content_operators_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $contentTeam = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)
            ->get('/admin/real-content-upload')
            ->assertOk()
            ->assertSee('Prepare with ChatGPT')
            ->assertSee('No PDF upload here, no OCR, no splitting and no question generation inside MODRIK.')
            ->assertSee('data-testid="modrik-real-content-upload"', false);

        $this->actingAs($contentTeam)->get('/admin/real-content-upload')->assertOk();
        $this->actingAs($student)->get('/admin/real-content-upload')->assertForbidden();
    }

    public function test_creating_chatgpt_package_only_creates_preparation_request_and_no_content(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $trackId = $this->createTrack('Year 6 Science', 'YEAR:6', 'TRACK:YEAR-6-SCIENCE');
        $this->actingAs($admin);

        Livewire::test(RealContentUpload::class)
            ->set('academicTrackId', $trackId)
            ->set('subjectLabel', 'Science')
            ->set('locales', ['ar', 'en'])
            ->set('contentTypes', ['lesson', 'practice_quiz'])
            ->set('maximumQuestionsPerQuiz', 20)
            ->call('createPreparation')
            ->assertHasNoErrors()
            ->assertSee('Package ready');

        self::assertSame(1, DB::table('preparation_requests')->count());
        $request = DB::table('preparation_requests')->first();
        self::assertNotNull($request);
        self::assertSame('ready', (string) $request->status);

        $settings = json_decode((string) $request->normalized_settings, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('TRACK:YEAR-6-SCIENCE', $settings['academic_scope']['track_reference']);
        self::assertSame('YEAR:6', $settings['academic_scope']['year_level']);
        self::assertFalse($settings['generation']['paid_ai_required']);

        self::assertSame(0, DB::table('preparation_imports')->count());
        self::assertSame(0, DB::table('curriculum_nodes')->count());
        self::assertSame(0, DB::table('lessons')->count());
        self::assertSame(0, DB::table('questions')->count());
        self::assertSame(0, DB::table('quizzes')->count());
    }

    private function createTrack(string $title, string $yearLevel, string $code): string
    {
        $id = (string) Str::ulid();
        $now = now();

        DB::table('academic_tracks')->insert([
            'id' => $id,
            'code' => $code,
            'board_reference' => 'BOARD:IG',
            'syllabus_version' => 'SYLLABUS:2026',
            'year_level' => $yearLevel,
            'title' => json_encode(['en' => $title, 'ar' => $title, 'fr' => $title], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => false,
            'availability_state' => 'published',
            'display_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $id;
    }
}
