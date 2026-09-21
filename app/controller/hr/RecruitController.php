<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\hr;

use app\admin\controller\BaseController;
use app\model\HrCandidate;
use app\model\HrDepartment;
use app\model\HrEmployee;
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
#[\erikwang2013\apidoc\annotation\Tag('人力资源')]
#[\erikwang2013\apidoc\annotation\Title('职位')]
#[\erikwang2013\apidoc\annotation\Group('人力资源')]

class RecruitController extends BaseController
{
    // ---------- 职位（erp_hr_job，软删除） ----------

    #[\erikwang2013\apidoc\annotation\Title('职位列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/job')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态:0草稿1发布中2已关闭')]
    #[\erikwang2013\apidoc\annotation\Param(name:'job_title', type:'string', desc:'职位名称（等值）')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobIndex(Request $request): Response
    {
        $validator = validator($request->all(), [
            'status' => 'integer',
            'job_title' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        $result = $this->recruit()->list(HrJob::class, [
            'status' => $request->input('status'),
            'job_title' => $request->input('job_title'),
        ], $page, $limit, [
            'eqFilters' => ['status'],
            'stringEqFilters' => ['job_title'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = $this->appendJobNames($result['list']);
        $list = array_map(fn ($row) => $this->encodeIds($row, ['id', 'department_id']), $list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title('新建职位')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/job')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'job_title', type:'string', desc:'职位名称，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'department_id', type:'int', desc:'部门ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'headcount', type:'int', desc:'招聘人数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'requirement', type:'string', desc:'任职要求')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'job_title' => 'required|string|max:100',
            'headcount' => 'integer|min:0',
            'department_id' => 'string',
            'requirement' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request, ['department_id' => '部门ID']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $job = $this->recruit()->create(HrJob::class, $data, ['status' => 0]);

        return $this->success($this->encodeIds($job->toArray()), $this->trans('Created successfully'));
    }

    #[\erikwang2013\apidoc\annotation\Title('职位详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobShow(Request $request, string $id): Response
    {
        $job = $this->recruit()->find(HrJob::class, $this->decodeId($id));
        if (!$job) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $rows = $this->appendJobNames([$job->toArray()]);

        return $this->success($this->encodeIds($rows[0], ['id', 'department_id']));
    }

    #[\erikwang2013\apidoc\annotation\Title('更新职位')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Param(name:'job_title', type:'string', desc:'职位名称')]
    #[\erikwang2013\apidoc\annotation\Param(name:'headcount', type:'int', desc:'招聘人数')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobUpdate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'job_title' => 'string',
            'headcount' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $data = $this->decodeForeignKeys($request, ['department_id' => '部门ID']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $job = $this->recruit()->update(HrJob::class, $this->decodeId($id), $data, ['status']);
        if (!$job) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($job->toArray()), $this->trans('Updated successfully'));
    }

    #[\erikwang2013\apidoc\annotation\Title('删除职位')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobDestroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'job_title' => 'string',
            'headcount' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $job = $this->recruit()->find(HrJob::class, $this->decodeId($id));
        if (!$job) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }

        $this->recruit()->delete(HrJob::class, (int) $job->id);

        return $this->success([], $this->trans('Deleted successfully'));
    }

    #[\erikwang2013\apidoc\annotation\Title('发布职位')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobPublish(Request $request, string $id): Response
    {
        try {
            $job = $this->recruit()->publishJob($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($job), $this->trans('Position published'));
    }

    #[\erikwang2013\apidoc\annotation\Title('关闭职位')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function jobClose(Request $request, string $id): Response
    {
        try {
            $job = $this->recruit()->closeJob($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($job), $this->trans('Position closed'));
    }

    // ---------- 候选人（erp_hr_candidate） ----------

    #[\erikwang2013\apidoc\annotation\Title('候选人列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/candidate')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰')]
    #[\erikwang2013\apidoc\annotation\Param(name:'job_id', type:'int', desc:'职位ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'姓名（等值）')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateIndex(Request $request): Response
    {
        $validator = validator($request->all(), [
            'status' => 'integer',
            'job_id' => 'string',
            'name' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        // 筛选值同源下发（职位下拉值为 hashid）：不解码会被 eqFilters 的 (int) 静默成 0 → 恒空列表
        $jobId = $request->input('job_id');
        if ($jobId !== null && $jobId !== '') {
            $jobId = $this->decodeFlexibleId($jobId);
            if ($jobId === null) {
                return $this->fail('职位ID' . $this->trans('Invalid'), 422);
            }
        }
        $result = $this->recruit()->list(HrCandidate::class, [
            'status' => $request->input('status'),
            'job_id' => $jobId,
            'name' => $request->input('name'),
        ], $page, $limit, [
            'eqFilters' => ['status', 'job_id'],
            'stringEqFilters' => ['name'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row, ['id', 'job_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title('新建候选人')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/candidate')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'name', type:'string', desc:'姓名，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'phone', type:'string', desc:'手机号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'source', type:'string', desc:'来源渠道')]
    #[\erikwang2013\apidoc\annotation\Param(name:'job_id', type:'int', desc:'应聘职位ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'expected_salary', type:'float', desc:'期望薪资')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            // name 真实列宽 VARCHAR(50)（erp_hr_candidate）：原 max:100 会放过超长串去撞 MySQL 1406
            'name' => 'required|string|max:50',
            'job_id' => 'required|string',
            'phone' => 'string',
            'source' => 'string',
            'expected_salary' => 'numeric',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request, ['job_id' => '职位ID']);
            $candidate = $this->recruit()->submitCandidate($data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($candidate, ['id', 'job_id']), $this->trans('Created successfully'));
    }

    #[\erikwang2013\apidoc\annotation\Title('候选人详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateShow(Request $request, string $id): Response
    {
        $candidate = $this->recruit()->find(HrCandidate::class, $this->decodeId($id));
        if (!$candidate) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']));
    }

    #[\erikwang2013\apidoc\annotation\Title('更新候选人')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Param(name:'phone', type:'string', desc:'手机号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'source', type:'string', desc:'来源渠道')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateUpdate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'phone' => 'string',
            'source' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        try {
            $data = $this->decodeForeignKeys($request, ['job_id' => '职位ID']);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        $candidate = $this->recruit()->update(HrCandidate::class, $this->decodeId($id), $data, ['status']);
        if (!$candidate) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        return $this->success($this->encodeIds($candidate->toArray(), ['id', 'job_id']), $this->trans('Updated successfully'));
    }

    #[\erikwang2013\apidoc\annotation\Title('推进候选人状态')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'目标状态:0新简历1初筛通过2面试中3已发Offer4已入职5已淘汰')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateAdvance(Request $request, string $id): Response
    {
        $validator = validator($request->all(), ['status' => 'required|integer|between:0,5', 'source' => 'string']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $candidate = $this->recruit()->advanceCandidate($this->decodeId($id), (int) $request->input('status'));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($candidate), $this->trans('Status updated'));
    }

    #[\erikwang2013\apidoc\annotation\Title('删除候选人')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', desc:'管理员密码')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function candidateDestroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $candidateId = $this->decodeId($id);
        if (!$this->recruit()->find(HrCandidate::class, $candidateId)) {
            return $this->fail($this->trans('Record not found'), 404);
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

        return $this->success([], $this->trans('Deleted successfully'));
    }

    // ---------- 面试（erp_hr_interview） ----------

    #[\erikwang2013\apidoc\annotation\Title('面试记录列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/interview')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'candidate_id', type:'int', desc:'候选人ID')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function interviewIndex(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        // 同上级联筛选：候选人详情页跳转带的 candidate_id 是 hashid
        $candidateId = $request->input('candidate_id');
        if ($candidateId !== null && $candidateId !== '') {
            $candidateId = $this->decodeFlexibleId($candidateId);
            if ($candidateId === null) {
                return $this->fail('候选人ID' . $this->trans('Invalid'), 422);
            }
        }
        $result = $this->recruit()->list(HrInterview::class, [
            'candidate_id' => $candidateId,
        ], $page, $limit, [
            'eqFilters' => ['candidate_id'],
            'orderBy' => [['round_no', 'asc']],
        ]);
        $list = $this->appendInterviewNames($result['list']);
        $list = array_map(fn ($row) => $this->encodeIds($row, ['id', 'candidate_id', 'interviewer_id']), $list);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title('记录面试')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/interview')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'candidate_id', type:'int', desc:'候选人ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'interview_date', type:'string', desc:'面试日期 Y-m-d，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'result', type:'int', desc:'结果:0待定1通过2不通过')]
    #[\erikwang2013\apidoc\annotation\Param(name:'round_no', type:'int', desc:'轮次，缺省自动取最大轮次+1')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function interviewStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'required|string',
            'interview_date' => 'required|date_format:Y-m-d',
            'round_no' => 'integer|min:1',
            'result' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request, ['candidate_id' => '候选人ID', 'interviewer_id' => '面试官ID']);
            $interview = $this->recruit()->recordInterview((int) ($data['candidate_id'] ?? 0), $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($interview), $this->trans('Interview recorded'));
    }

    #[\erikwang2013\apidoc\annotation\Title('变更面试结果')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Param(name:'result', type:'int', desc:'结果:1通过2不通过，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'comment', type:'string', desc:'评价')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function interviewUpdate(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'result' => 'required|integer|between:1,2',
            'comment' => 'string',
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

        return $this->success($this->encodeIds($interview), $this->trans('Result updated'));
    }

    // ---------- Offer（erp_hr_offer） ----------

    #[\erikwang2013\apidoc\annotation\Title('Offer列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/offer')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'candidate_id', type:'int', desc:'候选人ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', desc:'状态:0草稿1已发出2已接受3已拒绝')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function offerIndex(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        // 同上：Offer 列表按候选人筛选
        $candidateId = $request->input('candidate_id');
        if ($candidateId !== null && $candidateId !== '') {
            $candidateId = $this->decodeFlexibleId($candidateId);
            if ($candidateId === null) {
                return $this->fail('候选人ID' . $this->trans('Invalid'), 422);
            }
        }
        $result = $this->recruit()->list(HrOffer::class, [
            'candidate_id' => $candidateId,
            'status' => $request->input('status'),
        ], $page, $limit, [
            'eqFilters' => ['candidate_id', 'status'],
            'orderBy' => [['created_at', 'desc']],
        ]);
        $list = array_map(fn ($row) => $this->encodeIds($row, ['id', 'candidate_id']), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    #[\erikwang2013\apidoc\annotation\Title('发起Offer')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/offer')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Param(name:'candidate_id', type:'int', desc:'候选人ID，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'offered_salary', type:'float', desc:'Offer薪资，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'onboard_date', type:'string', desc:'入职日期 Y-m-d')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function offerStore(Request $request): Response
    {
        $validator = validator($request->all(), [
            'candidate_id' => 'required|string',
            'offered_salary' => 'required',
            'onboard_date' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        try {
            $data = $this->decodeForeignKeys($request, ['candidate_id' => '候选人ID']);
            $offer = $this->recruit()->applyOffer((int) ($data['candidate_id'] ?? 0), $data);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), $this->trans('Offer draft created'));
    }

    #[\erikwang2013\apidoc\annotation\Title('发出Offer')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function offerSend(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->sendOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), $this->trans('Offer sent'));
    }

    #[\erikwang2013\apidoc\annotation\Title('接受Offer')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function offerAccept(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->acceptOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), $this->trans('Offer accepted; the candidate has been onboarded'));
    }

    #[\erikwang2013\apidoc\annotation\Title('拒绝Offer')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function offerReject(Request $request, string $id): Response
    {
        try {
            $offer = $this->recruit()->rejectOffer($this->decodeId($id));
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($offer), $this->trans('Offer rejected; the candidate returned to the interview stage'));
    }

    // ---------- 漏斗统计 ----------

    #[\erikwang2013\apidoc\annotation\Title('招聘漏斗统计')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/hr/recruit/funnel')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Param(name:'from', type:'string', desc:'开始日期 Y-m-d，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'to', type:'string', desc:'结束日期 Y-m-d，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

    public function funnel(Request $request): Response
    {
        $validator = validator($request->all(), [
            'from' => 'string',
            'to' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $from = (string) $request->input('from', '');
        $to = (string) $request->input('to', '');

        try {
            $result = $this->recruit()->funnel($from, $to);
        } catch (InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($result);
    }

    /**
     * 可选外键双模解码（与 EmployeeController 同口径）：
     * 未传 / null / '' / '0' → 视为不改动，从写入数据中剔除；
     * 非空但解不出（含 (int) 会静默变 0 的垃圾串）→ 422，防孤儿行/1366 落库报 500。
     *
     * @param array<string, string> $map 字段 => 提示名
     */
    private function decodeForeignKeys(Request $request, array $map): array
    {
        $data = $request->all();
        foreach ($map as $field => $label) {
            $raw = $request->input($field);
            $rawStr = $raw === null ? '' : (string) $raw;
            if ($rawStr === '' || $rawStr === '0') {
                unset($data[$field]);
                continue;
            }
            $decoded = $this->decodeFlexibleId($rawStr);
            if ($decoded === null || $decoded < 1) {
                throw new InvalidArgumentException($label . $this->trans('Invalid'));
            }
            $data[$field] = $decoded;
        }

        return $data;
    }

    /** 职位行级补 department_name（含已软删部门），一次 pluck 成映射、行内查表，无 N+1。须在 encodeIds 之前调用。 */
    private function appendJobNames(array $rows): array
    {
        $ids = array_values(array_unique(array_map(static fn ($r) => (int) ($r['department_id'] ?? 0), $rows)));
        $names = HrDepartment::withTrashed()->whereIn('id', $ids)->pluck('name', 'id');

        return array_map(static function (array $row) use ($names): array {
            $row['department_name'] = (string) ($names[(int) ($row['department_id'] ?? 0)] ?? '');

            return $row;
        }, $rows);
    }

    /** 面试行级补 candidate_name / interviewer_name（面试官为 erp_hr_employee.id）。须在 encodeIds 之前调用。 */
    private function appendInterviewNames(array $rows): array
    {
        $candIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['candidate_id'] ?? 0), $rows)));
        $candNames = HrCandidate::query()->whereIn('id', $candIds)->pluck('name', 'id');
        $interviewerIds = array_values(array_unique(array_map(static fn ($r) => (int) ($r['interviewer_id'] ?? 0), $rows)));
        $interviewerNames = HrEmployee::withTrashed()->whereIn('id', $interviewerIds)->pluck('name', 'id');

        return array_map(static function (array $row) use ($candNames, $interviewerNames): array {
            $row['candidate_name'] = (string) ($candNames[(int) ($row['candidate_id'] ?? 0)] ?? '');
            $row['interviewer_name'] = (string) ($interviewerNames[(int) ($row['interviewer_id'] ?? 0)] ?? '');

            return $row;
        }, $rows);
    }

    private function recruit(): RecruitService
    {
        return Container::get(RecruitService::class);
    }
}
