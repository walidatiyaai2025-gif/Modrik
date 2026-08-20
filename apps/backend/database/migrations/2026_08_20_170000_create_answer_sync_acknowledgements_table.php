<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answer_sync_acknowledgements', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->constrained('users')->cascadeOnDelete();
            $table->char('operation_id_digest', 64);
            $table->char('request_hash', 64);
            $table->string('outcome', 24);
            $table->string('code', 80)->nullable();
            $table->unsignedInteger('answer_revision')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->boolean('retryable')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['actor_id', 'operation_id_digest'], 'answer_sync_actor_operation_unique');
            $table->index(['actor_id', 'created_at'], 'answer_sync_actor_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_sync_acknowledgements');
    }
};
