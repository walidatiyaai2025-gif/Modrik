<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->string('rights_review_status', 24)->default('pending');
            $table->string('rights_evidence_reference', 500)->nullable();
            $table->text('rights_review_note')->nullable();
            $table->char('rights_reviewed_by', 26)->nullable();
            $table->timestamp('rights_reviewed_at')->nullable();
            $table->index(['rights_review_status', 'status'], 'preparation_import_rights_review_idx');
        });
    }

    public function down(): void
    {
        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->dropIndex('preparation_import_rights_review_idx');
            $table->dropColumn([
                'rights_review_status',
                'rights_evidence_reference',
                'rights_review_note',
                'rights_reviewed_by',
                'rights_reviewed_at',
            ]);
        });
    }
};
