<?php

declare(strict_types=1);

namespace Tests;

/**
 * bin/install.php teilt database/schema.sql an Semikolons auf. Dieser Test stellt sicher,
 * dass dabei nur vollständige Anweisungen entstehen (z.B. kein Semikolon in einem Kommentar).
 */
final class SchemaTest extends \PHPUnit\Framework\TestCase
{
    public function testMysqlSchemaSplitsIntoCompleteStatements(): void
    {
        $schema = (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql');
        $schema = preg_replace('/--\s.*$/m', '', $schema) ?? '';
        $statements = array_values(array_filter(array_map('trim', explode(';', $schema))));

        self::assertGreaterThanOrEqual(10, count($statements));
        foreach ($statements as $statement) {
            self::assertMatchesRegularExpression('/^(CREATE TABLE IF NOT EXISTS \w+ \(|SET NAMES)/', $statement);
            self::assertSame(substr_count($statement, '('), substr_count($statement, ')'), "Klammern unausgeglichen:\n$statement");
            if (str_starts_with($statement, 'CREATE')) {
                self::assertStringEndsWith('COLLATE=utf8mb4_unicode_ci', $statement);
            }
        }
    }
}
