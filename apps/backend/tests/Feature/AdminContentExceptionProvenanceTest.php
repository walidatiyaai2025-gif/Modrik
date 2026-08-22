<?php

namespace Tests\Feature;

use App\Filament\Pages\ContentReviewExceptions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AdminContentExceptionProvenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_exception_queue_exposes_persisted_provenance_and_rights_evidence(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'account_status' => 'active',
            'locale' => 'en',
        ]);
        $this->actingAs($admin);

        $requestId = (string) Str::ulid();
        $importId = (string) Str::ulid();
        $now = now();

        DB::table('preparation_requests')->insert([
            'id' => $requestId,
            'created_by' => (string) $admin->getKey(),
            'schema_version' => '1.0.0',
            'settings_hash' => str_repeat('a', 64),
            'normalized_settings' => json_encode(['fixture' => true], JSON_THROW_ON_ERROR),
            'prompt' => 'Synthetic provenance fixture',
            'status' => 'prepared',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('preparation_imports')->insert([
            'id' => $importId,
            'preparation_request_id' => $requestId,
            'uploaded_by' => (string) $admin->getKey(),
            'archive_hash' => str_repeat('b', 64),
            'status' => 'validated',
            'validation_summary' => json_encode(['accepted' => true], JSON_THROW_ON_ERROR),
            'rights_review_status' => 'pending',
            'rights_basis' => 'licensed_source',
            'rights_evidence_reference' => 'rights-evidence-ref-001',
            'operation_state' => 'blocked',
            'operation_checkpoint' => 'rights_gate',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Livewire::test(ContentReviewExceptions::class)
            ->assertOk()
            ->assertSee('Preparation request')
            ->assertSee($requestId)
            ->assertSee('Rights basis')
            ->assertSee('licensed_source')
            ->assertSee('Rights evidence reference')
            ->assertSee('rights-evidence-ref-001')
            ->assertSee('Last updated');
    }
}
