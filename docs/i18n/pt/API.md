# Documento de referência da API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Documentação da API

O projeto usa [hg/apidoc](https://github.com/hg-code/apidoc) para gerar automaticamente a documentação interativa da API.

**Como acessar:** após iniciar o serviço, acesse `http://localhost:8788/apidoc`

**Grupos da documentação:**
| Grupo | Descrição | Nº de módulos |
|------|------|--------|
| Interfaces administrativas (Admin) | Todas as interfaces do sistema de administração backend | 25 módulos |
| Interfaces do cliente (Service API) | Interfaces leves chamadas pelo mobile/Web | 3 módulos |

**Cabeçalhos globais:**
| Cabeçalho | Descrição |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | Número da versão da API (v1) |
| `Accept-Language` | Idioma de internacionalização (zh-CN/en) |

**Convenção de anotações:** todos os métodos de controlador usam a série de anotações `@Apidoc\*` para indicar nome da interface, descrição, URL, método de requisição, parâmetros e estrutura de retorno.

## 1. Visão geral

O painel administrativo aberto (open-admin) é construído sobre webman v2 e fornece APIs JSON RESTful. Todas as interfaces administrativas exigem autenticação JWT e validação de permissão RBAC; as interfaces públicas são roteadas para controladores versionados por meio do cabeçalho de versão da API.

- **URL base**: `http://localhost:8788`
- **Versão da API**: controlada pelo cabeçalho `API-Version: v1` (padrão v1 quando ausente)

> **Resumo dos endpoints**: autenticação(5) | dashboard(1) | usuários(7) | papéis(4) | permissões(4) | configurações(4) | logs(1) | centro pessoal(3) | importação/exportação(3) | upload(1) | operações(4: health/metrics/docs/security.txt) | total de 37 endpoints
- **Autenticação**: `Authorization: Bearer <token>` (JWT)
- **Formato de resposta**: `{ "code": 0, "message": "success", "data": {...} }`
- **Endpoint de documentação**: `GET /api/docs` retorna a especificação JSON OpenAPI 3.0

### Internacionalização

A API alterna automaticamente o idioma por meio do cabeçalho `Accept-Language`:

| Valor do cabeçalho | Idioma |
|---------|------|
| `zh-CN`, `zh` | Chinês (padrão) |
| `en`, `en-US` | English |

```bash
# Resposta em inglês
curl -H "Accept-Language: en" http://localhost:8788/admin/product

# Resposta em chinês (padrão)
curl http://localhost:8788/admin/product
```

O campo `message` da resposta é retornado no idioma correspondente.

### Requisitos de requisição

- Apenas os métodos `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` são permitidos; usar outros métodos HTTP (como TRACE, CONNECT, PATCH) retorna 405
- Todas as requisições `POST` / `PUT` devem definir `Content-Type: application/json` (exceto upload de arquivos); caso contrário, retorna 415
- O corpo da requisição não pode exceder 10MB; caso contrário, retorna 413
- O filtro de segurança examina todas as entradas de requisição contra XSS, injeção SQL, path traversal e injeção de comandos; em caso de detecção, retorna 403
- 5 falhas consecutivas de login acionam o bloqueio de conta (15 minutos); requisições de login durante o bloqueio retornam 429
- Um usuário pode manter no máximo 3 Tokens válidos simultaneamente; ao exceder, o Token mais antigo entra automaticamente na lista negra

## 2. Códigos de erro

| code | Significado | Cenário de acionamento |
|------|------|---------|
| 0 | Sucesso | |
| 400 | Erro de parâmetro de requisição | Formato da requisição incorreto |
| 401 | Não autenticado | Token ausente / expirado / na lista negra |
| 403 | Sem permissão / bloqueio de segurança | Permissão RBAC insuficiente / acionado pelo SecurityFilter |
| 404 | Recurso não encontrado | O alvo da consulta/atualização/exclusão não existe |
| 405 | Método de requisição não permitido | Apenas GET/POST/PUT/DELETE/OPTIONS/HEAD; métodos não padronizados são rejeitados diretamente |
| 413 | Corpo da requisição muito grande | Content-Length acima de 10MB |
| 415 | Tipo de mídia não suportado | Content-Type de POST/PUT não é JSON nem upload de arquivo |
| 422 | Falha na validação de parâmetros | Campos obrigatórios ausentes, formato incorreto, validação de negócio reprovada |
| 429 | Requisições em excesso | Acionado pelo RateLimit / bloqueio de conta (5 falhas consecutivas de login bloqueiam por 15 minutos) |
| 500 | Erro interno do servidor | |

## 3. Endpoints públicos

Todos os endpoints públicos ficam no grupo `/api` e são distribuídos pelo middleware `ApiVersion` ao controlador versionado correspondente conforme o cabeçalho `API-Version` (como `app\api\v1\controller\AuthController`).

### 3.1 Health check

```
GET /health
```

- **Autenticação**: não necessária
- **Rate limit**: nenhum

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Os valores de `database`, `redis` e `elasticsearch`: `"ok"` | `"unavailable"`. `elasticsearch` retorna `"unavailable"` quando o ES está inacessível; se o estado de saúde do cluster não for green/yellow, retorna o valor real do status (como `"red"`).

### 3.2 Documentação da API

```
GET /api/docs
```

- **Autenticação**: não necessária
- **Rate limit**: padrão global (60 vezes/minuto)
- **Resposta**: especificação JSON OpenAPI 3.0.3, incluindo definições de todos os endpoints, parâmetros e schemas

### 3.3 Gerar captcha de clique

```
POST /api/captcha/generate
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 vezes/minuto)

**Corpo da requisição**:
```json
{
  "difficulty": "medium"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| difficulty | string | não | `easy` / `medium` / `hard`, padrão `medium` |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "Clique em A" },
        { "order": 2, "text": "Clique em B" }
      ]
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| key | string | Identificador do captcha, reenviado na validação |
| image | string | Imagem PNG codificada em base64 |
| extra.targets[].order | int | Ordem de clique |
| extra.targets[].text | string | Texto de instrução do alvo de clique |

### 3.4 Validar captcha de clique

```
POST /api/captcha/verify
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 vezes/minuto)

**Corpo da requisição**:
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| key | string | sim | Chave do captcha, retornada por generate |
| clicks | array{object} | sim | Array de coordenadas de clique, cada elemento contém `x` (int) e `y` (int) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Validação aprovada",
  "data": { "valid": true }
}
```

