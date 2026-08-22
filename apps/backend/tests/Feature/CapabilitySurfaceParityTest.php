<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentPreparationRequests;
use App\Filament\Pages\SystemCapabilities;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class CapabilitySurfaceParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_discover_and_reopen_saved_preparation_requests(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'locale' => 'en',
        ]);

        $created = app(ContentAdminWorkflowService::class)->createRequest($admin, [
            'schema_version' => '1.0.0',
            'settings' => [
                'locales' => ['ar'],
                'academic_scope' => [
                    'track_reference' => 'KW-MOE:TRACK:MIDDLE-SCHOOL',
                    'board_reference' => 'KW-MOE',
                    'syllabus_version' => '2025-2026-T1-P1',
                    'year_level' => 'GRADE-6',
                    'subject_references' => ['KW-MOE:SUBJECT:ARABIC'],
                ],
                'content_types' => ['lesson', 'practice_quiz', 'mock_exam'],
                'generation' => [
                    'include_answer_explanations' => true,
                    'maximum_questions_per_quiz' => 10,
                    'paid_ai_required' => false,
                ],
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(ContentPreparationRequests::class)
            ->assertSee((string) $created['preparation_request_id'])
            ->assertSee('KW-MOE:TRACK:MIDDLE-SCHOOL')
            ->assertSee('GRADE-6')
            ->assertSee('KW-MOE:SUBJECT:ARABIC')
            ->assertSee('Open request')
            ->call('setLocale', 'ar')
            ->assertSee('فتح الطلب')
            ->assertSeeHtml('dir="rtl"')
            ->call('setLocale', 'fr')
            ->assertSee('Ouvrir la demande')
            ->assertSeeHtml('dir="ltr"');
    }

    public function test_preparation_request_history_navigation_is_localized(): void
    {
        App::setLocale('en');
        $this->assertSame('Preparation Requests', ContentPreparationRequests::getNavigationLabel());

        App::setLocale('ar');
        $this->assertSame('طلبات إعداد المحتوى', ContentPreparationRequests::getNavigationLabel());

        App::setLocale('fr');
        $this->assertSame('Demandes de préparation', ContentPreparationRequests::getNavigationLabel());
    }

    public function test_admin_capability_registry_lists_interactive_student_background_policy_internal_and_gated_surfaces(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'locale' => 'en',
        ]);
        $this->actingAs($admin);

        Livewire::test(SystemCapabilities::class)
            ->assertSee('Content preparation, prompt/bundle and returned ZIP')
            ->assertSee('Preparation request history and saved settings')
            ->assertSee('Content rights and evidence review')
            ->assertSee('Dry-run, review, canonical import, publication and retry')
            ->assertSee('Registration, login, verification, recovery, sessions and account')
            ->assertSee('Academic catalogue, activation and reset/change')
            ->assertSee('Study and lesson reading')
            ->assertSee('Practice/assessment with server-authoritative order and scoring')
            ->assertSee('Progress and mastery')
            ->assertSee('Offline answer sync, retry and conflict recovery')
            ->assertSee('Advertising eligibility and no-ad policy')
            ->assertSee('Outbox, idempotency and publication transaction controls')
            ->assertSee('Runtime Inspector, diagnostics and correlation')
            ->assertSee('Background')
            ->assertSee('Policy')
            ->assertSee('Internal')
            ->assertSee('Gated')
            ->call('setLocale', 'ar')
            ->assertSee('وظائف النظام')
            ->assertSeeHtml('dir="rtl"')
            ->call('setLocale', 'fr')
            ->assertSee('Fonctions du système')
            ->assertSeeHtml('dir="ltr"');
    }

    public function test_release_badge_shows_short_build_and_preserves_full_sha_for_verification(): void
    {
        $release = str_repeat('a', 40);
        $html = view('filament.release-badge', ['release' => $release])->render();

        $this->assertStringContainsString('Build aaaaaaaaaaaa', $html);
        $this->assertStringContainsString($release, $html);
        $this->assertStringContainsString('data-testid="modrik-release-badge"', $html);
    }
}
