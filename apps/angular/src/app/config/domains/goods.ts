/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, enabledCol, moneyCol, ST_FILTER } from '../cells';
import { res, type MenuGroup } from '../types';

/** 商品档案 + 往来单位 */

const ON_OFF = [
  { label: '启用', value: 1 },
  { label: '禁用', value: 0 },
];

export const goodsMenus: MenuGroup[] = [
  {
    label: '商品管理',
    icon: 'box',
    moduleKey: 'product',
    children: [
      {
        label: '商品列表',
        path: '/product/product',
        cfg: res('商品管理', '/admin/v1/product', {
          fields: [
            { key: 'name', label: '商品名称', required: true },
            { key: 'code', label: '商品编码', required: true },
            { key: 'category_id', label: '分类', required: true, source: { endpoint: '/admin/v1/category' } },
            { key: 'unit', label: '单位', required: true },
            { key: 'brand_id', label: '品牌', source: { endpoint: '/admin/v1/brand' } },
            { key: 'barcode', label: '条码' },
            { key: 'spec', label: '规格型号' },
            { key: 'description', label: '商品描述', type: 'textarea', full: true },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
      {
        label: '商品分类',
        path: '/product/category',
        cfg: res('商品分类', '/admin/v1/category', { fields: [{ key: 'name', label: '分类名称', required: true }] }),
      },
      {
        label: '品牌管理',
        path: '/product/brand',
        cfg: res('品牌管理', '/admin/v1/brand', {
          fields: [
            { key: 'name', label: '品牌名称', required: true },
            { key: 'logo', label: 'LOGO 地址' },
            { key: 'description', label: '品牌描述', type: 'textarea', full: true },
            { key: 'sort', label: '排序', type: 'number', defaultValue: 0 },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
    ],
  },
  {
    label: '往来单位',
    icon: 'users',
    moduleKey: 'partner',
    children: [
      {
        label: '供应商',
        path: '/partner/supplier',
        cfg: {
          title: '供应商',
          endpoint: '/admin/v1/supplier',
          filters: ST_FILTER,
          columns: [
            { key: 'name', title: '供应商', primary: true },
            { key: 'code', title: '编码' },
            { key: 'contact', title: '联系人' },
            { key: 'phone', title: '电话' },
            moneyCol('credit_limit', '信用额度'),
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'name', label: '供应商名称', required: true },
            { key: 'code', label: '编码' },
            { key: 'contact_person', label: '联系人' },
            { key: 'phone', label: '电话' },
            { key: 'email', label: '邮箱' },
            { key: 'address', label: '地址' },
            { key: 'bank_name', label: '开户行' },
            { key: 'bank_account', label: '银行账号' },
            { key: 'tax_number', label: '税号' },
            { key: 'tax_rate', label: '税率', type: 'number' },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      {
        label: '客户',
        path: '/partner/customer',
        cfg: {
          title: '客户',
          endpoint: '/admin/v1/customer',
          filters: ST_FILTER,
          columns: [
            { key: 'name', title: '客户', primary: true },
            { key: 'code', title: '编码' },
            { key: 'level', title: '等级' },
            { key: 'contact', title: '联系人' },
            { key: 'phone', title: '电话' },
            moneyCol('credit_limit', '信用额度'),
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'name', label: '客户名称', required: true },
            { key: 'code', label: '编码' },
            { key: 'level_id', label: '等级' },
            { key: 'contact_person', label: '联系人' },
            { key: 'phone', label: '电话' },
            { key: 'email', label: '邮箱' },
            { key: 'address', label: '地址' },
            { key: 'credit_limit', label: '信用额度', type: 'number' },
            { key: 'credit_days', label: '信用账期(天)', type: 'number' },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
            { key: 'remark', label: '备注', type: 'textarea', full: true },
          ],
        },
      },
      {
        label: '仓库',
        path: '/partner/warehouse',
        cfg: res('仓库管理', '/admin/v1/warehouse', {
          fields: [
            { key: 'name', label: '仓库名称', required: true },
            { key: 'code', label: '仓库编码' },
            { key: 'address', label: '地址' },
            { key: 'manager', label: '负责人' },
            { key: 'phone', label: '电话' },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
      {
        label: '库位',
        path: '/partner/location',
        cfg: res('库位管理', '/admin/v1/location', {
          fields: [
            { key: 'warehouse_id', label: '所属仓库', source: { endpoint: '/admin/v1/warehouse' } },
            { key: 'code', label: '库位编码' },
            { key: 'name', label: '库位名称', required: true },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
    ],
  },
];

