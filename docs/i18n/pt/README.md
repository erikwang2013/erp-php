# Sistema ERP Aberto (open-erp)

Sistema ERP full-stack baseado em webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Mascote polvo do open-erp" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | Português | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Comparação de versões](EDITIONS.md) | [Diagrama de arquitetura](ARCHITECTURE.md) | [Diagrama de arquitetura do sistema](#diagrama-de-arquitetura-do-sistema) | [Documento de design](DESIGN.md) | [Arquitetura de segurança](SECURITY.md) | [Referência da API](API.md) | [Manual de funções](FUNCTIONS.md)

## Visão geral do projeto

O open-erp é um **sistema ERP full-stack de código aberto** voltado para pequenas e médias empresas, cobrindo domínios de negócio completos como compras/vendas/estoque, contabilidade financeira, manufatura (BOM/MRP/apontamento de operações/carga de capacidade), CRM, fluxo de aprovação, recursos humanos, notificações de mensagens e relatórios personalizados. O backend é construído sobre webman v2 + MySQL 8.0 (prefixo de tabela `erp_`, chave primária globalmente única via Snowflake), e o painel administrativo oferece três implementações: Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`) e Flutter 3.x Web (`apps/flutter/`), acompanhadas do cliente nativo HarmonyOS para dispositivos móveis (`apps/harmonyos/`).

O sistema adota como núcleo de design o **modelo orientado a documentos com vinculação automática**: a aprovação de um documento de negócio dispara automaticamente a movimentação de estoque, a geração de contas a receber/a pagar e a apropriação de custos; o fluxo de aprovação e as notificações de mensagens atravessam todos os documentos críticos; o MRP calcula as necessidades de materiais com base nos pedidos de venda e na BOM e gera sugestões de compra/produção, formando um ciclo de negócio ponta a ponta — do pedido de venda ao recebimento de compras e do planejamento de produção ao fechamento financeiro.

## Notas do projeto

- **Aritmética decimal exata**: valores de negócio como montantes, quantidades e ponderações usam aritmética decimal bcmath; o custo por média móvel ponderada, a baixa de contas a receber/a pagar e a saída dos diversos relatórios têm precisão de string, sem erro de ponto flutuante
- **Linha de base de segurança corporativa**: token JWT + autorização RBAC em nível de método, defesa em profundidade (panorama em camadas L0–L12 + 35 classes de detecção de ataque + cadeia de 7 middlewares, XSS/injeção SQL/CSRF/rate limit/CSP etc.), criptografia de armazenamento de campos sensíveis e criptografia de transmissão de interface, trilha completa de auditoria das operações
- **Capacidade configurável**: fluxo de aprovação multinó (com canvas do designer visual de fluxos), mecanismo de modelos de impressão de documentos (renderização por placeholders + PDF dompdf + etiquetas QR), bloqueio em tempo real do limite de crédito do cliente, rastreabilidade direta e reversa de lotes/números de série
- **Dados rastreáveis**: cada lançamento do fluxo de negócio fica registrado; lotes de estoque e números de série percorrem todo o ciclo de vida entrada→requisição→saída→rastreamento, com custeio no nível da linha do documento
- **Implantação amigável**: inicialização com um clique via Docker Compose v2 (MySQL/Redis/Elasticsearch); `composer install` local também executa diretamente
- **Internacionalização**: 13 idiomas (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), com cobertura completa das mensagens do backend e das interfaces dos dois painéis administrativos Angular/React; os dicionários do frontend são carregados sob demanda por idioma, e o README oferece ainda documentação em 12 idiomas

## Lista de funcionalidades

| Domínio de negócio | Funcionalidade | Descrição |
|--------|------|------|
| 🔐 Autenticação | Login/Registro/Refresh token/Logout | Captcha de clique + JWT + lista negra |
| | Bloqueio de conta | 5 falhas bloqueiam por 15 minutos |
| | Limite de sessões simultâneas | Máximo de 3 Tokens válidos por usuário |
| 📊 Dashboard | Visão geral de negócio + seis painéis: vendas/estoque/finanças/OMS/WMS/TMS | Tendência de vendas 30 dias/Top5 produtos mais vendidos/Distribuição de status de pedidos/Idade de contas a receber e pagar + Cache Redis por 5 minutos |
| 👥 Gerenciamento de usuários | CRUD + exclusão em lote/ativar-desativar | Soft delete + confirmação secundária de senha |
| | Importação em lote via Excel | Validação linha a linha + relatório de erros |
| 🔒 Papéis e permissões | CRUD de papéis + árvore de permissões | Autenticação RBAC na granularidade method.path |
| ⚙ Configuração do sistema | CRUD de pares chave-valor | Gerenciamento por grupos |
| 📋 Auditoria de operações | Consulta de logs + detecção de origem | Reconhecimento automático de 8 plataformas |
| 📁 Gerenciamento de arquivos | Upload/Exportação Excel/Exportação PDF | Mascaramento automático de dados sensíveis |
| 🛡 Proteção de segurança | 35 classes de detecção de ataque + cadeia de 7 middlewares | XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF/Rate limit/CSP... |
| 🏥 Operações | Health check/metrics/Documentação da API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Gestão de produtos | Cadastro de produto/SKU/Múltiplas especificações/Múltiplas unidades/Categoria/Marca/Estratégia de preço | Árvore de categorias multinível + conversão de múltiplas unidades |
| | Armazéns e localizações | Gerenciamento de múltiplos armazéns e localizações |
| | Cadastro de fornecedores/clientes | Contatos/Contas bancárias/Limite de crédito |
| 📥 Gestão de compras | Solicitação→Pedido→Recebimento→Devolução→Liquidação | Fluxo completo de compras + aprovação |
| | Sourcing de compras (consulta de preços→cotação→conversão do vencedor em pedido) | Comparação de preços entre múltiplos fornecedores, a cotação deve cobrir todos os itens da consulta, conversão do vencedor em pedido de compra com um clique |
| | Avaliação de fornecedores | Pontuação total 0–100 com classificação automática (A ≥ 90 / B ≥ 70 / C) + dimensões de avaliação em JSON + registro de quem avaliou |
| 📤 Gestão de vendas | Cotação→Pedido→Expedição→Devolução→Liquidação | Cotação vira pedido + margem bruta de vendas |
| | Controle de crédito do cliente | Gestão de limite/prazo de pagamento/congelamento + bloqueio de pedidos e remessas fora do limite ou atrasadas |
| 🏗 Gestão de estoque | Estoque em tempo real/Lote/Número de série/Transferência/Inventário/Alertas | Custeio por média móvel ponderada |
| 💰 Gestão financeira | Contas a receber/a pagar/Recebimentos e pagamentos/Diário/Reembolsos/Demonstração de resultados/Ativo imobilizado/Impostos/Multimoeda/Orçamento/Centro de custo e lucro | Geração automática de contas a receber/a pagar + baixa + gestão financeira completa |
| | Multi-organização + relatórios consolidados | Contabilidade de múltiplas empresas + lançamentos de eliminação (método de equivalência patrimonial/custo) |
| | Custeio de estoque/produção | Saída de materiais → apropriação de mão de obra/custos indiretos → custo dos produtos acabados → transferência de variações de custo |
| | Aceites + conciliação bancária | Registro de títulos + importação de extratos bancários com baixa automática |
| | Pool de notas de entrada + faturamento eletrônico | Gestão de notas de entrada + canal de emissão (adaptador + canal Mock) |
| 🤝 CRM | Clientes/Contatos/Registros de acompanhamento/Campanhas de marketing/Tickets de serviço/Relatórios analíticos/Funil de vendas/Pool público/Cotações/Contratos | Gestão do ciclo de vida completo do cliente |
| | Motor de valor do cliente | Operação de fidelidade com saldo/pontos/cupons |
| ✅ Fluxo de aprovação | Definição de workflow/Enviar para aprovação/Aprovar/Rejeitar/Retirar/Minhas aprovações | Mecanismo de fluxo de aprovação multinó |
| | Designer visual de fluxos | Configuração em canvas de nós/ramificações/arestas de retorno, reutiliza o mecanismo de aprovação |
| 🔔 Notificações | Lista de notificações/Marcar lida/Contagem de não lidas/Marcar todas lidas | Push de mensagens em tempo real e rastreamento de status |
| | Notificações multicanal | Canais SMS/e-mail (canal Mock + logs + novas tentativas) |
| 📐 Gestão de projetos | Projetos/Tarefas/Registros de horas | Acompanhamento do progresso do projeto e gestão de recursos |
| | Custo e orçamento do projeto | Horas × tarifa → apropriação de custos do projeto + desvio orçamentário |
| 👤 Recursos humanos | Departamentos/Funcionários/Cargos/Ponto/Férias/Salários | Gestão completa de RH |
| | Recrutamento/Desempenho/Treinamento/Previdência | Funil de recrutamento + avaliação KPI/360 + créditos de curso + regras de base de contribuição e holerite |
| 🏭 Manufatura | BOM/Ordens de produção/Roteiros de processo/Postos de trabalho/MRP | Planejamento de necessidades de materiais e execução de produção |
| | Apontamento de operação/Salário por peça/Baixa de terceirização | Camada de execução de operações MES + saída e baixa de materiais em ordens de terceirização |
| | Análise de carga de capacidade | Calendário de postos de trabalho + relatório de capacidade bruta |
| | Rastreabilidade de lotes/números de série | Cadeia de rastreabilidade direta e reversa + alerta de validade próxima |
| 📈 Relatórios personalizados | Modelos de relatório/Conjuntos de dados/Campos/Filtros/Execução/Agendamento | Construtor visual de relatórios |
| 📋 Gestão de pedidos (OMS) | Pedidos multicanal/Orquestração de atendimento/Reserva de estoque/Alocação/Cancelamento/RMA devoluções e trocas | Gestão do ciclo de vida completo do pedido |
| 🏗 Gestão de armazém (WMS) | Zonas e localizações/ASN/Recebimento/Putaway/Ondas/Picking/Embalagem/Expedição | Fluxo completo de operações de armazém |
| 🚚 Gestão de transporte (TMS) | Transportadoras/Serviços/Tarifas/Conhecimentos de transporte/Rastreamento/Faturas de frete | Comparação de frete entre transportadoras + rastreamento |
| 🛠 Gestão de equipamentos (EAM) | Cadastro de equipamentos/Planos de manutenção/Ordens de reparo/Sobressalentes | Gestão do ciclo de vida completo do equipamento |
| | Loop fechado de inspeção por leitura | Inspeção por leitura de QR, anomalias acionam automaticamente ordem de reparo |
| 🌐 Plataforma e abertura | Versionamento de rotas de API | Admin /admin/v1, cliente /api/v1, aberto /open/v1 (sem cabeçalho de versão) |
| | Mecanismo de modelos de impressão | Renderização por placeholders + PDF dompdf + etiquetas QR |
| | Campos personalizados de formulários | Extensão JSON custom_fields em tabelas mestre + validação |
| | Arquitetura multi-tenant | Tenant erp_tenant + contexto de requisição TenantScope + cobrança por vencimento (seam de middleware reservado, não registrado) |
| | Multilíngue | 13 idiomas (mensagens do backend + interfaces dos dois painéis administrativos Angular/React), dicionários carregados sob demanda por idioma como chunks independentes, com a entrada de troca fixa no globe da barra superior e no menu suspenso do centro pessoal |

## Módulos do ERP

Fluxo de dados entre os módulos de negócio:

- Recebimento de compras → entrada automática no estoque (custeio por média móvel ponderada) → geração automática de contas a pagar
- Expedição de vendas → saída automática do estoque → geração automática de contas a receber
- Recebimentos e pagamentos → baixa de contas a receber/a pagar → atualização do diário
- Aprovação de lançamentos → atualização automática do razão geral (resumo por conta) + razão auxiliar (registro item a item)
- Balanço patrimonial → gerado automaticamente pelo resumo dos saldos finais do razão geral
- Demonstração de fluxo de caixa → gerada automaticamente pelo resumo dos diários de caixa e bancos (classificação em operação/investimento/financiamento)
- Fluxo de aprovação → envio de documentos de negócio para aprovação → fluxo multinó → resultado da aprovação retorna ao módulo de negócio
- Notificações → acionadas por aprovações/alertas/eventos do sistema → push em tempo real → usuário marca como lida
- MRP → com base em pedidos de venda + BOM → cálculo das necessidades de materiais → geração de sugestões de compra/produção
- OMS → importação de pedidos multicanal → reserva de estoque (ATP) → criação de atendimento → envio ao WMS para picking/embalagem
- WMS → agregação de ondas → tarefas de picking → confirmação do picking → embalagem concluída → acionamento da criação do conhecimento de transporte (TMS)
- TMS → comparação de frete → criação do conhecimento de transporte → confirmação de expedição (stockOut+AR) → rastreamento → comprovação de entrega
- Entrada no WMS → ASN pré-aviso de chegada → recebimento → inspeção de qualidade → confirmação de putaway (stockIn+AP) → atualização do estoque
- RMA → solicitação de devolução → aprovação → devolução com entrada no estoque → reembolso

## Stack tecnológico

| Camada | Tecnologia | Descrição |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP de processo residente de altíssima performance |
| Versão PHP | 8.3+ | |
| Banco de dados | MySQL 8.0+ | Prefixo de tabela `erp_`, chave primária BIGINT não incremental |
| Mecanismo de busca | Elasticsearch | Sincronização automática do índice na escrita/exclusão via `webman-scout` (componente opcional, ver a seção «Motor de busca full-text») |
| Frontend administrativo A | Angular 22 | Páginas de recursos orientadas a config, motor de renderização `ResourcePage` (`apps/angular/`) |
| Frontend administrativo B | React 19 + Vite | Orientado a config, mesma origem do Angular + tokens de estilo (`apps/react/`) |
| Frontend administrativo C | Flutter 3.x | Web com estilo de painel administrativo para PC (`apps/flutter/`) |
| Mobile | HarmonyOS ArkTS | Cliente nativo HarmonyOS (`apps/harmonyos/`), compatível com celular/tablet/2em1 |

## Dependências principais

| Pacote | Uso |
|---|------|
| `erikwang2013/snowflake-php` | Geração de chaves primárias BIGINT globalmente únicas via algoritmo Snowflake |
| `erikwang2013/hashids` | Criptografia/descriptografia de IDs na camada de API, ocultando IDs reais do banco de dados |
| `erikwang2013/jwt-webman` | Emissão e validação de tokens de autenticação JWT |
| `erikwang2013/encryption` | Criptografia/descriptografia de dados sensíveis na camada de transmissão da interface |
| `erikwang2013/encryptable` | Criptografia/descriptografia automática de campos sensíveis na camada de armazenamento |
| `erikwang2013/webman-scout` | Sincronização de dados com Elasticsearch e busca de texto completo |
| `erikwang2013/season` | Dados de bandeiras nacionais |
| `erikwang2013/poster-php` | Geração e validação de captcha de clique + geração de pôsteres |
| `erikwang2013/security-php` | Verificações de ferramentas de segurança |
| `phpoffice/phpspreadsheet` | Exportação de Excel |
| `barryvdh/laravel-dompdf` | Exportação de PDF (baseado em Dompdf) |
| `erikwang2013/apidoc-php` | Geração automática de documentação da API | Documentação de interface por anotações, agrupada para admin/cliente |

## Internacionalização

O sistema suporta **13 idiomas**: `zh` (padrão), `en`, `ja`, `ko`, `de`, `fr`, `es`, `pt`, `ru`, `ar`, `hi`, `bn`, `id`.

| Camada | Localização do dicionário | Escala |
|---|---------|------|
| Mensagens do backend | `resource/translations/{idioma}/` | 13 diretórios de idioma: `zh_CN` 565 entradas, os outros 11 idiomas 544 entradas cada, `en` 30 entradas (critério: entradas folha; os rótulos de campos em `attributes` de `validation.php` são contados, as chaves de agrupamento não) |
| Frontend administrativo Angular | `apps/angular/src/app/core/zh-*.ts` (dicionário fonte `zh-en/`, 4 fatias mescladas) | Dicionário fonte de 1456 chaves × 11 novos idiomas |
| Frontend administrativo React | `apps/react/src/lib/i18n/zh*.ts` | Dicionário fonte de 1451 chaves × 11 novos idiomas |

- **Backend «inglês é a key»**: as próprias chaves das mensagens do backend são o texto em inglês; o `en` mantém apenas alguns mapeamentos, como nomes de regras do framework, sem precisar de um dicionário completo
- **Carregamento sob demanda por idioma**: cada um dos 12 dicionários do frontend é empacotado em um chunk independente e buscado conforme a necessidade na troca de idioma, sem pesar no carregamento inicial
- **Entrada de troca**: ícone globe próprio na barra superior + menu suspenso do centro pessoal (igual nos dois painéis Angular/React)
- **Camada de interface**: detecção automática pelo cabeçalho `Accept-Language` (zh-CN → chinês, en → English, os demais idiomas conforme a lista), padrão chinês
- **Geradores**: `scripts/gen-be-locales.mjs` (backend) e `scripts/gen-fe-locales.mjs --app angular|react` (frontend), com suporte a retomar de onde parou

## Estrutura do projeto

```
open-erp/
├── app/
│   ├── admin/controller/       # Controladores de administração do sistema (16)
│   ├── api/v1/controller/      # API do cliente (versão no caminho /api/v1, sem cabeçalho de versão)
│   ├── controller/             # Controladores dos módulos de negócio (139, 23 domínios)
│   │   ├── product/            # Produto/Categoria/Marca/Armazém/Localização/Fornecedor/Cliente (8)
│   │   ├── purchase/           # Solicitação de compra/Pedido/Recebimento/Devolução/Liquidação/Consulta de preços/Cotação/Avaliação de fornecedores (8)
│   │   ├── sales/              # Cotação de venda/Pedido/Expedição/Devolução/Liquidação (5)
│   │   ├── inventory/          # Estoque/Fluxo/Transferência/Inventário/Alertas (6)
│   │   ├── finance/            # A receber/a pagar/Lançamentos/Recebimentos e pagamentos/Diário/Razão geral/Razão auxiliar/Relatórios/Ativos/Impostos/Multimoeda/Orçamento/Centro de custo e lucro/Boletos/Conciliação/Faturas (28)
│   │   ├── crm/                # Oportunidades/Acompanhamento/Funil/Contatos/Pool público/Contratos/Cotações/Marketing/Tickets/Análises (10)
│   │   ├── workflow/           # Definição de workflow/Aprovação/Designer de processos (3)
│   │   ├── notification/       # Notificação interna/Envio por canal (2)
│   │   ├── project/            # Projetos/Tarefas/Horas/Custos (4)
│   │   ├── hr/                 # Departamentos/Funcionários/Cargos/Ponto/Férias/Salários/Recrutamento/Desempenho/Previdência/Treinamento (9)
│   │   ├── manufacturing/      # BOM/Ordens de produção/Processos/Postos de trabalho/MRP/Apontamento/Terceirização/Custos/Capacidade (13)
│   │   ├── report/             # Modelos de relatório/Conjuntos de dados/Execução/Agendamento (2)
│   │   ├── print/              # Motor de modelos de impressão (1)
│   │   ├── retail/             # Assinatura de membros/Pontos/Cupons (2)
│   │   ├── platform/           # Multitenancy/Campos personalizados (2)
│   │   ├── quality/            # Controle de qualidade (5)
│   │   ├── eam/                # Equipamentos/Manutenção/Reparos/Peças sobressalentes/Inspeção (5)
│   │   ├── bi/                 # Business intelligence (3)
│   │   ├── dms/                # Gestão de documentos (2)
│   │   ├── oms/                # Pedidos OMS/Atendimento/RMA/Canais (4)
│   │   ├── wms/                # Zonas/Localizações/ASN/Recebimento/Putaway/Ondas/Picking/Embalagem (8)
│   │   ├── tms/                # Transportadoras/Serviços/Tarifas/Conhecimentos/Rastreamento/Faturas de frete (6)
│   │   └── open/               # Interfaces da plataforma aberta (1)
│   ├── service/                # Camada de lógica de negócio (64)
│   │   ├── inventory/          # Entrada/saída de estoque + custeio por média móvel ponderada + reserva/ATP
│   │   ├── finance/            # Geração automática de contas a receber/a pagar + baixa
│   │   ├── notification/       # Serviço de envio de notificações
│   │   ├── oms/                # Orquestração de pedidos/Alocação de estoque/Ciclo de vida RMA
│   │   ├── wms/                # Fluxo de entrada (ASN→Recebimento→Putaway) / fluxo de saída (Ondas→Picking→Embalagem)
│   │   └── tms/                # Gestão de conhecimentos de transporte/Comparação de frete/Rastreamento
│   ├── model/                  # 224 modelos Eloquent (compartilhados entre módulos)
│   ├── middleware/             # 11 middlewares (ApiVersion removido, versão pela rota)
│   ├── common/                 # Serviços Hashids/Snowflake/Encryption
│   └── queue/                  # Tarefas de fila
├── apps/
│   ├── angular/                # Angular 22 admin (páginas de recurso dirigidas por config, ng serve :4200)
│   ├── react/                  # React 19 + Vite admin (Vite :5173)
│   ├── flutter/                # Flutter multiplataforma (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Cliente nativo HarmonyOS
├── config/                     # Arquivos de configuração (com comentários em chinês)
│   ├── plugin/erikwang2013/apidoc/        # Configuração da documentação da API
├── database/
│   ├── install.sql              # SQL de instalação completo (227 tabelas + dados de seed)
│   ├── e2e-seed.sql             # Seed mínimo para E2E/CI
│   └── backup/                 # Scripts de backup/restauração
├── docs/                       # Documentação de arquitetura, design, segurança e API
├── tests/                      # Testes PHPUnit (<!-- stats:test_files=113 --> arquivos de teste, <!-- stats:tests=1044 --> métodos de teste, <!-- stats:assertions=5020 --> asserções)
├── resource/
│   └── translations/           # Dicionários de mensagens do backend em 13 idiomas (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # Tradução em chinês (565 entradas)
│       ├── en/                 # Inglês é a chave, apenas 30 entradas (nomes de regras do framework etc.)
│       └── ja|ko|de|.../       # Os outros 11 idiomas com 544 entradas cada (gerador scripts/gen-be-locales.mjs)
├── public/                     # Entrada pública
├── runtime/                    # Arquivos de runtime
└── vendor/                     # Dependências Composer
```

## Diagrama de arquitetura do sistema

> Clique na imagem para ver o SVG original. Os diagramas usam nomes em inglês e mostram claramente o design de arquitetura de cada camada do sistema.

### Arquitetura de topologia do sistema

![System Architecture](./diagrams/system-architecture-cn.svg)

**Arquitetura em cinco camadas**: Camada de clientes → Camada de borda de gateway (reverse proxy Nginx) → Camada de aplicação (webman v2 + cadeia de middlewares + autenticação e autorização + lógica de negócio + serviços comuns) → Camada de armazenamento de dados (MySQL + Redis + Elasticsearch) → Camada de operações (CI/CD + Docker + Prometheus)

### Diagrama de fluxo de dados de negócio

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**Interligação de sete domínios de negócio**: Compras → Estoque → Vendas → Finanças formam o núcleo do ciclo fechado da cadeia de suprimentos; o gerenciamento de relacionamento com o cliente impulsiona as vendas; o MRP de manufatura baseia-se em pedidos de venda + lista de materiais para conduzir o plano de compras e o plano de produção; o fluxo de aprovação, as notificações, o gerenciamento de projetos e os recursos humanos percorrem todo o processo como módulos de suporte.

### Visão geral dos módulos funcionais

![Functional Modules](./diagrams/functional-modules-cn.svg)

**23 grandes domínios de negócio, 227 tabelas de dados, 159 controladores**: cobrem autenticação e segurança, dashboard, administração do sistema, proteção de segurança, monitoramento de operações, gestão de produtos, compras, vendas, estoque, finanças (14 submódulos), CRM (10 submódulos), fluxo de aprovação, notificações, gestão de projetos, recursos humanos, manufatura (MRP), relatórios personalizados, gestão de pedidos (OMS), gestão de armazém (WMS), gestão de transporte (TMS), gestão de qualidade (QMS), gestão de equipamentos (EAM), gestão de documentos (DMS) e painéis BI.

### Ciclo de vida da requisição

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Caminho completo da requisição, do cliente ao banco de dados**: Cliente (Angular/React/Flutter/HarmonyOS) → Terminação SSL no Nginx → Tratamento de CORS → Filtro de segurança → Rate limit → [Admin: autenticação JWT → permissão RBAC → log de operações] → Controlador → Camada de serviços → Camada de modelos → Cache/Banco de dados/Mecanismo de busca → Resposta JSON. O diagrama inclui dois caminhos: cache hit e cache miss. (A versão da API já está incorporada ao caminho da URL, sem etapa de validação separada; o idioma é resolvido por `app/common/I18n.php` a partir do `Accept-Language`.)

### Arquitetura de defesa em profundidade de segurança

![Security Architecture](./diagrams/security-architecture-cn.svg)

**Panorama da defesa em profundidade (L0–L12)**: L0 rede física → L1 segurança de transporte → L2 cabeçalhos HTTP seguros → L3 validação de requisição → L4 higienização de entrada → L5 proteção CSRF → L6 rate limit → L7 autenticação (JWT+Captcha+lista negra+controle de sessão) → L8 autorização RBAC → L9 proteção de dados (criptografia de transmissão + criptografia de armazenamento + ofuscação de ID + mascaramento de dados) → L10 auditoria e monitoramento → L11 divulgação de conformidade → L12 observabilidade (rastreamento distribuído X-Trace-Id + métricas de negócio + auditoria reforçada). A cadeia executável de 7 middlewares está em `docs/SECURITY.md`; as 35 classes de detecção de ataque estão em `config/plugin/erikwang2013/security-php/app.php`.

---

## Requisitos de ambiente

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (necessário apenas para desenvolvimento frontend)
- Node >= 22.22.3 (necessário apenas para o desenvolvimento dos frontends administrativos Angular/React; limite mínimo de `engines` do Angular CLI 22)
- Elasticsearch >= 7.x ou OpenSearch >= 2.x (opcional, necessário para a sincronização do índice; não instalar não afeta a leitura/escrita do negócio)
- DevEco Studio (opcional, necessário apenas para compilar o cliente HarmonyOS; pela linha de comando também é possível com `hvigorw assembleHap`)

## Domínio local padrão

O projeto usa por padrão o domínio local **`http://erp.test`** (endereço de API padrão do cliente Flutter e convenção de entrada web do backend; o cliente HarmonyOS aponta por padrão para a máquina hospedeira do emulador, `http://10.0.2.2:8788`).

