<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace tests;

use app\common\HashidsService;
use app\controller\bi\DashboardController;
use app\controller\bi\DatasetController;
use app\controller\bi\WidgetController;
use app\model\BiDashboard;
use app\model\BiWidget;
use app\model\ReportDataset;
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
 * 例外：testBiWritePathsDecodeHashidForeignKeys 需 .env 真库（外键双模解码要真落库才看得见
 * 1366），DB 不可用时该方法自跳过，保持无库环境绿。
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
        $source = file_get_contents(__DIR__ . '/../database/install.sql');
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
        // dashboard_id 是前端下拉下发的 hashid 串：required|integer 会把合法 hashid 判成
        // 422（图表管理弹窗恒失败），string 又会把数字 ID 挡回 —— 只留 required，
        // 双模判定交 decodeFlexibleId（控制器内 422 收口）
        $rules = ['dashboard_id' => 'required', 'name' => 'required|string|max:200', 'type' => 'required|string|max:50'];
        $this->assertTrue(validator(['name' => '销售趋势', 'type' => 'line'], $rules)->fails(), '缺少 dashboard_id 应失败');
        $this->assertTrue(validator(['dashboard_id' => 1, 'type' => 'line'], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['dashboard_id' => 1, 'name' => '销售趋势'], $rules)->fails(), '缺少 type 应失败');
        $this->assertFalse(validator(['dashboard_id' => 1, 'name' => '销售趋势', 'type' => 'line'], $rules)->fails(), '数字 ID 应通过');
        $this->assertFalse(validator(['dashboard_id' => HashidsService::encode(1), 'name' => '销售趋势', 'type' => 'line'], $rules)->fails(), 'hashid 串也应通过');
        // 上面校验的是规则副本，控制器改回 required|integer 它照样绿——补源码断言兜底
        $source = file_get_contents(__DIR__ . '/../app/controller/bi/WidgetController.php');
        $this->assertStringContainsString("'dashboard_id' => 'required',", $source);
        $this->assertStringNotContainsString("'dashboard_id' => 'required|integer'", $source);
    }

    public function testDatasetStoreValidation(): void
    {
        // template_id 同为模板下拉的 hashid 串：旧码 required|integer → 新增数据集恒 422；
        // string 又会把数字 ID 挡回 —— 只留 required，双模判定交 decodeFlexibleId
        $rules = ['name' => 'required|string|max:200', 'template_id' => 'required'];
        $this->assertTrue(validator(['template_id' => 5], $rules)->fails(), '缺少 name 应失败');
        $this->assertTrue(validator(['name' => '月度销售'], $rules)->fails(), '缺少 template_id 应失败');
        $this->assertFalse(validator(['name' => '月度销售', 'template_id' => 5], $rules)->fails(), '数字 ID 应通过');
        $this->assertFalse(validator(['name' => '月度销售', 'template_id' => HashidsService::encode(5)], $rules)->fails(), 'hashid 串也应通过');
        // 同上：源码断言兜住「控制器改回 required|integer」的回归（无库环境也能红）
        $source = file_get_contents(__DIR__ . '/../app/controller/bi/DatasetController.php');
        $this->assertStringContainsString("'template_id' => 'required',", $source);
        $this->assertStringNotContainsString("'template_id' => 'required|integer'", $source);
    }

    /**
     * 外键写入路径：看板 user_id / 数据集 template_id / 图表 dashboard_id+dataset_id
     * 收 hashid 串与原生数字（双模解码），不得直灌 BIGINT（1366 → 500）。
     * 口径分档：可选外键（user_id/dataset_id）垃圾串落 0（BaseController::decodeIdFields，
     * 同 purchase）；必填外键（template_id/dashboard_id）解不出 422，不写出无主行。
     *
     * 负控（改回缺陷即红）：把 store() 改回「fill 直灌 hashid」形态，本方法第一条断言
     * 就会以 500（1366 Incorrect integer value）失败；把必填外键放宽成落 0 则 3c 红。
     */
    public function testBiWritePathsDecodeHashidForeignKeys(): void
    {
        try {
            BiDashboard::query()->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('数据库不可用（需 .env 真实 erp 库），跳过: ' . $e->getMessage());
        }

        $suffix = (string) mt_rand(100000, 999999);
        // 外键靶值：三表该列均无 FK 约束，取足够大的正整数避免撞既有行
        $userId = 90000000000000001;
        $templateId = 90000000000000002;
        $dashboardId = 90000000000000003;
        $datasetId = 90000000000000004;
        $createdDashboards = [];
        $createdDatasets = [];
        $createdWidgets = [];

        try {
            // 1. 看板 store：user_id 收 hashid（旧码 fill 直灌 BIGINT → 1366 → 500）
            $resp = (new DashboardController())->store(new FakeRequest([
                'name' => '回归看板' . $suffix,
                'user_id' => HashidsService::encode($userId),
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '看板 store 应成功');
            $dashboardId = HashidsService::decode((string) $body['data']['id']);
            $createdDashboards[] = $dashboardId;
            $this->assertSame($userId, (int) BiDashboard::find($dashboardId)->user_id, 'user_id 应按 hashid 解码落库');

            // 1b. 看板 update：user_id 缺省＝不改动；垃圾串按 BaseController::decodeIdFields
            //     口径落 0（可选外键，同 purchase），不得直灌 BIGINT 崩 500
            $keep = (new DashboardController())->update(new FakeRequest(['name' => '改名' . $suffix]), (string) $body['data']['id']);
            $this->assertSame(0, (int) (json_decode($keep->rawBody(), true)['code'] ?? -1));
            $this->assertSame($userId, (int) BiDashboard::find($dashboardId)->user_id, '缺省字段不得改动既有外键');
            $bad = (new DashboardController())->update(new FakeRequest(['user_id' => 'not-a-hashid']), (string) $body['data']['id']);
            $this->assertSame(0, (int) (json_decode($bad->rawBody(), true)['code'] ?? -1), '垃圾串不得 500');
            $this->assertSame(0, (int) BiDashboard::find($dashboardId)->user_id, '可选外键垃圾串落 0（decodeIdFields 口径）');

            // 2. 数据集 store：template_id 收 hashid（旧码 required|integer → 422）
            $resp = (new DatasetController())->store(new FakeRequest([
                'name' => '回归数据集' . $suffix,
                'template_id' => HashidsService::encode($templateId),
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '数据集 store 应成功');
            $datasetId = HashidsService::decode((string) $body['data']['id']);
            $createdDatasets[] = $datasetId;
            $this->assertSame($templateId, (int) ReportDataset::find($datasetId)->template_id, 'template_id 应按 hashid 解码落库');

            // 2b. 数据集 store：垃圾串 422（不得退化成 (int)'abc'=0 静默错挂）
            $bad = (new DatasetController())->store(new FakeRequest([
                'name' => '回归数据集bad' . $suffix,
                'template_id' => 'not-a-hashid',
            ]));
            $this->assertSame(422, (int) (json_decode($bad->rawBody(), true)['code'] ?? -1), '垃圾 template_id 必须 422');

            // 3. 图表 store：dashboard_id 必填 + dataset_id 可空，两者都收 hashid
            //    （旧码 dashboard_id required|integer → 422，图表管理弹窗恒失败）
            $resp = (new WidgetController())->store(new FakeRequest([
                'dashboard_id' => HashidsService::encode($dashboardId),
                'name' => '回归图表' . $suffix,
                'type' => 'line',
                'dataset_id' => HashidsService::encode($datasetId),
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? '图表 store 应成功');
            $widgetId = HashidsService::decode((string) $body['data']['id']);
            $createdWidgets[] = $widgetId;
            $widget = BiWidget::find($widgetId);
            $this->assertSame($dashboardId, (int) $widget->dashboard_id, 'dashboard_id 应按 hashid 解码落库');
            $this->assertSame($datasetId, (int) $widget->dataset_id, 'dataset_id 应按 hashid 解码落库');

            // 3b. 图表 store：dataset_id 留空＝不绑定（0），垃圾串 422
            $resp = (new WidgetController())->store(new FakeRequest([
                'dashboard_id' => HashidsService::encode($dashboardId),
                'name' => '回归图表b' . $suffix,
                'type' => 'table',
                'dataset_id' => '',
            ]));
            $body = json_decode($resp->rawBody(), true);
            $this->assertSame(0, (int) ($body['code'] ?? -1), $body['message'] ?? 'dataset_id 留空应成功');
            $widgetBlankId = HashidsService::decode((string) $body['data']['id']);
            $createdWidgets[] = $widgetBlankId;
            $this->assertSame(0, (int) BiWidget::find($widgetBlankId)->dataset_id, 'dataset_id 留空应落 0');
            // 3c. 图表 store：垃圾 dashboard_id（必填外键）必须 422
            $bad = (new WidgetController())->store(new FakeRequest([
                'dashboard_id' => 'not-a-hashid',
                'name' => '回归图表bad' . $suffix,
                'type' => 'table',
            ]));
            $this->assertSame(422, (int) (json_decode($bad->rawBody(), true)['code'] ?? -1), '垃圾 dashboard_id 必须 422');
        } finally {
            if ($createdWidgets) {
                BiWidget::whereIn('id', $createdWidgets)->delete();
            }
            foreach ($createdDatasets as $id) {
                ReportDataset::where('id', $id)->delete();
            }
            foreach ($createdDashboards as $id) {
                BiWidget::where('dashboard_id', $id)->delete();
                BiDashboard::where('id', $id)->delete();
            }
        }
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
        $models = ['BiDashboard' => 'bi_dashboard', 'BiWidget' => 'bi_widget'];
        foreach ($models as $m => $table) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$m}.php");
            $this->assertStringContainsString("protected \$table = '{$table}'", $source, "{$m} 表名应为 {$table}（erp_ 前缀由连接层 config/database.php 施加）");
            $this->assertStringContainsString('$incrementing = false', $source, "{$m} 应关闭自增主键");
            $this->assertStringContainsString("keyType = 'int'", $source, "{$m} 主键类型应为 int");
        }
    }
}
