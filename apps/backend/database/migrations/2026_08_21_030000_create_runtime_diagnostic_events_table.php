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
            $table->string('event_class', 24);
            $table->string('severity', 16);
            $table->string('surface', 24);
            $table->string('category', 64);
            $table->string('correlation_id', 64);
            $table->string('route_name', 160)->nullable();
            $table->string('stable_code', 100)->nullable();
            $table->string('outcome', 32);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('environment', 32)->nullable();
            $table->string('build_ref', 128)->nullable();
            $table->ulid('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');

            $table->index(['correlation_id', 'recorded_at'], 'runtime_diag_corr_time');
            $table->index(['severity', 'recorded_at'], 'runtime_diag_severity_time');
            $table->index(['surface', 'recorded_at'], 'runtime_diag_surface_time');
            $table->index(['stable_code', 'recorded_at'], 'runtime_diag_code_time');
            $table->index(['event_class', 'recorded_at'], 'runtime_diag_class_time');
            $table->index(['actor_id', 'recorded_at'], 'runtime_diag_actor_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_diagnostic_events');
    }
};
