<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests;

use app\model\QualityIqcRecord;
use app\service\quality\QmsInspectionService;
use PHPUnit\Framework\TestCase;
use support\Response;

/**
 * 质量模块纯单测（Quality: InspectionStandard/IQC/IPQC/OQC/Nonconformity）
 * - QmsInspectionService::calculatePassRate 与 recordInspection 的未知类型分支为纯逻辑
 * - 控制器校验分支走真实代码路径
 * - reject 自动生成不合格品单为落库流程，跳过并注明
 */
class QualityModuleTest extends TestCase
{
    private function responseCode(Response $resp): int
    {
        $body = json_decode($resp->rawBody(), true);

        return (int) ($body['code'] ?? -1);
    }

    /* ============================ QmsInspectionService 合格率（纯逻辑） ============================ */

    public function testPassRateEmptyRecordsReturnsZero(): void
    {
        $this->assertSame(0.0, (new QmsInspectionService())->calculatePassRate([]));
    }

    public function testPassRateAllPassedReturnsOneHundred(): void
    {
        $svc = new QmsInspectionService();
        $this->assertSame(100.0, $svc->calculatePassRate([['inspected_qty' => 10, 'passed_qty' => 10]]));
        $this->assertSame(100.0, $svc->calculatePassRate([
            ['inspected_qty' => 5, 'passed_qty' => 5],
            ['inspected_qty' => 15, 'passed_qty' => 15],
        ]));
    }

    public function testPassRatePartialResult(): void
    {
        $svc = new QmsInspectionService();
        $this->assertSame(80.0, $svc->calculatePassRate([
            ['inspected_qty' => 10, 'passed_qty' => 8],
            ['inspected_qty' => 10, 'passed_qty' => 8],
        ]));
    }

    public function testPassRateRoundsToTwoDecimals(): void
    {
        // 1/3 ≈ 33.333... => 33.33（保留两位小数）
        $this->assertSame(33.33, (new QmsInspectionService())->calculatePassRate([
            ['inspected_qty' => 3, 'passed_qty' => 1],
        ]));
    }

    public function testPassRateTreatsMissingFieldsAsZero(): void
    {
        $svc = new QmsInspectionService();
        // 第一条缺 passed_qty、第二条缺 inspected_qty => 按 0 处理 => 5/10 = 50%
        $this->assertSame(50.0, $svc->calculatePassRate([
            ['inspected_qty' => 10],
            ['passed_qty' => 5],
        ]));
        // 没有任何 inspected_qty => 分母为 0 => 0
        $this->assertSame(0.0, $svc->calculatePassRate([['passed_qty' => 5]]));
    }

    public function testRecordInspectionUnknownTypeThrows(): void
    {
        // recordInspection 对未知类型在触库前抛 InvalidArgumentException
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown inspection type: xyz');
        (new QmsInspectionService())->recordInspection('xyz', []);
    }

    /* ============================ 控制器校验分支（真实代码路径） ============================ */

