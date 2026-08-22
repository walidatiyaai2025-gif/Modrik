<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_track_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('academic_track_id')->constrained('academic_tracks')->restrictOnDelete();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['academic_track_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_track_audits');
    }
};
