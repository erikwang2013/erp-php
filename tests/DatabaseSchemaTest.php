<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use PHPUnit\Framework\TestCase;

class DatabaseSchemaTest extends TestCase
{
    private string $installSql;

    protected function setUp(): void
    {
        $this->installSql = __DIR__ . '/../database/install.sql';
    }

    /**
     * Verify install.sql exists
     */
    public function testInstallSqlExists(): void
    {
        $this->assertFileExists($this->installSql);
    }

    /**
     * Verify install.sql has copyright header
     */
    public function testInstallSqlHasCopyrightHeader(): void
    {
        $this->assertStringContainsString(
            'Copyright (c) 2026 erik',
            file_get_contents($this->installSql),
            'install.sql should have copyright header'
        );
    }

    /**
     * Verify install.sql uses correct table prefix
     */
    public function testInstallSqlUsesCorrectTablePrefix(): void
    {
        $content = file_get_contents($this->installSql);
        $this->assertStringContainsString('CREATE TABLE IF NOT EXISTS `erik_', $content);
    }

    /**
     * Verify install.sql tables have snowflake-compatible IDs
     */
    public function testInstallSqlTablesHaveNonAutoIncrementId(): void
    {
        $content = file_get_contents($this->installSql);
        if (preg_match_all('/CREATE TABLE.*?`erik_(\w+)`/s', $content, $matches)) {
            foreach ($matches[0] as $match) {
                $this->assertStringNotContainsString(
                    'AUTO_INCREMENT',
                    $match,
                    'Table should not use AUTO_INCREMENT'
                );
            }
        }
    }
}
