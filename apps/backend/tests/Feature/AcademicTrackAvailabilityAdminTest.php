<?php

namespace Tests\Feature;

use App\Filament\Pages\AcademicTrackAvailability;
use App\Models\User;
use Database\Seeders\LearningSliceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicTrackAvailabilityAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LearningSliceSeeder::class);
    }

    public function test_only_admin_role_can_access_track_availability_operator_surface(): void
    {
        $contentUser = User::factory()->create([
            'role' => 'content_team',
            'account_status' => 'active',
        ]);
        $this->actingAs($contentUser);
        $this->assertFalse(AcademicTrackAvailability::canAccess());

        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);
        $this->assertTrue(AcademicTrackAvailability::canAccess());
    }

    public function test_admin_publish_requires_reason_persists_state_and_audit(): void
    {
        $admin = $this->admin();
        DB::table('academic_tracks')
            ->where('id', LearningSliceSeeder::TRACK_ID)
            ->update(['availability_state' => 'draft']);

        $page = new AcademicTrackAvailability;
        $page->begin(LearningSliceSeeder::TRACK_ID, 'published');
        $page->reason = 'Approved for learner catalogue publication.';
        $page->apply();

        $this->assertDatabaseHas('academic_tracks', [
            'id' => LearningSliceSeeder::TRACK_ID,
            'availability_state' => 'published',
        ]);
        $this->assertDatabaseHas('academic_track_audits', [
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'actor_id' => $admin->id,
            'action' => 'published',
            'reason' => 'Approved for learner catalogue publication.',
        ]);
    }

    public function test_in_use_retirement_fails_closed_until_explicit_confirmation_then_preserves_history(): void
    {
        $admin = $this->admin();
        $contextCountBefore = DB::table('user_academic_contexts')
            ->where('academic_track_id', LearningSliceSeeder::TRACK_ID)
            ->count();
        $this->assertGreaterThan(0, $contextCountBefore);

        $page = new AcademicTrackAvailability;
        $page->begin(LearningSliceSeeder::TRACK_ID, 'retired');
        $page->reason = 'Retire from new learner selection while preserving history.';

        try {
            $page->apply();
            $this->fail('Historical retirement must require explicit confirmation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('confirmHistoricalRetirement', $exception->errors());
        }

        $this->assertDatabaseHas('academic_tracks', [
            'id' => LearningSliceSeeder::TRACK_ID,
            'availability_state' => 'published',
        ]);
        $this->assertDatabaseMissing('academic_track_audits', [
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'action' => 'retired',
        ]);

        $page->confirmHistoricalRetirement = true;
        $page->apply();

        $this->assertDatabaseHas('academic_tracks', [
            'id' => LearningSliceSeeder::TRACK_ID,
            'availability_state' => 'retired',
        ]);
        $this->assertDatabaseHas('academic_track_audits', [
            'academic_track_id' => LearningSliceSeeder::TRACK_ID,
            'actor_id' => $admin->id,
            'action' => 'retired',
            'reason' => 'Retire from new learner selection while preserving history.',
        ]);
        $this->assertSame(
            $contextCountBefore,
            DB::table('user_academic_contexts')
                ->where('academic_track_id', LearningSliceSeeder::TRACK_ID)
                ->count(),
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
        ]);
        $this->actingAs($admin);

        return $admin;
    }
}
