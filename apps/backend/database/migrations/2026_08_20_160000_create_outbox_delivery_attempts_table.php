<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_delivery_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24);
            $table->string('error_code', 40)->nullable();
            $table->char('error_fingerprint', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamps();
            $table->unique(['outbox_event_id', 'attempt_number'], 'outbox_event_attempt_unique');
            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_delivery_attempts');
    }
};
