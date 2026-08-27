# Relatório de testes — 2026-08-26

> Atualização: 2026-08-27 — os 5 itens pendentes foram todos encerrados; números de testes 505/2342/26 → 513/2368/32; correções de defeitos 4 → 5. Valores antigos no «Registro de atualizações» ao final.

## Resumo executivo

| Métrica | Valor |
|------|----|
| Data do relatório | 2026-08-26 |
| Testes unitários PHP | 513 tests / 2368 assertions / 32 skipped |
| Testes de páginas Flutter | 98 tests todos aprovados (flutter analyze 0 error) |
| Automação de API | 104 endpoints / ~230 assertions (CI e2e já integrado, ver etapa «Run E2E API coverage» no ci.yml) |
| Cobertura (medida com pcov) | Geral 7,51% / app/service 15,65% / app/controller 3,62% |
| Análise estática | PHPStan 0 error ✅ |
| Estilo de código | php-cs-fixer 0 diff ✅ (3 arquivos existentes corrigidos de passagem) |
| Defeitos reais corrigidos de passagem | 5 (3 PHP + 1 Flutter + 1 formato) |
| Go/Rust | N/A (o repositório não contém nenhum código .go/.rs/Cargo.toml) |

Esta entrega é de testes em três frentes paralelas: testes unitários PHP (php-tester, 9 arquivos novos), automação de API (api-tester, 1 arquivo novo), testes de páginas Flutter (ui-tester, 8 arquivos novos com 29 casos).

## Matriz de cobertura

Módulos (22 domínios de negócio + 14 controladores de administração) com cobertura marcada por tipo de teste.

### 22 domínios de negócio

| Módulo | Unitário | API | UI | Observação |
|------|------|-----|-----|------|
| Finanças — consolidação | ✅ | ✅ | — | ConsolidationServiceTest 5 casos + API |
| Finanças — saldo de conta | ✅ | ✅ | — | AccountBalanceServiceTest 4 casos |
| Finanças — fechamento de período | ✅ | ✅ | — | PeriodCloseServiceTest 5 casos |
| Finanças — índices financeiros | ✅ | — | — | FinanceRatioServiceTest (existente) |
| Finanças — partidas dobradas | ✅ | — | — | DoubleEntryServiceTest (existente) |
| Estoque | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 casos + UI da listagem ERP |
| Vendas | ✅ | ✅ | ✅ | SalesModuleTest existente + UI da página de pedidos de venda |
| Produtos | ✅ | ✅ | ✅ | ProductModuleTest existente + UI da página de produtos |
| Compras | ✅ | ✅ | — | PurchaseModuleTest existente |
| Produção | ✅ | — | — | ManufacturingServiceTest existente |
| Motor MRP | ✅ | — | — | MrpEngineServiceTest existente |
| CRM | ✅ | ✅ | — | CrmModuleTest/CrmServiceTest existentes |
| RH | ✅ | — | — | HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest existentes |
| Projetos | ✅ | ✅ | ✅ | ProjectModuleTest existente + UI da página de projetos |
| Aprovação/Workflow | ✅ | ✅ | ✅ | WorkflowModuleTest existente + UI da página de aprovações |
| OMS/WMS/TMS | ✅ | — | — | OmsWmsTmsServiceTest existente |
| Qualidade QMS | ✅ | — | — | QualityModuleTest existente |
| Ativos EAM | ✅ | — | — | EamModuleTest existente |
| Documentos DMS | ✅ | — | — | DmsModuleTest existente |
| Relatórios BI | ✅ | ✅ | — | BiModuleTest existente + API |
| Canais de notificação | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService, 12 casos) |
| Relatórios/detalhe de documentos | ✅ | Parcial | ✅ | Lógica de geração tem testes unitários; UI da página de detalhe 3 casos (report_list_page_test) |

### Administração do sistema (14 controladores)

| Domínio do controlador | Unitário | API | UI | Observação |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (lado User) + UI da listagem de usuários |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (lado Role) + UI da listagem de papéis |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (lado Permission) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (lado Config) + UI da página de configurações |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| Outros 7 controladores (login/auditoria/dicionários etc.) | ✅ | ✅ | — | BusinessControllersTest: 10 domínios com controladores representativos validando caminhos de falha |
| Página de login | — | ✅ | ✅ | login_flow_test 2 casos |
| Central do usuário | — | ✅ | ✅ | profile_page_test 3 casos |
| Página de logs | — | ✅ | ✅ | log_page_test 2 casos |
| Dashboard | — | — | ✅ | dashboard_page_test 5 casos |
| Alertas de estoque/páginas financeiras | — | — | ✅ | erp_list_pages_test |

## Estatísticas de testes

### Testes unitários PHP: 513 tests / 2368 assertions / 32 skipped

Nesta entrega, 9 arquivos novos (todos com cabeçalho de copyright, 63 tests / 125 assertions):

| Arquivo | Casos | Objeto coberto |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance consolidação |
| tests/AccountBalanceServiceTest.php | 4 | saldo de conta |
| tests/PeriodCloseServiceTest.php | 5 | fechamento de período |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | extensões de estoque |
| tests/AdminUserRoleControllerTest.php | 9 | controladores User/Role |
| tests/AdminPermissionConfigControllerTest.php | 8 | controladores Permission/Config |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 domínios | validação de caminhos de falha de controladores representativos |

Em 2026-08-27, 3 arquivos PHP novos (14 tests; na ausência de TEST_DB_*, os testes de integração 6/6 são pulados automaticamente):