Em caso de falha na validação, `code` é 422, `message` é `"Validação falhou, tente novamente"` e `data.valid` é `false`.

### 3.5 Login

```
POST /api/auth/login
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 10 vezes/minuto (por IP + rota)

**Corpo da requisição**:
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário |
| password | string | sim | min:6, max:32 | Senha |
| captcha_key | string | sim | | Chave do captcha |
| clicks | array{object} | sim | min:2 | Array de coordenadas de clique |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Login bem-sucedido",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "Administrador"
    }
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| access_token | string | Token de acesso JWT |
| refresh_token | string | Token de refresh JWT |
| expires_in | int | Validade do token de acesso (segundos), padrão 7200 |
| user.id | string | ID do usuário criptografado com hashid |
| user.username | string | Nome de usuário |
| user.real_name | string | Nome real |

**Possíveis erros**:
- 422: falha na validação de parâmetros (campos obrigatórios ausentes, formato incorreto)
- 422: captcha incorreto, tente novamente
- 401: nome de usuário ou senha incorretos
- 403: conta desativada
- 429: conta bloqueada, tente novamente em 15 minutos (acionado por 5 falhas consecutivas de login)

### 3.6 Registro

```
POST /api/auth/register
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: 5 vezes/minuto (por IP + rota)
- **Interruptor**: desativado por padrão (`REGISTRATION_ENABLED=0`); quando desativado, retorna 403; é necessário ativar explicitamente no `.env` (`REGISTRATION_ENABLED=1`)

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "Novo usuário",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (único) |
| password | string | sim | min:6, max:32 | Senha (armazenada com hash bcrypt) |
| real_name | string | sim | max:50 | Nome real |
| captcha_key | string | sim | | Chave do captcha |
| clicks | array{object} | sim | min:2 | Array de coordenadas de clique |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Registro bem-sucedido",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "Novo usuário"
    }
  }
}
```

Após o registro bem-sucedido, os tokens JWT são retornados diretamente e o usuário fica habilitado por padrão (status=1). Este endpoint está disponível apenas quando `REGISTRATION_ENABLED=1`.

### 3.7 Refresh do token

```
POST /api/auth/refresh
```

- **Autenticação**: não necessária
- **Cabeçalho**: `API-Version: v1` (obrigatório)
- **Rate limit**: padrão global (60 vezes/minuto)

**Corpo da requisição**:
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| refresh_token | string | sim | refresh_token obtido no login/registro |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

O refresh bem-sucedido retorna ao mesmo tempo novos access_token e refresh_token; o token antigo é invalidado automaticamente. Durante o refresh, o último horário de login e o IP do usuário são atualizados.

**Possíveis erros**:
- 422: falta o refresh token
- 401: refresh token inválido ou expirado

### 3.8 Métricas de monitoramento Prometheus

```
GET /metrics
```

- **Autenticação**: não necessária
- **Rate limit**: nenhum
- **Formato de resposta**: Prometheus text format (`text/plain; version=0.0.4`)

Endpoint público de métricas de monitoramento Prometheus, para coleta pelo Grafana/Prometheus.

**Exemplo de resposta**:
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nome da métrica | Tipo | Descrição |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Número total acumulado de requisições HTTP |
| `openadmin_active_users` | gauge | Número atual de usuários ativos (login nas últimas 24 horas) |
| `openadmin_db_connection_status` | gauge | Estado da conexão com o banco de dados, 1=normal, 0=anormal |
| `openadmin_redis_connection_status` | gauge | Estado da conexão com o Redis, 1=normal, 0=anormal |
| `openadmin_memory_usage_bytes` | gauge | Uso atual de memória do processo PHP (bytes) |

## 4. Dashboard

Todas as interfaces administrativas ficam no grupo `/admin` e passam por três middlewares: `AdminAuth` (autenticação JWT), `AdminPermission` (validação de permissão RBAC) e `OperationLog` (registro de operações).

### 4.1 Dados do dashboard

```
GET /admin/dashboard
```

- **Autenticação**: JWT + RBAC
- **Cache**: Redis por 5 minutos

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "Total de usuários",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "Novos hoje",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "Usuários ativos",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "Logs de operações",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "Usuários acumulados", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "Logs de operações", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "Habilitado", "value": 1250 },
        { "name": "Desabilitado", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "Login do usuário",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Campo de stats | Tipo | Descrição |
|------|------|------|
| label | string | Nome do indicador |
| value | string | Valor do indicador (tipo string) |
| icon | string | Nome do ícone Material |
| color | string | Cor do cartão |
| trend | float? | Taxa de crescimento diário em relação ao dia anterior (percentual); apenas "Total de usuários" tem este campo |

| Campo de trends | Tipo | Descrição |
|------|------|------|
| dates | array{string} | Sequência de datas dos últimos 30 dias |
| series | array{object} | Dados da linha de tendência, cada item contém name (nome), data (array de valores), color (cor) |

## 5. Gerenciamento de usuários

Todos os `id` retornados pelas interfaces de gerenciamento de usuários são strings criptografadas com hashid. O campo de senha já foi excluído das respostas. Telefone e e-mail aparecem mascarados nas interfaces de listagem e retornam em texto claro nas interfaces de detalhe (os campos criptografados no banco são descriptografados automaticamente pela trait Encryptable).

### 5.1 Lista de usuários

```
GET /admin/user
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| keyword | string | não | | Palavra-chave de busca, corresponde a nome de usuário e nome real |
| status | int | não | | Filtro de status, 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "Administrador",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID do usuário criptografado com hashid |
| username | string | Nome de usuário |
| real_name | string | Nome real |
| phone | string | Telefone mascarado (formato `138****5678`) |
| email | string | E-mail mascarado (formato `a***@example.com`) |
| status | int | 1=habilitado, 0=desabilitado |
| last_login_at | string | Último horário de login (datetime) |
| created_at | string | Data de criação (datetime) |

### 5.2 Criar usuário

```
POST /admin/user
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "Novo usuário",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| username | string | sim | min:3, max:50 | Nome de usuário (único) |
| password | string | sim | min:6, max:32 | Senha (armazenada com bcrypt) |
| real_name | string | sim | max:50 | Nome real |
| phone | string | não | | Telefone (armazenado criptografado com Encryptable) |
| email | string | não | | E-mail (armazenado criptografado com Encryptable) |
| status | int | não | in:0,1 | Status, padrão 1 (habilitado) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Criado com sucesso",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "Novo usuário",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Possíveis erros**:
- 422: nome de usuário já existe
- 422: falha na validação de parâmetros (campos obrigatórios ausentes)

### 5.3 Detalhes do usuário

```
GET /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Administrador",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

