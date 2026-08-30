# Sistema ERP Aberto (open-erp)

Sistema ERP full-stack baseado em webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Mascote polvo do open-erp" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | [Français](../fr/README.md) | [Español](../es/README.md) | Português | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Comparação de versões](EDITIONS.md) | [Diagrama de arquitetura](ARCHITECTURE.md) | [Diagrama de arquitetura do sistema](#diagrama-de-arquitetura-do-sistema) | [Documento de design](DESIGN.md) | [Arquitetura de segurança](SECURITY.md) | [Referência da API](API.md) | [Manual de funções](FUNCTIONS.md)

## Lista de funcionalidades

| Domínio de negócio | Funcionalidade | Descrição |
|--------|------|------|
| 🔐 Autenticação | Login/Registro/Refresh token/Logout | Captcha de clique + JWT + lista negra |
| | Bloqueio de conta | 5 falhas bloqueiam por 15 minutos |
| | Limite de sessões simultâneas | Máximo de 3 Tokens válidos por usuário |
| 📊 Dashboard | Visão geral de negócio/Painel de vendas/Painel de estoque/Painel financeiro | Tendência de vendas 30 dias/Top5 produtos mais vendidos/Distribuição de status de pedidos/Idade de contas a receber e pagar + Cache Redis por 5 minutos |
| 👥 Gerenciamento de usuários | CRUD + exclusão em lote/ativar-desativar | Soft delete + confirmação secundária de senha |
| | Importação em lote via Excel | Validação linha a linha + relatório de erros |
| 🔒 Papéis e permissões | CRUD de papéis + árvore de permissões | Autenticação RBAC na granularidade method.path |
| ⚙ Configuração do sistema | CRUD de pares chave-valor | Gerenciamento por grupos |
| 📋 Auditoria de operações | Consulta de logs + detecção de origem | Reconhecimento automático de 8 plataformas |
| 📁 Gerenciamento de arquivos | Upload/Exportação Excel/Exportação PDF | Mascaramento automático de dados sensíveis |
| 🛡 Proteção de segurança | 18 camadas de defesa em profundidade | XSS/Injeção SQL/Path traversal/Injeção de comandos/CSRF/Rate limit/CSP... |
| 🏥 Operações | Health check/metrics/Documentação da API/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Gestão de produtos | Cadastro de produto/SKU/Múltiplas especificações/Múltiplas unidades/Categoria/Marca/Estratégia de preço | Árvore de categorias multinível + conversão de múltiplas unidades |
| | Armazéns e localizações | Gerenciamento de múltiplos armazéns e localizações |
| | Cadastro de fornecedores/clientes | Contatos/Contas bancárias/Limite de crédito |
| 📥 Gestão de compras | Solicitação→Pedido→Recebimento→Devolução→Liquidação | Fluxo completo de compras + aprovação |
| 📤 Gestão de vendas | Cotação→Pedido→Expedição→Devolução→Liquidação | Cotação vira pedido + margem bruta de vendas |
| 🏗 Gestão de estoque | Estoque em tempo real/Lote/Número de série/Transferência/Inventário/Alertas | Custeio por média móvel ponderada |
| 💰 Gestão financeira | Contas a receber/a pagar/Recebimentos e pagamentos/Diário/Reembolsos/Demonstração de resultados/Ativo imobilizado/Impostos/Multimoeda/Orçamento/Centro de custo e lucro | Geração automática de contas a receber/a pagar + baixa + gestão financeira completa |
| 🤝 CRM | Clientes/Contatos/Registros de acompanhamento/Campanhas de marketing/Tickets de serviço/Relatórios analíticos/Funil de vendas/Pool público/Cotações/Contratos | Gestão do ciclo de vida completo do cliente |
| ✅ Fluxo de aprovação | Definição de workflow/Enviar para aprovação/Aprovar/Rejeitar/Retirar/Minhas aprovações | Mecanismo de fluxo de aprovação multinó |
| 🔔 Notificações | Lista de notificações/Marcar lida/Contagem de não lidas/Marcar todas lidas | Push de mensagens em tempo real e rastreamento de status |
| 📐 Gestão de projetos | Projetos/Tarefas/Registros de horas | Acompanhamento do progresso do projeto e gestão de recursos |
| 👤 Recursos humanos | Departamentos/Funcionários/Cargos/Ponto/Férias/Salários | Gestão completa de RH |
| 🏭 Manufatura | BOM/Ordens de produção/Roteiros de processo/Postos de trabalho/MRP | Planejamento de necessidades de materiais e execução de produção |
| 📈 Relatórios personalizados | Modelos de relatório/Conjuntos de dados/Campos/Filtros/Execução/Agendamento | Construtor visual de relatórios |
| 📋 Gestão de pedidos (OMS) | Pedidos multicanal/Orquestração de atendimento/Reserva de estoque/Alocação/Cancelamento/RMA devoluções e trocas | Gestão do ciclo de vida completo do pedido |
| 🏗 Gestão de armazém (WMS) | Zonas e localizações/ASN/Recebimento/Putaway/Ondas/Picking/Embalagem/Expedição | Fluxo completo de operações de armazém |
| 🚚 Gestão de transporte (TMS) | Transportadoras/Serviços/Tarifas/Conhecimentos de transporte/Rastreamento/Faturas de frete | Comparação de frete entre transportadoras + rastreamento |

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
| Mecanismo de busca | Elasticsearch | Sincronização e consulta via `webman-scout` |
| Frontend administrativo | Flutter 3.x | Web com estilo de painel administrativo para PC (`apps/flutter/`) |
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
| `hg/apidoc` | Geração automática de documentação da API | Documentação de interface por anotações, agrupada para admin/cliente |

