<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\purchase;

use app\admin\controller\BaseController;
use app\model\PurchaseApply;
use app\model\PurchaseOrder;
use app\model\PurchaseOrderItem;
use app\model\PurchaseReceiveItem;
use Illuminate\Database\Capsule\Manager as DB;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('采购订单')]
#[\erikwang2013\apidoc\annotation\Group('采购管理')]

class OrderController extends BaseController
{
    /**
     * 采购订单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('采购订单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取采购订单列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/order')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（订单编码/供应商名称）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态筛选')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'业务数据')]

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

        // 供应商名称经 leftJoin 带出。erp_purchase_order 实列无 name 列（仅 code/apply_id/supplier_id 等，
        // 见 install.sql；旧实现 where name 是幻列，关键字搜必炸）——关键字搜订单编码/供应商名称。
        // apply_code 同理：apply_id 是 erp_purchase_apply 的外键，只下发 hashid 的话
        // 「这单转自哪张申请」在界面上无处可读（列表外键列落「-」、详情更无从下钻）
        $query = PurchaseOrder::query()
            ->leftJoin('supplier', 'supplier.id', '=', 'purchase_order.supplier_id')
            ->leftJoin('purchase_apply', 'purchase_apply.id', '=', 'purchase_order.apply_id')
            ->select('purchase_order.*', 'supplier.name as supplier_name', 'purchase_apply.code as apply_code');
        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('purchase_order.code', 'like', "%{$keyword}%")
                  ->orWhere('supplier.name', 'like', "%{$keyword}%");
            });
        }
        if ($status !== null && $status !== '') {
            $query->where('purchase_order.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('purchase_order.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'supplier_id', 'apply_id', 'warehouse_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建采购订单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建采购订单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个采购订单记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/purchase/order')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'订单编号，留空后端自生成（表无 name 列）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'supplier_id', type:'int', require:true, desc:'供应商ID（hashid）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:1, desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'采购订单记录')]

    public function store(Request $request): Response
    {
        // 表无 name 列（erp_purchase_order 仅 code/apply_id/supplier_id 等，见 install.sql）；
        // supplier_id 无 DB 默认值且入参为 hashid，缺省/无效直插会 1364 崩——解码落库为 int
        // remark/ordered_at 上限对齐列宽与列类型：超长落 varchar(500) 报 1406、
        // 非日期串落 datetime 列报 1292，两者都以 500 返回
        $validator = validator($request->all(), [
            'code' => 'nullable|string|max:50',
            // supplier_id 是必填外键（列 NOT NULL 无默认值）：只留 required（`string` = is_string()
            // 会把数字形态的 ID 判 422，`integer` 会把 hashid 判 422），双模判定在下面 decodeFlexibleId 收口
            'supplier_id' => 'required',
            'remark' => 'nullable|string|max:500',
            'ordered_at' => 'nullable|date',
            // status 落 TINYINT UNSIGNED（0待审核/1已审核/2部分收货/3已收货/4已取消）：
            // integer 放行 -1/999 会以 1264 Out of range → 500；total_amount 落 DECIMAL(12,2)，
            // 非数值/超 10 位整数部分以 1265/1264 → 500（两者都经 fillModelFromRequest 直落列）
            'status' => 'integer|between:0,4',
            'total_amount' => 'nullable|numeric|min:0|max:9999999999.99',
            // 明细（录入路径，同 RfqController 口径）：product_id 为 hashid 串，不能用 integer 规则
            // 挡回；解码在 buildItems 里做，垃圾串在那里抛 422
            'items' => 'nullable|array',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999999999.99',
            'items.*.price' => 'nullable|numeric|min:0|max:9999999999.99',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.sku_id' => 'nullable',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        // 不套 (string) 强转：supplier_id 已无 string 规则挡数组，强转会被 webman 的 set_error_handler
        // 升级成 ErrorException（数组转字符串）→ 未捕获 500；非标量由 decodeFlexibleId 返回 null → 422
        $supplierId = $this->decodeFlexibleId($request->input('supplier_id', ''));
        if ($supplierId === null || $supplierId < 1) {
            return $this->fail($this->trans('Invalid supplier_id'), 422);
        }

        $id = $this->generateId();
        try {
            // 明细与主表金额同事务：明细任一行解码/量程失败即整单回滚，不留「有单无明细」的半写单
            $item = DB::transaction(function () use ($request, $supplierId, $id) {
                $item = new PurchaseOrder();
                $item->id = $id;
                $this->fillModelFromRequest($item, $request);
                // 单号缺省自生成（前缀与 Flutter order_list_page.dart 下发的 'PO'+时间戳一致）
                $item->fill(['code' => doc_code($request->input('code'), 'PO')]);
                // 解码 int 须在 fill 之后覆写：supplier_id/apply_id/warehouse_id 均在 $fillable 内，
                // fill 会把请求里的 hash 串直填 BIGINT 列（1366 崩）——统一解码覆写，垃圾/空串落 0 缺省
                $item->supplier_id = $supplierId;
                $applyId = $this->decodeFlexibleId((string) $request->input('apply_id', '0')) ?? 0;
                $item->apply_id = $applyId;
                $item->warehouse_id = $this->decodeFlexibleId((string) $request->input('warehouse_id', '0')) ?? 0;
                // 日期字段清空后前端下发 ''：nullable|date 放行 ''，但 '' 落 datetime 列同样 1292，
                // 空串语义即「不填」（列可空），归一成 NULL
                if ($request->input('ordered_at') === '') {
                    $item->fill(['ordered_at' => null]);
                }
                $item->save();
                $this->markApplyOrdered($applyId);

                // 带明细时主表金额以明细汇总为准（覆盖入参 total_amount）：与 RfqService 中标转单
                // 同一口径，避免「明细 200 / 主表 0」这类无法对账的单
                [$lines, $total] = $this->buildItems((array) $request->input('items', []));
                if ($lines !== []) {
                    $item->fill(['total_amount' => $total]);
                    $item->save();
                    $this->saveItems($id, $lines);
                }

                return $item;
            });
        } catch (\Throwable $e) {
            $this->logError('purchase_order.store', $e);

            // 业务拒绝用 RuntimeException 表达 → 422；事务内的库故障（死锁/列不存在等
            // PDOException）仍 500，且不回显原始异常文本（PDO 消息含表名与 SQL 片段）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }

        // FK 一律 hashid 出参（与列表/详情同一名单）：漏编码会把 4.1e17 的雪花 ID 原样下发，
        // 前端按数字回传即丢精度（>2^53），回写时 hashid 解码失败 → 422
        return $this->success($this->encodeIds($item->toArray(), ['id', 'supplier_id', 'apply_id', 'warehouse_id']), $this->trans('Created successfully'));
    }

    /**
     * 采购订单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('采购订单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取采购订单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购订单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'采购订单详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseOrder::query()
            ->leftJoin('supplier', 'supplier.id', '=', 'purchase_order.supplier_id')
            ->leftJoin('purchase_apply', 'purchase_apply.id', '=', 'purchase_order.apply_id')
            ->where('purchase_order.id', $id)
            ->select('purchase_order.*', 'supplier.name as supplier_name', 'purchase_apply.code as apply_code')
            ->first();
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        // 与 index/store/update 同一 FK 名单：apply_id/warehouse_id 漏编码时详情页拿到裸雪花 ID，
        // JSON.parse 即已丢精度（4.1e17 > 2^53），据此回填的编辑表单必然写错
        $data = $this->encodeIds($item->toArray(), ['id', 'supplier_id', 'apply_id', 'warehouse_id']);
        // 嵌套明细：行级 id/order_id/product_id 均 hashid；product 缺失以 null 兜底不丢行
        $items = PurchaseOrderItem::query()
            ->leftJoin('product', 'product.id', '=', 'purchase_order_item.product_id')
            ->where('purchase_order_item.order_id', $id)
            ->select('purchase_order_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('purchase_order_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'order_id', 'product_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新采购订单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新采购订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新采购订单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购订单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'supplier_id', type:'int', default:'', desc:'供应商ID（hashid，后端解码）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'订单编号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的采购订单记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            // supplier_id 不卡 string（同 store）：数字形态的供应商 ID 会被 is_string() 判 422，
            // 双模判定在下面的 decodeFlexibleId 收口；局部更新不传即不动，故不加 required
            'code' => 'string|max:50',
            'remark' => 'nullable|string|max:500',
            'ordered_at' => 'nullable|date',
            // 同 store：status 卡 TINYINT UNSIGNED 语义域，total_amount 卡 DECIMAL(12,2) 量程
            'status' => 'integer|between:0,4',
            'total_amount' => 'nullable|numeric|min:0|max:9999999999.99',
            // 同 store：明细整表替换（提供 items 才动明细，不提供即「不改动」，
            // 与编辑弹框「明细仅新建期填写」的语义一致）
            'items' => 'nullable|array',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|numeric|gt:0|max:9999999999.99',
            'items.*.price' => 'nullable|numeric|min:0|max:9999999999.99',
            'items.*.unit' => 'nullable|string|max:20',
            'items.*.sku_id' => 'nullable',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = PurchaseOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        try {
            DB::transaction(function () use ($request, $item, $id) {
                $this->fillModelFromRequest($item, $request);
                // 同 store：三个 FK 都在 $fillable 内，fill 会把请求里的 hash 串与空串直填 BIGINT 列
                // （MySQL 严格模式 1366 → 500）。未传一律不动（局部更新不得清空既有外键）；
                // 传了即解码覆写。
                $supplierRaw = $request->input('supplier_id', null);
                if ($supplierRaw === null || $supplierRaw === '') {
                    // 未传/显式 null/空串：供应商是必填外键（列 NOT NULL 无默认值，同 store 的 required），
                    // 清空无意义 → 维持原值。必须显式回填：fill 已把请求里的 ''/null 写进模型，
                    // 不覆写就会以 '' 落 BIGINT 报 1366、以 null 落报 1048
                    $item->fill(['supplier_id' => $item->getOriginal('supplier_id')]);
                } else {
                    // 不套 (string) 强转：数组入参经 set_error_handler 会升级成 ErrorException → 500，
                    // 非标量由 decodeFlexibleId 返回 null → 422
                    $supplierId = $this->decodeFlexibleId($supplierRaw);
                    if ($supplierId === null || $supplierId < 1) {
                        throw new \RuntimeException($this->trans('Invalid supplier_id'));
                    }
                    $item->fill(['supplier_id' => $supplierId]);
                }
                $applyId = 0;
                foreach (['apply_id', 'warehouse_id'] as $field) {
                    $raw = $request->input($field, null);
                    if ($raw === null) {
                        // 未传（含显式 null）＝不改动：回填原值抹掉 fill 带进来的 null
                        // （列 NOT NULL，null 直落报 1048）
                        $item->fill([$field => $item->getOriginal($field)]);
                        continue;
                    }
                    // 空串＝清空 → 0（列 NOT NULL DEFAULT 0），口径同 sales/OrderController::update；
                    // 垃圾串落 0（可选外键口径），未传的字段绝不被清零。
                    // 不强转 string：数组入参经 set_error_handler 会升级成 ErrorException → 500，
                    // 非标量由 decodeFlexibleId 返回 null → 落 0
                    $decoded = $raw === '' ? 0 : ($this->decodeFlexibleId($raw) ?? 0);
                    $item->fill([$field => $decoded]);
                    if ($field === 'apply_id') {
                        $applyId = $decoded;
                    }
                }
                // 同 store：清空的日期字段下发 ''，列可空但 '' 落库报 1292，归一成 NULL
                if ($request->input('ordered_at') === '') {
                    $item->fill(['ordered_at' => null]);
                }
                $item->save();
                $this->markApplyOrdered($applyId);

                if (!$request->has('items')) {
                    return;
                }
                [$lines, $total] = $this->buildItems((array) $request->input('items'));
                if ($lines === []) {
                    throw new \RuntimeException('采购订单至少保留一条明细');
                }
                // 整表替换会换掉明细行 id，而已有收货明细（purchase_receive_item.order_item_id）
                // 与超收校验（按 order_item_id 汇总实收）都挂在行 id 上：一旦有实收，替换即留下对不上
                // 的孤儿收货行，且新行能再收满一次 → 重复入库。判据取收货明细表本身：
                // purchase_order_item.received_quantity 在收货链路里全程没人写（恒 0），拿它当判据是死代码
                $received = PurchaseReceiveItem::query()
                    ->join('purchase_order_item', 'purchase_order_item.id', '=', 'purchase_receive_item.order_item_id')
                    ->where('purchase_order_item.order_id', $id)
                    ->exists();
                if ($received) {
                    throw new \RuntimeException('该订单已有收货记录，明细不可整体替换');
                }
                PurchaseOrderItem::query()->where('order_id', $id)->delete();
                $this->saveItems($id, $lines);
                $item->fill(['total_amount' => $total]);
                $item->save();
            });
        } catch (\Throwable $e) {
            $this->logError('purchase_order.update', $e);

            // 业务拒绝用 RuntimeException 表达 → 422；事务内的库故障（死锁/列不存在等
            // PDOException）仍 500，且不回显原始异常文本（PDO 消息含表名与 SQL 片段）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }

        return $this->success($this->encodeIds($item->toArray(), ['id', 'supplier_id', 'apply_id', 'warehouse_id']), $this->trans('Updated successfully'));
    }

    /**
     * 删除采购订单（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除采购订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除采购订单，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('采购管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'采购订单hashid')]
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
        $item = PurchaseOrder::find($id);
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
     * 申请单侧的落库：订单认领了某张采购申请（apply_id>0）即把该申请置「已转订单」(3)。
     *
     * erp_purchase_apply.status 的 3 此前没有任何写入方（ApprovalController 只写自己的
     * ApprovalInstance，不回写目标单据；前端也没有流转入口），状态到「已批准」就断了。
     * 认领方是订单表本身，所以由订单落库驱动，而不是让操作员再手点一个「转订单」按钮
     * （那样只是把 status 改成一个没人验证过的值）。
     *
     * 软删除的申请不在此列（PurchaseApply 的全局 scope 兜住）；找不到就静默跳过——
     * 申请单被删不该让开单失败。
     */
    private function markApplyOrdered(int $applyId): void
    {
        if ($applyId < 1) {
            return;
        }
        PurchaseApply::query()->where('id', $applyId)->update(['status' => 3]);
    }

