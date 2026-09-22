<?php

/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */
declare(strict_types=1);

namespace app\controller\sales;

use app\admin\controller\BaseController;
use app\model\SalesDeliveryItem;
use app\model\SalesOrder;
use app\model\SalesOrderItem;
use app\service\notification\WebhookService;
use app\service\sales\CreditControlException;
use app\service\sales\CreditControlService;
use Illuminate\Database\Capsule\Manager as DB;
use support\Container;
use support\Request;
use support\Response;

#[\erikwang2013\apidoc\annotation\Title('销售订单')]
#[\erikwang2013\apidoc\annotation\Group('销售管理')]

class OrderController extends BaseController
{
    /**
     * 销售订单列表（分页）
     */
    #[\erikwang2013\apidoc\annotation\Title('销售订单列表')]
    #[\erikwang2013\apidoc\annotation\Desc('获取销售订单列表，支持分页、关键词搜索和状态筛选')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/sales/order')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'page', type:'int', default:1, desc:'页码')]
    #[\erikwang2013\apidoc\annotation\Param(name:'limit', type:'int', default:15, desc:'每页条数')]
    #[\erikwang2013\apidoc\annotation\Param(name:'keyword', type:'string', default:'', desc:'搜索关键词（订单编号）')]
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

        // erp_sales_order 无 name 列，客户名经 leftJoin 带出（customer_name）；customer.code 与 sales.code 同列名须限定
        $query = SalesOrder::query()
            ->leftJoin('customer', 'customer.id', '=', 'sales_order.customer_id')
            ->select('sales_order.*', 'customer.name as customer_name');
        if ($keyword) {
            // 表无 name 列（erp_sales_order 仅有 code/customer_id 等，见 install.sql），仅按订单编号搜索
            $query->where('sales_order.code', 'like', "%{$keyword}%");
        }
        if ($status !== null && $status !== '') {
            $query->where('sales_order.status', (int) $status);
        }

        $total = $query->count();
        $list = $query->offset(($page - 1) * $limit)
            ->limit($limit)->orderBy('sales_order.id', 'desc')
            ->get()->map(fn ($item) => $this->encodeIds($item->toArray(), ['id', 'customer_id', 'quotation_id', 'warehouse_id']));

