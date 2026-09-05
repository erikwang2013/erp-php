<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\OmsRma;
use support\Request;
use support\Response;

class RmaController extends BaseController
{
    /**
     * 退换货单列表（分页）
     */#[\erikwang2013\apidoc\annotation\Title("退换货单列表")]
#[\erikwang2013\apidoc\annotation\Desc("获取退换货单列表，支持分页、单号关键词搜索和状态筛选")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/rma")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", default:1, desc:"页码")]
#[\erikwang2013\apidoc\annotation\Param(name:"limit", type:"int", default:15, desc:"每页条数")]
#[\erikwang2013\apidoc\annotation\Param(name:"keyword", type:"string", default:"", desc:"搜索关键词（退换货单号）")]
#[\erikwang2013\apidoc\annotation\Param(name:"status", type:"int", default:"", desc:"状态筛选")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"退换货单列表数据")]

    public function index(Request $request): Response
    {
        $page = (int) $request->input('page', 1);
        $limit = (int) $request->input('limit', 15);
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        $query = OmsRma::query();
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray()));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建退换货单
     */#[\erikwang2013\apidoc\annotation\Title("创建退换货单")]
#[\erikwang2013\apidoc\annotation\Desc("新增退换货单，单号必填（不传则自动生成）")]
#[\erikwang2013\apidoc\annotation\Url("/admin/v1/oms/rma")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"code", type:"string", default:"", desc:"退换货单号")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"创建的退换货单记录")]

    public function store(Request $request): Response
    {
        $validator = validator($request->all(), ['code' => 'required|string|max:200']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        $item = new OmsRma();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        if (empty($item->code)) {
            $item->code = 'oms/rma' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('created'));
    }

    /**
     * 退换货单详情
     */#[\erikwang2013\apidoc\annotation\Title("退换货单详情")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID获取退换货单详细信息")]
#[\erikwang2013\apidoc\annotation\Method("GET")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"退换货单详情")]

    public function show(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        return $this->success($this->encodeIds($item->toArray()));
    }

    /**
     * 更新退换货单
     */#[\erikwang2013\apidoc\annotation\Title("更新退换货单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID更新退换货单信息")]
#[\erikwang2013\apidoc\annotation\Method("PUT")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"更新后的退换货单记录")]

    public function update(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray()), $this->trans('updated'));
    }

    /**
     * 删除退换货单（软删除）
     */#[\erikwang2013\apidoc\annotation\Title("删除退换货单")]
#[\erikwang2013\apidoc\annotation\Desc("根据ID软删除退换货单，需管理员密码二次确认")]
#[\erikwang2013\apidoc\annotation\Method("DELETE")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"password", type:"string", default:"", desc:"管理员密码（二次确认）")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"array", desc:"空数组")]

    public function destroy(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('not_found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('deleted'));
    }

    /**
     * 退换货单审批
     */#[\erikwang2013\apidoc\annotation\Title("退换货单审批")]
#[\erikwang2013\apidoc\annotation\Desc("审批退换货单：批准后进入退货流程，拒绝则标记为已拒绝")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Param(name:"approved", type:"bool", default:true, desc:"是否批准: true:批准/false:拒绝")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"审批后的退换货单记录")]

    public function approve(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 0) {
            return $this->fail('当前状态不可审批', 400);
        }

        $approved = $request->input('approved', true);
        if ($approved) {
            $rma->status = 1;
            $rma->approved_by = $request->adminId;
            $rma->approved_at = date('Y-m-d H:i:s');
        } else {
            $rma->status = 5;
        }
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), $approved ? '已批准' : '已拒绝');
    }

    /**
     * RMA收货确认
     */#[\erikwang2013\apidoc\annotation\Title("RMA收货确认")]
#[\erikwang2013\apidoc\annotation\Desc("退货寄回后确认收货，记录收货时间并流转到下一状态")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"收货确认后的退换货单记录")]

    public function receive(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 2) {
            return $this->fail('请等待退货寄回后再确认收货', 400);
        }

        $rma->status = 3;
        $rma->received_at = date('Y-m-d H:i:s');
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), '收货确认成功');
    }

    /**
     * RMA退款
     */#[\erikwang2013\apidoc\annotation\Title("RMA退款")]
#[\erikwang2013\apidoc\annotation\Desc("对已审批/已收货的退换货单执行退款，流转到退款完成状态")]
#[\erikwang2013\apidoc\annotation\Method("POST")]
#[\erikwang2013\apidoc\annotation\Author("erik")]
#[\erikwang2013\apidoc\annotation\Tag("退换货")]
#[\erikwang2013\apidoc\annotation\Param(name:"id", type:"string", default:"", desc:"退换货单hashid")]
#[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"业务代码,0=成功")]
#[\erikwang2013\apidoc\annotation\Returned("message", type:"string", desc:"业务信息")]
#[\erikwang2013\apidoc\annotation\Returned("data", type:"object", desc:"退款完成后的退换货单记录")]

    public function refund(Request $request, string $id): Response
    {
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('invalid_id'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('not_found'), 404);
        }
        if ($rma->status !== 3 && $rma->status !== 1) {
            return $this->fail('当前状态不可退款', 400);
        }

        $rma->status = 4;
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray()), '退款完成');
    }
}