## Internacionalização

Internacionalização | Detecção automática do cabeçalho Accept-Language | Suporte bilíngue Chinês/Inglês

## Estrutura do projeto

```
open-erp/
├── app/
│   ├── admin/controller/       # Controladores de administração do sistema (14)
│   ├── api/v1/controller/      # API do cliente (versão controlada pelo cabeçalho API-Version)
│   ├── controller/             # Controladores dos módulos de negócio (88)
│   │   ├── product/            # Produto/Categoria/Marca/Armazém/Localização/Fornecedor/Cliente (7)
│   │   ├── purchase/           # Solicitação de compra/Pedido/Recebimento/Devolução/Liquidação (5)
│   │   ├── sales/              # Cotação de venda/Pedido/Expedição/Devolução/Liquidação (5)
│   │   ├── inventory/          # Estoque/Fluxo/Transferência/Inventário/Alertas (5)
│   │   ├── finance/            # A receber/a pagar/Lançamentos/Recebimentos e pagamentos/Diário/Razão geral/Razão auxiliar/Relatórios/Ativos/Impostos/Multimoeda/Orçamento/Centro de custo e lucro (20)
│   │   ├── crm/                # Oportunidades/Acompanhamento/Funil/Contatos/Pool público/Contratos/Cotações/Marketing/Tickets/Análises (10)
│   │   ├── workflow/           # Definição de workflow/Envio de aprovação/Aprovar/Rejeitar/Retirar (2)
│   │   ├── notification/       # Lista de notificações/Lida/Contagem de não lidas (1)
│   │   ├── project/            # Projetos/Tarefas/Registros de horas (3)
│   │   ├── hr/                 # Departamentos/Funcionários/Cargos/Ponto/Férias/Salários (5)
│   │   ├── manufacturing/      # BOM/Ordens de produção/Roteiros/Postos de trabalho/MRP (5)
│   │   ├── report/             # Modelos de relatório/Conjuntos de dados/Execução/Agendamento (2)
│   │   ├── oms/                # Pedidos OMS/Atendimento/RMA/Canais (4)
│   │   ├── wms/                # Zonas/Localizações/ASN/Recebimento/Putaway/Ondas/Picking/Embalagem (8)
│   │   └── tms/                # Transportadoras/Serviços/Tarifas/Conhecimentos/Rastreamento/Faturas de frete (6)
│   ├── service/                # Camada de lógica de negócio
│   │   ├── inventory/          # Entrada/saída de estoque + custeio por média móvel ponderada + reserva/ATP
│   │   ├── finance/            # Geração automática de contas a receber/a pagar + baixa
│   │   ├── notification/       # Serviço de envio de notificações
│   │   ├── oms/                # Orquestração de pedidos/Alocação de estoque/Ciclo de vida RMA
│   │   ├── wms/                # Fluxo de entrada (ASN→Recebimento→Putaway) / fluxo de saída (Ondas→Picking→Embalagem)
│   │   └── tms/                # Gestão de conhecimentos de transporte/Comparação de frete/Rastreamento
│   ├── model/                  # 161 modelos Eloquent (compartilhados entre módulos)
│   ├── middleware/             # 12 middlewares
│   ├── common/                 # Serviços Hashids/Snowflake/Encryption
│   └── queue/                  # Tarefas de fila
├── apps/
│   ├── flutter/                # Flutter multiplataforma (Web PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Cliente nativo HarmonyOS
├── config/                     # Arquivos de configuração (com comentários em chinês)
│   ├── plugin/hg/apidoc/        # Configuração da documentação da API
├── database/
│   ├── install.sql              # SQL de instalação completo (163 tabelas + dados de seed)
│   ├── e2e-seed.sql             # Seed mínimo para E2E/CI
│   └── backup/                 # Scripts de backup/restauração
├── docs/                       # Documentação de arquitetura, design, segurança e API
├── tests/                      # Testes PHPUnit (20 arquivos de teste, 137 métodos de teste, 805 asserções)
├── resource/
│   └── translations/           # Arquivos de tradução (zh_CN, en)
│       ├── zh_CN/              # Tradução em chinês (127 chaves)
│       └── en/                 # Tradução em inglês (127 chaves)
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

**19 grandes domínios de negócio, 163 tabelas de dados, 121 controladores**: cobrem autenticação e segurança, dashboard, administração do sistema, proteção de segurança, monitoramento de operações, gestão de produtos, compras, vendas, estoque, finanças (14 submódulos), CRM (10 submódulos), fluxo de aprovação, notificações, gestão de projetos, recursos humanos, manufatura (MRP), relatórios personalizados, gestão de pedidos (OMS), gestão de armazém (WMS), gestão de transporte (TMS), gestão de qualidade (QMS), gestão de equipamentos (EAM), gestão de documentos (DMS) e painéis BI.

### Ciclo de vida da requisição

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Caminho completo da requisição, do cliente ao banco de dados**: Cliente (Flutter/HarmonyOS) → Terminação SSL no Nginx → Detecção de idioma → Tratamento de CORS → Filtro de segurança → Rate limit → Validação de versão da API → [Admin: autenticação JWT → permissão RBAC → log de operações] → Controlador → Camada de serviços → Camada de modelos → Cache/Banco de dados/Mecanismo de busca → Resposta JSON. O diagrama inclui dois caminhos: cache hit e cache miss.

### Arquitetura de defesa em profundidade de segurança

![Security Architecture](./diagrams/security-architecture-cn.svg)

**18 camadas de defesa em profundidade**: L0 rede física → L1 segurança de transporte → L2 cabeçalhos HTTP seguros → L3 validação de requisição → L4 higienização de entrada → L5 proteção CSRF → L6 rate limit → L7 autenticação (JWT+Captcha+lista negra+controle de sessão) → L8 autorização RBAC → L9 proteção de dados (criptografia de transmissão + criptografia de armazenamento + ofuscação de ID + mascaramento de dados) → L10 auditoria e monitoramento → L11 divulgação de conformidade.

---

## Requisitos de ambiente

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (necessário apenas para desenvolvimento frontend)
- Elasticsearch >= 7.x (opcional, necessário para a função de busca)

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
| `JWT_SECRET` | Chave de assinatura JWT | `open-admin-jwt-secret-change-in-production` |
| `HASHIDS_SALT` | Salt do Hashids | `open-admin-hashids-salt-2026` |
| `ENCRYPTION_KEY` | Chave de criptografia da API | Valor padrão de 32 bytes |
| `SNOWFLAKE_DATACENTER_ID` | ID do datacenter (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID do nó de trabalho (0-31) | `1` |
| `SCOUT_HOSTS` | Endereço do ES | `http://localhost:9200` |

