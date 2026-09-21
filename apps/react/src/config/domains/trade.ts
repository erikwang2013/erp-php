/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  dateCol,
  docStatus,
  intCol,
  moneyCol,
  statusCol,
  textCol,
} from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';

/**
 * 采购 / 销售 / 库存。
 * 单据类资源的 columns 以「编号 | 往来单位 | 金额 | 状态 | 时间」为基线，
 * 与 Flutter 端五列契约一致。状态枚举逐表不同（database/install.sql 的 status 列注释），各资源一份。
 */

/** 单据列基线：编号 | 往来单位 | 附加列 | 金额 | 状态 | 创建时间 */
const docCols = (
  party: string,
  partyKey: string,
  st: ReturnType<typeof docStatus>,
  extra: { key: string; title: string }[] = [],
) => [
  textCol('code', '编号', true),
  { key: partyKey, title: party },
  ...extra,
  moneyCol('total_amount', '金额'),
  statusCol(st.dict),
  dateCol('created_at', '创建时间'),
];

const APPLY = docStatus(['待审批', '已批准', '已驳回', '已转订单']);
const PORDER = docStatus(['待审核', '已审核', '部分收货', '已收货', '已取消']);
const PRECEIVE = docStatus(['待入库', '已入库']);
const PRETURN = docStatus(['待出库', '已出库']);
const RFQ = docStatus(['草稿', '已发布', '已中标', '已关闭', '已取消']);
const QUOTATION = docStatus(['草稿', '已报价', '已转订单', '已失效']);
const SORDER = docStatus(['待审核', '已审核', '部分发货', '已发货', '已取消']);
const SDELIVERY = docStatus(['待出库', '已出库']);
const SRETURN = docStatus(['待入库', '已入库']);
const TRANSFER = docStatus(['待调拨', '已调出', '已调入', '已完成']);
const CHECK = docStatus(['待盘点', '已盘点', '已处理']);
const SETTLE = docStatus(['未结算', '部分结算', '已结算']);