Na interface de detalhes, `phone` e `email` retornam em texto claro (no banco estão criptografados; o cast Encryptable descriptografa automaticamente), sem mascaramento. `password` e `id_card` nunca aparecem na resposta.

**Possíveis erros**:
- 404: usuário não existe

### 5.4 Atualizar usuário

```
PUT /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid

**Corpo da requisição**:
```json
{
  "real_name": "Novo nome",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real; se não enviado, mantém o valor original |
| password | string | não | Nova senha; string vazia ou não enviada significa não alterar |
| phone | string | não | Telefone |
| email | string | não | E-mail |
| status | int | não | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Atualizado com sucesso",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Novo nome",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Possíveis erros**:
- 404: usuário não existe

### 5.5 Excluir usuário

```
DELETE /admin/user/{id}
```

- **Autenticação**: JWT + RBAC
- **Parâmetro de caminho**: `{id}` é o ID do usuário criptografado com hashid
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Excluído com sucesso",
  "data": []
}
```

Executa soft delete (Eloquent SoftDeletes): os dados são marcados com deleted_at, sem exclusão física.

**Possíveis erros**:
- 404: usuário não existe
- 422: operação sensível exige senha de confirmação (password vazio)
- 422: falha na validação da senha (senha não confere)

### 5.6 Exclusão em lote de usuários

```
POST /admin/user/batch/destroy
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuário criptografados com hashid |
| password | string | sim | Senha do usuário atualmente logado (confirmação secundária) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Excluído com sucesso",
  "data": {
    "count": 2
  }
}
```

Executa soft delete; `data.count` é a quantidade efetivamente excluída.

**Possíveis erros**:
- 422: selecione os usuários a excluir (ids vazio)
- 422: ID inválido (falha na decodificação do hashid)
- 422: falha na validação da senha

### 5.7 Ativar/desativar usuários em lote

```
POST /admin/user/batch/status
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| ids | array{string} | sim | Array de IDs de usuário criptografados com hashid |
| status | int | sim | 0=desabilitado, 1=habilitado |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Ativação em lote bem-sucedida",
  "data": {
    "count": 2
  }
}
```

A `message` muda dinamicamente conforme o valor de status: `"Ativação em lote bem-sucedida"` ou `"Desativação em lote bem-sucedida"`.

**Possíveis erros**:
- 422: selecione os usuários (ids vazio)
- 422: valor de status inválido (status não é 0 nem 1)

## 6. Gerenciamento de papéis

### 6.1 Lista de papéis

```
GET /admin/role
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "Super administrador",
        "slug": "super_admin",
        "description": "Possui todas as permissões",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | ID do papel criptografado com hashid |
| name | string | Nome do papel |
| slug | string | Identificador do papel (único, usado para verificação de permissão) |
| description | string | Descrição do papel |
| status | int | 1=habilitado, 0=desabilitado |
| users_count | int | Número de usuários que possuem este papel |

### 6.2 Criar papel

```
POST /admin/role
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "Editor",
  "slug": "editor",
  "description": "Papel de edição de conteúdo",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| name | string | sim | max:50 | Nome do papel |
| slug | string | sim | max:50 | Identificador do papel |
| description | string | não | | Descrição do papel, padrão string vazia |
| status | int | não | | Status, padrão 1 |
| permission_ids | array{int} | não | | Array de IDs de permissão (IDs INT originais, não hashids) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Criado com sucesso",
  "data": {
    "id": "r5r6r7r8",
    "name": "Editor",
    "slug": "editor",
    "description": "Papel de edição de conteúdo",
    "status": 1
  }
}
```

### 6.3 Atualizar papel

```
PUT /admin/role/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "Editor de conteúdo",
  "description": "Descrição atualizada",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome do papel |
| description | string | não | Descrição |
| status | int | não | 0=desabilitado, 1=habilitado |
| permission_ids | array{int} | não | Array de IDs de permissão; se enviado, sincroniza (substitui) as permissões do papel |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Atualizado com sucesso",
  "data": {
    "id": "r5r6r7r8",
    "name": "Editor de conteúdo",
    "slug": "editor",
    "description": "Descrição atualizada",
    "status": 1
  }
}
```

### 6.4 Excluir papel

```
DELETE /admin/role/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Excluído com sucesso",
  "data": []
}
```

Ao excluir, as associações entre o papel e todas as permissões e usuários são removidas automaticamente e, em seguida, o registro do papel é excluído fisicamente.

## 7. Gerenciamento de permissões

As permissões usam estrutura em árvore (autoreferência por parent_id) e se dividem em três tipos. A interface de listagem retorna a árvore de permissões completa.

### 7.1 Árvore de permissões

```
GET /admin/permission
```

- **Autenticação**: JWT + RBAC

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "Gerenciamento de usuários",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "Lista de usuários",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | Criptografado com hashid |
| parent_id | string | Hashid da permissão pai; "0" indica nó raiz |
| name | string | Nome da permissão |
| slug | string | Identificador da permissão (identificador de rota/botão) |
| type | int | 1=menu, 2=botão, 3=interface |
| icon | string | Ícone do menu (nome de ícone Material) |
| path | string | Caminho de rota do frontend |
| sort | int | Valor de ordenação (crescente) |
| children | array? | Lista de permissões filhas (recursiva); omitido quando não há nós filhos |

### 7.2 Criar permissão

```
POST /admin/permission
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "parent_id": 0,
  "name": "Configuração do sistema",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| parent_id | int | não | | ID da permissão pai (tipo INT original), padrão 0 |
| name | string | sim | max:50 | Nome da permissão |
| slug | string | sim | max:100 | Identificador da permissão |
| type | int | sim | in:1,2,3 | 1=menu, 2=botão, 3=interface |
| icon | string | não | | Ícone do menu, padrão vazio |
| path | string | não | | Caminho de rota do frontend, padrão vazio |
| sort | int | não | | Valor de ordenação, padrão 0 |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Criado com sucesso",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "Configuração do sistema",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Atualizar permissão

```
PUT /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "name": "Configurações do sistema",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| name | string | não | Nome da permissão |
| icon | string | não | Ícone |
| path | string | não | Caminho de rota |
| sort | int | não | Valor de ordenação |

### 7.4 Excluir permissão

```
DELETE /admin/permission/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Excluído com sucesso",
  "data": []
}
```

