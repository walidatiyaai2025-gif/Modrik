<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_objectives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('skill_node_id')->constrained('curriculum_nodes')->restrictOnDelete();
            $table->string('code', 160);
            $table->json('title');
            $table->json('description')->nullable();
            $table->string('status', 24)->default('draft');
            $table->timestamps();
            $table->unique(['skill_node_id', 'code'], 'learning_objective_skill_code_unique');
            $table->index(['skill_node_id', 'status'], 'learning_objective_skill_status_idx');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->foreignUlid('learning_objective_id')
                ->nullable()
                ->after('curriculum_node_id')
                ->constrained('learning_objectives')
                ->restrictOnDelete();
            $table->string('difficulty', 24)->nullable()->after('type');
            $table->string('generation_kind', 24)->default('static')->after('difficulty');
            $table->json('template_contract')->nullable()->after('generation_kind');
            $table->json('source_provenance')->nullable()->after('template_contract');
            $table->string('review_state', 24)->default('approved')->after('source_provenance');
            $table->unsignedInteger('review_version')->default(1)->after('review_state');
            $table->char('reviewed_by', 26)->nullable()->after('review_version');
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->char('published_by', 26)->nullable()->after('reviewed_at');
            $table->timestamp('published_at')->nullable()->after('published_by');
            $table->index(['learning_objective_id', 'status'], 'question_objective_status_idx');
        });

        Schema::create('student_skill_mastery_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('academic_context_id')->constrained('user_academic_contexts')->restrictOnDelete();
            $table->foreignUlid('skill_node_id')->constrained('curriculum_nodes')->restrictOnDelete();
            $table->string('algorithm_version', 80)->nullable();
            $table->decimal('mastery_score', 5, 4)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->unsignedInteger('evidence_count')->default(0);
            $table->unsignedInteger('state_version')->default(1);
            $table->timestamp('last_evidence_at')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['academic_context_id', 'skill_node_id'], 'skill_mastery_context_skill_unique');
            $table->index(['user_id', 'archived_at'], 'skill_mastery_user_archive_idx');
            $table->index(['skill_node_id', 'archived_at'], 'skill_mastery_skill_archive_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_skill_mastery_states');

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropForeign(['learning_objective_id']);
        });
        Schema::table('questions', function (Blueprint $table): void {
            $table->dropIndex('question_objective_status_idx');
            $table->dropColumn([
                'learning_objective_id',
                'difficulty',
                'generation_kind',
                'template_contract',
                'source_provenance',
                'review_state',
                'review_version',
                'reviewed_by',
                'reviewed_at',
                'published_by',
                'published_at',
            ]);
        });

        Schema::dropIfExists('learning_objectives');
    }
};
