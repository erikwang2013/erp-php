<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\controller\report\ReportController;
use PHPUnit\Framework\TestCase;

/**
 * 报表执行 SQL 注入修复回归测试
 *
 * 覆盖（纯单测，不连库）：
 *  - buildWhereClause：值一律参数绑定，SQL 片段不含用户值
 *  - buildWhereClause：非法字段名（含函数/表达式）返回 null 被拒
 *  - validateIdentifier：白名单校验拒绝函数/表达式片段
 *  - 源码契约：异常不回显原始 SQL、group_by/join.on/filter.field 均先白名单校验
 */
class ReportSqlInjectionFixTest extends TestCase
{
    private ReportController $controller;

    protected function setUp(): void
    {
        $this->controller = new ReportController();
    }

    private function invokeProtected(object $object, string $method, mixed ...$args): mixed
    {
        $ref = new \ReflectionMethod($object, $method);

        return $ref->invoke($object, ...$args);
    }

    public function testWhereClauseValuesAreParameterized(): void
    {
        $clause = $this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'status',
            'op' => 'eq',
            'value' => '1 OR 1=1',
        ]);
        $this->assertNotNull($clause);
        $this->assertSame('`status` = ?', $clause[0]);
        $this->assertSame(['1 OR 1=1'], $clause[1]);
        // 值不得出现在 SQL 片段中
        $this->assertStringNotContainsString('1=1', $clause[0]);
    }

    public function testWhereClauseBetweenAndInAreParameterized(): void
    {
        $between = $this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'amount',
            'op' => 'between',
            'value' => [100, 200],
        ]);
        $this->assertSame('`amount` BETWEEN ? AND ?', $between[0]);
        $this->assertSame([100, 200], $between[1]);

        $in = $this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'id',
            'op' => 'in',
            'value' => [1, 2, 3],
        ]);
        $this->assertSame('`id` IN (?, ?, ?)', $in[0]);
        $this->assertSame([1, 2, 3], $in[1]);
    }

    public function testWhereClauseRejectsMalformedStructures(): void
    {
        // 非法字段名（函数/表达式）
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'status; DROP TABLE erik_user',
            'op' => 'eq',
            'value' => 1,
        ]));
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'sleep(1)',
            'op' => 'eq',
            'value' => 1,
        ]));
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => '1=1',
            'op' => 'eq',
            'value' => 1,
        ]));
        // 未收录操作符
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'status',
            'op' => 'REGEXP',
            'value' => 'x',
        ]));
        // between/in 值结构非法
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'amount', 'op' => 'between', 'value' => [1],
        ]));
        $this->assertNull($this->invokeProtected($this->controller, 'buildWhereClause', [
            'field' => 'id', 'op' => 'in', 'value' => [],
        ]));
    }

    public function testValidateIdentifierWhitelist(): void
    {
        $this->assertNull($this->invokeProtected($this->controller, 'validateIdentifier', 'user_id', 'ctx'));
        // 别名点号形式（JOIN 模板）合法
        $this->assertNull($this->invokeProtected($this->controller, 'validateIdentifier', 'a.b', 'ctx'));
        $this->assertNull($this->invokeProtected($this->controller, 'validateIdentifier', 'oi.product_id', 'ctx'));
        // 多级点号/函数/表达式/拼接注入一律拒绝
        $this->assertNotNull($this->invokeProtected($this->controller, 'validateIdentifier', 'a.b.c', 'ctx'));
        $this->assertNotNull($this->invokeProtected($this->controller, 'validateIdentifier', 'a. b', 'ctx'));
        $this->assertNotNull($this->invokeProtected($this->controller, 'validateIdentifier', 'user_id) OR 1=1--', 'ctx'));
        $this->assertNotNull($this->invokeProtected($this->controller, 'validateIdentifier', 'COUNT(*)', 'ctx'));
        $this->assertNotNull($this->invokeProtected($this->controller, 'validateIdentifier', 'a.b; DROP TABLE erik_user', 'ctx'));
    }

    public function testQuoteColumnRendersDottedIdentifiersSafely(): void
    {
        $this->assertSame('`oi`.`product_id`', $this->invokeProtected($this->controller, 'quoteColumn', 'oi.product_id'));
        $this->assertSame('`id`', $this->invokeProtected($this->controller, 'quoteColumn', 'id'));
    }

    public function testExceptionMessageDoesNotLeakRawSql(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/controller/report/ReportController.php');
        // 异常回显必须是固定文案，不得拼接 $e->getMessage()
        $this->assertMatchesRegularExpression(
            "/fail\('查询执行失败，请查看服务端日志', 500\)/",
            $source
        );
        $this->assertStringNotContainsString("'查询执行失败: ' . \$e->getMessage()", $source);
        // group_by / join.on / filter.field 拼接前均有白名单校验（含 alias.col 点号形式）
        $this->assertStringContainsString('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $source);
        $this->assertStringContainsString('JOIN ON 条件非法', $source);
        $this->assertStringContainsString('GROUP BY 字段非法', $source);
    }
}