Ao excluir, todas as permissões filhas são removidas em cascata (registros com `parent_id` = ID da permissão atual) e as associações com todos os papéis são removidas.

## 8. Configuração do sistema

A configuração do sistema é única pela combinação `group` + `key`.

### 8.1 Lista de configurações

```
GET /admin/config
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| group | string | não | | Filtro por grupo de configuração |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "Painel administrativo aberto",
        "type": "string",
        "description": "Nome do site",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| group | string | Grupo de configuração (como `system`, `email`, `storage`) |
| key | string | Chave de configuração |
| value | string | Valor de configuração |
| type | string | Dica de tipo de valor (`string`, `integer`, `boolean`, `json` etc.) |
| description | string | Descrição da configuração |

### 8.2 Criar configuração

```
POST /admin/config
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "Endereço do servidor SMTP"
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| group | string | sim | max:100 | Grupo de configuração |
| key | string | sim | max:100 | Chave de configuração (única dentro do grupo) |
| value | string | sim | | Valor de configuração |
| type | string | não | | Tipo de valor, padrão `string` |
| description | string | não | | Descrição da configuração, padrão vazio |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Criado com sucesso",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "Endereço do servidor SMTP"
  }
}
```

**Possíveis erros**:
- 422: item de configuração já existe (mesmo group + key)

### 8.3 Atualizar configuração

```
PUT /admin/config/{id}
```

- **Autenticação**: JWT + RBAC

**Corpo da requisição**:
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "Endereço SMTP atualizado"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| value | string | não | Atualiza o valor da configuração |
| type | string | não | Atualiza o tipo de valor |
| description | string | não | Atualiza o texto da descrição |

### 8.4 Excluir configuração

```
DELETE /admin/config/{id}
```

- **Autenticação**: JWT + RBAC
- **Operação sensível**: requer confirmação secundária de senha

**Corpo da requisição**:
```json
{
  "password": "admin_password"
}
```

Exclui fisicamente o registro de configuração.

## 9. Logs de operações

Os logs de operações são uma interface somente leitura; o middleware `OperationLog` grava automaticamente a cada requisição POST/PUT/DELETE, armazenando `user_id`, `action`, `method`, `path`, `ip`, `source` e `input`.

### 9.1 Lista de logs de operações

```
GET /admin/log
```

- **Autenticação**: JWT + RBAC

**Parâmetros de consulta**:

| Parâmetro | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| page | int | não | 1 | Número da página |
| limit | int | não | 15 | Itens por página |
| user_id | int | não | | Filtro exato por ID de usuário (tipo INT original) |
| action | string | não | | Filtro exato por ação |
| path | string | não | | Filtro difuso por caminho de requisição |
| start_date | string | não | | Data inicial (formato Y-m-d) |
| end_date | string | não | | Data final (formato Y-m-d) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "Login do usuário",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| id | string | hashid |
| user_name | string | Nome de usuário da operação (obtido pela associação user; operações sem login exibem "Sistema") |
| action | string | Descrição da ação |
| method | string | Método HTTP (POST/PUT/DELETE) |
| path | string | Caminho da requisição |
| ip | string | IP do cliente |
| source | string | Origem da requisição |
| input | string | String JSON dos parâmetros da requisição (sem arquivos) |
| created_at | string | Horário da operação (datetime) |

## 10. Centro pessoal

As interfaces do centro pessoal exigem apenas autenticação JWT (sem validação de permissão RBAC — o middleware `AdminPermission` deve incluí-las na lista de permissões).

### 10.1 Atualizar informações pessoais

```
PUT /admin/profile
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "real_name": "Novo nome",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| real_name | string | não | Nome real |
| phone | string | não | Telefone (armazenado criptografado com Encryptable) |
| email | string | não | E-mail (armazenado criptografado com Encryptable) |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Atualizado com sucesso",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "Novo nome",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Na resposta, `phone` e `email` retornam em texto claro; `password` e `id_card` são removidos.

### 10.2 Alterar senha

```
PUT /admin/profile/password
```

- **Autenticação**: JWT

**Corpo da requisição**:
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Campo | Tipo | Obrigatório | Regra de validação | Descrição |
|------|------|------|---------|------|
| old_password | string | sim | | Senha atual |
| new_password | string | sim | min:6, max:32 | Nova senha |

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Senha alterada com sucesso",
  "data": []
}
```

**Possíveis erros**:
- 422: informe a senha antiga e a nova senha
- 422: senha antiga incorreta
- 422: a nova senha deve ter de 6 a 32 caracteres

### 10.3 Logout

```
POST /admin/profile/logout
```

- **Autenticação**: JWT

**Corpo da requisição**: nenhum (sem requestBody; o token é lido do cabeçalho Authorization)

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Logout realizado",
  "data": []
}
```

Lógica de logout: decodifica o JWT para obter o tempo restante de validade (exp - now), grava o hash md5 do token na lista negra do Redis `jwt_blacklist:{md5}` com TTL = tempo restante de validade. Tokens na lista negra são bloqueados pelo middleware `AdminAuth`, retornando 401.

Sem token, retorna 401. Token expirado/inválido (exceção de decodificação) ainda é tratado como logout bem-sucedido.

## 11. Importação e exportação

### 11.1 Exportar Excel

```
POST /admin/export/excel
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corpo da requisição**:
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "Exportação da lista de usuários"
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| table | string | não | `admin_user` | Nome da tabela a exportar. Suporta: `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | não | | Array de nomes de colunas a exportar; vazio exporta todas as colunas da tabela |
| conditions | object | não | `{}` | Condições de filtro, pares chave-valor; valores não vazios são usados no WHERE |
| title | string | não | `Exportação de dados` | Título do Excel (exibido como nome da planilha) |

**Tabelas e colunas suportadas**:

| table | Colunas disponíveis |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Os campos sensíveis `phone`, `email` e `id_card` são mascarados automaticamente na exportação. Limite de 10000 linhas. Primeira linha congelada e filtro automático no Excel.

### 11.2 Exportar PDF

```
POST /admin/export/pdf
```

- **Autenticação**: JWT + RBAC
- **Tipo de resposta**: download de arquivo (`application/pdf`, A4 paisagem)

**Corpo da requisição**:
```json
{
  "type": "dashboard",
  "title": "Relatório do painel administrativo",
  "data": {
    "stats": [
      { "label": "Total de usuários", "value": "1280" }
    ]
  }
}
```

