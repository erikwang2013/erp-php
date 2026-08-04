# ERP 生态系统全量路线图 — 设计规范

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 基于 2026-08-04 生态审查报告制定，覆盖 P0～P3 四个优先级阶段

---

## 1. 当前基线

| 维度 | 现状 | 评分 |
|------|------|------|
| 后端 API | 14 模块 / 80+ 控制器 / 120+ 模型，多模块 CRUD 骨架 | 85/100 |
| 安全防护 | 18 层纵深防御，CORS/SecurityFilter/RateLimit/JWT/加密 | 95/100 |
| 前端 UI | Flutter 12 页、HarmonyOS 9 页，覆盖约 20% 模块；Web 管理面板缺失 | 20/100 |
| 运维生态 | Docker 化、CI 完成，缺迁移回滚、备份自动化、可观测性 | 70/100 |
| 业务深度 | 财务/HR/制造模块表结构完善但业务逻辑以 CRUD 为主 | 55/100 |
| **综合** | | **65/100** |

---

## 2. 总体策略

```
串行瀑布: P0 → P1 → P2 → P3
每个阶段内有独立性的子任务可并行推进
```

### 2.1 前端技术选型

- **Web 管理面板**：Flutter Web，复用 `apps/flutter` 现有代码，PC 管理后台风格，GetX 状态管理
- **移动端**：Flutter (iOS/Android)，与 Web 共享 `apps/flutter/lib/app/` 业务代码
- **HarmonyOS**：ArkTS，对齐 Flutter 功能集

### 2.2 后端策略

- **工业级**（A 档）：复式记账、薪资计算、MRP 引擎 — 算法完整、边界处理充分、生产可用
- **核心可用**（B 档）：质量管理、通知系统、BI 看板 — 关键规则实现，后续按需迭代

---

## 3. P0 — 前端生态（3-4 周）

> **目标**：让系统有可用的管理界面，覆盖所有已实现的后端模块

### 3.1 Flutter 项目架构重构

```
apps/flutter/lib/app/
├── main.dart                      # 入口，初始化 GetX + Dio
├── routes/
│   └── app_pages.dart             # 全量路由注册（按模块分组）
├── layouts/
│   └── admin_layout.dart          # PC 三栏布局（侧边栏 + 顶栏 + 内容）
├── theme/
│   └── app_theme.dart             # Material 3 主题（品牌色 #1677FF）
├── services/
│   ├── api_service.dart           # Dio 单例 + JWT 拦截器 + 自动刷新
│   ├── auth_service.dart          # 认证状态管理
│   ├── captcha_service.dart       # 点击验证码
│   └── export_service.dart        # Excel/PDF 导出下载
├── widgets/
│   ├── data_table_wrapper.dart    # 通用数据表格（分页/搜索/批量操作）
│   ├── form_dialog.dart           # 通用表单弹窗
│   ├── confirm_dialog.dart        # 二次确认弹窗（密码输入）
│   └── stat_card.dart             # 统计卡片
└── pages/
    ├── login/                     # 登录页
    ├── dashboard/                 # 仪表盘（6 个看板切换）
    ├── system/
    │   ├── user/                  # 用户管理（含批量/导入）
    │   ├── role/                  # 角色 + 权限树
    │   ├── config/                # 系统配置
    │   └── log/                   # 操作日志
    ├── product/                   # 商品/分类/品牌/SKU
    ├── partner/                   # 供应商/客户/仓库/库位
    ├── purchase/                  # 采购申请/订单/收货/退货/结算
    ├── sales/                     # 销售报价/订单/发货/退货/结算
    ├── inventory/                 # 库存/流水/调拨/盘点/预警
    ├── finance/
    │   ├── voucher/               # 记账凭证
    │   ├── ar_ap/                 # 应收应付
    │   ├── receipt_payment/       # 收付款
    │   ├── ledger/                # 总账/明细账
    │   ├── report/                # 三表（利润/资产负债/现金流）
    │   ├── asset/                 # 固定资产
    │   ├── tax/                   # 税务
    │   ├── currency/              # 多币种/汇率
    │   ├── budget/                # 预算
    │   └── cost_profit/           # 成本/利润中心
    ├── crm/
    │   ├── opportunity/           # 商机漏斗
    │   ├── contact/               # 联系人
    │   ├── pool/                  # 公海池
    │   ├── contract/              # 合同
    │   ├── quotation/             # 报价
    │   ├── campaign/              # 营销活动
    │   ├── ticket/                # 服务工单
    │   └── analytics/             # 客户分析
    ├── oms/                       # OMS 订单/履约/退货/渠道
    ├── wms/                       # WMS 库区库位/收货/上架/波次/拣货/打包
    ├── tms/                       # TMS 承运商/费率/运单/轨迹/结算
    ├── manufacturing/             # BOM/生产订单/工艺/工作站/MRP
    ├── hr/                        # 部门/员工/职位/考勤/请假/薪资
    ├── project/                   # 项目/任务/工时
    ├── workflow/                  # 审批工作流/我的审批
    ├── notification/              # 通知中心
    ├── report/                    # 自定义报表
    └── profile/                   # 个人中心
```

