<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class LegacyAcademicTrackAuthorizationRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_authorization_table_is_absent_after_current_migrations(): void
    {
        $this->assertFalse(Schema::hasTable('academic_track_authorizations'));
        $this->assertTrue(Schema::hasTable('academic_tracks'));
        $this->assertTrue(Schema::hasTable('user_academic_contexts'));
        $this->assertTrue(Schema::hasTable('academic_context_transitions'));
    }

    public function test_retirement_migration_has_a_schema_complete_rollback(): void
    {
        $migration = require database_path('migrations/2026_09_09_190700_drop_legacy_academic_track_authorizations_table.php');

        $migration->down();

        $this->assertTrue(Schema::hasTable('academic_track_authorizations'));
        foreach ([
            'id',
            'user_id',
            'academic_track_id',
            'sort_order',
            'authorized_at',
            'revoked_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('academic_track_authorizations', $column),
                "Rollback did not restore academic_track_authorizations.{$column}",
            );
        }

        $migration->up();

        $this->assertFalse(Schema::hasTable('academic_track_authorizations'));
        $this->assertTrue(Schema::hasTable('user_academic_contexts'));
        $this->assertTrue(Schema::hasTable('academic_context_transitions'));
    }

    public function test_runtime_fixture_tests_and_external_contracts_have_no_legacy_authorization_consumer(): void
    {
        $repositoryRoot = realpath(base_path('../..'));
        $this->assertNotFalse($repositoryRoot);

        $roots = [
            app_path(),
            base_path('routes'),
            base_path('config'),
            database_path('seeders'),
            base_path('tests'),
            $repositoryRoot.'/apps/web/src',
            $repositoryRoot.'/apps/mobile/lib',
            $repositoryRoot.'/docs/api',
            $repositoryRoot.'/docs/requirements',
            $repositoryRoot.'/qa',
            $repositoryRoot.'/scripts',
        ];
        $extensions = ['php', 'ts', 'tsx', 'dart', 'yaml', 'yml', 'mjs', 'cjs', 'sh'];
        $needles = ['academic_track_authorizations', 'TRACK_AUTHORIZATION_ID'];
        $self = realpath(__FILE__);

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
            foreach ($files as $file) {
                if (! $file instanceof SplFileInfo || ! $file->isFile()) {
                    continue;
                }
                if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }

                $path = $file->getRealPath();
                if ($path === false || $path === $self) {
                    continue;
                }

                $contents = file_get_contents($path);
                $this->assertNotFalse($contents, "Unable to read {$path} while checking legacy academic authorization consumers.");

                foreach ($needles as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $contents,
                        "Legacy academic authorization dependency remains in {$path}",
                    );
                }
            }
        }

        $erd = file_get_contents($repositoryRoot.'/docs/data/erd.md');
        $this->assertNotFalse($erd);
        $this->assertStringNotContainsString(
            'academic_track_authorizations',
            $erd,
            'Logical ERD still models the retired per-user authorization table.',
        );
    }
}
