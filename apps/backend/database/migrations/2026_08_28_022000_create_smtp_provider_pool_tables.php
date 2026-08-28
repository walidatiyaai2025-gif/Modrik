<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smtp_providers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('name', 80);
            $table->string('host', 255);
            $table->unsignedSmallInteger('port');
            $table->string('scheme', 16)->nullable();
            $table->string('username', 255)->nullable();
            $table->text('password_ciphertext');
            $table->string('from_address', 255);
            $table->string('from_name', 100)->default('MODRIK');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_tested_at')->nullable();
            $table->string('last_test_status', 16)->nullable();
            $table->string('last_error_code', 80)->nullable();
            $table->timestamps();
        });

        Schema::create('smtp_provider_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('smtp_provider_id')->index();
            $table->ulid('actor_id')->nullable()->index();
            $table->string('action', 40);
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('reason', 500);
            $table->timestamp('occurred_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smtp_provider_audits');
        Schema::dropIfExists('smtp_providers');
    }
};