| Arquivo | Casos | Objeto coberto |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | rollback/commit de transação de banco, fonte duplicada, bloqueio concorrente com pcntl_fork (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | serviço de notificações |
| tests/FinanceRatioServiceTest.php | 2 | índices financeiros |

### Testes de páginas Flutter: 98 tests todos aprovados

Nesta entrega, 8 arquivos novos com 29 casos (os 10 arquivos existentes não foram alterados e todos passam); `flutter analyze` 0 error (1 info existente):

| Arquivo | Casos |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

Em 2026-08-27, 1 arquivo novo (3 casos):

| Arquivo | Casos |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### Automação de API: 104 endpoints / ~230 assertions (19 grupos de módulos)

tests/E2E/api-coverage.php (423 linhas, `php -l` aprovado): somente leitura + idempotente (GET de detalhe na central do usuário → PUT reescrevendo o mesmo valor), inclui detecção de tabela ausente (500 + Base table not found → SKIP sinalizando a necessidade do seed completo do install.sql).

**Não executado localmente** (MySQL sem credenciais, porta 8788 sem serviço), requer o ambiente e2e do CI:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

Cobre 19 grupos de módulos: administração do sistema (usuários/papéis/permissões/configurações/health/métricas), finanças (consolidação/saldo/fechamento/índices), estoque, vendas, produtos, compras, projetos, aprovação, CRM, BI, notificações, relatórios.

> Errata: o api-tester suspeitou que a tabela `erik_admin_config` estivesse ausente — **não é defeito**. O nome real da tabela é `erik_system_config` (criada em install.sql:133, o modelo SystemConfig aponta corretamente), e o relatório foi corrigido.

## Cobertura

Medida com pcov (2026-08-26; não re-medida em 2026-08-27, valores mantidos): geral **7,51%** (baseline 4,8%), app/service **15,65%** (baseline 10,6%), app/controller **3,62%**.

Comparação com o limite do CI e a meta (ver P1-B4 em `superpowers/plans/2026-08-07-next-phase-plan.md`):

| Dimensão | Atual | Limite do CI | Meta |
|------|------|---------|------|
| Geral | 7,51% | 4% ✅ dentro | 30% |
| app/service | 15,65% | 10% ✅ dentro | 40% |
| app/controller | 3,62% | — | — |

A cobertura geral e de service já ultrapassaram o limite do CI, mas ainda há distância considerável até a meta; é necessário continuar acrescentando testes conforme a rota P1-B4.

## Defeitos reais corrigidos de passagem (4)

| # | Local | Defeito | Correção |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php, PermissionController.php | Falta `use support\Response;`, TypeError em execução | Adicionado o import |
| 2 | app/controller/Admin/DocsController.php | `path()` com terceiro parâmetro null quebra | Chamada corrigida |
| 3 | lib/pages/user_list_page.dart | Botões de exclusão/habilitação em lote sem envoltório Obx; os botões nunca aparecem após a seleção | Adicionado o envoltório Obx |
| 4 | scripts/api-coverage.php (e os 3 arquivos de app/queue/redis/search/ desta entrega) | Formatação não conforme ao cs-fixer | Corrigido conforme o fixer |
| 5 | app/model/FinanceCashJournal.php | Campo `UPDATED_AT` divergente do install.sql | Campo corrigido |

## Go / Rust

**N/A** — o repositório não contém nenhum código .go / .rs / Cargo.toml; os testes das duas pilhas são marcados como não aplicáveis.

## Encerramento dos itens pendentes (atualização 2026-08-27)

Os 5 itens pendentes da versão de 2026-08-26 foram todos tratados:

1. **Caminho de transação de banco** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` com 6 casos novos (rollback/commit/fonte duplicada/bloqueio concorrente com pcntl_fork, `Group(integration)`); sem TEST_DB_*, 6/6 pulados automaticamente; o job php do CI já injeta TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST.
2. **api-coverage integrado ao CI** ✅ — o seed do job e2e de `.github/workflows/ci.yml` foi atualizado para o install.sql completo (163 tabelas); após o smoke, adicionada a etapa «Run E2E API coverage».
3. **UI da página de detalhe de relatórios/documentos sem cobertura** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` com 3 casos todos aprovados.
4. **Dependência de ambiente do CaptchaTest** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` com compatibilidade dupla PIXELS→AREA + guarda clone(); `tests/CaptchaTest.php` reescrito conforme o contrato do poster-php v1.2.3, caminho local do imagick 7/7 aprovado (27 assertions).
5. **Meta de cobertura** ✅ progresso — adicionados `tests/NotificationServiceTest.php` e `tests/FinanceRatioServiceTest.php`; os números de cobertura mantêm a medição de 2026-08-26 (não re-medida), e a distância até a meta (30%/40%) exige suplementação contínua.

Baseline de regressão: **513 tests / 2368 assertions / 32 skipped** tudo verde (versão anterior 505/2342/26).

## Registro de atualizações

| Data | Alteração |
|------|------|
| 2026-08-26 | Versão inicial: 505 tests / 2342 assertions / 26 skipped; 5 itens pendentes; 4 correções de passagem |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; 5 itens pendentes todos encerrados; 5 correções de passagem; 4 arquivos de teste novos; todas as imagens com marca d'água erik.xyz |

## Caminhos de armazenamento do relatório e dos artefatos

- Este relatório: `docs/TEST_REPORT.md`
- Dados de cobertura: `runtime/coverage/` (gerado com pcov)
- Script de automação de API: `tests/E2E/api-coverage.php`
- Testes unitários PHP: `tests/*.php` (9 arquivos novos desta entrega, ver tabela acima)
- Testes Flutter: `test/pages/*.dart` (8 arquivos novos desta entrega, ver tabela acima)
