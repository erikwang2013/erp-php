/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, docStatus, mapText, moneyCol, statusCol, textCol } from '@/config/cells';
import { res, type DictMap, type MenuGroup } from '@/config/types';

import type { ActionDef } from '@/config/types';

/** 状态枚举逐表不同（database/install.sql 的 status 列注释），各自一份 */
const FULFILL = docStatus(['待处理', '分配中', '拣货中', '打包中', '待发货', '已发货', '已取消']);
const OMS = docStatus(['未分配', '已分配', '拣货中', '已打包', '已发货', '已签收']);
/** OMS 订单列表的 status 入参打的是销售订单状态（OrderController::index:69 where sales_order.status），与 fulfillment_status 不同表 */
const SALES_ORDER = docStatus(['待审核', '已审核', '部分发货', '已发货', '已取消']);
const RMA = docStatus(['待审核', '已批准', '已退回', '已收货', '已退款', '已拒绝']);
const ASN = docStatus(['待收货', '收货中', '已收货', '已上架']);
const RECEIVING = docStatus(['待收货', '收货中', '已完成', '已质检']);
const PUTAWAY = docStatus(['待上架', '上架中', '已完成']);
const WAVE = docStatus(['待处理', '处理中', '已完成']);
const PICK = docStatus(['待拣货', '拣货中', '已完成', '已取消']);
const PACK = docStatus(['待打包', '打包中', '已完成']);
const SHIPMENT = docStatus(['待发货', '已取件', '运输中', '已送达', '异常', '已退回']);
const FREIGHT = docStatus(['待审核', '已确认', '已付款']);

/** erp_oms_order.channel 渠道: manual/web/mobile/api/marketplace/edi/pos（列是显式列，字典须挂在列的 render 上才生效；
 *  EDI/POS 是语言中立值，与 HarmonyOS string.json 的 oms_channel_edi/oms_channel_pos 逐字同文案） */
const OMS_ORDER_CHANNEL: Record<string, string> = {
  manual: '手工',
  web: '网页',
  mobile: '移动端',
  api: '接口',
  marketplace: '电商平台',
  edi: 'EDI',
  pos: 'POS',
};

/** 单据列基线：编号 | 状态 | 创建时间（WMS/TMS 各表无金额列）；dicts 供详情抽屉的枚举键（同 cfg.dicts） */
const doc = (
  moduleKey: string,
  title: string,
  endpoint: string,
  st: ReturnType<typeof docStatus>,
  actions?: ActionDef | ActionDef[],
  columns?: { key: string; title: string }[],
  dicts?: DictMap,
) =>
  res(title, endpoint, {
    moduleKey,
    deleteNeedsPassword: true,
    filters: st.filter,
    actions: actions ? (Array.isArray(actions) ? actions : [actions]) : undefined,
    columns: columns ?? [textCol('code', '编号', true), statusCol(st.dict), dateCol('created_at', '创建时间')],
    dicts,
  });

