<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table): void {
            $table->foreignUlid('academic_context_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_academic_contexts')
                ->restrictOnDelete();
            $table->timestamp('archived_at')->nullable()->after('completed_at');
            $table->index(['academic_context_id', 'archived_at']);
        });

        Schema::table('progress_snapshots', function (Blueprint $table): void {
            $table->foreignUlid('academic_context_id')
                ->nullable()
                ->after('user_id')
                ->constrained('user_academic_contexts')
                ->restrictOnDelete();
            $table->timestamp('archived_at')->nullable()->after('calculated_at');
            $table->unique(
                ['academic_context_id', 'curriculum_node_id', 'source_version'],
                'progress_context_source_unique',
            );
            $table->index(['user_id', 'archived_at']);
        });
        Schema::table('progress_snapshots', function (Blueprint $table): void {
            // MariaDB used the original composite unique index for the user FK.
            // Add its replacement first so dropping the old index remains portable.
            $table->dropUnique('progress_source_unique');
        });

        $attemptBindings = DB::table('attempts as attempts')
            ->join('quizzes as quizzes', 'quizzes.id', '=', 'attempts.quiz_id')
            ->join('curriculum_nodes as nodes', 'nodes.id', '=', 'quizzes.curriculum_node_id')
            ->join('user_academic_contexts as contexts', function ($join): void {
                $join->on('contexts.user_id', '=', 'attempts.user_id')
                    ->on('contexts.academic_track_id', '=', 'nodes.academic_track_id');
            })
            ->orderByDesc('contexts.activated_at')
            ->get(['attempts.id as attempt_id', 'contexts.id as context_id'])
            ->unique('attempt_id');
        foreach ($attemptBindings as $binding) {
            DB::table('attempts')
                ->where('id', $binding->attempt_id)
                ->update(['academic_context_id' => $binding->context_id]);
        }

        $progressBindings = DB::table('progress_snapshots as progress')
            ->join('curriculum_nodes as nodes', 'nodes.id', '=', 'progress.curriculum_node_id')
            ->join('user_academic_contexts as contexts', function ($join): void {
                $join->on('contexts.user_id', '=', 'progress.user_id')
                    ->on('contexts.academic_track_id', '=', 'nodes.academic_track_id');
            })
            ->orderByDesc('contexts.activated_at')
            ->get(['progress.id as progress_id', 'contexts.id as context_id'])
            ->unique('progress_id');
        foreach ($progressBindings as $binding) {
            DB::table('progress_snapshots')
                ->where('id', $binding->progress_id)
                ->update(['academic_context_id' => $binding->context_id]);
        }

        Schema::table('attempts', function (Blueprint $table): void {
            $table->ulid('academic_context_id')->nullable(false)->change();
        });
        Schema::table('progress_snapshots', function (Blueprint $table): void {
            $table->ulid('academic_context_id')->nullable(false)->change();
        });

        Schema::create('academic_context_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUlid('from_context_id')->nullable()->constrained('user_academic_contexts')->restrictOnDelete();
            $table->foreignUlid('to_context_id')->constrained('user_academic_contexts')->restrictOnDelete();
            $table->string('action', 24);
            $table->unsignedInteger('archived_attempt_count')->default(0);
            $table->unsignedInteger('archived_progress_count')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_context_transitions');

        Schema::table('progress_snapshots', function (Blueprint $table): void {
            // Restore the user-leading index before removing its replacement.
            $table->unique(['user_id', 'curriculum_node_id', 'source_version'], 'progress_source_unique');
        });
        Schema::table('progress_snapshots', function (Blueprint $table): void {
            // MariaDB requires the foreign key to be removed before an index that
            // currently supports that key can be dropped.
            $table->dropForeign(['academic_context_id']);
        });
        Schema::table('progress_snapshots', function (Blueprint $table): void {
            $table->dropUnique('progress_context_source_unique');
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn(['academic_context_id', 'archived_at']);
        });

        Schema::table('attempts', function (Blueprint $table): void {
            // Keep the same FK-before-index rollback ordering for attempts.
            $table->dropForeign(['academic_context_id']);
        });
        Schema::table('attempts', function (Blueprint $table): void {
            $table->dropIndex(['academic_context_id', 'archived_at']);
            $table->dropColumn(['academic_context_id', 'archived_at']);
        });
    }
};
