<?php

namespace Tests\Feature;

use App\Filament\Pages\AcademicCatalogue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAcademicCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_access_structural_catalogue_management(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $content = User::factory()->create(['role' => 'content_team']);

        $this->actingAs($admin);
        $this->assertTrue(AcademicCatalogue::canAccess());

        $this->actingAs($content);
        $this->assertFalse(AcademicCatalogue::canAccess());
    }

    public function test_admin_can_register_owner_approved_track_and_audit_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'locale' => 'en']);
        $this->actingAs($admin);

        Livewire::test(AcademicCatalogue::class)
            ->set('form.code', 'OWNER:TRACK:APPROVED-001')
            ->set('form.board_reference', 'OWNER-BOARD-REFERENCE')
            ->set('form.syllabus_version', 'OWNER-SYLLABUS-VERSION')
            ->set('form.year_level', 'OWNER-YEAR-LEVEL')
            ->set('form.title_en', 'Owner approved track')
            ->set('form.title_ar', 'مسار معتمد من المالك')
            ->set('form.title_fr', 'Parcours approuvé par le propriétaire')
            ->set('form.is_fixture', false)
            ->set('form.reason', 'Register values supplied and approved by owner.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('OWNER:TRACK:APPROVED-001');

        $track = DB::table('academic_tracks')->where('code', 'OWNER:TRACK:APPROVED-001')->first();
        $this->assertNotNull($track);
        $this->assertFalse((bool) $track->is_fixture);
        $this->assertDatabaseHas('academic_track_audits', [
            'academic_track_id' => $track->id,
            'actor_id' => $admin->getKey(),
            'action' => 'created',
            'reason' => 'Register values supplied and approved by owner.',
        ]);
    }

    public function test_referenced_track_is_history_locked_against_admin_edit(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $learner = User::factory()->create(['role' => 'student']);
        $trackId = '01J00000000000000000000980';
        $now = now();

        DB::table('academic_tracks')->insert([
            'id' => $trackId,
            'code' => 'OWNER:TRACK:HISTORY-LOCKED',
            'board_reference' => 'OWNER-BOARD',
            'syllabus_version' => 'OWNER-VERSION',
            'year_level' => 'OWNER-YEAR',
            'title' => json_encode(['en' => 'History locked', 'ar' => 'مقفل تاريخيًا', 'fr' => 'Verrouillé'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'is_fixture' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_academic_contexts')->insert([
            'id' => '01J00000000000000000000981',
            'user_id' => $learner->getKey(),
            'academic_track_id' => $trackId,
            'status' => 'active',
            'activated_at' => $now,
            'archived_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($admin);
        Livewire::test(AcademicCatalogue::class)
            ->assertSee('History locked')
            ->assertSee('History locked')
            ->call('edit', $trackId)
            ->assertHasErrors(['form.code']);

        $this->assertSame('OWNER-VERSION', DB::table('academic_tracks')->where('id', $trackId)->value('syllabus_version'));
        $this->assertDatabaseCount('academic_track_audits', 0);
    }

    public function test_navigation_label_is_localized_for_ar_en_fr(): void
    {
        App::setLocale('en');
        $this->assertSame('Academic Catalogue', AcademicCatalogue::getNavigationLabel());
        App::setLocale('ar');
        $this->assertSame('الكتالوج الأكاديمي', AcademicCatalogue::getNavigationLabel());
        App::setLocale('fr');
        $this->assertSame('Catalogue académique', AcademicCatalogue::getNavigationLabel());
    }
}
