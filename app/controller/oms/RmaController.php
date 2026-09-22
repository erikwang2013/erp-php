<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\oms;

use app\admin\controller\BaseController;
use app\model\AdminUser;
use app\model\OmsRma;
use app\model\OmsRmaItem;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('退换货单')]
#[\erikwang2013\apidoc\annotation\Group('订单管理OMS')]

class RmaController extends BaseController
{
    /**
     * 退换货单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('退换货单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取退换货单列表，支持分页、单号关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/oms/rma')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（退换货单号）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'退换货单列表数据')]

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
        $keyword = $request->input('keyword', '');
        $status = $request->input('status');

        // order_id 是 erp_sales_order 的外键（表单下拉取 /admin/v1/sales/order 的 id，
        // RmaService::refund 也按 SalesOrder::where('id', order_id) 取订单总额）：
        // 只下发 hashid 的话「关联订单」列无处可读，leftJoin 把单号以 order_code 带出。
        // 筛选/排序三处必须带表名——sales_order 同有 code/status/id，裸列名会 1052 ambiguous
        $query = OmsRma::query()
            ->leftJoin('sales_order', 'sales_order.id', '=', 'oms_rma.order_id')
            ->select('oms_rma.*', 'sales_order.code as order_code');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('oms_rma.code', 'like', "%{$keyword}%");
            });
        }

        if ($status !== null && $status !== '') {
            $query->where('oms_rma.status', (int) $status);
        }

        $total = $query->count();
        $rows = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('oms_rma.id', 'desc')
            ->get()->toArray();
        // 行补审批人名（erp_admin_user.real_name）：approved_by 存的是管理员雪花ID
        // （RmaService::approve 的 $approverId 来自 request->adminId），前端取名称的键 =
        // 本键切掉末 3 字符 + _name（approved_by → approved_name）
        $approverNames = AdminUser::query()->whereIn('id', array_column($rows, 'approved_by'))
            ->pluck('real_name', 'id')->all();
        $list = array_map(function (array $item) use ($approverNames) {
            // 名称按裸 ID 查（encodeIds 之后 approved_by 已是 hashid）
            $item['approved_name'] = $approverNames[$item['approved_by']] ?? '';

            return $this->encodeIds($item, ['id', 'order_id', 'customer_id', 'return_shipment_id']);
        }, $rows);

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建退换货单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建退换货单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增退换货单，单号留空由后端自生成')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/oms/rma')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'string', desc:'客户ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'order_id', type:'string', desc:'销售订单ID hashid（必填）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'退换货单号，留空后端自生成')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'创建的退换货单记录')]

    public function store(Request $request): Response
    {
        // code 原来是 required|string|max:200，而下面又有 empty() 自生成兜底 —— 必填使兜底永不生效，
        // 且 FE 那页没有单号输入框（用户无处可填）→ 点新增必 422。改可选，让既有兜底生效。
        // 列宽 VARCHAR(50)，原 max:200 也偏松
        $validator = validator($request->all(), ['code' => 'nullable|string|max:50']);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }

        // erp_oms_rma 里 NOT NULL 无默认的非 id 列是 code/order_id/customer_id：后两者请求里传的是
        // hashid，直填会写坏 bigint，故双模解码，非法一律 422（不留 MySQL 1366 500）
        $customerId = $this->decodeFlexibleId($request->input('customer_id'));
        if ($customerId === null || $customerId < 1) {
            return $this->fail($this->trans('Invalid customer'), 422);
        }
        $orderId = $this->decodeFlexibleId($request->input('order_id'));
        if ($orderId === null || $orderId < 1) {
            return $this->fail($this->trans('Invalid order'), 422);
        }

        $item = new OmsRma();
        $item->id = $this->generateId();
        $this->fillModelFromRequest($item, $request);
        $item->fill(['customer_id' => $customerId, 'order_id' => $orderId]);
        if (empty($item->code)) {
            $item->code = 'oms/rma' . $this->generateId();
        }
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'order_id']), $this->trans('Created successfully'));
    }

    /**
     * 退换货单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('退换货单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取退换货单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'退换货单详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'order_id']);
        // 嵌套明细：行级 id/product_id hashid + 商品名/编码 join（product 缺失 null 兜底不丢行）
        $items = OmsRmaItem::query()
            ->leftJoin('product', 'product.id', '=', 'oms_rma_item.product_id')
            ->where('oms_rma_item.rma_id', $id)
            ->select('oms_rma_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('oms_rma_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'product_id', 'order_item_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新退换货单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新退换货单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新退换货单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的退换货单记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $this->fillModelFromRequest($item, $request);
        $item->save();

        return $this->success($this->encodeIds($item->toArray(), ['id', 'order_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除退换货单（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除退换货单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除退换货单，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
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
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }
        $err = $this->confirmPassword($request->adminId, $request->input('password', ''), $request);
        if ($err) {
            return $this->fail($err, 403);
        }

        $item = OmsRma::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        $item->delete();

        return $this->success([], $this->trans('Deleted successfully'));
    }

    /**
     * 退换货单审批
     */
    #[\erikwang2013\apidoc\annotation\Title('退换货单审批')]
    #[\erikwang2013\apidoc\annotation\Desc('审批退换货单：批准后进入退货流程，拒绝则标记为已拒绝')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'approved', type:'bool', default:true, desc:'是否批准: true:批准/false:拒绝')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'审批后的退换货单记录')]

    public function approve(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            'approved' => 'boolean',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ($rma->status !== 0) {
            return $this->fail($this->trans('The current status cannot be approved'), 400);
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

        return $this->success($this->encodeIds($rma->toArray(), ['id', 'order_id']), $approved ? $this->trans('Approved') : $this->trans('Declined'));
    }

    /**
     * RMA收货确认
     */
    #[\erikwang2013\apidoc\annotation\Title('RMA收货确认')]
    #[\erikwang2013\apidoc\annotation\Desc('退货寄回后确认收货，记录收货时间并流转到下一状态')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'收货确认后的退换货单记录')]

    public function receive(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ($rma->status !== 2) {
            return $this->fail($this->trans('Please wait for the returned goods to arrive before confirming receipt'), 400);
        }

        $rma->status = 3;
        $rma->received_at = date('Y-m-d H:i:s');
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray(), ['id', 'order_id']), $this->trans('Receipt confirmation succeeded'));
    }

    /**
     * RMA退款
     */
    #[\erikwang2013\apidoc\annotation\Title('RMA退款')]
    #[\erikwang2013\apidoc\annotation\Desc('对已审批/已收货的退换货单执行退款，流转到退款完成状态')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('退换货')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'退换货单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'退款完成后的退换货单记录')]

    public function refund(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeIdSafe($id);
        if (!$id) {
            return $this->fail($this->trans('Invalid ID'), 400);
        }

        $rma = OmsRma::find($id);
        if (!$rma) {
            return $this->fail($this->trans('Record not found'), 404);
        }
        if ($rma->status !== 3 && $rma->status !== 1) {
            return $this->fail($this->trans('The current status cannot be refunded'), 400);
        }

        $rma->status = 4;
        $rma->save();

        return $this->success($this->encodeIds($rma->toArray(), ['id', 'order_id']), $this->trans('Refund completed'));
    }
}
