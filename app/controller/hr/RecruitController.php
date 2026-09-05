<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrCandidate;
use app\model\HrInterview;
use app\model\HrJob;
use app\model\HrOffer;
use app\service\hr\RecruitService;
use InvalidArgumentException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 招聘管理（H1：招聘漏斗）
 * 职位/候选人/面试/Offer 分组接口。候选人状态机唯一入口为 RecruitService
 * （状态推进/面试联动/Offer 锁定与回退），status 一律经动作接口变更，禁止直改。
 * 统一返回 {code,message,data}；Tag 见类注解。
 */
#[\erikwang2013\apidoc\annotation\Tag("人力资源")]
#[\erikwang2013\apidoc\annotation\Title("职位")]

class RecruitController extends BaseController
{
    // ---------- 职位（erp_hr_job，软删除） ----------

    #[\erikwang2013\apidoc\annotation\Title("职位列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/job")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1发布中2已关闭")]
#[\erikwang2013\apidoc\annotation\Param(name:"job_title", type:"string", desc:"职位名称（等值）")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $result = $this->recruit()->list(HrJob::class, [
            'status' => $request->input('status'),
            'job_title' => $request->input('job_title'),
        ], $page, $limit, [
            'eqFilters' => ['status'],
            'stringEqFilters' => ['job_title'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("新建职位")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/job")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"job_title", type:"string", desc:"职位名称，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"department_id", type:"int", desc:"部门ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"headcount", type:"int", desc:"招聘人数")]
#[\erikwang2013\apidoc\annotation\Param(name:"requirement", type:"string", desc:"任职要求")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'job_title' => 'required|string|max:100',
            'headcount' => 'integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $job = $this->recruit()->create(HrJob::class, $request->all(), ['status' => 0]);

        return $this->success($this->encodeIds($job->toArray()), '创建成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("职位详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobShow(Request $request, string $id): Response
    {
        $job = $this->recruit()->find(HrJob::class, $this->decodeId($id));
        if (!$job) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($job->toArray()));
    }

    #[\erikwang2013\apidoc\annotation\Title("更新职位")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Param(name:"job_title", type:"string", desc:"职位名称")]
#[\erikwang2013\apidoc\annotation\Param(name:"headcount", type:"int", desc:"招聘人数")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobUpdate(Request $request, string $id): Response
    {
        $job = $this->recruit()->update(HrJob::class, $this->decodeId($id), $request->all(), ['status']);
        if (!$job) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($job->toArray()), '更新成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("删除职位")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobDestroy(Request $request, string $id): Response
    {
        $job = $this->recruit()->find(HrJob::class, $this->decodeId($id));
        if (!$job) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->recruit()->delete(HrJob::class, (int) $job->id);

        return $this->success([], '删除成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("发布职位")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobPublish(Request $request, string $id): Response
    {
        try {
            $job = $this->recruit()->publishJob($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($job), '职位已发布');
    }

    #[\erikwang2013\apidoc\annotation\Title("关闭职位")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function jobClose(Request $request, string $id): Response
    {
        try {
            $job = $this->recruit()->closeJob($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($job), '职位已关闭');
    }

    // ---------- 候选人（erp_hr_candidate） ----------

    #[\erikwang2013\apidoc\annotation\Title("候选人列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/candidate")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰")]
#[\erikwang2013\apidoc\annotation\Param(name:"job_id", type:"int", desc:"职位ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"姓名（等值）")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $result = $this->recruit()->list(HrCandidate::class, [
            'status' => $request->input('status'),
            'job_id' => $request->input('job_id'),
            'name' => $request->input('name'),
        ], $page, $limit, [
            'eqFilters' => ['status', 'job_id'],
            'stringEqFilters' => ['name'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("新建候选人")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/candidate")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"name", type:"string", desc:"姓名，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"phone", type:"string", desc:"手机号")]
#[\erikwang2013\apidoc\annotation\Param(name:"source", type:"string", desc:"来源渠道")]
#[\erikwang2013\apidoc\annotation\Param(name:"job_id", type:"int", desc:"应聘职位ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"expected_salary", type:"float", desc:"期望薪资")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'name' => 'required|string|max:100',
            'job_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $candidate = $this->recruit()->submitCandidate($request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($candidate, ['id', 'job_id']), '创建成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("候选人详情")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateShow(Request $request, string $id): Response
    {
        $candidate = $this->recruit()->find(HrCandidate::class, $this->decodeId($id));
        if (!$candidate) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']));
    }

    #[\erikwang2013\apidoc\annotation\Title("更新候选人")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Param(name:"phone", type:"string", desc:"手机号")]
#[\erikwang2013\apidoc\annotation\Param(name:"source", type:"string", desc:"来源渠道")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateUpdate(Request $request, string $id): Response
    {
        $candidate = $this->recruit()->update(HrCandidate::class, $this->decodeId($id), $request->all(), ['status']);
        if (!$candidate) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']), '更新成功');
    }

    #[\erikwang2013\apidoc\annotation\Title("推进候选人状态")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"目标状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateAdvance(Request $request, string $id): Response
    {
        $validator = validator($request->all(), ['status' => 'required|integer|between:0,5']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $candidate = $this->recruit()->advanceCandidate($this->decodeId($id), (int) $request->input('status'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($candidate), '状态已更新');
    }

    #[\erikwang2013\apidoc\annotation\Title("删除候选人")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", desc:"管理员密码")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function candidateDestroy(Request $request, string $id): Response
    {
        $candidateId = $this->decodeId($id);
        if (!$this->recruit()->find(HrCandidate::class, $candidateId)) {
            return $this->fail('记录不存在', 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        try {
            $this->recruit()->destroyCandidate($candidateId);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success([], '删除成功');
    }

    // ---------- 面试（erp_hr_interview） ----------

    #[\erikwang2013\apidoc\annotation\Title("面试记录列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/interview")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"candidate_id", type:"int", desc:"候选人ID")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function interviewIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $result = $this->recruit()->list(HrInterview::class, [
            'candidate_id' => $request->input('candidate_id'),
        ], $page, $limit, [
            'eqFilters' => ['candidate_id'],
            'orderBy' => [['round_no', 'asc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("记录面试")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/interview")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"candidate_id", type:"int", desc:"候选人ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"interview_date", type:"string", desc:"面试日期 Y-m-d，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"result", type:"int", desc:"结果:0待定1通过2不通过")]
#[\erikwang2013\apidoc\annotation\Param(name:"round_no", type:"int", desc:"轮次，缺省自动取最大轮次+1")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function interviewStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'required|integer',
            'interview_date' => 'required|date_format:Y-m-d',
            'round_no' => 'integer|min:1',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $interview = $this->recruit()->recordInterview((int) $request->input('candidate_id'), $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($interview), '面试已记录');
    }

    #[\erikwang2013\apidoc\annotation\Title("变更面试结果")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Param(name:"result", type:"int", desc:"结果:1通过2不通过，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"comment", type:"string", desc:"评价")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function interviewUpdate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'result' => 'required|integer|between:1,2',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $interview = $this->recruit()->updateInterviewResult(
                $this->decodeId($id),
                (int) $request->input('result'),
                (string) $request->input('comment', '')
            );
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($interview), '结果已更新');
    }

    // ---------- Offer（erp_hr_offer） ----------

    #[\erikwang2013\apidoc\annotation\Title("Offer列表")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/offer")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"candidate_id", type:"int", desc:"候选人ID")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", desc:"状态:0草稿1已发出2已接受3已拒绝")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function offerIndex(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $result = $this->recruit()->list(HrOffer::class, [
            'candidate_id' => $request->input('candidate_id'),
            'status' => $request->input('status'),
        ], $page, $limit, [
            'eqFilters' => ['candidate_id', 'status'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title("发起Offer")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/offer")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Param(name:"candidate_id", type:"int", desc:"候选人ID，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"offered_salary", type:"float", desc:"Offer薪资，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"onboard_date", type:"string", desc:"入职日期 Y-m-d")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function offerStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'required|integer',
            'offered_salary' => 'required',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $offer = $this->recruit()->applyOffer((int) $request->input('candidate_id'), $request->all());
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 草稿已创建');
    }

    #[\erikwang2013\apidoc\annotation\Title("发出Offer")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function offerSend(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->sendOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 已发出');
    }

    #[\erikwang2013\apidoc\annotation\Title("接受Offer")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function offerAccept(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->acceptOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 已接受，候选人已入职');
    }

    #[\erikwang2013\apidoc\annotation\Title("拒绝Offer")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function offerReject(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->rejectOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 已拒绝，候选人回到面试中');
    }

    // ---------- 漏斗统计 ----------

    #[\erikwang2013\apidoc\annotation\Title("招聘漏斗统计")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/hr/recruit/funnel")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Param(name:"from", type:"string", desc:"开始日期 Y-m-d，必填")]
#[\erikwang2013\apidoc\annotation\Param(name:"to", type:"string", desc:"结束日期 Y-m-d，必填")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"业务数据")]

    public function funnel(Request $request): Response
    {
        $from = (string) $request->input('from', '');
        $to = (string) $request->input('to', '');

        try {
            $result = $this->recruit()->funnel($from, $to);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result);
    }

    private function recruit(): RecruitService
    {
        return Container::get(RecruitService::class);
    }
}
