<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\service\hr;

use app\model\HrCandidate;
use app\model\HrInterview;
use app\model\HrOffer;
use app\service\AbstractCrudService;
use Illuminate\Database\Capsule\Manager as DB;
use InvalidArgumentException;

/**
 * 招聘漏斗服务（H1）
 *
 * 候选人状态机（erp_hr_candidate.status）：
 *   0 新简历 → 1 初筛通过 → 2 面试中 → 3 已发Offer → 4 已入职
 *   任意非 5 状态可直接推进到 5（已淘汰）；其余仅支持逐级推进，越级/回退/同态
 *   一律拒绝并抛出带明确中文说明的 InvalidArgumentException。
 *
 * 联动约束（本类为候选人状态变更的唯一入口，外部不得直改 status）：
 *  - recordInterview：候选人须处于 1/2；写入成功后联动候选人为 2（面试中）。
 *    result=通过(1) 仅允许候选人已处于 2（首轮面试不允许初筛直判通过）；
 *    result=待定(0)/不通过(2) 不阻断联动（不通过≠淘汰，淘汰须显式推进到 5）。
 *  - updateInterviewResult：仅候选人处于 2 时可回填/变更面试结果（1通过/2不通过）。
 *  - applyOffer：仅候选人处于 2 时创建 Offer 草稿并锁定候选人 → 3。
 *  - sendOffer：草稿(0) → 已发出(1)，候选人须仍处于 3。
 *  - acceptOffer：offer 1→2（已接受），候选人 3→4（已入职）。
 *  - rejectOffer：offer 1→3（已拒绝），候选人 3→2（回到面试中，可再面/再Offer）。
 *  - destroyCandidate：状态 3/4 的候选人不可删除。
 *
 * funnel() 漏斗口径（快照统计，bcmath）：
 *   候选人群组 = created_at ∈ [from, to]（含边界，含期间已淘汰者）；
 *   stage_counts 为「查询时点」状态分布；
 *   stage_reached 为期间候选人在「库内可回溯」口径下曾到达各里程碑的人数：
 *     有 erp_hr_interview 行 ⇒ 到达过面试里程碑；有 erp_hr_offer 行 ⇒ 到达过
 *     Offer 里程碑（凡能产生面试/Offer 事件者必已过初筛，故一并计入初筛里程碑）。
 *     淘汰(5)前已过初筛但从未产生任何面试/Offer 事件的候选人无法回溯区分
 *     （0→5 与 1→5 事件等价），按保守口径不计入任何 stage_reached。
 *   rates 为逐级转化率（%），分母为 0 时对应值为 null；字符串一律 'xx.xx'。
 */
class RecruitService extends AbstractCrudService
{
    /** 候选人状态中文名（异常消息用） */
    public const CANDIDATE_STATUS_TEXT = [0 => '新简历', 1 => '初筛通过', 2 => '面试中', 3 => '已发Offer', 4 => '已入职', 5 => '已淘汰'];

    /** Offer 状态中文名（异常消息用） */
    public const OFFER_STATUS_TEXT = [0 => '草稿', 1 => '已发出', 2 => '已接受', 3 => '已拒绝'];

    private const DATE_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    /** 状态机判定：逐级推进 0→1→2→3→4，或任意非 5 状态淘汰至 5 */
    public function canAdvanceCandidateStatus(int $from, int $to): bool
    {
        return ($to === 5 && $from !== 5) || ($to === $from + 1 && $from < 4);
    }

    /**
     * 推进候选人状态（唯一入口，controller 校验后调用）。
     */
    public function advanceCandidate(int $candidateId, int $to): array
    {
        $candidate = HrCandidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('候选人不存在');
        }
        $from = (int) $candidate->status;
        if ($to < 0 || $to > 5) {
            throw new InvalidArgumentException('目标状态不合法（0新简历/1初筛通过/2面试中/3已发Offer/4已入职/5已淘汰）');
        }
        if (!$this->canAdvanceCandidateStatus($from, $to)) {
            throw new InvalidArgumentException(sprintf(
                '候选人状态不允许从 %s(%d) 推进到 %s(%d)：仅支持逐级推进 0→1→2→3→4，或任意状态淘汰至 5',
                self::CANDIDATE_STATUS_TEXT[$from] ?? (string) $from,
                $from,
                self::CANDIDATE_STATUS_TEXT[$to] ?? (string) $to,
                $to
            ));
        }
        $candidate->status = $to;
        $candidate->save();

