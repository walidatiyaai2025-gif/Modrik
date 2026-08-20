<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table): void {
            $table->json('assessment_metadata')->nullable()->after('maximum_score');
            $table->boolean('option_shuffle_safe')->default(false)->after('assessment_metadata');
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->json('blueprint')->nullable()->after('blueprint_version');
        });

        Schema::table('attempts', function (Blueprint $table): void {
            $table->char('seed_fingerprint', 64)->nullable()->after('seed_encrypted');
            $table->json('scope_snapshot')->nullable()->after('blueprint_version');
        });

        $legacyAttempts = DB::table('attempts')
            ->join('quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->get([
                'attempts.id as attempt_id',
                'quizzes.curriculum_node_id',
                'quizzes.kind',
                'quizzes.blueprint_version',
                'quizzes.blueprint',
            ]);

        foreach ($legacyAttempts as $attempt) {
            $blueprint = null;
            if (is_string($attempt->blueprint)) {
                $decoded = json_decode($attempt->blueprint, true);
                $blueprint = is_array($decoded) ? $decoded : null;
            }
            DB::table('attempts')->where('id', $attempt->attempt_id)->update([
                'scope_snapshot' => json_encode([
                    'curriculum_node_id' => $attempt->curriculum_node_id,
                    'quiz_kind' => $attempt->kind,
                    'blueprint_version' => (int) $attempt->blueprint_version,
                    'blueprint' => $blueprint,
                    'question_order_policy' => 'shuffle',
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropColumn(['seed_fingerprint', 'scope_snapshot']);
        });

        Schema::table('quizzes', function (Blueprint $table): void {
            $table->dropColumn('blueprint');
        });

        Schema::table('questions', function (Blueprint $table): void {
            $table->dropColumn(['assessment_metadata', 'option_shuffle_safe']);
        });
    }
};