    public function testIqcStoreRejectsMissingCode(): void
    {
        $resp = (new \app\controller\quality\IncomingCheckController())->store(new FakeRequest([
            'inspected_qty' => 10,
            'result' => 'pass',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'IQC 缺少 code 应校验失败');
    }

    public function testIqcStoreRejectsInvalidResult(): void
    {
        $resp = (new \app\controller\quality\IncomingCheckController())->store(new FakeRequest([
            'code' => 'IQC-001',
            'inspected_qty' => 10,
            'result' => 'maybe',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'IQC result 只能为 pass/reject');
    }

    public function testIqcStoreRejectsNegativeInspectedQty(): void
    {
        $resp = (new \app\controller\quality\IncomingCheckController())->store(new FakeRequest([
            'code' => 'IQC-001',
            'inspected_qty' => -1,
            'result' => 'pass',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'IQC 检验数量不能为负');
    }

    public function testRecordEndpointRejectsInvalidRecordType(): void
    {
        $resp = (new \app\controller\quality\IncomingCheckController())->record(new FakeRequest([
            'record_type' => 'sale',
            'inspected_qty' => 1,
            'result' => 'pass',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'record 接口 record_type 只能为 iqc/ipqc/oqc');
    }

    public function testPassRateEndpointReturnsCalculatedRate(): void
    {
        // passRate 接口不触库，走真实控制器代码路径
        $resp = (new \app\controller\quality\IncomingCheckController())->passRate(new FakeRequest([
            'records' => [['inspected_qty' => 10, 'passed_qty' => 8]],
        ]));
        $body = json_decode($resp->rawBody(), true);
        $this->assertSame(0, $this->responseCode($resp));
        $this->assertEquals(80.0, $body['data']['pass_rate']);
    }

    public function testPassRateEndpointRejectsNonArrayRecords(): void
    {
        $resp = (new \app\controller\quality\IncomingCheckController())->passRate(new FakeRequest([
            'records' => 'not-an-array',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'records 非数组应返回 422');
    }

    public function testProcessCheckStoreRejectsMissingCode(): void
    {
        $resp = (new \app\controller\quality\ProcessCheckController())->store(new FakeRequest([
            'inspected_qty' => 10,
            'result' => 'pass',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'IPQC 缺少 code 应校验失败');
    }

    public function testFinalCheckStoreRejectsMissingCode(): void
    {
        $resp = (new \app\controller\quality\FinalCheckController())->store(new FakeRequest([
            'inspected_qty' => 10,
            'result' => 'pass',
        ]));
        $this->assertSame(422, $this->responseCode($resp), 'OQC 缺少 code 应校验失败');
    }

    public function testNonconformityStoreRejectsMissingDefectType(): void
    {
        $resp = (new \app\controller\quality\NonconformityController())->store(new FakeRequest([
            'code' => 'NC-001',
            'defect_qty' => 2,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '不合格品单缺少 defect_type 应校验失败');
    }

    public function testNonconformityStoreRejectsNegativeDefectQty(): void
    {
        $resp = (new \app\controller\quality\NonconformityController())->store(new FakeRequest([
            'code' => 'NC-001',
            'defect_type' => '划痕',
            'defect_qty' => -1,
        ]));
        $this->assertSame(422, $this->responseCode($resp), '不合格品数量不能为负');
    }

    public function testInspectionStandardStoreRejectsMissingName(): void
    {
        $resp = (new \app\controller\quality\InspectionStandardController())->store(new FakeRequest([
            'code' => 'STD-001',
        ]));
        $this->assertSame(422, $this->responseCode($resp), '检验标准缺少 name 应校验失败');
    }

    /* ============================ 结构与模型契约 ============================ */

    public function testQualityControllersExtendBaseController(): void
    {
        $controllers = [
            'app\\controller\\quality\\InspectionStandardController',
            'app\\controller\\quality\\IncomingCheckController',
            'app\\controller\\quality\\ProcessCheckController',
            'app\\controller\\quality\\FinalCheckController',
            'app\\controller\\quality\\NonconformityController',
        ];
        foreach ($controllers as $class) {
            $this->assertTrue(class_exists($class), "{$class} 应存在");
            $this->assertTrue(is_subclass_of($class, 'app\\admin\\controller\\BaseController'), "{$class} 应继承 BaseController");
        }
        $methods = get_class_methods('app\\controller\\quality\\IncomingCheckController');
        foreach (['index', 'store', 'show', 'update', 'destroy', 'record', 'passRate'] as $m) {
            $this->assertContains($m, $methods, 'IncomingCheckController 应具备 CRUD + record + passRate');
        }
    }

    public function testQualityModelsUseSnowflakePrimaryKey(): void
    {
        $models = [
            'QualityInspectionStandard',
            'QualityIqcRecord',
            'QualityIpcqRecord',
            'QualityOqcRecord',
            'QualityNonconformity',
        ];
        foreach ($models as $name) {
            $source = file_get_contents(__DIR__ . "/../app/model/{$name}.php");
            $this->assertStringContainsString('erik_quality', $source, "{$name} 表必须使用 erik_quality 前缀");
            $this->assertStringContainsString('public $incrementing = false', $source, "{$name} 必须使用非自增主键");
            $this->assertStringContainsString("protected \$keyType = 'int'", $source, "{$name} 主键类型必须为 int");
        }
    }

    public function testQualityIqcModelFillableFields(): void
    {
        $fillable = (new QualityIqcRecord())->getFillable();
        foreach (['code', 'inspected_qty', 'passed_qty', 'rejected_qty', 'result', 'inspector'] as $field) {
            $this->assertContains($field, $fillable, "QualityIqcRecord 应允许填充 {$field}");
        }
    }

    /* ============================ reject 自动生成不合格品单（源码契约 + 落库跳过） ============================ */

    public function testRecordInspectionRejectAutoCreatesNonconformityContract(): void
    {
        // QmsInspectionService::recordInspection 中 reject 且 rejected_qty>0 时自动创建不合格品单
        $src = file_get_contents(__DIR__ . '/../app/service/quality/QmsInspectionService.php');
        $this->assertStringContainsString("(\$data['result'] ?? '') === 'reject'", $src);
        $this->assertStringContainsString("(\$data['rejected_qty'] ?? 0) > 0", $src);
        $this->assertStringContainsString('new QualityNonconformity()', $src);
        $this->assertStringContainsString('$nc->status = 0', $src, '不合格品单初始状态应为待处理(0)');
        $this->assertStringContainsString("\$nc->defect_qty = \$data['rejected_qty']", $src, '不合格数量取自 rejected_qty');
        $this->assertStringContainsString('$nc->source_type = $recordType', $src, '不合格品单应记录来源检验类型');
    }

    public function testRecordInspectionWriteFlowRequiresDatabase(): void
    {
        // recordInspection 的检验记录落库与 reject 自动生成不合格品单依赖 MySQL，纯单测环境跳过。
        $this->markTestSkipped('依赖 MySQL: QualityIqc/Ipcq/Oqc 记录落库 + QualityNonconformity 自动生成');
    }
}