- **Acesso local**: adicione a linha `127.0.0.1 erp.test` ao arquivo hosts e aponte o servidor web/reverse proxy para a porta de escuta do backend (padrão `8788`, ver `APP_HTTP_PORT` no `.env`, alterável no assistente de instalação ou no `.env`; o WebSocket usa `8282` por padrão, correspondente a `APP_WS_PORT`).
- **Alterar o domínio de implantação**:
  - Injeção na build do Flutter: `flutter build web --dart-define=API_BASE_URL=https://seu-dominio`
  - HarmonyOS: edite o `BASE_URL` em `apps/harmonyos/entry/src/main/ets/utils/Config.ets` (constante somente leitura, padrão `http://10.0.2.2:8788`)
  - Para depurar no emulador, é possível voltar temporariamente para `http://10.0.2.2:8788` (acesso à máquina hospedeira)
- Todas as versões de interface já estão no caminho (`/admin/v1`, `/api/v1`, `/open/v1`); o cliente só precisa configurar o endereço raiz.

## Início rápido

### 1. Instalar dependências

```bash
composer install
```

### 2. Configurar variáveis de ambiente

Copie e modifique as variáveis de ambiente (opcional; sem configuração, são usados os valores padrão de `config/*.php`):

```bash
cp .env.example .env
```

