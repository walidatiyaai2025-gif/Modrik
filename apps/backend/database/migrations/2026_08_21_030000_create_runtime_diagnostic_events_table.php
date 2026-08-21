<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runtime_diagnostic_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->timestamp('occurred_at')->index();
            $table->string('correlation_id', 96)->index();
            $table->string('data_class', 24)->index();
            $table->string('severity', 16)->index();
            $table->string('surface', 24)->index();
            $table->string('category', 64)->index();
            $table->string('stable_code', 96)->nullable()->index();
            $table->string('route', 160)->nullable();
            $table->string('action', 191)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('environment', 32)->nullable();
            $table->string('build_identity', 96)->nullable();
            $table->foreignUlid('actor_id')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['correlation_id', 'occurred_at'], 'runtime_diag_correlation_time_idx');
            $table->index(['data_class', 'occurred_at'], 'runtime_diag_class_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_diagnostic_events');
    }
};
