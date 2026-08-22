<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_notifications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 48);
            $table->json('title');
            $table->json('body');
            $table->string('action', 32)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_at']);
            $table->index(['user_id', 'read_at', 'occurred_at'], 'student_notifications_unread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_notifications');
    }
};
