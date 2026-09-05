<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\manufacturing;

use erikwang2013\apidoc\annotation as Apidoc;

use app\admin\controller\BaseController;
use app\model\HrEmployee;
use app\model\MfgProductionOrder;
use app\model\MfgRouting;
use app\model\MfgWorkReport;
use app\model\MfgWorkstation;
use app\service\manufacturing\WorkReportService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\QueryException;
use InvalidArgumentException;
use RuntimeException;
use support\Container;
use support\Request;
use support\Response;

/**
 * 工序报工单管理 — CRUD + 审核（WIP 人工成本归集）
 * @Apidoc\Tag("生产制造")
 */
class WorkReportController extends BaseController
{
    /**
     * 报工单列表（分页）
     * @Apidoc\Title("报工单列表")
     * @Apidoc\Url("/admin/v1/mfg/work-report")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="page", type="int", desc="页码")
     * @Apidoc\Param(name="limit", type="int", desc="每页条数")
     * @Apidoc\Param(name="keyword", type="string", desc="编码关键词")
     * @Apidoc\Param(name="status", type="int", desc="状态 0草稿/1已审核")
     * @Apidoc\Param(name="order_id", type="int", desc="生产工单ID")
     * @Apidoc\Param(name="employee_id", type="int", desc="员工ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);

        $result = $this->service()->list(MfgWorkReport::class, [
            'keyword' => $request->input('keyword', ''),
            'status' => $request->input('status'),
            'order_id' => $request->input('order_id'),
            'employee_id' => $request->input('employee_id'),
        ], $page, $limit, [
            'searchFields' => ['code'],
            'eqFilters' => ['status'],
            'truthyFilters' => ['order_id', 'employee_id'],
        ]);
        $list = array_map(fn ($item) => $this->encodeIds($item), $result['list']);

        return $this->success(['list' => $list, 'total' => $result['total'], 'page' => $result['page'], 'limit' => $result['limit']]);
    }

    /**
     * 创建报工单（草稿）
     * @Apidoc\Title("创建报工单")
     * @Apidoc\Url("/admin/v1/mfg/work-report")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="code", type="string", desc="报工单编码，缺省自动生成 WR+日期+随机尾")
     * @Apidoc\Param(name="order_id", type="int", desc="生产工单ID，必填")
     * @Apidoc\Param(name="product_id", type="int", desc="产品ID，必填（须等于工序所属产品）")
     * @Apidoc\Param(name="routing_id", type="int", desc="工序ID，必填")
     * @Apidoc\Param(name="workstation_id", type="int", desc="工作站ID，缺省0")
     * @Apidoc\Param(name="employee_id", type="int", desc="报工员工ID，必填")
     * @Apidoc\Param(name="report_date", type="string", desc="报工日期 Y-m-d，默认当天")
     * @Apidoc\Param(name="quantity", type="numeric", desc="报工数量，必填 >0")
     * @Apidoc\Param(name="qualified_qty", type="numeric", desc="合格数量，默认=报工数量")
     * @Apidoc\Param(name="remark", type="string", desc="备注")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'nullable|string|max:50',
            'order_id' => 'required|integer',
            'product_id' => 'required|integer',
            'routing_id' => 'required|integer',
            'workstation_id' => 'nullable|integer',
            'employee_id' => 'required|integer',
            'report_date' => 'nullable|date',
            'quantity' => 'required|numeric',
            'qualified_qty' => 'nullable|numeric',
            'remark' => 'nullable|string|max:255',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 数量/合格数与存在性前置校验（bcmath）
        $quantity = bc_norm((string) $request->input('quantity'));
        if (bccomp($quantity, '0', 4) <= 0) {
            return $this->fail('报工数量必须大于0', 422);
        }
        $qualified = $request->input('qualified_qty') !== null ? bc_norm((string) $request->input('qualified_qty')) : $quantity;
        if (bccomp($qualified, '0', 4) < 0 || bccomp($qualified, $quantity, 4) > 0) {
            return $this->fail('合格数量必须在0与报工数量之间', 422);
        }
        $routing = MfgRouting::query()->where('id', (int) $request->input('routing_id'))->first();
        if (!$routing) {
            return $this->fail('工序不存在', 422);
        }
        if ((int) $routing->product_id !== (int) $request->input('product_id')) {
            return $this->fail('产品与工序所属产品不匹配', 422);
        }
        $order = MfgProductionOrder::query()->where('id', (int) $request->input('order_id'))->first();
        if (!$order) {
            return $this->fail('生产工单不存在', 422);
        }
        if (!HrEmployee::query()->where('id', (int) $request->input('employee_id'))->exists()) {
            return $this->fail('员工不存在', 422);
        }
        $workstationId = (int) $request->input('workstation_id', 0);
        if ($workstationId > 0 && !MfgWorkstation::query()->where('id', $workstationId)->exists()) {
            return $this->fail('工作站不存在', 422);
        }

        $id = $this->generateId();
        try {
            $doc = new MfgWorkReport();
            $doc->id = $id;
            $doc->code = trim((string) $request->input('code')) ?: 'WR' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $doc->order_id = (int) $order->id;
            $doc->product_id = (int) $routing->product_id;
            $doc->routing_id = (int) $routing->id;
            $doc->workstation_id = $workstationId;
            $doc->employee_id = (int) $request->input('employee_id');
            $doc->report_date = $request->input('report_date') ?: date('Y-m-d');
            $doc->quantity = $quantity;
            $doc->qualified_qty = $qualified;
            $doc->piece_rate = '0';
            $doc->amount = '0';
            $doc->status = 0;
            $doc->remark = (string) $request->input('remark', '');
            $doc->save();
        } catch (QueryException $e) {
            if ($this->isDuplicateKey($e)) {
                return $this->fail('报工单号已存在', 422);
            }
            throw $e;
        }

        return $this->success($this->encodeIds(['id' => $id]), '创建成功');
    }

    /**
     * 报工单详情
     * @Apidoc\Title("报工单详情")
     * @Apidoc\Url("/admin/v1/mfg/work-report/{id}")
     * @Apidoc\Method("GET")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="报工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $item = $this->service()->find(MfgWorkReport::class, $id, ['order', 'routing', 'workstation', 'employee']);
        if (!$item) {
            return $this->fail('记录不存在', 404);
        }
        $data = $item->toArray();
        foreach (['order', 'routing', 'workstation', 'employee'] as $rel) {
            if ($item->relationLoaded($rel) && $item->$rel) {
                $data[$rel] = $this->encodeIds($item->$rel->toArray());
            }
        }

        return $this->success($this->encodeIds($data));
    }

    /**
     * 更新报工单（仅草稿：数量/合格数/日期等，工单产品工序员工不可改）
     * @Apidoc\Title("更新报工单")
     * @Apidoc\Url("/admin/v1/mfg/work-report/{id}")
     * @Apidoc\Method("PUT")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="报工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgWorkReport::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的报工单不可修改', 422);
        }
        $quantity = $request->input('quantity') !== null ? bc_norm((string) $request->input('quantity')) : null;
        $qualified = $request->input('qualified_qty') !== null ? bc_norm((string) $request->input('qualified_qty')) : null;
        if ($quantity !== null && bccomp($quantity, '0', 4) <= 0) {
            return $this->fail('报工数量必须大于0', 422);
        }
        if ($qualified !== null && (bccomp($qualified, '0', 4) < 0 || bccomp($qualified, $quantity ?? bc_norm($doc->quantity), 4) > 0)) {
            return $this->fail('合格数量必须在0与报工数量之间', 422);
        }
        $data = $request->all();
        unset($data['code'], $data['order_id'], $data['product_id'], $data['routing_id'], $data['employee_id'], $data['status']);
        $this->service()->update(MfgWorkReport::class, $id, $data, ['status', 'piece_rate', 'amount', 'audit_at']);

        $doc = MfgWorkReport::query()->with(['order', 'routing', 'employee'])->where('id', $id)->first();
        $data = $doc->toArray();
        foreach (['order', 'routing', 'employee'] as $rel) {
            if ($doc->relationLoaded($rel) && $doc->$rel) {
                $data[$rel] = $this->encodeIds($doc->$rel->toArray());
            }
        }

        return $this->success($this->encodeIds($data), '更新成功');
    }

    /**
     * 删除报工单（仅草稿，需密码确认）
     * @Apidoc\Title("删除报工单")
     * @Apidoc\Url("/admin/v1/mfg/work-report/{id}")
     * @Apidoc\Method("DELETE")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="报工单ID")
     * @Apidoc\Param(name="password", type="string", desc="管理员密码")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        $doc = MfgWorkReport::query()->where('id', $id)->first();
        if (!$doc) {
            return $this->fail('记录不存在', 404);
        }
        if ((int) $doc->status !== 0) {
            return $this->fail('已审核的报工单不可删除', 422);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        MfgWorkReport::query()->where('id', $id)->delete();

        return $this->success([], '删除成功');
    }

    /**
     * 审核报工单（快照计件金额并归集 WIP 人工成本）
     * @Apidoc\Title("审核报工单")
     * @Apidoc\Url("/admin/v1/mfg/work-report/{id}/audit")
     * @Apidoc\Method("POST")
     * @Apidoc\Author("erik")
     * @Apidoc\Tag("生产制造")
     * @Apidoc\Param(name="id", type="string", desc="报工单ID")
     * @Apidoc\Returned("code", type="int", desc="业务代码,0=成功")
     */
    public function audit(Request $request, string $id): Response
    {
        $id = $this->decodeId($id);
        try {
            $item = $this->service()->audit($id);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->success($this->encodeIds($item->toArray()), '审核成功');
    }

    /** 唯一键冲突判定（与成本服务同实现） */
    private function isDuplicateKey(QueryException $e): bool
    {
        return (bool) preg_match('/Duplicate entry .* for key/', $e->getMessage());
    }

    /** 报工服务 */
    private function service(): WorkReportService
    {
        return Container::get(WorkReportService::class);
    }
}
