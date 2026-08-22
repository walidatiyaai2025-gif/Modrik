<?php

namespace Tests\Feature;

use App\Filament\Admin\Dashboard;
use App\Filament\Pages\ContentPreparationRequests;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUxFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_is_modrik_operational_surface_not_stock_filament_info(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $response = $this->actingAs($admin)->get('/admin');

        $response
            ->assertOk()
            ->assertSee('MODRIK Operations')
            ->assertSee('Operations stable')
            ->assertSee('Quick actions')
            ->assertSee('Recent operational activity')
            ->assertSee('data-testid="modrik-admin-dashboard"', false)
            ->assertSee('data-testid="modrik-admin-topbar-context"', false)
            ->assertDontSee('Filament Info');
    }

    public function test_admin_language_switch_is_global_whitelisted_and_persists_rtl_locale(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $this->actingAs($admin)
            ->get('/admin?admin_locale=ar')
            ->assertOk()
            ->assertSee('مركز عمليات مُدرك')
            ->assertSee('dir="rtl"', false);

        $this->assertSame('ar', session('admin_locale'));

        $this->get('/admin?admin_locale=unsupported')
            ->assertOk()
            ->assertSee('مركز عمليات مُدرك');

        $this->assertSame('ar', session('admin_locale'));
    }

    public function test_admin_theme_tracks_canonical_brand_tokens_and_removes_stock_widgets(): void
    {
        $tokenPath = base_path('../../packages/design-tokens/tokens.json');
        $tokens = json_decode((string) file_get_contents($tokenPath), true, flags: JSON_THROW_ON_ERROR);
        $theme = (string) file_get_contents(resource_path('css/filament/admin/theme.css'));
        $provider = (string) file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));

        foreach ([
            $tokens['color']['brand']['navy']['$value'],
            $tokens['color']['brand']['blue']['$value'],
            $tokens['color']['brand']['teal']['$value'],
            $tokens['color']['brand']['sky']['$value'],
            $tokens['color']['brand']['amber']['$value'],
            $tokens['color']['neutral']['background']['$value'],
            $tokens['color']['neutral']['slate']['$value'],
            $tokens['color']['neutral']['ink']['$value'],
            $tokens['color']['semantic']['success']['$value'],
            $tokens['color']['semantic']['warning']['$value'],
            $tokens['color']['semantic']['error']['$value'],
            $tokens['color']['semantic']['info']['$value'],
        ] as $value) {
            $this->assertStringContainsString((string) $value, $theme);
        }

        $this->assertStringContainsString("@import '../../../../vendor/filament/filament/resources/css/theme.css';", $theme);
        $this->assertStringContainsString("'Poppins'", $theme);
        $this->assertStringContainsString("'Noto Kufi Arabic'", $theme);
        $this->assertStringContainsString('viteTheme', $provider);
        $this->assertStringNotContainsString('FilamentInfoWidget', $provider);
        $this->assertStringNotContainsString('AccountWidget', $provider);
    }

    public function test_navigation_group_labels_are_localized_for_shared_admin_information_architecture(): void
    {
        App::setLocale('en');
        $this->assertSame('Content', AdminNavigationGroup::Content->getLabel());
        $this->assertSame('Operations', AdminNavigationGroup::Operations->getLabel());

        App::setLocale('ar');
        $this->assertSame('المحتوى', AdminNavigationGroup::Content->getLabel());
        $this->assertSame('التشغيل والمراقبة', AdminNavigationGroup::Operations->getLabel());

        App::setLocale('fr');
        $this->assertSame('Contenu', AdminNavigationGroup::Content->getLabel());
        $this->assertSame('Opérations', AdminNavigationGroup::Operations->getLabel());
    }

    public function test_preparation_history_prioritizes_operator_context_and_keeps_ids_as_traceability(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        $created = app(ContentAdminWorkflowService::class)->createRequest($admin, [
            'schema_version' => '1.0.0',
            'settings' => [
                'locales' => ['ar', 'en'],
                'academic_scope' => [
                    'track_reference' => 'UX-TRACK',
                    'board_reference' => 'UX-BOARD',
                    'syllabus_version' => 'UX-V1',
                    'year_level' => 'GRADE-6',
                    'subject_references' => ['UX:SUBJECT:ARABIC'],
                ],
                'content_types' => ['lesson', 'practice_quiz'],
                'generation' => [
                    'include_answer_explanations' => true,
                    'maximum_questions_per_quiz' => 10,
                    'paid_ai_required' => false,
                ],
            ],
        ]);

        $this->actingAs($admin);

        Livewire::test(ContentPreparationRequests::class)
            ->assertSee('UX-TRACK')
            ->assertSee('GRADE-6')
            ->assertSee('UX:SUBJECT:ARABIC')
            ->assertSee('Open request')
            ->assertSee('Technical traceability')
            ->assertSee((string) $created['preparation_request_id'])
            ->assertSeeHtml('<details')
            ->call('setLocale', 'ar')
            ->assertSee('فتح الطلب')
            ->assertSee('بيانات التتبع التقنية')
            ->assertSeeHtml('dir="rtl"')
            ->call('setLocale', 'fr')
            ->assertSee('Ouvrir la demande')
            ->assertSee('Traçabilité technique')
            ->assertSeeHtml('dir="ltr"');
    }

    public function test_dashboard_metrics_are_backed_by_persisted_workflow_data(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);

        app(ContentAdminWorkflowService::class)->createRequest($admin, [
            'schema_version' => '1.0.0',
            'settings' => [
                'locales' => ['en'],
                'academic_scope' => [
                    'track_reference' => 'DASHBOARD-TRACK',
                    'board_reference' => 'DASHBOARD-BOARD',
                    'syllabus_version' => 'DASHBOARD-V1',
                    'year_level' => 'GRADE-6',
                    'subject_references' => ['DASHBOARD:SUBJECT'],
                ],
                'content_types' => ['lesson'],
                'generation' => [
                    'include_answer_explanations' => true,
                    'maximum_questions_per_quiz' => 10,
                    'paid_ai_required' => false,
                ],
            ],
        ]);

        $this->actingAs($admin);

        $dashboard = app(Dashboard::class);
        $metrics = $dashboard->metrics();

        $this->assertSame('Preparation requests', $metrics[0]['label']);
        $this->assertSame(1, $metrics[0]['value']);

        Livewire::test(Dashboard::class)
            ->assertSee('Preparation requests');
    }
}
