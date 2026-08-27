# Roteiro completo do ecossistema ERP — Especificação de design

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Elaborado com base no relatório de revisão do ecossistema de 2026-08-04, cobrindo quatro fases prioritárias P0～P3

---

## 1. Linha de base atual

| Dimensão | Situação atual | Pontuação |
|------|------|------|
| API do backend | 14 módulos / 80+ controllers / 120+ models, esqueleto CRUD multi-módulo | 85/100 |
| Defesa de segurança | 18 camadas de defesa em profundidade, CORS/SecurityFilter/RateLimit/JWT/criptografia | 95/100 |
| UI do frontend | Flutter 12 páginas, HarmonyOS 9 páginas, cobrindo ~20% dos módulos; painel de administração Web ausente | 20/100 |
| Ecossistema de operação | Dockerizado, CI concluído; faltam rollback de migração, automação de backup, observabilidade | 70/100 |
| Profundidade de negócio | Tabelas dos módulos financeiro/HR/manufatura completas, mas a lógica de negócio é predominantemente CRUD | 55/100 |
| **Geral** | | **65/100** |

---

## 2. Estratégia geral

```
瀑布串行: P0 → P1 → P2 → P3
Cada fase tem sub-tarefas independentes que podem avançar em paralelo
```

### 2.1 Seleção tecnológica do frontend

- **Painel de administração Web**: Flutter Web, reutilizando o código existente de `apps/flutter`, estilo painel de administração para PC, gerenciamento de estado GetX
- **Mobile**: Flutter (iOS/Android), compartilhando o código de negócio `apps/flutter/lib/app/` com o Web
- **HarmonyOS**: ArkTS, alinhado ao conjunto de funcionalidades do Flutter

### 2.2 Estratégia do backend

- **Nível industrial** (classe A): contabilidade de partidas dobradas, cálculo de folha de pagamento, motor MRP — algoritmos completos, tratamento de bordas suficiente, pronto para produção
- **Núcleo utilizável** (classe B): gestão de qualidade, sistema de notificações, painéis BI — regras-chave implementadas, iterações posteriores sob demanda

---

## 3. P0 — Ecossistema do frontend (3-4 semanas)

> **Objetivo**: dar ao sistema uma interface de administração utilizável, cobrindo todos os módulos de backend já implementados

### 3.1 Reestruturação da arquitetura do projeto Flutter

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

### 3.2 Desenvolvimento de componentes comuns

| Componente | Funcionalidade | Cenários de uso |
|------|------|----------|
| `DataTableWrapper` | Paginação/ordenação/busca por palavra-chave/filtro de status/seleção em lote/colunas configuráveis | Todas as páginas de listagem |
| `FormDialog` | Renderização dinâmica de formulários/validação de campos/envio/fechamento | Todos os diálogos de criação/edição |
| `ConfirmDialog` | Confirmação secundária com senha | Todas as operações de exclusão |
| `StatCard` | Valor/seta de tendência/título | Dashboard |
| `BreadcrumbNav` | Navegação por trilha de pão | Páginas profundas |
| `FileUploader` | Upload por arrastar e soltar/progresso/pré-visualização | Importação/upload de imagens |

### 3.3 Complemento do HarmonyOS

Alinhado ao conjunto de páginas do Flutter, completar as páginas dos módulos OMS/WMS/TMS/manufatura/HR/aprovação/notificação/relatórios.

### 3.4 Critérios de aceite do P0

- [ ] O painel de administração Web Flutter cobre os 14 módulos
- [ ] Todas as páginas de listagem CRUD funcionais (paginação/busca/filtro)
- [ ] Todos os formulários de criação/edição funcionais (validação/envio)
- [ ] Confirmação secundária de senha nas operações de exclusão
- [ ] Refresh automático de JWT sem fricção
- [ ] Layout responsivo adaptado para PC/tablet/celular
- [ ] Nº de páginas HarmonyOS ≥ 80% do nº de páginas Flutter

---

## 4. P1 — Profundidade de negócio (4-6 semanas)

> **Objetivo**: elevar os módulos principais de esqueleto CRUD para motores de cálculo de negócio reais

### 4.1 Motor de contabilidade de partidas dobradas (nível industrial)

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

**Regras-chave**:
- Ao salvar um voucher, impor "todo débito tem crédito correspondente, débito e crédito sempre iguais"
- Vouchers já auditados não podem ser alterados; exigem estorno em tinta vermelha
- Fechamento de período: saldos das contas de resultado → lucro do ano corrente, suporta fechamento em múltiplas etapas
- Multi-moeda: conversão pela taxa de fechamento, ganhos/perdas cambiais calculados automaticamente

### 4.2 Motor de cálculo de folha de pagamento (nível industrial)

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

