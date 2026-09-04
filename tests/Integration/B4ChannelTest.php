<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace tests\Integration;

use app\service\notification\ChannelService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;

/**
 * P2-5 B4 通知渠道驱动化集成测试（--group=integration）
 *
 * 环境变量契约（缺省即整类优雅跳过，详见 IntegrationTestCase 类头）：
 *   TEST_DB_HOST / TEST_DB_PORT / TEST_DB_DATABASE / TEST_DB_USERNAME / TEST_DB_PASSWORD
 *
 * 前置：先向测试库导入 database/b4b7_channel.sql（erp_notification_channel_log
 * 与本文件蓝图为同一结构，二者其一即可；蓝图在缺表时按需自建、已存在则清空，
 * CI 多轮幂等）。erp_tenant 等真实表不受本类影响。
 *
 * 被测契约（错误消息为稳定契约，逐条精确断言）：
 * 1. Mock 驱动确定性：sms 接收方以 9 开头 → 接收方号码非法；mail 无 @ →
 *    接收方邮箱非法；内容 >500 字符 → 内容超长；成功 message_id 形如
 *    MOCK20260905103000_0001（同秒序号递增）。
 * 2. ChannelService::send 校验（不支持的渠道/接收方不能为空/内容不能为空）、
 *    成败均落 erp_notification_channel_log（status 1/2），失败不写 message_id。
 * 3. 幂等窗口：同 (channel,to,sha256(content)) 且 status=1、sent_at 距今
 *    ≤300 秒时返回既有记录（dedup=true、同 log_id），不重复发送不重复落日志。
 * 4. retryFailures：status=2 且上次尝试 ≥60 秒前（id 升序 ≤limit 条）才重试；
 *    每次尝试刷新 sent_at；成功转 1 记 message_id，仍失败保持 2 刷 error。
 * 5. sendLogs：channel/status/to(模糊) 过滤 + 倒序分页 total。
 */
#[Group('integration')]
class B4ChannelTest extends IntegrationTestCase
{
    private const LOG_TABLE = 'erp_notification_channel_log';

