# 功能差距分析与新功能规划（2026-09）

## 1. 文档说明

- 基线：本仓库 main 分支（v1.3.0 之后，bcmath 全量改造完成），以 README 功能清单与代码盘点为准。
- 代码规模：104 控制器 / 162 模型 / 12 个领域 service 目录 / 21 个业务域。
- 参照系：金蝶云·星空、金蝶云·苍穹、金蝶 K/3 WISE、KIS 系列、精斗云、用友 U9 Cloud、U8+/U8 Cloud、YonSuite、浪潮 inSuite/GS、鼎捷家族（E10/T100/易飞/易助）、SAP S/4HANA、SAP B1、Oracle Fusion/NetSuite、万达宝、简道云、东软 RealStar、卓力、智邦国际、华为云企业服务、博科 YigoERP。
- 结论先行：本项目已是**完整的中小企业全链路 ERP（进销存+财务+制造+电商履约）**，覆盖广度优于多数同类开源系统；主要差距在**多组织财务与合并报表、成本核算深度、寻源与合同、制造执行层、税务发票电子化、平台开放能力**六处，其次是零售会员、HR 纵深与多租户 SaaS 化。

## 2. 现状盘点（已有能力，不再规划）

| 域 | 控制器数 | 代表模型 | 已具备（可直接对标主流） |
|---|---|---|---|
| 财务 finance | 20 | Voucher/VoucherItem/GeneralLedger/SubsidiaryLedger/ArAp/Payment/Receipt/Settlement/CashJournal/CashFlow/BankAccount/Asset/AssetDepreciation/Budget/BudgetItem/CostCenter/ProfitCenter/Profit/CostRecord/Expense/Currency/ExchangeRate/TaxRate/TaxRecord/Allocation/Account/BalanceSheet | 记账→凭证→总账/明细账全链路、应收应付与自动核销、三表、期间结转、预算、成本/利润中心、多币种+汇率、固定资产+折旧、税务记录 |
| 进销存 | 采购 5 / 销售 5 / 库存 5 | Purchase{Apply,Order,Receive,Return,Settlement}、Sales{Quotation,Order,Delivery,Return,Settlement}、Inventory/Batch/Serial/Alert/CheckTask/Transfer | 报价→订单→发货→应收、申请→订单→收货→应付、实时库存、**批次/序列号**、移动加权平均、调拨、盘点、预警 |
| 商品/档案 | 7 | Product/ProductSku/ProductPrice/ProductUnit/Brand/Category、Customer/CustomerLevel/Supplier/Warehouse/Location | 多单位、价格策略、信用额度档案字段 |
| 制造 manufacturing | 5 | MfgBom/BomItem/MfgRouting/MfgWorkstation/MfgMrpPlan/MrpItem/MfgProductionOrder/ProductionItem | BOM、工艺路线、工作站、MRP→采购/生产建议、生产订单 |
| 质量 quality | 5 | InspectionStandard/Iqc/Process(Qc)/Final(Oqc)/Nonconformity | 进料/过程/成品检验、检验标准、不合格处理 |
| WMS | 8 | Zone/Wave/WaveOrder/Asn/Receiving/Putaway/Pick/Pack/Location | ASN→收货→上架→波次→拣货→打包→发货全流程 |
| TMS | 6 | Carrier/CarrierService/FreightRate/Shipment/ShipmentPackage/FreightInvoice/TrackingEvent | 承运商、费率、运单、运费发票、比价、轨迹 |
| OMS | 4 | Channel/Order/Fulfillment/InventoryReservation/Rma | 多渠道、履约编排、ATP 预占、RMA |
| CRM | 10 | Contact/Opportunity/FunnelStage/Pool/Campaign/Contract/Ticket/FollowRecord/Analytics | 全生命周期、公海、营销活动、客户合同、工单 |
| HR | 5 | Employee/Department/Position/Attendance/AttendanceRule/Leave/Salary/SalaryItem | 组织人事、考勤、请假、工资+个税 |
| EAM | 4 | Equipment/MaintenancePlan/RepairOrder/SparePart | 设备、保养计划、维修工单、备件 |
| 平台 | 10+ | ApprovalWorkflow/Approval{Instance,Node,Record}/BiDashboard/BiWidget/Report{Template,Dataset,Field,Filter,Schedule}/Notification{Setting,Template}/OperationLog/SystemConfig | 审批工作流引擎、BI 仪表盘、自定义报表构建器、消息通知、审计日志、RBAC |
| 其他 | DMS 2 / project 3 / 通知 1 | Dms{Category,Document,DocumentVersion}、Project{Task,Member,Timesheet,Gantt} | 文档管理、项目管理、甘特图 |