export const fulfillMenus: MenuGroup[] = [
  {
    label: '订单管理',
    icon: 'clipboard',
    moduleKey: 'oms',
    children: [
      {
        label: 'OMS 订单',
        path: '/oms/order',
        cfg: {
          title: 'OMS 订单',
          moduleKey: 'oms',
          endpoint: '/admin/v1/oms/order',
          deleteNeedsPassword: true,
          filters: SALES_ORDER.filter,
          // 列没覆盖的枚举键只在详情抽屉露面，逐键字典取自 install.sql 该列注释（禁止跨表复用）
          dicts: {
            // 筛选键：erp_oms_order 无 status 列、回包不含该键（OrderController::index 的 select 只有 oms_order.* + sales_order.code），
            // 但筛选 options 声明的 0..4 就是关联销售订单状态枚举 —— 与筛选同源声明（与 Angular 同源），避免「能提交却显示不了」的码
            status: SALES_ORDER.dict,
            // erp_oms_order.priority：优先级: 1=最高 5=正常 9=最低
            priority: { 1: '最高', 5: '正常', 9: '最低' },
            // erp_oms_order.payment_status：支付状态: 0=待支付 1=已支付 2=部分退款 3=已退款
            payment_status: { 0: '待支付', 1: '已支付', 2: '部分退款', 3: '已退款' },
          },
          // 表无客户名/总额列；单号来自 leftJoin sales_order.code，状态列为 fulfillment_status
          columns: [
            textCol('code', '订单号', true),
            { key: 'channel', title: '渠道', render: (r) => mapText(r.channel, OMS_ORDER_CHANNEL) },
            moneyCol('shipping_fee', '运费'),
            statusCol(OMS.dict, 'fulfillment_status'),
            dateCol('created_at', '下单时间'),
          ],
          actions: [
            {
              label: '库存分配',
              icon: 'layers',
              path: (r) => (Number(r.fulfillment_status) === 0 ? `/admin/v1/oms/order/${String(r.id)}/allocate` : null),
              bodyFields: [
                {
                  key: 'items',
                  label: '分配明细',
                  type: 'items',
                  required: true,
                  itemFields: [
                    { key: 'product_id', label: '商品', required: true, source: { endpoint: '/admin/v1/product' } },
                    { key: 'quantity', label: '数量', required: true, type: 'number' },
                    { key: 'warehouse_id', label: '仓库', source: { endpoint: '/admin/v1/warehouse' } },
                  ],
                },
              ],
              message: '已完成库存分配',
            },
            // 发货仓库必填，缺省直接 422（OrderController::fulfill）
            { label: '履约', icon: 'truck', path: (r) => `/admin/v1/oms/order/${String(r.id)}/fulfill`, bodyFields: [{ key: 'warehouse_id', label: '发货仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }], message: '已生成履约单' },
            { label: '取消', icon: 'close', variant: 'icon-danger', path: (r) => (Number(r.fulfillment_status) >= 4 ? null : `/admin/v1/oms/order/${String(r.id)}/cancel`), message: '订单已取消' },
          ],
        },
      },
      { label: '履约管理', path: '/oms/fulfillment', cfg: doc('oms', '履约管理', '/admin/v1/oms/fulfillment', FULFILL, undefined, [textCol('warehouse_name', '发货仓库'), statusCol(FULFILL.dict), dateCol('created_at', '创建时间')]) },
      {
        label: '退换货 RMA',
        path: '/oms/rma',
        cfg: {
          title: '退换货 RMA',
          moduleKey: 'oms',
          endpoint: '/admin/v1/oms/rma',
          deleteNeedsPassword: true,
          // 后端必填 customer_id + order_id（install.sql erp_oms_rma 两列 NOT NULL 无默认，RmaController::store 不解码、不兜底）
          fields: [
            { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } },
            { key: 'order_id', label: '关联订单', required: true, source: { endpoint: '/admin/v1/sales/order', labelKey: 'code' } },
            // 类型值域取自 install.sql 该列注释：1=退货 2=换货 3=维修
            { key: 'type', label: '退换类型', type: 'select', defaultValue: 1, options: [{ label: '退货', value: 1 }, { label: '换货', value: 2 }, { label: '维修', value: 3 }] },
            { key: 'code', label: 'RMA 单号' },
            { key: 'refund_amount', label: '退款金额', type: 'number' },
            { key: 'reason', label: '原因', type: 'textarea', full: true, help: '最多 200 字' },
          ],
          filters: RMA.filter,
          columns: [textCol('code', '编号', true), moneyCol('refund_amount', '退款金额'), statusCol(RMA.dict), dateCol('created_at', '创建时间')],
          // erp_oms_rma.type：类型: 1=退货 2=换货 3=维修（列不在 columns 里，只有详情抽屉看得到）
          dicts: { type: { 1: '退货', 2: '换货', 3: '维修' } },
          // 门控与 RmaController 一致：批准仅 0、入库仅 2、退款仅 1|3
          actions: [
            { label: '批准', icon: 'check', path: (r) => (Number(r.status) === 0 ? `/admin/v1/oms/rma/${String(r.id)}/approve` : null), message: '已批准' },
            { label: '退货入库', icon: 'download', path: (r) => (Number(r.status) === 2 ? `/admin/v1/oms/rma/${String(r.id)}/receive` : null), message: '已入库' },
            { label: '退款', icon: 'dollar', path: (r) => ([1, 3].includes(Number(r.status)) ? `/admin/v1/oms/rma/${String(r.id)}/refund` : null), message: '已退款' },
          ],
        },
      },
      // erp_channel.status：状态: 0=禁用 1=启用（不写 columns 的推断页，不给字典就裸出原值 —— 引擎「通用档」兜底 2026-09-22 已删，不会再误标「已生效」）
      // erp_channel.type：类型: direct/marketplace/edi/pos（改前裸出 direct；与 erp_oms_order.channel 不同表，禁止跨表复用）
      { label: '渠道管理', path: '/oms/channel', cfg: res('销售渠道', '/admin/v1/oms/channel', { moduleKey: 'oms', deleteNeedsPassword: true, fields: [{ key: 'name', label: '渠道名称', required: true }, { key: 'code', label: '渠道编码', required: true }], dicts: { status: { 1: '启用', 0: '禁用' }, type: { direct: '直销', marketplace: '电商平台', edi: 'EDI', pos: 'POS' } } }) },
    ],
  },
  {
    label: '仓储管理',
    icon: 'box',
    moduleKey: 'wms',
    children: [
      // 逐键字典：erp_wms_zone.type 类型: 1=收货区 2=存储区 3=拣货区 4=打包区 5=发货区 6=退货区 7=质检区
      //           erp_wms_zone.status 状态: 0=禁用 1=启用（type 改前裸出数字 1；status 改前落通用档误标「已生效」）
      { label: '库区管理', path: '/wms/zone', cfg: res('库区管理', '/admin/v1/wms/zone', { moduleKey: 'wms', deleteNeedsPassword: true, fields: [{ key: 'warehouse_id', label: '所属仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'code', label: '库区编码', required: true }, { key: 'name', label: '库区名称', required: true }], dicts: { type: { 1: '收货区', 2: '存储区', 3: '拣货区', 4: '打包区', 5: '发货区', 6: '退货区', 7: '质检区' }, status: { 1: '启用', 0: '禁用' } } }) },
      // 逐键字典：erp_wms_location.status 状态: 1=可用 0=禁用（改前落通用档误标「已生效/待处理」；与其它表的「启用」不同名，禁止跨表复用）
      { label: '库位管理', path: '/wms/location', cfg: res('库位管理', '/admin/v1/wms/location', { moduleKey: 'wms', deleteNeedsPassword: true, fields: [{ key: 'location_id', label: '所属库位', required: true, source: { endpoint: '/admin/v1/location' } }, { key: 'zone_id', label: '库区', required: true, source: { endpoint: '/admin/v1/wms/zone' } }, { key: 'bin', label: '货位' }, { key: 'barcode', label: '条码' }], dicts: { status: { 1: '可用', 0: '禁用' } } }) },
      {
        label: '预到货 ASN',
        path: '/wms/asn',
        cfg: doc('wms', '预到货 ASN', '/admin/v1/wms/asn', ASN, {
          label: '生成收货任务',
          icon: 'download',
          // 仅待收货可开工（AsnController::start → WmsInboundService::startReceiving）
          path: (r) => (Number(r.status) === 0 ? `/admin/v1/wms/asn/${String(r.id)}/receive` : null),
          message: '已生成收货任务',
        }),
      },
      {
        label: '收货管理',
        path: '/wms/receiving',
        cfg: doc('wms', '收货管理', '/admin/v1/wms/receiving', RECEIVING, [
          {
            label: '开始收货',
            icon: 'download',
            // 仅待收货可开工（ReceivingController::start 条件 UPDATE status 0→1）
            path: (r) => (Number(r.status) === 0 ? `/admin/v1/wms/receiving/${String(r.id)}/start` : null),
            message: '收货已开始',
          },
          {
            label: '收货完成',
            icon: 'check',
            path: (r) => (Number(r.status) === 1 ? `/admin/v1/wms/receiving/${String(r.id)}/complete` : null),
            // 后端必填实收明细（WmsInboundService::completeReceiving:104-106 按 product_id+sku_id 回填 received_quantity，无兜底）
            // 注意 items 服务端不解码（ReceivingController::complete:242 原样透传）：product_id 的 hashid 需后端补 decodeFlexibleId
            bodyFields: [
              {
                key: 'items',
                label: '实收明细',
                type: 'items',
                required: true,
                itemFields: [
                  { key: 'product_id', label: '商品', required: true, source: { endpoint: '/admin/v1/product' } },
                  { key: 'sku_id', label: 'SKU', help: '数字 ID 或 hashid，留空=不分规格' },
                  { key: 'received_quantity', label: '实收数量', required: true, type: 'number' },
                ],
              },
            ],
            message: '收货已完成',
          },
        ], [textCol('code', '编号', true), statusCol(RECEIVING.dict), dateCol('received_at', '收货完成时间')]),
      },
      {
        label: '上架管理',
        path: '/wms/putaway',
        cfg: doc('wms', '上架管理', '/admin/v1/wms/putaway', PUTAWAY, [
          {
            label: '开始上架',
            icon: 'download',
            // 仅待上架可开工（PutawayController::start → WmsInboundService::startPutaway）
            path: (r) => (Number(r.status) === 0 ? `/admin/v1/wms/putaway/${String(r.id)}/start` : null),
            message: '上架已开始',
          },
          {
            label: '上架完成',
            icon: 'check',
            path: (r) => (Number(r.status) === 1 ? `/admin/v1/wms/putaway/${String(r.id)}/complete` : null),
            message: '上架已完成',
          },
        ], [textCol('code', '编号', true), statusCol(PUTAWAY.dict), dateCol('completed_at', '完成时间')], {
          // erp_wms_putaway_task.strategy 上架策略: fifo/lifo/zone_fixed/abc（列不在 columns 里，只有详情抽屉看得到）
          strategy: { fifo: '先进先出', lifo: '后进先出', zone_fixed: '固定货位', abc: 'ABC分类' },
        }),
      },
      {
        label: '波次管理',
        path: '/wms/wave',
        cfg: doc('wms', '波次管理', '/admin/v1/wms/wave', WAVE, {
          label: '波次释放',
          icon: 'send',
          path: (r) => (Number(r.status) === 0 ? `/admin/v1/wms/wave/${String(r.id)}/release` : null),
          message: '波次已释放',
        }, undefined, {
          // erp_wms_wave.type 类型: 1=拣货波次 2=发货波次；erp_wms_wave.priority 优先级: 1=最高 5=正常
          type: { 1: '拣货波次', 2: '发货波次' },
          priority: { 1: '最高', 5: '正常' },
        }),
      },
      {
        label: '拣货管理',
        path: '/wms/pick',
        cfg: doc('wms', '拣货管理', '/admin/v1/wms/pick', PICK, {
          label: '拣货确认',
          icon: 'check',
          path: (r) => (Number(r.status) === 1 ? `/admin/v1/wms/pick/${String(r.id)}/confirm` : null),
          message: '拣货已确认',
        }, undefined, {
          // erp_wms_pick_task.type 类型: 1=按单拣货 2=批量拣货 3=分区拣货 4=波次拣货
          // erp_wms_pick_task.priority 优先级: 1=最高 5=正常
          type: { 1: '按单拣货', 2: '批量拣货', 3: '分区拣货', 4: '波次拣货' },
          priority: { 1: '最高', 5: '正常' },
        }),
      },
      {
        label: '打包管理',
        path: '/wms/pack',
        cfg: doc('wms', '打包管理', '/admin/v1/wms/pack', PACK, {
          label: '打包完成',
          icon: 'check',
          path: (r) => (Number(r.status) === 1 ? `/admin/v1/wms/pack/${String(r.id)}/complete` : null),
          message: '打包已完成',
        }, undefined, {
          // erp_wms_pack_task.package_type 包装类型: box/bag/pallet/envelope（与 HarmonyOS string.json 的 pack_type_* 逐字同文案）
          package_type: { box: '纸箱', bag: '袋子', pallet: '托盘', envelope: '信封' },
        }),
      },
    ],
  },
  {
    label: '运输管理',
    icon: 'truck',
    moduleKey: 'tms',
    children: [
      // erp_tms_carrier.status 状态: 0=禁用 1=启用（推断页，不给字典就裸出原值 —— 引擎「通用档」兜底 2026-09-22 已删，不会再误标「已生效」）
      // erp_tms_carrier.type 类型: express/ltl/ftl/air/ocean/rail；erp_tms_carrier.api_provider API供应商: custom/shippo/afterShip/17track
      // （api_provider 的 shippo/afterShip/17track 是供应商名，保持拉丁原文）
      { label: '承运商', path: '/tms/carrier', cfg: res('承运商', '/admin/v1/tms/carrier', { moduleKey: 'tms', deleteNeedsPassword: true, fields: [{ key: 'name', label: '承运商名称', required: true }, { key: 'code', label: '编码', required: true }], dicts: { status: { 1: '启用', 0: '禁用' }, type: { express: '快递', ltl: '零担', ftl: '整车', air: '空运', ocean: '海运', rail: '铁路' }, api_provider: { custom: '自定义', shippo: 'Shippo', afterShip: 'AfterShip', '17track': '17TRACK' } } }) },
      // erp_tms_carrier_service.status 状态: 0=禁用 1=启用（改前落通用档误标「已生效」）
      // erp_tms_carrier_service.type 服务类型: standard/express/overnight/2day/economy（与 erp_tms_carrier.type 不同表，禁止跨表复用）
      { label: '运输服务', path: '/tms/service', cfg: res('运输服务', '/admin/v1/tms/service', { moduleKey: 'tms', deleteNeedsPassword: true, fields: [{ key: 'name', label: '服务名称', required: true }, { key: 'carrier_id', label: '承运商', required: true, source: { endpoint: '/admin/v1/tms/carrier', labelKey: 'name' } }, { key: 'code', label: '服务编码', required: true }], dicts: { status: { 1: '启用', 0: '禁用' }, type: { standard: '标准', express: '加急', overnight: '次日达', '2day': '两日达', economy: '经济' } } }) },
      // erp_tms_freight_rate.status 状态: 0=禁用 1=启用（改前落通用档误标「已生效」；该表无 type 列）
      { label: '运费费率', path: '/tms/freight-rate', cfg: res('运费费率', '/admin/v1/tms/freight-rate', { moduleKey: 'tms', deleteNeedsPassword: true, fields: [{ key: 'carrier_service_id', label: '承运商服务', required: true, source: { endpoint: '/admin/v1/tms/service', labelKey: 'name' } }, { key: 'valid_from', label: '生效日期', required: true, type: 'date' }], dicts: { status: { 1: '启用', 0: '禁用' } } }) },
      {
        label: '运单管理',
        path: '/tms/shipment',
        cfg: {
          title: '运单管理',
          moduleKey: 'tms',
          endpoint: '/admin/v1/tms/shipment',
          deleteNeedsPassword: true,
          filters: SHIPMENT.filter,
          // 列表无承运商名 join；金额真列 freight_charge
          columns: [textCol('code', '运单号', true), textCol('tracking_no', '物流单号'), moneyCol('freight_charge', '运费'), statusCol(SHIPMENT.dict), dateCol('created_at', '创建时间')],
          actions: [
            {
              label: '发货',
              icon: 'send',
              path: (r) => (Number(r.status) === 0 ? `/admin/v1/tms/shipment/${String(r.id)}/ship` : null),
              // 后端必填履约单与 OMS 订单（WmsOutboundService::confirmShip 按此扣减库存）
              bodyFields: [
                { key: 'fulfillment_id', label: '履约单', required: true, source: { endpoint: '/admin/v1/oms/fulfillment', labelKey: 'warehouse_name' } },
                { key: 'oms_order_id', label: 'OMS 订单', required: true, source: { endpoint: '/admin/v1/oms/order', labelKey: 'code' } },
              ],
              message: '运单已发货',
            },
            { label: '获取面单', icon: 'file', path: (r) => `/admin/v1/tms/shipment/${String(r.id)}/get-label`, message: '面单已生成' },
          ],
        },
      },
      // erp_tms_tracking_event.status_code 状态码: picked_up/in_transit/out_for_delivery/delivered/exception
      // （改前裸出 picked_up；键不以 _status 结尾，走的是 mapText 支；与 HarmonyOS string.json 的 tracking_status_* 逐字同文案）
      { label: '物流轨迹', path: '/tms/tracking', cfg: res('物流轨迹', '/admin/v1/tms/tracking', { moduleKey: 'tms', deleteNeedsPassword: true, dicts: { status_code: { picked_up: '已取件', in_transit: '运输中', out_for_delivery: '派送中', delivered: '已送达', exception: '异常' } } }) },
      {
        label: '运费发票',
        path: '/tms/freight-invoice',
        cfg: {
          title: '运费发票',
          moduleKey: 'tms',
          endpoint: '/admin/v1/tms/freight-invoice',
          deleteNeedsPassword: true,
          // 后端必填 carrier_id + shipment_id（install.sql erp_tms_freight_invoice 两列 NOT NULL 无默认；FreightInvoiceController::store 不解码、不兜底）
          fields: [
            { key: 'carrier_id', label: '承运商', required: true, source: { endpoint: '/admin/v1/tms/carrier', labelKey: 'name' } },
            // 选项名用 code（内部运单号，唯一非空）而非 tracking_no（'取件前为空' → 下拉出现空标签）
            { key: 'shipment_id', label: '运单', required: true, source: { endpoint: '/admin/v1/tms/shipment', labelKey: 'code' } },
            { key: 'code', label: '发票号' },
            { key: 'amount', label: '金额', type: 'number' },
            { key: 'currency', label: '币种', placeholder: 'CNY' },
            { key: 'invoice_date', label: '发票日期', type: 'date' },
            { key: 'due_date', label: '到期日', type: 'date' },
          ],
          filters: FREIGHT.filter,
          columns: [textCol('code', '编号', true), moneyCol('amount', '金额'), statusCol(FREIGHT.dict), dateCol('invoice_date', '开票日期')],
          // 支付门控 status===1（FreightInvoiceController::pay）
          actions: [
            { label: '确认', icon: 'check', path: (r) => (Number(r.status) === 0 ? `/admin/v1/tms/freight-invoice/${String(r.id)}/confirm` : null), message: '运费已确认' },
            { label: '支付', icon: 'dollar', path: (r) => (Number(r.status) === 1 ? `/admin/v1/tms/freight-invoice/${String(r.id)}/pay` : null), message: '运费已支付' },
          ],
        },
      },
    ],
  },
];