Principais itens de configuração:

| Variável de ambiente | Descrição | Valor padrão |
|---------|------|--------|
| `JWT_SECRET_KEY` | Chave de assinatura JWT (`env_required`: ausente, vazia ou valor fraco → recusa na inicialização) | `.env.example` traz 48 caracteres aleatórios |
| `HASHIDS_SALT` | Salt do Hashids (`env_required`) | `.env.example` traz 48 caracteres aleatórios |
| `ENCRYPTION_KEY` | Chave mestra de criptografia da camada de transporte e de armazenamento da API (`env_crypto_key`: AES-256 exige 32 bytes, comprimento diferente → recusa na inicialização) | `.env.example` traz 32 caracteres aleatórios |
| `APIDOC_PASSWORD` / `APIDOC_SECRET_KEY` | Senha de acesso ao site de documentação e chave de assinatura do token. Se ficarem vazios ou ainda com o valor de reserva `CHANGE_ME_*`, são **sempre tratados como não configurados** (o valor de reserva está neste repositório público; copiá-lo para produção equivale a publicar a senha) → o site de documentação recusa o acesso, sem afetar a inicialização da aplicação | `.env.example` traz o valor de reserva `CHANGE_ME_*` (é preciso rodar o script gerador abaixo para substituí-lo) |
| `SNOWFLAKE_DATACENTER_ID` | ID do datacenter (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID do nó de trabalho (0-31) | `1` |
| `SCOUT_HOSTS` | Endereço do ES | `http://localhost:9200` |
| `APP_HTTP_PORT` / `APP_WS_PORT` | Portas de escuta HTTP / WebSocket do backend (para onde o Nginx e outros proxies apontam) | `8788` / `8282` |
| `ANGULAR_DEV_PORT` / `REACT_DEV_PORT` | Portas do servidor de desenvolvimento do frontend (`npm run dev`, apenas em desenvolvimento) | `4200` / `5173` |
| `NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT` | Portas publicadas no host pelo docker-compose (as portas internas dos contêineres são fixas) | `80` / `443` / `3306` / `9200` |

**Em produção, substitua obrigatoriamente todas as chaves por strings aleatórias** (`JWT_SECRET_KEY` / `ENCRYPTION_KEY` / `HASHIDS_SALT` etc.: se estiverem ausentes, vazias ou ainda com valores fracos do tipo `change-me`/`xxx`, a inicialização é recusada por `env_required` / `env_crypto_key`, sem degradação silenciosa):

```bash
# Gera chaves aleatórias e grava no .env (idempotente; valores já configurados não são sobrescritos)
bash scripts/gen-env-keys.sh .env
```

### 3. Inicializar o banco de dados

**Opção 1: Assistente de instalação via Web (recomendado)**

Após iniciar o serviço, acesse `http://localhost:8788/install` e siga o assistente para concluir a instalação em 4 etapas: verificação do ambiente → configuração do banco de dados → conta de administrador → instalação em um clique. A etapa de configuração do banco de dados oferece uma caixa **importar dados de demonstração** (produtos/especificações/SKUs/clientes/fornecedores, faixa de ID 41…, removível por faixa); desmarcada por padrão — não a marque em produção.

**Opção 2: Importação via linha de comando**

```bash
mysql -u root -p nome_do_banco < database/install.sql
```

O `install.sql` é a linha de base completa em arquivo único, com toda a estrutura das 227 tabelas e os dados de seed.

**Opção 3: Ambiente Docker**

Não é preciso importar manualmente: o `install.sql` já está montado em `/docker-entrypoint-initdb.d` do contêiner MySQL e é inicializado automaticamente na primeira inicialização.

### 4. Iniciar o serviço

```bash
php start.php start
```

Por padrão, escuta em `http://0.0.0.0:8788`.

### 5. Iniciar o frontend (opcional)

**Painel administrativo Flutter (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo de painel administrativo para PC)
```

**Cliente HarmonyOS (mobile):**

Use o DevEco Studio para abrir o diretório `apps/harmonyos/` e execute em um dispositivo real ou emulador.

### 6. Implantação em um clique com Docker Compose (recomendado para produção)

O projeto oferece uma solução completa de orquestração Docker com 5 serviços: Nginx, PHP (app webman), MySQL, Redis e Elasticsearch.

```bash
# 1. Configurar as variáveis de ambiente do Docker
cp .env.docker .env
# 2. Substituir chaves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Iniciar todos os serviços
docker compose up -d

# 4. Inicializar o banco de dados (executar dentro do contêiner app)

# 5. Acessar
# http://localhost:8788  (webman)
# http://localhost:8080  (reverse proxy Nginx)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, baseado em `php:8.3-cli`
- `docker-compose.yml`: orquestração de 5 serviços, isolamento de rede, persistência de dados via volumes
- `.env.docker`: variáveis de ambiente específicas para Docker

## Uso

### 1. Login

No primeiro uso, acesse o instalador web `http://localhost:8788/install` para concluir a instalação e criar uma conta de administrador. Já instalado, abra o console, insira suas credenciais e passe no captcha de clique para entrar.

### 2. Navegação

Após o login, acesse os módulos pela barra lateral: painel, produtos, compras, vendas, estoque, finanças, CRM, fluxos de aprovação, notificações, projetos, RH, fabricação, relatórios personalizados, OMS/WMS/TMS, painéis BI e administração do sistema (usuários/papéis/configuração/logs). A barra lateral é fixa no desktop e vira uma gaveta no mobile.

### 3. Permissões e segurança

- Funções e APIs são controladas por RBAC; menus e interfaces sem permissão são inacessíveis (403)
- Operações sensíveis, como excluir usuários/papéis, exigem confirmar a senha atual no corpo da requisição
- Após o logout, o token é imediatamente incluído na lista negra

### 4. Motor de busca full-text (opcional)

A sincronização de índices é feita por meio de `erikwang2013/webman-scout` (ao adicionar o trait `Searchable` a um modelo, o índice é sincronizado automaticamente ao salvar). Há suporte a dois motores, **Elasticsearch** e **OpenSearch**, e você escolhe um deles:

**① Instalar o cliente correspondente (o pacote Composer e o driver precisam combinar; instalar o errado gera o erro "Please install the ... client")**

| Motor | Cliente Composer |
|---|---|
| Elasticsearch | `composer require elasticsearch/elasticsearch:^9.5` |
| OpenSearch | `composer require opensearch-project/opensearch-php:^2.0` |

**② Configurar o `.env` para escolher o driver**

```ini
# elasticsearch | opensearch (deve ser igual ao cliente instalado acima)
SCOUT_DRIVER=opensearch
# Prefixo do nome do índice / shards / réplicas / tamanho do bloco em lote / soft delete (comum aos dois motores)
SCOUT_PREFIX=erp_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true
```

**③ Configuração de conexão (os dois motores leem de lugares diferentes)**

- **Elasticsearch**: `SCOUT_HOSTS` no `.env` (vários nós separados por vírgula, por exemplo `http://localhost:9200`), conexão direta sem autenticação;
- **OpenSearch**: a imagem oficial habilita o plugin de segurança por padrão (TLS autoassinado + autenticação por conta) e usa a seção `opensearch` de `config/scout.php`, sem ler `SCOUT_HOSTS`:

  ```ini
  # .env
  SCOUT_OPENSEARCH_HOST=https://localhost:9200
  SCOUT_OPENSEARCH_USERNAME=admin
  SCOUT_OPENSEARCH_PASSWORD=sua_senha
  ```

  A seção `opensearch` de `config/scout.php` traz `ssl_verification=false` por padrão (certificado autoassinado local); em produção altere para `true` e configure o certificado — nunca use senhas fracas.

> O Docker Compose deste projeto já inclui o Elasticsearch (serviço `open-admin-es`): em implantações via Docker escolha **driver elasticsearch + cliente ES**; para contêineres OpenSearch externos/independentes escolha **driver opensearch + opensearch-php**.
>
> **Escopo da indexação**: todos os 224 modelos em `app/model/` possuem `Searchable` e, a cada gravação/soft delete, sincronizam o índice via `ModelObserver`; entre eles, AdminUser, Customer, Product e Supplier definem `toSearchableArray()` com uma lista branca de campos, e os demais modelos entram no índice pelo padrão (linha inteira).
>
> **Motor indisponível não afeta a escrita do negócio** (medido: apontando o driver para uma porta inalcançável, `save()` continua funcionando, apenas com o custo extra de uma tentativa de conexão que expira) — o motor de busca é um componente opcional; sem ele todo o negócio continua rodando.
>
> **Nota de escopo**: o projeto atualmente integra apenas a **sincronização de índices** (gravação/soft delete sincronizam na hora); não há interface nem endpoint de busca. Se o negócio precisar de busca, chame diretamente a API de consulta do Scout (os filtros das listas do painel administrativo usam consultas `where` no backend, sem passar pelo motor de busca).

### 5. Multilíngue

Troca automática pelo cabeçalho `Accept-Language`, com suporte a 13 idiomas (`zh` é o padrão, além de `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`); os painéis administrativos Angular/React têm ainda o ícone globe na barra superior e a troca no menu suspenso do centro pessoal. Veja [Internacionalização](#internacionalização).

## Convenções do banco de dados

- **Prefixo de tabela**: `erp_`
- **Chave primária**: todas as tabelas usam `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT desabilitado**
- **Geração de ID**: o ID da chave primária é gerado pela camada de aplicação via `SnowflakeService::generate()`, único em ambientes distribuídos
- **Campos obrigatórios**: toda tabela deve conter `id`, `created_at`, `updated_at`
- **Soft delete**: tabelas que precisam de soft delete adicionam `deleted_at DATETIME DEFAULT NULL`
- **Campos sensíveis**: telefone, e-mail, número de documento de identidade etc. usam o plugin `encryptable` para criptografia/descriptografia automática; o campo no banco de dados usa `VARCHAR(500)` para armazenar o texto cifrado

## Convenções da API

### Documentação da API

O projeto usa `erikwang2013/apidoc-php`: **a documentação é gerada automaticamente a partir das anotações dos controllers**, sem manutenção separada:

```bash
php start.php start          # inicia o backend
# depois acesse no navegador
http://localhost:8788/apidoc
```

- **Caminho de acesso**: `/apidoc` (prefixo de rota do plugin, ver `config/plugin/erikwang2013/apidoc/route.php`);
  esse caminho é liberado no middleware de rate limit, portanto navegar em lote pelas anotações não é bloqueado
- **Cobertura**: as interfaces administrativas (Admin) são agrupadas por módulo, com parâmetros de requisição e estruturas de resposta completos; as interfaces de cliente (Service API) cobrem autenticação/captcha/produto
- **Como documentar uma nova interface**: basta anotar o método do controller; ao salvar, basta atualizar `/apidoc` para ver o efeito

  ```php
  #[\erikwang2013\apidoc\annotation\Title("Lista de produtos")]
  #[\erikwang2013\apidoc\annotation\Desc("Consulta paginada de produtos")]
  #[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
  #[\erikwang2013\apidoc\annotation\Method("GET")]
  #[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"número da página")]
  #[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"código de negócio, 0=sucesso")]
  public function index(Request $request): Response { /* ... */ }
  ```

- Em produção, se for necessário restringir o acesso, consulte `nginx-security.conf`

### Formato de resposta unificado

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Códigos de erro de negócio

| Código de erro | Significado | Descrição |
|-------|------|------|
| `0` | Sucesso | |
| `400` | Erro de parâmetro de requisição | |
| `401` | Não autenticado (Token inválido ou expirado) | |
| `403` | Sem permissão / bloqueio de segurança | Falha na autenticação RBAC / detecção de ataque SecurityFilter |
| `404` | Recurso não encontrado | |
| `422` | Falha na validação de parâmetros | |
| `413` | Corpo da requisição muito grande | Acionado pelo SecurityFilter, acima de 10MB |
| `405` | Método de requisição não permitido | Acionado pelo SecurityFilter, apenas GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Tipo de mídia não suportado | Acionado pelo SecurityFilter, Content-Type não é JSON |
| `429` | Requisições em excesso | Acionado pelo RateLimit / bloqueio de conta (5 falhas de login bloqueiam por 15 minutos) |
| `500` | Erro interno do servidor | |

### Internacionalização

O cabeçalho `Accept-Language` troca automaticamente o idioma (zh-CN → chinês, en → inglês); o padrão é chinês.

### Tratamento de ID

- **IDs em requisições/respostas**: criptografados como string via hashids, sem expor os IDs reais do banco de dados
- **Caminhos da interface**: `GET /admin/v1/user/{hashid}` — `{id}` no caminho é a string hashid
- **Armazenamento no banco**: valor original BIGINT, gerado por snowflake

### Versão da API

A versão da API fica no caminho da URL (ex.: `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`), **o cliente não precisa de nenhum cabeçalho de versão**:

- As interfaces públicas versionadas vinculam-se diretamente à classe de controlador da versão (`app/api/v1/controller/`)
- Para adicionar uma versão, registre um novo grupo de rotas `/api/vN`, com os controladores da versão em `app/api/vN/`
- A antiga resolução dinâmica por `v()` e o middleware de cabeçalho `ApiVersion` foram removidos

### Rate limit

Baseado no algoritmo de janela deslizante do Redis, padrão de 60 vezes/minuto/IP/rota. Interfaces sensíveis têm limites mais rígidos:
- Login: 10 vezes/minuto
- Registro: 5 vezes/minuto (desativado por padrão; precisa de `REGISTRATION_ENABLED=1` para ativar)

Os cabeçalhos de resposta incluem `X-RateLimit-Limit`, `X-RateLimit-Remaining` e `X-RateLimit-Reset`. Ao exceder o limite, retorna 429 com `Retry-After`.

### Arquitetura de middlewares

Os middlewares globais atuam em todas as requisições, executados em ordem:

```
Cors (pré-processamento de CORS + cabeçalhos de resposta)
  → SecurityFilter (limitação de métodos HTTP/tamanho do corpo/validação de Content-Type/bloqueio de ataques XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF)
  → RateLimit (rate limit de janela deslizante no Redis + bloqueio de conta: 5 falhas de login bloqueiam por 15 minutos)
  → TracingId (ID de rastreamento da cadeia)
```

Middlewares de grupo de rotas: `/admin/v1` monta `AdminAuth (autenticação JWT + lista negra) → AdminPermission (autorização RBAC) → OperationLog (registro automático de POST/PUT/DELETE, com detecção de origem)`; `/open/v1` monta `OpenApiAuth`; o callback de rastreamento do TMS monta `TrackingSignature`. O idioma é resolvido por `app/common/I18n.php` a partir do `Accept-Language`, **não é middleware**.

`/health`, `/api/docs` e `/install` são endpoints públicos e passam apenas por `Cors → SecurityFilter → RateLimit → TracingId`.

Reforços de segurança:
- **Bloqueio de conta**: após 5 falhas consecutivas de login, a conta é bloqueada automaticamente por 15 minutos; logins durante o bloqueio retornam 429
- **Limite de sessões simultâneas**: no máximo 3 Tokens válidos por usuário; ao exceder, o Token mais antigo entra automaticamente na lista negra
- **security.txt**: `GET /.well-known/security.txt` fornece informações de contato de segurança no padrão RFC 9116
- **Configuração de segurança do Nginx**: consulte `nginx-security.conf` para um exemplo completo de reforço de segurança de reverse proxy

### Autenticação

Login e registro exigem primeiro a validação do **captcha de clique**:

1. O cliente solicita `POST /api/v1/captcha/generate` para obter a imagem do captcha (PNG base64) e a lista de alvos de texto
2. O usuário clica nas posições correspondentes dos textos na imagem, em ordem, e o sistema coleta as coordenadas dos cliques `[{x, y}, ...]`
3. Ao fazer login, envie também `captcha_key` e `clicks`; o servidor valida primeiro o captcha e depois as credenciais

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

As interfaces subsequentes do admin exigem autenticação JWT:

```http
Authorization: Bearer <token>
```

Após o login bem-sucedido, retorna access_token com validade de 2 horas; também retorna refresh_token com validade de 14 dias.

Ao fazer logout, o Token entra na lista negra do Redis e não pode ser reutilizado durante o período de validade. POST /admin/v1/profile/logout

### Confirmação secundária de operações sensíveis

Operações sensíveis como exclusão de usuário, papel e permissão exigem o envio da `password` do usuário atualmente logado no corpo da requisição para confirmação secundária de identidade:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista da API

A lista completa de interfaces (públicas / administrativas / de negócio / do cliente) foi movida para um documento separado:

→ [Documento de referência da API](API.md)

## Observações sobre o frontend

### Painel administrativo Angular (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (porta em ANGULAR_DEV_PORT no .env)
npm run build      # tsc --noEmit + ng build, artefato em dist/angular
npm run typecheck  # apenas verificação de tipos
```

- **Requisito de versão do Node**: o `engines` do Angular CLI 22 exige **Node ≥ 22.22.3** (versões inferiores fazem o `ng build` recusar a inicialização).
  Com um Node mais antigo na máquina, use o npx para apontar temporariamente (a forma de build mais usada neste repositório, adotada fora do CI):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  Em ambientes sem `npx` (como a máquina de validação offline deste repositório), use o tsc que acompanha a CLI para a verificação de tipos:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Proxy de desenvolvimento**: o `proxy.conf.js` já encaminha `/admin` `/api` `/open` `/health` `/metrics` `/install`
  para o `APP_HTTP_PORT` do `.env` (padrão 8788), portanto com `ng serve` **não** é preciso configurar o endereço do backend
- **Arquitetura**: dirigida por config — os `src/app/config/domains/*.ts` declaram menus e páginas de recurso, e **um único `ResourcePage`
  renderiza todas as páginas de negócio** (criar uma nova página de recurso ≈ adicionar um objeto de configuração, sem escrever componente)
- **Multilíngue**: 13 idiomas, com dicionários carregados sob demanda por idioma (cada um vira um chunk); troca pelo ícone globe da barra superior
- **Autoverificação** (todas dispensam navegador e rodam direto com `node`): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### Painel administrativo React (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (porta em REACT_DEV_PORT no .env)
npm run build      # tsc --noEmit + vite build, artefato em dist/
```

- Assim como o Angular, é **dirigido por config**: os `src/config/domains/*.ts` declaram menus e páginas de recurso,
  e o motor de renderização fica em `src/components/ResourcePage.tsx`; os tokens de estilo estão em `src/styles/tokens.css`
  (o `styles/theme.less` do lado Angular tem os mesmos valores)
- O ponto de troca de idioma fica na página do **centro pessoal** (no lado Angular há também o ícone globe na barra superior)

### Painel administrativo Flutter (estilo PC, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (estilo de painel administrativo para PC), também suporta iOS/Android/macOS/Windows/Linux
flutter analyze          # Análise estática (a mesma usada no CI)
```

- **Layout**: barra lateral (recolhível 64px/240px) + barra superior + área de conteúdo, três breakpoints responsivos (celular/tablet/desktop)
- **Cobertura**: 22 grupos de menu, 102 páginas com rota, 119 arquivos de página (os menus são declarados em `lib/app/config/menu_config.dart` e as páginas em `lib/app/pages/`) — dashboard, administração do sistema, gestão de produtos, fornecedores/clientes, gestão de compras, gestão de vendas, gestão de estoque, gestão financeira, CRM, OMS (gestão de pedidos), WMS (gestão de armazém), TMS (gestão de transporte), manufatura, gestão da qualidade, recursos humanos, gestão de projetos, fluxo de aprovação, central de notificações, relatórios personalizados, painéis BI, gestão de equipamentos, gestão de documentos
- **Gerenciamento de estado**: GetX (`ApiService` singleton + persistência de Token no `AuthService`)
- **Dashboard**: cartões de estatísticas, linha de tendência de vendas, Top produtos, distribuição de status de pedidos, idade de contas a receber e a pagar, visão geral do estoque (fl_chart)
- **Exportação**: exportação Excel/PDF (`ExportService`); o PDF inclui informações de direitos autorais não removíveis
- **Operações em lote**: exclusão em lote com seleção múltipla, ativar/desativar em lote
- **Tema**: Material 3 com temas claro/escuro
- **Internacionalização**: chinês/inglês (`lib/l10n/app_zh.arb` como modelo, gerado por `flutter gen-l10n`)

### Mobile HarmonyOS (`apps/harmonyos/`)

- **Build**: abra `apps/harmonyos/` no DevEco Studio; o equivalente na linha de comando é
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (requer HarmonyOS SDK + command-line-tools; artefato em `entry/build/default/outputs/default/*.hap`)
- **Páginas**: o registro `entry/src/main/resources/base/profile/main_pages.json` tem 41 páginas registradas, todas alcançáveis pela interface (login, dashboard com cartões de KPI + grade de negócios, lista de usuários, papéis e permissões, centro pessoal, além das páginas dos subsistemas de produtos/estoque/compras/vendas/OMS/WMS/TMS/manufatura/RH/aprovação etc.); a grade de negócios do dashboard oferece 32 entradas diretas, e as páginas de detalhe dos subsistemas são abertas por ações das linhas de lista
- **Autenticação**: JWT Bearer + refresh automático e transparente do Token em 401; falha no refresh redireciona automaticamente para a página de login
- **Armazenamento**: Token gerenciado via AppStorage
- **Internacionalização**: chinês/inglês (`resources/base/element/string.json` e `resources/en_US/element/string.json`)
- **Rede**: o `BASE_URL` é definido em `entry/src/main/ets/utils/Config.ets` (constante somente leitura) e tem `http://10.0.2.2:8788` como padrão (endereço da máquina hospedeira visto pelo emulador)

## Convenções de desenvolvimento

- Referências a funções/classes globais não usam prefixo `\`; use `use` para importar
- Todos os arquivos PHP devem conter a declaração de direitos autorais no cabeçalho
- Todos os arquivos de configuração devem conter comentários explicativos em chinês
- A chave primária do banco de dados deve ser gerada pelo snowflake na camada de aplicação; auto incremento é proibido
- Todos os IDs em parâmetros e respostas da camada de API devem passar por criptografia/descriptografia hashids
- O middleware AdminPermission usa cache Redis para permissões de usuário (TTL=60s), eliminando o gargalo de consultas N+1

## Implantação

### Docker Compose (recomendado)

O `docker-compose.yml` na raiz do projeto orquestra 5 serviços:

| Serviço | Imagem | Porta |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construído pelo `Dockerfile` local | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

A imagem PHP é construída via `Dockerfile`, imagem base `php:8.3-cli`, com OPcache habilitado.

```bash
cp .env.docker .env
# Substituir chaves por valores aleatorios (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

Pipeline de integração contínua do GitHub Actions: `.github/workflows/ci.yml`, com cinco jobs:

| Job | Conteúdo |
|------|------|
| `php` (matriz PHP 8.3 / 8.4, com serviços MySQL 8 + Redis 7) | Validação do composer e auditoria de segurança → `php -l` → **PHPStan** (level 5 + baseline) → **PHP CS Fixer** (dry-run) → importação do `install.sql` completo → **PHPUnit** (inclui casos de integração) → coleta de cobertura com pcov → limites de cobertura (geral ≥ 4%, `app/service` ≥ 10%, apertados gradualmente) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, a apertar quando o ambiente estabilizar) |
| `docs` | `bash scripts/doc-stats.sh --check`: valida se as anotações `<!-- stats:key=value -->` do README e dos docs batem com as contagens reais do código (controladores/serviços/modelos/tabelas/testes); divergência deixa o job vermelho |
| `e2e` | Sobe o serviço real webman → health check → smoke test dos fluxos principais HTTP + cobertura da API administrativa |
| `release` | Após push para `main` e aprovação dos jobs acima, cria tag patch+1 e publica um Release (ver abaixo) |

> Cobertura da análise estática do frontend: o CI hoje só roda Flutter; Angular/React (`tsc --noEmit`) e HarmonyOS (`hvigorw assembleHap`) precisam ser executados localmente ou em jobs adicionados depois.

### Processo de release (incremento de versão)

Após o push para `main` e a aprovação de todos os jobs php / docs / e2e, o job `release` do `ci.yml` cria e envia automaticamente uma nova tag com **patch+1** sobre a tag mais recente (`v1.1.4` → `v1.1.5`) e em seguida cria um GitHub Release de mesmo nome (com `--generate-notes` gerando a descrição de alterações automaticamente).

- **Disparo**: apenas push para `main` (PRs não disparam; push de tag não casa com o filtro de branch e não dispara este workflow recursivamente)
- **Idempotência**: se já existir uma tag ou release de mesmo nome no remoto (CI concorrente / tag criada manualmente), o job é ignorado automaticamente, sem erro
- **Simulação local**: `bash scripts/bump-version.sh --check` imprime a próxima versão (somente leitura, não escreve no remoto)

### Backup do banco de dados

Diretório `database/backup/`:

- `backup.sh` — backup mysqldump + gzip, limpeza automática de backups com mais de 30 dias
- `restore.sh` — restauração interativa, lista os backups disponíveis para seleção

### Configuração de segurança do Nginx

Para implantação em produção, consulte `nginx-security.conf` para o reforço de segurança do reverse proxy.

## Software livre não é fácil, seu apoio é bem-vindo

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./images/weixinpay.png "WeChat") | ![支付宝](./images/alipay.png "Alipay") |

### Transferência global (remessa bancária / Global Bank Transfer)

**Informações do beneficiário**

- Nome do beneficiário: WANG KEXUN
- Número da conta do beneficiário: 881015918251

**Banco beneficiário**

- ZA Bank SWIFT Code: AABLHKHHXXX
- Nome do banco: ZA Bank Limited
- Código do banco: 387
- Endereço do banco: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banco intermediário para transferências internacionais (se necessário)**

> Estas são informações do banco intermediário (banco de correspondência), não do banco beneficiário. Consulte o banco remetente sobre a necessidade de fornecê-las.

- Para depósitos em dólares de Hong Kong, RMB e dólares americanos: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, código bancário 006, filial Hong Kong Branch, código de filial 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Para depósitos em outras moedas: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Doação em criptomoedas (Crypto Donation)

Se este projeto ajudar você, escaneie o código QR para doar, obrigado!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