### 3.2 通用组件开发

| 组件 | 功能 | 使用场景 |
|------|------|----------|
| `DataTableWrapper` | 分页/排序/关键词搜索/状态筛选/批量选择/列配置 | 所有列表页 |
| `FormDialog` | 动态表单渲染/字段校验/提交/关闭 | 所有创建/编辑弹窗 |
| `ConfirmDialog` | 密码二次确认输入 | 所有删除操作 |
| `StatCard` | 数值/趋势箭头/标题 | 仪表盘 |
| `BreadcrumbNav` | 面包屑导航 | 深层页面 |
| `FileUploader` | 拖拽上传/进度/预览 | 导入/图片上传 |

### 3.3 HarmonyOS 补全

对齐 Flutter 页面集，补齐：OMS/WMS/TMS/制造/HR/审批/通知/报表模块页面。

### 3.4 P0 验收标准

- [ ] Flutter Web 管理面板覆盖全部 14 个模块
- [ ] 所有 CRUD 列表页可用（分页/搜索/筛选）
- [ ] 所有创建/编辑表单可用（校验/提交）
- [ ] 删除操作二次密码确认
- [ ] JWT 自动刷新无感
- [ ] PC/平板/手机响应式布局适配
- [ ] HarmonyOS 页面数 ≥ Flutter 页面数的 80%

---

## 4. P1 — 业务深度（4-6 周）

> **目标**：将核心模块从 CRUD 骨架升级为真正的业务计算引擎

### 4.1 财务复式记账引擎（工业级）

```
app/service/finance/
├── DoubleEntryService.php        # 借贷平衡校验 + 自动分录生成
├── PeriodCloseService.php        # 期末结转（损益结转/成本结转）
├── AccountBalanceService.php     # 科目余额汇总（按月/按季/按年）
├── ConsolidationService.php      # 多币种合并报表（汇率折算）
└── FinancialRatioService.php     # 财务比率自动计算

app/controller/finance/
├── PeriodCloseController.php     # 期末结转操作
├── AccountBalanceController.php  # 科目余额查询
└── FinancialRatioController.php  # 比率分析查询
```

**关键规则**：
- 凭证保存时强制执行「有借必有贷，借贷必相等」
- 已审核凭证不可修改，需红字冲销
- 期末结转：损益类科目余额 → 本年利润，支持多步结转
- 多币种：按期末汇率折算、汇兑损益自动计算

### 4.2 薪资计算引擎（工业级）

```
app/service/hr/
├── SalaryEngineService.php       # 薪资计算主引擎
├── SocialInsuranceService.php    # 社保计算（养老/医疗/失业/工伤/生育）
├── HousingFundService.php        # 公积金计算
├── TaxCalculatorService.php      # 个税累进税率计算
└── BankPayrollService.php        # 银行代发文件导出

app/controller/hr/
└── PayrollController.php         # 薪资计算/发放/查询
```

**关键规则**：
- 社保基数上下限（各地市每年调整，配置化）
- 公积金基数 + 缴存比例（5%-12%，配置化）
- 个税累进税率表（3%-45%，年度汇算清缴）
- 银行代发格式：支持 ICBC/BOC/CCB/CMB 等主流银行
- 工资条生成（含各项明细）

### 4.3 MRP 引擎（工业级）