## 3. 与主流 ERP 的差距矩阵

图例：★★★ 必备缺口（主流标配，直接决定产品档位）｜ ★★ 重要增强｜ ★ 差异化可选

### 3.1 财务（对标：金蝶云·星空总账/合并报表、U8+ 财务链、S4HANA 财务会计）

| # | 差距项 | 主流对应 | 级别 | 现状佐证 |
|---|---|---|---|---|
| F1 | **多组织/多公司核算**（组织-账簿模型、平行记账、跨组织交易） | 星空组织架构、U9 多组织、S4 company code | ★★★ | 无 Company/Organization/Tenant 模型，仅成本/利润中心作维度 |
| F2 | **合并报表**（抵消分录、权益法/成本法、工作底稿） | 星空合并报表、U8 合并、B1 集团 | ★★★ | BalanceSheet 仅单体 |
| F3 | **存货核算/成本会计**：生产领料→人工/制费归集→完工成本→成本差异 | 星空存货核算、S4 CO/ML、U8 成本管理 | ★★★ | 移动加权平均仅覆盖出入库成本，无生产成本归集 |
| F4 | 发票管理：应收/应付发票、开票申请、发票校验（三单匹配） | 星空/金蝶发票云、S4 FI-AR/AP invoice | ★★★ | 只有 TaxRecord/TaxRate，无 Invoice 模型；核销基于收付款而非发票 |
| F5 | 税务电子化：数电票开收票、进项发票池、申报表 | 金蝶发票云、用友税务云 | ★★ | 税模块只有税率与记录 |
| F6 | 资金管理：票据（承兑汇票）、银企直连/对账单导入 | 星空资金、U8 资金管理、B1 | ★★ | BankAccount/CashJournal 无银企接口 |
| F7 | 信用控制执行：销售订单/发货实时信用额度拦截 | 星空信用管理、B1 credit check | ★★ | Customer 有信用额度字段但无执行级拦截（controller 层未引用） |
| F8 | 期末调汇与汇兑损益凭证 | 星空期末调汇、S4 FAGL_FCV | ★★ | 有汇率表，无调汇功能 |
| F9 | 应收应付账龄深化：对账单、询证函、账龄催收动作 | 星空、U8 | ★ | Dashboard 有账龄桶，无催收/对账单据 |
| F10 | 预算编制工作流+预算执行刚性控制 | 星空预算、S4 CO | ★ | 有 Budget 无编制流程与硬控 |

### 3.2 供应链（对标：星空供应链、U8+ 购销存、S4 MM/SD）

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| S1 | **寻源采购**：询比价、招投标、供应商评估准入、框架协议 | 星空寻源、U8 采购管理、S4 sourcing | ★★★ | 采购只有 申请→订单→收货→结算 直线流程 |
| S2 | **采购合同管理**（与订单联动、分期付款条款） | 星空采购合同、S4 outline agreement | ★★ | 无 PurchaseContract 模型 |
| S3 | 销售合同/订单的合同履约追踪（CrmContract 与 SalesOrder 打通） | 星空销售合同 | ★★ | CrmContract 独立于销售流程 |
| S4 | 批次效期管理：生产日期/效期/近效期预警、先进先出建议 | 星空批次、U8 | ★★ | InventoryBatch 无效期字段体系（待确认迁移） |
| S5 | **库龄/呆滞料分析**（与移动平均/存货核算联动） | 星空库龄、S4 slow moving | ★★ | 无 |
| S6 | 条码/RFID/PDA 移动作业 | 星空掌上报工、U8 条码 | ★ | 纯 PC 界面 |
| S7 | 价目表/折扣/返利/促销体系化（非单字段价格策略） | 星空价格政策、U8 促销、S4 condition | ★ | ProductPrice 单表多档 |
| S8 | 委托代销/寄售/调拨在途深化 | U8、星空 | ★ | 有 Transfer 无寄售 |