        return $candidate->toArray();
    }

    /**
     * 记录面试并联动候选人进入面试中（2）。
     * $data: round_no?(缺省=已有最大轮次+1) interviewer_id? interview_date(必填) result?(0-2) comment?
     */
    public function recordInterview(int $candidateId, array $data): array
    {
        $candidate = HrCandidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('候选人不存在');
        }
        $status = (int) $candidate->status;
        if ($status !== 1 && $status !== 2) {
            throw new InvalidArgumentException(sprintf(
                '仅初筛通过/面试中的候选人可记录面试，当前状态：%s',
                self::CANDIDATE_STATUS_TEXT[$status] ?? (string) $status
            ));
        }
        $result = (int) ($data['result'] ?? 0);
        if (!in_array($result, [0, 1, 2], true)) {
            throw new InvalidArgumentException('面试结果不合法（0待定/1通过/2不通过）');
        }
        if ($result === 1 && $status === 1) {
            throw new InvalidArgumentException('候选人尚未进入面试中，本轮不得判定通过：请先以「待定」记录首轮面试联动进入面试中，再回填结果');
        }
        $interviewDate = trim((string) ($data['interview_date'] ?? ''));
        if (preg_match(self::DATE_PATTERN, $interviewDate) !== 1) {
            throw new InvalidArgumentException('面试日期格式应为 Y-m-d');
        }
        $comment = trim((string) ($data['comment'] ?? ''));
        if (mb_strlen($comment) > 500) {
            throw new InvalidArgumentException('面试评价不能超过 500 字');
        }
        $roundNo = isset($data['round_no']) && (int) $data['round_no'] > 0
            ? (int) $data['round_no']
            : (int) (HrInterview::where('candidate_id', $candidateId)->max('round_no') ?? 0) + 1;

        $interview = new HrInterview();
        $interview->id = $this->generateId();
        $interview->candidate_id = $candidateId;
        $interview->round_no = $roundNo;
        $interview->interviewer_id = (int) ($data['interviewer_id'] ?? 0);
        $interview->interview_date = $interviewDate;
        $interview->result = $result;
        $interview->comment = $comment;
        $interview->save();

        if ($status !== 2) {
            $candidate->status = 2;
            $candidate->save();
        }

        $payload = $interview->toArray();
        $payload['candidate_status'] = (int) $candidate->status;

        return $payload;
    }

    /**
     * 回填/变更面试结果（1通过/2不通过）。仅候选人处于 2（面试中）时可变更。
     */
    public function updateInterviewResult(int $interviewId, int $result, string $comment = ''): array
    {
        if (!in_array($result, [1, 2], true)) {
            throw new InvalidArgumentException('面试结果不合法（1通过/2不通过）');
        }
        $interview = HrInterview::find($interviewId);
        if ($interview === null) {
            throw new InvalidArgumentException('面试记录不存在');
        }
        $candidate = HrCandidate::find((int) $interview->candidate_id);
        if ($candidate === null) {
            throw new InvalidArgumentException('候选人不存在');
        }
        $status = (int) $candidate->status;
        if ($status !== 2) {
            throw new InvalidArgumentException(sprintf(
                '候选人当前不在面试中（%s），面试结果不可变更',
                self::CANDIDATE_STATUS_TEXT[$status] ?? (string) $status
            ));
        }
        $comment = trim($comment);
        if (mb_strlen($comment) > 500) {
            throw new InvalidArgumentException('面试评价不能超过 500 字');
        }
        $interview->result = $result;
        $interview->comment = $comment;
        $interview->save();

        return $interview->toArray();
    }

    /**
     * 发起 Offer：候选人须处于 2（面试中），创建 Offer 草稿(0)并锁定候选人 → 3。
     * $data: offered_salary(必填 DECIMAL(12,2)) onboard_date?(Y-m-d)
     */
    public function applyOffer(int $candidateId, array $data): array
    {
        $candidate = HrCandidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('候选人不存在');
        }
        $status = (int) $candidate->status;
        if ($status !== 2) {
            throw new InvalidArgumentException(sprintf(
                '仅面试中的候选人可发起 Offer，当前状态：%s',
                self::CANDIDATE_STATUS_TEXT[$status] ?? (string) $status
            ));
        }
        $salary = (string) ($data['offered_salary'] ?? '');
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $salary) !== 1) {
            throw new InvalidArgumentException('Offer 薪资不合法（须为 0.00~9999999999.99，最多 2 位小数）');
        }
        $onboardDate = isset($data['onboard_date']) ? trim((string) $data['onboard_date']) : '';
        if ($onboardDate !== '' && preg_match(self::DATE_PATTERN, $onboardDate) !== 1) {
            throw new InvalidArgumentException('入职日期格式应为 Y-m-d');
        }

        $offer = new HrOffer();
        $offer->id = $this->generateId();
        $offer->candidate_id = $candidateId;
        $offer->offered_salary = $salary;
        $offer->onboard_date = $onboardDate === '' ? null : $onboardDate;
        $offer->status = 0;

        DB::transaction(function () use ($offer, $candidate): void {
            $offer->save();
            $candidate->status = 3;
            $candidate->save();
        });

        $payload = $offer->toArray();
        $payload['candidate_status'] = 3;

        return $payload;
    }

    /** 发出 Offer：草稿(0) → 已发出(1)，候选人须仍处于 3（Offer 锁定）。 */
    public function sendOffer(int $offerId): array
    {
        $offer = HrOffer::find($offerId);
        if ($offer === null) {
            throw new InvalidArgumentException('Offer 不存在');
        }
        if ((int) $offer->status !== 0) {
            throw new InvalidArgumentException(sprintf(
                '仅草稿状态的 Offer 可发出，当前状态：%s',
                self::OFFER_STATUS_TEXT[(int) $offer->status] ?? (string) $offer->status
            ));
        }
        $candidate = HrCandidate::find((int) $offer->candidate_id);
        if ($candidate === null || (int) $candidate->status !== 3) {
            throw new InvalidArgumentException('候选人已不在 Offer 锁定状态，无法发出该 Offer');
        }
        $offer->status = 1;
        $offer->save();

        return $offer->toArray();
    }

    /** 接受 Offer：offer 1→2（已接受），候选人 3→4（已入职）。 */
    public function acceptOffer(int $offerId): array
    {
        $offer = HrOffer::find($offerId);
        if ($offer === null) {
            throw new InvalidArgumentException('Offer 不存在');
        }
        if ((int) $offer->status !== 1) {
            throw new InvalidArgumentException(sprintf(
                '仅已发出的 Offer 可接受，当前状态：%s',
                self::OFFER_STATUS_TEXT[(int) $offer->status] ?? (string) $offer->status
            ));
        }
        $candidate = HrCandidate::find((int) $offer->candidate_id);
        if ($candidate === null || (int) $candidate->status !== 3) {
            throw new InvalidArgumentException('候选人不在 Offer 锁定状态（状态已变更），无法接受该 Offer');
        }

        DB::transaction(function () use ($offer, $candidate): void {
            $offer->status = 2;
            $offer->save();
            $candidate->status = 4;
            $candidate->save();
        });

        $payload = $offer->toArray();
        $payload['candidate_status'] = 4;

        return $payload;
    }

    /** 拒绝 Offer：offer 1→3（已拒绝），候选人 3→2（回到面试中，可再次推进/再 Offer）。 */
    public function rejectOffer(int $offerId): array
    {
        $offer = HrOffer::find($offerId);
        if ($offer === null) {
            throw new InvalidArgumentException('Offer 不存在');
        }
        if ((int) $offer->status !== 1) {
            throw new InvalidArgumentException(sprintf(
                '仅已发出的 Offer 可拒绝，当前状态：%s',
                self::OFFER_STATUS_TEXT[(int) $offer->status] ?? (string) $offer->status
            ));
        }
        $candidate = HrCandidate::find((int) $offer->candidate_id);
        if ($candidate === null || (int) $candidate->status !== 3) {
            throw new InvalidArgumentException('候选人不在 Offer 锁定状态（状态已变更），无法拒绝该 Offer');
        }

        DB::transaction(function () use ($offer, $candidate): void {
            $offer->status = 3;
            $offer->save();
            $candidate->status = 2;
            $candidate->save();
        });

        $payload = $offer->toArray();
        $payload['candidate_status'] = 2;

        return $payload;
    }

    /** 删除候选人（物理删除）。状态 3（已发Offer）/4（已入职）的候选人不可删除。 */
    public function destroyCandidate(int $candidateId): bool
    {
        $candidate = HrCandidate::find($candidateId);
        if ($candidate === null) {
            throw new InvalidArgumentException('候选人不存在');
        }
        $status = (int) $candidate->status;
        if ($status === 3 || $status === 4) {
            throw new InvalidArgumentException(sprintf(
                '候选人已进入 %s 阶段，不可删除',
                self::CANDIDATE_STATUS_TEXT[$status] ?? (string) $status
            ));
        }

        return (bool) $candidate->delete();
    }

    /**
     * 招聘漏斗统计（口径详见类头注释）。
     *
     * @return array{total:int,stage_counts:array<string,int>,stage_reached:array<string,int>,rates:array<string,string|null>}
     */
    public function funnel(string $from, string $to): array
    {
        if (preg_match(self::DATE_PATTERN, $from) !== 1 || preg_match(self::DATE_PATTERN, $to) !== 1) {
            throw new InvalidArgumentException('起止日期格式应为 Y-m-d');
        }
        if (strcmp($from, $to) > 0) {
            throw new InvalidArgumentException('结束日期不能早于开始日期');
        }

        $cohort = HrCandidate::whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->get(['id', 'status']);
        $total = $cohort->count();
        $counts = [0, 0, 0, 0, 0, 0];
        $eliminatedIds = [];
        foreach ($cohort as $row) {
            $status = (int) $row->status;
            $counts[$status] = ($counts[$status] ?? 0) + 1;
            if ($status === 5) {
                $eliminatedIds[] = (int) $row->id;
            }
        }

        $eliminatedWithInterview = 0;
        $eliminatedWithOffer = 0;
        if ($eliminatedIds !== []) {
            $eliminatedWithInterview = DB::table('erp_hr_interview')
                ->whereIn('candidate_id', $eliminatedIds)
                ->distinct('candidate_id')
                ->count('candidate_id');
            $eliminatedWithOffer = DB::table('erp_hr_offer')
                ->whereIn('candidate_id', $eliminatedIds)
                ->distinct('candidate_id')
                ->count('candidate_id');
        }

        // 曾到达各里程碑人数（含期间已被淘汰者，回溯口径见类头）
        $reachedScreening = $counts[1] + $counts[2] + $counts[3] + $counts[4]
            + $eliminatedWithInterview + $eliminatedWithOffer;
        $reachedInterview = $counts[2] + $counts[3] + $counts[4] + $eliminatedWithInterview;
        $reachedOffer = $counts[3] + $counts[4] + $eliminatedWithOffer;
        $reachedHired = $counts[4];

        return [
            'total' => $total,
            'stage_counts' => [
                'new' => $counts[0],
                'screening' => $counts[1],
                'interview' => $counts[2],
                'offered' => $counts[3],
                'hired' => $counts[4],
                'eliminated' => $counts[5],
            ],
            'stage_reached' => [
                'screening' => $reachedScreening,
                'interview' => $reachedInterview,
                'offer' => $reachedOffer,
                'hired' => $reachedHired,
            ],
            'rates' => [
                'new_to_screening' => $this->convertRate($reachedScreening, $total),
                'screening_to_interview' => $this->convertRate($reachedInterview, $reachedScreening),
                'interview_to_offer' => $this->convertRate($reachedOffer, $reachedInterview),
                'offer_to_hired' => $this->convertRate($reachedHired, $reachedOffer),
            ],
        ];
    }

    /** 转化率：numerator/denominator*100，保留两位（'xx.xx'）；分母为 0 返回 null。 */
    private function convertRate(int $numerator, int $denominator): ?string
    {
        if ($denominator === 0) {
            return null;
        }

        return bc_round(bcdiv(bcmul((string) $numerator, '100', 6), (string) $denominator, 6), 2);
    }
}
