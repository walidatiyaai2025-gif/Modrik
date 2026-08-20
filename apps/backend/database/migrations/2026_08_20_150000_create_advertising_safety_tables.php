<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertising_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedInteger('version')->unique();
            $table->boolean('global_enabled')->default(false);
            $table->timestamp('effective_at');
            $table->timestamp('expires_at');
            $table->foreignUlid('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('change_reference', 120);
            $table->timestamps();
            $table->index(['effective_at', 'expires_at']);
        });

        Schema::create('advertising_placements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('advertising_policy_id')->constrained('advertising_policies')->cascadeOnDelete();
            $table->string('placement_code', 64);
            $table->boolean('enabled')->default(false);
            $table->timestamps();
            $table->unique(['advertising_policy_id', 'placement_code'], 'advertising_policy_placement_unique');
        });

        Schema::create('user_age_assurances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('age_band', 24);
            $table->string('assurance_source', 40);
            $table->timestamp('assured_at');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique('user_id');
            $table->index(['age_band', 'expires_at']);
        });

        Schema::create('advertising_decision_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('advertising_policy_id')->nullable()->constrained('advertising_policies')->restrictOnDelete();
            $table->string('placement_code', 64);
            $table->string('zone_code', 32)->nullable();
            $table->boolean('advertising_allowed');
            $table->string('reason_code', 40);
            $table->unsignedInteger('policy_version')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->index(['user_id', 'decided_at']);
            $table->index(['reason_code', 'decided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertising_decision_audits');
        Schema::dropIfExists('user_age_assurances');
        Schema::dropIfExists('advertising_placements');
        Schema::dropIfExists('advertising_policies');
    }
};