Ou modo tabela:
```json
{
  "type": "table",
  "title": "Lista de usuários",
  "data": {
    "columns": ["Nome de usuário", "Nome real", "Status"],
    "rows": [
      ["admin", "Administrador", "Habilitado"],
      ["editor", "Editor", "Habilitado"]
    ]
  }
}
```

| Campo | Tipo | Obrigatório | Valor padrão | Descrição |
|------|------|------|------|------|
| type | string | não | `table` | Tipo de exportação: `table` / `dashboard` |
| title | string | não | `Exportação de dados` | Título do PDF |
| data | object | não | `{}` | Dados a exportar |

Com `type=dashboard`, `data` deve conter o array `stats` (renderizado como cartões); com `type=table`, `data` deve conter os arrays `columns` e `rows`.

O modelo do PDF inclui informações de direitos autorais e timestamp de exportação.

### 11.3 Importar usuários (Excel)

```
POST /admin/import/users
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data` (upload de arquivo)

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Formato `.xlsx` ou `.xls` |

**Requisitos das colunas do Excel**:

| Nome da coluna | Obrigatório | Descrição |
|------|------|------|
| username | sim | Nome de usuário (único) |
| password | sim | Senha (armazenada com hash bcrypt) |
| real_name | sim | Nome real |
| phone | não | Telefone |
| email | não | E-mail |
| status | não | Status, padrão 1 |

A linha 1 contém os cabeçalhos das colunas (sem distinção de maiúsculas/minúsculas); os dados começam na linha 2.

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Importação concluída",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "Nome de usuário vazio" },
      { "row": 7, "reason": "O nome de usuário zhangsan já existe" }
    ]
  }
}
```

| Campo | Tipo | Descrição |
|------|------|------|
| total | int | Número total de linhas (sem a linha de cabeçalho) |
| success | int | Número de importações bem-sucedidas |
| failed | int | Número de falhas |
| errors | array | Detalhes das falhas, cada item contém row (número da linha no Excel) e reason (motivo da falha) |

## 12. Upload de arquivos

```
POST /admin/upload
```

- **Autenticação**: JWT + RBAC
- **Tipo de requisição**: `multipart/form-data`

**Campos do formulário**:

| Campo | Tipo | Obrigatório | Descrição |
|------|------|------|------|
| file | file | sim | Arquivo a enviar |

**Tipos de arquivo permitidos**: `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Tamanho máximo do arquivo**: 10MB

**Exemplo de resposta**:
```json
{
  "code": 0,
  "message": "Upload bem-sucedido",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Os arquivos são armazenados em diretórios por data em `public/upload/{Y-m-d}/`, com nome `md5(uniqid) + extensão original`. `url` é um caminho relativo à raiz do site.

**Possíveis erros**:
- 422: selecione um arquivo (não enviado)
- 422: tipo de arquivo não suportado
- 422: o tamanho do arquivo não pode exceder 10MB
- 500: falha no upload (arquivo inválido)

## 13. Cabeçalhos de resposta

Todas as interfaces (injetadas pela camada de middlewares globais) incluem os seguintes cabeçalhos de resposta:

| Cabeçalho | Descrição |
|----|------|
| `X-RateLimit-Limit` | Limite do rate limit (quantidade) |
| `X-RateLimit-Remaining` | Requisições restantes |
| `X-RateLimit-Reset` | Timestamp de reset da janela do rate limit |
| `Retry-After` | Retornado apenas quando o rate limit é acionado; segundos sugeridos de espera |
| `X-Content-Type-Options` | `nosniff` (padrão do webman; proíbe MIME sniffing) |
| `X-Frame-Options` | `DENY` (fornecido pelo middleware CORS/configuração base do webman) |

Detalhes do rate limit:
- Limite global padrão: 60 vezes/minuto / IP+rota
- Endpoint de login `/api/auth/login`: 10 vezes/minuto
- Endpoint de registro `/api/auth/register`: 5 vezes/minuto
- Usa o algoritmo de janela deslizante atômico do Redis (Lua ZSET), evitando corrida TOCTOU
- Quando o Redis está indisponível, fail open (deixa passar), sem bloquear requisições

## 14. Fluxo de autenticação

Sequência completa de autenticação:

```
1. Cliente solicita POST /api/captcha/generate
   (Cabeçalho: API-Version: v1)
    ↓
   Servidor retorna: key + imagem base64 + instruções dos alvos de clique

2. Usuário clica nas posições dos alvos na imagem; o front/cliente coleta as coordenadas

3. Cliente solicita POST /api/auth/login
   (Cabeçalhos: API-Version: v1, Content-Type: application/json)
   Corpo: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   Servidor:
   a. Validação de parâmetros → 422
   b. Validação do captcha → 422
   c. Validação das credenciais do usuário → 401
   d. Verificação do status da conta → 403
   e. Emissão do JWT (access + refresh) → 200
   f. Atualização de last_login_at / last_login_ip
    ↓
   Cliente salva: access_token, refresh_token, expires_in

4. Requisições subsequentes carregam o JWT
   Cabeçalho: Authorization: Bearer <access_token>
    ↓
   Middleware AdminAuth:
   a. Extrai o Bearer token
   b. Verifica a lista negra (Redis jwt_blacklist:{md5}) → 401
   c. Decodifica o JWT e valida a expiração → 401
   d. Define $request->adminId = campo sub
    ↓
   Middleware AdminPermission:
   a. Resolve o identificador de permissão da rota de recurso
   b. Consulta os papéis do usuário → permissões dos papéis e faz a correspondência
   c. Sem permissão → 403
    ↓
   Controller processa a requisição
    ↓
   Response + cabeçalhos X-RateLimit-*

5. Refresh antes da expiração do Access Token
   Cliente solicita POST /api/auth/refresh
   Corpo: { refresh_token: "..." }
    ↓
   Servidor decodifica o refresh_token → emite novos access + refresh
    ↓
   Cliente atualiza os tokens locais

6. Logout
   Cliente solicita POST /admin/profile/logout
   Cabeçalho: Authorization: Bearer <access_token>
    ↓
   Servidor:
   a. Decodifica o JWT e obtém o TTL restante
   b. Grava na lista negra do Redis: jwt_blacklist:{md5(token)} = 1, TTL = validade restante
   c. Retorna sucesso