### 3.3 制造执行（对标：星空制造、U9、鼎捷 T100、S4 PP）

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| M1 | **MES 执行层**：工序派工/报工、工时与计件工资、在制跟踪 | 星空 MES、鼎捷智物流、S4 PP-PI | ★★★ | 制造止于生产订单（无工序级执行数据流） |
| M2 | **委外加工**（整单/工序委外、委外发料核销） | 星空委外、U8、S4 subcontracting | ★★★ | 无 |
| M3 | 产能负荷与高级排产 APS | 星空、T100 APS | ★★ | Workstation 无产能日历与排程 |
| M4 | 生产质量联动与检验判定入库 | 星空、T100 | ★★ | 质量模块与制造模块未单据级打通（有待确认） |
| M5 | 工程变更 ECN/ECR、BOM 版本生效 | PLM 集成族 | ★ | MfgBom 无版本化变更流 |
| M6 | 条码追溯链：批次/序列号正反向追溯报表 | 星空质量追溯、U8 | ★ | 有批次/序列号原始数据，无追溯报表 |

### 3.4 CRM/营销与零售（对标：星空、YonSuite、NetSuite、简道云）

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| C1 | 会员体系：会员等级积分、储值、卡券、小程序/企微触点 | 金蝶管易云、有赞、简道云 | ★★ | Customer/CustomerLevel 为 B2B 档案，无 B2C 会员域 |
| C2 | 营销自动化：活动效果归因、客户分群与自动化触达 | 星空营销云、Salesforce | ★ | 有 CrmCampaign 无分群与自动化 |
| C3 | 客服工单深化：SLA、多渠道接入 | 智邦、金蝶 | ★ | CrmTicket 基础 |

### 3.5 HR（对标：星空人力云、U8 HR、B1 定位外）

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| H1 | 招聘管理（职位发布、简历、offer） | 星空人力云 | ★★ | 无 |
| H2 | 绩效考核（KPI/360/绩效流程） | 星空、U8 HR | ★★ | 无 |
| H3 | 培训管理（课程、计划、学分） | 星空 | ★ | 无 |
| H4 | 社保公积金计算与工资条自助（移动端） | 金蝶、用友 | ★ | 薪资已有个税，无社保与自助端 |

### 3.6 EAM 与项目

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| E1 | 点检/巡检/保养计划执行闭环（扫码点检） | 星空设备云 | ★★ | MaintenancePlan 有，无点检执行 |
| P1 | 项目成本归集与预算控制（工时成本×费率→项目损益） | 星空项目云、S4 PS | ★★ | Timesheet 有工时，无成本/损益 |

### 3.7 平台与集成（对标：星空 BOS、YonBuilder、S4 Fiori+API、NetSuite SuiteCloud）

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| B1 | **单据打印模板引擎**（套打、模板管理、条码标签） | 星空单据设计、U8 套打 | ★★★ | 有 dompdf/poster 依赖与 PDF 基础，无单据模板模型 |
| B2 | **OpenAPI/Webhook 开发者门户**（第三方对接标准口） | 星空开放平台、S4 API | ★★★ | 纯内部 API，无签名分发/文档门户/事件订阅 |
| B3 | 可视化流程设计器（BPMN/泳道，配置式改流程） | 星空流程设计器、YonBPM | ★★ | ApprovalWorkflow 模型驱动，无画布配置 |
| B4 | 消息渠道扩展：短信/邮件(模板化)/WebSocket 实时推送 | 主流均配 | ★★ | Notification 有模板，渠道未扩 |
| B5 | 多租户 SaaS 化落地：TenantScope 启用、租户开通/隔离/计量 | 星空/精斗云 SaaS | ★★ | TenantScope 中间件预留**未注册** |
| B6 | 主数据管理 MDM：客户/供应商/物料统一编码规则与清洗 | 星空 MDM、S4 | ★ | 各域主数据独立 |
| B7 | 低代码表单/字段自定义（非 BOS 级） | YonBuilder、简道云 | ★ | 无 |

### 3.8 报表与 BI

| # | 差距项 | 主流对应 | 级别 | 现状 |
|---|---|---|---|---|
| R1 | 移动端管理驾驶舱 / 移动审批 | 主流全配 App | ★★ | 有 Flutter/HarmonyOS 客户端历史（README），需核对在维护 |
| R2 | 合并口径管理报表、现金流量表附表编制 | S4/星空 | ★ | 主表已有 |

## 4. 分期路线图

排序原则：① 决定产品档位与可销售行业的必备项优先；② 复用既有数据（批次/序列号/双分录/工作流引擎）的优先；③ 独立交付、不阻塞主线的先上。

### P0 — 财务与供应链补课（做完即达"正规 ERP"档位，约 12–18 人周）