**Em produção, é obrigatório alterar todas as chaves para strings aleatórias.**

### 3. Inicializar o banco de dados

**Opção 1: Assistente de instalação via Web (recomendado)**

Após iniciar o serviço, acesse `http://localhost:8788/install` e siga o assistente para concluir a instalação em 4 etapas: verificação do ambiente → configuração do banco de dados → conta de administrador → instalação em um clique.

**Opção 2: Importação via linha de comando**

```bash
mysql -u root -p nome_do_banco < database/install.sql
```

O `install.sql` é a fusão de 29 arquivos de migração e contém a estrutura completa das 163 tabelas e os dados de seed.

**Opção 3: Ambiente Docker**

```bash
docker-compose exec app mysql -h mysql -u root -p < database/install.sql
```

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

# 2. Iniciar todos os serviços
docker-compose up -d

# 3. Inicializar o banco de dados (executar dentro do contêiner app)
docker-compose exec app mysql -h mysql -u root -p < database/install.sql

# 4. Acessar
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

### 4. Multilíngue

Troca automática pelo cabeçalho `Accept-Language` (zh-CN / en), com chinês por padrão.

## Convenções do banco de dados

- **Prefixo de tabela**: `erp_`
- **Chave primária**: todas as tabelas usam `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT desabilitado**
- **Geração de ID**: o ID da chave primária é gerado pela camada de aplicação via `SnowflakeService::generate()`, único em ambientes distribuídos
- **Campos obrigatórios**: toda tabela deve conter `id`, `created_at`, `updated_at`
- **Soft delete**: tabelas que precisam de soft delete adicionam `deleted_at DATETIME DEFAULT NULL`
- **Campos sensíveis**: telefone, e-mail, número de documento de identidade etc. usam o plugin `encryptable` para criptografia/descriptografia automática; o campo no banco de dados usa `VARCHAR(500)` para armazenar o texto cifrado

## Convenções da API

### Documentação da API

O projeto usa hg/apidoc para gerar automaticamente a documentação da interface; acesse `/apidoc` para visualizar.

- Interfaces administrativas (Admin): 25 grupos de módulos, com parâmetros de requisição e estruturas de resposta completos
- Interfaces do cliente (Service API): 3 grupos — autenticação/captcha/produto
- Todas as interfaces indicam os cabeçalhos globais: autenticação JWT, versão da API, internacionalização

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
- **Caminhos da interface**: `GET /admin/user/{hashid}` — `{id}` no caminho é a string hashid
- **Armazenamento no banco**: valor original BIGINT, gerado por snowflake

### Versão da API

A versão da API é controlada pelo cabeçalho da requisição, **não aparece na URL**:

```http
API-Version: v1
```

- Sem o cabeçalho de versão, o padrão é `v1`
- Versões não suportadas retornam `400 Bad Request`
- Para adicionar uma nova versão, basta criar o diretório `app/api/{version}/controller/` e registrar a nova versão no middleware

### Rate limit

Baseado no algoritmo de janela deslizante do Redis, padrão de 60 vezes/minuto/IP/rota. Interfaces sensíveis têm limites mais rígidos:
- Login: 10 vezes/minuto
- Registro: 5 vezes/minuto (desativado por padrão; precisa de `REGISTRATION_ENABLED=1` para ativar)

Os cabeçalhos de resposta incluem `X-RateLimit-Limit`, `X-RateLimit-Remaining` e `X-RateLimit-Reset`. Ao exceder o limite, retorna 429 com `Retry-After`.

### Arquitetura de middlewares

Os middlewares globais atuam em todas as requisições, executados em ordem:

```
Locale (detecção automática de Accept-Language, define o locale)
  → Cors (pré-processamento de CORS + cabeçalhos de resposta)
  → SecurityFilter (limitação de métodos HTTP/tamanho do corpo/validação de Content-Type/XSS/Injeção SQL/Path traversal/Injeção de comandos/bloqueio de CSRF)
  → RateLimit (rate limit de janela deslizante no Redis + bloqueio de conta: 5 falhas de login bloqueiam por 15 minutos)
  → ApiVersion (validação da versão da API, grupo de rotas /api)
  → AdminAuth (autenticação JWT + lista negra, grupo de rotas /admin)
  → AdminPermission (autenticação RBAC, grupo de rotas /admin)
  → OperationLog (registro automático de POST/PUT/DELETE, com detecção de origem, grupo de rotas /admin)
