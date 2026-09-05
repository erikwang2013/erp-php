<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 * @Apidoc\Tag("寻源采购")
 */

declare(strict_types=1);

namespace app\controller\purchase;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\SupplierAssessment;
use support\Request;
use support\Response;

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
     * @Apidoc\Title("供应商评分列表")
     * @Apidoc\Url("/admin/v1/purchase/supplier-assessment")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $supplierId = $request->input('supplier_id');
        $grade = $request->input('grade');

        $query = SupplierAssessment::query();
        if ($supplierId) {
            $query->where('supplier_id', $this->decodeId($supplierId));
        }
        if ($grade) {
            $query->where('grade', (string) $grade);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'supplier_id', 'assessor_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 新增供应商评分（等级由服务端按总分推导）
     * @Apidoc\Title("新增供应商评分")
     * @Apidoc\Desc("total_score 0-100；等级规则 A ≥ 90 / B ≥ 70 / C；dimensions 为评估维度 JSON")
     * @Apidoc\Url("/admin/v1/purchase/supplier-assessment")
     * @Apidoc\Method("POST")
     * @Apidoc\Tag("寻源采购")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'supplier_id' => 'required|string',
            'total_score' => 'required|numeric|between:0,100',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $assessment = new SupplierAssessment();
        $assessment->id = $this->generateId();
        $assessment->supplier_id = $this->decodeId($request->input('supplier_id'));
        $assessment->total_score = bc_norm($request->input('total_score'));
        $assessment->grade = static::gradeFor($assessment->total_score);
        $assessment->dimensions = (array) ($request->input('dimensions', []));
        $assessment->assessor_id = (int) ($request->adminId ?? 0);
        $assessment->assessed_at = $request->input('assessed_at') ?: date('Y-m-d H:i:s');
        $assessment->remark = (string) $request->input('remark', '');
        $assessment->save();

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']), '评分成功');
    }

    /**
     * 评分详情
     * @Apidoc\Title("评分详情")
     * @Apidoc\Url("/admin/v1/purchase/supplier-assessment/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Tag("寻源采购")
     */
    public function show(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail('评分记录不存在', 404);
        }

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']));
    }

    /**
     * 更新评分（等级随总分重新推导）
     * @Apidoc\Title("更新评分")
     * @Apidoc\Url("/admin/v1/purchase/supplier-assessment/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Tag("寻源采购")
     */
    public function update(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail('评分记录不存在', 404);
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
        foreach (['assessed_at', 'remark'] as $field) {
            if ($request->has($field)) {
                $assessment->{$field} = $request->input($field) ?: null;
            }
        }
        if ($request->has('dimensions')) {
            $assessment->dimensions = (array) $request->input('dimensions');
        }
        $assessment->save();

        return $this->success($this->encodeIds($assessment->toArray(), ['id', 'supplier_id', 'assessor_id']), '更新成功');
    }

    /**
     * 删除评分（软删除，需管理员密码二次确认）
     * @Apidoc\Title("删除评分")
     * @Apidoc\Url("/admin/v1/purchase/supplier-assessment/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Tag("寻源采购")
     */
    public function destroy(Request $request, string $id): Response
    {
        $assessment = SupplierAssessment::find($this->decodeId($id));
        if (!$assessment) {
            return $this->fail('评分记录不存在', 404);
        }
        $error = $this->confirmPassword((int) ($request->adminId ?? 0), (string) $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $assessment->delete();

        return $this->success([], '删除成功');
    }
}
