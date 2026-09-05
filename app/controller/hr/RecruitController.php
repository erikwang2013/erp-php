<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use erikwang2013\apidoc\annotation as Apidoc;

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
 * @Apidoc\Tag("人力资源")
 */
class RecruitController extends BaseController
{
    // ---------- 职位（erp_hr_job，软删除） ----------

    /**
     * @Apidoc\Title("职位列表")
     * @Apidoc\Url("/admin/v1/hr/recruit/job")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="status", type="int", desc="状态:0草稿1发布中2已关闭")
     * @Apidoc\Param(name="job_title", type="string", desc="职位名称（等值）")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("新建职位")
     * @Apidoc\Url("/admin/v1/hr/recruit/job")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="job_title", type="string", desc="职位名称，必填")
     * @Apidoc\Param(name="department_id", type="int", desc="部门ID")
     * @Apidoc\Param(name="headcount", type="int", desc="招聘人数")
     * @Apidoc\Param(name="requirement", type="string", desc="任职要求")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("职位详情")
     * @Apidoc\Url("/admin/v1/hr/recruit/job/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function jobShow(Request $request, string $id): Response
    {
        $job = $this->recruit()->find(HrJob::class, $this->decodeId($id));
        if (!$job) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($job->toArray()));
    }

    /**
     * @Apidoc\Title("更新职位")
     * @Apidoc\Url("/admin/v1/hr/recruit/job/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="job_title", type="string", desc="职位名称")
     * @Apidoc\Param(name="headcount", type="int", desc="招聘人数")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function jobUpdate(Request $request, string $id): Response
    {
        $job = $this->recruit()->update(HrJob::class, $this->decodeId($id), $request->all(), ['status']);
        if (!$job) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($job->toArray()), '更新成功');
    }

    /**
     * @Apidoc\Title("删除职位")
     * @Apidoc\Url("/admin/v1/hr/recruit/job/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("发布职位")
     * @Apidoc\Url("/admin/v1/hr/recruit/job/{id}/publish")
     * @Apidoc\Method("POST")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function jobPublish(Request $request, string $id): Response
    {
        try {
            $job = $this->recruit()->publishJob($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($job), '职位已发布');
    }

    /**
     * @Apidoc\Title("关闭职位")
     * @Apidoc\Url("/admin/v1/hr/recruit/job/{id}/close")
     * @Apidoc\Method("POST")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("候选人列表")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="status", type="int", desc="状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰")
     * @Apidoc\Param(name="job_id", type="int", desc="职位ID")
     * @Apidoc\Param(name="name", type="string", desc="姓名（等值）")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("新建候选人")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="name", type="string", desc="姓名，必填")
     * @Apidoc\Param(name="phone", type="string", desc="手机号")
     * @Apidoc\Param(name="source", type="string", desc="来源渠道")
     * @Apidoc\Param(name="job_id", type="int", desc="应聘职位ID，必填")
     * @Apidoc\Param(name="expected_salary", type="float", desc="期望薪资")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("候选人详情")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function candidateShow(Request $request, string $id): Response
    {
        $candidate = $this->recruit()->find(HrCandidate::class, $this->decodeId($id));
        if (!$candidate) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']));
    }

    /**
     * @Apidoc\Title("更新候选人")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="phone", type="string", desc="手机号")
     * @Apidoc\Param(name="source", type="string", desc="来源渠道")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function candidateUpdate(Request $request, string $id): Response
    {
        $candidate = $this->recruit()->update(HrCandidate::class, $this->decodeId($id), $request->all(), ['status']);
        if (!$candidate) {
            return $this->fail('记录不存在', 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']), '更新成功');
    }

    /**
     * @Apidoc\Title("推进候选人状态")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate/{id}/advance")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="status", type="int", desc="目标状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("删除候选人")
     * @Apidoc\Url("/admin/v1/hr/recruit/candidate/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("面试记录列表")
     * @Apidoc\Url("/admin/v1/hr/recruit/interview")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="candidate_id", type="int", desc="候选人ID")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("记录面试")
     * @Apidoc\Url("/admin/v1/hr/recruit/interview")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="candidate_id", type="int", desc="候选人ID，必填")
     * @Apidoc\Param(name="interview_date", type="string", desc="面试日期 Y-m-d，必填")
     * @Apidoc\Param(name="result", type="int", desc="结果:0待定1通过2不通过")
     * @Apidoc\Param(name="round_no", type="int", desc="轮次，缺省自动取最大轮次+1")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("变更面试结果")
     * @Apidoc\Url("/admin/v1/hr/recruit/interview/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Param(name="result", type="int", desc="结果:1通过2不通过，必填")
     * @Apidoc\Param(name="comment", type="string", desc="评价")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("Offer列表")
     * @Apidoc\Url("/admin/v1/hr/recruit/offer")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="candidate_id", type="int", desc="候选人ID")
     * @Apidoc\Param(name="status", type="int", desc="状态:0草稿1已发出2已接受3已拒绝")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("发起Offer")
     * @Apidoc\Url("/admin/v1/hr/recruit/offer")
     * @Apidoc\Method("POST")
     * @Apidoc\Param(name="candidate_id", type="int", desc="候选人ID，必填")
     * @Apidoc\Param(name="offered_salary", type="float", desc="Offer薪资，必填")
     * @Apidoc\Param(name="onboard_date", type="string", desc="入职日期 Y-m-d")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("发出Offer")
     * @Apidoc\Url("/admin/v1/hr/recruit/offer/{id}/send")
     * @Apidoc\Method("POST")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function offerSend(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->sendOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 已发出');
    }

    /**
     * @Apidoc\Title("接受Offer")
     * @Apidoc\Url("/admin/v1/hr/recruit/offer/{id}/accept")
     * @Apidoc\Method("POST")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
    public function offerAccept(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->acceptOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), 'Offer 已接受，候选人已入职');
    }

    /**
     * @Apidoc\Title("拒绝Offer")
     * @Apidoc\Url("/admin/v1/hr/recruit/offer/{id}/reject")
     * @Apidoc\Method("POST")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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

    /**
     * @Apidoc\Title("招聘漏斗统计")
     * @Apidoc\Url("/admin/v1/hr/recruit/funnel")
     * @Apidoc\Method("GET")
     * @Apidoc\Param(name="from", type="string", desc="开始日期 Y-m-d，必填")
     * @Apidoc\Param(name="to", type="string", desc="结束日期 Y-m-d，必填")
     * @Apidoc\Returned("data", type="object", desc="业务数据")
     */
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
