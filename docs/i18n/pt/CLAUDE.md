# Painel de Administração Aberto (open-admin)

Sistema de painel de administração full-stack baseado em webman v2 + Flutter.

![Mascote polvo](images/mascot.svg)

## Declaração de copyright

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Imutável, inamovível, irreversível.** Todos os arquivos novos devem incluir a declaração de copyright acima como comentário de cabeçalho.

## Roadmap do ecossistema

> Especificação de design: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> Documento de arquitetura: `ARCHITECTURE.md` §21
> Matriz de funcionalidades: `FUNCTIONS.md` §19

**Pontuação geral atual 89/100** — o roadmap completo P0~P3 foi concluído, com cobertura full-stack de 22 módulos, pronto para produção.

| Fase | Prazo | Entregáveis | Status |
|------|------|--------|------|
| 🔵 **P0** Ecossistema front-end | 3-4 semanas | 97 páginas Flutter + 34 páginas HarmonyOS + 4 componentes comuns | ✅ |
| 🟢 **P1** Profundidade de negócio | 4-6 semanas | Motor financeiro + motor de salários + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Confiabilidade operacional | 1-2 semanas | Rollback de migração + backup automático + TraceId + fila com duplo driver | ✅ |
| 🟣 **P3** Melhoria da experiência | 2-3 semanas | Painéis BI + EAM + multitenancy + DMS + 7 novas tabelas | ✅ |

**Testes**: 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter**: 0 errors, 0 warnings.

## Lista de funcionalidades

| Domínio | Funcionalidades |
|----|------|
| Autenticação | Login/registro/refresh/logout + captcha + bloqueio de conta + limite de sessões |
| Dashboards | Visão geral operacional/painel de vendas/painel de estoque/painel financeiro (cache Redis 5m) |
| Usuários | CRUD + exclusão/habilitação-desabilitação em lote + importação Excel |
| Papéis e permissões | CRUD + árvore de permissões + autorização RBAC method.path |
| Configuração do sistema | CRUD de pares chave-valor |
| Auditoria de operações | Consulta de logs + detecção automática de origem em 8 plataformas |
| Arquivos | Upload + exportação Excel/PDF (mascaramento de dados sensíveis) |
| Segurança | 18 camadas de defesa em profundidade (XSS/Injeção SQL/CSRF/rate limit/CSP...) |
| Operações | Health check/métricas Prometheus/documentação da API/security.txt + Docker + CI/CD |
| Gestão de produtos | Produto/SKU/categoria/marca/armazém/localização/fornecedor/cliente |
| Gestão de compras | Solicitação→pedido→recebimento→devolução→liquidação (entrada automática + geração de contas a pagar) |
| Gestão de vendas | Cotação→pedido→expedição→devolução→liquidação (saída automática + geração de contas a receber) |
| Gestão de estoque | Estoque em tempo real/fluxos/lotes/transferência/inventário/alertas (custo por média móvel ponderada) |
| Gestão financeira | Contas a receber e a pagar/vouchers/recebimentos e pagamentos/diário/razão geral/razão auxiliar/três demonstrações/ativo imobilizado/tributos/multimoeda/orçamento |
| CRM | Oportunidades/acompanhamento/funil/contatos/pool compartilhado/contratos/cotações/marketing/tickets/análises |
| Fluxo de aprovação | Definição de fluxos/envio/aprovação/rejeição/retirada/minhas aprovações |
| Notificações de mensagens | Lista de notificações/lida/marcar todas como lidas/contagem de não lidas |
| Gestão de projetos | Projetos/tarefas/registro de horas |
| Recursos humanos | Departamentos/funcionários/cargos/ponto/afastamentos/salários |
| Produção industrial | BOM/ordens de produção/roteiros/estações de trabalho/MRP |
| Relatórios personalizados | Modelos de relatório/datasets/campos/filtros/execução/agendamento |
| OMS — gestão de pedidos | Pedidos multicanal/orquestração de atendimento/reserva de estoque (ATP)/RMA troca-devolução/gestão de canais |
| WMS — gestão de armazém | Zonas e localizações (hierarquia+código de barras)/entrada (ASN→recebimento→putaway)/saída (ondas→separação→embalagem) |
| TMS — gestão de transporte | Transportadoras/comparação de fretes/conhecimentos de transporte/rastreamento logístico (webhook) |
| QMS — gestão da qualidade | Inspeção IQC de entrada/IPQC de processo/OQC de saída + padrões de inspeção + tratamento de não conformidades |
| EAM — gestão de equipamentos | Cadastro de equipamentos/planos de manutenção/ordens de reparo/gestão de peças sobressalentes |
| DMS — gestão de documentos | Categorias de documentos/documentos/gestão de versões |
| Painéis BI | Layout dos painéis/componentes de gráficos |

