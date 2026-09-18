<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_source_materials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source_key', 200)->unique();
            $table->string('name', 500);
            $table->string('kind', 32)->default('other');
            $table->string('reference', 500)->nullable();
            $table->string('rights_status', 24)->default('pending');
            $table->text('rights_note')->nullable();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'rights_status'], 'qb_source_kind_rights_idx');
        });

        Schema::create('question_bank_imports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('preparation_request_id')->constrained('preparation_requests')->restrictOnDelete();
            $table->foreignUlid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->ulid('pack_id')->unique();
            $table->string('schema_version', 48);
            $table->char('settings_hash', 64);
            $table->string('prompt_id', 120);
            $table->string('prompt_version', 50);
            $table->char('content_hash', 64);
            $table->string('status', 24);
            $table->json('validation_summary');
            $table->longText('raw_payload');
            $table->text('review_reason')->nullable();
            $table->foreignUlid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUlid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['uploaded_by', 'content_hash'], 'qb_import_actor_content_unique');
            $table->index(['status', 'created_at'], 'qb_import_status_time_idx');
        });

        Schema::create('question_bank_import_sources', function (Blueprint $table): void {
            $table->foreignUlid('question_bank_import_id')->constrained('question_bank_imports')->cascadeOnDelete();
            $table->foreignUlid('source_material_id')->constrained('question_bank_source_materials')->restrictOnDelete();
            $table->string('source_name', 500);
            $table->timestamps();
            $table->primary(['question_bank_import_id', 'source_material_id'], 'qb_import_source_pk');
        });

        Schema::create('question_bank_import_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_bank_import_id')->constrained('question_bank_imports')->cascadeOnDelete();
            $table->string('external_id', 100);
            $table->foreignUlid('source_material_id')->nullable()->constrained('question_bank_source_materials')->restrictOnDelete();
            $table->foreignUlid('curriculum_node_id')->nullable()->constrained('curriculum_nodes')->restrictOnDelete();
            $table->foreignUlid('learning_objective_id')->nullable()->constrained('learning_objectives')->restrictOnDelete();
            $table->foreignUlid('canonical_question_id')->nullable()->constrained('questions')->nullOnDelete();
            $table->string('type', 40);
            $table->string('language', 35);
            $table->string('difficulty', 24);
            $table->unsignedInteger('source_page')->nullable();
            $table->string('scope_state', 32)->default('mapping_required');
            $table->string('status', 24)->default('staged');
            $table->char('content_hash', 64);
            $table->longText('payload');
            $table->string('rejection_code', 96)->nullable();
            $table->timestamps();
            $table->unique(['question_bank_import_id', 'external_id'], 'qb_import_external_unique');
            $table->index(['content_hash', 'status'], 'qb_item_hash_status_idx');
            $table->index(['curriculum_node_id', 'status'], 'qb_item_node_status_idx');
        });

        Schema::create('question_bank_workflow_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('question_bank_import_id')->nullable()->constrained('question_bank_imports')->cascadeOnDelete();
            $table->foreignUlid('question_bank_import_item_id')->nullable()->constrained('question_bank_import_items')->cascadeOnDelete();
            $table->foreignUlid('source_material_id')->nullable()->constrained('question_bank_source_materials')->restrictOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 64);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['question_bank_import_id', 'created_at'], 'qb_audit_import_time_idx');
            $table->index(['source_material_id', 'created_at'], 'qb_audit_source_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_bank_workflow_audits');
        Schema::dropIfExists('question_bank_import_items');
        Schema::dropIfExists('question_bank_import_sources');
        Schema::dropIfExists('question_bank_imports');
        Schema::dropIfExists('question_bank_source_materials');
    }
};