**Regras-chave**:
- Limites inferior e superior da base do seguro social (ajustados anualmente por cidade, configuráveis)
- Base do fundo de habitação + taxa de contribuição (5%-12%, configurável)
- Tabela de alíquotas progressivas do imposto de renda (3%-45%, liquidação anual)
- Formato de pagamento bancário: suporta bancos principais como ICBC/BOC/CCB/CMB
- Geração do contracheque (com todos os detalhes)

### 4.3 Motor MRP (nível industrial)

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

**Regras-chave**:
- Expansão da BOM camada por camada, considerando a taxa de perda
- Necessidade líquida = necessidade bruta − estoque existente − estoque em trânsito + quantidade já alocada + estoque de segurança
- Código de nível baixo (LLC) garante que cada material seja calculado apenas uma vez
- Lead time retroativamente define a data sugerida do pedido
- Regras de lote: lote fixo/lote econômico/sob demanda

### 4.4 Gestão de qualidade (núcleo utilizável)

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

### 4.5 Sistema de notificações em tempo real (núcleo utilizável)

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

**Regras-chave**:
- WebSocket baseado no protocolo nativo do workerman
- Templates de notificação: placeholders de variáveis `{order_code}` substituídos em tempo de execução
- Prioridade de canais: interno → e-mail → WeCom → DingTalk, configurável

### 4.6 Critérios de aceite do P1

- [ ] Voucher com débito/crédito desiguais ao salvar → retorna erro
- [ ] Saída do motor de folha de pagamento consistente com o cálculo manual (amostragem de 10 folhas mensais)
- [ ] Cálculo de necessidade líquida do MRP consistente com o cálculo manual no Excel
- [ ] Fluxo completo dos três documentos de inspeção de qualidade (IQC/IPQC/OQC)
- [ ] Latência das notificações WebSocket < 2 segundos
- [ ] Todos os novos serviços com cobertura de testes PHPUnit (algoritmos-chave ≥ 95%)

---

## 5. P2 — Confiabilidade operacional (1-2 semanas)

> **Objetivo**: capacidade operacional de nível de produção

### 5.1 Rollback de migração de banco

```
database/migrations/
├── migrate.sh                    # 前滚脚本
└── rollback.sh                   # 回滚脚本（按迁移文件逆序执行）
```

Cada arquivo de migração ganha um arquivo `_rollback.sql` correspondente.

### 5.2 Reforço de backup e restauração

```
database/backup/
├── backup.sh                     # 已有
├── restore.sh                    # 已有
├── auto-backup.sh                # 新增：cron 定时备份 + 告警
└── backup-validator.sh           # 新增：备份文件完整性校验
```

### 5.3 Observabilidade

```
app/service/observability/
├── TracerService.php             # OpenTelemetry 追踪
└── MetricCollector.php           # 业务指标采集
```

- Trace ID em nível de requisição (exposto pelo cabeçalho de resposta `X-Trace-Id`)
- Indicadores de negócio-chave: volume de pedidos, taxa de cumprimento, dias de giro de estoque

### 5.4 Upgrade da fila de mensagens

Fila Redis atual → suportar RabbitMQ como driver opcional:

```
config/queue.php                  # 队列驱动配置（redis/rabbitmq）
```

### 5.5 Critérios de aceite do P2

- [ ] Script de rollback de migração executável e validação de integridade de dados aprovada
- [ ] Cron de backup automático disparando normalmente
- [ ] Trace ID presente em toda a cadeia da requisição
- [ ] Driver RabbitMQ comutável sem perda de mensagens

---

## 6. P3 — Aprimoramento de experiência (2-3 semanas)

> **Objetivo**: funcionalidades avançadas e melhor experiência do usuário

### 6.1 Painéis BI

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

- Dashboards com layout arrastável
- Componentes: gráfico de barras/linhas/pizza, cartões de dados, tabelas
- Reutiliza o mecanismo de datasets de `app/controller/report/`

### 6.2 Gestão de equipamentos (EAM)

```
app/controller/eam/
├── EquipmentController.php       # 设备台账
├── MaintenancePlanController.php # 保养计划
├── RepairOrderController.php     # 维修工单
└── SparePartController.php       # 备件管理
```

### 6.3 Multi-tenant

```
app/middleware/TenantScope.php    # 租户隔离中间件
app/model/concerns/TenantScope.php # Eloquent 租户作用域 Trait
```

- Banco compartilhado + isolamento por `tenant_id`
- Visão entre tenants para superadministradores

### 6.4 Gestão de documentos (DMS)

```
app/controller/dms/
├── DocumentController.php        # 文档 CRUD + 版本管理
├── CategoryController.php        # 文档分类
└── ApprovalController.php        # 文档审批发布
```

### 6.5 Critérios de aceite do P3

- [ ] Dashboards BI com layout customizável por arrastar
- [ ] Ciclo fechado ficha de equipamento → plano de manutenção → ordem de reparo
- [ ] Tenant A não consegue acessar dados do tenant B
- [ ] Histórico de versões de documentos rastreável

---

## 7. Resumo das mudanças de models de dados

### Novas tabelas no P0

