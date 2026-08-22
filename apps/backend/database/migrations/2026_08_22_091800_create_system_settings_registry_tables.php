<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 160);
            $table->string('environment', 32);
            $table->string('value_type', 24);
            $table->json('value');
            $table->unsignedBigInteger('version')->default(1);
            $table->foreignUlid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['key', 'environment']);
            $table->index(['environment', 'key']);
        });

        Schema::create('system_setting_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('system_setting_id')->constrained('system_settings')->restrictOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->unsignedBigInteger('from_version')->nullable();
            $table->unsignedBigInteger('to_version');
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['system_setting_id', 'occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });

        Schema::create('integration_operation_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('environment', 32);
            $table->string('integration', 40);
            $table->string('operation', 64);
            $table->string('target_type', 32)->nullable();
            $table->char('target_fingerprint', 64)->nullable();
            $table->string('result_code', 96);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['integration', 'operation', 'occurred_at'], 'ioa_integration_operation_time_idx');
            $table->index(['environment', 'occurred_at'], 'ioa_environment_time_idx');
            $table->index(['actor_id', 'occurred_at'], 'ioa_actor_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_operation_audits');
        Schema::dropIfExists('system_setting_audits');
        Schema::dropIfExists('system_settings');
    }
};
