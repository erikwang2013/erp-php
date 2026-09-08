/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, DOC_DICT, DOC_FILTER, moneyCol, statusCol, textCol } from '@/config/cells';
import { res, type MenuGroup } from '@/config/types';

import type { ActionDef } from '@/config/types';

const doc = (
  moduleKey: string,
  title: string,
  endpoint: string,
  action?: ActionDef,
) =>
  res(title, endpoint, {
    moduleKey,
    filters: DOC_FILTER,
    actions: action ? [action] : undefined,
    columns: [textCol('code', '编号', true), moneyCol('total_amount', '金额'), statusCol(DOC_DICT), dateCol('created_at', '创建时间')],
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
          filters: DOC_FILTER,
          columns: [textCol('code', '订单号', true), textCol('channel_name', '渠道'), textCol('customer_name', '客户'), moneyCol('total_amount', '金额'), statusCol(DOC_DICT), dateCol('created_at', '下单时间')],
          actions: [
            { label: '库存分配', icon: 'layers', path: (r) => `/admin/v1/oms/order/${String(r.id)}/allocate`, message: '已完成库存分配' },
            { label: '履约', icon: 'truck', path: (r) => `/admin/v1/oms/order/${String(r.id)}/fulfill`, message: '已生成履约单' },
            { label: '取消', icon: 'close', variant: 'icon-danger', path: (r) => `/admin/v1/oms/order/${String(r.id)}/cancel`, message: '订单已取消' },
          ],
        },
      },
      { label: '履约管理', path: '/oms/fulfillment', cfg: doc('oms', '履约管理', '/admin/v1/oms/fulfillment') },
      {
        label: '退换货 RMA',
        path: '/oms/rma',
        cfg: {
          title: '退换货 RMA',
          moduleKey: 'oms',
          endpoint: '/admin/v1/oms/rma',
          filters: DOC_FILTER,
          actions: [
            { label: '批准', icon: 'check', path: (r) => `/admin/v1/oms/rma/${String(r.id)}/approve`, message: '已批准' },
            { label: '退货入库', icon: 'download', path: (r) => `/admin/v1/oms/rma/${String(r.id)}/receive`, message: '已入库' },
            { label: '退款', icon: 'dollar', path: (r) => `/admin/v1/oms/rma/${String(r.id)}/refund`, message: '已退款' },
          ],
        },
      },
      { label: '渠道管理', path: '/oms/channel', cfg: res('销售渠道', '/admin/v1/oms/channel', { moduleKey: 'oms', fields: [{ key: 'name', label: '渠道名称', required: true }] }) },
    ],
  },
  {
    label: '仓储管理',
    icon: 'box',
    moduleKey: 'wms',
    children: [
      { label: '库区管理', path: '/wms/zone', cfg: res('库区管理', '/admin/v1/wms/zone', { moduleKey: 'wms', fields: [{ key: 'name', label: '库区名称', required: true }, { key: 'code', label: '库区编码' }] }) },
      { label: '库位管理', path: '/wms/location', cfg: res('库位管理', '/admin/v1/wms/location', { moduleKey: 'wms', fields: [{ key: 'code', label: '库位编码', required: true }, { key: 'name', label: '库位名称' }, { key: 'warehouse_id', label: '所属仓库', placeholder: 'hashid' }] }) },
      { label: '预到货 ASN', path: '/wms/asn', cfg: doc('wms', '预到货 ASN', '/admin/v1/wms/asn') },
      {
        label: '收货管理',
        path: '/wms/receiving',
        cfg: doc('wms', '收货管理', '/admin/v1/wms/receiving', { label: '收货完成', icon: 'check', path: (r) => `/admin/v1/wms/receiving/${String(r.id)}/complete`, message: '收货已完成' }),
      },
      {
        label: '上架管理',
        path: '/wms/putaway',
        cfg: doc('wms', '上架管理', '/admin/v1/wms/putaway', { label: '上架完成', icon: 'check', path: (r) => `/admin/v1/wms/putaway/${String(r.id)}/complete`, message: '上架已完成' }),
      },
      {
        label: '波次管理',
        path: '/wms/wave',
        cfg: doc('wms', '波次管理', '/admin/v1/wms/wave', { label: '波次释放', icon: 'send', path: (r) => `/admin/v1/wms/wave/${String(r.id)}/release`, message: '波次已释放' }),
      },
      {
        label: '拣货管理',
        path: '/wms/pick',
        cfg: doc('wms', '拣货管理', '/admin/v1/wms/pick', { label: '拣货确认', icon: 'check', path: (r) => `/admin/v1/wms/pick/${String(r.id)}/confirm`, message: '拣货已确认' }),
      },
      {
        label: '打包管理',
        path: '/wms/pack',
        cfg: doc('wms', '打包管理', '/admin/v1/wms/pack', { label: '打包完成', icon: 'check', path: (r) => `/admin/v1/wms/pack/${String(r.id)}/complete`, message: '打包已完成' }),
      },
    ],
  },
  {
    label: '运输管理',
    icon: 'truck',
    moduleKey: 'tms',
    children: [
      { label: '承运商', path: '/tms/carrier', cfg: res('承运商', '/admin/v1/tms/carrier', { moduleKey: 'tms', fields: [{ key: 'name', label: '承运商名称', required: true }, { key: 'code', label: '编码' }] }) },
      { label: '运输服务', path: '/tms/service', cfg: res('运输服务', '/admin/v1/tms/service', { moduleKey: 'tms', fields: [{ key: 'name', label: '服务名称', required: true }, { key: 'code', label: '编码' }] }) },
      { label: '运费费率', path: '/tms/freight-rate', cfg: res('运费费率', '/admin/v1/tms/freight-rate', { moduleKey: 'tms', fields: [{ key: 'carrier_service_id', label: '承运商服务', required: true, source: { endpoint: '/admin/v1/tms/service', labelKey: 'name' } }, { key: 'valid_from', label: '生效日期', required: true, type: 'date' }] }) },
      {
        label: '运单管理',
        path: '/tms/shipment',
        cfg: {
          title: '运单管理',
          moduleKey: 'tms',
          endpoint: '/admin/v1/tms/shipment',
          filters: DOC_FILTER,
          columns: [textCol('code', '运单号', true), textCol('carrier_name', '承运商'), moneyCol('freight_amount', '运费'), statusCol(DOC_DICT), dateCol('created_at', '创建时间')],
          actions: [
            { label: '发货', icon: 'send', path: (r) => `/admin/v1/tms/shipment/${String(r.id)}/ship`, message: '运单已发货' },
            { label: '获取面单', icon: 'file', path: (r) => `/admin/v1/tms/shipment/${String(r.id)}/get-label`, message: '面单已生成' },
          ],
        },
      },
      { label: '物流轨迹', path: '/tms/tracking', cfg: res('物流轨迹', '/admin/v1/tms/tracking', { moduleKey: 'tms' }) },
      {
        label: '运费发票',
        path: '/tms/freight-invoice',
        cfg: {
          title: '运费发票',
          moduleKey: 'tms',
          endpoint: '/admin/v1/tms/freight-invoice',
          actions: [
            { label: '确认', icon: 'check', path: (r) => `/admin/v1/tms/freight-invoice/${String(r.id)}/confirm`, message: '运费已确认' },
            { label: '支付', icon: 'dollar', path: (r) => `/admin/v1/tms/freight-invoice/${String(r.id)}/pay`, message: '运费已支付' },
          ],
        },
      },
    ],
  },
];
