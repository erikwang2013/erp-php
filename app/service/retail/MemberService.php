<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\retail;

use app\common\SnowflakeService;
use app\model\Customer;
use app\model\Member;
use app\model\MemberBalanceAccount;
use app\model\MemberBalanceLog;
use app\model\MemberCoupon;
use app\model\MemberCouponTemplate;
use app\model\MemberPointAccount;
use app\model\MemberPointLog;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * 会员价值引擎服务 — P2-3 C1（会员/储值/积分/卡券；全量 bcmath，禁 float 算术）
 *
 * 原子性：余额/积分/发券变动一律在 DB::transaction 内先 lockForUpdate 账户行
 * （一会员一行）再判定写入——杜绝并发透支/双退/超发。返回 [data, null] 成功、
 * [null, msg] 失败；金额为 ≤2 位小数十进制字符串（禁 is_numeric：放行 1e3/INF
 * 等 bcadd 解析陷阱形态）；biz_id 为外部业务单号（仅数字），消费/退款以其 +
 * biz_type 为业务键。会员禁用后拒绝一切资金/积分/发券写操作。
 *
 * POS/小程序对接缝：consume/refund/earnPoints/consumePoints 即收银闭环原子入口
 * ——消费方传自身订单 id 作 biz_id 自建订单（本批不写 OMS 订单表）；卡券权益在
 * 后续批次由收银端按 memberOverview + 卡券自行核算。
 */
class MemberService
{
    /** 会员状态 */
    public const STATUS_DISABLED = 0;
    public const STATUS_ENABLED = 1;
    /** 储值流水类型 */
    public const BAL_RECHARGE = 'recharge';
    public const BAL_CONSUME = 'consume';
    public const BAL_REFUND = 'refund';
    public const BAL_ADJUST = 'adjust';
    /** 积分流水类型 */
    public const POINT_EARN = 'earn';
    public const POINT_CONSUME = 'consume';
    public const POINT_EXPIRE = 'expire';
    public const POINT_ADJUST = 'adjust';
    /** 卡券状态 */
    public const COUPON_UNUSED = 0;
    public const COUPON_USED = 1;
    public const COUPON_EXPIRED = 2;
    /** 开卡来源白名单 */
    private const SOURCES = ['pos', 'miniapp', 'manual'];

    /**
     * 开卡（含储值账户 0.00 + 积分账户 0 同步建档）。
     * 手机号唯一且软删记录占用号码：withTrashed 复核 + uk_phone 双保险，
     * 软删会员一律拒绝重开（先恢复原会员再重开是既有惯例的破坏面，本批不做）。
     */
    public function openMember(array $input): array
    {
        $phone = trim((string) ($input['phone'] ?? ''));
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            return [null, '手机号格式非法，须为 11 位手机号'];
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            return [null, '姓名必填'];
        }
        if (mb_strlen($name) > 50) {
            return [null, '姓名超长(50)'];
        }
        // 等级/客户ID 严格数字串校验（拒 '2.5'/'abc'/'-1' 等被 (int) 静默强转的载荷，与 phone/source 同严）
        $levelStr = isset($input['level']) ? trim((string) $input['level']) : '0';
        if (!preg_match('/^\d+$/', $levelStr) || (int) $levelStr > 3) {
            return [null, '会员等级非法'];
        }
        $level = (int) $levelStr;
        $customerIdStr = isset($input['customer_id']) ? trim((string) $input['customer_id']) : '0';
        if (!preg_match('/^\d+$/', $customerIdStr)) {
            return [null, '客户ID非法（须为纯数字）'];
        }
        $customerId = (int) $customerIdStr;
        if ($customerId > 0 && Customer::find($customerId) === null) {
            return [null, '关联客户不存在'];
        }
        $source = (string) ($input['source'] ?? 'manual');
        if (!in_array($source, self::SOURCES, true)) {
            return [null, '开卡来源非法'];
        }
        $remark = trim((string) ($input['remark'] ?? ''));
        if (mb_strlen($remark) > 500) {
            return [null, '备注超长(500)'];
        }
        if (Member::withTrashed()->where('phone', $phone)->exists()) {
            return [null, '该手机号已开卡，不可重复开卡'];
        }
        try {
            $member = DB::transaction(function () use ($phone, $name, $level, $customerId, $source, $remark): Member {
                // uk_phone 兜底并发同号开卡
                $member = new Member();
                $member->id = SnowflakeService::generate();
                $member->phone = $phone;
                $member->name = $name;
                $member->level = $level;
                $member->customer_id = $customerId;
                $member->source = $source;
                $member->status = self::STATUS_ENABLED;
                $member->remark = $remark;
                $member->save();
                $acc = new MemberBalanceAccount();
                $acc->id = SnowflakeService::generate();
                $acc->member_id = (int) $member->id;
                $acc->balance = '0.00';
                $acc->save();
                $point = new MemberPointAccount();
                $point->id = SnowflakeService::generate();
                $point->member_id = (int) $member->id;
                $point->points = 0;
                $point->save();

                return $member;
            });
        } catch (\Throwable $e) {
            // uk_phone 并发同号兜底：1062 重复键 → 规格话术，其余真失败照实返回
            if ($e instanceof \Illuminate\Database\QueryException && ($e->errorInfo[1] ?? 0) === 1062) {
                return [null, '该手机号已开卡，不可重复开卡'];
            }

            return [null, '开卡失败: ' . $e->getMessage()];
        }

