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

        Schema::create('academic_catalogue_metadata_audits', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('target_type', 24);
            $table->string('target_key', 160);
            $table->foreignUlid('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 48);
            $table->json('before')->nullable();
            $table->json('after');
            $table->string('reason', 500);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['target_type', 'target_key', 'occurred_at'], 'acad_catalogue_audit_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_catalogue_metadata_audits');

        Schema::table('academic_tracks', function (Blueprint $table): void {
            $table->dropIndex(['display_order']);
            $table->dropColumn('display_order');
        });

        Schema::dropIfExists('academic_year_metadata');
    }
};
