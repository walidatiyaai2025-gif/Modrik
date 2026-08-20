<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_recovery_actions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->ulid('request_id');
            $table->foreignUlid('delivery_attempt_id')->constrained('outbox_delivery_attempts')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status', 24);
            $table->string('error_code', 40)->nullable();
            $table->char('error_fingerprint', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['outbox_event_id', 'request_id'], 'outbox_recovery_event_request_unique');
            $table->unique('delivery_attempt_id', 'outbox_recovery_delivery_attempt_unique');
            $table->index(['status', 'started_at'], 'outbox_recovery_status_started_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_recovery_actions');
    }
};
