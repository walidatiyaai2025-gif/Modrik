<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_track_authorizations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('academic_track_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('authorized_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'academic_track_id'], 'academic_track_authorization_unique');
            $table->index(
                ['user_id', 'revoked_at', 'sort_order', 'academic_track_id'],
                'academic_track_catalogue_lookup',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_track_authorizations');
    }
};
