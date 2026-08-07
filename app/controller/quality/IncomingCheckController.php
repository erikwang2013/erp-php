<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\quality;

use app\admin\controller\BaseController;
use app\model\QualityIqcRecord;
use app\service\quality\QmsInspectionService;
use support\Request;
use support\Response;

/**
 * 来料检验 (IQC)
 * @Apidoc\Tag("质量管理")
 */
class IncomingCheckController extends BaseController
{
    public function index(Request $request): Response
    {
        $page = (int)$request->input('page', 1);
        $limit = (int)$request->input('limit', 15);
        $query = QualityIqcRecord::query();
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('code', 'like', "%{$keyword}%");
        }
        $result = $request->input('result', '');
        if ($result !== '') {
            $query->where('result', $result);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray()));

        return $this->success(['list' => $list, 'total' => $total, 'page' => $page, 'limit' => $limit]);
    }

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'inspected_qty' => 'required|integer|min:0',
            'result' => 'required|in:pass,reject',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new QualityIqcRecord();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '创建成功');
    }

    public function show(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIqcRecord::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail('记录不存在', 404);
    }

    public function update(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIqcRecord::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), '更新成功');
    }

    public function destroy(Request $request, string $hashid): Response
    {
        $id = $this->decodeId($hashid);
        $item = QualityIqcRecord::find($id);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $item->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 检验登记（自动生成不合格品单）
     * @Apidoc\Title("检验登记")
     * @Apidoc\Desc("按检验类型(iqc/ipqc/oqc)登记结果，reject时自动创建不合格品单")
     * @Apidoc\Url("/admin/quality/inspection/record")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="record_type", type="string", desc="检验类型: iqc/ipqc/oqc，必填")
     * @Apidoc\Param(name="inspected_qty", type="int", desc="检验数量，必填")
     * @Apidoc\Param(name="result", type="string", desc="结果: pass/reject，必填")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="检验记录ID")
     */
    public function record(Request $request): Response
    {
        $validator = validator($request->all(), [
            'record_type' => 'required|in:iqc,ipqc,oqc',
            'inspected_qty' => 'required|integer|min:0',
            'result' => 'required|in:pass,reject',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = (new QmsInspectionService())->recordInspection((string) $request->input('record_type'), $request->all());

        return $this->success(['id' => $this->encodeIds(['id' => $id])['id']], '检验记录已保存');
    }

    /**
     * 检验合格率
     * @Apidoc\Title("检验合格率")
     * @Apidoc\Desc("按检验明细汇总计算合格率")
     * @Apidoc\Url("/admin/quality/inspection/pass-rate")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("质量管理")
     * @Apidoc\Param(name="records", type="array", desc="检验记录[{inspected_qty,passed_qty}]")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     * @Apidoc\Returned("message", type="string", desc="业务信息")
     * @Apidoc\Returned("data", type="object", desc="合格率(pass_rate)")
     */
    public function passRate(Request $request): Response
    {
        $records = $request->input('records', []);
        if (!is_array($records)) {
            return $this->fail('records 必须为数组', 422);
        }

        return $this->success(['pass_rate' => (new QmsInspectionService())->calculatePassRate($records)]);
    }
}
