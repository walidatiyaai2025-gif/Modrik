<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->string('rights_basis', 32)->nullable();
            $table->json('rights_source_references')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('preparation_imports', function (Blueprint $table): void {
            $table->dropColumn(['rights_basis', 'rights_source_references']);
        });
    }
};
