# Painel de Administração Aberto — Documento de design

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Para o diagrama de arquitetura Mermaid detalhado, consulte [ARCHITECTURE.md](ARCHITECTURE.md) (renderização automática no GitHub/GitLab/VS Code).

## 1. Arquitetura do sistema

> **Lista de funcionalidades**: autenticação (login/register/refresh/logout + bloqueio de conta + limite de sessões) | dashboards (cache Redis) | usuários CRUD+lote+importação | papéis e permissões (RBAC) | configuração do sistema | auditoria de operações (origem em 8 plataformas) | arquivos (upload+exportação+mascaramento) | segurança (18 camadas) | operações (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                    Camada de clientes                         │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  Painel admin        │  │  Cliente (celular/tablet/    │  │
│  │  (estilo desktop)    │  │  2in1)                       │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │               Camada de gateway da API                │    │
│  │  AdminAuth (autenticação) → AdminPermission          │    │
│  │  (autorização) → Controller                          │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │        Camada de lógica de negócio                   │    │
│  │           (Controller/Service)                       │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                    Camada Model                       │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (geração de   (criptografia  (criptografia  │    │    │
│  │  │   chave primária) de campos do banco) de     │    │    │
│  │  │                 transmissão da API)          │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │            Camada de armazenamento de dados          │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (principal)│ │ (busca       │  │ (cache)  │        │    │
│  │  │           │  │  full-text) │  │          │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Arquitetura do back-end

### 2.1 Design em camadas

| Camada | Diretório | Responsabilidades |
|---|------|------|
| Rotas | `config/route.php` | Mapeamento de URL para controladores, vínculo de middlewares, rotas versionadas |
| Middlewares | `app/middleware/` | Bloqueio de ataques (SecurityFilter), rate limit (RateLimit), autenticação (JWT), autorização (RBAC), versão da API (ApiVersion) |
| Controladores | 14: Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (lado admin) + Captcha/Auth (API v1) | Validação dos parâmetros da requisição, chamada da lógica de negócio, formatação da resposta |
| Serviços de negócio | `app/service/` | Lógica de negócio reutilizável (reservado) |
| Modelos de dados | `app/model/` | Mapeamento ORM, relações, criptografia de campos |
| Utilitários comuns | `app/common/` | Serviços Hashids, Snowflake, Encryption |

### 2.2 Ciclo de vida da requisição

```
Requisição do cliente
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Correspondência de Route
  │
  ▼
Cadeia de middlewares:
  SecurityFilter ──────► Verificação de métodos HTTP → 405 (apenas GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     Bloqueio de ataques XSS/Injeção SQL/Path Traversal/Injeção de comandos/CSRF (403)
  ▼
  RateLimit ───────────► Rate limit por janela deslizante no Redis
  │ (falha retorna 429 + cabeçalho Retry-After)
  ▼
  ApiVersion ─────────► Validação do cabeçalho API-Version, injeta $request->apiVersion
  │ (falha retorna 400)
  ▼
  AdminAuth ──────────► Verificação JWT, injeta $request->adminId
  │ (falha retorna 401)
  ▼
  AdminPermission ────► Verificação de permissão RBAC (cache Redis 60s)
  │ (falha retorna 403)
  ▼
  OperationLog ───────► Registro do log de operação (POST/PUT/DELETE), detecção automática da origem
  │
  ▼
Controller::method()
  │
  ├─► Validação de parâmetros (validator)
  ├─► Confirmação de operações sensíveis (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Operações no Model (criptografia automática via encryptable)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Ciclo de vida do ID

```
Geração (Snowflake) → Armazenamento (MySQL BIGINT) → Transmissão (codificação Hashids) → Externo (string hash)
                                                                                        │
                            HashidsService::decode() ←──────────────────────────────────┘
```

### 2.4 Sistema de criptografia de dados

```
Camada de transmissão (encryption)   — AES-256-CBC, chave independente
Camada de armazenamento (encryptable) — AES-128-ECB, chave independente, processado automaticamente pelo Model $casts
Camada de exibição (mask)            — telefone: 138****1234, e-mail: a***@example.com
```

## 3. Design do banco de dados

### 3.1 Relação ER

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (usuário)       │    (associação            │     (papel)
                  │     usuário-papel)        │
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (associação papel-permissão)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (permissão/menu)
                  │
                  ▼
           erp_operation_log
             (log de operações)

erp_system_config (configuração do sistema) — tabela independente
```

### 3.2 Estrutura das tabelas principais

| Tabela | Nº de campos | Observação |
|------|-------|------|
| `erp_admin_user` | 14 | Usuário administrador; phone/email/id_card armazenados criptografados; suporte a soft delete |
| `erp_admin_role` | 7 | Papel, slug único |
| `erp_admin_permission` | 10 | Árvore de permissões (parent_id autorreferente), type: 1=menu 2=botão 3=API |
| `erp_admin_user_role` | 2 | Tabela intermediária muitos-para-muitos usuário-papel |
| `erp_admin_role_permission` | 2 | Tabela intermediária muitos-para-muitos papel-permissão |
| `erp_system_config` | 8 | Configurações chave-valor, group+key com unicidade conjunta |
| `erp_operation_log` | 9 | Log de auditoria de operações (inclui origem source) |

### 3.3 Convenção da chave primária

- Tipo: `BIGINT UNSIGNED NOT NULL`
- Característica: **não incremental**, gerada pelo algoritmo Snowflake na camada de aplicação
- Vantagens: unicidade global, amigável a ambientes distribuídos, tendência crescente favorece índices, não expõe o volume de negócio
- Configuração: datacenter_id(0-31) + worker_id(0-31), suporta 1024 nós em concorrência

## 4. Design da API

### 4.1 Convenção de URL

```
Interfaces públicas:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

Lado admin:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

Rotas de recurso:
  GET    /admin/user          → listagem
  POST   /admin/user          → criação
  GET    /admin/user/{hashid} → detalhe
  PUT    /admin/user/{hashid} → atualização
  DELETE /admin/user/{hashid} → exclusão (exige confirmação de senha)

Configuração do sistema:  /admin/config[/{hashid}]
Log de operações:  /admin/log
Central do usuário:  /admin/profile[/password|/logout]
Importação:     /admin/import/users
Upload:     /admin/upload
Lote:     /admin/user/batch/{destroy|status}
Documentação:     /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 Estratégia de versões da API

A versão da API é controlada pelo cabeçalho de requisição, **sem aparecer no caminho da URL**:

```http
API-Version: v1
```

| Mecanismo | Observação |
|------|------|
| Versão padrão | Sem o cabeçalho `API-Version`, o padrão é `v1` |
| Validação | O middleware `ApiVersion` valida; versões não suportadas retornam 400 |
| Rotas | A função auxiliar `v()` resolve dinamicamente a classe do controlador pela versão |
| Diretório | Controladores organizados por versão: `app/api/{version}/controller/` |

Exemplo de extensão — adicionar API v2:
1. Criar `app/api/v2/controller/AuthController.php`
2. Adicionar `'v2'` à constante `SUPPORTED` do middleware `ApiVersion`
3. As definições de rotas não precisam ser alteradas

```bash
# Usar v1
curl -H "API-Version: v1" /api/auth/login

# Usar v2
curl -H "API-Version: v2" /api/auth/login

# Sem passar, padrão v1
curl /api/auth/login
```

### 4.3 Estratégia de rate limit

Baseada no algoritmo de janela deslizante com Redis Sorted Set, executada por script Lua atômico:

| Interface | Limite |
|------|------|
| Padrão | 60 vezes/minuto/IP/rota |
| POST /api/auth/login | 10 vezes/minuto |
| POST /api/auth/register | 5 vezes/minuto |

Ao exceder, retorna 429, com os cabeçalhos X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Resposta unificada

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Significado | Cenário de disparo |
|------|------|---------|
| 0 | Sucesso | Resposta normal |
| 400 | Erro de parâmetro | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente/expirado/inválido |
| 403 | Sem permissão | O papel do usuário não contém a permissão necessária |
| 404 | Não existe | Recurso não encontrado |
| 422 | Falha de validação | Parâmetros do formulário fora das regras / falha na confirmação de senha |
| 500 | Erro do servidor | Exceção inesperada |

### 4.5 Fluxo de autenticação (incluindo captcha de clique)

```
Cliente                              Servidor
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② O usuário clica na posição     │
  │     do texto na imagem            │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modelo de permissões (RBAC)

```
  Usuário ──┬── Papel ──┬── Permissão
  User        Role        Permission
                 │
                 ├── type=1: Menu (controla a visibilidade da barra lateral)
                 ├── type=2: Botão (controla as ações na página)
                 └── type=3: API  (controla o acesso às interfaces)

  Formato do identificador de permissão: {method}.{path}
  Ex.: get.admin/user  post.admin/user  delete.admin/user
  Identificador de super administrador: * (pula todas as verificações de permissão)
```

### 4.7 Segunda confirmação de operações sensíveis

Operações sensíveis como excluir usuário, papel ou permissão exigem que a senha do usuário atual seja enviada no corpo da requisição para revalidação de identidade:

```
Cliente                            Servidor
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → senha errada retorna 422
  │                                │ → senha correta, continua a execução
  │◄── 200 { code: 0 }           │
```

O front-end exibe uma caixa de diálogo de confirmação antes de disparar a exclusão, coleta a senha do usuário e envia a requisição.

## 5. Design do front-end

### 5.1 Painel administrativo Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ Menu            🔔 Mensagens  👤 Admin  ▼  │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Área de conteúdo                   │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 Dashboard│ │ Cartões de   │ │ Gráfico  │     │
│ 👥 Usuários│ │ estatísticas │ │ de       │     │
│ 🔒 Papéis  │ │ ×4           │ │ tendência│     │
│ ⚙ Config │  └──────────────┘ └──────────┘     │
│ 📋 Logs   │  ┌──────┐ ┌────────────────┐       │
│          │  │Pizza │ │ Logs de        │       │
│          │  │(gráf.)│ │ operações      │       │
│          │  └──────┘ │ recentes       │       │
│          │           └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Características: barra lateral recolhível, tema duplo Material 3, tabelas de dados de alta densidade, diálogos pop-up, interações por hover do mouse

### 5.2 Mobile HarmonyOS

Rotas de páginas:

| Página | Rota | Observação |
|------|------|------|
| LoginPage | `pages/LoginPage` | Nome de usuário + senha + login com captcha de clique |
| DashboardPage | `pages/DashboardPage` | Cartões de estatísticas + operações recentes |
| UserListPage | `pages/UserListPage` | Lista de usuários, busca + pull-to-refresh + carregar ao rolar |
| UserDetailPage | `pages/UserDetailPage` | Criar/editar/ver/excluir (confirmação com AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Central do usuário, logout (confirmação com AlertDialog) |

Fluxo de dados: Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Design de segurança

### 6.1 Defesa em profundidade

| Camada | Medida |
|------|------|
| Restrição de métodos | Lista branca de métodos HTTP no SecurityFilter, apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padronizados retornam 405 |
| Bloqueio de ataques | Middleware SecurityFilter, detecção e bloqueio de XSS/Injeção SQL/Path Traversal/Injeção de comandos/CSRF |
| Verificação humana | Captcha de clique (Click Captcha), validação obrigatória no login/registro |
| Bloqueio de conta | 5 falhas consecutivas de login bloqueiam a conta por 15 minutos; durante o bloqueio retorna 429 |
| Limite de sessões | No máximo 3 Tokens concorrentes por usuário; ao exceder, o Token mais antigo entra automaticamente na lista negra |
| Rate limit | Middleware RateLimit, janela deslizante no Redis, Lua atômico |
| CSP | Cabeçalho Content-Security-Policy restringe a origem dos recursos, prevenindo XSS e injeção de dados |
| Confirmação de operações | Operações sensíveis como exclusão exigem digitar a senha do usuário atual para segunda confirmação |
| Transmissão | HTTPS + JWT Bearer Token |
| ID de interfaces | Criptografia Hashids; o ID real não pode ser deduzido externamente |
| Corpo da requisição | Criptografia de campos sensíveis com AES-256-CBC |
| Banco de dados | Chave primária BIGINT (não expõe o incremento) |
| Banco de dados | Armazenamento criptografado de campos sensíveis com AES-128-ECB |
| Autenticação | JWT HS256, expiração de 2h + refresh token |
| Autorização | RBAC, controle de permissão com granularidade method.path |
| Auditoria | OperationLog registra todas as operações (inclui detecção automática da origem source) |

### 6.2 Gestão de chaves

```
JWT_SECRET          → injetado por variável de ambiente, string aleatória de 64 caracteres
HASHIDS_SALT        → sal único; se vazar, exige troca global
ENCRYPTION_KEY      → chave de criptografia de transmissão da API, 32 bytes
ENCRYPTABLE_KEY     → chave de criptografia de armazenamento do banco, independente da chave de transmissão
SCOUT_HOSTS         → endereço do ES, implantação em rede interna
```

### 6.3 Proteção de dados sensíveis

| Cenário | Campo | Medida |
|------|------|------|
| Exibição em listagem | phone | Mascaramento: 138****1234 |
| Exibição em listagem | email | Mascaramento: a***@example.com |
| Visualização de detalhe | phone/email | Exige interface de descriptografia |
| Exportação Excel | phone/email | Exportação com mascaramento |
| Exportação PDF | todos os campos | Mascaramento + marca d'água de copyright não removível |
| Armazenamento | phone/email/id_card | Criptografia encryptable para texto cifrado |

## 7. Design de exportação

### 7.1 Exportação Excel

```
Requisição: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() consulta os dados (limit 10000)
  → mascaramento dos campos sensíveis
  → construção com PhpSpreadsheet (cabeçalho azul com texto branco + primeira linha congelada + filtro automático)
  → gravação em runtime/tmp/ → resposta de download
```

### 7.2 Exportação PDF

```
Requisição: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + CSS inline + copyright no cabeçalho da página + copyright não removível no rodapé
  → renderização com Dompdf A4 paisagem
  → gravação em runtime/tmp/ → resposta de download
```

## 8. Arquitetura de implantação

### 8.1 Topologia recomendada

```
Nginx (:443 HTTPS) → webman worker × N (:8787) → MySQL + ES + Redis
                    arquivos estáticos: build do Flutter Web
```

### 8.2 Docker Compose (ambiente de produção recomendado)

O `docker-compose.yml` na raiz do projeto orquestra todos os serviços da topologia acima:

| Serviço | Imagem/build | Porta | Observação |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy reverso + arquivos estáticos + Gzip |
| `app` | build do `Dockerfile` local | 8787 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Banco de dados principal, persistência com volume de dados |
| `redis` | redis:7-alpine | 6379 | Cache / rate limit / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Busca full-text |

Antes de iniciar, substitua as chaves `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` etc. do `docker-compose.yml` por strings aleatórias.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

A integração contínua do GitHub Actions está definida em `.github/workflows/ci.yml`:
- Verificação de sintaxe PHP (`php -l`)
- Testes unitários PHPUnit
- Análise estática Flutter (`flutter analyze`)

### 8.4 Backup do banco de dados

`database/backup/backup.sh` — backup mysqldump + gzip, limpeza automática de backups antigos com mais de 30 dias.
`database/backup/restore.sh` — seleção interativa e restauração do backup.

### 8.5 Monitoramento

O endpoint `GET /metrics` (`MetricsController`) expõe 5 métricas gauge em formato de texto Prometheus: total de requisições HTTP, usuários ativos, status de conexão do banco/Redis, uso de memória.

### 8.6 Requisitos de ambiente

| Componente | Versão mínima | Configuração recomendada |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ OPcache habilitado |
| MySQL | 8.0+ | 8.0+ replicação mestre-escravo |
| Elasticsearch | 7.x | 8.x cluster de 3 nós |
| Redis | 6.x | 7.x modo sentinel |
| Nginx | 1.20+ | Proxy reverso + gzip + SSL |
| Flutter SDK | 3.41+ | Versão estável mais recente |
| HarmonyOS | API 12 | DevEco Studio 5.x |