```
app/service/manufacturing/
├── MrpEngineService.php           # MRP 运算主引擎
├── DemandForecastService.php      # 需求汇总（订单+预测+安全库存）
├── NetRequirementService.php      # 净需求计算（毛需求-在库-在途）
├── BomExplosionService.php        # BOM 展开（逐层展开到原材料）
└── OrderSuggestionService.php     # 建议订单生成（采购/生产/外协）

app/model/
├── MfgMrpRunLog.php              # MRP 运算日志
└── MfgOrderSuggestion.php        # 建议订单
```

**关键规则**：
- BOM 逐层展开，考虑损耗率
- 净需求 = 毛需求 - 现有库存 - 在途库存 + 已分配量 + 安全库存
- 低层码 (LLC) 确保同一物料只计算一次
- 提前期倒推建议订单日期
- 批量规则：固定批量/经济批量/按需

### 4.4 质量管理（核心可用）

```
app/controller/quality/
├── InspectionStandardController.php  # 检验标准
├── IncomingCheckController.php       # IQC 来料检验
├── ProcessCheckController.php        # IPQC 过程检验
├── FinalCheckController.php          # OQC 出货检验
└── NonconformityController.php       # 不合格品处理

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 实时通知系统（核心可用）

```
app/service/notification/
├── WebSocketService.php           # WebSocket 连接管理 + 推送
├── ChannelRouter.php              # 多渠道路由（站内/邮件/企微/钉钉）
├── TemplateRenderer.php           # 通知模板渲染

app/process/
└── WebSocket.php                  # WebSocket 进程

app/controller/notification/
├── WebSocketController.php        # WebSocket 事件处理
└── ChannelConfigController.php    # 通知渠道配置
```

**关键规则**：
- WebSocket 基于 workerman 原生协议
- 通知模板：变量占位符 `{order_code}` 运行时替换
- 渠道优先级：站内 → 邮件 → 企微 → 钉钉，可配置

### 4.6 P1 验收标准

- [ ] 凭证保存时借贷不相等 → 返回错误
- [ ] 薪资引擎输出结果与人工计算一致（抽查 10 人月薪数据）
- [ ] MRP 净需求计算与 Excel 手工推算一致
- [ ] 质量检验三单（IQC/IPQC/OQC）完整流转
- [ ] WebSocket 通知延迟 < 2 秒
- [ ] 所有新服务有 PHPUnit 测试覆盖（关键算法 ≥ 95%）

---

## 5. P2 — 运维可靠性（1-2 周）

> **目标**：生产级运维能力

### 5.1 数据库迁移回滚

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

每个迁移文件增加对应的 `_rollback.sql` 文件。

### 5.2 备份恢复增强

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 可观测性

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- 请求级 trace ID（通过响应头 `X-Trace-Id` 透出）
- 关键业务指标：订单量、履约率、库存周转天数

### 5.4 消息队列升级

现有 Redis 队列 → 支持 RabbitMQ 作为可选驱动：

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 P2 验收标准

- [ ] 迁移回滚脚本可执行且数据完整性验证通过
- [ ] 自动备份 cron 正常触发
- [ ] Trace ID 贯穿请求全链路
- [ ] RabbitMQ 驱动可切换且消息不丢失

---

## 6. P3 — 体验增强（2-3 周）

> **目标**：高级功能和更好的用户体验

### 6.1 BI 数据看板

```
app/controller/bi/
├── DashboardController.php       # 可配置仪表盘
├── WidgetController.php          # 图表小组件 CRUD
└── DatasetController.php         # 数据集管理

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- 可拖拽布局的仪表盘
- 小组件：柱状图/折线图/饼图/数据卡片/表格
- 复用 `app/controller/report/` 的数据集机制

### 6.2 设备管理 (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 多租户

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- 共享数据库 + `tenant_id` 隔离
- 超级管理员跨租户视图

### 6.4 文档管理 (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 P3 验收标准

- [ ] BI 仪表盘可拖拽自定义布局
- [ ] 设备台账 → 保养计划 → 维修工单闭环
- [ ] 租户 A 无法访问租户 B 数据
- [ ] 文档版本历史可追溯

---

## 7. 数据模型变更汇总

### P0 新增表

无新增表，前端生态不涉及后端表结构变更。

