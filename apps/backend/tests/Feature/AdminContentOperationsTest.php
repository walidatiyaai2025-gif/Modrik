<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentIngestionOperations;
use App\Filament\Pages\ContentOperations;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_content_team_can_open_guided_content_operations_hub(): void
    {
        foreach (['admin', 'content_team'] as $role) {
            $user = User::factory()->create(['role' => $role, 'account_status' => 'active', 'locale' => 'en']);
            $this->actingAs($user);

            Livewire::test(ContentOperations::class)
                ->assertOk()
                ->assertSee('Official content lifecycle')
                ->assertSee('Academic Track')
                ->assertSee('Preparation')
                ->assertSee('Ingestion & Processing')
                ->assertSee('Rights')
                ->assertSee('Review & Publish')
                ->assertSee('Publication authority is preserved');

            auth()->logout();
        }
    }

    public function test_student_cannot_access_content_operations_surfaces(): void
    {
        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($student);

        $this->assertFalse(ContentOperations::canAccess());
        $this->assertFalse(ContentIngestionOperations::canAccess());
    }

    public function test_content_operations_navigation_is_localized_and_rtl_safe(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        App::setLocale('en');
        $this->assertSame('Content Operations', ContentOperations::getNavigationLabel());
        $this->assertSame('Ingestion & Processing', ContentIngestionOperations::getNavigationLabel());
        App::setLocale('fr');
        $this->assertSame('Opérations de contenu', ContentOperations::getNavigationLabel());
        $this->assertSame('Ingestion et traitement', ContentIngestionOperations::getNavigationLabel());
        App::setLocale('ar');
        $this->assertSame('عمليات المحتوى', ContentOperations::getNavigationLabel());
        $this->assertSame('الاستيعاب والمعالجة', ContentIngestionOperations::getNavigationLabel());
        Livewire::test(ContentIngestionOperations::class)->assertSee('dir="rtl"', false);
    }

    public function test_ingestion_surface_starts_with_empty_state_and_metrics(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        Livewire::test(ContentIngestionOperations::class)
            ->assertOk()
            ->assertSee('Ingestion log')
            ->assertSee('No ingestion activity yet.');

        $metrics = Livewire::test(ContentIngestionOperations::class)->instance()->metrics();
        $this->assertSame(['total' => 0, 'processing' => 0, 'blocked' => 0, 'failed' => 0], $metrics);
    }

    public function test_hub_links_only_to_supported_operator_surfaces(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);
        App::setLocale('en');

        $steps = Livewire::test(ContentOperations::class)->instance()->lifecycle();
        $this->assertCount(5, $steps);
        $this->assertSame(['required', 'active', 'active', 'gate', 'gate'], array_column($steps, 'state'));
        $this->assertNotContains('', array_column($steps, 'url'));
    }
}
