<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\crm;

use app\model\CrmAnalyticsMetric;
use app\model\CrmAnalyticsReport;
use app\model\CrmCampaign;
use app\model\CrmCampaignParticipant;
use app\model\CrmContract;
use app\model\CrmContractItem;
use app\model\CrmPoolRecord;
use app\model\CrmPoolRule;
use app\model\CrmQuotation;
use app\model\CrmQuotationItem;
use app\model\CrmTicket;
use app\model\CrmTicketReply;
use app\model\Customer;
use app\model\SalesOrder;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;
use Throwable;

/**
 * CRM 模块薄服务层（P2-F2）
 *
 * 承接 CRM 模块 10 个控制器的模型查询/写入逻辑：
 *  - 通用 CRUD（列表/详情/创建/更新/删除）由 AbstractCrudService 提供；
 *  - 本类沉淀模块特有业务：合同状态流转、报价转合同、公海池领取/释放、
 *    工单指派/解决/回复、明细级联清理、分析报表模拟数据构建等。
 *
 * 业务规则校验失败时抛出 \InvalidArgumentException（中文消息），
 * 由控制器 catch 后映射为 422 响应；记录不存在统一返回 null（控制器 → 404）。
 */
class CrmService extends AbstractCrudService
{
    /**
     * 合同状态流转图：0草稿 1待审批 2已审批 3执行中 4已完成 5已终止
     * from => [允许流转的 to 列表]
     */
    public const CONTRACT_STATUS_FLOW = [
        0 => [1],
        1 => [2, 0],
        2 => [3],
        3 => [4, 5],
        4 => [],
        5 => [],
    ];

    /**
     * 合同状态流转图（纯逻辑，可单测）
     *
     * @return array<int, int[]>
     */
    public function contractStatusFlow(): array
    {
        return self::CONTRACT_STATUS_FLOW;
    }

    /**
     * 合同状态流转校验（纯逻辑，可单测）
     */
    public function canTransitionContract(int $from, int $to): bool
    {
        return $this->canTransition($from, $to, self::CONTRACT_STATUS_FLOW);
    }

    /**
     * 合同状态流转：校验通过后更新状态并保存
     *
     * @return CrmContract|null 合同不存在返回 null
     * @throws \InvalidArgumentException 状态流转不允许时抛出
     */
    public function transitionContract(int $id, int $toStatus): ?CrmContract
    {
        $contract = CrmContract::find($id);
        if (!$contract) {
            return null;
        }

        $currentStatus = (int) $contract->status;
        if (!$this->canTransitionContract($currentStatus, $toStatus)) {
            throw new InvalidArgumentException("不允许从状态 {$currentStatus} 流转到 {$toStatus}");
        }

        $contract->status = $toStatus;
        $contract->save();

        return $contract;
    }