| 项 | 范围 | 复用 | 依赖 |
|---|---|---|---|
| F1+F2 多组织→合并报表 | Company 模型+账套(period/currency)；先做"集团核算+单体报表+简单合并"而非全平行记账 | FinanceAccount 体系 | 需先统一 erp_ 表前缀遗留问题（config prefix 二次加前缀）以支撑多库表 |
| F3 存货/生产成本核算 | 领料单→费用归集→完工入库成本分摊→差异结转凭证 | Voucher 自动生成链、BOM | F1 的组织维度可选 |
| S1 寻源采购 | 询比价单(报价登记/比价)→中标转采购订单；供应商准入评分 | PurchaseApply | — |
| F4 发票管理 | 应收/应付发票+开票申请+三单(订单/收货/发票)匹配，核销入口从收付款扩到发票 | ArAp/Settlement | — |
| B2 OpenAPI 基础 | API Key 签名+限流+文档（apidoc-php 已有依赖）+Webhook 事件表 | 现有中间件 | — |

### P1 — 制造执行与业财闭环增强（约 16–20 人周）

| 项 | 范围 |
|---|---|
| M1+M2 MES 与委外 | 工序报工/计件工资（→HR 工资联动）；委外订单+发料/收料核销 |
| F7 信用控制 | 销售订单/发货/出库三处拦截（额度/账期/冻结），可配置放行 |
| M3 产能负荷 | 工作站日历+粗能力负荷报表（先报表后排程） |
| M6 追溯链报表 | 批次/序列号正反向追溯 + 效期预警 |
| B3 可视化流程 | 流程设计器前端画布（配置节点/条件，引擎复用 ApprovalNode） |
| B1 打印模板 | 单据套打：模板模型+占位符渲染（dompdf）+ 条码标签（poster 可出图） |
| H1+H2 HR 招聘/绩效 | 招聘漏斗；KPI 模板+评分流程 |
| E1+P1 点检与项目损益 | 点检扫码执行；Timesheet×费率→项目成本/预算偏差 |

### P2 — 差异化与生态（面向 SaaS/零售/行业纵深，按市场反馈取舍，各 8–12 人周）

| 项 | 范围 |
|---|---|
| F6 资金 | 票据台账、银企直连（先对账单导入+自动核销） |
| F5 税务 | 数电票开票接口、进项发票池（对接发票云/税务平台） |
| C1 会员零售 | 会员/积分/储值/卡券 + 轻量 POS/小程序商城入口（OMS Channel 已有可挂） |
| B5 多租户启用 | TenantScope 注册+租户开通/数据隔离验证/计费（建议以 P0 的 Company 模型为底座演进） |
| B4+B7 渠道与低代码 | 短信/邮件渠道驱动化；表单字段扩展（json 列自定义） |
| H3+H4 培训/社保 | 课程体系；社保基数规则+工资条自助 |

## 5. 实施原则（贴合 webman 技术栈）

1. **金额/成本一律 bcmath**：v1.3.0 已全量改造，新增功能（存货核算、发票、合并、薪酬）从第一天走 `bc_round/bcadd/bccomp`，禁止 float 进入新单据逻辑。
2. **单据流模式复用**：全部新单据按"头+行+Draft→Submitted→Audited 状态机+审核后生成凭证/库存流"既有范式落表，不另起炉灶（新增表前缀统一 erp_，注意 config prefix 二次加前缀遗留，先修再做 P0 F1）。
3. **引擎优先，界面后置**：工作流/审批、报表构建器、通知、审计日志已具备，新功能（发票审批、合同审批）直接挂 ApprovalWorkflow，不重复实现。
4. **每期 3–5 个并行小团队**：按上表分组（财务组/供应链组/制造组/平台组），组间以模型与事件解耦；提交沿用"功能：/修复："中文前缀，单一职责提交。
5. **质量门槛**：新单据服务层必配 PHPUnit 用例（含 bc 半值、跨期、核销边界）；phpstan analyse 新增清零；docs/FUNCTIONS.md 完成度矩阵随每期更新。

## 6. 差异化定位建议

- 不追 SAP/用友的广度：**电商履约（OMS→WMS→TMS→签收→AR）+ 制造（BOM/MRP/生产/质检）的一体化**已是稀缺组合，P0/P1 应把这条链的业财闭环（生产完工→存货核算→凭证→报表）做穿，这是多数同类开源 ERP（含 ERPNext 系）也没做透的地带。
- 开放优先：B2（OpenAPI/Webhook）先于多租户做，单租户深客户（自托管）是 PHP 生态常见付费场景；多租户 SaaS（B5）排 P2，以 Company 底座演进，避免早期复杂度。
- 借鉴简道云/YigoERP 的"配置化"只在打印模板与流程画布两级发力，不做 BOS 级低代码——收益/成本比不划算。
