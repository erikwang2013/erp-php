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
 */
#[\erikwang2013\apidoc\annotation\Tag('质量管理')]
#[\erikwang2013\apidoc\annotation\Title('来料检验记录')]
#[\erikwang2013\apidoc\annotation\Group('质量管理QMS')]

class IncomingCheckController extends BaseController
{
    /**
     * 来料检验记录列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('来料检验记录列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取来料检验(IQC)记录列表，支持分页、单号关键词搜索和结果筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/iqc')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（检验单号）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'result', type:'string', default:'', desc:'结果筛选: pass/reject')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'检验记录列表数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'result' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        // 关联名 leftJoin 带出（同 sales/OrderController::index 口径）：三张被关联表都无 name 列
        // 可直接取，故各取可读列——采购收货单取 code（收货单号）、商品/检验标准取 name。
        // 否则列表只能上屏裸 hashid（前端任何页面都不该贴 hashid）。code/id 与主表同名列，
        // where/orderBy 一并限定来源，否则 JOIN 后报 1052 列歧义。
        $query = QualityIqcRecord::query()
            ->leftJoin('purchase_receive', 'purchase_receive.id', '=', 'quality_iqc_record.receiving_id')
            ->leftJoin('product', 'product.id', '=', 'quality_iqc_record.product_id')
            ->leftJoin('quality_inspection_standard', 'quality_inspection_standard.id', '=', 'quality_iqc_record.standard_id')
            ->select('quality_iqc_record.*', 'purchase_receive.code as receiving_code', 'product.name as product_name', 'quality_inspection_standard.name as standard_name');
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('quality_iqc_record.code', 'like', "%{$keyword}%");
        }
        $result = $request->input('result', '');
        if ($result !== '') {
            $query->where('quality_iqc_record.result', $result);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('quality_iqc_record.id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray(), ['id', 'receiving_id', 'product_id', 'standard_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建来料检验记录
     */
    #[\erikwang2013\apidoc\annotation\Title('创建来料检验记录')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一条来料检验(IQC)记录，检验单号/检验数量/检验结果必填')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/iqc')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'检验单号（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'inspected_qty', type:'int', default:'', desc:'检验数量（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'result', type:'string', default:'', desc:'检验结果: pass/reject（必填）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'创建的检验记录')]

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
        // 外键（列表 encodeIds 下发的 hashid 串）须解码后再落库：fill 直填 BIGINT 列报 1366。
        // 未提供/空串（前端下拉留空下发 ''）落 0 = 未关联（列 NOT NULL DEFAULT 0）
        $fks = $this->foreignKeyFields($item);
        $item->fill($this->decodeIdFields($request, $fks) + array_fill_keys($fks, 0));
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 来料检验记录详情
     */
    #[\erikwang2013\apidoc\annotation\Title('来料检验记录详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取来料检验(IQC)记录详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'检验记录hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'检验记录详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = QualityIqcRecord::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail($this->trans('Record not found'), 404);
    }

    /**
     * 更新来料检验记录
     */
    #[\erikwang2013\apidoc\annotation\Title('更新来料检验记录')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新来料检验(IQC)记录信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'检验记录hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的检验记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = QualityIqcRecord::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $this->fillModelFromRequest($item, $request);
        // 同 store：外键提供时解码覆写（hashid 直填 BIGINT 列报 1366）；缺省/空串=不改动
        $item->fill($this->decodeIdFields($request, $this->foreignKeyFields($item)));
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除来料检验记录（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除来料检验记录')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除来料检验(IQC)记录，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'检验记录hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'password', type:'string', default:'', desc:'管理员密码（二次确认）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'array', desc:'空数组')]

    public function destroy(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = QualityIqcRecord::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $adminId = $request->adminId ?? 0;
        $error = $this->confirmPassword($adminId, $request->input('password', ''), $request);
        if ($error !== null) {
            return $this->fail($error, 422);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 检验登记（自动生成不合格品单）
     */
    #[\erikwang2013\apidoc\annotation\Title('检验登记')]
    #[\erikwang2013\apidoc\annotation\Desc('按检验类型(iqc/ipqc/oqc)登记结果，reject时自动创建不合格品单')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/inspection/record')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'record_type', type:'string', desc:'检验类型: iqc/ipqc/oqc，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'inspected_qty', type:'int', desc:'检验数量，必填')]
    #[\erikwang2013\apidoc\annotation\Param(name:'result', type:'string', desc:'结果: pass/reject，必填')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'检验记录ID')]

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

        return $this->success(['id' => $this->encodeIds(['id' => $id])['id']], $this->trans('Inspection record saved'));
    }

    /**
     * 检验合格率
     */
    #[\erikwang2013\apidoc\annotation\Title('检验合格率')]
    #[\erikwang2013\apidoc\annotation\Desc('按检验明细汇总计算合格率')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/inspection/pass-rate')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'合格率(pass_rate)')]

    public function passRate(Request $request): Response
    {
        $records = $request->input('records', []);
        if (!is_array($records)) {
            return $this->fail($this->trans('records must be an array'), 422);
        }

        return $this->success(['pass_rate' => (new QmsInspectionService())->calculatePassRate($records)]);
    }
}
