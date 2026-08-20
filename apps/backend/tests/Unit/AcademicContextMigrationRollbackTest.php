<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AcademicContextMigrationRollbackTest extends TestCase
{
    public function test_academic_context_foreign_keys_are_dropped_before_supporting_indexes(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_08_20_130000_add_academic_context_archival.php';

        self::assertFileExists($path);
        $contents = file_get_contents($path);
        self::assertIsString($contents);

        $down = strstr($contents, 'public function down(): void');
        self::assertIsString($down);
        self::assertStringNotContainsString("dropConstrainedForeignId('academic_context_id')", $down);

        $foreignStatement = '$table->dropForeign([\'academic_context_id\']);';
        $progressForeign = strpos($down, $foreignStatement);
        $progressUnique = strpos($down, '$table->dropUnique(\'progress_context_source_unique\');');
        $progressColumns = strpos($down, '$table->dropColumn([\'academic_context_id\', \'archived_at\']);');

        self::assertNotFalse($progressForeign);
        self::assertNotFalse($progressUnique);
        self::assertNotFalse($progressColumns);
        self::assertTrue($progressForeign < $progressUnique);
        self::assertTrue($progressUnique < $progressColumns);

        $attemptsForeign = strpos($down, $foreignStatement, $progressForeign + 1);
        $attemptsIndex = strpos($down, '$table->dropIndex([\'academic_context_id\', \'archived_at\']);');
        $attemptsColumns = strpos(
            $down,
            '$table->dropColumn([\'academic_context_id\', \'archived_at\']);',
            $progressColumns + 1,
        );

        self::assertNotFalse($attemptsForeign);
        self::assertNotFalse($attemptsIndex);
        self::assertNotFalse($attemptsColumns);
        self::assertTrue($attemptsForeign < $attemptsIndex);
        self::assertTrue($attemptsIndex < $attemptsColumns);
    }
}
