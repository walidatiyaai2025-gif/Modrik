<?php

namespace Tests\Feature;

use App\Filament\Pages\PublicLegalStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

final class AdminPublicLegalStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_access_public_legal_status(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $content = User::factory()->create(['role' => 'content_team', 'account_status' => 'active']);
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active']);

        $this->actingAs($admin)->get('/admin/public-legal-status')
            ->assertOk()
            ->assertSee('data-testid="modrik-public-legal-status"', false);

        $this->actingAs($content)->get('/admin/public-legal-status')->assertForbidden();
        $this->actingAs($student)->get('/admin/public-legal-status')->assertForbidden();
    }

    public function test_status_surface_matches_current_public_contract_keys_and_blockers(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        $component = Livewire::test(PublicLegalStatus::class)
            ->assertSee('/privacy')
            ->assertSee('/terms')
            ->assertSee('/account-deletion')
            ->assertSee('/support')
            ->assertSee('LEGAL_ENTITY_CONTROLLER')
            ->assertSee('POLICY_VERSION')
            ->assertSee('not_implemented')
            ->assertSee('blocked_pending_owner_legal_inputs');

        $source = file_get_contents(base_path('../web/src/public-site/content.ts'));
        $this->assertIsString($source);

        /** @var PublicLegalStatus $instance */
        $instance = $component->instance();
        $this->assertInstanceOf(PublicLegalStatus::class, $instance);

        foreach ($instance->publicPages() as $page) {
            if ($page['key'] === 'landing') {
                continue;
            }

            $this->assertStringContainsString('key: "'.$page['key'].'"', $source);
        }

        foreach ($instance->legalBlockers() as $blocker) {
            $this->assertStringContainsString('"'.$blocker.'"', $source);
        }
    }

    public function test_surface_is_read_only_and_does_not_invent_legal_authority(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        Livewire::test(PublicLegalStatus::class)
            ->assertSee('No legal text editing or publishing.')
            ->assertSee('No fabricated legal entity, jurisdiction or contact details.')
            ->assertDontSee('Save legal policy')
            ->assertDontSee('Publish legal policy')
            ->assertDontSee('LEGAL_ENTITY_CONTROLLER=')
            ->assertDontSee('POLICY_VERSION=');
    }

    public function test_arabic_surface_is_rtl_and_french_navigation_label_is_localized(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active']);
        $this->actingAs($admin);

        App::setLocale('ar');
        Livewire::test(PublicLegalStatus::class)
            ->assertSee('dir="rtl"', false)
            ->assertSee('المدخلات القانونية المحجوبة');

        App::setLocale('fr');
        $this->assertSame('Public, juridique et aide', PublicLegalStatus::getNavigationLabel());
    }
}
