<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\model\CustomFieldDefinition;
use app\service\notification\ChannelService;
use app\service\platform\CustomFieldService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;

/**
 * P2-5 B4+B7 对抗性集成测试（--group=integration）
 *
 * 环境变量契约同 IntegrationTestCase：TEST_DB_HOST/PORT/DATABASE/USERNAME/PASSWORD。
 * 缺 erp_notification_channel_log / erp_custom_field_definition 表时按需自建
 * （蓝图与 database/b4b7_channel.sql 同构）；erp_sales_order 缺 custom_fields
 * 列时相关用例优雅跳过。与 B4ChannelTest/B7CustomFieldTest 覆盖互补，全部行
 * 以 content 'B47T-' / field_key 'b47x%' / code 'B47T%' 前缀自清理，绝不碰他人数据。
 *
 * 对抗点：
 * 1. 幂等窗口：同 (channel,to,sha256(content)) 300s 成功窗口二次 send 不重发不重落
 *    日志（行数=1 即驱动调用恰 1 次的可观测代理）、message_id/log_id 与既有一致、
 *    与 subject 无关；sent_at 拨回 301s → 正常重发；窗口内多行成功命中最新一行；
 *    同 content 异 to 不幂等；失败记录永不进窗口。
 * 2. Mock 确定性：接收方校验先于长度（9 开头/无 @ 且内容超长仍报接收方错误）；
 *    mb_strlen 500 恰过 501 拒；message_id 形如 MOCK+14 位时间+4 位序号，同秒
 *    连续成功序号严格 +1。
 * 3. 失败留痕：驱动失败 → log status=2 error 非空、返回体带错误不抛异常；
 *    retryFailures 只挑 status=2 且 sent_at < now-60s（冷却内不挑、成功行永不挑），
 *    成功转 1 记 message_id，仍失败保持 2 刷 error 清 message_id，每次尝试刷
 *    sent_at（防击穿：紧跟二次调用 attempted=0）；计数与 DB 一致；channel 过滤 +
 *    id 升序 + limit；未知 channel 直接拒绝。
 * 4. B7 类型校验：number 拒 '1e3'/'-2'/'.5'/'2.555'，date 拒 '2026-13-01'/
 *    '2026-02-30'/'2026-9-5'，select 白名单外拒，text/textarea 501 拒 500 过；
 *    is_required 缺失/空串/纯空白/null → 「字段 {label} 必填」；未知 key 宽容忽略；
 *    int 0 是非空值满足必填。
 * 5. 定义 CRUD：key 大写/连字符/51 位拒、entity/type 白名单外拒、select 空/缺
 *    value/重复 value 拒、非 select 带 options 落库 null、同 (entity,key) 重复拒
 *    （uk_entity_field）、update/delete 不存在拒、update 夹带改标识被原值覆盖、
 *    物理删除后可重建同键。
 * 6. JSON 列：中文/嵌套 select 值写入读回等价（MySQL 二进制按键排序 → ksort 后
 *    assertSame）；停用定义不影响已存旧值读取；非法 JSON 直插 → 明确拒绝。
 */
#[Group('integration')]
class B47AdversarialIntegrationTest extends IntegrationTestCase
{
    private const LOG_TABLE = 'erp_notification_channel_log';

    private const DEF_TABLE = 'erp_custom_field_definition';

    private const ORDER_TABLE = 'erp_sales_order';

    private ChannelService $channel;

    private CustomFieldService $fields;

    /** 直插日志行 id 基座（自增、远小于雪花 id，升序可控） */
    private int $logIdBase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireTestDatabase();