```

`/health`, `/api/docs` e `/install` são endpoints públicos e passam apenas por `Locale → Cors → SecurityFilter → RateLimit`.

Reforços de segurança:
- **Bloqueio de conta**: após 5 falhas consecutivas de login, a conta é bloqueada automaticamente por 15 minutos; logins durante o bloqueio retornam 429
- **Limite de sessões simultâneas**: no máximo 3 Tokens válidos por usuário; ao exceder, o Token mais antigo entra automaticamente na lista negra
- **security.txt**: `GET /.well-known/security.txt` fornece informações de contato de segurança no padrão RFC 9116
- **Configuração de segurança do Nginx**: consulte `nginx-security.conf` para um exemplo completo de reforço de segurança de reverse proxy

### Autenticação

Login e registro exigem primeiro a validação do **captcha de clique**:

1. O cliente solicita `POST /api/captcha/generate` para obter a imagem do captcha (PNG base64) e a lista de alvos de texto
2. O usuário clica nas posições correspondentes dos textos na imagem, em ordem, e o sistema coleta as coordenadas dos cliques `[{x, y}, ...]`
3. Ao fazer login, envie também `captcha_key` e `clicks`; o servidor valida primeiro o captcha e depois as credenciais

```http
POST /api/auth/login
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

Ao fazer logout, o Token entra na lista negra do Redis e não pode ser reutilizado durante o período de validade. POST /admin/profile/logout

