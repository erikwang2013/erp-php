/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import {
  dateCol,
  DOC_DICT,
  DOC_FILTER,
  intCol,
  moneyCol,
  statusCol,
  textCol,
} from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';

/**
 * 采购 / 销售 / 库存。
 * 单据类资源的 columns 以「编号 | 往来单位 | 金额 | 状态 | 时间」为基线，
 * 与 Flutter 端五列契约一致。
 */

const docCols = (
  party: string,
  partyKey: string,
  extra: { key: string; title: string }[] = [],
) => [
  textCol('code', '编号', true),
  { key: partyKey, title: party },
  ...extra,
  moneyCol('total_amount', '金额'),
  statusCol(DOC_DICT),
  dateCol('created_at', '创建时间'),
];

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
          filters: DOC_FILTER,
          columns: docCols('申请部门', 'department_name', [intCol('total_count', '品项数')]),
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
          filters: DOC_FILTER,
          columns: docCols('供应商', 'supplier_name'),
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
          filters: DOC_FILTER,
          columns: docCols('供应商', 'supplier_name', [dateCol('receive_date', '收货日期')]),
          fields: [
            { key: 'order_id', label: '采购订单', required: true, source: { endpoint: '/admin/v1/purchase/order', labelKey: 'code' } },
            { key: 'supplier_id', label: '供应商', required: true, source: { endpoint: '/admin/v1/supplier' } },
            { key: 'warehouse_id', label: '仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } },
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
          filters: DOC_FILTER,
          columns: docCols('供应商', 'supplier_name'),
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
          columns: docCols('供应商', 'supplier_name'),
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
          filters: DOC_FILTER,
          columns: docCols('供应商', 'supplier_name'),
          actions: [
            { label: '比价', icon: 'chart', path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/compare` },
            { label: '中标转订单', icon: 'check', path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/award`, message: '已生成采购订单' },
            { label: '关闭', icon: 'close', path: (r) => `/admin/v1/purchase/rfq/${String(r.id)}/close`, message: '询价单已关闭' },
          ],
        },
      },
      { label: '供应商报价', path: '/purchase/rfq-quote', cfg: res('供应商报价', '/admin/v1/purchase/rfq-quote') },
      { label: '供应商评估', path: '/purchase/assessment', cfg: res('供应商评估', '/admin/v1/purchase/supplier-assessment') },
    ],
  },
  {
    label: '销售管理',
    icon: 'send',
    moduleKey: 'sales',
    children: [
      { label: '销售报价', path: '/sales/quotation', cfg: { title: '销售报价', moduleKey: 'sales', endpoint: '/admin/v1/sales/quotation', filters: DOC_FILTER, columns: docCols('客户', 'customer_name'), fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }] } },
      { label: '销售订单', path: '/sales/order', cfg: { title: '销售订单', moduleKey: 'sales', endpoint: '/admin/v1/sales/order', filters: DOC_FILTER, columns: docCols('客户', 'customer_name'), fields: [{ key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }] } },
      { label: '销售发货', path: '/sales/delivery', cfg: { title: '销售发货', moduleKey: 'sales', endpoint: '/admin/v1/sales/delivery', filters: DOC_FILTER, columns: docCols('客户', 'customer_name', [dateCol('delivery_date', '发货日期')]), fields: [{ key: 'order_id', label: '销售订单', required: true, source: { endpoint: '/admin/v1/sales/order', labelKey: 'code' } }, { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'warehouse_id', label: '仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'remark', label: '备注', type: 'textarea', full: true }] } },
      { label: '销售退货', path: '/sales/return', cfg: { title: '销售退货', moduleKey: 'sales', endpoint: '/admin/v1/sales/return', filters: DOC_FILTER, columns: docCols('客户', 'customer_name'), fields: [{ key: 'delivery_id', label: '发货单', required: true, source: { endpoint: '/admin/v1/sales/delivery', labelKey: 'code' } }, { key: 'customer_id', label: '客户', required: true, source: { endpoint: '/admin/v1/customer' } }, { key: 'warehouse_id', label: '退货仓库', required: true, source: { endpoint: '/admin/v1/warehouse' } }, { key: 'total_amount', label: '退货金额', type: 'number' }] } },
      { label: '销售结算', path: '/sales/settlement', cfg: { title: '销售结算', moduleKey: 'sales', endpoint: '/admin/v1/sales/settlement', columns: docCols('客户', 'customer_name'), fields: [{ key: 'delivery_id', label: '发货单', required: true, source: { endpoint: '/admin/v1/sales/delivery', labelKey: 'code' } }, { key: 'receipt_payment_id', label: '收款单', required: true, source: { endpoint: '/admin/v1/finance/receipt', labelKey: 'code' } }, { key: 'amount', label: '核销金额', required: true, type: 'number' }] } },
    ],
  },
  {
    label: '库存管理',
    icon: 'layers',
    moduleKey: 'inventory',
    children: [
      { label: '实时库存', path: '/inventory/stock', cfg: res('实时库存', '/admin/v1/inventory') },
      { label: '库存流水', path: '/inventory/flow', cfg: res('库存流水', '/admin/v1/inventory/flow') },
      { label: '库存调拨', path: '/inventory/transfer', cfg: { title: '库存调拨', moduleKey: 'inventory', endpoint: '/admin/v1/inventory/transfer', filters: DOC_FILTER, columns: docCols('调出仓库', 'from_warehouse_name', [dateCol('transfer_date', '调拨日期')]), fields: [{ key: 'code', label: '调拨单号' }, { key: 'remark', label: '备注', type: 'textarea', full: true }] } },
      { label: '盘点任务', path: '/inventory/check', cfg: { title: '盘点任务', moduleKey: 'inventory', endpoint: '/admin/v1/inventory/check', filters: DOC_FILTER, fields: [{ key: 'name', label: '盘点任务名称', required: true }, { key: 'code', label: '盘点单号' }] } },
      { label: '库存预警', path: '/inventory/alert', cfg: res('库存预警', '/admin/v1/inventory/alert', { fields: [{ key: 'product_id', label: '产品', required: true, source: { endpoint: '/admin/v1/product' } }, { key: 'sku_id', label: 'SKU ID', placeholder: '0=全部' }, { key: 'warehouse_id', label: '仓库', source: { endpoint: '/admin/v1/warehouse' } }, { key: 'min_quantity', label: '最小库存阈值', type: 'number' }, { key: 'max_quantity', label: '最大库存阈值', type: 'number' }, { key: 'enabled', label: '是否启用', type: 'select', defaultValue: 1, options: [{ label: '启用', value: 1 }, { label: '禁用', value: 0 }] }] }) },
      { label: '批次效期预警', path: '/inventory/expiry', cfg: res('批次效期预警', '/admin/v1/trace/expiry', { moduleKey: 'inventory', canDelete: false, params: { days: 90 } }) },
    ],
  },
];
