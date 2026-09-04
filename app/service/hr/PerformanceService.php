<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrKpiTemplate;
use app\model\HrKpiTemplateItem;
use app\model\HrPerfPlan;
use app\model\HrPerfScore;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * 绩效考核服务（H2：KPI 模板 + 评分流程）
 *
 * 状态机：
 *   KPI 模板（erp_hr_kpi_template.status）：0 草稿 / 1 启用
 *     - 创建即草稿；templateEnable 启用前校验：指标数 ≥ 1 且权重合计 = 100.00；
 *     - 模板指标项「整存整替」（参照 WorkflowDesignerService::save()：先删后插，
 *       DB::transaction 包裹）；启用后的模板禁止再替换指标（防止已建批次口径漂移，
 *       名称/周期类型仍可改）；仅草稿模板可调整指标项；
 *     - 模板被考核批次引用后不可删除。
 *   考核批次（erp_hr_perf_plan.status）：0 草稿 / 1 进行中 / 2 已归档
 *     - createPlan 仅允许引用「已启用」模板；
 *     - startPlan：0 → 1；archivePlan：1 → 2，且要求至少存在一条评分记录
 *       （部分评分亦允许归档——归档语义=冻结考核期，非全员完成）；
 *     - submitScore 仅允许处于 1（进行中）的批次。
 *
 * 评分口径（全部 bcmath）：
 *   - 评分提交按唯一键 (plan_id, employee_id, rater_id, indicator) 覆盖更新
 *     （同人同指标重复提交 = 改分）；indicator+rater_type 必须命中模板指标项，
 *     否则拒绝（阻止脱离模板的评分污染）；
 *   - summary() 多评分人语义：同 (indicator, rater_type) 分组先同类平均（scale 6），
 *     再按模板权重加权：总分 = Σ(同类平均分 × 权重%)；仅匹配到模板指标的评分组
 *     参与计分；未评分指标不计入（rated_weight 明示已计权重合计，可为 <100）；
 *   - 分数/权重 DECIMAL(5,2)，得分限 0.00~100.00；数值一律 bcmath 运算，
 *     对外输出 'xx.xx' 两位小数字符串。
 */
class PerformanceService extends AbstractCrudService
{
    public const PLAN_STATUS_TEXT = [0 => '草稿', 1 => '进行中', 2 => '已归档'];

    private const PERIOD_TYPES = ['monthly', 'quarterly', 'yearly'];
    private const RATER_TYPES = [1, 2, 3];
    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';
    private const WEIGHT_PATTERN = '/^(?:\d{1,3})(?:\.\d{1,2})?$/';
    private const SCORE_PATTERN = '/^(?:\d{1,3})(?:\.\d{1,2})?$/';

    // ---------- 模板 ----------

