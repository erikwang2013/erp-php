# Subprojeto A: Aprimoramento do backend — Especificação de design

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Escopo

Esta é a melhoria do backend, com 15 pontos de funcionalidade no total, envolvendo 9 arquivos novos + 4 arquivos modificados.

---

## Lista de arquivos novos/modificados

```
app/middleware/
├── OperationLog.php          # 新增：操作日志自动记录
├── Cors.php                  # 新增：跨域
└── RateLimit.php             # 新增：Redis 限流
app/admin/controller/
├── ConfigController.php      # 新增：系统配置 CRUD
├── LogController.php         # 新增：操作日志查询
├── ProfileController.php     # 新增：个人中心（含登出）
├── UploadController.php      # 新增：文件上传
├── ImportController.php      # 新增：Excel 导入用户
└── HealthController.php      # 新增：健康检查
app/model/
├── AdminUser.php             # 修改：加 SoftDeletes + Searchable trait
└── OperationLog.php          # 修改：加 public $timestamps = false
app/middleware/
└── AdminAuth.php             # 修改：JWT 黑名单校验
app/admin/controller/
├── DashboardController.php   # 修改：改为数据库实时统计
└── UserController.php        # 修改：新增批处理动作
config/
└── route.php                 # 修改：新增路由 + 中间件
```

---

## 1. Middlewares

### 1.1 Middleware CORS

**Arquivo**: `app/middleware/Cors.php`

- Requisições de pré-verificação OPTIONS retornam 204 diretamente
- Requisições sem pré-verificação recebem `Access-Control-Allow-Origin: *` no cabeçalho da resposta
- Cabeçalhos permitidos: `Authorization, Content-Type, API-Version`
- Cache máximo: 86400 segundos

Montagem: middleware global (`config/middleware.php`)

### 1.2 Middleware de limite de taxa

**Arquivo**: `app/middleware/RateLimit.php`

- Armazenamento: janela deslizante Redis Sorted Set
- Padrão: 60 vezes/minuto/IP/rota
- Endpoints sensíveis:
  - `/api/auth/login`: 10 vezes/minuto
  - `/api/auth/register`: 5 vezes/minuto
- Excedido retorna `429 Too Many Requests`

Montagem: middleware global (`config/middleware.php`), depois do Cors e antes do ApiVersion

### 1.3 Middleware de log de operações

**Arquivo**: `app/middleware/OperationLog.php`

- Registra apenas POST/PUT/DELETE
- Campos registrados: user_id, action, method, path, ip, input(JSON)
- Escrita assíncrona após o retorno da resposta (sem bloqueio)

Montagem: grupo de rotas `/admin`, depois do AdminPermission

### 1.4 Cadeia de execução de middlewares globais

```
所有请求:
  Cors → RateLimit → ApiVersion → {Route 中间件} → Controller

/admin/* 请求:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Logout (lista negra JWT)

**Arquivo**: `app/middleware/AdminAuth.php` (modificado)

**Princípio**: o JWT em si não tem estado; no logout o token é adicionado à lista negra do Redis, e o AdminAuth consulta a lista negra antes de validar.

**Reforma do AdminAuth**:
- No início de `process()`: verificar a partir do conjunto `jwt_blacklist` do Redis se o token atual está na lista negra
- Se estiver na lista negra, retornar 401

**Rota de logout** (sob o centro pessoal):

| Método | Rota | Observação |
|------|------|------|
| `POST` | `/admin/profile/logout` | Adiciona o token Bearer atual à lista negra do Redis, TTL=tempo restante de validade do token |

**Lógica do Logout**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Novos controllers e reforma dos existentes

### 2.1 CRUD de configuração do sistema (`ConfigController`)

Herda de `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `index()` | GET `/admin/config` | Lista paginada, filtrável por `group`, paginação `page`/`limit` |
| `store()` | POST `/admin/config` | Cria item de configuração; obrigatórios: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Atualiza value/type/description do item |
| `destroy()` | DELETE `/admin/config/{id}` | Exclui item, requer `confirmPassword()` |

### 2.2 Consulta de log de operações (`LogController`)

