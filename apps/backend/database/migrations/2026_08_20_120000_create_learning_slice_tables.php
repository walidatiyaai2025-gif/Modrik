<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_tracks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 120)->unique();
            $table->string('board_reference', 160)->nullable();
            $table->string('syllabus_version', 120)->nullable();
            $table->string('year_level', 40);
            $table->json('title');
            $table->boolean('is_fixture')->default(false);
            $table->timestamps();
        });

        Schema::create('user_academic_contexts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('academic_track_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->timestamp('activated_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('curriculum_nodes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_track_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('parent_id')->nullable()->constrained('curriculum_nodes')->restrictOnDelete();
            $table->string('code', 160);
            $table->string('type', 32);
            $table->json('title');
            $table->string('status', 24)->default('published');
            $table->timestamps();
            $table->unique(['academic_track_id', 'parent_id', 'code'], 'curriculum_node_scope_unique');
        });

        Schema::create('lessons', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_node_id')->constrained()->restrictOnDelete();
            $table->string('slug', 160);
            $table->unsignedInteger('content_version');
            $table->json('title');
            $table->string('status', 24);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['curriculum_node_id', 'slug', 'content_version'], 'lesson_version_unique');
        });

        Schema::create('lesson_blocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('type', 40);
            $table->json('content');
            $table->timestamps();
            $table->unique(['lesson_id', 'position']);
        });

        Schema::create('questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_node_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('content_version');
            $table->string('type', 40);
            $table->json('prompt');
            $table->json('options')->nullable();
            $table->json('answer_contract');
            $table->json('explanation');
            $table->decimal('maximum_score', 8, 2);
            $table->string('status', 24)->default('published');
            $table->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('curriculum_node_id')->constrained()->restrictOnDelete();
            $table->string('kind', 32);
            $table->unsignedInteger('blueprint_version');
            $table->json('title');
            $table->string('status', 24)->default('published');
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->foreignUlid('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('source_position');
            $table->unique(['quiz_id', 'question_id']);
            $table->unique(['quiz_id', 'source_position']);
        });

        Schema::create('attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('quiz_id')->constrained()->restrictOnDelete();
            $table->string('status', 24);
            $table->text('seed_encrypted');
            $table->unsignedInteger('blueprint_version');
            $table->string('ordering_algorithm', 40);
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->decimal('score', 8, 2)->nullable();
            $table->decimal('max_score', 8, 2)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('attempt_questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('question_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->json('question_snapshot');
            $table->timestamps();
            $table->unique(['attempt_id', 'position']);
            $table->unique(['attempt_id', 'question_id']);
        });

        Schema::create('attempt_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('attempt_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('value');
            $table->timestamp('answered_at');
            $table->timestamps();
            $table->unique(['attempt_question_id', 'revision']);
        });

        Schema::create('progress_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('curriculum_node_id')->constrained()->restrictOnDelete();
            $table->decimal('mastery', 5, 4);
            $table->unsignedInteger('source_version');
            $table->timestamp('calculated_at');
            $table->timestamps();
            $table->unique(['user_id', 'curriculum_node_id', 'source_version'], 'progress_source_unique');
        });

        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('operation', 80);
            $table->char('key_digest', 64);
            $table->char('request_hash', 64);
            $table->string('state', 24);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['actor_id', 'operation', 'key_digest'], 'idempotency_scope_unique');
        });

        Schema::create('outbox_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('aggregate_type', 80);
            $table->ulid('aggregate_id');
            $table->string('event_type', 120);
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['published_at', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_events');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('progress_snapshots');
        Schema::dropIfExists('attempt_answers');
        Schema::dropIfExists('attempt_questions');
        Schema::dropIfExists('attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('questions');
        Schema::dropIfExists('lesson_blocks');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('curriculum_nodes');
        Schema::dropIfExists('user_academic_contexts');
        Schema::dropIfExists('academic_tracks');
    }
};
