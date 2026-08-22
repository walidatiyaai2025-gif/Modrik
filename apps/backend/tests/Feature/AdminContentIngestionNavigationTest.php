<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentIngestionOperations;
use App\Filament\Pages\ContentPreparationRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentIngestionNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingestion_surface_routes_upload_to_originating_preparation_request_workflow(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'account_status' => 'active', 'locale' => 'en']);
        $this->actingAs($admin);

        $component = Livewire::test(ContentIngestionOperations::class);
        $component
            ->assertOk()
            ->assertSee('Returned ZIP upload')
            ->assertSee('Open returned ZIP upload');

        /** @var ContentIngestionOperations $page */
        $page = $component->instance();
        $this->assertSame(ContentPreparationRequests::getUrl(), $page->uploadSurfaceUrl());
    }
}
