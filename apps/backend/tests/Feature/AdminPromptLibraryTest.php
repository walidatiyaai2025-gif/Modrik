<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentOperations;
use App\Filament\Pages\PromptLibrary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPromptLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_content_team_can_open_prompt_library_and_student_cannot(): void
    {
        foreach (['admin', 'content_team'] as $role) {
            $user = User::factory()->create(['role' => $role, 'account_status' => 'active', 'locale' => 'en']);
            $this->actingAs($user);

            Livewire::test(PromptLibrary::class)
                ->assertOk()
                ->assertSee('MODRIK_QUESTION_BANK_MASTER_V1')
                ->assertSee('modrik-question-bank-v1')
                ->assertSee('Manual/offline preparation only')
                ->assertSee('Copy Prompt')
                ->assertSee('Compatible sample output')
                ->assertSee('Which option equals 1 + 1?');

            auth()->logout();
        }

        $student = User::factory()->create(['role' => 'student', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($student);
        $this->assertFalse(PromptLibrary::canAccess());
    }

    public function test_prompt_library_seed_is_pinned_to_canonical_prompt_and_question_bank_fixture(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        /** @var PromptLibrary $page */
        $page = Livewire::test(PromptLibrary::class)->instance();
        $entries = $page->prompts();
        $this->assertCount(1, $entries);

        $entry = $entries[0];
        $this->assertSame('MODRIK_QUESTION_BANK_MASTER_V1', $entry['id']);
        $this->assertSame('1.0.0', $entry['version']);
        $this->assertSame('modrik-question-bank-v1', $entry['compatible_schema']);
        $this->assertSame('active', $entry['status']);
        $this->assertSame('none', $entry['runtime_dependency']);

        $canonical = file_get_contents(base_path('../../docs/learning/MODRIK_QUESTION_BANK_MASTER_V1.md'));
        $this->assertIsString($canonical);
        $parts = explode("\n---\n", $canonical, 2);
        $this->assertCount(2, $parts);
        $this->assertSame(trim($parts[1]), trim($entry['prompt']));

        $fixture = file_get_contents(base_path('../../schemas/question-bank/v1/fixtures/valid/minimal.json'));
        $this->assertIsString($fixture);
        $this->assertSame(
            json_decode($fixture, true, flags: JSON_THROW_ON_ERROR),
            json_decode($entry['sample_output'], true, flags: JSON_THROW_ON_ERROR),
        );

        /** @var ContentOperations $operations */
        $operations = Livewire::test(ContentOperations::class)->instance();
        $this->assertContains(PromptLibrary::getUrl(), array_column($operations->supportingSurfaces(), 'url'));
    }

    public function test_prompt_library_navigation_is_localized_and_arabic_is_rtl(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        App::setLocale('en');
        $this->assertSame('Prompt Library', PromptLibrary::getNavigationLabel());
        App::setLocale('fr');
        $this->assertSame('Bibliothèque de prompts', PromptLibrary::getNavigationLabel());
        App::setLocale('ar');
        $this->assertSame('مكتبة الـPrompt', PromptLibrary::getNavigationLabel());

        Livewire::test(PromptLibrary::class)
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }
}
