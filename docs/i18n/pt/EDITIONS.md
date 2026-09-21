# Comparação de versões

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> As estatísticas são coletadas em tempo real por `bash scripts/doc-stats.sh` e marcadas nos documentos com `<!-- stats:key=value -->`;
> o CI (job docs em `.github/workflows/ci.yml`) valida automaticamente a consistência entre documentos e código — qualquer divergência fica vermelha.

O Sistema ERP Aberto oferece três versões para atender às necessidades de empresas de diferentes portes.

---

## Visão geral das versões

| Dimensão | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Branch | `lite` | `standard` | `full` |
| Tabelas de dados | 62 (valor planejado) | 72 (valor planejado) | 227 <!-- stats:tables=227 --> |
| Controladores | 48 (valor planejado) | 42 (valor planejado) | 159 <!-- stats:controllers=159 --> |
| Módulos de negócio | 6 (valor planejado) | 6 (valor planejado) | 23 <!-- stats:modules=23 --> |

> **Critério das estatísticas**: o repositório implementa atualmente apenas a versão Full (um único código); as colunas Lite/Standard são valores planejados do produto
> (sem branches correspondentes, ver «Estratégia de branches» abaixo) e não participam da validação do doc-stats.
> Os números da coluna Full são medidos por `scripts/doc-stats.sh` (227 tabelas / 159 controladores / 23 módulos de negócio),
> consistentes com o apêndice de `FUNCTIONS.md`.
> **Fato sobre os branches** (medido em 2026-09-22 com `git branch -a` + `git ls-remote --heads origin`):
> tanto no repositório local quanto no remoto resta apenas o branch `main`; os três branches `lite` / `standard` / `full` foram **excluídos**
> (em 2026-08-31 ainda se media a coexistência dos três, todos parados no commit `eea90c0` de 2026-08-17, sem diferenças entre si e 38 commits atrás de `main`).
> O commit de arquivo continua no histórico de `main` (`git merge-base --is-ancestor eea90c0 main` é verdadeiro),
> ou seja, hoje as diferenças de versão só podem ser rastreadas por commits e tags — não há mais branch de versão para fazer checkout no repositório.

---

## Mudanças da v1.17.0 (2026-09-15)

> O posicionamento das versões não muda: o repositório continua implementando apenas a versão Full (um único código); as colunas Lite/Standard são valores planejados do produto e seus branches foram arquivados e congelados.

- **O painel administrativo passa de duas para três implementações**: entram o Angular 22 (`apps/angular/`) e o React 19 + Vite (`apps/react/`),
  lado a lado com o já existente Flutter 3.x Web (`apps/flutter/`); as três pontas compartilham as mesmas interfaces `/admin/v1`, `/api/v1` e `/open/v1`.
- **13 idiomas em toda a plataforma** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id):
  - Mensagens de resposta do backend em `resource/translations/<locale>/` — `zh_CN` com 565 entradas, cada um dos outros 11 idiomas com 544, `en` com 30
    (critério: entradas folha dos três arquivos; os rótulos do campo `attributes` de `validation.php` entram na conta, as chaves de grupo não — `zh_CN` tem 21 entradas a mais por traduzir 21 rótulos de campo. Na linha `en` "inglês é a chave", o dicionário fica praticamente vazio)
  - Interface do painel administrativo: dicionário fonte do Angular com 1456 chaves e do React com 1451 chaves × 11 novos idiomas; **carregamento sob demanda por idioma**, cada idioma vira um chunk
  - Geradores: `scripts/gen-be-locales.mjs` (backend) e `scripts/gen-fe-locales.mjs` (frontend, `--app angular|react`)
  - Ponto de troca: **ícone globe próprio** na barra superior + menu suspenso no centro pessoal (igual nas duas pontas)
- **Flutter e HarmonyOS continuam com dois idiomas (chinês/inglês)**, fora deste ciclo.
- **Impacto na tabela abaixo**: coluna Full de 163 tabelas / 122 controladores / 19 módulos de negócio → **227 / 159 / 23**;
  a matriz de completude ganha a linha «multilíngue (i18n)» (linhas de módulo 44 → 45, API de backend 39 → 40, lógica de negócio 33 → 34);
  nota: após a fusão da linha duplicada «multi-tenant» na matriz de 2026-09-15, as linhas de módulo voltam a 44 (API de backend 39, lógica de negócio 33) —
  a frase acima é o critério incremental vigente no v1.17.0 e permanece como está.

## Mudanças da v1.4.0 (2026-09-05)

> O posicionamento das versões não muda: o repositório continua implementando apenas a versão Full (um único código); as colunas Lite/Standard são valores planejados do produto e seus branches foram arquivados e congelados.

