# Planejamento da próxima fase (P4 / período de evolução 1.1)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Elaboração: arquiteto de sistemas ｜ Data: 2026-08-07 ｜ Base: três pesquisas anteriores (planejamento e lacunas / back-end e qualidade / front-end) + verificação por amostragem em campo
> Status: rascunho (aguardando revisão) ｜ Versão alvo: 1.1 (período de evolução)

---

## 1. Posicionamento da fase

O roadmap P0~P3 foi integralmente entregue: 22 módulos de negócio, 163 tabelas, 121 controladores, 24 serviços, 161 modelos, 12 middlewares;
96 páginas Flutter + 34 páginas HarmonyOS; pontuação geral 89/100. **Esta fase não adiciona novos domínios de negócio**, mas completa as capacidades
"implementadas mas sem fechamento de ciclo", trata a dívida de qualidade, elimina a deriva documental, produzindo a **versão de evolução 1.1** de fácil manutenção no longo prazo.

Três julgamentos centrais (todos comprovados por amostragem):

1. **Muitas capacidades "existem mas não estão em efeito"**: o middleware TenantScope e o trait de modelo não estão registrados em `config/middleware.php` (multitenancy é casca vazia);
   a fila está configurada com duplo driver redis/rabbitmq mas `config/process.php` não tem processo consumidor; a conexão WebSocket não valida JWT;
   as estatísticas OMS/WMS/TMS do dashboard Flutter são valores falsos hardcoded, enquanto os endpoints `/dashboard/oms|wms|tms` já existem no back-end mas não são chamados;
   o front-end chama o endpoint inexistente de notificações `/admin/notification/my/read` (no back-end é `/admin/notification/read-all`).
2. **Dívida de qualidade e segurança**: 11 módulos de negócio com zero testes; PHPStan level 5 mas a baseline suprime 974 erros; os 137 testes são todos puramente unitários, sem integração/E2E/cobertura;
   `.env.docker` com muitas chaves fracas; o CI tem apenas job PHP, sem nenhuma barreira de qualidade de front-end.
3. **Deriva documental sistemática**: contagens de testes 132/779→135/799→137/805 inconsistentes em três versões; o apêndice do FUNCTIONS.md diverge muito da medição;
   números do EDITIONS.md contraditórios entre si; as três branches lite/standard/full estão 20~41 commits atrás de main.

**Princípio**: primeiro completar o que está "implementado mas sem fechamento de ciclo" (endpoints mortos, TenantScope/fila não conectados, dashboard mockado), depois adicionar testes e barreiras de qualidade,
e só então otimizar estrutura e documentação. Todas as tarefas são pequenas e claras, concluíveis em uma única sessão de agente; o que não estiver certo é marcado como "a validar".

---

## 2. Análise de lacunas (resumo)

Consolidar as lacunas das três pesquisas em **6 grupos de trabalho**. Cada item traz o caminho da evidência.