```

### Estrutura do JWT

- **access_token**: `{ sub: <user_id>, username: "<name>" }`, TTL padrão de 7200 segundos (controlado por `default_expire` na configuração do JWT)
- **refresh_token**: `{ sub: <user_id>, token_type: "refresh" }`, TTL padrão de 1209600 segundos (controlado por `refresh_expire` na configuração do JWT, ou seja, 14 dias)

### Gestão de segurança

- Senhas armazenadas com hash `PASSWORD_BCRYPT`
- Campos sensíveis (phone, email, id_card) usam `erikwang2013/encryptable` para criptografia/descriptografia transparente na camada de banco de dados
- IDs na camada de API usam `erikwang2013/hashids` para transmissão criptografada, evitando expor a sequência de IDs snowflake originais
- O SecurityFilter examina globalmente XSS, injeção SQL, path traversal e injeção de comandos; mesmo IP com 5 detecções/60 segundos entra na lista negra temporária por 15 minutos
- Operações sensíveis (excluir usuário, papel, permissão, configuração) exigem confirmação secundária com a senha do usuário logado
- Limite de sessões simultâneas: no máximo 3 Tokens válidos por usuário; o login de um 4º dispositivo força o Token mais antigo para a lista negra
- Bloqueio de conta: 5 falhas consecutivas de login acionam bloqueio de 15 minutos; durante o bloqueio, retorna 429

## 15. Implantação e operações

### Docker Compose

A raiz do projeto fornece `docker-compose.yml`, orquestrando 5 serviços (Nginx, app webman, MySQL, Redis, Elasticsearch). O PHP é construído via `Dockerfile` (baseado em `php:8.3-cli`, com OPcache habilitado).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` define o pipeline de integração contínua do GitHub Actions:
- Verificação de sintaxe `php -l`
- Testes de unidade PHPUnit
- Análise estática `flutter analyze`

### Backup do banco de dados

O diretório `database/backup/` fornece scripts de backup e restauração:
- `backup.sh` — backup compactado com mysqldump + gzip, limpeza automática de backups com mais de 30 dias
- `restore.sh` — restauração interativa, lista os backups existentes para o usuário escolher

### Configuração de segurança do Nginx

Para implantação em produção, consulte `nginx-security.conf` para o reforço de segurança do reverse proxy.

## 16. Endpoints da API de negócio (ERP)

Todos os endpoints de negócio ficam no grupo `/admin` e passam por três middlewares: `AdminAuth` (autenticação JWT), `AdminPermission` (validação de permissão RBAC) e `OperationLog` (registro de operações).

> Total de endpoints: produtos(17) | compras(8) | vendas(6) | estoque(6) | finanças(17) | CRM(13) | workflow(6) | notificações(4) | projetos(3) | RH(9) | manufatura(7) | relatórios(4) | dashboards(3) | clientes(2) | total de 105 endpoints

Endpoints de integração entre módulos são marcados com 🔗.

### 16.1 Gestão de produtos (Product Management)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/product | Lista de produtos (paginação + busca + filtro de categoria/status) |
| POST | /admin/product | Criar produto (inclui SKU e preços) |
| GET | /admin/product/{id} | Detalhes do produto (inclui categoria/marca/SKU/preços/unidade) |
| PUT | /admin/product/{id} | Atualizar produto |
| DELETE | /admin/product/{id} | Excluir produto (soft delete, requer confirmação de senha) |
| GET | /admin/category | Lista de categorias (árvore) |
| POST | /admin/category | Criar categoria |
| PUT | /admin/category/{id} | Atualizar categoria |
| DELETE | /admin/category/{id} | Excluir categoria |
| GET | /admin/brand | Lista de marcas |
| POST | /admin/brand | Criar marca |
| GET | /admin/warehouse | Lista de armazéns |
| POST | /admin/warehouse | Criar armazém |
| GET | /admin/location | Lista de localizações |
| GET | /admin/warehouse/{id}/locations | Localizações de um armazém |
| GET | /admin/supplier | Lista de fornecedores (busca ES) |
| POST | /admin/supplier | Criar fornecedor |
| GET | /admin/customer | Lista de clientes (busca ES) |
| POST | /admin/customer | Criar cliente |

### 16.2 Gestão de compras (Purchase)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/purchase/apply | Lista de solicitações de compra |
| POST | /admin/purchase/apply | Criar solicitação de compra |
| GET | /admin/purchase/order | Lista de pedidos de compra |
| POST | /admin/purchase/order | Criar pedido de compra |
| 🔗 POST | /admin/purchase/receive | Criar recibo de recebimento (entrada automática no estoque + geração de contas a pagar) |
| GET | /admin/purchase/receive | Lista de recibos de recebimento |
| GET | /admin/purchase/receive/{id} | Detalhes do recibo de recebimento |
| POST | /admin/purchase/return | Criar nota de devolução |
| GET | /admin/purchase/settlement | Lista de liquidações com fornecedores |

### 16.3 Gestão de vendas (Sales)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/sales/quotation | Lista de cotações |
| POST | /admin/sales/quotation | Criar cotação |
| GET | /admin/sales/order | Lista de pedidos de venda |
| POST | /admin/sales/order | Criar pedido de venda |
| 🔗 POST | /admin/sales/delivery | Criar nota de expedição (saída automática do estoque + geração de contas a receber) |
| GET | /admin/sales/delivery | Lista de notas de expedição |
| GET | /admin/sales/settlement | Lista de liquidações com clientes |

### 16.4 Gestão de estoque (Inventory)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/inventory | Estoque em tempo real (dimensões armazém/localização/lote/SKU) |
| GET | /admin/inventory/flow | Fluxo de entrada/saída |
| GET | /admin/inventory/transfer | Lista de transferências |
| POST | /admin/inventory/transfer | Criar transferência |
| GET | /admin/inventory/check | Lista de tarefas de inventário |
| POST | /admin/inventory/check | Criar tarefa de inventário |
| GET | /admin/inventory/alert | Regras de alerta de estoque |

### 16.5 Gestão financeira (Finance)

