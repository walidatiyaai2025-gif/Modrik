<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preparation_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('schema_version', 20);
            $table->char('settings_hash', 64);
            $table->json('normalized_settings');
            $table->text('prompt');
            $table->string('status', 24);
            $table->timestamps();
            $table->index(['created_by', 'status']);
        });

        Schema::create('preparation_imports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('preparation_request_id')->nullable()->constrained('preparation_requests')->restrictOnDelete();
            $table->char('claimed_preparation_request_id', 26)->nullable();
            $table->foreignUlid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->char('archive_hash', 64);
            $table->ulid('pack_id')->nullable();
            $table->string('rights_status', 32)->nullable();
            $table->string('status', 24);
            $table->json('validation_summary');
            $table->unsignedInteger('imported_file_count')->default(0);
            $table->unsignedInteger('imported_record_count')->default(0);
            $table->timestamps();
            $table->unique(['uploaded_by', 'archive_hash'], 'preparation_archive_actor_unique');
            $table->index(['preparation_request_id', 'status']);
        });

        Schema::create('preparation_import_files', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('preparation_import_id')->constrained('preparation_imports')->cascadeOnDelete();
            $table->string('path', 240);
            $table->string('media_type', 80);
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('status', 24);
            $table->unsignedInteger('imported_records')->default(0);
            $table->timestamps();
            $table->unique(['preparation_import_id', 'path'], 'preparation_import_path_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preparation_import_files');
        Schema::dropIfExists('preparation_imports');
        Schema::dropIfExists('preparation_requests');
    }
};
