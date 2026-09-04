<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\print\PrintTemplateService;
use Illuminate\Database\Capsule\Manager as Capsule;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;

/**
 * P1-B1 单据打印模板引擎集成测试。
 *
 * 覆盖口径（与 PrintTemplateService 类注释一致）：
 *  - 模板 CRUD（含软删 + 软删后同 code 拒绝重建）；
 *  - 占位符：扁平 / 点路径 / 内置 date+datetime / 缺失键 → 空串 + missing 清单；
 *  - 字符串值字节级直通（bcmath 规范化后的金额原样透传，无浮点搅动）；
 *  - {{qr:}} → PNG data URI（GD 驱动），文本为空或驱动异常 → 空串 + missing 不抛；
 *  - renderPdf → dompdf 二进制以 %PDF 开头；
 *  - self-cleaning：tearDown 硬删本测试模板。
 */
#[Group('integration')]
class B1PrintTemplateTest extends IntegrationTestCase
{
    /** @var int[] 本测试创建的模板 id，tearDown 硬删清理 */
    private array $tplIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();
        $this->tplIds = [];
    }

    protected function tearDown(): void
    {
        // Query\Builder 无 forceDelete；->delete() 即硬删（绕过模型软删），
        // 软删行一并清掉，否则 uk_code 与后续测试/真实数据冲突
        if ($this->tplIds !== []) {
            Capsule::table('erp_print_template')->whereIn('id', $this->tplIds)->delete();
        }
        parent::tearDown();
    }

    private function printService(): PrintTemplateService
    {
        return new PrintTemplateService();
    }

    private function createTemplate(string $code, string $content = '单号: {{order.code}}', array $extra = []): int
    {
        $tpl = $this->printService()->createTemplate(array_merge([
            'code' => $code,
            'name' => "模板 {$code}",
            'content' => $content,
            'target_type' => 'sales_order',
            'paper_size' => 'A4',
            'orientation' => 'portrait',
            'enabled' => 1,
        ], $extra));
        $this->tplIds[] = (int) $tpl->id;

        return (int) $tpl->id;
    }

    #[TestDox('模板 CRUD：创建/读取/列表/更新/软删，软删后同 code 拒绝重建')]
    public function testCrudLifecycle(): void
    {
        $svc = $this->printService();

        $id = $this->createTemplate('BT_CRUD', '<p>{{a}}</p>', ['paper_size' => 'A5', 'orientation' => 'landscape']);
        $fetched = $svc->getById($id);
        self::assertNotNull($fetched);
        self::assertSame('BT_CRUD', (string) $fetched->code);
        self::assertSame('A5', (string) $fetched->paper_size);
        self::assertSame('landscape', (string) $fetched->orientation);

        $byCode = $svc->getByCode('BT_CRUD');
        self::assertSame($id, (int) $byCode->id);

        $page = $svc->listTemplates('BT_CRUD', '', 1, 15);
        self::assertSame(1, $page['total']);

        $updated = $svc->updateTemplate($id, ['name' => '改名', 'content' => '<p>{{b}}</p>']);
        self::assertSame('改名', (string) $updated->name);

        $svc->deleteTemplate($id);
        self::assertNull($svc->getById($id), '软删后普通查询不可见');

        // 软删后同 code 重建 → 拒绝（uk_code 唯一含软删）
        try {
            $svc->createTemplate([
                'code' => 'BT_CRUD',
                'name' => '重建',
                'content' => '<p>x</p>',
            ]);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('模板编码已存在', $e->getMessage());
        }

        // 不存在模板的更新/删除 → 明确异常
        try {
            $svc->updateTemplate(99999999, ['name' => 'x']);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('模板不存在', $e->getMessage());
        }
    }

    #[TestDox('渲染：扁平与点路径占位符替换，缺失键空串并进 missing 清单')]
    public function testRenderPlaceholders(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_RENDER', '订单 {{order.code}} 客户 {{customer.name}} 金额 {{total}} 缺 {{ghost.x}}');

        $out = $svc->render($id, [
            'order' => ['code' => 'SO-2026-0001'],
            'customer' => ['name' => '某公司'],
            'total' => '133.74',
        ]);

        self::assertSame(
            '订单 SO-2026-0001 客户 某公司 金额 133.74 缺 ',
            $out['html'],
            '点路径/扁平替换 + 缺失键空串'
        );
        self::assertSame(['ghost.x'], $out['missing'], '缺失键进清单');

        // 按 code 渲染等价
        $byCode = $svc->render('BT_RENDER', ['order' => ['code' => 'X']]);
        self::assertStringContainsString('X', $byCode['html']);

        // 模板不存在 → 异常
        try {
            $svc->render('NO_SUCH_CODE', []);
            self::fail('应当抛出异常');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('打印模板不存在', $e->getMessage());
        }
    }

    #[TestDox('渲染：bcmath 规范化金额字符串字节级直通（无浮点搅动）')]
    public function testRenderStringPassthroughExact(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_MONEY', '{{amount}}|{{qty}}');
        // 模拟 bcmath 结果：含尾零与精度的字符串必须原样出现，不能变 133.7/0.1 之类
        $out = $svc->render($id, ['amount' => '133.74', 'qty' => '0.100']);

        self::assertSame('133.74|0.100', $out['html'], '金额/数量字符串原样透传');
        self::assertSame([], $out['missing']);
    }

    #[TestDox('渲染：值含 HTML 原样插入（管理员可信模板语义）；空值按缺失处理')]
    public function testRenderRawHtmlAndEmptyValue(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_RAW', '<div>{{fragment}}</div><td>{{zero}}</td>');

        $out = $svc->render($id, ['fragment' => '<b>加粗</b>', 'zero' => '0']);
        self::assertSame('<div><b>加粗</b></div><td>0</td>', $out['html'], 'HTML 原样 + 字符串 0 保留');
        self::assertSame([], $out['missing']);

        // 值为空串 → 视为缺失
        $out2 = $svc->render($id, ['fragment' => '', 'zero' => '']);
        self::assertStringContainsString('<td></td>', $out2['html']);
        self::assertSame(['fragment', 'zero'], $out2['missing']);
    }

    #[TestDox('渲染：内置 date/datetime 占位符按渲染时刻填充')]
    public function testRenderBuiltinDate(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_DATE', '日期 {{date}} 时间 {{datetime}}');

        $out = $svc->render($id, []);

        self::assertMatchesRegularExpression('/^日期 \d{4}-\d{2}-\d{2} 时间 \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $out['html']);
        self::assertSame([], $out['missing']);

        // date 占位符输出当日 Y-m-d（HTML 前缀为 UTF-8 中文，不能用字节 substr）
        preg_match('/\d{4}-\d{2}-\d{2}/', $out['html'], $m);
        self::assertSame(date('Y-m-d'), $m[0] ?? '', 'date = 渲染当日');
    }

    #[TestDox('渲染：{{qr:}} 产出 PNG data URI；空文本计入 missing 不抛异常')]
    public function testRenderQrPlaceholder(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_QR', 'img: {{qr:https://example.com/trace/1}} end');

        $out = $svc->render($id, []);
        self::assertStringStartsWith(
            'img: data:image/png;base64,',
            $out['html'],
            'QR 渲染为 PNG data URI'
        );
        self::assertStringEndsWith(' end', $out['html']);
        self::assertSame([], $out['missing']);

        // 剥 'img: '(5) + 'data:image/png;base64,'(22) + ' end'(4)
        $payload = substr($out['html'], 27, -4);
        $decoded = base64_decode($payload, true);
        self::assertNotFalse($decoded);
        self::assertSame("\x89PNG", substr((string) $decoded, 0, 4), '真实 PNG 魔数');

        // 空文本 → 空串 + missing
        $id2 = $this->createTemplate('BT_QR_EMPTY', 'x{{qr:}}y');
        $out3 = $svc->render($id2, []);
        self::assertSame('xy', $out3['html'], '空 QR 文本渲染为空串');
        self::assertSame(['qr:'], $out3['missing']);
    }

    #[TestDox('渲染：{{qr:}} 文本为纯数字/中文亦出图（贴标签场景）')]
    public function testRenderQrCjkText(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_QR2', '{{qr:批次2026-A}}');
        $out = $svc->render($id, []);

        self::assertStringStartsWith('data:image/png;base64,', $out['html']);
        self::assertSame([], $out['missing']);
    }

    #[TestDox('renderPdf：产出以 %PDF 开头的二进制（纸张/方向取模板配置）')]
    public function testRenderPdf(): void
    {
        $svc = $this->printService();
        $id = $this->createTemplate('BT_PDF', '<html><body><h1>{{title}}</h1><p>{{order.code}}</p></body></html>');

        $binary = $svc->renderPdf($id, ['title' => '发货单', 'order' => ['code' => 'DL-001']]);
        self::assertStringStartsWith('%PDF', $binary, 'dompdf 输出 PDF 魔数');
        self::assertGreaterThan(500, strlen($binary), 'PDF 非空');
    }

    #[TestDox('parsePlaceholders：提取去重占位符清单')]
    public function testParsePlaceholders(): void
    {
        $svc = $this->printService();
        $tokens = $svc->parsePlaceholders('{{a}} 与 {{a.b}} 与 {{a}} 重复 {{date}} {{qr:文本}}');

        self::assertSame(['a', 'a.b', 'date', 'qr:文本'], $tokens, '去重且保序');
    }

    #[TestDox('自清理：tearDown 清理语句把本测试产生的模板行清零')]
    public function testCleanupLeavesNoResidue(): void
    {
        $id = $this->createTemplate('BT_CLEAN', 'x{{a}}y');
        $ids = [(int) $id];
        self::assertSame(1, Capsule::table('erp_print_template')->whereIn('id', $ids)->count());

        Capsule::table('erp_print_template')->whereIn('id', $ids)->delete();
        self::assertSame(0, Capsule::table('erp_print_template')->whereIn('id', $ids)->count());
    }
}
