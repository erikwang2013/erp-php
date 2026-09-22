<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\quality;

use app\admin\controller\BaseController;
use app\model\QualityNonconformity;
use support\Request;
use support\Response;

/**
 * 不合格品管理
 */
#[\erikwang2013\apidoc\annotation\Tag('质量管理')]
#[\erikwang2013\apidoc\annotation\Title('不合格品单')]
#[\erikwang2013\apidoc\annotation\Group('质量管理QMS')]

class NonconformityController extends BaseController
{
    /**
     * 不合格品单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('不合格品单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取不合格品单列表，支持分页、单号/缺陷类型关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/nonconformity')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（单号/缺陷类型）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'不合格品单列表数据')]

    public function index(Request $request): Response
    {
        $validator = validator($request->all(), [
            'page' => 'integer',
            'limit' => 'integer',
            'keyword' => 'string',
            'status' => 'integer',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        [$page, $limit] = $this->pageParams($request);
        // 商品名 leftJoin 带出（同 sales/OrderController::index 口径），否则列表只能上屏裸 hashid。
        // product 与主表同有 code/status 列，where/orderBy 一并限定来源，否则 JOIN 后报 1052 列歧义。
        // source_id 是多态外键（source_type 决定指向 iqc/ipqc/oqc 三张表），无法 leftJoin，前端落「-」。
        $query = QualityNonconformity::query()
            ->leftJoin('product', 'product.id', '=', 'quality_nonconformity.product_id')
            ->select('quality_nonconformity.*', 'product.name as product_name');
        $keyword = $request->input('keyword', '');
        if ($keyword) {
            $query->where('quality_nonconformity.code', 'like', "%{$keyword}%")->orWhere('quality_nonconformity.defect_type', 'like', "%{$keyword}%");
        }
        $status = $request->input('status');
        if ($status !== null && $status !== '') {
            $query->where('quality_nonconformity.status', (int)$status);
        }
        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)->limit($limit)->orderBy('quality_nonconformity.id', 'desc')->get()->map(fn ($i) => $this->encodeIds($i->toArray(), ['id', 'source_id', 'product_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建不合格品单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建不合格品单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增不合格品单，单号/缺陷类型/缺陷数量必填')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/quality/nonconformity')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'不合格品单号（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'defect_type', type:'string', default:'', desc:'缺陷类型（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'defect_qty', type:'int', default:'', desc:'缺陷数量（必填）')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'创建的不合格品单记录')]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), [
            'code' => 'required|string|max:50',
            'defect_type' => 'required|string|max:100',
            'defect_qty' => 'required|integer|min:0',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $item = new QualityNonconformity();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        // 外键（列表 encodeIds 下发的 hashid 串）须解码后再落库：fill 直填 BIGINT 列报 1366。
        // source_id 也是客户端来的 ID（多态指向 iqc/ipqc/oqc 记录），同样走双模解码。
        // 未提供/空串（前端留空下发 ''）落 0 = 未关联（列 NOT NULL DEFAULT 0）
        $fks = $this->foreignKeyFields($item);
        $item->fill($this->decodeIdFields($request, $fks) + array_fill_keys($fks, 0));
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 不合格品单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('不合格品单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取不合格品单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'不合格品单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'不合格品单详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = QualityNonconformity::find($id);

        return $item ? $this->success($this->encodeIds($item->toArray())) : $this->fail($this->trans('Record not found'), 404);
    }

    /**
     * 更新不合格品单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新不合格品单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新不合格品单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'不合格品单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的不合格品单记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = QualityNonconformity::find($id);
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
     * 删除不合格品单（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除不合格品单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除不合格品单，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('质量管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'不合格品单hashid')]
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
        $item = QualityNonconformity::find($id);
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
}