        self::createTableIfMissing(self::LOG_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('channel', 20);
            $table->string('to', 200);
            $table->string('subject', 200)->default('');
            $table->text('content');
            $table->char('content_hash', 64)->default('');
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('message_id', 80)->default('');
            $table->string('error', 500)->default('');
            $table->dateTime('sent_at')->useCurrent();
            $table->unsignedBigInteger('operator_id')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->index(['channel', 'status']);
            $table->index('to');
            $table->index('content_hash');
        });
        Capsule::table(self::LOG_TABLE)->where('content', 'like', 'B47T%')->delete();

        self::createTableIfMissing(self::DEF_TABLE, static function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('entity_type', 30);
            $table->string('field_key', 50);
            $table->string('label', 100);
            $table->string('field_type', 20);
            $table->json('options')->nullable();
            $table->unsignedTinyInteger('is_required')->default(0);
            $table->unsignedInteger('sort')->default(0);
            $table->unsignedTinyInteger('status')->default(1);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            // 唯一键必须命名 uk_entity_field（服务按该名捕重复）
            $table->unique(['entity_type', 'field_key'], 'uk_entity_field');
        });
        Capsule::table(self::DEF_TABLE)->where('field_key', 'like', 'b47x%')->delete();

        $this->channel = new ChannelService();
        $this->fields = new CustomFieldService();
        $this->logIdBase = random_int(100_000_000_000, 900_000_000_000);
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            $schema = Capsule::schema();
            if ($schema->hasTable(self::LOG_TABLE)) {
                Capsule::table(self::LOG_TABLE)->where('content', 'like', 'B47T%')->delete();
            }
            if ($schema->hasTable(self::DEF_TABLE)) {
                Capsule::table(self::DEF_TABLE)->where('field_key', 'like', 'b47x%')->delete();
            }
            if ($schema->hasTable(self::ORDER_TABLE)) {
                Capsule::table(self::ORDER_TABLE)->where('code', 'like', 'B47T%')->delete();
            }
        }
        parent::tearDown();
    }

    private static function secondsAgo(int $seconds): string
    {
        return date('Y-m-d H:i:s', time() - $seconds);
    }

    /** 直插发送日志行（构造历史状态；content 缺省走默认并据此补 content_hash） */
    private function insertLogRow(array $overrides = []): void
    {
        $this->logIdBase++;
        if (!array_key_exists('content_hash', $overrides)) {
            $overrides['content_hash'] = hash('sha256', (string) ($overrides['content'] ?? 'B47T-直插行'));
        }
        Capsule::table(self::LOG_TABLE)->insert(array_merge([
            'id' => $this->logIdBase, 'channel' => 'sms', 'to' => '13800138000', 'subject' => '',
            'content' => 'B47T-直插行', 'content_hash' => hash('sha256', 'B47T-直插行'),
            'status' => 2, 'message_id' => '', 'error' => '',
            'sent_at' => date('Y-m-d H:i:s'), 'operator_id' => 0,
        ], $overrides));
    }

    /** 创建定义并断言成功（返回启用态模型） */
    private function createDef(array $data): CustomFieldDefinition
    {
        [$def, $error] = $this->fields->create($data);
        $this->assertNull($error, 'create 应成功: ' . (string) $error);
        $this->assertNotNull($def);

        return $def;
    }

    /** 全量五键更新定义（entity/key 由服务强制原值，调用方无需也不得传入） */
    private function updateDef(CustomFieldDefinition $def, array $overrides = []): CustomFieldDefinition
    {
        [$model, $error] = $this->fields->update((int) $def->id, array_merge([
            'label' => (string) $def->label,
            'field_type' => (string) $def->field_type,
            'is_required' => (int) $def->is_required,
            'sort' => (int) $def->sort,
            'status' => (int) $def->status,
        ], $overrides));
        $this->assertNull($error, 'update 应成功: ' . (string) $error);

        return $model;
    }

    private function requireOrderTableReady(): void
    {
        $schema = Capsule::schema();
        if (!$schema->hasTable(self::ORDER_TABLE) || !$schema->hasColumn(self::ORDER_TABLE, 'custom_fields')) {
            self::markTestSkipped(self::ORDER_TABLE . ' 缺 custom_fields 列：请先导入 database/b4b7_channel.sql');
        }
    }

    private function logRowCount(string $content, string $channel, string $to): int
    {
        return (int) Capsule::table(self::LOG_TABLE)
            ->where('content', $content)->where('channel', $channel)->where('to', $to)
            ->count();
    }

    public function testIdempotencyWindowReturnsExistingRecordWithoutResend(): void
    {
        $content = 'B47T-幂等窗口-催款通知';
        $first = $this->channel->send('sms', '13800138000', '主题甲', $content);
        $this->assertTrue($first['success']);
        $this->assertFalse($first['dedup']);

        // 二次 send：subject 不在判重键内 → dedup 命中既有记录，不重发不重落日志
        $second = $this->channel->send('sms', '13800138000', '主题乙', $content);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['dedup']);
        $this->assertSame($first['message_id'], $second['message_id']);
        $this->assertSame($first['log_id'], $second['log_id']);
        $this->assertSame(1, $this->logRowCount($content, 'sms', '13800138000'));
        $this->assertSame(1, (int) Capsule::table(self::LOG_TABLE)->where('content', $content)->count());
    }

    public function testIdempotencyExpiryResendNewestPickAndFailureNeverInWindow(): void
    {
        $content = 'B47T-窗口过期-账单提醒';
        $first = $this->channel->send('sms', '13900139000', '', $content);
        $this->assertTrue($first['success']);
        Capsule::table(self::LOG_TABLE)->where('id', $first['log_id'])->update(['sent_at' => self::secondsAgo(301)]);

        // 超窗（301s > 300s）→ 正常重发：新行、dedup=false
        $resend = $this->channel->send('sms', '13900139000', '', $content);
        $this->assertFalse($resend['dedup']);
        $this->assertNotSame($first['log_id'], $resend['log_id']);
        $this->assertSame(2, $this->logRowCount($content, 'sms', '13900139000'));

        // 窗口内两行成功 → 命中最新一行（desc），返回其 message_id/log_id
        $third = $this->channel->send('sms', '13900139000', '', $content);
        $this->assertTrue($third['dedup']);
        $this->assertSame($resend['log_id'], $third['log_id']);
        $this->assertSame($resend['message_id'], $third['message_id']);

        // 同 content 异 to → 不幂等
        $other = $this->channel->send('sms', '13700137000', '', $content);
        $this->assertFalse($other['dedup']);
        $this->assertSame(1, $this->logRowCount($content, 'sms', '13700137000'));

        // 失败记录不进窗口：同键反复失败每次都是新行（status=2 ×3、log_id 各异）
        $failContent = 'B47T-失败不进窗口';
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $r = $this->channel->send('sms', '91234567890', '', $failContent);
            $this->assertFalse($r['success']);
            $this->assertFalse($r['dedup']);
            $ids[] = $r['log_id'];
        }
        $this->assertSame(3, count(array_unique($ids)));
        $this->assertSame(3, (int) Capsule::table(self::LOG_TABLE)
            ->where('content', $failContent)->where('status', 2)->count());
    }

    public function testDriverValidationOrderLengthBoundaryAndSendGuards(): void
    {
        // 校验顺序：接收方先于长度——'9' 开头 + 超长内容仍报号码非法（双非法确定性）
        $r1 = $this->channel->send('sms', '91234567890', '', 'B47T-' . str_repeat('好', 600));
        $this->assertFalse($r1['success']);
        $this->assertSame('接收方号码非法', $r1['error']);
        $this->assertSame('', $r1['message_id']);
        $this->assertNotNull($r1['log_id']);
        $this->assertSame(2, (int) Capsule::table(self::LOG_TABLE)->where('id', $r1['log_id'])->value('status'));
        $this->assertSame('接收方号码非法', (string) Capsule::table(self::LOG_TABLE)->where('id', $r1['log_id'])->value('error'));

        // mb_strlen 边界：501（多字节汉字）拒；500 恰过成功
        $long = $this->channel->send('sms', '13800138000', '', 'B47T-' . str_repeat('好', 496));
        $this->assertFalse($long['success']);
        $this->assertSame('内容超长', $long['error']);
        $ok500 = $this->channel->send('sms', '13800138000', '', 'B47T-' . str_repeat('a', 495));
        $this->assertTrue($ok500['success']);
        $this->assertSame('', $ok500['error']);

        // mail：无 @ 拒（先于长度）；合法邮箱 + 501 → 内容超长
        $mail1 = $this->channel->send('mail', 'no-at-address', '', 'B47T-' . str_repeat('好', 600));
        $this->assertFalse($mail1['success']);
        $this->assertSame('接收方邮箱非法', $mail1['error']);
        $mail2 = $this->channel->send('mail', 'user@example.com', '', 'B47T-' . str_repeat('好', 496));
        $this->assertFalse($mail2['success']);
        $this->assertSame('内容超长', $mail2['error']);

        // 入参边界：channel 白名单外 / to 空 / content 空 → 拒绝且不落日志
        $g1 = $this->channel->send('wechat', '13800138000', '', 'x');
        $this->assertFalse($g1['success']);
        $this->assertSame('不支持的渠道', $g1['error']);
        $g2 = $this->channel->send('sms', '', '', 'x');
        $this->assertFalse($g2['success']);
        $this->assertSame('接收方不能为空', $g2['error']);
        $g3 = $this->channel->send('sms', '13800138000', '', '');
        $this->assertFalse($g3['success']);
        $this->assertSame('内容不能为空', $g3['error']);
        $this->assertNull($g1['log_id']);
        $this->assertNull($g2['log_id']);
        $this->assertNull($g3['log_id']);
        $this->assertSame(0, (int) Capsule::table(self::LOG_TABLE)->where('channel', 'wechat')->count());
    }

    public function testMessageIdSequentialIncrementWithinSameSecond(): void
    {
        $a = $this->channel->send('sms', '13800138000', '', 'B47T-序号甲');
        $b = $this->channel->send('sms', '13800138000', '', 'B47T-序号乙');
        $this->assertTrue($a['success']);
        $this->assertTrue($b['success']);
        $this->assertMatchesRegularExpression('/^MOCK\d{14}_\d{4}$/', $a['message_id']);
        $this->assertMatchesRegularExpression('/^MOCK\d{14}_\d{4}$/', $b['message_id']);
        // 同一秒内连续成功 → 尾缀序号严格 +1（驱动进程内按秒计数）
        if (substr($a['message_id'], 4, 14) === substr($b['message_id'], 4, 14)) {
            $this->assertSame(
                (string) ((int) substr($a['message_id'], -4) + 1),
                (string) ((int) substr($b['message_id'], -4))
            );
        }
    }

    public function testRetryFailuresPicksOnlyAgedFailureFlipsAndRefreshesCooldown(): void
    {
        $flipContent = 'B47T-重试翻转';   // 收件人合法 → 重试成功后转 1
        $stayContent = 'B47T-重试仍败';   // 9 开头 → 重试仍失败（error 刷新）
        $freshContent = 'B47T-重试太新';  // 60s 冷却内 → 不挑
        $okContent = 'B47T-重试成功旧行';  // 成功行 → 永不重试
        $this->insertLogRow(['to' => '13800138000', 'content' => $flipContent, 'sent_at' => self::secondsAgo(120)]);
        $this->insertLogRow(['to' => '91234567890', 'content' => $stayContent, 'error' => '旧错误', 'sent_at' => self::secondsAgo(120)]);
        $this->insertLogRow(['content' => $freshContent, 'sent_at' => date('Y-m-d H:i:s')]);
        $this->insertLogRow(['content' => $okContent, 'status' => 1, 'message_id' => 'MOCKold', 'sent_at' => self::secondsAgo(3600)]);

        $retry = $this->channel->retryFailures();
        $this->assertSame(['attempted' => 2, 'succeeded' => 1, 'failed' => 1, 'error' => ''], $retry);

        $flip = Capsule::table(self::LOG_TABLE)->where('content', $flipContent)->first();
        $this->assertSame(1, (int) $flip->status);
        $this->assertMatchesRegularExpression('/^MOCK\d{14}_\d{4}$/', (string) $flip->message_id);
        $this->assertSame('', (string) $flip->error);

        $stay = Capsule::table(self::LOG_TABLE)->where('content', $stayContent)->first();
        $this->assertSame(2, (int) $stay->status);
        $this->assertSame('接收方号码非法', (string) $stay->error);
        $this->assertSame('', (string) $stay->message_id);
        $this->assertGreaterThanOrEqual(time() - 2, strtotime((string) $stay->sent_at), '失败重试也应刷新 sent_at');

        $fresh = Capsule::table(self::LOG_TABLE)->where('content', $freshContent)->first();
        $this->assertSame(2, (int) $fresh->status);
        $this->assertSame('', (string) $fresh->error);
        $ok = Capsule::table(self::LOG_TABLE)->where('content', $okContent)->first();
        $this->assertSame(1, (int) $ok->status);
        $this->assertSame('MOCKold', (string) $ok->message_id);

        // 紧接第二次调用：全部候选 sent_at 已被刷新出冷却窗 → attempted=0
        $again = $this->channel->retryFailures();
        $this->assertSame(0, $again['attempted']);
        $this->assertSame(0, $again['succeeded']);
        $this->assertSame(0, $again['failed']);
    }

    public function testRetryFailuresChannelFilterLimitAndAscendingId(): void
    {
        $c1 = 'B47T-限额翻转';
        $c2 = 'B47T-限额仍败';
        $mailC = 'B47T-限额邮件';
        $this->insertLogRow(['content' => $c1, 'sent_at' => self::secondsAgo(300)]);
        $this->insertLogRow(['to' => '91234567890', 'content' => $c2, 'sent_at' => self::secondsAgo(300)]);
        $this->insertLogRow(['channel' => 'mail', 'to' => 'user@example.com', 'content' => $mailC, 'sent_at' => self::secondsAgo(300)]);

        // channel 过滤：mail 行不参与；计数与 DB 翻转一致
        $filtered = $this->channel->retryFailures('sms');
        $this->assertSame(2, $filtered['attempted']);
        $this->assertSame(1, $filtered['succeeded']);
        $this->assertSame(1, $filtered['failed']);
        $this->assertSame(2, (int) Capsule::table(self::LOG_TABLE)->where('content', $mailC)->value('status'));

        // limit=1 只挑 id 最低的候选（再限额1 翻转），再限额2 原样未触碰
        $this->insertLogRow(['content' => 'B47T-再限额1', 'sent_at' => self::secondsAgo(300)]);
        $this->insertLogRow(['to' => '91234567890', 'content' => 'B47T-再限额2', 'sent_at' => self::secondsAgo(300)]);
        $lim = $this->channel->retryFailures(null, 1);
        $this->assertSame(1, $lim['attempted']);
        $this->assertSame(1, $lim['succeeded']);
        $this->assertSame('', (string) Capsule::table(self::LOG_TABLE)->where('content', 'B47T-再限额2')->value('error'));

        // 未知 channel 参数 → 直接拒绝不扫库
        $bad = $this->channel->retryFailures('wechat');
        $this->assertSame(['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'error' => '不支持的渠道'], $bad);
    }

    public function testSendLogsFilterPaginationDescAndWhitelistSemantics(): void
    {
        $mail = $this->channel->send('mail', 'b47t@example.com', '', 'B47T-日志邮件');
        $ok = $this->channel->send('sms', '13800138000', '', 'B47T-日志成功');
        $fail = $this->channel->send('sms', '91234567890', '', 'B47T-日志失败');
        $this->assertTrue($mail['success']);
        $this->assertTrue($ok['success']);
        $this->assertFalse($fail['success']);

        $all = $this->channel->sendLogs([], 1, 50);
        $this->assertSame(3, $all['total']);
        $this->assertSame($fail['log_id'], $all['list'][0]['id'], '倒序：最新在前');
        $this->assertSame(2, $all['list'][0]['status']);
        $this->assertSame('接收方号码非法', $all['list'][0]['error']);

        $sms = $this->channel->sendLogs(['channel' => 'sms'], 1, 20);
        $this->assertSame(2, $sms['total']);
        $this->assertSame('sms', $sms['list'][0]['channel']);
        $this->assertSame('sms', $sms['list'][1]['channel']);

        // 非白名单 channel 过滤被忽略（返回全部）；status 字符串入参按整型过滤
        $wechat = $this->channel->sendLogs(['channel' => 'wechat'], 1, 20);
        $this->assertSame(3, $wechat['total']);
        $failOnly = $this->channel->sendLogs(['status' => '2'], 1, 20);
        $this->assertSame(1, $failOnly['total']);

        // 分页：page 0 归 1（与 page 1 同首行）；pageSize 0 下限 1；翻页取中行
        $zero = $this->channel->sendLogs([], 0, 1);
        $one = $this->channel->sendLogs([], 1, 1);
        $this->assertSame($one['list'][0]['id'], $zero['list'][0]['id']);
        $this->assertSame(1, count($this->channel->sendLogs([], 1, 0)['list']));
        $page2 = $this->channel->sendLogs([], 2, 1);
        $this->assertSame(3, $page2['total']);
        $this->assertSame($ok['log_id'], $page2['list'][0]['id']);
    }

    public function testDefinitionCreateWhitelistsAndDuplicateRejected(): void
    {
        $base = ['entity_type' => 'sales_order', 'field_key' => 'b47xok', 'label' => '对抗字段', 'field_type' => 'text'];
        $sel = ['entity_type' => 'sales_order', 'field_key' => 'b47xsel', 'label' => '下拉', 'field_type' => 'select'];
        $this->assertSame('不支持的实体类型', $this->fields->create(['entity_type' => 'invoice'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => 'B47XBad'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => 'b47x-bad'] + $base)[1]);
        $this->assertSame('字段标识只允许小写字母、数字、下划线（≤50位）', $this->fields->create(['field_key' => str_repeat('a', 51)] + $base)[1]);
        $this->assertSame('字段名称不能为空', $this->fields->create(['label' => ''] + $base)[1]);
        $this->assertSame('字段名称长度不能超过100字', $this->fields->create(['label' => str_repeat('长', 101)] + $base)[1]);
        $this->assertSame('不支持的字段类型', $this->fields->create(['field_type' => 'enum'] + $base)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create(['options' => []] + $sel)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create(['options' => [['value' => '', 'label' => '空值']]] + $sel)[1]);
        $this->assertSame('选项须为[{value,label}]数组', $this->fields->create([
            'options' => [['value' => 'a', 'label' => 'A'], ['value' => 'a', 'label' => 'A2']],
        ] + $sel)[1]);

        // 合法 select → options 数组读回；非 select 带 options → 落库 null
        $def = $this->createDef($sel + ['options' => [['value' => 'a', 'label' => 'A'], ['value' => 'b', 'label' => 'B']]]);
        $this->assertSame(['a', 'b'], array_column($def->options ?? [], 'value'));
        $text = $this->createDef($base + ['options' => [['value' => 'x', 'label' => 'X']]]);
        $this->assertNull($text->options);

        // 同 (entity,key) 重复 → uk_entity_field 捕获
        $this->assertSame('字段定义已存在', $this->fields->create($base)[1]);
    }

    public function testDefinitionUpdateDeleteMissingAndIdentityImmutable(): void
    {
        $this->assertSame('字段定义不存在', $this->fields->update(9_999_999_999, ['label' => 'x'])[1]);
        $this->assertSame('字段定义不存在', $this->fields->delete(9_999_999_999)[1]);

        $def = $this->createDef(['entity_type' => 'customer', 'field_key' => 'b47xupd', 'label' => '旧名', 'field_type' => 'text', 'is_required' => 1, 'sort' => 3, 'status' => 1]);
        // 标识不可改：update 夹带 field_key/entity_type 被服务以原值覆盖
        $updated = $this->updateDef($def, ['label' => '新名', 'status' => 0, 'field_key' => 'b47xevil', 'entity_type' => 'supplier']);
        $this->assertSame('新名', (string) $updated->label);
        $this->assertSame(0, (int) $updated->status);
        $this->assertSame('b47xupd', (string) $updated->field_key);
        $this->assertSame('customer', (string) $updated->entity_type);

        // 物理删除后同 (entity,key) 可重建成功且 id 不同（唯一约束只防并存）
        [$gone, $err] = $this->fields->delete((int) $def->id);
        $this->assertTrue($gone);
        $this->assertNull($err);
        $reborn = $this->createDef(['entity_type' => 'customer', 'field_key' => 'b47xupd', 'label' => '新', 'field_type' => 'text']);
        $this->assertNotSame((int) $def->id, (int) $reborn->id);
    }

    public function testValidateNumberDateSelectTextRejectAndBoundary(): void
    {
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xnum', 'label' => '数量', 'field_type' => 'number']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xdat', 'label' => '交期', 'field_type' => 'date']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xsel', 'label' => '级别', 'field_type' => 'select', 'options' => [['value' => 'a', 'label' => 'A']]]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xtxt', 'label' => '备注', 'field_type' => 'text']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xbig', 'label' => '正文', 'field_type' => 'textarea']);
        $numMsg = '字段 数量 必须是数字（最多两位小数）';
        foreach (['1e3', '-2', '.5', '2.555', '12,5', ' 9.90 '] as $bad) {
            $this->assertSame([$numMsg], $this->fields->validate('sales_order', ['b47xnum' => $bad]), 'number 拒: ' . $bad);
        }
        $dateMsg = '字段 交期 日期格式须为 Y-m-d';
        foreach (['2026-13-01', '2026-02-30', '2026-9-5', 'abc', '2026/09/05'] as $bad) {
            $this->assertSame([$dateMsg], $this->fields->validate('sales_order', ['b47xdat' => $bad]), 'date 拒: ' . $bad);
        }
        $this->assertSame(['字段 级别 选项值不合法'], $this->fields->validate('sales_order', ['b47xsel' => 'zz']));
        $lenMsg = '字段 备注 长度不能超过500字';
        $this->assertSame([$lenMsg], $this->fields->validate('sales_order', ['b47xtxt' => str_repeat('好', 501)]));
        $this->assertSame([], $this->fields->validate('sales_order', ['b47xtxt' => str_repeat('好', 500)]));
        $this->assertSame(['字段 正文 长度不能超过500字'], $this->fields->validate('sales_order', ['b47xbig' => str_repeat('a', 501)]));
        // 全类型合规（number 两位小数 / date 真实日期 / select 选项内）
        $this->assertSame([], $this->fields->validate('sales_order', [
            'b47xnum' => '12.50', 'b47xdat' => '2026-02-28', 'b47xsel' => 'a', 'b47xtxt' => 'x',
        ]));
    }

    public function testValidateRequiredMissingEmptyAndUnknownKeyTolerance(): void
    {
        $this->createDef(['entity_type' => 'purchase_order', 'field_key' => 'b47xreq', 'label' => '合同号', 'field_type' => 'text', 'is_required' => 1]);
        $this->createDef(['entity_type' => 'purchase_order', 'field_key' => 'b47xamt', 'label' => '金额', 'field_type' => 'number']);
        $reqMsg = '字段 合同号 必填';
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', []));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => '']));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => '   ']));
        $this->assertSame([$reqMsg], $this->fields->validate('purchase_order', ['b47xreq' => null]));
        // int 0 为非空值：满足必填且通过数字校验
        $this->assertSame([], $this->fields->validate('purchase_order', ['b47xreq' => 'x', 'b47xamt' => 0]));
        // 未知 key 宽容：不报错、不进入归一化结果
        $this->assertSame([], $this->fields->validate('purchase_order', ['b47xreq' => '值', 'ghost_key' => '任意值']));
        [$norm, $errs] = $this->fields->applySchema('purchase_order', ['b47xreq' => 'X-1', 'ghost_key' => '剔除我']);
        $this->assertSame([], $errs);
        $this->assertSame(['b47xreq' => 'X-1'], $norm);
    }

    public function testDisabledDefinitionExcludedFromValidateAndSchema(): void
    {
        $def = $this->createDef(['entity_type' => 'supplier', 'field_key' => 'b47xoff', 'label' => '停用字段', 'field_type' => 'text', 'is_required' => 1]);
        $this->assertSame(['字段 停用字段 必填'], $this->fields->validate('supplier', []));
        $this->updateDef($def, ['status' => 0]);
        $this->assertSame([], $this->fields->validate('supplier', []));
        [$norm, $errs] = $this->fields->applySchema('supplier', ['b47xoff' => '值']);
        $this->assertSame([], $errs);
        $this->assertSame([], $norm);
    }

    public function testApplySchemaNormalizeAndSalesOrderJsonRoundtrip(): void
    {
        $this->requireOrderTableReady();
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xnote', 'label' => '备注', 'field_type' => 'text']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xamt', 'label' => '金额', 'field_type' => 'number']);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xreg', 'label' => '大区', 'field_type' => 'select', 'options' => [
            ['value' => '华东区', 'label' => '华东'],
            ['value' => '华南区', 'label' => '华南'],
        ]]);
        $this->createDef(['entity_type' => 'sales_order', 'field_key' => 'b47xclr', 'label' => '清空', 'field_type' => 'text']);

        [$values, $errors] = $this->fields->applySchema('sales_order', [
            'b47xnote' => '中文备注【含特殊字符】',
            'b47xamt' => '12.50',
            'b47xreg' => '华东区',
            'b47xclr' => '',
            'ghost' => '剔',
        ]);
        $this->assertSame([], $errors);
        $this->assertSame([
            'b47xnote' => '中文备注【含特殊字符】', 'b47xamt' => '12.50', 'b47xreg' => '华东区', 'b47xclr' => null,
        ], $values);

        $id = random_int(900_000_000_001, 999_999_999_999);
        $code = 'B47T-' . date('His') . random_int(1000, 9999);
        try {
            Capsule::table(self::ORDER_TABLE)->insert([
                'id' => $id, 'code' => $code, 'customer_id' => 0,
                'custom_fields' => json_encode($values, JSON_UNESCAPED_UNICODE),
            ]);
        } catch (QueryException $e) {
            self::markTestSkipped(self::ORDER_TABLE . ' 不允许最简插入（先按 install.sql 导入主结构）: ' . $e->getMessage());
        }
        try {
            $row = Capsule::table(self::ORDER_TABLE)->where('id', $id)->first();
            $this->assertNotNull($row);
            $decoded = json_decode((string) $row->custom_fields, true);
            // MySQL JSON 二进制存储按键排序 → ksort 后与字母序字面量 assertSame
            ksort($decoded);
            $this->assertSame([
                'b47xamt' => '12.50', 'b47xclr' => null, 'b47xnote' => '中文备注【含特殊字符】', 'b47xreg' => '华东区',
            ], $decoded);

            // 停用定义后旧值仍原样在行上（停用不影响已存数据读取）
            $defNote = (new CustomFieldDefinition())->newQuery()->where('field_key', 'b47xnote')->first();
            $this->updateDef($defNote, ['status' => 0]);
            $decoded2 = json_decode((string) Capsule::table(self::ORDER_TABLE)->where('id', $id)->value('custom_fields'), true);
            $this->assertSame('中文备注【含特殊字符】', $decoded2['b47xnote']);
        } finally {
            Capsule::table(self::ORDER_TABLE)->where('id', $id)->delete();
        }
    }

    public function testInvalidJsonIntoCustomFieldsColumnRejected(): void
    {
        $this->requireOrderTableReady();
        $code = 'B47T-BADJSON-' . random_int(1000, 9999);
        try {
            Capsule::table(self::ORDER_TABLE)->insert([
                'id' => random_int(900_000_000_001, 999_999_999_999),
                'code' => $code,
                'customer_id' => 0,
                'custom_fields' => '{"b47xnote": 未闭合',
            ]);
            $this->fail('非法 JSON 应被 custom_fields 列拒绝');
        } catch (QueryException $e) {
            $message = $e->getMessage();
            if (!str_contains($message, '3140') && !str_contains($message, 'Invalid JSON')) {
                self::markTestSkipped('插入未触达 JSON 校验（主表缺列?），错误: ' . $message);
            }
        }
        $this->assertSame(0, (int) Capsule::table(self::ORDER_TABLE)->where('code', 'like', 'B47T%')->count());
    }
}