        return [$this->memberShape($member), null];
    }

    /** 储值充值（入正）。返回 {balance_after}。 */
    public function recharge(int $memberId, string $amount, int $operatorId, string $remark = ''): array
    {
        if ($this->activeMember($memberId) === null) {
            return [null, '会员不存在或已禁用'];
        }
        $money = $this->normalizeMoney($amount);
        if ($money === null) {
            return [null, '充值金额非法（最多两位小数）'];
        }
        if (bccomp($money, '0', 2) !== 1) {
            return [null, '充值金额必须大于 0'];
        }
        if (mb_strlen($remark) > 500) {
            return [null, '备注超长(500)'];
        }
        try {
            $balanceAfter = DB::transaction(fn () => $this->applyBalanceChange(
                $memberId,
                $money,
                self::BAL_RECHARGE,
                0,
                $operatorId,
                $remark,
                false,
                false
            ));
        } catch (\Throwable $e) {
            return [null, '充值失败: ' . $e->getMessage()];
        }

        return [['balance_after' => $balanceAfter], null];
    }

    /**
     * 储值消费（出负；POS/小程序收银闭环原子入口——biz_id 传自身订单号）。
     * 不足整笔拒绝（「储值余额不足」）且不留任何流水；优惠核算在收银端，本批不含折扣拆分。
     */
    public function consume(int $memberId, string $amount, string $bizId, int $operatorId): array
    {
        if ($this->activeMember($memberId) === null) {
            return [null, '会员不存在或已禁用'];
        }
        $money = $this->normalizeMoney($amount);
        if ($money === null) {
            return [null, '消费金额非法（最多两位小数）'];
        }
        if (bccomp($money, '0', 2) !== 1) {
            return [null, '消费金额必须大于 0'];
        }
        $biz = $this->normalizeBizId($bizId);
        if ($biz === null) {
            return [null, '业务单号非法（须为纯数字）'];
        }
        try {
            $balanceAfter = DB::transaction(fn () => $this->applyBalanceChange(
                $memberId,
                bcsub('0', $money, 2),
                self::BAL_CONSUME,
                $biz,
                $operatorId,
                '',
                true,
                false
            ));
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }

        return [['balance_after' => $balanceAfter], null];
    }

    /**
     * 储值退款（入正，冲正已扣金额）。幂等：同 biz_id 已退过 → 「该业务单已退款」；
     * 金额由调用方给出，允许部分退款——累计退款不超原消费额的上限校验在 POS 退款单侧。
     */
    public function refund(int $memberId, string $amount, string $bizId, int $operatorId): array
    {
        if ($this->activeMember($memberId) === null) {
            return [null, '会员不存在或已禁用'];
        }
        $money = $this->normalizeMoney($amount);
        if ($money === null) {
            return [null, '退款金额非法（最多两位小数）'];
        }
        if (bccomp($money, '0', 2) !== 1) {
            return [null, '退款金额必须大于 0'];
        }
        $biz = $this->normalizeBizId($bizId);
        if ($biz === null) {
            return [null, '业务单号非法（须为纯数字）'];
        }
        try {
            $balanceAfter = DB::transaction(fn () => $this->applyBalanceChange(
                $memberId,
                $money,
                self::BAL_REFUND,
                $biz,
                $operatorId,
                '',
                false,
                true
            ));
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }

        return [['balance_after' => $balanceAfter], null];
    }

    /** 积分入账（赚取）。返回 {points_after}。 */
    public function earnPoints(int $memberId, int $points, int $operatorId, string $remark = ''): array
    {
        if ($points <= 0) {
            return [null, '积分数必须大于 0'];
        }

        return $this->movePoints($memberId, $points, self::POINT_EARN, $operatorId, $remark);
    }

    /** 积分抵扣（出负；POS 收银抵扣入口，不足整笔拒绝「积分不足」）。返回 {points_after}。 */
    public function consumePoints(int $memberId, int $points, int $operatorId, string $remark = ''): array
    {
        if ($points <= 0) {
            return [null, '积分数必须大于 0'];
        }

        return $this->movePoints($memberId, -$points, self::POINT_CONSUME, $operatorId, $remark);
    }

    /** 积分作废（出负，定期任务/手工调过期积分）。返回 {points_after}。 */
    public function expirePoints(int $memberId, int $points, int $operatorId, string $remark = ''): array
    {
        if ($points <= 0) {
            return [null, '作废积分数必须大于 0'];
        }

        return $this->movePoints($memberId, -$points, self::POINT_EXPIRE, $operatorId, $remark);
    }

    /**
     * 发券。校验模板启用 + 限量余量；expire_at = 领取时刻 + 模板 valid_days 天
     * （valid_days=0 → NULL 长期有效）。模板行锁防并发超发。
     * 返回 {coupon_id, received_at, expire_at}。
     */
    public function issueCoupon(int $memberId, int $templateId, int $operatorId): array
    {
        if ($this->activeMember($memberId) === null) {
            return [null, '会员不存在或已禁用'];
        }
        try {
            $issued = DB::transaction(function () use ($memberId, $templateId, $operatorId): array {
                $template = MemberCouponTemplate::where('id', $templateId)
                    ->where('status', 1)->lockForUpdate()->first();
                if ($template === null) {
                    throw new \RuntimeException('卡券模板不存在或已停用');
                }
                if ((int) $template->total_qty > 0 && (int) $template->issued_qty >= (int) $template->total_qty) {
                    throw new \RuntimeException('该卡券模板已发完');
                }
                $receivedAt = date('Y-m-d H:i:s');
                $validDays = (int) $template->valid_days;
                $coupon = new MemberCoupon();
                $coupon->id = SnowflakeService::generate();
                $coupon->member_id = $memberId;
                $coupon->template_id = $templateId;
                $coupon->status = self::COUPON_UNUSED;
                $coupon->received_at = $receivedAt;
                $coupon->expire_at = $validDays > 0
                    ? date('Y-m-d H:i:s', strtotime("+{$validDays} days", strtotime($receivedAt)))
                    : null;
                $coupon->order_source = '';
                $coupon->save();
                $template->issued_qty = (int) $template->issued_qty + 1;
                $template->save();

                return ['coupon_id' => (int) $coupon->id, 'received_at' => $receivedAt, 'expire_at' => $coupon->expire_at];
            });
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }

        return [$issued, null];
    }

    /**
     * 核销卡券（0未使用 → 1已核销）。过期券核销判拒时惰性置 2（无定时扫表，
     * 批量过期清零由后续批次任务做；可用性判定一律按 expire_at）；memberId=0
     * 为管理端代核销（不校验归属），>0 须归属一致（「该卡券不属于该会员」）。返回 {used_at}。
     */
    public function redeemCoupon(int $memberCouponId, string $orderSource, int $operatorId, int $memberId = 0): array
    {
        $source = trim($orderSource);
        if ($source === '') {
            return [null, '核销来源不能为空'];
        }
        if (mb_strlen($source) > 20) {
            return [null, '核销来源超长(20)'];
        }
        try {
            $usedAt = DB::transaction(function () use ($memberCouponId, $source, $operatorId, $memberId): string {
                $coupon = MemberCoupon::where('id', $memberCouponId)->lockForUpdate()->first();
                if ($coupon === null) {
                    throw new \RuntimeException('卡券不存在');
                }
                if ($memberId > 0 && (int) $coupon->member_id !== $memberId) {
                    throw new \RuntimeException('该卡券不属于该会员');
                }
                if ($this->activeMember((int) $coupon->member_id) === null) {
                    throw new \RuntimeException('会员不存在或已禁用');
                }
                if ($coupon->expire_at !== null && strtotime((string) $coupon->expire_at) <= time()) {
                    throw new \RuntimeException('该卡券已过期');
                }
                if ((int) $coupon->status === self::COUPON_USED) {
                    throw new \RuntimeException('该卡券已核销');
                }
                if ((int) $coupon->status === self::COUPON_EXPIRED) {
                    throw new \RuntimeException('该卡券已过期');
                }
                $usedAt = date('Y-m-d H:i:s');
                $coupon->status = self::COUPON_USED;
                $coupon->used_at = $usedAt;
                $coupon->order_source = $source;
                $coupon->save();

                return $usedAt;
            });
        } catch (\Throwable $e) {
            // 惰性置 2：事务内写会随回滚撤销，须在事务外补记（where status=0 防覆盖已核销行）
            if ($e->getMessage() === '该卡券已过期') {
                MemberCoupon::where('id', $memberCouponId)->where('status', self::COUPON_UNUSED)
                    ->update(['status' => self::COUPON_EXPIRED]);
            }

            return [null, $e->getMessage()];
        }

        return [['used_at' => $usedAt], null];
    }

    /**
     * 会员总览：主档 + 储值/积分余额 + 可用卡券数 + 累计充值/消费。
     * 累计由流水逐行 bcadd 求得（不信任账户列做汇总口径），余额取账户行。
     */
    public function memberOverview(int $memberId): array
    {
        $member = Member::find($memberId);
        if ($member === null) {
            return [null, '会员不存在'];
        }
        $balance = '0.00';
        $points = 0;
        $account = MemberBalanceAccount::where('member_id', $memberId)->first();
        if ($account !== null) {
            $balance = bc_round(bc_norm((string) $account->balance), 2);
        }
        $pointAccount = MemberPointAccount::where('member_id', $memberId)->first();
        if ($pointAccount !== null) {
            $points = (int) $pointAccount->points;
        }
        $totalRecharge = $totalConsume = '0';
        foreach (MemberBalanceLog::where('member_id', $memberId)->orderBy('id')->get() as $log) {
            if ((string) $log->biz_type === self::BAL_RECHARGE) {
                $totalRecharge = bcadd($totalRecharge, bc_norm((string) $log->amount), 2);
            } elseif ((string) $log->biz_type === self::BAL_CONSUME) {
                // 消费流水 amount 为负，累计时取绝对值
                $totalConsume = bcadd($totalConsume, bcsub('0', bc_norm((string) $log->amount), 2), 2);
            }
        }
        $available = MemberCoupon::where('member_id', $memberId)->where('status', self::COUPON_UNUSED)
            ->where(function ($q): void {
                $q->whereNull('expire_at')->orWhere('expire_at', '>', date('Y-m-d H:i:s'));
            })->count();

        return [$this->memberShape($member) + [
            'balance' => $balance,
            'points' => $points,
            'coupons_available' => (int) $available,
            'total_recharge' => bc_round($totalRecharge, 2),
            'total_consume' => bc_round($totalConsume, 2),
        ], null];
    }

    /** 会员主档出参（openMember/memberOverview 共用；键值全显式 int/string 型） */
    private function memberShape(Member $member): array
    {
        return [
            'id' => (int) $member->id,
            'phone' => (string) $member->phone,
            'name' => (string) $member->name,
            'level' => (int) $member->level,
            'customer_id' => (int) $member->customer_id,
            'source' => (string) $member->source,
            'status' => (int) $member->status,
            'remark' => (string) $member->remark,
        ];
    }

    /** 积分通用变动（带符号 delta 落账 + 流水），不足抛「积分不足」 */
    private function movePoints(int $memberId, int $delta, string $bizType, int $operatorId, string $remark): array
    {
        if ($this->activeMember($memberId) === null) {
            return [null, '会员不存在或已禁用'];
        }
        if (mb_strlen($remark) > 500) {
            return [null, '备注超长(500)'];
        }
        try {
            $pointsAfter = DB::transaction(function () use ($memberId, $delta, $bizType, $operatorId, $remark): int {
                $account = $this->lockPointAccount($memberId);
                if ($delta < 0 && (int) $account->points + $delta < 0) {
                    throw new \RuntimeException('积分不足');
                }
                $pointsAfter = (int) $account->points + $delta;
                $account->points = $pointsAfter;
                $account->save();
                $log = new MemberPointLog();
                $log->id = SnowflakeService::generate();
                $log->member_id = $memberId;
                $log->biz_type = $bizType;
                $log->biz_id = 0;
                $log->points = $delta;
                $log->points_after = $pointsAfter;
                $log->operator_id = $operatorId;
                $log->remark = $remark;
                $log->save();

                return $pointsAfter;
            });
        } catch (\Throwable $e) {
            return [null, $e->getMessage()];
        }

        return [['points_after' => $pointsAfter], null];
    }

    /** 会员存在且启用；否则 null（写操作统一前置门槛） */
    private function activeMember(int $memberId): ?Member
    {
        $member = Member::find($memberId);
        if ($member === null || (int) $member->status !== self::STATUS_ENABLED) {
            return null;
        }

        return $member;
    }

    /** 事务内锁储值账户行；账户缺失（种子直插会员场景）补建 0 余额行 */
    private function lockBalanceAccount(int $memberId): MemberBalanceAccount
    {
        $account = MemberBalanceAccount::where('member_id', $memberId)->lockForUpdate()->first();
        if ($account === null) {
            $account = new MemberBalanceAccount();
            $account->id = SnowflakeService::generate();
            $account->member_id = $memberId;
            $account->balance = '0.00';
            $account->save();
        }

        return $account;
    }

    /** 事务内锁积分账户行；缺失补建 0 分行 */
    private function lockPointAccount(int $memberId): MemberPointAccount
    {
        $account = MemberPointAccount::where('member_id', $memberId)->lockForUpdate()->first();
        if ($account === null) {
            $account = new MemberPointAccount();
            $account->id = SnowflakeService::generate();
            $account->member_id = $memberId;
            $account->points = 0;
            $account->save();
        }

        return $account;
    }

    /**
     * 储值变动原子体（充值/消费/退款共用；事务内行锁后执行）：
     * 幂等门槛（同 biz 已退 → 抛「该业务单已退款」）→ 余额门槛（不足抛「储值余额不足」，
     * 仅消费需要）→ 落账 → 写流水；抛异常即整笔回滚不留痕。$signed 为带符号金额。
     */
    private function applyBalanceChange(
        int $memberId,
        string $signed,
        string $bizType,
        int $bizId,
        int $operatorId,
        string $remark,
        bool $checkSufficient,
        bool $checkDupRefund
    ): string {
        $account = $this->lockBalanceAccount($memberId);
        if ($checkDupRefund && MemberBalanceLog::where('member_id', $memberId)
            ->where('biz_type', self::BAL_REFUND)->where('biz_id', $bizId)->exists()) {
            throw new \RuntimeException('该业务单已退款');
        }
        if ($checkSufficient && bccomp($account->balance, bc_abs($signed), 2) < 0) {
            throw new \RuntimeException('储值余额不足');
        }
        $after = bcadd($account->balance, $signed, 2);
        $account->balance = $after;
        $account->save();
        $log = new MemberBalanceLog();
        $log->id = SnowflakeService::generate();
        $log->member_id = $memberId;
        $log->biz_type = $bizType;
        $log->biz_id = $bizId;
        $log->amount = $signed;
        $log->balance_after = $after;
        $log->operator_id = $operatorId;
        $log->remark = $remark;
        $log->save();

        return $after;
    }

    /** 金额边界校验：纯十进制、≤2 位小数（拒绝 1e3/INF/3 位小数等 bcmath 陷阱形态） */
    private function normalizeMoney(string $raw): ?string
    {
        $raw = trim($raw);
        if (!preg_match('/^-?\d+(\.\d{1,2})?$/', $raw)) {
            return null;
        }

        return bc_round(bc_norm($raw), 2);
    }

    /** 业务单号：纯数字（1~19 位），落库为整型 */
    private function normalizeBizId(string $raw): ?int
    {
        $raw = trim($raw);
        if ($raw === '' || !preg_match('/^\d{1,19}$/', $raw)) {
            return null;
        }

        return (int) $raw;
    }
}