export const tradeMenus: MenuGroup[] = [
  {
    label: '采购管理',
    icon: 'cart',
    moduleKey: 'purchase',
    children: [
      {
        label: '采购申请',
        path: '/purchase/apply',
        cfg: {
          title: '采购申请',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/apply',
          deleteNeedsPassword: true,
          filters: APPLY.filter,
          // 列表无 join：无部门名/品项数/金额列（ApplyController::index 纯 toArray）
          columns: [
            textCol('code', '编号', true),
            textCol('department', '申请部门'),
            statusCol(APPLY.dict),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'apply_user_id', label: '申请人ID', required: true },
            { key: 'department', label: '申请部门' },
          ],
        },
      },
      {
        label: '采购订单',
        path: '/purchase/order',
        cfg: {
          title: '采购订单',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/order',
          deleteNeedsPassword: true,
          filters: PORDER.filter,
          columns: docCols('供应商', 'supplier_name', PORDER),
          fields: [
            { key: 'code', label: '订单编号', required: true },
            { key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } },
          ],
        },
      },
      {
        label: '采购收货',
        path: '/purchase/receive',
        cfg: {
          title: '采购收货',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/receive',
          deleteNeedsPassword: true,
          filters: PRECEIVE.filter,
          // 出参带嵌套 supplier（ReceiveController::with），无金额/调拨时间列
          columns: [
            textCol('code', '编号', true),
            textCol('supplier.name', '供应商'),
            statusCol(PRECEIVE.dict),
            dateCol('received_at', '收货日期'),
          ],
          fields: [
            { key: 'order_id', label: '采购订单', required: true, source: { endpoint: '/admin/v1/purchase/order', labelKey: 'code' } },
            { key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } },
            { key: 'warehouse_id', label: '仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } },
            {
              key: 'items',
              label: '收货明细',
              type: 'items',
              required: true,
              itemFields: [
                { key: 'product_id', label: '商品', required: true, source: { endpoint: '/admin/v1/product' } },
                { key: 'order_item_id', label: '订单明细行', required: true, help: '采购订单明细行的 hashid' },
                { key: 'quantity', label: '数量', required: true, type: 'number' },
                { key: 'price', label: '单价', required: true, type: 'number' },
              ],
            },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      {
        label: '采购退货',
        path: '/purchase/return',
        cfg: {
          title: '采购退货',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/return',
          deleteNeedsPassword: true,
          filters: PRETURN.filter,
          columns: docCols('供应商', 'supplier_name', PRETURN, [textCol('receive_code', '收货单')]),
          fields: [
            { key: 'receive_id', label: '收货单', required: true, source: { endpoint: '/admin/v1/purchase/receive', labelKey: 'code' } },
            { key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } },
            { key: 'warehouse_id', label: '退货仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'total_amount', label: '退货金额', type: 'number' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      {
        label: '采购结算',
        path: '/purchase/settlement',
        cfg: {
          title: '采购结算',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/settlement',
          deleteNeedsPassword: true,
          // 后端 select finance_ar_ap.*：无单号/供应商名/总额列，金额列名 amount
          columns: [
            textCol('receive_id', '收货单'),
            moneyCol('amount', '应付金额'),
            moneyCol('paid_amount', '已结'),
            statusCol(SETTLE.dict),
            dateCol('settled_at', '结算时间'),
          ],
          fields: [
            { key: 'receive_id', label: '收货单', required: true, source: { endpoint: '/admin/v1/purchase/receive', labelKey: 'code' } },
            { key: 'receipt_payment_id', label: '付款单', required: true, source: { endpoint: '/admin/v1/finance/payment', labelKey: 'code' } },
            { key: 'amount', label: '核销金额', required: true, type: 'number' },
          ],
        },
      },
      {
        label: '询价单',
        path: '/purchase/rfq',
        cfg: {
          title: '询价单',
          moduleKey: 'purchase',
          endpoint: '/admin/v1/purchase/rfq',
          deleteNeedsPassword: true,
          filters: RFQ.filter,
          // 表无 code（真列 rfq_no）、无供应商名（supplier_range 为说明文本）
          columns: [
            textCol('rfq_no', '询价单号', true),
            statusCol(RFQ.dict),
            dateCol('require_date', '需求日期'),
            dateCol('created_at', '创建时间'),
          ],
          actions: [
            // 后端只注册 GET /{id}/compare（默认 POST 会 404）
            { label: '比价', icon: 'chart', method: 'GET', showResult: true, path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/compare` },
            {
              label: '中标转订单',
              icon: 'check',
              path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/award`,
              bodyFields: [
                { key: 'quote_id', label: '中标报价', required: true, source: { endpoint: '/admin/v1/purchase/rfq-quote', labelKey: 'amount' } },
              ],
              message: '已生成采购订单',
            },
            { label: '关闭', icon: 'close', path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/close`, message: '询价单已关闭' },
          ],
        },
      },
      { label: '供应商报价', path: '/purchase/rfq-quote', cfg: res('供应商报价', '/admin/v1/purchase/rfq-quote', { deleteNeedsPassword: true }) },
      {
        label: '供应商评估',
        path: '/purchase/assessment',
        cfg: res('供应商评估', '/admin/v1/purchase/supplier-assessment', {
          deleteNeedsPassword: true,
          // 列表 leftJoin supplier 带出 supplier_name（SupplierAssessmentController::index）；grade 由后端按总分派生（gradeFor），故只读不可填
          columns: [textCol('supplier_name', '供应商', true), intCol('total_score', '总分'), textCol('grade', '等级'), dateCol('assessed_at', '评估日期'), textCol('remark', '备注')],
          // 后端必填 supplier_id + total_score（0-100）；dimensions(JSON) 需评分维度编辑器，暂不提供入口
          fields: [
            { key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier', labelKey: 'name' } },
            { key: 'total_score', label: '总分', required: true, type: 'number', help: '0-100；等级由后端派生' },
            { key: 'assessed_at', label: '评估日期', type: 'date' },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        }),
      },
    ],
  },
  {
    label: '销售管理',
    icon: 'send',
    moduleKey: 'sales',
    children: [
      { label: '销售报价', path: '/sales/quotation', cfg: { title: '销售报价', moduleKey: 'sales', endpoint: '/admin/v1/sales/quotation', deleteNeedsPassword: true, filters: QUOTATION.filter, columns: docCols('客户', 'customer_name', QUOTATION), fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }] } },
      { label: '销售订单', path: '/sales/order', cfg: { title: '销售订单', moduleKey: 'sales', endpoint: '/admin/v1/sales/order', deleteNeedsPassword: true, filters: SORDER.filter, columns: docCols('客户', 'customer_name', SORDER), fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }] } },
      { label: '销售发货', path: '/sales/delivery', cfg: { title: '销售发货', moduleKey: 'sales', endpoint: '/admin/v1/sales/delivery', deleteNeedsPassword: true, filters: SDELIVERY.filter, columns: [textCol('code', '编号', true), textCol('customer.name', '客户'), statusCol(SDELIVERY.dict), dateCol('delivered_at', '发货日期')], fields: [{ key: 'order_id', label: '销售订单', required: true, source: { endpoint: '/admin/v1/sales/order', labelKey: 'code' } }, { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'warehouse_id', label: '仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'items', label: '发货明细', type: 'items', required: true, itemFields: [{ key: 'product_id', label: '商品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'order_item_id', label: '订单明细行', required: true, help: '销售订单明细行的 hashid，须属于所选订单' }, { key: 'quantity', label: '数量', required: true, type: 'number' }, { key: 'price', label: '单价', required: true, type: 'number' }] }, { key: 'remark', label: '备注', type: 'textarea', full: true }] } },
      { label: '销售退货', path: '/sales/return', cfg: { title: '销售退货', moduleKey: 'sales', endpoint: '/admin/v1/sales/return', deleteNeedsPassword: true, filters: SRETURN.filter, columns: docCols('客户', 'customer_name', SRETURN), fields: [{ key: 'delivery_id', label: '发货单', required: true, source: { endpoint: '/admin/v1/sales/delivery', labelKey: 'code' } }, { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'warehouse_id', label: '退货仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'total_amount', label: '退货金额', type: 'number' }] } },
      { label: '销售结算', path: '/sales/settlement', cfg: { title: '销售结算', moduleKey: 'sales', endpoint: '/admin/v1/sales/settlement', deleteNeedsPassword: true, columns: [textCol('delivery_id', '发货单'), moneyCol('amount', '应收金额'), moneyCol('received_amount', '已收'), statusCol(SETTLE.dict), dateCol('settled_at', '结算时间')], fields: [{ key: 'delivery_id', label: '发货单', required: true, source: { endpoint: '/admin/v1/sales/delivery', labelKey: 'code' } }, { key: 'receipt_payment_id', label: '收款单', required: true, source: { endpoint: '/admin/v1/finance/receipt', labelKey: 'code' } }, { key: 'amount', label: '核销金额', required: true, type: 'number' }] } },
    ],
  },
  {
    label: '库存管理',
    icon: 'layers',
    moduleKey: 'inventory',
    children: [
      { label: '实时库存', path: '/inventory/stock', cfg: res('实时库存', '/admin/v1/inventory', { deleteNeedsPassword: true }) },
      { label: '库存流水', path: '/inventory/flow', cfg: res('库存流水', '/admin/v1/inventory/flow', { deleteNeedsPassword: true }) },
      { label: '库存调拨', path: '/inventory/transfer', cfg: { title: '库存调拨', moduleKey: 'inventory', endpoint: '/admin/v1/inventory/transfer', deleteNeedsPassword: true, filters: TRANSFER.filter, columns: [textCol('code', '编号', true), statusCol(TRANSFER.dict), dateCol('transferred_at', '调拨时间'), dateCol('created_at', '创建时间')], fields: [{ key: 'from_warehouse_id', label: '调出仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'to_warehouse_id', label: '调入仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'code', label: '调拨单号' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] } },
      { label: '盘点任务', path: '/inventory/check', cfg: { title: '盘点任务', moduleKey: 'inventory', endpoint: '/admin/v1/inventory/check', deleteNeedsPassword: true, filters: CHECK.filter, fields: [{ key: 'warehouse_id', label: '仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'code', label: '盘点单号' }] } },
      { label: '库存预警', path: '/inventory/alert', cfg: res('库存预警', '/admin/v1/inventory/alert', { deleteNeedsPassword: true, fields: [{ key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'sku_id', label: 'SKU ID', placeholder: '0=全部' }, { key: 'warehouse_id', label: '仓库', source: { endpoint: '/admin/v1/warehouse' } }, { key: 'min_quantity', label: '最小库存阈值', type: 'number' }, { key: 'max_quantity', label: '最大库存阈值', type: 'number' }, { key: 'enabled', label: '是否启用', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }] }) },
      { label: '批次效期预警', path: '/inventory/expiry', cfg: res('批次效期预警', '/admin/v1/trace/expiry', { moduleKey: 'inventory', canDelete: false, params: { days: 90 } }) },
    ],
  },
];
