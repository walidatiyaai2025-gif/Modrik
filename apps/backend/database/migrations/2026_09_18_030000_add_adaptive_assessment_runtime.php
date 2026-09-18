<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->unsignedInteger('duration_ms')->default(0)->after('value');
            $table->unsignedSmallInteger('hint_count')->default(0)->after('duration_ms');
            $table->boolean('is_correct')->nullable()->after('hint_count');
            $table->decimal('awarded_score', 8, 2)->nullable()->after('is_correct');
            $table->timestamp('graded_at')->nullable()->after('awarded_score');
        });
    }

    public function down(): void
    {
        Schema::table('attempt_answers', function (Blueprint $table): void {
            $table->dropColumn(['duration_ms', 'hint_count', 'is_correct', 'awarded_score', 'graded_at']);
        });
    }
};