        return $this->successPage($list, $total, $page, $limit);
    }

    /**
     * 创建销售订单
     */
    #[\erikwang2013\apidoc\annotation\Title('创建销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('新增一个销售订单记录')]
    #[\erikwang2013\apidoc\annotation\Url('/admin/v1/sales/order')]
    #[\erikwang2013\apidoc\annotation\Method('POST')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', require:true, desc:'订单编号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'int', require:true, desc:'客户ID（hashid，后端解码）')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'订单编号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:1, desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'销售订单记录')]

    public function store(Request $request): Response
    {
        // 表无 name 列（erp_sales_order 仅 code/customer_id 等，见 install.sql），仅校验真实列
        $validator = validator($request->all(), [
            'code' => 'nullable|string|max:50',
            // customer_id 是必填外键（列 NOT NULL 无默认值）：只留 required（`string` = is_string()
            // 会把数字形态的 ID 判 422，`integer` 会把 hashid 判 422），双模判定在下面 decodeFlexibleId 收口
            'customer_id' => 'required',
            'status' => 'integer',
            // 明细（录入路径，与 purchase/OrderController、RfqController 同一口径）：product_id 为
            // hashid 串，不能用 integer 规则挡回；解码在 buildItems 里做，垃圾串在那里抛 422
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

        // customer_id 入参为 hashid 串或数字 ID（缺省/无效 → 422）；解码 int 供信用控制并覆写落库。
        // 原 decodeIdSafe 是纯 hashid 解码：数字形态的 ID 抛异常 → 422（与 purchase store 同族缺口），
        // 且 hashids 对某些纯数字串会解出垃圾大数（见 decodeFlexibleId 注释）→ 改用双模 + 往返校验。
        // 不套 (string) 强转：数组入参经 set_error_handler 会升级成 ErrorException → 500，
        // 非标量由 decodeFlexibleId 返回 null → 422
        $customerId = $this->decodeFlexibleId($request->input('customer_id', ''));
        if ($customerId === null || $customerId < 1) {
            return $this->fail($this->trans('Invalid customer_id'), 422);
        }

        // 明细先算后写：带明细时主表金额以明细汇总为准。信用控制必须看到这个真实金额——
        // 若沿用入参 total_amount，客户端下发 total_amount=0 配任意高额明细即可绕过额度校验
        try {
            [$lines, $totalAmount] = $this->buildItems((array) $request->input('items', []));
        } catch (\Throwable $e) {
            $this->logError('sales_order.store.items', $e);

            // 业务拒绝（明细为空/商品解码失败/量程越界）用 RuntimeException 表达 → 422；
            // PDOException 属库故障，仍 500（判据同 wms/ReceivingController）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }
        if ($lines === []) {
            $totalAmount = $request->input('total_amount', '0');
        }

        // 信用控制前置拦截：带客户且金额可识别时校验（冻结恒生效；额度未启用自动放行）
        if (is_numeric($totalAmount)) {
            try {
                Container::get(CreditControlService::class)->assertOrderCreate($customerId, (string) $totalAmount);
            } catch (CreditControlException $e) {
                return $this->fail($e->getMessage(), 422);
            }
        }

        // 先落局部变量：doc_code 用雪花号生成、非幂等，调用两次会得到两个不同单号；
        // 订单实体属性无 @property 注解，回读模型属性会新增 PHPStan property.notFound
        $id = $this->generateId();
        $code = doc_code($request->input('code'), 'SO');

        try {
            // 明细与主表同事务：任一行解码/量程失败即整单回滚，不留「有单无明细」的半写单
            // （半写单在发货端取 orderItems 为空 → 按商品反查 0 行 → 发货 422）
            $item = DB::transaction(function () use ($request, $customerId, $id, $code, $lines, $totalAmount) {
                $item = new SalesOrder();
                $item->id = $id;
                $this->fillModelFromRequest($item, $request);
                // 单号缺省自生成（fill 之后覆写，理由同 customer_id）
                $item->fill(['code' => $code]);
                // 解码 int 须在 fill 之后覆写：customer_id 非 guarded，先赋会被请求里的 hash 串直填覆写（崩/脏数据）
                $item->customer_id = $customerId;
                // warehouse_id 在 $fillable 内且列 NOT NULL DEFAULT 0：缺省/空串/hashid 经
                // decodeFlexibleId 归一（垃圾串落 0 而非 1366 崩），口径同 purchase/OrderController
                $item->fill(['warehouse_id' => $this->decodeFlexibleId((string) $request->input('warehouse_id', '0')) ?? 0]);
                // 带明细时主表金额以明细汇总为准（覆盖入参）：与 purchase/OrderController、RfqService
                // 同一口径，避免「明细 200 / 主表 0」这类无法对账的单
                if ($lines !== []) {
                    $item->fill(['total_amount' => $totalAmount]);
                }
                $item->save();
                $this->saveItems($id, $lines);

                return $item;
            });
        } catch (\Throwable $e) {
            $this->logError('sales_order.store', $e);

            // 业务拒绝用 RuntimeException 表达 → 422；事务内的库故障（死锁/列不存在等
            // PDOException）仍 500，且不回显原始异常文本（PDO 消息含表名与 SQL 片段）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }

        // 订单创建事件异步入队：HTTP 请求内只入队，真实投递与退避重试由消费进程做
        // （见 app/queue/redis/WebhookTask）；RedisQueue::push 自身吞异常返 false，不会影响下单主流程
        Container::get(WebhookService::class)->dispatchAsync('order.created', [
            'id' => $this->encodeId($id),
            'code' => $code,
            'customer_id' => $this->encodeId($customerId),
            'total_amount' => is_scalar($totalAmount) ? (string) $totalAmount : '',
        ]);

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Created successfully'));
    }

    /**
     * 销售订单详情
     */
    #[\erikwang2013\apidoc\annotation\Title('销售订单详情')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID获取销售订单详细信息')]
    #[\erikwang2013\apidoc\annotation\Method('GET')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'销售订单hashid')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'销售订单详情')]

    public function show(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
        ]);
        if ($validator->fails()) {
            return $this->fail($validator->errors()->first(), 422);
        }
        $id = $this->decodeId($id);
        $item = SalesOrder::query()
            ->leftJoin('customer', 'customer.id', '=', 'sales_order.customer_id')
            ->where('sales_order.id', $id)
            ->select('sales_order.*', 'customer.name as customer_name')
            ->first();
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        $data = $this->encodeIds($item->toArray(), ['id', 'customer_id']);
        // 嵌套明细：行级 id/order_id/product_id 均 hashid（批5/6 下钻请求直接复用）；
        // product 软删/硬删均以 null 兜底不丢行（leftJoin 天然保留，硬删行 name/code 为 null）
        $items = SalesOrderItem::query()
            ->leftJoin('product', 'product.id', '=', 'sales_order_item.product_id')
            ->where('sales_order_item.order_id', $id)
            ->select('sales_order_item.*', 'product.name as product_name', 'product.code as product_code')
            ->orderBy('sales_order_item.id')
            ->get()
            ->map(fn ($row) => $this->encodeIds($row->toArray(), ['id', 'order_id', 'product_id']));
        $data['items'] = $items->all();

        return $this->success($data);
    }

    /**
     * 更新销售订单
     */
    #[\erikwang2013\apidoc\annotation\Title('更新销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID更新销售订单信息')]
    #[\erikwang2013\apidoc\annotation\Method('PUT')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'销售订单hashid')]
    #[\erikwang2013\apidoc\annotation\Param(name:'customer_id', type:'int', default:'', desc:'客户ID')]
    #[\erikwang2013\apidoc\annotation\Param(name:'code', type:'string', default:'', desc:'订单编号')]
    #[\erikwang2013\apidoc\annotation\Param(name:'status', type:'int', default:'', desc:'状态')]
    #[\erikwang2013\apidoc\annotation\Returned('code', type:'int', desc:'业务代码,0=成功')]
    #[\erikwang2013\apidoc\annotation\Returned('message', type:'string', desc:'业务信息')]
    #[\erikwang2013\apidoc\annotation\Returned('data', type:'object', desc:'更新后的销售订单记录')]

    public function update(Request $request, string $id): Response
    {
        $validator = validator($request->all(), [
            'id' => 'string',
            // customer_id 不卡 string（同 store）：数字形态的客户 ID 会被 is_string() 判 422，
            // 双模判定在下面的 decodeFlexibleId 收口；局部更新不传即不动，故不加 required
            'code' => 'string',
            'status' => 'integer',
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
        $item = SalesOrder::find($id);
        if (!$item) {
            return $this->fail($this->trans('Record not found'), 404);
        }

        try {
            DB::transaction(function () use ($request, $item, $id) {
                $this->fillModelFromRequest($item, $request);
                // 同 store：customer_id 非 guarded，fill 会把请求里的 hash 串与空串直填 BIGINT 列
                // （MySQL 严格模式 1366 → 500）。未传（含显式 null）一律不动（局部更新不得清空既有外键）；
                // 空串＝必填外键清空无意义 → 维持原值（必须显式回填：fill 已把 ''/null 写进模型）；
                // 传值即解码覆写（hashid 与数字双模），垃圾串 422。口径同 purchase/OrderController::update
                $customerRaw = $request->input('customer_id', null);
                if ($customerRaw === null || $customerRaw === '') {
                    $item->fill(['customer_id' => $item->getOriginal('customer_id')]);
                } else {
                    $customerId = $this->decodeFlexibleId($customerRaw);
                    if ($customerId === null || $customerId < 1) {
                        throw new \RuntimeException($this->trans('Invalid customer_id'));
                    }
                    $item->fill(['customer_id' => $customerId]);
                }
                // 同 store：warehouse_id 在 $fillable 内，fill 会把请求 hash 串/空串直填 BIGINT 列
                // （1366）；未传/显式 null 一律不动（局部更新不得清空既有外键。显式 null 必须显式回填：
                // fill 已把 null 写进模型，不覆写会以 null 落 NOT NULL 列报 1048 → 500），
                // 空串即「清空」→ 0（列 NOT NULL DEFAULT 0）
                $warehouseRaw = $request->input('warehouse_id', null);
                if ($warehouseRaw === null) {
                    $item->fill(['warehouse_id' => $item->getOriginal('warehouse_id')]);
                } else {
                    $item->fill(['warehouse_id' => $this->decodeFlexibleId((string) $warehouseRaw) ?? 0]);
                }

                if (!$request->has('items')) {
                    $item->save();

                    return;
                }
                [$lines, $total] = $this->buildItems((array) $request->input('items'));
                if ($lines === []) {
                    throw new \RuntimeException('销售订单至少保留一条明细');
                }
                // 整表替换会换掉明细行 id，而已有发货明细（sales_delivery_item.order_item_id）
                // 与超发校验（按 order_item_id 汇总实发）都挂在行 id 上：一旦有实发，替换即留下对不上
                // 的孤儿发货行，且新行能再发满一次 → 重复出库。判据取发货明细表本身：
                // sales_order_item.delivered_quantity 全仓没有任何写入方（恒 0），拿它当判据是死代码
                $delivered = SalesDeliveryItem::query()
                    ->join('sales_order_item', 'sales_order_item.id', '=', 'sales_delivery_item.order_item_id')
                    ->where('sales_order_item.order_id', $id)
                    ->exists();
                if ($delivered) {
                    throw new \RuntimeException('该订单已有发货记录，明细不可整体替换');
                }
                SalesOrderItem::query()->where('order_id', $id)->delete();
                $this->saveItems($id, $lines);
                $item->fill(['total_amount' => $total]);
                $item->save();
            });
        } catch (\Throwable $e) {
            $this->logError('sales_order.update', $e);

            // 业务拒绝用 RuntimeException 表达 → 422；事务内的库故障（死锁/列不存在等
            // PDOException）仍 500，且不回显原始异常文本（PDO 消息含表名与 SQL 片段）
            $clientFault = ($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException)
                && !$e instanceof \PDOException;

            return $clientFault ? $this->fail($e->getMessage(), 422) : $this->failServer();
        }

        return $this->success($this->encodeIds($item->toArray()), $this->trans('Updated successfully'));
    }

    /**
     * 删除销售订单（软删除）
     */
    #[\erikwang2013\apidoc\annotation\Title('删除销售订单')]
    #[\erikwang2013\apidoc\annotation\Desc('根据ID软删除销售订单，需管理员密码二次确认')]
    #[\erikwang2013\apidoc\annotation\Method('DELETE')]
    #[\erikwang2013\apidoc\annotation\Author('erik')]
    #[\erikwang2013\apidoc\annotation\Tag('销售管理')]
    #[\erikwang2013\apidoc\annotation\Param(name:'id', type:'string', default:'', desc:'销售订单hashid')]
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
        $item = SalesOrder::find($id);
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
     * 明细行校验 + 金额计算（先算后写：金额合计要回写主表、也是信用控制的入参）。
     * 单一 product_id 口径与 purchase/OrderController::buildItems、RfqController::saveItems 一致：
     * hashid 双模解码，垃圾/空串即拒绝（写 0 会得到无商品的孤儿明细行，发货时按商品反查必然对不上）。
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
     * 明细落库：行 id 与主表同源（雪花）。delivered_quantity 落 0——注意发货链路并不回写此列
     * （实发累计在 sales_delivery_item 里按 order_item_id 现算），此列仅供列表展示
     *
     * @param list<array<string,int|string>> $lines buildItems 的产出
     */
    private function saveItems(int $orderId, array $lines): void
    {
        foreach ($lines as $line) {
            $item = new SalesOrderItem();
            $item->id = $this->generateId();
            $item->order_id = $orderId;
            $item->product_id = $line['product_id'];
            $item->sku_id = $line['sku_id'];
            $item->quantity = $line['quantity'];
            $item->delivered_quantity = '0.00';
            $item->price = $line['price'];
            $item->amount = $line['amount'];
            $item->unit = $line['unit'];
            $item->save();
        }
    }
}