## Pilha tecnológica

### Back-end
- PHP 8.3+, webman v2 (workerman/webman)
- Banco de dados: MySQL 8.0+, prefixo de tabelas `erp_`
- Chave primária: BIGINT não incremental, gerada por `erikwang2013/snowflake-php`
- Criptografia de IDs na camada de API: `erikwang2013/hashids`
- Autenticação JWT: `erikwang2013/jwt-webman`
- Criptografia de dados sensíveis da API: `erikwang2013/encryption`
- Criptografia de campos sensíveis do banco: `erikwang2013/encryptable`
- Sincronização e consulta ES: `erikwang2013/webman-scout`
- Bandeiras de países: `erikwang2013/season`
- Geração de documentação da API: `hg/apidoc` | baseada em anotações, acesse /apidoc

### Front-end
- Flutter 3.x, diretório de código-fonte `apps/flutter/`
- O lado Web segue o estilo de painel de administração para PC (não estilo de app mobile)
- Suporte a lado cliente e lado administrador
- HarmonyOS ArkTS, diretório de código-fonte `apps/harmonyos/`

## Estrutura do projeto

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (14 个)
│   │   ├── BaseController.php      # 基础控制器
│   │   ├── DashboardController.php # 仪表盘 + 销售/库存/财务面板
│   │   ├── UserController.php      # 用户 CRUD + 批量操作
│   │   ├── RoleController.php      # 角色 CRUD
│   │   ├── PermissionController.php# 权限 CRUD
│   │   ├── ConfigController.php    # 系统配置 CRUD
│   │   ├── LogController.php       # 操作日志查询
│   │   ├── ProfileController.php   # 个人中心 + 登出
│   │   ├── ExportController.php    # Excel/PDF 导出
│   │   ├── ImportController.php    # Excel 导入用户
│   │   ├── UploadController.php    # 文件上传
│   │   ├── HealthController.php    # 健康检查
│   │   ├── DocsController.php      # OpenAPI 文档
│   │   └── MetricsController.php   # Prometheus 监控指标
│   ├── api/v1/controller/      # 客户端 API（版本头控制）
│   │   ├── CaptchaController.php   # 点击验证码
│   │   ├── AuthController.php      # 登录/注册/刷新
│   │   └── ProductController.php   # 商品查询（不含进价）
│   ├── controller/              # 业务模块控制器（104 个，含 InstallController）
│   │   ├── product/             # 商品/分类/品牌/仓库/库位/供应商/客户 (7个)
│   │   ├── purchase/            # 采购申请/订单/收货/退货/结算 (5个)
│   │   ├── sales/               # 销售报价/订单/发货/退货/结算 (5个)
│   │   ├── inventory/           # 库存/流水/调拨/盘点/预警 (5个)
│   │   ├── finance/             # 应收应付/凭证/收付款/日记账/总账/明细账/三表/固定资产/税务/多币种/预算/成本利润中心 (20个)
│   │   ├── crm/                 # 商机/跟进/漏斗/联系人/公海池/报价/合同/营销/工单/分析 (10个)
│   │   ├── workflow/            # 工作流定义/审批提交/批准/拒绝/撤回 (2个)
│   │   ├── notification/        # 通知列表/已读/未读计数 (1个)
│   │   ├── project/             # 项目/任务/工时记录 (3个)
│   │   ├── hr/                  # 部门/员工/职位/考勤/请假/薪资 (5个)
│   │   ├── manufacturing/       # BOM/生产订单/工艺路线/工作站/MRP (5个)
│   │   ├── report/              # 报表模板/数据集/执行/定时调度 (2个)
│   │   ├── oms/                 # 订单/履约/库存预占/RMA/渠道 (4个)
│   │   ├── wms/                 # 库区库位/ASN收货/上架/波次/拣货/打包 (8个)
│   │   ├── tms/                 # 承运商/费率/运单/面单/轨迹 (6个)
│   │   ├── quality/             # IQC/IPQC/OQC/检验标准/不合格品 (5个)
│   │   ├── eam/                 # 设备/保养计划/维修工单/备件 (4个)
│   │   ├── dms/                 # 文档分类/文档/版本 (2个)
│   │   └── bi/                  # BI看板/图表组件 (3个)
│   ├── service/                 # 业务逻辑层（容器注册，24 个）
│   │   ├── finance/             # FinanceService: 应收应付自动生成+收付款核销+日记账
│   │   ├── inventory/           # InventoryService: 出入库+移动加权平均成本核算
│   │   ├── notification/        # NotificationService: 通知发送
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # 订单/仓储/运输/质检/人事/制造服务
│   ├── common/                  # 公共工具类（容器注册，4 个）
│   │   ├── HashidsService.php   # ID 编解码
│   │   ├── SnowflakeService.php # Snowflake ID 生成
│   │   ├── EncryptionService.php# 数据加解密 + 脱敏
│   │   └── I18n.php             # 国际化翻译
│   ├── middleware/              # 中间件（12 个）
│   │   ├── Locale.php           # Accept-Language 语言自动检测
│   │   ├── Cors.php             # 跨域
│   │   ├── SecurityFilter.php   # XSS/SQL注入/路径遍历/命令注入/CSRF 拦截
│   │   ├── RateLimit.php        # Redis 滑动窗口限流
│   │   ├── ApiVersion.php       # API 版本校验
│   │   ├── AdminAuth.php        # JWT 认证 + 黑名单
│   │   ├── AdminPermission.php  # RBAC 权限校验
│   │   ├── OperationLog.php     # 操作日志自动记录
│   │   ├── TenantScope.php      # 多租户隔离（静态调用）
│   │   ├── TracingId.php        # 全链路 TraceId
│   │   ├── TrackingSignature.php# 请求签名校验
│   │   └── StaticFile.php       # 静态文件服务（webman 内建）
│   ├── model/                   # 数据模型（161 个）
│   ├── queue/                   # 队列任务
│   └── process/                 # 进程 (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter 全平台 (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # 业务页面 (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # 响应式布局
│   │       └── theme/          # Material 3 主题
│   └── harmonyos/              # HarmonyOS 客户端
├── config/                     # 配置文件
│   ├── route.php               # 路由 + API 版本策略
│   ├── middleware.php           # 全局中间件注册
│   ├── translation.php          # 语言配置
│   └── plugin/hg/apidoc/        # API 文档配置（管理端25模块+客户端3模块）
├── database/
│   ├── install.sql              # 完整安装SQL（163张表 + 种子数据，全部迁移已并入）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 数据库备份脚本
│       ├── backup.sh           # mysqldump+gzip，30天保留
│       └── restore.sh          # 交互式恢复
├── docs/                       # 文档
│   ├── ARCHITECTURE.md         # Mermaid 架构图
│   ├── DESIGN.md               # 设计文档
│   ├── FEATURE_DESIGN.md       # 功能设计文档
│   ├── SECURITY.md             # 安全架构设计
│   ├── API.md                  # API 参考文档
│   ├── nginx-security.conf     # Nginx 安全参考配置
│   ├── diagrams/               # 分解架构图
│   └── superpowers/            # 规范与计划
│       ├── specs/              # 设计规范
│       └── plans/              # 实现计划
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
├── tests/                      # 测试
├── vendor/                     # Composer 依赖
├── CLAUDE.md                   # 本文件
├── README.md                   # 中文说明
├── README_EN.md                # 英文说明
├── .env                        # 环境变量（不纳入版本控制）
├── .env.example                # 环境变量模板
├── .env.docker                 # Docker 环境变量
├── composer.json               # PHP 依赖
├── Dockerfile                  # Docker 构建（含 OPcache + event + redis 扩展）
├── docker-compose.yml          # Docker 编排
└── .github/
    └── workflows/
        └── ci.yml              # CI/CD 流水线（PHP语法+PHPStan+CS Fixer+PHPUnit+composer audit，多版本矩阵）
