<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_update_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_version', 64)->nullable();
            $table->string('to_version', 64)->nullable();
            $table->char('release_sha', 40)->nullable();
            $table->string('status', 48);
            $table->json('safe_details')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_update_history');
    }
};
