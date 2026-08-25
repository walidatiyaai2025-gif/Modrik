<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('academic_tracks', function (Blueprint $table): void {
            $table->string('availability_state', 24)->default('draft')->index();
        });

        // Preserve the catalogue behavior of already-reviewed records while making
        // every future direct insert fail closed until an operator publishes it.
        DB::table('academic_tracks')->update(['availability_state' => 'published']);
    }

    public function down(): void
    {
        Schema::table('academic_tracks', function (Blueprint $table): void {
            $table->dropColumn('availability_state');
        });
    }
};