Herda de `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `index()` | GET `/admin/log` | Lista paginada, com filtros: user_id, action, path, created_at (intervalo) |

Sem criação/edição/exclusão; os logs são registrados automaticamente pelo middleware.

### 2.3 Centro pessoal (`ProfileController`)

Herda de `BaseController`. Opera o usuário logado atualmente (`$request->adminId`).

| Método | Rota | Observação |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Atualiza real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Altera a senha; requer old_password, new_password, new_password_confirmation |

### 2.4 Upload de arquivos (`UploadController`)

Herda de `BaseController`.

| Método | Rota | Observação |
|------|------|------|
| `upload()` | POST `/admin/upload` | Recebe o arquivo; suporta image/jpeg/png/gif/pdf/xlsx/docx |

- Máximo de 10MB
- Caminho de armazenamento: `public/upload/{date}/{hash}.{ext}`
- Retorna: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Dados reais do dashboard

**Arquivo**: `app/admin/controller/DashboardController.php` (modificado)

Substituir os dados falsos atualmente codificados por estatísticas em tempo real do banco:

| Métrica | Origem | Observação |
|------|------|------|
| Total de usuários | `AdminUser::count()` | Sem soft deletes |
| Novos hoje | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Total de papéis | `AdminRole::count()` | |
| Total de permissões | `AdminPermission::count()` | |
| Dados de tendência | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Novos por dia nos últimos 7 dias |
| Dados de distribuição | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Distribuição por status |
| Operações recentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Últimas 10 entradas de log de operações |

### 2.6 Operações em lote de usuários

**Arquivo**: `app/admin/controller/UserController.php` (modificado, novos métodos)

| Método | Rota | Observação |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Exclusão em lote, corpo `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Habilitar/desabilitar em lote, corpo `{ ids: [hashid, ...], status: 1|0 }` |

- Cada id passa primeiro por `decodeId()` para converter em BIGINT
- `batchDestroy()` deve ser validado por `confirmPassword()`

### 2.7 Importação de dados

**Arquivo**: `app/admin/controller/ImportController.php` (novo)

| Método | Rota | Observação |
|------|------|------|
| `users()` | POST `/admin/import/users` | Envia arquivo Excel e cria usuários em lote |

Fluxo:
1. Receber o arquivo `.xlsx`
2. Análise com PhpSpreadsheet; colunas esperadas: `username, password, real_name, phone, email, status`
3. Validação linha a linha + criação (ID via snowflake, senha com bcrypt, phone/email criptografados com encryption)
4. Retorna o resultado: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Verificação de saúde

**Arquivo**: `app/admin/controller/HealthController.php` (novo)

`GET /health` (sem autenticação, não entra no log de operações):

Retorna o status de conexão de cada componente:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- Quando a detecção de um componente falha, o valor do campo correspondente é a descrição do erro
- A rota não usa o prefixo `/admin`; é registrada separadamente no escopo global

---

## 3. Correções de modelos

### 3.1 Timestamps do OperationLog

**Arquivo**: `app/model/OperationLog.php` (modificado)

A tabela `erik_operation_log` tem apenas a coluna `created_at` (sem `updated_at`). O `save()` padrão do Eloquent tenta escrever `updated_at`, causando erro de SQL.

Correção: `public $timestamps = false;` + especificar `created_at` manualmente na escrita.

### 3.2 Reforma do modelo AdminUser

- Adicionar trait `Searchable`
- Implementar `toSearchableArray()`: retorna username, real_name
- Quando o `UserController::index()` detecta palavra-chave, usa `AdminUser::search($kw)->get()` em vez de LIKE do MySQL

O ES precisa primeiro criar o índice; pode ser feito pelo comando do Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Alterações de rotas

Novas rotas em `config/route.php`:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

`config/middleware.php` registra os middlewares globais:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Complemento de códigos de erro

| code | Significado | Cenário de acionamento |
|------|------|---------|
| 429 | Requisições em excesso | Acionado pelo RateLimit |

---

## 6. Fora do escopo desta rodada

- Sistema de notificações (requer fila de mensagens + infraestrutura de push no frontend)
- Páginas de frontend Flutter (subprojeto B)
- Refresh de Token no HarmonyOS (subprojeto C)