Sem novas tabelas; o ecossistema do frontend não envolve mudanças na estrutura do backend.

### Novas tabelas no P1

| Nome da tabela | Uso | Fase |
|------|------|------|
| `erp_finance_period_close` | Registro de fechamento de período | P1 |
| `erp_finance_account_balance` | Snapshot de saldos de contas | P1 |
| `erp_hr_salary_config` | Configuração de cálculo de folha | P1 |
| `erp_hr_social_insurance_config` | Configuração de base do seguro social | P1 |
| `erp_hr_housing_fund_config` | Configuração do fundo de habitação | P1 |
| `erp_mfg_mrp_run_log` | Log de execução do MRP | P1 |
| `erp_mfg_order_suggestion` | Pedido sugerido | P1 |
| `erp_quality_inspection_standard` | Padrão de inspeção | P1 |
| `erp_quality_iqc_record` | Inspeção IQC de entrada | P1 |
| `erp_quality_ipqc_record` | Inspeção IPQC de processo | P1 |
| `erp_quality_oqc_record` | Inspeção OQC de saída | P1 |
| `erp_quality_nonconformity` | Produto não conforme | P1 |
| `erp_notification_channel_config` | Configuração de canais de notificação | P1 |
| `erp_notification_template` | Templates de notificação | P1 |

### Novas tabelas no P3

| Nome da tabela | Uso | Fase |
|------|------|------|
| `erp_bi_dashboard` | Dashboard BI | P3 |
| `erp_bi_widget` | Componente BI | P3 |
| `erp_eam_equipment` | Ficha de equipamentos | P3 |
| `erp_eam_maintenance_plan` | Plano de manutenção | P3 |
| `erp_eam_repair_order` | Ordem de reparo | P3 |
| `erp_dms_document` | Documento controlado | P3 |
| `erp_dms_document_version` | Versão de documento | P3 |

---

## 8. Resumo das mudanças na camada de serviços

| Serviço | Atual | Mudança no P1 | Mudança no P2 | Mudança no P3 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | Novos DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Folha de pagamento | Nenhum | Novos SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Manufatura | CRUD | Novos MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Qualidade | Nenhum | Novo QmsInspectionService | — | — |
| Notificações | Básico | Novos WebSocketService, ChannelRouter | — | — |
| Observabilidade | Processo Monitor | — | Novos TracerService, MetricCollector | — |
| BI | Nenhum | — | — | Novo BiDashboardService |
| Equipamentos | Nenhum | — | — | Novo EamService |

---

## 9. Mudanças na cadeia de middlewares

```
当前: Locale → Cors → SecurityFilter → RateLimit → {路由组}

P0: 无变更
P1: + WebSocketUpgrade（/ws 路径升级 WebSocket 连接）
P2: + TracingId（注入 X-Trace-Id）
P3: + TenantScope（多租户隔离）
```

---

## 10. Marcos e entregas

| Marco | Tempo | Entrega |
|--------|------|--------|
| M0 — Linha de base atual | 2026-08-04 | Relatório de revisão `audit-report-2026-08-04.md` |
| M1 — P0 concluído | +3 semanas | Painel de administração Web Flutter para todos os módulos |
| M2 — P1 concluído | +8 semanas | Motor financeiro + motor de folha + motor MRP + qualidade + notificações |
| M3 — P2 concluído | +10 semanas | Rollback de migração + backup automático + Trace + upgrade de fila |
| M4 — P3 concluído | +13 semanas | Painéis BI + gestão de equipamentos + multi-tenant + gestão de documentos |

---

## 11. Riscos e mitigação

| Risco | Impacto | Medidas de mitigação |
|------|------|----------|
| Desempenho do Flutter Web inferior ao JS nativo | Travamentos em tabelas grandes | Paginação no cliente + rolagem virtual + Web Worker |
| Mudanças regulatórias no motor de folha | Resultado não conforme | Seguro/taxas configuráveis, não codificadas |
| Timeout do MRP com grandes volumes de dados | Execução interrompida | Processamento em lotes + callback de progresso |
| Número excessivo de conexões WebSocket | Pressão de memória do servidor | Workerman é naturalmente de alta concorrência + limite de conexões |
| Falha de isolamento multi-tenant | Vazamento de dados | Middleware global TenantScope + cobertura de testes |

---

## 12. O que NÃO será feito (excluído explicitamente)

- ❌ Sem divisão em microservices — a arquitetura monolítica atual é suficiente; lógica complexa é coesa na camada de Service
- ❌ Sem Kubernetes — Docker Compose atende ao tamanho atual
- ❌ Sem funcionalidades AI/ML — não estão no roteiro do MVP
- ❌ Sem apps iOS/Android nativos separados — o Flutter multiplataforma já cobre
- ❌ Sem GraphQL — API RESTful é suficiente, a estratégia de versão de API é madura
- ❌ Sem assinatura eletrônica/integração de hardware WMS (PDA/scanners) — apenas nível de software
