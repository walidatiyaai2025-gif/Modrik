<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

final class SystemFactoryResetService
{
    /**
     * Installation/runtime state that must survive an application factory reset.
     *
     * The reset intentionally removes all user-created academic/content/learning data,
     * all accounts except the operator, and transient runtime state. It does not drop
     * the schema, release history, SMTP configuration, or system settings required to
     * keep the installation operable.
     *
     * @var list<string>
     */
    private const PRESERVED_TABLES = [
        'migrations',
        'system_update_history',
        'system_settings',
        'system_setting_audits',
        'smtp_providers',
        'smtp_provider_audits',
        'system_factory_reset_audits',
    ];

    /**
     * Rows belonging to the operator are retained so the reset cannot lock the
     * current administrator out of the installation.
     *
     * @var array<string, string>
     */
    private const OPERATOR_SCOPED_TABLES = [
        'users' => 'id',
        'sessions' => 'user_id',
        'auth_sessions' => 'user_id',
        'auth_provider_identities' => 'user_id',
    ];

    /**
     * Preserved tables can hold nullable references to users that will be removed.
     * Null those references before deleting the old accounts while FK checks are off.
     *
     * @var array<string, string>
     */
    private const PRESERVED_USER_REFERENCES = [
        'system_update_history' => 'initiated_by',
        'system_settings' => 'updated_by',
        'system_setting_audits' => 'actor_id',
        'smtp_provider_audits' => 'actor_id',
        'system_factory_reset_audits' => 'actor_id',
    ];

    /** @return array<string, int> */
    public function preview(User $actor): array
    {
        $this->assertAdmin($actor);

        return [
            'other_accounts' => $this->countWhereNotOperator('users', 'id', (string) $actor->getKey()),
            'academic_tracks' => $this->count('academic_tracks'),
            'curriculum_nodes' => $this->count('curriculum_nodes'),
            'lessons' => $this->count('lessons'),
            'questions' => $this->count('questions'),
            'quizzes' => $this->count('quizzes'),
            'attempts' => $this->count('attempts'),
            'preparation_requests' => $this->count('preparation_requests'),
            'preparation_imports' => $this->count('preparation_imports'),
        ];
    }

    /**
     * @return array{tables_cleared:int, rows_deleted:int, table_rows:array<string,int>}
     */
    public function execute(User $actor): array
    {
        $this->assertAdmin($actor);

        $actorId = (string) $actor->getKey();
        $tables = $this->tableNames();
        $tableRows = [];

        foreach ($tables as $table) {
            if ($this->isPreserved($table)) {
                continue;
            }

            $tableRows[$table] = $this->resettableRowCount($table, $actorId);
        }

        $rowsDeleted = array_sum($tableRows);
        $tablesCleared = count(array_filter($tableRows, static fn (int $count): bool => $count > 0));

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($actorId, $tables, $tableRows, $rowsDeleted, $tablesCleared): void {
                $this->sanitizePreservedUserReferences($actorId);

                foreach ($tables as $table) {
                    if ($this->isPreserved($table)) {
                        continue;
                    }

                    $operatorColumn = self::OPERATOR_SCOPED_TABLES[$table] ?? null;
                    if ($operatorColumn !== null) {
                        DB::table($table)
                            ->where(function ($query) use ($operatorColumn, $actorId): void {
                                $query->whereNull($operatorColumn)
                                    ->orWhere($operatorColumn, '!=', $actorId);
                            })
                            ->delete();

                        continue;
                    }

                    DB::table($table)->delete();
                }

                if (Schema::hasTable('system_factory_reset_audits')) {
                    DB::table('system_factory_reset_audits')->insert([
                        'id' => (string) Str::ulid(),
                        'actor_id' => $actorId,
                        'mode' => 'full_application_reset_preserve_installation',
                        'tables_cleared' => $tablesCleared,
                        'rows_deleted' => $rowsDeleted,
                        'summary' => json_encode([
                            'table_rows' => $tableRows,
                            'preserved_tables' => self::PRESERVED_TABLES,
                            'preserved_operator_id' => $actorId,
                        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'occurred_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 3);
        } catch (Throwable $exception) {
            Log::error('MODRIK factory reset failed.', [
                'actor_id' => $actorId,
                'exception' => $exception::class,
            ]);

            throw $exception;
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        Log::warning('MODRIK factory reset completed.', [
            'actor_id' => $actorId,
            'tables_cleared' => $tablesCleared,
            'rows_deleted' => $rowsDeleted,
        ]);

        return [
            'tables_cleared' => $tablesCleared,
            'rows_deleted' => $rowsDeleted,
            'table_rows' => $tableRows,
        ];
    }

    /** @return list<string> */
    private function tableNames(): array
    {
        $tables = DB::connection()->getSchemaBuilder()->getTableListing();

        $normalized = array_map(static function (mixed $table): string {
            $name = (string) $table;
            $separator = strrpos($name, '.');
            if ($separator !== false) {
                $name = substr($name, $separator + 1);
            }

            return trim($name, '`"[]');
        }, $tables);

        return array_values(array_unique(array_filter(
            $normalized,
            static fn (string $table): bool => $table !== '' && ! str_starts_with($table, 'sqlite_'),
        )));
    }

    private function resettableRowCount(string $table, string $actorId): int
    {
        $operatorColumn = self::OPERATOR_SCOPED_TABLES[$table] ?? null;
        if ($operatorColumn === null) {
            return (int) DB::table($table)->count();
        }

        return (int) DB::table($table)
            ->where(function ($query) use ($operatorColumn, $actorId): void {
                $query->whereNull($operatorColumn)
                    ->orWhere($operatorColumn, '!=', $actorId);
            })
            ->count();
    }

    private function sanitizePreservedUserReferences(string $actorId): void
    {
        foreach (self::PRESERVED_USER_REFERENCES as $table => $column) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            DB::table($table)
                ->whereNotNull($column)
                ->where($column, '!=', $actorId)
                ->update([$column => null]);
        }
    }

    private function isPreserved(string $table): bool
    {
        return in_array($table, self::PRESERVED_TABLES, true);
    }

    private function count(string $table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    private function countWhereNotOperator(string $table, string $column, string $actorId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)
            ->where(function ($query) use ($column, $actorId): void {
                $query->whereNull($column)->orWhere($column, '!=', $actorId);
            })
            ->count();
    }

    private function assertAdmin(User $actor): void
    {
        if ((string) $actor->role !== 'admin'
            || (string) $actor->account_status !== 'active'
            || $actor->deleted_at !== null) {
            throw new AuthorizationException('Only an active Admin account may factory-reset MODRIK.');
        }
    }
}
