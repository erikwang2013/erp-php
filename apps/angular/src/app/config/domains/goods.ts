/*
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 */

import { dateCol, enabledCol, moneyCol, ST_FILTER, textCol } from '../cells';
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
          deleteNeedsPassword: true,
          // erp_product.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          // 显式列出列：inferColumns 按行键顺序取前 8 列，而 erp_product 的列序是
          // category_id/brand_id/code/name/barcode/spec/unit/image —— 正好占满 8 格，
          // 后端追加的 price 与 description/status 永远进不了列表（列不存在，不是空白）
          columns: [
            textCol('name', '商品名称', true),
            textCol('code', '商品编码'),
            textCol('category_name', '分类'),
            textCol('spec', '规格型号'),
            textCol('unit', '单位'),
            moneyCol('price', '价格'),
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          // 列表接口不带 skus，详情弹层按需补拉 GET /admin/v1/product/{id} 才有规格属性
          detailFetch: true,
          fields: [
            { key: 'name', label: '商品名称', required: true },
            { key: 'code', label: '商品编码', required: true },
            {
              key: 'category_id',
              label: '分类',
              required: true,
              source: { endpoint: '/admin/v1/category' },
            },
            { key: 'unit', label: '单位', required: true },
            { key: 'brand_id', label: '品牌', source: { endpoint: '/admin/v1/brand' } },
            { key: 'barcode', label: '条码' },
            // 规格型号改为从商品规格表选（valueKey 取 name：erp_product.spec 是 VARCHAR，
            // 存名称而非 hashid —— 列表/详情可直接显示，无需再解码）
            {
              key: 'spec',
              label: '规格型号',
              source: { endpoint: '/admin/v1/spec', valueKey: 'name' },
            },
            { key: 'description', label: '商品描述', type: 'textarea', full: true },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
      {
        label: '商品分类',
        path: '/product/category',
        cfg: res('商品分类', '/admin/v1/category', {
          deleteNeedsPassword: true,
          // erp_category.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          fields: [{ key: 'name', label: '分类名称', required: true }],
        }),
      },
      {
        label: '品牌管理',
        path: '/product/brand',
        cfg: res('品牌管理', '/admin/v1/brand', {
          deleteNeedsPassword: true,
          // erp_brand.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          fields: [
            { key: 'name', label: '品牌名称', required: true },
            { key: 'logo', label: 'LOGO 地址' },
            { key: 'description', label: '品牌描述', type: 'textarea', full: true },
            { key: 'sort', label: '排序', type: 'number', defaultValue: 0 },
            { key: 'status', label: '状态', type: 'select', defaultValue: 1, options: ON_OFF },
          ],
        }),
      },
      {
        label: '商品规格',
        path: '/product/spec',
        cfg: res('商品规格', '/admin/v1/spec', {
          deleteNeedsPassword: true,
          // erp_product_spec.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          // 显式列出列：inferColumns 只按行数据键推断，无法得知 attrs 该渲染成胶囊
          columns: [
            { key: 'name', title: '规格名称', primary: true },
            { key: 'attrs', title: '规格属性', kind: 'tags' },
            { key: 'sort', title: '排序', align: 'right' },
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'name', label: '规格名称', required: true },
            {
              key: 'attrs',
              label: '规格属性',
              type: 'textarea',
              full: true,
              help: 'JSON 对象：属性名 → 值数组，如 {"颜色":["红","蓝"],"尺寸":["S","M","L"]}；留空 / {} 表示无属性',
            },
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
          deleteNeedsPassword: true,
          filters: ST_FILTER,
          // erp_supplier.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          columns: [
            { key: 'name', title: '供应商', primary: true },
            { key: 'code', title: '编码' },
            { key: 'contact_person', title: '联系人' },
            { key: 'phone', title: '电话' },
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'name', label: '供应商名称', required: true },
            { key: 'code', label: '编码', required: true, help: '后端 NOT NULL，留空直接 500' },
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
          deleteNeedsPassword: true,
          filters: ST_FILTER,
          // erp_customer.status 注释「状态: 0=禁用 1=启用」；
          // credit_frozen 注释「信用冻结: 0=正常 1=冻结(阻断一切新销售单据)」
          dicts: { status: { 1: '启用', 0: '禁用' }, credit_frozen: { 0: '正常', 1: '冻结' } },
          columns: [
            { key: 'name', title: '客户', primary: true },
            { key: 'code', title: '编码' },
            // 裸对象列没有 kind：cellOf 落 default 支直出 row['level_id']（encodeIds 后的 hashid）。
            // 客户列表未 join customer_level（后端无 level_name），走 textCol → rel 支落「-」占位
            textCol('level_id', '等级'),
            { key: 'contact_person', title: '联系人' },
            { key: 'phone', title: '电话' },
            moneyCol('credit_limit', '信用额度'),
            enabledCol(),
            dateCol('created_at', '创建时间'),
          ],
          fields: [
            { key: 'name', label: '客户名称', required: true },
            { key: 'code', label: '编码', required: true, help: '后端 NOT NULL，留空直接 500' },
            { key: 'level_id', label: '等级', source: { endpoint: '/admin/v1/customer-level' } },
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
          deleteNeedsPassword: true,
          // erp_warehouse.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
          fields: [
            { key: 'name', label: '仓库名称', required: true },
            { key: 'code', label: '仓库编码', required: true, help: '后端 NOT NULL，留空直接 500' },
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
          deleteNeedsPassword: true,
          // erp_location.status 注释「状态: 0=禁用 1=启用」
          dicts: { status: { 1: '启用', 0: '禁用' } },
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