    /**
     * 明细行校验 + 金额计算（先算后写：金额合计要回写主表）。
     * 单一 product_id 口径与 RfqController::saveItems 一致：hashid 双模解码，垃圾/空串即拒绝
     * （写 0 会得到无商品的孤儿明细行，收货时按商品反查必然对不上）。
     *
     * @return array{0: list<array<string,int|string>>, 1: string} [待写行, 金额合计]
     */
    private function buildItems(array $items): array
    {
        $lines = [];
        $total = '0.00';
        foreach ($items as $row) {
            $productId = $this->decodeFlexibleId($row['product_id'] ?? '');
            if ($productId === null || $productId < 1) {
                throw new \RuntimeException('明细 product_id 无效');
            }
            // 数量/单价落 DECIMAL(12,2)：bc_norm 归一，非法串由 store/update 的 validator 提前挡回
            $quantity = bc_norm((string) ($row['quantity'] ?? '0'));
            $price = bc_norm((string) ($row['price'] ?? '0'));
            // 金额 = 数量 × 单价（先 scale=4 再四舍五入 2 位，与 RfqService::lineAmount 同口径）；
            // 明细金额列是 DECIMAL(12,2)，超量程写库报 1264 → 500，这里提前给可读原因
            $amount = bc_round(bcmul($quantity, $price, 4), 2);
            if (bccomp($amount, '9999999999.99', 2) > 0) {
                throw new \RuntimeException('明细金额（数量 × 单价）超出上限 9999999999.99');
            }
            $total = bcadd($total, $amount, 2);
            $lines[] = [
                'product_id' => $productId,
                'sku_id' => $this->decodeFlexibleId($row['sku_id'] ?? '') ?? 0,
                'quantity' => $quantity,
                'price' => $price,
                'amount' => $amount,
                'unit' => (string) ($row['unit'] ?? ''),
            ];
        }
        if (bccomp($total, '9999999999.99', 2) > 0) {
            throw new \RuntimeException('订单金额合计超出上限 9999999999.99');
        }

        return [$lines, $total];
    }

    /**
     * 明细落库：行 id 与主表同源（雪花）。received_quantity 落 0——注意收货链路并不回写此列
     * （实收累计在 purchase_receive_item 里按 order_item_id 现算），此列仅供列表展示
     *
     * @param list<array<string,int|string>> $lines buildItems 的产出
     */
    private function saveItems(int $orderId, array $lines): void
    {
        foreach ($lines as $line) {
            $item = new PurchaseOrderItem();
            $item->id = $this->generateId();
            $item->order_id = $orderId;
            $item->product_id = $line['product_id'];
            $item->sku_id = $line['sku_id'];
            $item->quantity = $line['quantity'];
            $item->received_quantity = '0.00';
            $item->price = $line['price'];
            $item->amount = $line['amount'];
            $item->unit = $line['unit'];
            $item->save();
        }
    }
}