    private ChannelService $service;

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
        Capsule::table(self::LOG_TABLE)->delete();
        $this->service = new ChannelService();
    }

    protected function tearDown(): void
    {
        if (self::$capsule !== null) {
            self::dropTableIfExists(self::LOG_TABLE);
        }
        parent::tearDown();
    }

    private static function secondsAgo(int $seconds): string
    {
        return date('Y-m-d H:i:s', time() - $seconds);
    }

    /** 直插发送日志行（构造任意历史状态；时间戳列走表默认） */
    private function insertLogRow(int $id, array $overrides = []): void
    {
        Capsule::table(self::LOG_TABLE)->insert(array_merge([
            'id' => $id,
            'channel' => 'sms',
            'to' => '13800138000',
            'subject' => '',
            'content' => '直插行',
            'content_hash' => str_repeat('0', 64),
            'status' => 2,
            'message_id' => '',
            'error' => '',
            'sent_at' => date('Y-m-d H:i:s'),
            'operator_id' => 0,
        ], $overrides));
    }

    private function rowCount(): int
    {
        return (int) Capsule::table(self::LOG_TABLE)->count();
    }

    // ---------- 1. 驱动确定性 ----------

    public function testSmsMockDeterministicFailureAndSuccess(): void
    {
        // 9 开头 → 号码非法；尝试同样落库 status=2（服务层才拒绝、不落库）
        $fail = $this->service->send('sms', '91234567890', '', '催款通知');
        $this->assertFalse($fail['success']);
        $this->assertSame('接收方号码非法', $fail['error']);
        $this->assertNotNull($fail['log_id']);
        $this->assertSame('', $fail['message_id']);

        // 正常号码 → 成功；message_id 同秒序号格式
        $ok = $this->service->send('sms', '13800138000', '还款提醒', '本月账单已出');
        $this->assertTrue($ok['success']);
        $this->assertFalse($ok['dedup']);
        $this->assertMatchesRegularExpression('/^MOCK\d{14}_\d{4}$/', $ok['message_id']);
        $this->assertNotNull($ok['log_id']);
        $this->assertSame('', $ok['error']);

        $this->assertSame(2, $this->rowCount());
        $row = Capsule::table(self::LOG_TABLE)->where('id', $ok['log_id'])->first();
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('MOCK', substr((string) $row->message_id, 0, 4));
        $failRow = Capsule::table(self::LOG_TABLE)->where('channel', 'sms')->where('to', '91234567890')->first();
        $this->assertSame(2, (int) $failRow->status);
        $this->assertSame('接收方号码非法', (string) $failRow->error);
        $this->assertSame('', (string) $failRow->message_id);
    }

    public function testMailMockDeterministicFailure(): void
    {
        $fail = $this->service->send('mail', 'not-an-email', '主题', '正文');
        $this->assertFalse($fail['success']);
        $this->assertSame('接收方邮箱非法', $fail['error']);

        $ok = $this->service->send('mail', 'ops@example.com', '主题', '正文');
        $this->assertTrue($ok['success']);
        $this->assertSame('', $ok['error']);
    }

    public function testContentOver500CharsFailsBothChannels(): void
    {
        $long = str_repeat('长', 501);
        $sms = $this->service->send('sms', '13800138000', '', $long);
        $this->assertFalse($sms['success']);
        $this->assertSame('内容超长', $sms['error']);

        $mail = $this->service->send('mail', 'a@b.com', '', $long);
        $this->assertFalse($mail['success']);
        $this->assertSame('内容超长', $mail['error']);

        // 500 字符为合法上限（边界）
        $edge = $this->service->send('sms', '13800138000', '', str_repeat('长', 500));
        $this->assertTrue($edge['success']);
    }

    // ---------- 2. 服务层校验 ----------

    public function testSendRejectsUnknownChannelAndEmptyFields(): void
    {
        $cases = [
            ['inapp', 'u1', '内容', '不支持的渠道'], // inapp 属站内通知，走 erp_notification
            ['push', 'u1', '内容', '不支持的渠道'],
            ['sms', '', '内容', '接收方不能为空'],
            ['sms', '13800138000', '', '内容不能为空'],
        ];
        foreach ($cases as [$channel, $to, $content, $expected]) {
            $result = $this->service->send($channel, $to, '', $content);
            $this->assertFalse($result['success']);
            $this->assertSame($expected, $result['error']);
            $this->assertNull($result['log_id']);
        }
        $this->assertSame(0, $this->rowCount());
    }

    // ---------- 3. 幂等窗口（300s） ----------

    public function testDedupWithinWindowReturnsExistingRecord(): void
    {
        $first = $this->service->send('sms', '13800138000', '', '同一条通知');
        $second = $this->service->send('sms', '13800138000', '', '同一条通知');
        $this->assertTrue($first['success']);
        $this->assertTrue($second['success']);
        $this->assertTrue($second['dedup']);
        $this->assertSame($first['log_id'], $second['log_id']);
        $this->assertSame($first['message_id'], $second['message_id']);
        $this->assertSame(1, $this->rowCount());

        // 同内容不同接收方 / 同接收方不同内容：不判重
        $this->service->send('sms', '13900139000', '', '同一条通知');
        $this->service->send('sms', '13800138000', '', '另一条通知');
        $this->assertSame(3, $this->rowCount());
    }

    public function testFailureOutsideDedupWindowIsRetriedOnNextSend(): void
    {
        $this->assertTrue($this->service->send('sms', '13800138000', '', '窗口内容')['success']);
        // 把成功行拨回 301 秒前 → 同内容重发不再命中窗口
        Capsule::table(self::LOG_TABLE)->update(['sent_at' => self::secondsAgo(301)]);
        $again = $this->service->send('sms', '13800138000', '', '窗口内容');
        $this->assertTrue($again['success']);
        $this->assertFalse($again['dedup']);
        $this->assertSame(2, $this->rowCount());
    }

    // ---------- 4. 重试 ----------

    public function testRetrySkipsFreshFailuresAndRetriesAfterCooldown(): void
    {
        // 冷却期内（60 秒内刚失败）→ 不动
        $this->insertLogRow(900001, ['status' => 2]);
        $fresh = $this->service->retryFailures();
        $this->assertSame(['attempted' => 0, 'succeeded' => 0, 'failed' => 0, 'error' => ''], $fresh);

        // 120 秒前的失败 → 重试成功：attempted/succeeded=1，status 转 1 + message_id
        $this->insertLogRow(900002, [
            'status' => 2,
            'content' => '补发通知',
            'content_hash' => hash('sha256', '补发通知'),
            'sent_at' => self::secondsAgo(120),
        ]);
        $retried = $this->service->retryFailures('sms', 10);
        $this->assertSame(1, $retried['attempted']);
        $this->assertSame(1, $retried['succeeded']);
        $this->assertSame(0, $retried['failed']);
        $this->assertSame('', $retried['error']);

        $row = Capsule::table(self::LOG_TABLE)->where('id', 900002)->first();
        $this->assertSame(1, (int) $row->status);
        $this->assertSame('', (string) $row->error);
        $this->assertMatchesRegularExpression('/^MOCK\d{14}_\d{4}$/', (string) $row->message_id);
        $this->assertTrue((string) $row->sent_at >= self::secondsAgo(5), '重试应刷新 sent_at');
    }

    public function testRetryStillFailingKeepsFailureAndRefreshesSentAt(): void
    {
        // 接收方合法仅内容超长：驱动先校验接收方后校验长度，双非法时错误不确定
        $this->insertLogRow(900011, [
            'status' => 2,
            'content' => str_repeat('长', 501),
            'content_hash' => hash('sha256', str_repeat('长', 501)),
            'sent_at' => self::secondsAgo(600),
        ]);
        $result = $this->service->retryFailures('sms', 10);
        $this->assertSame(1, $result['attempted']);
        $this->assertSame(0, $result['succeeded']);
        $this->assertSame(1, $result['failed']);

        $row = Capsule::table(self::LOG_TABLE)->where('id', 900011)->first();
        $this->assertSame(2, (int) $row->status);
        $this->assertSame('内容超长', (string) $row->error);
        $this->assertTrue((string) $row->sent_at >= self::secondsAgo(5), '失败重试也应刷新 sent_at');
    }

    public function testRetryRejectsUnknownChannel(): void
    {
        $this->insertLogRow(900021, ['status' => 2, 'sent_at' => self::secondsAgo(600)]);
        $result = $this->service->retryFailures('wechat', 10);
        $this->assertSame(0, $result['attempted']);
        $this->assertSame('不支持的渠道', $result['error']);
        // 仍失败行未被触碰
        $row = Capsule::table(self::LOG_TABLE)->where('id', 900021)->first();
        $this->assertSame(2, (int) $row->status);
    }

    // ---------- 5. 日志查询 ----------

    public function testSendLogsFilterAndPagination(): void
    {
        $this->insertLogRow(900101, ['channel' => 'mail', 'status' => 1, 'to' => 'ops@example.com', 'sent_at' => self::secondsAgo(600)]);
        $this->insertLogRow(900102, ['channel' => 'sms', 'status' => 2, 'to' => '91234567890', 'sent_at' => self::secondsAgo(600)]);
        $this->insertLogRow(900103, ['channel' => 'sms', 'status' => 1, 'to' => '13800138000', 'sent_at' => self::secondsAgo(600)]);

        $all = $this->service->sendLogs();
        $this->assertSame(3, $all['total']);
        $this->assertSame(900103, $all['list'][0]['id']); // 倒序

        $smsOk = $this->service->sendLogs(['channel' => 'sms', 'status' => 1]);
        $this->assertSame(1, $smsOk['total']);
        $this->assertSame('13800138000', $smsOk['list'][0]['to']);

        $like = $this->service->sendLogs(['to' => 'ops@']);
        $this->assertSame(1, $like['total']);
        $this->assertSame('mail', $like['list'][0]['channel']);

        $paged = $this->service->sendLogs([], 1, 2);
        $this->assertSame(3, $paged['total']);
        $this->assertCount(2, $paged['list']);
    }
}