    /** 校验指标项形状并返回规范化数组；items 为空数组合法（空草稿模板）。 */
    public function normalizeItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $indicator = trim((string) ($item['indicator'] ?? ''));
            if ($indicator === '' || mb_strlen($indicator) > 100) {
                throw new InvalidArgumentException('指标名称必填且不能超过 100 字');
            }
            $weight = trim((string) ($item['weight'] ?? ''));
            if (preg_match(self::WEIGHT_PATTERN, $weight) !== 1) {
                throw new InvalidArgumentException('指标权重不合法（须为 0.01~100.00 之间，最多 2 位小数）');
            }
            if (bccomp($weight, '0.00', 2) <= 0 || bccomp($weight, '100.00', 2) > 0) {
                throw new InvalidArgumentException('指标权重须在 0.01~100.00 之间');
            }
            $raterType = (int) ($item['rater_type'] ?? 0);
            if (!in_array($raterType, self::RATER_TYPES, true)) {
                throw new InvalidArgumentException('评分人类型不合法（1自评/2上级/3同事360）');
            }
            $normalized[] = [
                'indicator' => $indicator,
                'weight' => $weight,
                'target_value' => mb_substr(trim((string) ($item['target_value'] ?? '')), 0, 100),
                'rater_type' => $raterType,
                'sort' => max(0, (int) ($item['sort'] ?? 0)),
            ];
        }

        return $normalized;
    }

    /** 权重合计（bcadd scale=2，返回两位小数字符串，如 '100.00'）。 */
    public function itemsWeightTotal(array $items): string
    {
        $total = '0.00';
        foreach ($items as $item) {
            $total = bcadd($total, (string) $item['weight'], 2);
        }

        return $total;
    }

    /** 新建模板（草稿态，含指标项整存整替）。$data: name(必填) period_type?(默认 monthly) items?(可选) */
    public function templateStore(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new InvalidArgumentException('模板名称必填且不能超过 100 字');
        }
        $periodType = (string) ($data['period_type'] ?? 'monthly');
        if (!in_array($periodType, self::PERIOD_TYPES, true)) {
            throw new InvalidArgumentException('考核周期类型不合法（monthly/quarterly/yearly）');
        }
        $items = $this->normalizeItems(isset($data['items']) && is_array($data['items']) ? $data['items'] : []);
        if ($items !== []) {
            $this->assertWeightTotal($items);
        }

        $template = null;
        DB::transaction(function () use ($name, $periodType, $items, &$template): void {
            $template = new HrKpiTemplate();
            $template->id = $this->generateId();
            $template->name = $name;
            $template->period_type = $periodType;
            $template->status = 0;
            $template->save();
            $this->replaceItems((int) $template->id, $items);
        });

        return $this->templateShow((int) $template->id);
    }

    /**
     * 更新模板：名称/周期类型随时可改；items 仅在草稿态下允许整存整替。
     */
    public function templateUpdate(int $templateId, array $data): array
    {
        $template = HrKpiTemplate::find($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('模板不存在');
        }
        if (isset($data['name'])) {
            $name = trim((string) $data['name']);
            if ($name === '' || mb_strlen($name) > 100) {
                throw new InvalidArgumentException('模板名称必填且不能超过 100 字');
            }
            $template->name = $name;
        }
        if (isset($data['period_type'])) {
            if (!in_array((string) $data['period_type'], self::PERIOD_TYPES, true)) {
                throw new InvalidArgumentException('考核周期类型不合法（monthly/quarterly/yearly）');
            }
            $template->period_type = (string) $data['period_type'];
        }
        if (array_key_exists('items', $data)) {
            if ((int) $template->status !== 0) {
                throw new InvalidArgumentException('模板已启用，不可修改指标项（如需调整请新建模板）');
            }
            $items = $this->normalizeItems(is_array($data['items']) ? $data['items'] : []);
            if ($items !== []) {
                $this->assertWeightTotal($items);
            }
            DB::transaction(function () use ($template, $items): void {
                $this->replaceItems((int) $template->id, $items);
            });
        }
        $template->save();

        return $this->templateShow($templateId);
    }

    /** 启用模板：草稿(0) → 启用(1)；校验指标 ≥ 1 且权重合计 = 100.00。 */
    public function templateEnable(int $templateId): array
    {
        $template = HrKpiTemplate::find($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('模板不存在');
        }
        if ((int) $template->status === 1) {
            throw new InvalidArgumentException('模板已启用，请勿重复操作');
        }
        $items = $this->templateItems($templateId);
        if ($items === []) {
            throw new InvalidArgumentException('模板未配置指标，不可启用');
        }
        $this->assertWeightTotal($items);

        $template->status = 1;
        $template->save();

        return $this->templateShow($templateId);
    }

    /** 模板详情（含按 sort 升序的指标项）。 */
    public function templateShow(int $templateId): array
    {
        $template = HrKpiTemplate::find($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('模板不存在');
        }
        $payload = $template->toArray();
        $payload['items'] = $this->templateItems($templateId);

        return $payload;
    }

    /** 删除模板（含指标项）。模板已被考核批次引用时禁止删除。 */
    public function templateDestroy(int $templateId): bool
    {
        $template = HrKpiTemplate::find($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('模板不存在');
        }
        if (HrPerfPlan::where('template_id', $templateId)->exists()) {
            throw new InvalidArgumentException('模板已被考核批次引用，不可删除');
        }
        $deleted = false;
        DB::transaction(function () use ($templateId, &$deleted): void {
            HrKpiTemplateItem::where('template_id', $templateId)->delete();
            $deleted = (bool) HrKpiTemplate::where('id', $templateId)->delete();
        });

        return $deleted;
    }

    // ---------- 考核批次 ----------

    /** 创建考核批次（草稿 0），仅允许引用已启用模板。 */
    public function createPlan(array $data): array
    {
        $templateId = (int) ($data['template_id'] ?? 0);
        $template = HrKpiTemplate::find($templateId);
        if ($template === null) {
            throw new InvalidArgumentException('模板不存在');
        }
        if ((int) $template->status !== 1) {
            throw new InvalidArgumentException('模板未启用，不可基于草稿模板创建考核批次');
        }
        $periodStart = trim((string) ($data['period_start'] ?? ''));
        $periodEnd = trim((string) ($data['period_end'] ?? ''));
        if (preg_match(self::DATE_PATTERN, $periodStart) !== 1 || preg_match(self::DATE_PATTERN, $periodEnd) !== 1) {
            throw new InvalidArgumentException('考核周期起止日期格式应为 Y-m-d');
        }
        if (strcmp($periodStart, $periodEnd) > 0) {
            throw new InvalidArgumentException('考核周期结束日期不能早于开始日期');
        }

        $plan = new HrPerfPlan();
        $plan->id = $this->generateId();
        $plan->template_id = $templateId;
        $plan->period_start = $periodStart;
        $plan->period_end = $periodEnd;
        $plan->status = 0;
        $plan->created_by = (int) ($data['created_by'] ?? 0);
        $plan->save();

        return $plan->toArray();
    }

    /** 启动批次：草稿(0) → 进行中(1)。 */
    public function startPlan(int $planId): array
    {
        $plan = HrPerfPlan::find($planId);
        if ($plan === null) {
            throw new InvalidArgumentException('考核批次不存在');
        }
        if ((int) $plan->status !== 0) {
            throw new InvalidArgumentException(sprintf(
                '仅草稿状态的考核批次可启动，当前状态：%s',
                self::PLAN_STATUS_TEXT[(int) $plan->status] ?? (string) $plan->status
            ));
        }
        $plan->status = 1;
        $plan->save();

        return $plan->toArray();
    }

    /**
     * 归档批次：进行中(1) → 已归档(2)。要求至少存在一条评分记录；
     * 部分评分亦允许归档（归档语义=冻结考核期，非全员完成）。
     */
    public function archivePlan(int $planId): array
    {
        $plan = HrPerfPlan::find($planId);
        if ($plan === null) {
            throw new InvalidArgumentException('考核批次不存在');
        }
        if ((int) $plan->status !== 1) {
            throw new InvalidArgumentException(sprintf(
                '仅进行中状态的考核批次可归档，当前状态：%s',
                self::PLAN_STATUS_TEXT[(int) $plan->status] ?? (string) $plan->status
            ));
        }
        if (!HrPerfScore::where('plan_id', $planId)->exists()) {
            throw new InvalidArgumentException('考核批次尚无评分记录，不可归档（至少需一条评分）');
        }
        $plan->status = 2;
        $plan->save();

        return $plan->toArray();
    }

    // ---------- 评分 ----------

    /** 提交/覆盖评分（仅进行中批次）。按 (plan_id,employee_id,rater_id,indicator) 覆盖更新。$scores: [{indicator, score, comment?}] */
    public function submitScore(int $planId, int $employeeId, int $raterId, int $raterType, array $scores): int
    {
        $plan = HrPerfPlan::find($planId);
        if ($plan === null) {
            throw new InvalidArgumentException('考核批次不存在');
        }
        if ((int) $plan->status !== 1) {
            throw new InvalidArgumentException(sprintf(
                '仅进行中状态的考核批次可提交评分，当前状态：%s',
                self::PLAN_STATUS_TEXT[(int) $plan->status] ?? (string) $plan->status
            ));
        }
        if (!in_array($raterType, self::RATER_TYPES, true)) {
            throw new InvalidArgumentException('评分人类型不合法（1自评/2上级/3同事360）');
        }
        if ($scores === []) {
            throw new InvalidArgumentException('评分数据不能为空');
        }
        // 模板指标索引：(indicator|rater_type) → 指标项，评分必须命中
        $items = HrKpiTemplateItem::where('template_id', (int) $plan->template_id)->get();
        $itemMap = [];
        foreach ($items as $item) {
            $itemMap[$item->indicator . '|' . (int) $item->rater_type] = $item;
        }

        $rows = [];
        foreach ($scores as $score) {
            $indicator = trim((string) ($score['indicator'] ?? ''));
            if ($indicator === '' || mb_strlen($indicator) > 100) {
                throw new InvalidArgumentException('指标名称必填且不能超过 100 字');
            }
            $key = $indicator . '|' . $raterType;
            if (!isset($itemMap[$key])) {
                throw new InvalidArgumentException(sprintf('指标「%s」不在模板中或评分人类型不符（%d）', $indicator, $raterType));
            }
            $scoreValue = trim((string) ($score['score'] ?? ''));
            if (preg_match(self::SCORE_PATTERN, $scoreValue) !== 1) {
                throw new InvalidArgumentException('得分不合法（须为 0.00~100.00，最多 2 位小数）');
            }
            if (bccomp($scoreValue, '100.00', 2) > 0) {
                throw new InvalidArgumentException('得分不能超过 100.00');
            }
            $comment = trim((string) ($score['comment'] ?? ''));
            if (mb_strlen($comment) > 500) {
                throw new InvalidArgumentException('评分评语不能超过 500 字');
            }
            $rows[] = [
                'indicator' => $indicator,
                'score' => $scoreValue,
                'comment' => $comment,
                'rater_type' => $raterType,
            ];
        }

        DB::transaction(function () use ($planId, $employeeId, $raterId, $rows): void {
            foreach ($rows as $row) {
                $existing = HrPerfScore::where('plan_id', $planId)
                    ->where('employee_id', $employeeId)
                    ->where('rater_id', $raterId)
                    ->where('indicator', $row['indicator'])
                    ->first();
                if ($existing !== null) {
                    $existing->rater_type = $row['rater_type'];
                    $existing->score = $row['score'];
                    $existing->comment = $row['comment'];
                    $existing->save();
                } else {
                    $scoreModel = new HrPerfScore();
                    $scoreModel->id = $this->generateId();
                    $scoreModel->plan_id = $planId;
                    $scoreModel->employee_id = $employeeId;
                    $scoreModel->rater_id = $raterId;
                    $scoreModel->rater_type = $row['rater_type'];
                    $scoreModel->indicator = $row['indicator'];
                    $scoreModel->score = $row['score'];
                    $scoreModel->comment = $row['comment'];
                    $scoreModel->save();
                }
            }
        });

        return count($rows);
    }

    /** 员工考核汇总：同类平均（scale 6）→ 按模板权重加权；无评分记录返回 null。口径详见类头注释。 */
    public function summary(int $planId, int $employeeId): ?array
    {
        $plan = HrPerfPlan::find($planId);
        if ($plan === null) {
            throw new InvalidArgumentException('考核批次不存在');
        }
        $scores = HrPerfScore::where('plan_id', $planId)
            ->where('employee_id', $employeeId)
            ->get();
        if ($scores->isEmpty()) {
            return null;
        }

        // 模板指标（按 sort 升序），构造 (indicator|rater_type) → item
        $items = HrKpiTemplateItem::where('template_id', (int) $plan->template_id)
            ->orderBy('sort')
            ->get();

        // 分组：同 (indicator, rater_type) 同类平均（scale 6）
        $groups = [];
        foreach ($scores as $row) {
            $key = $row->indicator . '|' . (int) $row->rater_type;
            if (!isset($groups[$key])) {
                $groups[$key] = ['sum' => '0.000000', 'count' => 0];
            }
            $groups[$key]['sum'] = bcadd($groups[$key]['sum'], bc_norm((string) $row->score), 6);
            $groups[$key]['count']++;
        }

        $details = [];
        $weightSum = '0.00';
        $total = '0.000000';
        foreach ($items as $item) {
            $key = $item->indicator . '|' . (int) $item->rater_type;
            if (!isset($groups[$key])) {
                continue; // 未评分指标不计入
            }
            $avg = bcdiv($groups[$key]['sum'], (string) $groups[$key]['count'], 6);
            $weight = bc_norm((string) $item->weight);
            $weightSum = bcadd($weightSum, $weight, 2);
            $total = bcadd($total, bcdiv(bcmul($avg, $weight, 6), '100', 6), 6);
            $details[] = [
                'indicator' => (string) $item->indicator,
                'rater_type' => (int) $item->rater_type,
                'weight' => $weight,
                'raters' => $groups[$key]['count'],
                'avg_score' => bc_round($avg, 2),
            ];
        }

        return [
            'plan_id' => $planId,
            'employee_id' => $employeeId,
            'items' => $details,
            'rated_weight' => $weightSum,
            'total' => bc_round($total, 2),
        ];
    }

    // ---------- 内部辅助 ----------

    /** 整存整替模板指标项（调用方负责事务与草稿态校验）。 */
    private function replaceItems(int $templateId, array $items): void
    {
        HrKpiTemplateItem::where('template_id', $templateId)->delete();
        foreach ($items as $item) {
            $row = new HrKpiTemplateItem();
            $row->id = $this->generateId();
            $row->template_id = $templateId;
            $row->indicator = $item['indicator'];
            $row->weight = $item['weight'];
            $row->target_value = $item['target_value'];
            $row->rater_type = $item['rater_type'];
            $row->sort = $item['sort'];
            $row->save();
        }
    }

    /** 模板指标项（sort 升序），返回规范数组。 */
    private function templateItems(int $templateId): array
    {
        return HrKpiTemplateItem::where('template_id', $templateId)
            ->orderBy('sort')
            ->get()
            ->map(static function (HrKpiTemplateItem $item): array {
                return [
                    'id' => (int) $item->id,
                    'indicator' => (string) $item->indicator,
                    'weight' => bc_norm((string) $item->weight),
                    'target_value' => (string) $item->target_value,
                    'rater_type' => (int) $item->rater_type,
                    'sort' => (int) $item->sort,
                ];
            })
            ->values()
            ->all();
    }

    /** 权重合计 = 100.00 校验（bcadd scale=2 判定）。 */
    private function assertWeightTotal(array $items): void
    {
        $total = $this->itemsWeightTotal($items);
        if (bccomp($total, '100.00', 2) !== 0) {
            throw new InvalidArgumentException('模板指标权重合计须为 100.00，当前合计：' . $total);
        }
    }
}
