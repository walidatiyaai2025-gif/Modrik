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

    public function test_admin_registers_readable_track_while_backend_generates_internal_identity(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'locale' => 'en']);
        $this->actingAs($admin);

        Livewire::test(AcademicCatalogue::class)
            ->set('form.board_reference', '__new__')
            ->set('form.new_board_label', 'Kuwait Ministry of Education')
            ->set('form.syllabus_version', '__new__')
            ->set('form.new_syllabus_label', 'National Curriculum 2026')
            ->set('form.year_level', '__new__')
            ->set('form.new_year_level_label', 'Grade 6')
            ->set('form.title_en', 'Kuwait Grade 6 National Curriculum')
            ->set('form.title_ar', 'المنهج الوطني الكويتي للصف السادس')
            ->set('form.title_fr', 'Programme national du Koweït — 6e année')
            ->set('form.is_fixture', false)
            ->set('form.reason', 'Register approved Grade 6 curriculum for the 2026 academic year.')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Kuwait Grade 6 National Curriculum');

        $track = DB::table('academic_tracks')->first();
        $this->assertNotNull($track);
        $this->assertStringStartsWith('TRACK:', (string) $track->code);
        $this->assertStringStartsWith('BOARD:', (string) $track->board_reference);
        $this->assertStringStartsWith('SYLLABUS:', (string) $track->syllabus_version);
        $this->assertStringStartsWith('YEAR:', (string) $track->year_level);
        $this->assertFalse((bool) $track->is_fixture);
        $this->assertDatabaseHas('academic_track_audits', [
            'academic_track_id' => $track->id,
            'actor_id' => $admin->getKey(),
            'action' => 'created',
            'reason' => 'Register approved Grade 6 curriculum for the 2026 academic year.',
        ]);
    }

    public function test_catalogue_form_does_not_expose_raw_reference_or_code_text_inputs(): void
    {
        $view = (string) file_get_contents(resource_path('views/filament/pages/academic-catalogue.blade.php'));

        $this->assertStringNotContainsString('wire:model="form.code"', $view);
        $this->assertStringNotContainsString('wire:model="form.board_reference" type="text"', $view);
        $this->assertStringNotContainsString('wire:model="form.syllabus_version" type="text"', $view);
        $this->assertStringNotContainsString('wire:model="form.year_level" type="text"', $view);
        $this->assertStringContainsString('wire:model.live="form.board_reference"', $view);
        $this->assertStringContainsString('wire:model.live="form.syllabus_version"', $view);
        $this->assertStringContainsString('wire:model.live="form.year_level"', $view);
        $this->assertStringContainsString('No Track Reference or internal code is required', $view);
        $this->assertStringContainsString('From academic track to published content', $view);
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
            ->call('edit', $trackId)
            ->assertHasErrors(['form.title_en']);

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
