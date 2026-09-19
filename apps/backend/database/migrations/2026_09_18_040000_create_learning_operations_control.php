<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_feature_controls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('feature_key', 100)->unique();
            $table->string('state', 24)->default('disabled');
            $table->json('scope')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('learning_feature_control_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('feature_control_id')->constrained('learning_feature_controls')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('from_version')->nullable();
            $table->unsignedInteger('to_version');
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['feature_control_id', 'occurred_at'], 'learning_feature_audit_time_idx');
        });

        Schema::create('learning_job_controls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('job_key', 100)->unique();
            $table->boolean('paused')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 32)->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->json('last_counts')->nullable();
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('learning_job_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_job_control_id')->constrained('learning_job_controls')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('trigger', 24);
            $table->string('status', 32);
            $table->json('counts')->nullable();
            $table->string('error_code', 100)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();
            $table->index(['learning_job_control_id', 'started_at'], 'learning_job_run_time_idx');
        });

        Schema::create('learning_job_control_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('learning_job_control_id')->constrained('learning_job_controls')->cascadeOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->unsignedInteger('from_version');
            $table->unsignedInteger('to_version');
            $table->boolean('before_paused');
            $table->boolean('after_paused');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_job_control_audits');
        Schema::dropIfExists('learning_job_runs');
        Schema::dropIfExists('learning_job_controls');
        Schema::dropIfExists('learning_feature_control_audits');
        Schema::dropIfExists('learning_feature_controls');
    }
};
