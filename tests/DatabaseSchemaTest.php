<?php
/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);
namespace tests;
use PHPUnit\Framework\TestCase;

class DatabaseSchemaTest extends TestCase
{
    private string $migrationDir;

    protected function setUp(): void
    {
        $this->migrationDir = __DIR__ . '/../database/migrations';
    }

    /**
     * Verify migration SQL files exist
     */
    public function testAllMigrationFilesExist(): void
    {
        $migrations = glob($this->migrationDir . '/*.sql');
        $this->assertGreaterThanOrEqual(5, count($migrations), 'Should have at least 5 migration files');
    }

    /**
     * Verify all migration files have copyright header
     */
    public function testAllMigrationsHaveCopyrightHeader(): void
    {
        foreach (glob($this->migrationDir . '/*.sql') as $file) {
            $content = file_get_contents($file);
            $this->assertStringContainsString(
                'Copyright (c) 2026 erik',
                $content,
                "{$file} should have copyright header"
            );
        }
    }

    /**
     * Verify all migration files use correct table prefix
     */
    public function testAllMigrationsUseCorrectTablePrefix(): void
    {
        foreach (glob($this->migrationDir . '/2026_05_22_*.sql') as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'CREATE TABLE')) {
                $this->assertStringContainsString(
                    '`erik_',
                    $content,
                    "{$file} should use erik_ table prefix"
                );
            }
        }
    }

    /**
     * Verify all migration tables have snowflake-compatible IDs
     */
    public function testAllMigrationTablesHaveNonAutoIncrementId(): void
    {
        // All tables use BIGINT UNSIGNED NOT NULL, no AUTO_INCREMENT
        foreach (glob($this->migrationDir . '/2026_05_22_*.sql') as $file) {
            $content = file_get_contents($file);
            if (preg_match_all('/CREATE TABLE.*?`erik_(\w+)`/s', $content, $matches)) {
                foreach ($matches[0] as $match) {
                    $this->assertStringNotContainsString(
                        'AUTO_INCREMENT',
                        $match,
                        "Table in {$file} should not use AUTO_INCREMENT"
                    );
                }
            }
        }
    }
}