- **Versionamento de caminho em todo o site**: `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*`;
  a única exceção é `GET /api/docs` (documentação OpenAPI) e o webhook do TMS; a autenticação dos pontos de permissão RBAC usa o `method.path` sem o segmento de versão,
  sem qualquer migração dos dados de papéis existentes (commit `3ee1430`; o controle pelo cabeçalho `API-Version` foi removido antes, commit `8276a1b`).
- **P0 multi-organização e contabilidade de custos**: contabilidade independente por organização (Company/LedgerPeriod), motor de relatórios consolidados (conversão pela taxa de fechamento + eliminação entre subsidiárias,
  com snapshot preferencial em FinanceConsolidationReport), custeio de estoque/produção (requisição de materiais + apropriação de custos).
- **P1 execução de manufatura e colaboração**: apontamento de operações/salário por peça/expedição e recebimento de terceirização/carga de capacidade/rastreabilidade de lotes e séries (M1/M2/M6/M3), controle de crédito (F7),
  canvas do fluxo de aprovação (B3), modelos de impressão (B1), salários de RH (H1/H2), inspeção por leitura de QR em equipamentos (E1), custo de projeto (P1).
- **P2 diferenciação e ecossistema**: programa de fidelidade (C1), registro de títulos e conciliação bancária (F6), pool de notas de entrada e faturamento eletrônico (F5, a integração real com a autoridade fiscal é um ponto de adaptação),
  canal multicanal e novas tentativas em caso de falha (B4), campos personalizados (B7), cobrança por vencimento de multi-tenant (B5 — o middleware de isolamento de tenant ainda não está registrado, ativação parcial),
  treinamento e previdência social (H3/H4).
- **Matriz de funcionalidades**: das 44 linhas de módulo, 33 têm ✅ duplo; 21 linhas estão marcadas como v1.4.0 (incluindo 1 linha de ativação parcial), ver `FUNCTIONS.md` §19.

> O detalhamento das mudanças está no `CHANGELOG.md` na raiz do repositório.

---

## Comparação de funcionalidades

### Administração do sistema

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gerenciamento de usuários (CRUD + lote + importação) | ✔ | ✔ | ✔ |
| Papéis e permissões (árvore de permissões RBAC de três níveis) | ✔ | ✔ | ✔ |
| Configuração do sistema (chave-valor) | ✔ | ✔ | ✔ |
| Auditoria de operações (detecção de origem em 8 plataformas) | ✔ | ✔ | ✔ |
| Upload de arquivos / Exportação Excel / Exportação PDF | ✔ | ✔ | ✔ |
| Health check / Métricas Prometheus | ✔ | ✔ | ✔ |
| Autenticação JWT + captcha de clique | ✔ | ✔ | ✔ |
| 7 camadas de proteção de segurança | ✔ | ✔ | ✔ |
| Internacionalização (i18n) bilíngue chinês/inglês | — | — | ✔ |

### Produtos e dados básicos

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cadastro de produto + SKU com múltiplas especificações | ✔ | ✔ | ✔ |
| Conversão de múltiplas unidades + estratégia de preço | ✔ | ✔ | ✔ |
| Categoria de produto (árvore) + marca | ✔ | ✔ | ✔ |
| Múltiplos armazéns + múltiplas localizações | ✔ | ✔ | ✔ |
| Cadastro de fornecedores / clientes | ✔ | ✔ | ✔ |

### Gestão de compras

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Solicitação de compra + aprovação | ✔ | ✔ | ✔ |
| Pedido de compra | ✔ | ✔ | ✔ |
| Recebimento de compra (entrada automática no estoque + geração de contas a pagar) | ✔ | ✔ | ✔ |
| Devolução de compra | ✔ | ✔ | ✔ |
| Liquidação com fornecedores | ✔ | ✔ | ✔ |

### Gestão de vendas

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Cotação (com conversão em pedido) | ✔ | ✔ | ✔ |
| Pedido de venda | ✔ | ✔ | ✔ |
| Expedição de venda (saída automática do estoque + geração de contas a receber) | ✔ | ✔ | ✔ |
| Devolução de venda | ✔ | ✔ | ✔ |
| Liquidação com clientes + análise de margem | ✔ | ✔ | ✔ |

### Gestão de estoque

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Estoque em tempo real (precisão de quatro dimensões) | ✔ | ✔ | ✔ |
| Fluxo de entrada/saída de estoque | ✔ | ✔ | ✔ |
| Rastreamento de lotes + rastreamento de números de série | ✔ | ✔ | ✔ |
| Transferência de estoque | ✔ | ✔ | ✔ |
| Gestão de inventário (planejado + dinâmico) | ✔ | ✔ | ✔ |
| Alertas de estoque (avisos de limite superior/inferior) | ✔ | ✔ | ✔ |
| Custeio por média móvel ponderada | ✔ | ✔ | ✔ |

