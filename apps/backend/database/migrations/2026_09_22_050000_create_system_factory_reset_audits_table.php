<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_factory_reset_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 48);
            $table->unsignedInteger('tables_cleared')->default(0);
            $table->unsignedBigInteger('rows_deleted')->default(0);
            $table->json('summary');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['occurred_at']);
            $table->index(['actor_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_factory_reset_audits');
    }
};
