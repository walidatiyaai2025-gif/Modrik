<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparation_requests', function (Blueprint $table): void {
            $table->char('superseded_by_request_id', 26)->nullable();
            $table->timestamp('superseded_at')->nullable();
            $table->index(['status', 'superseded_at'], 'preparation_request_supersession_idx');
        });

        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->longText('validated_content')->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->json('dry_run_summary')->nullable();
            $table->char('dry_run_hash', 64)->nullable();
            $table->string('review_decision', 24)->nullable();
            $table->text('review_reason')->nullable();
            $table->char('reviewed_by', 26)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->char('published_by', 26)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('operation_state', 24)->default('idle');
            $table->string('operation_checkpoint', 64)->nullable();
            $table->unsignedInteger('operation_attempts')->default(0);
            $table->string('last_error_code', 96)->nullable();
            $table->char('last_error_fingerprint', 64)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->index(['status', 'review_decision'], 'preparation_import_review_idx');
            $table->index(['operation_state', 'last_error_at'], 'preparation_import_operation_idx');
        });

        Schema::create('content_publications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('preparation_import_id')->constrained('preparation_imports')->restrictOnDelete();
            $table->char('initiated_by', 26);
            $table->string('status', 24);
            $table->string('checkpoint', 64)->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code', 96)->nullable();
            $table->char('last_error_fingerprint', 64)->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique('preparation_import_id');
            $table->index(['status', 'last_error_at']);
        });

        Schema::create('content_publication_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('content_publication_id')->constrained('content_publications')->cascadeOnDelete();
            $table->string('entity_type', 32);
            $table->char('entity_id', 26);
            $table->string('action', 24);
            $table->timestamps();
            $table->unique(['content_publication_id', 'entity_type', 'entity_id'], 'content_publication_entity_unique');
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('content_workflow_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->char('preparation_request_id', 26)->nullable();
            $table->char('preparation_import_id', 26)->nullable();
            $table->char('actor_id', 26)->nullable();
            $table->string('action', 64);
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata');
            $table->timestamp('created_at');
            $table->index(['preparation_import_id', 'created_at'], 'content_workflow_import_audit_idx');
            $table->index(['preparation_request_id', 'created_at'], 'content_workflow_request_audit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_workflow_audits');
        Schema::dropIfExists('content_publication_items');
        Schema::dropIfExists('content_publications');

        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->dropIndex('preparation_import_review_idx');
            $table->dropIndex('preparation_import_operation_idx');
            $table->dropColumn([
                'validated_content',
                'content_hash',
                'dry_run_summary',
                'dry_run_hash',
                'review_decision',
                'review_reason',
                'reviewed_by',
                'reviewed_at',
                'published_by',
                'published_at',
                'operation_state',
                'operation_checkpoint',
                'operation_attempts',
                'last_error_code',
                'last_error_fingerprint',
                'last_error_at',
            ]);
        });

        Schema::table('preparation_requests', function (Blueprint $table): void {
            $table->dropIndex('preparation_request_supersession_idx');
            $table->dropColumn(['superseded_by_request_id', 'superseded_at']);
        });
    }
};
