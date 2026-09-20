<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_child_links', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('parent_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('child_user_id')->constrained('users')->restrictOnDelete();
            $table->string('status', 24)->default('active');
            $table->timestamp('linked_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['parent_user_id', 'child_user_id'], 'parent_child_unique');
            $table->index(['parent_user_id', 'status'], 'parent_child_parent_status_idx');
            $table->index(['child_user_id', 'status'], 'parent_child_child_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_child_links');
    }
};