| Método | Caminho | Descrição |
|------|------|------|
| POST | /admin/finance/voucher | Criar lançamento contábil |
| GET | /admin/finance/ar-ap | Lista de contas a receber/a pagar |
| POST | /admin/finance/receipt | Criar recibo de recebimento |
| POST | /admin/finance/payment | Criar recibo de pagamento |
| GET | /admin/finance/cash-journal | Diário de caixa e bancos |
| GET | /admin/finance/expense | Lista de reembolsos de despesas |
| POST | /admin/finance/expense | Enviar solicitação de reembolso |
| GET | /admin/finance/report/profit | Demonstração de resultados |
| GET | /admin/finance/general-ledger | Razão geral (resumo por conta + período) |
| GET | /admin/finance/subsidiary-ledger | Razão auxiliar (detalhes item a item por conta) |
| GET | /admin/finance/report/balance-sheet | Balanço patrimonial (inclui geração automática) |
| GET | /admin/finance/report/cash-flow | Demonstração de fluxo de caixa (operação/investimento/financiamento) |
| GET | /admin/finance/bank-account | Lista de contas bancárias |
| GET/POST/PUT/DELETE | /admin/finance/asset | CRUD de ativo imobilizado + provisionamento de depreciação |
| GET/POST | /admin/finance/tax-rate | Configuração de alíquotas de impostos |
| GET | /admin/finance/tax-record | Registros fiscais |
| GET/POST/PUT/DELETE | /admin/finance/currency | Gestão de moedas |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | Gestão de câmbio |
| GET/POST/PUT/DELETE | /admin/finance/budget | Gestão orçamentária (inclui comparação orçamento vs. realizado) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | Centro de custo (estrutura em árvore) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | Centro de lucro (estrutura em árvore) |

### 16.6 CRM

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/crm/opportunity | Lista de oportunidades |
| POST | /admin/crm/opportunity | Criar oportunidade |
| GET | /admin/crm/follow | Lista de registros de acompanhamento |
| POST | /admin/crm/follow | Criar registro de acompanhamento |
| GET | /admin/crm/funnel | Configuração dos estágios do funil |
| GET | /admin/crm/contact | Lista de contatos |
| POST | /admin/crm/contact | Criar contato |
| GET | /admin/crm/pool | Lista de clientes do pool público |
| POST | /admin/crm/pool/claim/{id} | Reivindicar cliente do pool público |
| POST | /admin/crm/pool/release/{id} | Liberar cliente para o pool público |
| GET/POST | /admin/crm/pool/rules | CRUD de regras do pool público |
| GET | /admin/crm/contract | Lista de contratos |
| POST | /admin/crm/contract | Criar contrato |
| GET | /admin/crm/contract/{id} | Detalhes do contrato |
| PUT | /admin/crm/contract/{id} | Atualizar contrato |
| DELETE | /admin/crm/contract/{id} | Excluir contrato |
| GET | /admin/crm/quotation | Lista de cotações do CRM |
| POST | /admin/crm/quotation | Criar cotação no CRM |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 Cotação vira contrato |
| GET/POST/PUT/DELETE | /admin/crm/campaign | Campanhas de marketing |
| GET/POST/PUT/DELETE | /admin/crm/ticket | Tickets de serviço |
| POST | /admin/crm/ticket/{id}/assign | Atribuir ticket |
| POST | /admin/crm/ticket/{id}/resolve | Resolver ticket |
| GET/POST | /admin/crm/analytics/report | Relatórios analíticos de clientes |
| GET/POST | /admin/crm/analytics/metric | Métricas analíticas |

### 16.7 Fluxo de aprovação (Workflow)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/workflow | Lista de definições de workflow |
| POST | /admin/workflow | Criar definição de workflow |
| GET | /admin/workflow/{id} | Detalhes do workflow |
| PUT | /admin/workflow/{id} | Atualizar workflow |
| DELETE | /admin/workflow/{id} | Excluir workflow |
| POST | /admin/workflow/{id}/submit | 🔗 Enviar para aprovação (cria instância de aprovação) |
| POST | /admin/approval/{id}/approve | Aprovar |
| POST | /admin/approval/{id}/reject | Rejeitar |
| POST | /admin/approval/{id}/withdraw | Retirar |
| ANY | /admin/approval/my | Minhas aprovações (pendentes/aprovadas) |

### 16.8 Notificações (Notification)

| Método | Caminho | Descrição |
|------|------|------|
| ANY | /admin/notification/my | Minhas notificações (paginação, ordem cronológica inversa) |
| POST | /admin/notification/{id}/read | Marcar uma como lida |
| POST | /admin/notification/read-all | Marcar todas como lidas |
| ANY | /admin/notification/unread-count | Quantidade de mensagens não lidas |

### 16.9 Gestão de projetos (Project)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/project | Lista de projetos |
| POST | /admin/project | Criar projeto |
| GET | /admin/project/{id} | Detalhes do projeto |
| PUT | /admin/project/{id} | Atualizar projeto |
| DELETE | /admin/project/{id} | Excluir projeto |
| GET | /admin/project/task | Lista de tarefas |
| POST | /admin/project/task | Criar tarefa |
| PUT | /admin/project/task/{id} | Atualizar tarefa |
| DELETE | /admin/project/task/{id} | Excluir tarefa |
| GET | /admin/project/timesheet | Lista de registros de horas |
| POST | /admin/project/timesheet | Registrar horas |
| PUT | /admin/project/timesheet/{id} | Atualizar horas |
| DELETE | /admin/project/timesheet/{id} | Excluir horas |

### 16.10 Gestão de recursos humanos (HR)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/hr/department | Lista de departamentos (árvore) |
| POST | /admin/hr/department | Criar departamento |
| PUT | /admin/hr/department/{id} | Atualizar departamento |
| DELETE | /admin/hr/department/{id} | Excluir departamento |
| GET | /admin/hr/employee | Lista de funcionários |
| POST | /admin/hr/employee | Criar funcionário |
| PUT | /admin/hr/employee/{id} | Atualizar funcionário |
| DELETE | /admin/hr/employee/{id} | Excluir funcionário |
| GET | /admin/hr/position | Lista de cargos |
| POST | /admin/hr/position | Criar cargo |
| PUT | /admin/hr/position/{id} | Atualizar cargo |
| DELETE | /admin/hr/position/{id} | Excluir cargo |
| ANY | /admin/hr/attendance | Consulta de registros de ponto |
| POST | /admin/hr/attendance/clock-in | Registrar entrada |
| POST | /admin/hr/attendance/clock-out | Registrar saída |
| ANY | /admin/hr/leave | Lista de licenças/afastamentos |
| POST | /admin/hr/leave | Enviar solicitação de licença |
| GET | /admin/hr/leave/{id} | Detalhes da licença |
| PUT | /admin/hr/leave/{id} | Atualizar licença |
| DELETE | /admin/hr/leave/{id} | Excluir licença |
| POST | /admin/hr/leave/{id}/approve | 🔗 Aprovar licença |
| GET | /admin/hr/salary | Lista de salários |
| POST | /admin/hr/salary | Gerar folha de pagamento |
| PUT | /admin/hr/salary/{id} | Atualizar salário |
| DELETE | /admin/hr/salary/{id} | Excluir salário |
| POST | /admin/hr/salary/{id}/pay | Pagar salário |
| ANY | /admin/hr/salary-item | Lista de itens salariais |
| POST | /admin/hr/salary-item | Criar item salarial |
| GET | /admin/hr/salary-item/{id} | Detalhes do item salarial |
| PUT | /admin/hr/salary-item/{id} | Atualizar item salarial |
| DELETE | /admin/hr/salary-item/{id} | Excluir item salarial |

