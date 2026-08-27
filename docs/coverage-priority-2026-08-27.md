# 覆盖缺口优先级清单 — 高价值动作端点（E2 选点依据）

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 来源：`php scripts/check-endpoints.php --doc=docs/endpoint-audit-2026-08-27.md` ②覆盖缺口清单（共 163 个，后端路由 571 条）
> 生成日期：2026-08-27　｜　用途：P5 规划 E2（前端深度覆盖，P2 批）的选点依据，只做前 10~15 个动作端点

## 筛选口径

- **只选动作端点**（POST/PUT/DELETE，含业务状态变更或数据创建），排除 GET 列表/详情（E3 详情页单独评估）与系统类端点（webhook、健康检查、安装向导、security.txt）。
- **按业务价值排序**：CRM 客户资产流转 > 财务期末处理 > 审批/薪资闭环 > BI 可视化 > OMS/WMS 履约链路。
- 每条注明价值理由与对应 Flutter 页面（`apps/flutter/lib/app/pages/` 下既有页面，页面存在但未调用该端点）。

## 优先级清单（15 项）

| # | 方法 | 端点 | 模块 | 价值理由 | 对应 Flutter 页面 |
|---|------|------|------|----------|-------------------|
| 1 | POST | `/admin/crm/pool/claim/{id}` | CRM 公海 | 公海认领是 CRM 客户流转核心动作，缺失则公海池只读，销售无法抢单 | `apps/flutter/lib/app/pages/crm/pool_page.dart` |
| 2 | POST | `/admin/crm/pool/release/{id}` | CRM 公海 | 客户释放回公海，配合认领构成公海池完整闭环（含超时回池治理） | `apps/flutter/lib/app/pages/crm/pool_page.dart` |
| 3 | POST | `/admin/crm/ticket/{id}/assign` | CRM 工单 | 工单指派负责人是服务响应 SLA 的起点，当前工单页只能读 | `apps/flutter/lib/app/pages/crm/ticket_list_page.dart` |
| 4 | POST | `/admin/crm/ticket/{id}/resolve` | CRM 工单 | 工单解决/关闭是服务工单生命周期终点，缺失则工单无法闭环 | `apps/flutter/lib/app/pages/crm/ticket_list_page.dart` |
| 5 | POST | `/admin/crm/analytics/generate` | CRM 分析 | 客户分析报表按需生成，是销售管理层决策入口 | `apps/flutter/lib/app/pages/crm/analytics_page.dart` |
| 6 | POST | `/admin/crm/analytics/metric` | CRM 分析 | 自定义分析指标创建，缺失则分析页只能看默认指标 | `apps/flutter/lib/app/pages/crm/analytics_page.dart` |
| 7 | POST | `/admin/bi/widget` | BI | 图表组件创建是"搭建看板"的原子操作，无此则看板不可配置 | `apps/flutter/lib/app/pages/bi/dashboard_list_page.dart` |
| 8 | PUT | `/admin/bi/widget/{id}` | BI | 图表配置更新（指标/维度/图表类型），看板迭代的日常操作 | `apps/flutter/lib/app/pages/bi/dashboard_list_page.dart` |
| 9 | DELETE | `/admin/bi/widget/{id}` | BI | 图表移除，看板管理的必要操作 | `apps/flutter/lib/app/pages/bi/dashboard_list_page.dart` |
| 10 | POST | `/admin/finance/report/consolidate` | 财务报告 | 期末合并/结转是结账流程关键动作，缺失则期末处理断链 | `apps/flutter/lib/app/pages/finance/report_page.dart` |
| 11 | POST | `/admin/finance/report/ratios` | 财务报告 | 财务比率分析（偿债/盈利/营运）是管理层报表核心 | `apps/flutter/lib/app/pages/finance/report_page.dart` |
| 12 | POST | `/admin/finance/asset/{id}/depreciate` | 固定资产 | 资产计提折旧按月操作，缺失则固定资产模块只有台账 | `apps/flutter/lib/app/pages/finance/asset_list_page.dart` |
| 13 | POST | `/admin/hr/salary/{id}/pay` | 薪资 | 薪资发放是 HR 模块最终交付动作，缺失则薪资只算不发 | `apps/flutter/lib/app/pages/hr/salary_page.dart` |
| 14 | POST | `/admin/hr/leave/{id}/approve` | 考勤 | 请假审批是 HR 审批闭环核心，缺失则请假单无法流转 | `apps/flutter/lib/app/pages/hr/leave_page.dart` |
| 15 | POST | `/admin/workflow/{id}/submit` | 审批流 | 通用工作流提交入口，补齐后各业务单据可复用审批链 | `apps/flutter/lib/app/pages/workflow/workflow_list_page.dart` |

## 备选（按需扩容，第 16~22 位）

| # | 方法 | 端点 | 模块 | 价值理由 | 对应 Flutter 页面 |
|---|------|------|------|----------|-------------------|
| 16 | POST | `/admin/hr/attendance/clock-in` | 考勤 | 移动打卡是考勤日常高频动作 | `apps/flutter/lib/app/pages/hr/attendance_page.dart` |
| 17 | POST | `/admin/finance/report/balance-sheet/save` | 财务报告 | 资产负债表样式/口径配置持久化 | `apps/flutter/lib/app/pages/finance/report_page.dart` |
| 18 | POST | `/admin/oms/order/{id}/allocate` | OMS | 订单库存预占分配，履约链路起点 | `apps/flutter/lib/app/pages/oms/order_list_page.dart` |
| 19 | POST | `/admin/oms/rma/{id}/refund` | OMS | 退货退款是售后闭环收尾动作 | `apps/flutter/lib/app/pages/oms/rma_list_page.dart` |
| 20 | POST | `/admin/wms/pick/{id}/start` | WMS | 拣货开始是出库执行链路动作 | `apps/flutter/lib/app/pages/wms/pick_page.dart` |
| 21 | POST | `/admin/quality/inspection/record` | QMS | 检验结果录入是质检流程核心动作 | `apps/flutter/lib/app/pages/quality/iqc_list_page.dart` |
| 22 | POST | `/admin/tms/freight-invoice/{id}/pay` | TMS | 运费发票付款，TMS 结算闭环 | `apps/flutter/lib/app/pages/tms/freight_invoice_page.dart` |

## 核销方式

E2 批完成后，对已实现的端点逐条在 `scripts/check-endpoints.php` ②清单中核销（重新生成审计报告，对应端点从缺口清单消失），并确保 Flutter 98 测试全绿。