### Gestão financeira

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Contas a receber/a pagar (geração automática + baixa) | ✔ | ✔ | ✔ |
| Recibo de recebimento / recibo de pagamento | ✔ | ✔ | ✔ |
| Diário de caixa e bancos | ✔ | ✔ | ✔ |
| Reembolso de despesas (envio → aprovação → pagamento) | ✔ | ✔ | ✔ |
| Demonstração de resultados | ✔ | ✔ | ✔ |
| Depreciação de ativo imobilizado | — | — | ✔ |
| Gestão tributária (configuração de múltiplos impostos) | — | — | ✔ |
| Multimoeda + gestão de câmbio | — | — | ✔ |
| Gestão orçamentária (comparação orçamento vs. realizado) | — | — | ✔ |
| Centro de custo / centro de lucro (apuração em árvore) | — | — | ✔ |

### CRM

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestão de contatos de clientes | ✔ | ✔ | ✔ |
| Registros de acompanhamento | ✔ | ✔ | ✔ |
| Gestão de campanhas de marketing | — | — | ✔ |
| Tickets de serviço (prioridade + atribuição + fluxo de resolução) | — | — | ✔ |
| Relatórios analíticos de clientes | — | — | ✔ |

### Capacidades da plataforma

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Mecanismo de fluxo de aprovação | — | — | ✔ |
| Sistema de notificações | — | — | ✔ |
| Documentação da API (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Módulos de extensão

| Funcionalidade | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestão de projetos (WBS/Gantt/horas) | — | — | ✔ |
| Recursos humanos (organização/ponto/salários) | — | — | ✔ |
| Manufatura (BOM/MRP/ordens/processos) | — | — | ✔ |
| Construtor de relatórios personalizados | — | — | ✔ |

---

## Cenários de uso

| Versão | Cenário recomendado |
|------|---------|
| **Lite** | Empresas comerciais de pequeno e médio porte, com foco em compras/vendas/estoque + finanças básicas, sem necessidade de fluxos de aprovação e módulos de extensão |
| **Standard** | Mesmo porte funcional, com design de tabelas mais enxuto, adequado como base para desenvolvimento personalizado |
| **Full** | Empresas de médio e grande porte, que precisam da plataforma full-stack completa: compras/vendas/estoque + finanças + CRM + RH + manufatura + gestão de projetos |

---

## Caminho de upgrade

| Versão | Porte (tabelas / módulos de negócio) | Descrição |
|------|--------------------------|------|
| Lite | 62 tabelas / 6 módulos de negócio (valores planejados) | Sem aprovação/notificações/RH/manufatura/relatórios |
| Standard | 72 tabelas / 6 módulos de negócio (valores planejados) | Modelo de dados mais enxuto |
| Full | 227 tabelas <!-- stats:tables=227 --> / 23 módulos de negócio <!-- stats:modules=23 --> | Capacidade completa de plataforma empresarial |

---

## Estratégia de branches (a partir de 2026-08-27)

> Aplica-se aos três branches de versão `lite` / `standard` / `full` e é consistente com o job `release` do CI (tag de versão idempotente).
> **Complemento de estado atual (medido em 2026-09-22)**: os três branches foram excluídos; os itens restantes desta seção devem ser lidos como «arquivo = commits e tags»,
> pois não há mais branch de versão disponível para checkout.

- **`main` é a única fonte de desenvolvimento**: todo desenvolvimento de funcionalidades, correção de defeitos e atualização de dependências é mesclado em `main`, e os commits são executados uniformemente pelo Lead.
- **Branches de versão apenas arquivados, não mantidos**: `lite` / `standard` / `full` foram congelados como branches de arquivo histórico e não recebem mais commits novos,
  nem sincronizam incrementos de `main`, nem sofrem atualização ou push forçado (para evitar manter três linhas de código); **encerrado o período de congelamento, os três branches foram excluídos**,
  e o conteúdo arquivado permanece no commit `eea90c0` do histórico de `main`.
- **As diferenças de versão são registradas por tags de versão**: a release é criada de forma idempotente pelo job `release` do CI a partir da tag mais recente, no formato `vX.Y.Z`
  (ver `scripts/bump-version.sh`); as diferenças funcionais entre as versões seguem as tags e a tabela de comparação de funcionalidades acima, e não linhas de código de branches mantidos.
- **Validação**: o CI de `main` é a validação da release; os branches arquivados não rodam mais CI separadamente. (A partir de 2026-09-15 o job `release` depende de `docs` + `e2e`; o job `php` continua rodando mas não bloqueia a release — seus pontos vermelhos são dívida histórica de testes de integração exclusiva do CI, ver os comentários em `.github/workflows/ci.yml`.)
