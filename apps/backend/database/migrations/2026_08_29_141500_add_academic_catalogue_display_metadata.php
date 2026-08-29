<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_year_metadata', function (Blueprint $table): void {
            $table->string('year_level', 160)->primary();
            $table->json('labels');
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::table('academic_tracks', function (Blueprint $table): void {
            $table->integer('display_order')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('academic_tracks', function (Blueprint $table): void {
            $table->dropIndex(['display_order']);
            $table->dropColumn('display_order');
        });

        Schema::dropIfExists('academic_year_metadata');
    }
};
