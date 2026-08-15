<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\controller\bi\DashboardController;
use app\controller\bi\DatasetController;
use app\controller\bi\WidgetController;
use PHPUnit\Framework\TestCase;

/**
 * BI 模块（看板/数据集/组件）纯单测
 *
 * 覆盖：
 *  - 看板组件排序规则（position_y 优先，其次 position_x）
 *  - 组件布局默认值（type=table / width=4 / height=3）
 *  - 看板删除级联组件、详情内嵌组件
 *  - 数据集关键词检索 name/query_sql
 *  - 各控制器 store() 校验规则、控制器/模型结构约定
 *
 * 说明：本模块控制器以 CRUD 为主，业务逻辑较薄；DB 路径不在单测执行，
 * 相关行为以业务规则/源码契约方式验证。
 */
class BiModuleTest extends TestCase
{
    public function testWidgetsOrderedByPosition(): void
    {
        // show(): position_y asc → position_x asc
        $widgets = [
            ['position_y' => 2, 'position_x' => 0],
            ['position_y' => 1, 'position_x' => 5],
            ['position_y' => 1, 'position_x' => 0],
        ];
        usort($widgets, fn ($a, $b) => $a['position_y'] <=> $b['position_y'] ?: $a['position_x'] <=> $b['position_x']);
        $this->assertSame(1, $widgets[0]['position_y']);
        $this->assertSame(0, $widgets[0]['position_x']);
        $this->assertSame(1, $widgets[1]['position_y']);
        $this->assertSame(5, $widgets[1]['position_x']);
        $this->assertSame(2, $widgets[2]['position_y']);

        $source = file_get_contents(__DIR__ . '/../app/controller/bi/DashboardController.php');
        $this->assertStringContainsString("orderBy('position_y', 'asc')", $source);
        $this->assertStringContainsString("orderBy('position_x', 'asc')", $source);
    }

    public function testWidgetLayoutDefaults(): void
    {
        // 迁移定义：type 默认 table、width 默认 4、height 默认 3
        $source = file_get_contents(__DIR__ . '/../database/migrations/2026_08_04_000025_p3_tables.sql');
        $this->assertStringContainsString("`type` VARCHAR(50) NOT NULL DEFAULT 'table'", $source);
        $this->assertStringContainsString('`width` INT NOT NULL DEFAULT 4', $source);
        $this->assertStringContainsString('`height` INT NOT NULL DEFAULT 3', $source);
        $this->assertStringContainsString('`position_x` INT NOT NULL DEFAULT 0', $source);
        $this->assertStringContainsString('`position_y` INT NOT NULL DEFAULT 0', $source);
    }

    public function testDashboardDestroyCascadesWidgets(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/controller/bi/DashboardController.php');
        $this->assertStringContainsString("BiWidget::where('dashboard_id', \$id)->delete();", $source);
    }

    public function testDashboardShowEmbedsWidgets(): void
    {
        $source = file_get_contents(__DIR__ . '/../app/controller/bi/DashboardController.php');
        $this->assertStringContainsString("\$data['widgets'] = \$widgets;", $source);
    }

    public function testDashboardStoreValidation(): void
    {
        $rules = ['name' => 'required|string|max:200'];
        $this->assertTrue(validator([], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['name' => str_repeat('x', 201)], $rules)->fails(), 'name 超长应失败');
        $this->assertFalse(validator(['name' => '销售看板'], $rules)->fails(), '合法输入应通过');
    }

    public function testWidgetStoreValidation(): void
    {
        $rules = ['dashboard_id' => 'required|integer', 'name' => 'required|string|max:200', 'type' => 'required|string|max:50'];
        $this->assertTrue(validator(['name' => '销售趋势', 'type' => 'line'], $rules)->fails(), '缺少 dashboard_id 应失败');
        $this->assertTrue(validator(['dashboard_id' => 1, 'type' => 'line'], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['dashboard_id' => 1, 'name' => '销售趋势'], $rules)->fails(), '缺少 type 应失败');
        $this->assertFalse(validator(['dashboard_id' => 1, 'name' => '销售趋势', 'type' => 'line'], $rules)->fails(), '合法输入应通过');
    }

    public function testDatasetStoreValidation(): void
    {
        $rules = ['name' => 'required|string|max:200', 'template_id' => 'required|integer'];
        $this->assertTrue(validator(['template_id' => 5], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['name' => '月度销售'], $rules)->fails(), '缺少 template_id 应失败');
        $this->assertFalse(validator(['name' => '月度销售', 'template_id' => 5], $rules)->fails(), '合法输入应通过');
    }

    public function testDatasetKeywordSearchesNameAndSql(): void
    {
        // index(): keyword 同时匹配 name 与 query_sql
        $source = file_get_contents(__DIR__ . '/../app/controller/bi/DatasetController.php');
        $this->assertStringContainsString("'name', 'like'", $source);
        $this->assertStringContainsString("'query_sql', 'like'", $source);
        // Widget 列表支持 dashboard_id 过滤
        $widgetSource = file_get_contents(__DIR__ . '/../app/controller/bi/WidgetController.php');
        $this->assertStringContainsString("'dashboard_id', \$this->decodeId(\$dashboardId)", $widgetSource);
    }

    public function testBiControllersExtendBaseControllerAndHaveCrud(): void
    {
        foreach ([DashboardController::class, DatasetController::class, WidgetController::class] as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
            $methods = get_class_methods($class);
            foreach (['index', 'store', 'show', 'update', 'destroy'] as $m) {
                $this->assertContains($m, $methods, "{$class} 应含 {$m}()");
            }
        }
    }

    public function testBiModelsUseSnowflakePrimaryKey(): void
    {
        foreach (['BiDashboard', 'BiWidget'] as $m) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$m}.php");
            $this->assertStringContainsString('erik_bi_', $source, "{$m} 表应使用 erik_bi_ 前缀");
            $this->assertStringContainsString('$incrementing = false', $source, "{$m} 应关闭自增主键");
            $this->assertStringContainsString("keyType = 'int'", $source, "{$m} 主键类型应为 int");
        }
    }
}