### P1 新增表

| 表名 | 用途 | 阶段 |
|------|------|------|
| `erik_finance_period_close` | 期末结转记录 | P1 |
| `erik_finance_account_balance` | 科目余额快照 | P1 |
| `erik_hr_salary_config` | 薪资计算配置 | P1 |
| `erik_hr_social_insurance_config` | 社保基数配置 | P1 |
| `erik_hr_housing_fund_config` | 公积金配置 | P1 |
| `erik_mfg_mrp_run_log` | MRP 运算日志 | P1 |
| `erik_mfg_order_suggestion` | 建议订单 | P1 |
| `erik_quality_inspection_standard` | 检验标准 | P1 |
| `erik_quality_iqc_record` | IQC 来料检验 | P1 |
| `erik_quality_ipqc_record` | IPQC 过程检验 | P1 |
| `erik_quality_oqc_record` | OQC 出货检验 | P1 |
| `erik_quality_nonconformity` | 不合格品 | P1 |
| `erik_notification_channel_config` | 通知渠道配置 | P1 |
| `erik_notification_template` | 通知模板 | P1 |

### P3 新增表

| 表名 | 用途 | 阶段 |
|------|------|------|
| `erik_bi_dashboard` | BI 仪表盘 | P3 |
| `erik_bi_widget` | BI 小组件 | P3 |
| `erik_eam_equipment` | 设备台账 | P3 |
| `erik_eam_maintenance_plan` | 保养计划 | P3 |
| `erik_eam_repair_order` | 维修工单 | P3 |
| `erik_dms_document` | 受控文档 | P3 |
| `erik_dms_document_version` | 文档版本 | P3 |

---

## 8. 服务层变更汇总

| 服务 | 当前 | P1 变更 | P2 变更 | P3 变更 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | 新增 DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| 薪资 | 无 | 新增 SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| 制造 | CRUD | 新增 MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| 质量 | 无 | 新增 QmsInspectionService | — | — |
| 通知 | 基础 | 新增 WebSocketService, ChannelRouter | — | — |
| 可观测 | Monitor进程 | — | 新增 TracerService, MetricCollector | — |
| BI | 无 | — | — | 新增 BiDashboardService |
| 设备 | 无 | — | — | 新增 EamService |

---

## 9. 中间件链变更

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. 里程碑与交付物

| 里程碑 | 时间 | 交付物 |
|--------|------|--------|
| M0 — 当前基线 | 2026-08-04 | 审查报告 `audit-report-2026-08-04.md` |
| M1 — P0 完成 | +3 周 | Flutter Web 全模块管理面板 |
| M2 — P1 完成 | +8 周 | 财务引擎 + 薪资引擎 + MRP 引擎 + 质量 + 通知 |
| M3 — P2 完成 | +10 周 | 迁移回滚 + 自动备份 + Trace + 队列升级 |
| M4 — P3 完成 | +13 周 | BI 看板 + 设备管理 + 多租户 + 文档管理 |

---

## 11. 风险与缓解

| 风险 | 影响 | 缓解措施 |
|------|------|----------|
| Flutter Web 性能不如原生 JS | 大数据表格卡顿 | 客户端分页 + 虚拟滚动 + Web Worker |
| 薪资引擎法规变化 | 计算结果不合规 | 社保/税率配置化，非硬编码 |
| MRP 运算大数据量超时 | 运算中断 | 分批处理 + 进度回调 |
| WebSocket 长连接数过多 | 服务器内存压力 | workerman 天然高并发 + 连接数限制 |
| 多租户数据隔离遗漏 | 数据泄露 | TenantScope 全局中间件 + 测试覆盖 |

---

## 12. 不做的事情（明确排除）

- ❌ 不引入微服务拆分 — 当前单体架构足够，复杂逻辑通过 Service 层内聚
- ❌ 不引入 Kubernetes — Docker Compose 满足当前规模
- ❌ 不做 AI/ML 功能 — 不在 MVP 路线图中
- ❌ 不开发原生 iOS/Android 独立 App — Flutter 跨平台已覆盖
- ❌ 不引入 GraphQL — RESTful API 足够，API 版本策略成熟
- ❌ 不做电子签章/WMS 硬件集成（PDA/扫描枪）— 纯软件层面
