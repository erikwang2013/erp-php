# ERP 业务模块设计规范

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. 概述

在现有 `service/` 系统管理基座之上，扩展进销存、财务、CRM 三大业务域，构建完整 ERP 系统。
所有代码在 `service/app/` 下单体部署，模块按目录分层。

### 1.1 阶段规划

| 阶段 | 模块 | 说明 |
|------|------|------|
| Phase 1 | 商品基础数据 + 采购 + 销售 + 库存 + 财务 + CRM | 核心业务闭环 |
| Phase 2 | 制造管理 + 项目管理 | 后续扩展 |

### 1.2 技术栈（沿用现有）

- PHP 8.3+, webman v2, MySQL 8.0+
- 主键 BIGINT 由 snowflake-php 生成
- API 层 ID 用 hashids 加解密
- JWT 认证、敏感数据加密全部使用 erikwang2013/* 系列包
- 表前缀 `erik_`，软删除，全局函数不加 `\`

---

## 2. 项目结构

```
service/app/
├── admin/controller/          # 系统管理控制器（已有，保持不变）
├── api/v1/controller/         # 客户端API（已有 + 扩展）
├── common/                    # 共享工具（已有 Snowflake/Hashids/Encryption）
├── middleware/                # 全局中间件（已有7个）
├── model/                     # 所有数据模型（跨模块共享）
├── service/                   # 业务逻辑层（按模块分目录）
│   ├── product/               # 商品与基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── controller/                # 业务模块控制器
│   ├── product/               # 商品基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── queue/                     # 队列任务（已有 + 业务队列）
├── process/                   # 进程（已有 Http, Monitor）
└── functions.php              # 全局辅助函数（已有）
```

### 2.1 分层职责

| 层 | 文件位置 | 职责 |
|----|----------|------|
| Controller | `app/controller/{module}/` | 参数校验、响应格式化、调用 Service |
| Service | `app/service/{module}/` | 业务逻辑、跨模块联动、事务管理 |
| Model | `app/model/` | 数据模型、关联关系、查询作用域、encryptable trait |

---

## 3. 模块功能清单

### 3.1 商品与基础数据

| 功能 | 说明 |
|------|------|
| 商品档案 | 商品名、编码、条码、分类（树形）、品牌、规格属性 |
| 多规格 SKU | 同一商品多规格，各自独立 SKU、条码、价格 |
| 多单位换算 | 基本单位 ↔ 辅助单位换算率 |
| 价格策略 | 进货价、批发价、零售价、客户等级价 |
| 分类管理 | 无限级分类树，支持拖拽排序 |
| 品牌管理 | 品牌 CRUD |
| 仓库管理 | 多仓库，每个仓库多库位 |
| 库位管理 | 仓库下的存储位置，编码唯一 |
| 供应商档案 | 名称、联系人、电话、地址、银行账户、税率 |
| 客户档案 | 名称、联系人、电话、地址、客户等级、信用额度 |

### 3.2 采购模块

| 功能 | 说明 |
|------|------|
| 采购申请 | 部门/人员提交采购需求，支持审批流程 |
| 采购订单 | 基于申请或直接创建，关联供应商、商品、数量、单价 |
| 采购收货 | 按订单收货，生成入库单，支持分批收货 |
| 采购退货 | 退回供应商，生成出库单冲销 |
| 供应商对账 | 按供应商+时间段汇总采购金额、已付、应付 |
| 采购结算 | 核销采购收货与付款 |

### 3.3 销售模块

| 功能 | 说明 |
|------|------|
| 报价单 | 向客户报价，支持转销售订单 |
| 销售订单 | 客户下单，关联商品、数量、单价、折扣 |
| 销售发货 | 按订单发货，生成出库单，支持分批发货 |
| 销售退货 | 客户退貨，生成入库单冲销 |
| 客户对账 | 按客户+时间段汇总销售金额、已收、应收 |
| 销售结算 | 核销销售发货与收款 |
| 销售毛利 | 按订单/商品/客户维度计算毛利 |

### 3.4 库存模块

| 功能 | 说明 |
|------|------|
| 实时库存 | 仓库+库位+批次+SKU 维度库存量 |
| 批次追踪 | 生产日期、过期日期、批次号 |
| 序列号追踪 | 唯一序列号，出入库时记录 |
| 出入库流水 | 所有库存变动的统一日志（来源单号+类型+数量+方向） |
| 库存调拨 | 仓库间/库位间调拨，生成调拨出入库单 |
| 盘点任务 | 计划盘点（按仓库/分类）+ 动态盘点（按SKU） |
| 盘点差异 | 盘盈/盘亏自动生成出入库流水 |
| 库存预警 | 按SKU+仓库设置上下限，低于下限或高于上限告警 |
| 成本核算 | 移动加权平均法，每次入库重新计算成本价 |

### 3.5 财务模块

| 功能 | 说明 |
|------|------|
| 会计科目 | 科目树（资产/负债/权益/收入/费用），支持自定义 |
| 应收应付 | 由销售/采购单据自动生成，手动核销 |
| 收款单 | 多账户、多方式（现金/银行/微信/支付宝）收款 |
| 付款单 | 多账户、多方式付款 |
| 核销 | 收款单核销应收、付款单核销应付 |
| 现金银行日记账 | 按账户+日期记录收支流水 |
| 费用报销 | 提交→审批→打款，关联科目 |
| 利润表 | 按月汇总收入/成本/费用/利润 |

### 3.6 CRM 模块

| 功能 | 说明 |
|------|------|
| 客户管理 | 客户档案（与基础数据客户关联） |
| 联系人管理 | 客户下的多个联系人 |
| 跟进记录 | 跟进方式、时间、内容、下次跟进计划 |
| 销售漏斗 | 阶段配置 + 商机金额预估 + 阶段转化率 |

---

## 4. 数据库表设计

所有表 `erik_` 前缀，`id` BIGINT 非自增，含 `created_at`/`updated_at`/`deleted_at`。

### 4.1 商品基础数据

```
erik_product             商品主表
erik_product_sku         商品SKU/规格
erik_product_unit        多单位换算
erik_product_price       价格策略
erik_category            商品分类（树形 parent_id）
erik_brand               品牌
erik_warehouse           仓库
erik_location            库位
erik_supplier            供应商
erik_customer            客户
erik_customer_level      客户等级
```

### 4.2 采购模块

```
erik_purchase_apply       采购申请
erik_purchase_apply_item  申请明细
erik_purchase_order       采购订单
erik_purchase_order_item  订单明细
erik_purchase_receive     采购收货主表
erik_purchase_receive_item 收货明细
erik_purchase_return      采购退货主表
erik_purchase_return_item 退货明细
erik_purchase_settlement  供应商结算记录
```

### 4.3 销售模块

```
erik_sales_quotation      报价单主表
erik_sales_quotation_item 报价明细
erik_sales_order          销售订单主表
erik_sales_order_item     订单明细
erik_sales_delivery       销售发货主表
erik_sales_delivery_item  发货明细
erik_sales_return         销售退货主表
erik_sales_return_item    退货明细
erik_sales_settlement     客户结算记录
```

### 4.4 库存模块

```
erik_inventory            实时库存
erik_inventory_batch      批次信息
erik_inventory_serial     序列号记录
erik_inventory_flow       出入库流水
erik_transfer             调拨单主表
erik_transfer_item        调拨明细
erik_check_task           盘点任务
erik_check_detail         盘点明细
erik_inventory_alert_rule 库存预警规则
erik_inventory_alert_log  库存预警日志
erik_cost_record          成本计算记录
```

### 4.5 财务模块

```
erik_finance_account      会计科目
erik_finance_voucher      记账凭证
erik_finance_voucher_item 凭证分录
erik_finance_ar_ap        应收应付明细
erik_finance_receipt      收款单
erik_finance_payment      付款单
erik_finance_cash_journal 现金银行日记账
erik_finance_expense      费用报销单
erik_finance_expense_item 报销明细
erik_finance_profit       利润表快照
erik_finance_bank_account 银行账户
```

### 4.6 CRM 模块

```
erik_crm_funnel_stage     销售漏斗阶段配置
erik_crm_opportunity      商机
erik_crm_follow_record    跟进记录
erik_crm_contact          联系人
```

---

## 5. API 路由

沿用 `/admin/*` 命名空间，完整中间件链（Auth → Permission → OperationLog）。

```
# 商品基础数据
/admin/product/*          商品/分类/品牌 CRUD
/admin/warehouse/*        仓库/库位 CRUD
/admin/supplier/*         供应商 CRUD
/admin/customer/*         客户/客户等级 CRUD

# 采购
/admin/purchase/apply/*      采购申请 + 审批
/admin/purchase/order/*      采购订单
/admin/purchase/receive/*    采购收货
/admin/purchase/return/*     采购退货
/admin/purchase/settlement/* 供应商结算

# 销售
/admin/sales/quotation/*     报价单（含转订单）
/admin/sales/order/*         销售订单
/admin/sales/delivery/*      销售发货
/admin/sales/return/*        销售退货
/admin/sales/settlement/*    客户结算

# 库存
/admin/inventory/*           实时库存查询
/admin/inventory/batch/*     批次管理
/admin/inventory/serial/*    序列号管理
/admin/inventory/flow/*      出入库流水
/admin/inventory/transfer/*  调拨
/admin/inventory/check/*     盘点
/admin/inventory/alert/*     预警规则

# 财务
/admin/finance/account/*     会计科目
/admin/finance/voucher/*     记账凭证
/admin/finance/receipt/*     收款单
/admin/finance/payment/*     付款单
/admin/finance/cash/*        现金银行日记账
/admin/finance/expense/*     费用报销
/admin/finance/report/*      财务报表

# CRM
/admin/crm/opportunity/*     商机
/admin/crm/follow/*          跟进记录
/admin/crm/funnel/*          漏斗阶段配置
/admin/crm/contact/*         联系人

# 仪表盘（扩展）
/admin/dashboard/sales       销售面板
/admin/dashboard/inventory   库存面板
/admin/dashboard/finance     财务面板
```

客户端 API `/api/v1/*` 提供轻量接口（商品查询、下单、订单状态等），供 Flutter App / HarmonyOS 调用。

---

## 6. 跨模块数据流

```
采购收货 → inventory_flow(入库) → inventory(+数量) → cost_record(重算均价)
       → finance_ar_ap(应付)

销售发货 → inventory_flow(出库) → inventory(-数量) → cost_record(记录成本)
       → finance_ar_ap(应收)

收款单核销 → finance_ar_ap(已收更新) → cash_journal(收入记录)
付款单核销 → finance_ar_ap(已付更新) → cash_journal(支出记录)

盘点差异 → inventory_flow(盘盈入库/盘亏出库) → inventory(调整)

费用报销(已打款) → finance_payment(自动生成) → cash_journal(支出记录)
```

实现方式：每个业务操作完成后通过事件触发下游动作，不直接跨模块调用 Service。

---

## 7. Excel/PDF 导出

- 所有列表页面支持 `?export=excel` 参数，生成带样式的 .xlsx 文件
- 仪表盘面板支持 `?export=pdf`，输出含图表的 PDF 报告
- 敏感字段（金额、手机号等）导出时调用 EncryptionService 脱敏
- 复用现有 ExportController 基类，各模块控制器继承并实现自己的导出列定义

---

## 8. 仪表盘面板

| 面板 | 路由 | 指标 |
|------|------|------|
| 经营总览 | `/admin/dashboard` | 今日/本月销售额、采购额、应收/应付、库存总值、毛利 |
| 库存看板 | `/admin/dashboard/inventory` | 预警列表、出入库趋势、库位占用率 |
| 销售看板 | `/admin/dashboard/sales` | 趋势图、客户排行、商品热销、漏斗转化率 |
| 财务看板 | `/admin/dashboard/finance` | 收支趋势、应收应付账龄、现金流 |

数据 Redis 缓存 5 分钟，支持时间范围切换。

---

## 9. 前端设计

| 端 | 目录 | 框架 | 风格 |
|----|------|------|------|
| Web 管理后台 | `apps/flutter/` (web) | Flutter + GetX | PC 管理后台（侧边栏+顶栏+内容区） |
| 客户端 App | `apps/flutter/` (app) | Flutter + GetX | 移动端原生风格 |
| HarmonyOS | `apps/harmonyos/` | ArkTS | 鸿蒙原生，App 风格 |

Flutter 代码通过路由和布局判断区分 Web PC 端与移动端渲染。

---

## 10. 实现顺序

| 步骤 | 内容 | 依赖 |
|------|------|------|
| 1 | 数据库迁移 SQL（所有业务表） | 无 |
| 2 | Model 层（所有模块数据模型） | 步骤1 |
| 3 | 商品基础数据模块（CRUD） | 步骤2 |
| 4 | 采购模块 | 步骤3 |
| 5 | 销售模块 | 步骤3 |
| 6 | 库存模块 + 成本核算 | 步骤4,5 |
| 7 | 财务模块 | 步骤4,5,6 |
| 8 | CRM 模块 | 步骤3 |
| 9 | 仪表盘面板 | 步骤4-8 |
| 10 | Excel/PDF 导出 | 步骤4-9 |
| 11 | 客户端 API（/api/*） | 步骤4-8 |
| 12 | Flutter 前端页面 | 步骤4-10 |
| 13 | HarmonyOS 前端页面 | 步骤11 |