### Confirmação secundária de operações sensíveis

Operações sensíveis como exclusão de usuário, papel e permissão exigem o envio da `password` do usuário atualmente logado no corpo da requisição para confirmação secundária de identidade:

```http
DELETE /admin/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Lista da API

A lista completa de interfaces (públicas / administrativas / de negócio / do cliente) foi movida para um documento separado:

→ [Documento de referência da API](API.md)

## Observações sobre o frontend

### Painel administrativo Flutter (estilo PC)

- **Layout**: barra lateral (recolhível 64px/240px) + barra superior + área de conteúdo, três breakpoints responsivos (celular/tablet/desktop)
- **Páginas**: login, dashboard, gerenciamento de usuários, papéis e permissões, configuração do sistema, logs de operações, centro pessoal
- **Gerenciamento de estado**: GetX (`ApiService` singleton + persistência de Token no `AuthService`)
- **Dashboard**: cartões de estatísticas, gráfico de tendências (fl_chart), gráfico de pizza, logs de operações recentes
- **Exportação**: exportação Excel/PDF; o PDF inclui informações de direitos autorais não removíveis
- **Operações em lote**: exclusão em lote com seleção múltipla, ativar/desativar em lote
- **Tema**: Material 3 com temas claro/escuro

### Mobile HarmonyOS

- **Páginas**: login, dashboard, lista/detalhes de usuário, centro pessoal
- **Autenticação**: JWT Bearer + refresh automático e transparente do Token em 401; falha no refresh redireciona automaticamente para a página de login
- **Armazenamento**: Token gerenciado via AppStorage

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
docker-compose up -d
```

### CI/CD

Pipeline de integração contínua do GitHub Actions: `.github/workflows/ci.yml`

- Verificação de sintaxe PHP (`php -l`)
- Testes de unidade PHPUnit
- Análise estática Flutter (`flutter analyze`, já incluída no CI e ativa — veja o job flutter em `.github/workflows/ci.yml`)

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
