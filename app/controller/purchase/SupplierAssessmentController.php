<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\SupplierAssessment;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('供应商评分')]
#[\erikwang2013\apidoc\annotation\Group('采购管理')]

class SupplierAssessmentController extends BaseController
{
    /** 等级档位：A ≥ 90，B ≥ 70，其余 C */
    public static function gradeFor(string|int|float $totalScore): string
    {
        $score = bc_norm($totalScore);
        if (bccomp($score, '90', 0) >= 0) {
            return 'A';
        }

        return bccomp($score, '70', 0) >= 0 ? 'B' : 'C';
    }

    /**
     * 供应商评分列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('供应商评分列表')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/supplier-assessment')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function index(Request $request): Response
    {
        [$page, $limit] = $this->pageParams($request);
        $supplierId = $request->input('supplier_id');
        $grade = $request->input('grade');

        // 带出可读供应商名（同 SalesOrderController::index 的 leftJoin 惯例）；
        // 两表都有 id/created_at 等同名列，故 where/orderBy 一律限定表名
        $query = SupplierAssessment::query()
            ->leftJoin('supplier', 'supplier.id', '=', 'supplier_assessment.supplier_id')
            ->select('supplier_assessment.*', 'supplier.name as supplier_name');
        if ($supplierId) {
            // 双模解码（hashid/原生数字）；无法解析即视为未筛选（同收货列表的过滤惯例）
            $decodedSupplier = $this->decodeFlexibleId($supplierId);
            if ($decodedSupplier !== null && $decodedSupplier > 0) {
                $query->where('supplier_assessment.supplier_id', $decodedSupplier);
            }
        }
        if ($grade) {
            $query->where('supplier_assessment.grade', (string) $grade);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('supplier_assessment.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'supplier_id', 'assessor_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 新增供应商评分（等级由服务端按总分推导）
     */
    #[\erikwang2013\apidoc\annotation\Title('新增供应商评分')]
    #[\erikwang2013\apidoc\annotation\Desc('total_score 0-100；等级规则 A ≥ 90 / B ≥ 70 / C；dimensions 为评估维度 JSON')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/supplier-assessment')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'supplier_id' => 'required|string',
            'total_score' => 'required|numeric|between:0,100',
            // 上限/类型对齐建表列：dimensions 是 json 列（非数组入参不落库）、
            // assessed_at 是 datetime（非日期串报 1292）、remark varchar(500)（超长报 1406），
            // 三者原先都以 500「服务器内部错误」返回
            'dimensions' => 'nullable|array',
            'assessed_at' => 'nullable|date',
            'remark' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // 双模解码 + 显式拒绝：原样透传时垃圾串抛「无效的加密ID」（兜在 ApiHandler 里，
        // 文案指不出是哪个参数），且「像 hashid 的数字串」会解成 PHP_INT_MAX 落库成孤儿
        $supplierId = $this->decodeFlexibleId($request->input('supplier_id'));
        if ($supplierId === null || $supplierId < 1) {
            return $this->fail('供应商（supplier_id）无效', 422);
        }

        $assessment = new SupplierAssessment();
        $assessment->id = $this->generateId();
        $assessment->supplier_id = $supplierId;
        $assessment->total_score = bc_norm($request->input('total_score'));
        $assessment->grade = static::gradeFor($assessment->total_score);
        $assessment->dimensions = (array) ($request->input('dimensions', []));
        $assessment->assessor_id = (int) ($request->adminId ?? 0);
        $assessment->assessed_at = $request->input('assessed_at') ?: date('Y-m-d H:i:s');
        $assessment->remark = (string) $request->input('remark', '');
        $assessment->save();

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']), $this->trans('Rating submitted successfully'));
    }

    /**
     * 评分详情
     */
    #[\erikwang2013\apidoc\annotation\Title('评分详情')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function show(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail($this->trans('Rating record not found'), 404);
        }

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']));
    }

    /**
     * 更新评分（等级随总分重新推导）
     */
    #[\erikwang2013\apidoc\annotation\Title('更新评分')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function update(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail($this->trans('Rating record not found'), 404);
        }

        if ($request->has('total_score')) {
            $score = $request->input('total_score');
            $validator = validator(['total_score' => $score], ['total_score' => 'required|numeric|between:0,100']);
            if ($validator->fails()) {
                return $this->fail($validator->errors()->first(), 422);
            }
            $assessment->total_score = bc_norm($score);
            $assessment->grade = static::gradeFor($assessment->total_score);
        }
        // 同 store：日期/文本/JSON 三类入参须先按列类型校验，否则 1292 / 1406 / 3140 一律以 500 返回
        $validator = validator($request->all(), [
            'assessed_at' => 'nullable|date',
            'remark' => 'nullable|string|max:500',
            'dimensions' => 'nullable|array',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // remark 列 NOT NULL default ''，不可按「空即成 NULL」处理（1048 → 500）：空串就是空串
        if ($request->has('remark')) {
            $assessment->remark = (string) $request->input('remark', '');
        }
        if ($request->has('assessed_at')) {
            $assessment->assessed_at = $request->input('assessed_at') ?: null;
        }
        if ($request->has('dimensions')) {
            $assessment->dimensions = (array) $request->input('dimensions');
        }
        $assessment->save();

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除评分（软删除，需管理员密码二次确认）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除评分')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Tag('寻源采购')]

    public function destroy(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail($this->trans('Rating record not found'), 404);
        }
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $assessment->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }
}