    /**
     * 报价转合同：复制报价及其明细到合同，并将报价状态置为已转合同(3)
     *
     * @return array{quotation: CrmQuotation, contract: CrmContract}
     */
    public function convertQuotationToContract(CrmQuotation $quotation, string $code, string $name, string $remark): array
    {
        DB::beginTransaction();
        try {
            $contract = new CrmContract();
            $contract->id = $this->generateId();
            $contract->code = $code !== '' ? $code : 'CT' . $this->generateId();
            $contract->name = $name !== '' ? $name : '合同-' . $quotation->code;
            $contract->customer_id = $quotation->customer_id;
            $contract->opportunity_id = $quotation->opportunity_id;
            $contract->quotation_id = $quotation->id;
            $contract->total_amount = $quotation->total_amount;
            $contract->status = 0;
            $contract->owner_user_id = $quotation->owner_user_id;
            $contract->remark = $remark;
            $contract->save();

            // 复制报价明细到合同明细
            $quotationItems = CrmQuotationItem::where('quotation_id', $quotation->id)->get();
            foreach ($quotationItems as $qItem) {
                $cItem = new CrmContractItem();
                $cItem->id = $this->generateId();
                $cItem->contract_id = $contract->id;
                $cItem->product_id = $qItem->product_id;
                $cItem->sku_id = $qItem->sku_id;
                $cItem->quantity = $qItem->quantity;
                $cItem->price = $qItem->price;
                $cItem->amount = $qItem->amount;
                $cItem->unit = $qItem->unit;
                $cItem->save();
            }

            $quotation->status = 3;
            $quotation->save();

            DB::commit();

            return ['quotation' => $quotation, 'contract' => $contract];
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 删除工单及其全部回复
     */
    public function deleteTicketWithReplies(int $id): bool
    {
        $ticket = CrmTicket::find($id);
        if (!$ticket) {
            return false;
        }
        $this->deleteWhere(CrmTicketReply::class, ['ticket_id' => $id]);

        return (bool) $ticket->delete();
    }

    /**
     * 删除营销活动及其全部参与记录
     */
    public function deleteCampaignWithParticipants(int $id): bool
    {
        $campaign = CrmCampaign::find($id);
        if (!$campaign) {
            return false;
        }
        $this->deleteWhere(CrmCampaignParticipant::class, ['campaign_id' => $id]);

        return (bool) $campaign->delete();
    }

    /**
     * 替换主表明细：先按外键清空旧明细，再批量插入新明细
     *
     * @param string $itemModel 明细模型类名
     * @param string $fkField   外键字段名（如 quotation_id）
     * @param array<int, array<string, mixed>> $items 明细数据（忽略每项中的 id 字段）
     * @return int 写入明细条数
     */
    public function replaceItems(string $itemModel, string $fkField, int $parentId, array $items): int
    {
        DB::beginTransaction();
        try {
            $this->deleteWhere($itemModel, [$fkField => $parentId]);

            $count = 0;
            foreach ($items as $itemData) {
                $detail = new $itemModel();
                $detail->id = $this->generateId();
                $detail->$fkField = $parentId;
                foreach ($itemData as $key => $value) {
                    if ($key !== 'id') {
                        $detail->$key = $value;
                    }
                }
                $detail->save();
                $count++;
            }

            DB::commit();

            return $count;
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 工单回复列表（按 id 升序，原始数组）
     *
     * @return array<int, array>
     */
    public function ticketReplies(int $ticketId): array
    {
        return CrmTicketReply::where('ticket_id', $ticketId)
            ->orderBy('id', 'asc')
            ->get()
            ->map(static fn ($item): array => $item->toArray())
            ->all();
    }

    /**
     * 活动参与记录列表（按 id 降序，原始数组）
     *
     * @return array<int, array>
     */
    public function campaignParticipants(int $campaignId): array
    {
        return CrmCampaignParticipant::where('campaign_id', $campaignId)
            ->orderBy('id', 'desc')
            ->get()
            ->map(static fn ($item): array => $item->toArray())
            ->all();
    }

    /**
     * 指派工单：设置处理人；待处理(0)工单指派后自动流转为处理中(1)
     *
     * @return CrmTicket|null 工单不存在返回 null
     */
    public function assignTicket(int $id, int $assigneeUserId): ?CrmTicket
    {
        $ticket = CrmTicket::find($id);
        if (!$ticket) {
            return null;
        }
        $ticket->assignee_user_id = $assigneeUserId;
        if ((int) $ticket->status === 0) {
            $ticket->status = 1;
        }
        $ticket->save();

        return $ticket;
    }

    /**
     * 解决工单：标记为已解决(2)并记录解决时间；content 非空时自动追加一条回复
     *
     * @return CrmTicket|null 工单不存在返回 null
     * @throws \InvalidArgumentException 工单已关闭(3)时抛出
     */
    public function resolveTicket(int $id, string $content, int $userId): ?CrmTicket
    {
        $ticket = CrmTicket::find($id);
        if (!$ticket) {
            return null;
        }
        if ((int) $ticket->status === 3) {
            throw new InvalidArgumentException('工单已关闭，无法解决');
        }

        $ticket->status = 2;
        $ticket->resolved_at = date('Y-m-d H:i:s');
        $ticket->save();

        if ($content !== '') {
            $this->addTicketReply($ticket->id, $userId, $content, 0);
        }

        return $ticket;
    }

    /**
     * 添加工单回复
     */
    public function addTicketReply(int $ticketId, int $userId, string $content, int $isInternal): CrmTicketReply
    {
        $reply = new CrmTicketReply();
        $reply->id = $this->generateId();
        $reply->ticket_id = $ticketId;
        $reply->user_id = $userId;
        $reply->content = $content;
        $reply->is_internal = $isInternal;
        $reply->save();

        return $reply;
    }

    /**
     * 公海池客户列表（status=0 或无归属人的客户）
     * 支持关键词（name/code）与客户等级过滤。
     *
     * @param array<string, mixed> $filters
     * @return array{list: array, total: int, page: int, limit: int}
     */
    public function poolCustomers(array $filters = [], int $page = 1, int $limit = 15): array
    {
        return $this->list(Customer::class, $filters, $page, $limit, [
            'baseOrWhere' => [['status', 0], ['owner_user_id', 0]],
            'searchFields' => ['name', 'code'],
            'eqFilters' => ['level_id'],
        ]);
    }

    /**
     * 从公海池领取客户：校验归属规则与领取上限后设置归属人，并记录领取流水
     *
     * @return Customer|null 客户不存在返回 null
     * @throws \InvalidArgumentException 客户不在公海池 / 超出领取上限时抛出
     */
    public function claimCustomer(int $customerId, int $adminId, string $remark): ?Customer
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return null;
        }
        if ($customer->status !== 0 && $customer->owner_user_id !== 0) {
            throw new InvalidArgumentException('该客户不在公海池中');
        }

        // 命中公海池规则时校验领取数量上限
        $rule = CrmPoolRule::where('level_id', $customer->level_id)
            ->where('enabled', 1)
            ->first();
        if ($rule) {
            $claimed = Customer::where('owner_user_id', $adminId)
                ->where('status', '>', 0)
                ->count();
            if ($claimed >= $rule->max_claims) {
                throw new InvalidArgumentException('已达到最大领取数量限制(' . $rule->max_claims . ')');
            }
        }

        $customer->owner_user_id = $adminId;
        $customer->status = 1;
        $customer->save();

        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $customerId;
        $record->action = 1;
        $record->from_user_id = 0;
        $record->to_user_id = $adminId;
        $record->remark = $remark;
        $record->save();

        return $customer;
    }

    /**
     * 释放客户到公海池：清空归属人并记录释放流水
     *
     * @return Customer|null 客户不存在返回 null
     */
    public function releaseCustomer(int $customerId, int $adminId, string $remark): ?Customer
    {
        $customer = Customer::find($customerId);
        if (!$customer) {
            return null;
        }

        $fromUserId = $customer->owner_user_id;
        $customer->owner_user_id = 0;
        $customer->status = 0;
        $customer->save();

        $record = new CrmPoolRecord();
        $record->id = $this->generateId();
        $record->customer_id = $customerId;
        $record->action = 2;
        $record->from_user_id = $fromUserId;
        $record->to_user_id = 0;
        $record->remark = $remark;
        $record->save();

        return $customer;
    }

    /**
     * 构建分析报表数据（纯逻辑，可单测）
     * 有数据源的指标从真实表聚合；无统计口径的指标返回 0，不生成模拟值冒充真实。
     */
    public function buildReportData(string $type, int $year, int $period, int $periodType): array
    {
        $periodLabel = match ($periodType) {
            2 => "{$year}年Q{$period}",
            3 => "{$year}年度",
            default => "{$year}年{$period}月",
        };
        [$from, $to] = $this->periodRange($year, $period, $periodType);

        $totalOrders = SalesOrder::whereBetween('ordered_at', [$from, $to])->count();
        $totalAmount = (float) SalesOrder::whereBetween('ordered_at', [$from, $to])->sum('total_amount');

        return match ($type) {
            'customer' => [
                'new_customers' => Customer::whereBetween('created_at', [$from, $to])->count(),
                'active_customers' => 0,
                'churn_customers' => 0,
                'retention_rate' => 0,
                'period' => $periodLabel,
            ],
            'order' => [
                'total_orders' => $totalOrders,
                'total_amount' => $totalAmount,
                'avg_order_value' => $totalOrders > 0 ? round($totalAmount / $totalOrders, 2) : 0,
                'period' => $periodLabel,
            ],
            'revenue' => [
                'total_revenue' => $totalAmount,
                'total_cost' => 0,
                'gross_profit' => 0,
                'gross_margin' => 0,
                'period' => $periodLabel,
            ],
            'activity' => [
                'total_campaigns' => CrmCampaign::whereBetween('created_at', [$from, $to])->count(),
                'total_participants' => CrmCampaignParticipant::whereBetween('created_at', [$from, $to])->count(),
                'conversion_count' => 0,
                'conversion_rate' => 0,
                'period' => $periodLabel,
            ],
            'retention' => [
                'cohort_size' => 0,
                'month1_retention' => 0,
                'month3_retention' => 0,
                'month6_retention' => 0,
                'period' => $periodLabel,
            ],
            default => ['period' => $periodLabel],
        };
    }

    /**
     * 期间起止日期边界：月=当月、季=季度首末月、年=全年
     */
    private function periodRange(int $year, int $period, int $periodType): array
    {
        if ($periodType === 3) {
            return ["{$year}-01-01", "{$year}-12-31"];
        }
        $startMonth = max(1, min(12, $periodType === 2 ? ($period - 1) * 3 + 1 : $period));
        $endMonth = $periodType === 2 ? min(12, $startMonth + 2) : $startMonth;

        return [
            sprintf('%04d-%02d-01', $year, $startMonth),
            date('Y-m-t', mktime(0, 0, 0, $endMonth, 1, $year)),
        ];
    }

    /**
     * 生成分析报表（字段显式赋值，与旧控制器 generate() 行为一致）
     */
    public function createAnalyticsReport(string $name, string $type, int $periodType, int $periodYear, int $periodValue, array $reportData): CrmAnalyticsReport
    {
        $report = new CrmAnalyticsReport();
        $report->id = $this->generateId();
        $report->name = $name;
        $report->type = $type;
        $report->period_type = $periodType;
        $report->period_year = $periodYear;
        $report->period_value = $periodValue;
        $report->report_data = json_encode($reportData, JSON_UNESCAPED_UNICODE);
        $report->generated_at = date('Y-m-d H:i:s');
        $report->save();

        return $report;
    }

    /**
     * 创建或更新分析指标：传 id 则更新（不存在返回 null），否则创建。
     * 请求字段经 CrmAnalyticsMetric::$fillable 过滤后持久化。
     *
     * @return CrmAnalyticsMetric|null
     */
    public function upsertMetric(?int $id, array $data): ?CrmAnalyticsMetric
    {
        if ($id !== null) {
            $metric = CrmAnalyticsMetric::find($id);
            if (!$metric) {
                return null;
            }
            $metric->fill($this->fillableOnly($metric, $data));
            $metric->save();

            return $metric;
        }

        return $this->create(CrmAnalyticsMetric::class, $data);
    }
}
