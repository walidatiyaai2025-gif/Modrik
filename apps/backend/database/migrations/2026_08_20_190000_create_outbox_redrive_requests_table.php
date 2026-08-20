<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_redrive_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('outbox_event_id')->constrained('outbox_events')->cascadeOnDelete();
            $table->unsignedSmallInteger('exhausted_attempt_number');
            $table->string('status', 24)->default('requested');
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedSmallInteger('successful_attempt_number')->nullable();
            $table->timestamps();
            $table->unique(
                ['outbox_event_id', 'exhausted_attempt_number'],
                'outbox_redrive_event_attempt_unique',
            );
            $table->index(['status', 'requested_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_redrive_requests');
    }
};