### 16.11 Manufatura (Manufacturing)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/mfg/bom | Lista de BOM |
| POST | /admin/mfg/bom | Criar BOM |
| PUT | /admin/mfg/bom/{id} | Atualizar BOM |
| DELETE | /admin/mfg/bom/{id} | Excluir BOM |
| GET | /admin/mfg/production | Lista de ordens de produção |
| POST | /admin/mfg/production | Criar ordem de produção |
| PUT | /admin/mfg/production/{id} | Atualizar ordem de produção |
| DELETE | /admin/mfg/production/{id} | Excluir ordem de produção |
| POST | /admin/mfg/production/{id}/start | Iniciar produção |
| POST | /admin/mfg/production/{id}/complete | Concluir produção |
| GET | /admin/mfg/routing | Lista de roteiros de processo |
| POST | /admin/mfg/routing | Criar roteiro |
| PUT | /admin/mfg/routing/{id} | Atualizar roteiro |
| DELETE | /admin/mfg/routing/{id} | Excluir roteiro |
| GET | /admin/mfg/workstation | Lista de postos de trabalho |
| POST | /admin/mfg/workstation | Criar posto de trabalho |
| PUT | /admin/mfg/workstation/{id} | Atualizar posto de trabalho |
| DELETE | /admin/mfg/workstation/{id} | Excluir posto de trabalho |
| GET | /admin/mfg/mrp | Lista de planos MRP |
| POST | /admin/mfg/mrp | Criar plano MRP |
| PUT | /admin/mfg/mrp/{id} | Atualizar plano MRP |
| DELETE | /admin/mfg/mrp/{id} | Excluir plano MRP |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 Executar MRP e gerar sugestões de compra/produção |

### 16.12 Relatórios personalizados (Report Builder)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/report | Lista de modelos de relatório |
| POST | /admin/report | Criar modelo de relatório |
| GET | /admin/report/{id} | Detalhes do modelo de relatório |
| PUT | /admin/report/{id} | Atualizar modelo de relatório |
| DELETE | /admin/report/{id} | Excluir modelo de relatório |
| POST | /admin/report/{id}/execute | Executar relatório e gerar dados |
| ANY | /admin/report/{id}/result | Resultado da execução do relatório |
| GET | /admin/report/schedule | Lista de agendamentos |
| POST | /admin/report/schedule | Criar agendamento |
| PUT | /admin/report/schedule/{id} | Atualizar agendamento |
| DELETE | /admin/report/schedule/{id} | Excluir agendamento |

### 16.13 Dashboards (Dashboard)

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/dashboard/sales | Painel de vendas |
| GET | /admin/dashboard/inventory | Painel de estoque |
| GET | /admin/dashboard/finance | Painel financeiro |

### 16.14 API do cliente (Client API)

As interfaces do cliente ficam no grupo `/api` e exigem o cabeçalho `API-Version`. As informações de produto não incluem preço de custo.

| Método | Caminho | Descrição |
|------|------|------|
| GET | /api/product | Lista de produtos (sem preço de custo) |
| GET | /api/product/{hashid} | Detalhes do produto (inclui preços de varejo/atacado, sem preço de custo) |

### 16.15 Gestão de pedidos OMS

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/oms/order | Lista de pedidos OMS |
| POST | /admin/oms/order | Criar pedido OMS |
| 🔗 POST | /admin/oms/order/{id}/allocate | Alocação de estoque (reserva) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | Criar atendimento |
| POST | /admin/oms/order/{id}/cancel | Cancelar pedido (libera reserva) |
| POST | /admin/oms/rma/{id}/approve | Aprovar RMA |
| POST | /admin/oms/rma/{id}/refund | Reembolso de RMA |

### 16.16 Gestão de armazém WMS

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/wms/zone | Lista de zonas (CRUD) |
| GET | /admin/wms/location | Lista de localizações WMS (CRUD) |
| GET | /admin/wms/asn | Lista de ASN (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | Concluir recebimento → geração automática de tarefa de putaway |
| POST | /admin/wms/putaway/{id}/complete | Confirmar putaway → aciona stockIn |
| POST | /admin/wms/wave/{id}/release | Liberar onda → gera tarefas de picking |
| POST | /admin/wms/pick/{id}/start | Iniciar picking |
| POST | /admin/wms/pick/{id}/confirm | Confirmar picking |
| POST | /admin/wms/pack/{id}/complete | Embalagem concluída |

### 16.17 Gestão de transporte TMS

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/tms/carrier | Lista de transportadoras (CRUD) |
| GET | /admin/tms/service | Serviços das transportadoras (CRUD) |
| GET | /admin/tms/freight-rate | Tarifas de frete (CRUD) |
| GET | /admin/tms/shipment | Lista de conhecimentos de transporte (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | Confirmar expedição (stockOut+AR) |
| POST | /admin/tms/tracking/callback | Webhook de rastreamento da transportadora |
| POST | /admin/tms/freight-invoice/{id}/pay | Pagamento de fatura de frete (gera AP) |

### 16.18 Extensões de dashboards

| Método | Caminho | Descrição |
|------|------|------|
| GET | /admin/dashboard/oms | KPIs do OMS (pendentes/em picking/enviados hoje/RMA) |
| GET | /admin/dashboard/wms | KPIs do WMS (aguardando recebimento/putaway/picking/embalagem) |
| GET | /admin/dashboard/tms | KPIs do TMS (aguardando envio/em trânsito/entregues/anomalias) |

### 16.19 Integração entre módulos

Os seguintes endpoints acionam integração automática entre módulos, marcados com 🔗:

| Endpoint | Ação de integração |
|------|---------|
| 🔗 POST /admin/purchase/receive | Chama automaticamente InventoryService.stockIn() para atualizar o estoque e recalcular o custo médio móvel ponderado; chama FinanceService.createAp() para gerar registros de contas a pagar |
| 🔗 POST /admin/sales/delivery | Chama automaticamente InventoryService.stockOut() para baixar o estoque (pelo custo médio móvel ponderado); chama FinanceService.createAr() para gerar registros de contas a receber |