### Grupo de trabalho A: Fechamento de ciclo de negócio (prioridade máxima)

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| A1 | "Marcar todas como lidas" de notificações chama endpoint inexistente no front-end | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` chama `/admin/notification/my/read`; a rota do back-end é `POST /admin/notification/read-all` em `config/route.php:250` | Confirmado |
| A2 | Estatísticas OMS/WMS/TMS do dashboard são valores mock, requisição sem JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (Dio independente com `baseUrl: http://localhost:8787`, sem interceptador; `omsStats/wmsStats/tmsStats` hardcoded; comentário "Mock values for now"); endpoints reais no back-end `config/route.php:231-233` | Confirmado |
| A3 | Middleware TenantScope e trait de modelo não conectados; multitenancy é casca vazia | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` existem; a cadeia global de `config/middleware.php` registra apenas Locale/Cors/SecurityFilter/RateLimit/TracingId, e os grupos do route.php também não referenciam | Confirmado |
| A4 | Fila com duplo driver mas sem processo consumidor; sem efeito de ponta a ponta | `config/queue.php` (padrão redis, opcional rabbitmq); `config/process.php` tem apenas os três processos webman/socket/monitor | Confirmado |
| A5 | WebSocket sem autenticação | `app/process/WebSocket.php:23` comentário "could validate JWT here"; em `:47-50` a mensagem auth retorna success:true diretamente, sem validar token | Confirmado |
| A6 | Parâmetro de paginação quebrado em 25 páginas de listagem do HarmonyOS (`${this.page}` dentro de aspas simples não é interpolado) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (verificado por amostragem); outros 24 pontos com o mesmo padrão | Confirmado (lista completa a conferir) |
| A7 | Endpoints de ações de negócio em grande parte sem integração no front-end (liquidação/três demonstrações/atendimento/aprovação/cálculo de salários etc.) | Conclusão da pesquisa da matriz de cobertura; ex.: compras/vendas sem página de liquidação, financeiro sem 13 endpoints, CRM sem follow/funil/fluxo de contratos | A validar (conferir lista por módulo) |
| A8 | Formulários de muitas páginas de negócio têm apenas os campos genéricos name/code | Conclusão da pesquisa (criar pedido de venda/voucher contábil preenchendo apenas nome e código) | A validar (conferir página por página) |

### Grupo de trabalho B: Reconstrução do sistema de testes

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| B1 | 11 módulos de negócio com zero testes: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | Os 19 arquivos de teste em `tests/` cobrem apenas admin/finance/inventory/oms/wms/tms/notification/hr/mrp/classes-base de segurança; os 11 módulos acima não têm arquivo de teste próprio — entre eles crm/eam/dms/quality/report/workflow, seis módulos com **zero menções** em qualquer arquivo de teste; project/purchase/sales/product/bi são referenciados apenas acidentalmente por testes de classes-base genéricas ou de módulos vizinhos (amostragem de padrão no ControllerPatternTest, lista de rotas no bootstrap.php, contexto de entrada de estoque purchase/product no InventoryServiceTest, "bi" como substring de debit_amount no DoubleEntryServiceTest), nenhum é cobertura dedicada | Confirmado |
| B2 | Sem integração/E2E/cobertura; os 137 tests / 805 assertions são todos puramente unitários (executam em 1,2s, puramente em memória) | Medição real `vendor/bin/phpunit`: "OK (137 tests, 805 assertions)" | Confirmado |
| B3 | PHPStan level 5 mas a baseline suprime 974 erros | Medição real: 974 nós message em `phpstan-baseline.neon` | Confirmado |
| B4 | CI sem coleta de cobertura, sem job de testes de integração | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, apenas composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Confirmado |
| B5 | Controladores purchase/sales com dependências hardcoded de serviços | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143` (nos dois arquivos, `use` declarado em :15-16, `new InventoryService()/new FinanceService()` instanciados em :142-143) | Confirmado |