```

## Cadeia de execução de middlewares

```
全局:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → {路由中间件}
/health:  Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → Controller
/admin:   Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api:     Locale → Cors → SecurityFilter(方法检查→405) → RateLimit → TracingId → ApiVersion → Controller
```

## Reforços de segurança

- **Restrição de métodos HTTP**: o SecurityFilter permite apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padronizados retornam 405
- **Cabeçalho CSP**: Content-Security-Policy + X-Permitted-Cross-Domain-Policies injetados em todas as respostas
- **Bloqueio de conta**: após 5 falhas consecutivas de login, a conta fica bloqueada por 15 minutos
- **Limite de sessões simultâneas**: no máximo 3 Tokens válidos por usuário; ao exceder, o Token mais antigo entra na lista negra
- **security.txt**: endpoint `/.well-known/security.txt` RFC 9116
- **Configuração de segurança do Nginx**: referência de reforço de segurança para proxy reverso em `nginx-security.conf`

## Estratégia de versões da API

A versão é controlada pelo cabeçalho de requisição `API-Version` (padrão `v1`), sem aparecer na URL:

```bash
curl -H "API-Version: v1" http://localhost:8788/api/auth/login
```

Para adicionar uma nova versão, basta criar o diretório `app/api/{version}/controller/` e registrá-lo no middleware `ApiVersion`.

## Estratégia de rate limit

Janela deslizante no Redis (Lua atômico), padrão 60 vezes/minuto/IP/rota:
- Login: 10 vezes/minuto
- Registro: 5 vezes/minuto
- Cabeçalhos de resposta: `X-RateLimit-Limit/Remaining/Reset`; ao exceder, acrescenta `Retry-After`

## Convenções de código

### PHP
- Referências a funções/classes globais sem `\` na frente, usando `use` para importar
- Arquivos de configuração devem incluir comentários em chinês explicando o significado de cada item
- Todo arquivo `.php` novo deve ter a declaração de copyright no cabeçalho

### Banco de dados
- Prefixo de tabelas: `erp_`
- Chave primária `id`: tipo BIGINT, não incremental, gerada por snowflake
- Campos sensíveis usam o trait `erikwang2013/encryptable` para criptografia/descriptografia automática
- O schema tem database/install.sql como única fonte de verdade (SQL em arquivo único)

### Flutter
- O layout do lado Web usa o estilo de painel de administração para PC (barra lateral + barra superior + área de conteúdo)
- Usa gerenciamento de estado GetX, `ApiService` singleton (Dio + interceptor JWT)
- Persistência de Token com `shared_preferences`
- Breakpoints responsivos: mobile (< 768px) e desktop (>= 768px)

### HarmonyOS
- Usa o cliente HTTP nativo `@ohos.net.http`
- Refresh transparente de Token: em 401, chama automaticamente `/api/auth/refresh`
- Falha no refresh redireciona automaticamente para a página de login

## Implantação

### Docker Compose (ambiente de produção recomendado)

O `docker-compose.yml` na raiz do projeto orquestra 5 serviços:

| Serviço | Observação |
|------|------|
| `nginx` | Proxy reverso Nginx (80/443), serviço de arquivos estáticos |
| `app` | Aplicação webman PHP 8.3, construída com `Dockerfile` (inclui OPcache + event + redis) |
| `mysql` | MySQL 8.0, persistência com volume de dados |
| `redis` | Redis 7 Alpine, cache/rate limit/Session |
| `elasticsearch` | Elasticsearch 8.x, busca full-text |

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define o pipeline do GitHub Actions (matriz PHP 8.2/8.3/8.4):

- Verificação de sintaxe PHP (`php -l`)
- Análise estática PHPStan (`vendor/bin/phpstan analyse`)
- Verificação de estilo PHP CS Fixer (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- Testes unitários PHPUnit
- Auditoria de segurança do Composer (`composer audit --no-dev`)

### Backup do banco de dados

`database/backup/backup.sh` — mysqldump + gzip, limpeza automática de backups antigos com mais de 30 dias.
`database/backup/restore.sh` — restauração interativa, lista os backups disponíveis para escolha.

### Monitoramento

O endpoint `GET /metrics` (`MetricsController`) gera o formato de texto Prometheus, com 5 métricas gauge:
- `openadmin_http_requests_total` — total de requisições
- `openadmin_active_users` — número de usuários ativos
- `openadmin_db_connection_status` — status da conexão com o banco (0/1)
- `openadmin_redis_connection_status` — status da conexão com o Redis (0/1)
- `openadmin_memory_usage_bytes` — uso de memória
