<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_account_operation_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('target_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 64);
            $table->string('reason', 500);
            $table->json('before')->nullable();
            $table->json('after');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['target_user_id', 'occurred_at'], 'acct_ops_target_occurred_idx');
            $table->index(['actor_id', 'occurred_at'], 'acct_ops_actor_occurred_idx');
            $table->index(['action', 'occurred_at'], 'acct_ops_action_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_account_operation_audits');
    }
};
