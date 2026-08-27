# 表单字段核验清单（A3）

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 日期：2026-08-27 ｜ 依据：P5 规划 §2 工作组 A 条目 A3（P4 A8-1 无独立验收记录，需全量核验）
> 范围：Flutter 业务表单页 vs 后端控制器 store/update 参数契约（validator 规则 + 模型表列）
> 方法：抽查 13 个页面；表单取 `FormFieldConfig` 声名字段；后端契约取控制器 `store()` 的 `validator`/`$request->input` 与 `install.sql` 表列（模型多 `guarded` 仅 id/时间戳，即表列全量可写入）
> 口径：只列"后端存在但表单未提供"的关键业务字段（金额/日期/往来单位/数量等）；忽略通用 name/code；拿不准标"待核"

## 总览

| 模块 | 页面 | 表单字段 | 缺失关键字段 | 后端必填 | 建议 |
|------|------|----------|--------------|----------|------|
| 采购 | purchase/apply_list_page.dart | code, name | apply_user_id(申请人)、department(部门)、remark(备注)；表单 `name` 在后端无对应列 | 表 NOT NULL: code、apply_user_id；validator 仅要求 name | 补字段（申请人/部门），name 改传 apply_user_id |
| 采购 | purchase/order_list_page.dart | code, name, apply_id, supplier_id, warehouse_id, total_amount, ordered_at, remark, status | 无关键缺失（供应商/金额/日期/仓库均齐） | supplier_id 必填 | 保持 |
| 销售 | sales/quotation_list_page.dart | code, name | customer_id(客户)、total_amount(报价金额)、quoted_at(报价日期)、remark | 表 NOT NULL: code、customer_id | 补字段（客户/金额/日期） |
| 财务 | finance/voucher_list_page.dart | code, name, voucher_date, item_account_id, item_debit_amount, item_credit_amount, item_summary, status, remark | 无关键缺失（凭证日期/借贷金额明细齐） | 无强必填 | 保持 |
| 财务 | finance/payment_list_page.dart | code, name | supplier_id(供应商)、amount(金额)、bank_account_id(账户)、method(方式)、paid_at(付款日期)、remark | validator 必填: **supplier_id、amount** | 补字段（当前表单提交必 422） |
| 财务 | finance/receipt_list_page.dart | code, name | customer_id(客户)、amount(金额)、bank_account_id(账户)、method(方式)、received_at(收款日期)、remark | validator 必填: **customer_id、amount** | 补字段（当前表单提交必 422） |
| 财务 | finance/asset_list_page.dart | code, name | category(类别)、purchase_date(购置日期)、purchase_amount(原值)、salvage_value(残值)、useful_life(使用年限)、depreciation_method(折旧方式) | 表 NOT NULL: code、name | 补字段（资产入账核心字段全缺） |
| CRM | crm/opportunity_list_page.dart | code, name, amount, stage | customer_id(客户)、probability(赢率)、expected_close_date(预计成交)、owner_user_id(负责人)；`amount`/`stage` 与表列 `estimated_amount`/`stage_id` 字段名不对应 | 表 NOT NULL: customer_id、stage_id | 补字段 + 字段名对齐（待核） |
| CRM | crm/contract_list_page.dart | code, name | customer_id(客户)、total_amount(合同金额)、signed_at(签订日期)、start_date/end_date(起止日期)、owner_user_id(负责人) | validator 必填: **customer_id**；表 NOT NULL: name | 补字段（当前缺客户即 422） |
| HR | hr/attendance_page.dart | **无任何表单控件**（0 个 TextField/FormFieldConfig/Dialog） | 请假创建表单完全缺失：employee_id、type、start_date、end_date、days、reason | 表 NOT NULL: employee_id、type、start_date、end_date | 补页面（后端 AttendanceController store 已实现） |
| HR | hr/salary_page.dart | employee_id, period_year, period_month, base_salary, performance, overtime, deduction, tax | 无关键缺失（net_salary 由后端计算） | validator 必填: employee_id、period_year、period_month | 保持 |
| 库存 | inventory/transfer_list_page.dart | code, name | from_warehouse_id(调出仓)、to_warehouse_id(调入仓)、transferred_at(调拨日期)、remark | 表 NOT NULL: **from_warehouse_id、to_warehouse_id** | 补字段（当前表单提交必 DB 失败） |
| 库存 | inventory/check_list_page.dart | code, name | warehouse_id(盘点仓库)、type(盘点类型)、checked_at(盘点日期)、remark | 表 NOT NULL: **warehouse_id** | 补字段（当前表单提交必 DB 失败） |

## 统计

- 抽查页面：**13 个**（采购 2、销售 1、财务 5、CRM 2、HR 2、库存 2）
- 缺关键字段页数：**10 个**；字段齐备仅 3 个（purchase/order、finance/voucher、hr/salary）

## Top 缺口模式

1. **财务收付款页只有 code/name**（payment_list_page.dart、receipt_list_page.dart）：后端 validator 强制 supplier_id/customer_id + amount 必填，当前表单提交必然 422——两页均为不可用表单。
2. **表单退化为基础字段**：sales/quotation、finance/asset、crm/contract、inventory/transfer、inventory/check 五页仅 code/name，金额/日期/往来单位/仓库等核心业务字段全缺；其中 transfer/check 缺 NOT NULL 列，提交必 DB 报错。
3. **HR 请假创建表单在 Flutter 完全缺失**：hr/attendance_page.dart 无任何输入控件（0 匹配），后端 AttendanceController 的 leave store 已实现但前端无入口。
4. **字段名不对应（待核）**：crm/opportunity 表单 `amount`/`stage` vs 表列 `estimated_amount`/`stage_id`，提交后金额/阶段字段无法落库。

## 证据路径

- 后端契约：`app/controller/{purchase,sales,finance,crm,hr,inventory}/*Controller.php` store()（validator + $request->input）
- 表列：`database/install.sql`（erp_purchase_apply / erp_sales_quotation / erp_finance_voucher / erp_finance_payment / erp_finance_receipt / erp_finance_asset / erp_crm_opportunity / erp_crm_contract / erp_hr_leave / erp_hr_salary / erp_transfer / erp_check_task）
- 表单字段：`apps/flutter/lib/app/pages/**/*_list_page.dart` 的 `FormFieldConfig` 声明（共享组件 `apps/flutter/lib/app/widgets/form_dialog.dart`）
- 模型 mass-assign 放开：`app/model/*.php` guarded 仅 id/created_at/updated_at/deleted_at

## 待核项

- crm/opportunity 的 amount/stage 字段映射（是否另有换算逻辑）
- inventory/transfer 与 check 是否依赖明细子表单（TransferItem/CheckDetail）批量提交