### Grupo de trabalho C: Infraestrutura e governança de segurança

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| C1 | Chaves fracas em `.env.docker` | `JWT_SECRET_KEY=change-me-...`, `ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`, `DB_PASSWORD=root`, `ES_PASSWORD=changeme`, `RABBITMQ_PASSWORD=guest` (.env.docker:15,32,37,51,67,81) | Confirmado |
| C2 | Validação forte de variáveis de ambiente incompleta | Pesquisa: apenas ENCRYPTION_KEY passa por env_required | A validar (conferir config/jwt.php, encryption.php) |
| C3 | fail-open engolindo erros silenciosamente | Conclusão da pesquisa; escopo a auditar (try/catch vazios, catch sem log) | A validar (precisa de auditoria com grep) |
| C4 | backup-validator.sh e `_rollback.sql` por migração ausentes | `find` no repositório inteiro sem correspondências; as 29 migrações SQL em `database/migrations/` não têm arquivos de rollback correspondentes | Confirmado |
| C5 | Canais de notificação stub (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Confirmado |
| C6 | Lacuna de monitoramento: sem métricas de backlog da fila/número de conexões WebSocket | `app/admin/controller/MetricsController.php` tem 5 gauges atuais | Parcialmente confirmado |

### Grupo de trabalho D: Matriz de versões e governança de documentos

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| D1 | Branches lite/standard/full 20~41 commits atrás de main | `git rev-list --left-right --count main...lite|standard|full` medido: 41/41/20 behind, e lite/standard têm 6~7 commits exclusivos à frente cada | Confirmado |
| D2 | Números do EDITIONS.md contraditórios | Tabela de visão geral: controladores 48/42/70, módulos de negócio 6/6/12; porém a seção de caminho de upgrade escreve 12/12/19 módulos, 163 tabelas; incompatível com os 121 controladores medidos | Confirmado |
| D3 | Apêndice do FUNCTIONS.md com deriva | O apêndice escreve 11 arquivos/90 métodos/168 assertions/9 middlewares/22 migrações; medido: 19~20 arquivos/137 testes/805 assertions/12 middlewares/29 migrações | Confirmado |
| D4 | Contagem de testes com deriva em três versões (132/779→135/799→137/805) | Histórico de documentos e registros de commit git | Confirmado |
| D5 | Matriz de conclusão marca QMS/EAM/DMS/BI com 🔴 mas o código já existe | Matriz perto de `docs/FUNCTIONS.md:555` vs `app/controller/{quality,eam,dms,bi}/` já implementados | Confirmado |
| D6 | Critério de contagem de controladores confuso: docs/CLAUDE.md escreve "104 controladores de negócio", medido no total 122 | `find app -path '*/controller/*.php' | wc -l` = 122 (inclui admin 14 + api 3 + negócio 104 + Index/Install); critério da pesquisa 121 | Confirmado (diferença de critério) |
| D7 | Critério de contagem de migrações: pesquisa 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (numeradas até 000030, faltam 000007/000008) | Confirmado (29 é a medição) |

### Grupo de trabalho E: Qualidade e alinhamento do front-end

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| E1 | CI sem flutter analyze/test/build, sem build hvigor | `.github/workflows/ci.yml` tem apenas job PHP | Confirmado |
| E2 | README afirma que o CI inclui análise estática Flutter, o que não corresponde à realidade | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml sem esse passo | Confirmado |
| E3 | Flutter tem apenas 1 teste de fumaça | `apps/flutter/test/widget_test.dart` é o único arquivo de teste | Confirmado |
| E4 | Token HarmonyOS sem persistência (AppStorage apenas em memória, volta à página de login em inicialização a frio) | Conclusão da pesquisa (a conferir em `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`) | A validar |
| E5 | 25 páginas do HarmonyOS são modelos, listas somente leitura de name/code sem criar/editar/excluir | Verificado por amostragem: OrderListPage.ets tem 65 linhas, apenas lista somente leitura de name/code | Confirmado |
| E6 | Profundidade de cobertura do front-end insuficiente (ver A7/A8) | Idem | A validar |

### Grupo de trabalho F: Camadas de API e governança de arquitetura (baixa prioridade, conforme a capacidade)

| # | Lacuna | Caminho da evidência | Status |
|---|------|----------|------|
| F1 | Versionamento /api com apenas 3 controladores; todo o negócio no bloco único /admin | `app/api/v1/controller/` tem apenas Captcha/Auth/Product | Confirmado |
| F2 | 10 módulos de controladores consultam modelos diretamente sem camada de serviço | Conclusão da pesquisa (controladores crm/product etc. usam consultas diretas aos modelos) | Parcialmente confirmado (a auditar integralmente) |
| F3 | purchase/sales com `new` hardcoded de serviços em vez de injeção de dependências | Evidência B5 | Confirmado |

---

## 3. Planejamento por fases

Em três lotes por prioridade (P0→P1→P2), **cada período pode ser lançado de forma independente e todos os critérios de aceitação são quantificáveis**. Prazo total de aproximadamente **8~9 semanas** (premissa de paralelismo: estimativa com **2~3 desenvolvedores em paralelo + colaboração da equipe de agentes**; soma das tarefas individuais ≈ **77 dias-pessoa** — P0 ≈12,5d, P1 ≈29,5d, P2 ≈35d — se executadas em série por uma pessoa, seriam ~15 semanas. Base do paralelismo: tarefas pequenas de back-end como A1/A4/A5 são independentes entre si e podem rodar em paralelo; os testes de cada módulo do B1 podem ser divididos em subtarefas paralelas; os grupos B/C podem se sobrepor aos E/D entre períodos; as tarefas de front-end Flutter/HarmonyOS não bloqueiam as de back-end; dependências explícitas entre tarefas ver §5).

**Sistema de numeração**: a numeração das tarefas por fase corresponde 1:1 à numeração das lacunas do §2 (A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3); entre eles, D6/D7 (critérios de controladores e migrações) são incorporados à tarefa D3 para unificação, E2 (declaração falsa do README) é incorporado à aceitação de E1, E6 (profundidade de cobertura) é incorporado ao A7-2, F1 (versionamento /api) é explicitamente não feito neste período (ver §6); há também a tarefa i18n correspondente à pesquisa "Flutter i18n não concluído", sem numeração na tabela de lacunas.

### 3.1 Lote 1 P0: Baseline de fechamento de ciclo (semanas 1~2)

**Objetivo**: eliminar endpoints mortos e dados falsos, transformando as capacidades existentes não conectadas (TenantScope/fila/WebSocket) em utilizáveis ou explicitamente rebaixadas.

| Tarefa | Conteúdo | Escopo | Critério de aceitação | Prazo |
|------|------|----------|----------|------|
| A1 | Corrigir notificação "marcar todas como lidas": o front-end passa a chamar `POST /admin/notification/read-all` (ou o back-end ganha rota alias — escolher uma, recomenda-se alterar o front-end) | `notification_page.dart` + `config/route.php` | Chamada manual/automática passa; nova assertion PHPUnit comprovando que a rota existe | 0,5d |
| A2 | Dashboard com dados reais: remover o Dio independente, usar ApiService (interceptador JWT); as três abas OMS/WMS/TMS chamam `/dashboard/oms\|wms\|tms`; excluir valores falsos hardcoded; manter a semântica de cache Redis 5m | `dashboard_controller.dart` + páginas relacionadas | Com sessão logada, as três abas do dashboard mostram dados reais do back-end; no painel Network é visível 200 com cabeçalho Authorization; excluir comentário mock | 2d |
| A3 | Conectar o TenantScope: registrar no grupo de rotas `/admin`; o ID do tenant vem do claim JWT ou do cabeçalho `X-Tenant-Id` (**ponto de decisão**, ver §5); o trait do modelo já está pronto, sem grandes alterações | `config/route.php`, `app/middleware/TenantScope.php`, `config/middleware.php` | Dados de dois tenants mutuamente invisíveis (novo teste de integração); sem cabeçalho de tenant retorna 400 em vez de deixar passar silenciosamente; **rebaixamento alternativo**: se o momento for considerado imaturo, documentar explicitamente "multitenancy é capacidade reservada" e fornecer os passos de ativação; aceitação = documento e código consistentes | 2d |
| A4 | Fila de ponta a ponta: adicionar em `config/process.php` o processo consumidor `redis-queue` (driver redis padrão); nova tarefa de fumaça observável (ex.: escrever log de operação assíncrono); documentar os passos de troca para rabbitmq | `config/process.php`, `app/queue/` | Após iniciar, o processo consumidor está online (`php start.php status`); após enfileirar a tarefa de fumaça, o efeito colateral alvo aparece em até 5s | 1d |
| A5 | Autenticação WebSocket: validar JWT no estabelecimento da conexão/mensagem `auth` (reutilizando a lógica do AdminAuth); token inválido retorna auth_result:false e desconecta; sincronizar documentação | `app/process/WebSocket.php` + ponto de conexão do front-end | Conexões sem token ou com token forjado são rejeitadas; conexão com token válido é bem-sucedida; novo teste cobrindo | 1d |
| A6 | Corrigir paginação HarmonyOS: os 25 pontos de interpolação com aspas simples viram template string/concatenação; page incrementa + carregamento ao final + pull-to-refresh; extrair componente de paginação unificado | `apps/harmonyos/entry/src/main/ets/pages/**` (25 arquivos) | grep no repositório inteiro sem resíduo do padrão `${this.page}` em aspas simples; parâmetros de requisição de paginação corretos; build passa | 2d |
| A7-1 | Zerar endpoints mortos: com a matriz de cobertura da pesquisa como base, rodar uma comparação automática "URL do front-end × rota do back-end" (script extrai as strings de requisição do Flutter/HarmonyOS vs `config/route.php`), gerando a lista de diferenças restantes | `apps/flutter/lib`, `apps/harmonyos/.../pages`, `config/route.php` | Artefato do script de comparação commitado (docs/); na lista de diferenças, "front-end chamou mas back-end não existe" zera (inexistentes mas razoáveis marcados na whitelist) | 2d |
| A8-1 | Complementar campos em formulários de alto valor: páginas de pedidos de compra/venda e voucher contábil ganham campos-chave de negócio (valor/data/unidade parceira/linhas de detalhe); apenas complementar, sem fazer engine de formulários | Páginas Flutter correspondentes | Formulário consegue criar documento completo com campos de negócio, API retorna 200 | 2d |

**Resumo de aceitação do P0**: A1~A6 todos implementados; lista de endpoints mortos zerada; CI todo verde; sem nova deriva documental (alterações sincronizam a lista de funcionalidades do docs/CLAUDE.md).

### 3.2 Lote 2 P1: Baseline de testes e segurança (semanas 3~5)

**Objetivo**: sistema de testes evolui de "apenas unitários" para "unitários+integração+cobertura"; pontos fracos de segurança zerados.

| Tarefa | Conteúdo | Escopo | Critério de aceitação | Prazo |
|------|------|----------|----------|------|
| B1 | Complementar testes dos 11 módulos de negócio: testes de camada de serviço/modelo por módulo, cobrindo CRUD + ações centrais (liquidação, fluxo de aprovação, fluxo de QC, ordens de equipamento etc.) | `tests/` (novos arquivos de teste crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow) | Novos ≥150 tests / ≥500 assertions; cada um dos 11 módulos ≥10 tests; `vendor/bin/phpunit` todo verde | 2w |
| B2 | Testes de integração: aproveitando os services mysql8/redis7 já existentes no CI, novo grupo de testes de integração (CRUD com banco real + rollback de transação + verificação de isolamento do TenantScope + fumaça da fila) | `tests/Integration/` + agrupamento no `phpunit.xml` | Grupo de integração todo verde no CI; executável localmente com `--group=integration` | 1w |
| B3 | Fumaça E2E: percorrer via HTTP real health→login→CRUD central→dashboard, com script | `tests/E2E/` (scripts curl/php) | Novo job do CI percorre 10 cadeias centrais; falha = vermelho | 2d |
| B4 | Cobertura: integrar phpunit --coverage com limiares (camada de negócio ≥40%, geral ≥30%; a validar se o CI suporta coleta com xdebug) | `phpunit.xml`, `ci.yml` | CI gera relatório de cobertura; abaixo do limiar falha | 1d |
| B5 | Serviços nos controladores (4 módulos de alta frequência): controladores finance/inventory/sales/purchase sem `new`, usando o contêiner (`support\Container`) — preparando o caminho para os testes do B1 | `app/controller/{finance,inventory,sales,purchase}/**` | Sem resíduo de `new InventoryService/FinanceService`; testes existentes todos verdes | 3d |
| C1 | Zerar chaves fracas: `.env.docker`/`.env.example` passam a ter placeholders aleatórios + validação forte na inicialização (ausente/igual a placeholder recusa iniciar); CI ganha passo de `validação de env` | `.env*`, `config/*.php`, `ci.yml` | Iniciar com `change-me` falha diretamente com orientação; novo contêiner Docker gera chaves aleatórias automaticamente | 1d |
| C2 | Expandir validação forte de variáveis de ambiente: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD entram no env_required (primeiro conferir a situação do config/jwt.php; a validar) | `config/*.php` | Sem qualquer chave crítica, a inicialização falha com mensagem de erro clara em chinês | 1d |
| C3 | Auditoria fail-open: grep de catch vazios/catch sem log; mudar para fail-closed + log (incluindo TraceId) | todo o app/ | Lista de auditoria commitada; itens corrigidos com teste ou log comprovando | 2d |
| C4 | Governança de migrações: complementar `database/backup/backup-validator.sh` (validação automática de restauração após backup) + 29 `_rollback.sql` por migração (deduzidos do install.sql) | `database/` | Script validator executa nos arquivos de backup (backup→restore→comparar nº de tabelas/linhas); cada arquivo de migração tem um `_rollback.sql` de mesmo nome ao lado | 2d |
| C5 | Implementar canais de notificação (lacuna C5): pelo menos um canal utilizável (recomendado email: driver SMTP ou driver com log em arquivo); se o momento for imaturo, documentar explicitamente o rebaixamento para "apenas mensagens internas + pontos de adaptação reservados para email/wecom/dingtalk" com passos de integração (escolher um; decisão explícita obrigatória) | `app/service/notification/ChannelRouter.php` + nova classe de driver + docs | Driver de email: após envio bem-sucedido da notificação, ChannelRouter retorna true (teste usa driver de log para assertion); se rebaixado: comentário em ChannelRouter.php:23 e docs marcam explicitamente "reservado", eliminando a ambiguidade de "stub for future implementation" | 1,5d |
| C6 | Complementar métricas de monitoramento: backlog da fila (redis LLEN), número de conexões WebSocket online | `MetricsController.php` | `/metrics` passa a emitir 2 novos gauges | 1d |

**Resumo de aceitação do P1**: total de testes ≥287 (137+150); relatório de cobertura gerado e acima do limiar; inicialização falha com chaves fracas/ausentes; validator e scripts de rollback no lugar; pelo menos um canal de notificação utilizável ou rebaixamento documentado; novos jobs de integração/E2E/cobertura no CI todos verdes.

### 3.3 Lote 3 P2: Documentos, matriz de versões e profundidade do front-end (semanas 6~8)

**Objetivo**: números dos documentos totalmente alinhados com os fatos do código (verificação automática), matriz de versões recupera a confiabilidade, front-end completa a profundidade de alto valor.

| Tarefa | Conteúdo | Escopo | Critério de aceitação | Prazo |
|------|------|----------|----------|------|
| D1 | Sincronizar as três branches: main é mesclada em lite/standard/full, resolver conflitos, CI das três branches todo verde; **ponto de decisão**: a partir daí, adotar a estratégia "main como única fonte de desenvolvimento; branches de versão recebem apenas cherry-pick a cada release" | três branches git + ci.yml | behind das três branches = 0; CI de cada uma verde; resolução de conflitos registrada | 1w |
| D2 | Reescrever EDITIONS.md: com base na medição real (números de tabelas/controladores/módulos vindos do script de contagem de código), remover trechos contraditórios | `docs/EDITIONS.md` | Todos os números do documento iguais à saída do script | 1d |
| D3 | Automatizar estatísticas dos documentos: escrever `scripts/doc-stats.sh` (contagem de controladores/serviços/modelos/migrações/testes/middlewares + saída do phpunit), o apêndice do FUNCTIONS.md passa a citar sua saída; unificar também D6 (critérios de controladores 104/121/122) e D7 (critérios de migrações 22/29/30) no critério único do script | `scripts/doc-stats.sh`, `docs/FUNCTIONS.md`, `docs/CLAUDE.md` | Saída do script e documento iguais; todos os números de README/docs reproduzíveis pelo script (incluindo a unificação dos critérios de controladores/migrações) | 2d |
| D4 | Corrigir a matriz de conclusão: itens realmente implementados (QMS/EAM/DMS/BI etc.) viram ✅, com evidência no código | `docs/FUNCTIONS.md` | Matriz correspondente 1:1 aos diretórios de `app/controller/`, sem 🔴/✅ deslocados | 1d |
| D5 | Job de verificação documental no CI: rodar doc-stats e comparar com os documentos; deriva = vermelho | `ci.yml` + script | Alterar um número e o CI fica vermelho (demonstração com autoteste) | 1d |
| E1 | Job Flutter no CI: flutter analyze + flutter test + build web, integrados ao ci.yml | `ci.yml`, `apps/flutter/` | Os três passos todos verdes; declaração do README.md:635 igual à realidade | 1d |
| E3 | Expandir testes Flutter: interceptador ApiService/refresh 401, fluxos AuthService, validações de formulários-chave, ≥20 testes widget/unit | `apps/flutter/test/` | `flutter test` todo verde, ≥20 tests | 1w |
| E4 | Persistência de token HarmonyOS: AppStorage com persistência real + restauração em inicialização a frio + lógica de refresh 401 (primeiro conferir a situação do ApiService; a validar) | `apps/harmonyos/.../service/ApiService.ets` | Matar o processo e reiniciar mantém a sessão logada; token expirado faz refresh automático | 2d |
| E5 | Complementar criar/editar/excluir nas páginas centrais do HarmonyOS: por ordem de valor (2~3 páginas de listagem de cada: compras/vendas/estoque/financeiro/OMS), completando ações de criar/editar/excluir e formulários em cada página | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | As ≥10 páginas de listagem selecionadas têm criar/editar/excluir e se comunicam com o back-end; build hvigor passa (sem ambiente do SDK HarmonyOS, marcar "aguardando CI pronto") | 1w |
| i18n | i18n mínimo do Flutter (lacuna da pesquisa "Flutter i18n não concluído"): mensagens de erro do ApiService e textos-chave de login/navegação/dashboard integrados ao i18n (arquivos arb, em articulação com `app/common/I18n.php` do back-end); **apenas o mínimo viável, sem reformular os textos de todas as páginas** | `apps/flutter/lib/app/services/`, `apps/flutter/lib/l10n/` | Mensagens de erro-chave e ≥10 textos de página alternam conforme o idioma (en/zh); `flutter test` todo verde | 2d |
| A7-2 | Cobertura profunda do front-end: conforme a lista comparada do A7-1, completar páginas de endpoints-chave — liquidação de compras/vendas, três demonstrações financeiras/ajuste de fim de período/contas bancárias, CRM follow/funil/fluxo de contratos etc. | `apps/flutter/lib/app/pages/**` | Na lista comparada, os itens de alta prioridade "existe no back-end mas sem cobertura no front-end" (liquidação/três demonstrações/atendimento/aprovação/salários) zeram | 1w |
| F2/F3 | Extração leve de camada de serviço (opcional, conforme a capacidade): extrair camada de serviço fina + injeção de dependências dos 3~5 módulos com mais consulta direta a modelos; **explicitamente sem refatoração total obrigatória** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Controladores dos módulos extraídos sem consulta direta a modelos; testes existentes todos verdes; módulos não extraídos com documentação "controlador consulta o modelo diretamente, dívida técnica conhecida" | 1w |

**Resumo de aceitação do P2**: três branches sincronizadas e CI verde; números dos docs reproduzíveis por script; CI com job Flutter e verificação documental; Flutter ≥20 testes; HarmonyOS com persistência + ≥10 páginas com criar/editar/excluir; cobertura de endpoints de alta prioridade zerada.

---

## 4. Critérios de aceitação (resumo, todos verificáveis)

- **Endpoints**: o endpoint de notificação A1, `/dashboard/oms|wms|tms` do A2 e os endpoints de alta prioridade do A7 podem ser chamados via curl com JWT retornando 200/dados de negócio.
- **Testes**: `vendor/bin/phpunit` todo verde (≥287 tests); `flutter test` todo verde (≥20); jobs de integração/E2E verdes no CI.
- **Segurança**: iniciar com chave `change-me` falha; token inválido no WebSocket é rejeitado; sem catch vazio engolindo erros silenciosamente (lista de auditoria).
- **Canais/i18n**: pelo menos um canal de notificação utilizável ou rebaixamento documentado; mensagens de erro-chave e ≥10 textos do Flutter alternam entre chinês/inglês (mínimo viável).
- **CI**: todos os jobs de `.github/workflows/ci.yml` verdes (matriz PHP + integração + cobertura + flutter + verificação documental).
- **Documentos**: a saída de `scripts/doc-stats.sh` é igual a todos os números dos docs (deriva = CI vermelho).
- **Branches**: `git rev-list --left-right --count main...lite|standard|full` é `0 0` em todos.
- **Front-end**: HarmonyOS sem resíduo de `${this.page}` em aspas simples; inicialização a frio mantém a sessão; páginas centrais com criar/editar/excluir funcionando com o back-end.

---

## 5. Dependências e riscos

**Dependências**:
- Grupo A (fechamento de ciclo) → Grupo B (testes): os testes de B1/B2 devem mirar endpoints **reais e utilizáveis**; por isso o P0 primeiro corrige endpoints mortos e conexões, e o P1 complementa os testes.
- B5 (serviços nos controladores) → B1 (testes): **prepara o caminho apenas para os testes dos quatro módulos finance/inventory/sales/purchase que ele cobre** (eliminando o `new` hardcoded, o serviço pode receber mock injetado; entre eles, purchase/sales são módulos de zero teste, finance/inventory já têm testes que podem ser melhorados de quebra); os testes dos demais módulos de zero teste (crm/eam/dms/quality/project/product/bi/report/workflow) **não dependem** de B5 e podem avançar em paralelo com B5.
- D1 (sincronização das branches) → D3/D5 (verificação documental): após a sincronização, main é a única fonte de verdade; só então o critério documental pode ser único.
- E1 (CI Flutter) → E3 (expansão de testes): primeiro a barreira; só então expandir testes faz sentido como proteção.

**Riscos e mitigações**:
| Risco | Impacto | Mitigação |
|------|------|------|
| Conectar o TenantScope afeta todas as consultas /admin, podendo introduzir regressão de visibilidade de dados | Alto | Testes de integração primeiro; obter o tenant do claim JWT (sem alteração de front-end); ou rebaixar dentro do P0 para "documentar como reservado" com decisão explícita |
| Conflitos de merge na sincronização das três branches podem introduzir regressões | Médio-alto | Primeiro main todo verde; após o merge, as três branches só entregam com CI todo verde; resolução de conflitos registrada |
| Processo consumidor da fila indisponível em alguns ambientes (rabbitmq) | Médio | Driver redis padrão (CI já tem redis7); rabbitmq apenas com passos de troca documentados |
| Alteração de autenticação WebSocket quebra clientes existentes | Médio | Front e back modificados em conjunto no mesmo marco; token inválido rejeitado sem afetar sessões legítimas |
| Matriz de cobertura/lista de campos de formulário são conclusões de pesquisa, parte "a validar" | Médio | A7-1 faz primeiro o script de comparação automática; o resultado do script é a base, sem completar páginas por impressão |
| Escopo da refatoração da camada de serviço sai do controle | Médio | Explicitamente extrair apenas 3~5 módulos, sem refatoração total; sem versionamento total do /api (F1 não é feito neste período) |
| Limiar de cobertura indisponível no ambiente do CI (xdebug não instalado) | Baixo | Primeiro gerar relatório local + limiar documentado; a capacidade de coleta do CI entra após "a validar" |
| CI HarmonyOS (hvigor) precisa do SDK HarmonyOS; o ambiente de CI público pode não ter | Médio | Marcar "aguardando CI pronto"; build local é a base de validação, sem bloquear outras tarefas |

---

## 6. Explicitamente não feito

Dando continuidade às exclusões do roadmap §12, salvo forte justificativa (precisa de revisão e aprovação própria):
- ❌ Desmembramento em microserviços / implantação K8s (experimento mantido em `.claude/worktrees/microservices-split/`, sem integrar à linha principal)
- ❌ Capacidades AI/ML (previsão, recomendação inteligente, NLP)
- ❌ App nativo (iOS/Android nativos) — Flutter já cobre todas as plataformas
- ❌ Interfaces GraphQL
- ❌ Integração de hardware (IoT/leitor de código de barras/conexão direta a impressoras)
- ❌ Solução comercial completa de multitenancy (cobrança SaaS, autoatendimento de tenants) — neste período apenas conexão mínima ou reserva documentada
- ❌ Versionamento total do /api (F1) — o lado de negócio permanece em /admin, registrado apenas como dívida de arquitetura
- ❌ Refatoração total da camada de serviço e reformulação total de formulários — extração por ordem de valor, sem refatoração "big bang"
- ❌ Complemento total de páginas HarmonyOS — apenas criar/editar/excluir das páginas centrais de alto valor
- ❌ Reformulação total de textos i18n do Flutter — neste período apenas o mínimo viável (mensagens de erro + ≥10 textos-chave); o multilíngue de todas as páginas fica para versões futuras

---

## 7. Marcos sugeridos

| Marco | Tempo | Conteúdo | Critério de saída |
|--------|------|------|----------|
| **M1 Baseline de fechamento de ciclo** | fim da semana 2 | Grupo A todo: endpoints mortos zerados, dashboard com dados reais, TenantScope/fila/WebSocket implementados, paginação HarmonyOS corrigida | Resumo de aceitação do P0 todo aprovado |
| **M2 Baseline de qualidade** | fim da semana 5 | Grupo B todo + itens de segurança do grupo C: testes dos 11 módulos, integração/E2E/cobertura, chaves fracas zeradas, auditoria fail-open, governança de migrações, canais de notificação | Resumo de aceitação do P1 todo aprovado |
| **M3 Qualidade do front-end** | fim da semana 6 | Grupo E: job Flutter no CI + expansão de testes, persistência de token HarmonyOS e criar/editar/excluir das páginas centrais | flutter CI verde, persistência em efeito, ≥10 páginas com criar/editar/excluir |
| **M4 Versões e governança documental** | fim da semana 7 | Grupo D: sincronização das três branches, reescrita de EDITIONS/FUNCTIONS, automação doc-stats + verificação no CI | branches sincronizadas, deriva documental = vermelho |
| **M5 Cobertura profunda** | fim da semana 8 | A7-2 profundidade do front-end + extração leve de camada de serviço do grupo F | cobertura de endpoints de alta prioridade zerada, módulos extraídos sem consulta direta a modelos |
| **M6 Lançamento 1.1** | fim da semana 9 | Regressão completa, notas de lançamento (CHANGELOG), verificação final da documentação, arquivamento | Todos os critérios de saída dos marcos aprovados (indicadores rígidos): total de testes ≥287 e phpunit todo verde, relatório de cobertura acima do limiar, todos os jobs do ci.yml verdes (matriz PHP+integração+cobertura+flutter+verificação documental), três branches sincronizadas em 0 0, lista de endpoints mortos zerada, mecanismo doc-stats deriva-vermelho em efeito; CHANGELOG e verificação final da documentação aprovados; reavaliação da revisão apenas como referência, sem limiar de pontuação |

---

## Anexo: arquivos-chave já verificados por amostragem neste planejamento

- `config/middleware.php`, `config/route.php` (:231-233 endpoints do dashboard, :248-251 rotas de notificação, :387-415 grupos de middlewares)
- `config/process.php`, `config/queue.php`
- `app/middleware/TenantScope.php`, `app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php` (:23, :47-50)
- `app/service/notification/ChannelRouter.php` (:23 stub)
- `app/controller/sales/DeliveryController.php` (:142-143), `app/controller/purchase/ReceiveController.php` (:142-143; nos dois arquivos a instanciação `new` está aqui; `use` declarado em :15-16)
- `app/api/v1/controller/` (apenas 3 controladores)
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (estatísticas mock + Dio independente)
- `apps/flutter/lib/app/pages/notification/notification_page.dart` (:43 endpoint morto)
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets` (:24 bug de interpolação)
- `tests/` (lista dos 19 arquivos de teste), medição real de `vendor/bin/phpunit` 137/805
- `phpstan-baseline.neon` (974 message)
- `.github/workflows/ci.yml` (apenas job PHP), `README.md` (:635 declaração falsa)
- `.env.docker` (chaves fracas), `database/migrations/` (29, sem _rollback)
- `docs/EDITIONS.md` (contraditório), `docs/FUNCTIONS.md` (apêndice com deriva), `docs/CLAUDE.md` (critério 104 vs 122 controladores medidos)
- Branches git `lite/standard/full` (behind 41/41/20)

> Observação de critério: controladores medidos via `find app -path '*/controller/*.php'` = 122 (inclui admin 14 + api 3 + controladores de negócio + Index/Install); critério da pesquisa 121, critério de negócio do docs/CLAUDE.md 104; a diferença entre os três vem dos escopos de contagem distintos, já listada como item de governança D6 para unificar o critério.
