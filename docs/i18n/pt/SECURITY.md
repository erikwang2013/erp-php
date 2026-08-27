# Documento de design de arquitetura de segurança

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Visão geral da defesa em profundidade

O sistema usa um modelo de defesa em profundidade de 7 camadas, filtrando requisições maliciosas camada por camada, de fora para dentro, garantindo que, se qualquer camada falhar, as camadas seguintes continuem protegendo.

Toda a cadeia de middlewares é executada na seguinte ordem (ver `config/middleware.php`):

```
Requisição → Cors → SecurityFilter → RateLimit → [middlewares do grupo de rotas: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Camada | Middleware/mecanismo | Alvo de proteção |
|----|--------|---------|
| 1 | SecurityFilter | Interceptação de XSS / injeção SQL / path traversal / injeção de comando / ataques CSRF |
| 2 | Cors | Segurança de cross-origin + injeção de cabeçalhos de segurança de resposta |
| 3 | RateLimit | Rate limit de janela deslizante Redis, proteção contra força bruta |
| 4 | AdminAuth | Autenticação JWT + logout com blacklist |
| 5 | AdminPermission | Autorização RBAC com granularidade method.path |
| 6 | OperationLog | Auditoria de operações + rastreamento da plataforma de origem |
| 7 | Criptografia de dados | Ofuscação de ID Hashids + criptografia de banco Encryptable + criptografia de transporte EncryptionService |

O frontend em três camadas (Flutter) tem validação de entrada própria e independente; o backend não confia nela. Cada camada defende de forma independente.

---

## 2. Mecanismo de detecção de ataques

### 2.0 Restrição de métodos HTTP

O SecurityFilter valida o método HTTP antes de qualquer detecção de ataque, permitindo apenas os métodos padrão:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Métodos não padrão (como TRACE, CONNECT, PATCH, métodos personalizados etc.) retornam diretamente **405 Method Not Allowed**, com corpo HTML vazio, sem entrar na detecção de ataque ou na lógica de negócio.

Esta é a primeira linha de defesa em profundidade, bloqueando efetivamente:
- Ataques de rastreamento entre sites TRACE (XST)
- Abuso de proxy de túnel CONNECT
- Sondagem de métodos WebDAV não padrão
- Enumeração de métodos HTTP por scanners automatizados

### 2.1 XSS cross-site scripting

Todos os regex vêm de `SecurityFilter::PATTERNS['XSS']`, correspondência sem distinção de maiúsculas/minúsculas.

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Tag de script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` e variantes com espaços |
| Atributo de evento | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Eventos inline como `onclick="javascript:..."` |
| Protocolo falso JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| XSS via Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Injeção de template | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` — injeção de template servidor/Angular/Vue |

### 2.2 Injeção SQL

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| UNION select | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` — extração de dados |
| OR sempre verdadeiro | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destruição de estrutura | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Chamada de stored procedure | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | Execução de comandos via stored procedures estendidas do MSSQL |
| Sondagem de metadados | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | Sondagem de estrutura do banco MySQL/PG/SQLite/MSSQL |
| Bypass por comentário | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` — bypass por comentário |

### 2.3 Path traversal

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Retrocesso de diretório | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` — retrocesso multinível |
| Sondagem de arquivos sensíveis | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Truncamento por byte nulo | `%00` | `../../../etc/passwd%00.jpg` — bypass de validação de extensão |

### 2.4 Injeção de comando

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Comando com pipe/ponto e vírgula | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substituição com crases | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substituição $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Download remoto via pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF cross-site request forgery

A lógica de validação é implementada em `SecurityFilter::checkCsrf()`:

```php
// Apenas POST/PUT/DELETE disparam a validação
// Origin e Referer ambos vazios → libera (clientes não-navegador)
// Origin não vazio → compara o domínio do Origin com o Host
```

Regras de comparação:
- Remove o prefixo `www.` do Host e compara exatamente com o domínio do Origin
- Se o Host é o domínio pai do Origin (ex.: `Origin: app.example.com`, `Host: example.com` — dispara `str_contains($originHost, '.' . $hostOnly)`), libera
- Nem correspondência exata nem subdomínio → retorna 403, julgado como ataque CSRF

Nota: clientes não-navegador (como curl sem Origin/Referer) são liberados diretamente; a proteção CSRF só é eficaz em ambiente de navegador.

### 2.6 Upload malicioso de arquivos

| Padrão de detecção | Regex | Ataque defendido |
|----------|------|-----------|
| Disfarce de extensão dupla | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` — bypass de whitelist |
| Extensão PHP | `\.php\s*$/m` | Passar caminho `.php` diretamente nos parâmetros da requisição |

---

## 3. Escalonamento de ataques e blacklist de IP

O SecurityFilter tem um mecanismo embutido de escalonamento de ataques para impedir varredura contínua do mesmo IP.

### Fluxo de escalonamento

```
1ª detecção de varredura → Redis INCR security_escalate:{ip} = 1, TTL=60s
2ª detecção de varredura → INCR → 2
...
5ª detecção de varredura → INCR → 5
    → Dispara banimento: SETEX security_ban:{ip} 900 1
    → Limpa o contador DEL security_escalate:{ip}
    → Grava log de segurança: [SECURITY] IP banned 15min
```

### Comportamento durante o banimento

Cada requisição que entra no SecurityFilter verifica primeiro `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Um IP banido tem todas as requisições (incluindo as legítimas) retornando 403 diretamente por 15 minutos, pulando completamente a lógica de negócio.

### Constantes de configuração

| Constante | Valor | Significado |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Limite de disparos na janela de 60 segundos |
| ESCALATE_WINDOW | 60 | Janela do contador (segundos) |
| BAN_DURATION | 900 | Duração da blacklist (segundos), ou seja, 15 minutos |

### Logs de segurança

Local do arquivo: `runtime/logs/security.log`

Exemplo de formato de log:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Limite de tamanho do corpo da requisição

`Content-Length > 10MB` retorna diretamente 413 Payload Too Large, protegendo contra ataques DoS com corpo de requisição gigante.

### Validação de Content-Type

Requisições POST/PUT **devem** declarar `Content-Type` como `application/json` ou `application/x-www-form-urlencoded`; caso contrário, retorna 415 Unsupported Media Type. Requisições de upload de arquivo (com campo file) pulam esta verificação.

---

## 4. Cabeçalhos de segurança de resposta

Todos os cabeçalhos são injetados no middleware `Cors`, anexados a cada resposta via `$response->withHeaders()`.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Permite cross-origin de qualquer origem (cenário de painel admin em intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Conjunto de métodos permitidos |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Cabeçalhos personalizados permitidos |
| Access-Control-Max-Age | `86400` | Cache de preflight por 24 horas |
| X-Content-Type-Options | `nosniff` | Proíbe MIME sniffing no navegador |
| X-Frame-Options | `DENY` | Proíbe qualquer incorporação em iframe, proteção contra clickjacking |
| X-XSS-Protection | `1; mode=block` | Ativa o filtro XSS embutido do navegador e bloqueia a renderização da página |
| Referrer-Policy | `strict-origin-when-cross-origin` | Mesma origem envia URL completa, cross-origin envia apenas o domínio |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Desativa API de câmera/microfone/geolocalização em todo o site |

Requisições de preflight OPTIONS retornam diretamente 204 com resposta vazia, sem entrar na cadeia de middlewares seguinte.

### 4.2 Content-Security-Policy (CSP)

Injetado no middleware Cors junto com os outros cabeçalhos de segurança, fornecendo defesa em profundidade e restringindo as fontes de recursos que o navegador pode carregar e executar.

| Cabeçalho | Valor | Função |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Restringe as fontes de scripts/estilos/imagens/conexões/frames/formulários |
| X-Permitted-Cross-Domain-Policies | `none` | Proíbe carregamento de arquivos de política cross-domain do Adobe Flash/PDF |

Pontos principais da política CSP:
- `default-src 'self'`: por padrão, apenas recursos da mesma origem
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: permite scripts da mesma origem + scripts inline (necessário para Flutter Web) + eval (necessário para debug de Flutter Web)
- `frame-ancestors 'none'`: proíbe incorporação em iframe por qualquer página, dupla proteção com X-Frame-Options: DENY
- `base-uri 'self'`: restringe a tag `<base>` apenas para mesma origem
- `form-action 'self'`: restringe envio de formulários apenas para mesma origem

---

## 5. Política de rate limit

### Algoritmo

Janela deslizante com Redis Sorted Set + script atômico Lua, operações principais:

```lua
-- 1. Limpa registros antigos fora da janela
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Verifica a contagem atual da janela
local count = redis.call('ZCARD', KEYS[1])
-- 3. Se exceder o limite, retorna {0, count}; senão faz ZADD e retorna {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- sufixo aleatório para evitar sobrescrita no mesmo milissegundo
redis.call('EXPIRE', KEYS[1], window + 10)
```

O script Lua é executado em thread única no servidor Redis, **naturalmente atômico**, eliminando a condição de corrida TOCTOU (Time-of-check to Time-of-use).

### Configuração de rate limit

| Rota | Limite | Janela | Cenário |
|------|------|------|------|
| Padrão (todas as rotas) | 60 vezes/minuto | 60s | API geral |
| `/api/auth/login` | 10 vezes/minuto | 60s | Login (proteção contra força bruta) |
| `/api/auth/register` | 5 vezes/minuto | 60s | Registro (proteção contra registro em massa; desativado por padrão, requer `REGISTRATION_ENABLED=1`) |

### Cabeçalhos de resposta

Ao disparar o rate limit, retorna HTTP 429 com corpo JSON:
```json
{"code": 429, "message": "Solicitação frequente demais, tente novamente mais tarde", "data": []}
```

Todas as respostas (incluindo as normais) carregam os seguintes cabeçalhos:

| Cabeçalho | Observação |
|----|------|
| X-RateLimit-Limit | Número máximo de requisições permitidas na janela atual |
| X-RateLimit-Remaining | Requisições restantes disponíveis na janela atual |
| X-RateLimit-Reset | Timestamp Unix do reset da janela |
| Retry-After | Presente apenas no rate limit, segundos sugeridos de espera |

### Estratégia de degradação

Quando o Redis está com problemas (timeout de conexão, indisponível etc.), o comportamento é **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, libera todas as requisições
}
```

Prefere perder a proteção de rate limit por um tempo a bloquear requisições de negócio legítimas.

### 5.4 Mecanismo de bloqueio de conta

Além do rate limit, o endpoint de login tem um mecanismo adicional de **bloqueio de conta**, prevenindo força bruta direcionada a usuários específicos.

**Fluxo de bloqueio**:

```
Falha de login → Redis INCR account_lockout:{userId} TTL=900s
5 falhas consecutivas → Redis SETEX account_locked:{userId} 900 1
            → Retorna 429 "Conta bloqueada, tente novamente em 15 minutos"
            → Limpa o contador DEL account_lockout:{userId}
```

**Comportamento durante o bloqueio**:

Durante o bloqueio, todas as requisições de login retornam diretamente 429, sem verificação de senha, bloqueando completamente as tentativas de força bruta.

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Número máximo de falhas consecutivas |
| LOCKOUT_DURATION | 900 | Duração do bloqueio (segundos), ou seja, 15 minutos |

Nota: o bloqueio de conta é baseado no `userId`, não no IP; portanto, o atacante não consegue contornar trocando de IP. Combinado com o rate limit por IP (10 vezes/minuto), forma proteção dupla:
- Nível de IP: rate limit de 10 vezes/minuto bloqueia força bruta distribuída
- Nível de conta: bloqueio após 5 falhas impede força bruta direcionada

---

## 6. Autenticação e autorização

### 6.1 Autenticação JWT

Implementada pelo middleware AdminAuth, montado no grupo de rotas que exige autenticação.

**Configuração de parâmetros** (`config/plugin/erikwang2013/jwt/jwt`, injetada pelo `.env`):

| Parâmetro | Valor | Observação |
|------|-----|------|
| Algoritmo | HS256 | Assinatura simétrica HMAC-SHA256 |
| Chave | `JWT_SECRET` | Injetada via variável de ambiente, deve ser trocada em produção |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Emissor | `open-admin` | `JWT_ISSUER` |
| Audiência | `open-admin` | `JWT_AUDIENCE` |

**Extração do token**: extraído do cabeçalho `Authorization: Bearer <token>`, removendo o prefixo `Bearer ` para obter o JWT original.

**Fluxo de autenticação**:
1. Token vazio → diretamente 401 `{"code": 401, "message": "Não autenticado"}`
2. Verifica blacklist Redis `jwt_blacklist:{md5(token)}` → encontrou → 401 `Token inválido, faça login novamente`
3. JWT decode → falhou (expirado/assinatura não confere) → 401 `Token expirado ou inválido`
4. Sucesso → injeta `$request->adminId` e `$request->adminUsername`

**Mecanismo de blacklist**: ao fazer logout, o `md5(token)` é gravado no Redis com TTL igual ao tempo restante de validade do JWT. Se o Redis falhar, a verificação de blacklist é pulada (fail-open); tokens já deslogados continuam utilizáveis por pouco tempo, mas a validade curta do próprio JWT (2h) atua como proteção de última linha.

### 6.2 Limite de sessões concorrentes

Para evitar que um token vazado seja abusado em vários dispositivos, o sistema limita o número de tokens válidos que o mesmo usuário pode ter simultaneamente.

**Lógica de limitação**:

```
Login bem-sucedido → emite novo Token
         → consulta a quantidade de tokens válidos do usuário atual: Redis SCARD user_tokens:{userId}
         → se quantidade >= 3 (MAX_CONCURRENT_SESSIONS):
            → ordena por tempo de criação, remove o Token mais antigo:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → adiciona o novo Token ao conjunto: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuração**:

| Constante | Valor | Significado |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Número máximo de tokens concorrentes por usuário |

**Cenário de expulsão**: quando o usuário faz login no 4º dispositivo, o token do 1º dispositivo é forçado para a blacklist; requisições posteriores retornam 401 "Token inválido, faça login novamente".

No logout, o token atual é removido do conjunto. Quando o token expira naturalmente, a chave Redis expira automaticamente e os membros do conjunto diminuem.

### 6.3 Modelo de permissões RBAC

Implementado pelo middleware AdminPermission.

**Modelo de dados**: associação em três níveis User -> Role -> Permission

- `erp_admin_user` (tabela de usuários)
- `erp_admin_user_role` (tabela de associação usuário-papel)
- `erp_admin_role` (tabela de papéis)
- `erp_admin_role_permission` (tabela de associação papel-permissão)
- `erp_admin_permission` (tabela de permissões)

**Tipos de permissão**:
| type | Significado | Exemplo |
|------|------|------|
| 1 | Permissão de menu | Controla a visibilidade da navegação à esquerda |
| 2 | Permissão de botão | Controla botões de ação na página (criar/editar/excluir) |
| 3 | Permissão de API | Controla chamadas de interface do backend |

Formato do identificador de permissão de API: `{method}.{path}`

Exemplos:
- `post.admin/user` — criar usuário
- `put.admin/user` — editar usuário
- `delete.admin/user` — excluir usuário
- `get.admin/user` — ver lista de usuários

**Fluxo de autorização**:
1. `$request->adminId` vazio → libera (rota sem autenticação prévia)
2. Obtém usuário → papéis (pula papéis desativados com `status=0`) → lista de permissões
3. Superadministrador (`slug = '*'`) → libera diretamente
4. Constrói `strtolower(method) . '.' . trim(path, '/')` → compara com a lista de permissões
5. Sem correspondência → 403 `{"code": 403, "message": "Sem permissão de acesso"}`

**Confirmação secundária**: o BaseController fornece o método `confirmPassword()`. Operações sensíveis (excluir usuário, exportar dados etc.) exigem a senha atual adicionalmente na camada do Controller, evitando operações não autorizadas após sequestro de sessão.

---

## 7. Logs de auditoria

### 7.1 Logs de operação

O middleware OperationLog registra automaticamente logs de operação para requisições POST / PUT / DELETE. Requisições GET não são registradas.

**Campos registrados**:

| Campo | Origem | Observação |
|------|------|------|
| id | SnowflakeService::generate() | ID globalmente único |
| user_id | `$request->adminId` | ID do operador, 0 se não autenticado |
| action | `$request->method()` | Equivale ao method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Caminho da requisição |
| ip | `$request->getRealIp()` | IP real do cliente |
| source | detectSource() | Plataforma de origem do cliente |
| input | corpo da requisição (JSON mascarado) | Dados enviados pela operação |
| created_at | `date('Y-m-d H:i:s')` | Horário da operação |

**Filtro de campos sensíveis**: percorre recursivamente o corpo da requisição; os valores dos seguintes campos são substituídos por `***`:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Detecção da plataforma de origem** (`detectSource()`), por prioridade:

1. Lê primeiro o cabeçalho personalizado `X-Client-Platform` (declarado explicitamente por clientes nativos)
2. Degrada para inferência pela string User-Agent (ordem de detecção do método `detectSource()`):

| Plataforma | Palavras-chave do UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | valor padrão de fallback |

**Tolerância a falhas**: exceções na escrita do log não bloqueiam requisições de negócio (`catch (\Throwable)` engole silenciosamente).

### 7.2 Logs de segurança

**Local do arquivo**: `runtime/logs/security.log`

**Conteúdo registrado**:
- Logs de interceptação de ataques: categoria do ataque, IP, caminho, campo, origem, trecho do payload (primeiros 200 caracteres)
- Avisos de banimento de IP: IP banido, número de disparos

As permissões do log são `FILE_APPEND | LOCK_EX`, garantindo escrita segura em concorrência.

---

## 8. Proteção de dados

O sistema usa uma estratégia de proteção de dados em três camadas, correspondendo às três fases do fluxo de dados.

### 8.1 Camada de transporte — EncryptionService

O `EncryptionService` usa o pacote `erikwang2013/encryption` para criptografar/descriptografar campos sensíveis em requisições/respostas da API.

**Detalhes técnicos**:
- Algoritmo: `aes-256-cbc-hmac` (com assinatura HMAC embutida contra adulteração)
- Chave: variável de ambiente `ENCRYPTION_KEY`, alinhada automaticamente para 32 bytes
- Uso: campos como número de telefone e número de identidade transmitidos entre o cliente e a API

**Métodos utilitários de mascaramento**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nome de usuário com mais de 2 caracteres) ou `a**@example.com`

### 8.2 Camada de armazenamento — Encryptable Cast

O modelo `AdminUser` usa o Eloquent cast `Erikwang2013\Encryptable\Encryptable`, com os campos correspondentes:

- `email` → cast para Encryptable, criptografia/descriptografia automática
- `phone` → cast para Encryptable, criptografia/descriptografia automática
- `id_card` → cast para Encryptable, criptografia/descriptografia automática

Ao gravar no banco, criptografa automaticamente em texto cifrado; ao ler, descriptografa automaticamente em texto claro. O tipo de coluna armazenada no banco é `VARCHAR(500)`, com o texto cifrado em base64.

**Sistema de chaves**: usa `ENCRYPTABLE_KEY` separado da criptografia de transporte (`ENCRYPTION_KEY`); o vazamento de uma chave não invalida a outra camada.

Rotação de chaves: a variável de ambiente `ENCRYPTION_PREVIOUS_KEYS` suporta uma lista de chaves históricas (separadas por vírgulas); ao ler dados antigos, tenta descriptografar com as chaves históricas; ao gravar, re-criptografa com a chave atual.

### 8.3 Camada de exibição — Ofuscação de ID e mascaramento

**Ofuscação de ID Hashids**: o `HashidsService` usa o pacote `erikwang2013/hashids`.

- IDs BIGINT do banco retornados pela API externa são codificados em strings hash (ex.: `xK3mN9qR2pL7wV8b`)
- O cliente envia a string hash nas requisições; o backend decodifica automaticamente para o ID original
- O salt `HASHIDS_SALT` é injetado via variável de ambiente; salts diferentes produzem resultados de codificação/decodificação totalmente diferentes
- Comprimento mínimo do hash: 16 caracteres, usando conjunto alfanumérico de 62 caracteres
- O BaseController fornece métodos convenientes `encodeId()`, `decodeId()`, `encodeIds()`

**Mascaramento em exportações**: nas exportações Excel/PDF (ExportController), os campos sensíveis são mascarados uniformemente:
- Telefone: `138****1234`
- E-mail: `a***@example.com`
- Identidade: totalmente coberto como `********`

---

## 9. Gerenciamento de chaves

Todas as chaves são injetadas via variáveis de ambiente `.env`; os arquivos de configuração usam `getenv()` para ler, com valores padrão embutidos de fallback (seguros apenas em desenvolvimento).

| Variável de ambiente | Uso | Pacote | Requisito de produção |
|----------|------|-----|---------|
| JWT_SECRET | Chave de assinatura JWT | erikwang2013/jwt-webman | string aleatória com 64+ caracteres |
| JWT_ALGORITHM | Algoritmo de assinatura JWT | idem | manter HS256 |
| HASHIDS_SALT | Salt de codificação de ID | erikwang2013/hashids | string aleatória |
| SNOWFLAKE_DATACENTER_ID | ID do datacenter (0-31) | erikwang2013/snowflake-php | manter padrão em datacenter único |
| ENCRYPTION_KEY | Chave de criptografia da camada de transporte da API | erikwang2013/encryption | string aleatória de 32 bytes |
| ENCRYPTABLE_KEY | Chave de criptografia da camada de armazenamento do banco | erikwang2013/encryptable | string aleatória de 32 bytes, diferente da chave de transporte |

**Requisitos de segurança**:
- O arquivo `.env` está no `.gitignore`; é proibido enviá-lo ao repositório
- O `.env.example` é um arquivo de modelo público, sem chaves reais
- Em produção, **é obrigatório** trocar todas as chaves padrão por strings aleatórias
- Recomenda-se gerar chaves com `openssl rand -base64 32`

### Isolamento de armazenamento de chaves

| Camada | Chave de configuração | Variável de ambiente da chave |
|----|--------|-------------|
| Criptografia de transporte | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Criptografia de armazenamento | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Ofuscação de ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Assinatura JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

O sistema fornece em `/.well-known/security.txt` um endpoint de informações de contato de segurança em conformidade com RFC 9116, facilitando que pesquisadores de segurança encontrem rapidamente o canal de reporte ao descobrir vulnerabilidades.

**Forma de acesso**:

```
GET /.well-known/security.txt
```

**Conteúdo da resposta**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Descrição dos campos**:

| Campo | Observação |
|------|------|
| Contact | Contato para reporte de vulnerabilidades de segurança |
| Expires | Data de expiração do arquivo, precisa de atualização periódica |
| Preferred-Languages | Idiomas preferidos de comunicação |
| Canonical | URL canônica deste arquivo |
| Policy | Link da política de segurança/política de divulgação de vulnerabilidades |

Este endpoint não é limitado por middlewares de rate limit, autenticação etc.; qualquer pessoa pode acessá-lo diretamente.

---

## 11. Configuração de segurança Nginx

O projeto fornece `docs/nginx-security.conf` como configuração de referência de reforço de segurança para o proxy reverso Nginx em produção.

**Medidas de segurança incluídas**:

| Item de configuração | Função |
|--------|------|
| `server_tokens off` | Oculta o número da versão do Nginx |
| `client_max_body_size 10m` | Limita o tamanho do corpo da requisição, em conjunto com o SecurityFilter |
| `limit_req_zone` | Limite de frequência de requisições no nível do Nginx |
| `limit_conn_zone` | Limite de conexões concorrentes |
| `add_header` cabeçalhos de segurança | Anexa cabeçalhos de segurança como X-XSS-Protection no nível do Nginx |
| `if ($request_method)` | Recusa métodos HTTP não padrão no nível do Nginx |
| Configuração SSL/TLS | Configuração moderna TLS 1.2/1.3, desativa suítes de criptografia fracas |
| Ocultar cabeçalhos do backend | `proxy_hide_header` remove cabeçalhos sensíveis como a versão do webman |

**Como usar**: mescle as configurações de `docs/nginx-security.conf` no bloco server do seu Nginx, ajustando conforme o domínio real e o caminho do certificado.

---

## 12. Modelo de ameaças

### 12.1 Ameaças protegidas

| Tipo de ameaça | Vetor de ataque | Camada de defesa |
|----------|---------|---------|
| Abuso de método HTTP | Ataque XST TRACE/TRACK, proxy de túnel CONNECT, sondagem de métodos WebDAV | SecurityFilter 405 com whitelist de métodos (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Força bruta direcionada | Tentativas repetidas de senha contra usuário específico | Bloqueio de conta (5 falhas bloqueia 15 min) + RateLimit (login 10/min) + Captcha |
| Força bruta | Tentativas distribuídas de usuário/senha por IPs | RateLimit (login 10/min) + Captcha |
| XSS cross-site scripting | `<script>`, onerror, javascript: | SecurityFilter (5 padrões) + cabeçalho X-XSS-Protection + CSP |
| Injeção SQL | UNION SELECT, OR 1=1, bypass por comentário | SecurityFilter (6 padrões) + consultas parametrizadas Eloquent ORM |
| CSRF cross-site request forgery | Sites maliciosos enviando requisições em nome do usuário | Validação de Origin/Referer no SecurityFilter |
| Path traversal | `../../etc/passwd` | Padrões de path traversal no SecurityFilter + whitelist de extensões no UploadController |
| Injeção de comando | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 padrões) |
| Sequestro de sessão | Roubo de token JWT | JWT de curta validade (2h) + logout com blacklist + confirmação secundária de senha em operações sensíveis |
| Enumeração de ID | Percorrer IDs numéricos para adivinhar volume de dados | Hashids ofusca em strings aleatórias |
| Vazamento de dados | Extração do banco / man-in-the-middle / vazamento de logs | Criptografia/mascaramento em três camadas + filtro de campos sensíveis no OperationLog |
| Ataque DoS | Corpo de requisição gigante / requisições de alta frequência | Limite de 10MB do corpo + RateLimit 60/min + blacklist de IP |
| Escalação de privilégios | Usuário de baixo privilégio acessando interfaces de administração | Autorização RBAC com granularidade method.path |
| Ataque de upload de arquivo | shell.php.png com extensão dupla | Detecção de arquivos maliciosos no SecurityFilter |

### 12.2 Limitações conhecidas

| Limitação | Escopo de impacto | Mitigação |
|------|---------|---------|
| Proteção CSRF só é eficaz em navegadores | Clientes não-navegador (curl, Postman, apps móveis) podem pular a verificação Origin/Referer | Clientes não-navegador naturalmente não sofrem ataques CSRF; usa autenticação JWT em vez de Cookie |
| Rate limit e blacklist degradam para fail-open quando o Redis está indisponível | Atacantes podem contornar rate limit e bloqueio de alta frequência | Monitorar alertas de disponibilidade do Redis; validade curta do JWT como proteção de última linha |
| Sem mecanismo WAF independente | O SecurityFilter usa correspondência `@preg_match` com regex, não um mecanismo de regras WAF dedicado | Em produção, recomenda-se Nginx ModSecurity ou Cloudflare WAF na frente |
| JWT sem estado não pode ser invalidado proativamente | Antes da expiração, o token não pode ser revogado do servidor (exceto via blacklist) | Blacklist + TTL curto de 2h reduz a janela de risco |
| Blacklist de IP só em memória | Após reinício do Redis, a blacklist se perde | O banimento dura apenas 15 minutos; impacto limitado |
| Endpoints de administração sem rate limit especial | Interfaces de admin compartilham o limite padrão de 60/min com as comuns | A frequência de operações de admin é naturalmente baixa; sem necessidade por enquanto |
| `@preg_match` suprime erros | Falha silenciosa com entrada regex malformada | `preg_last_error()` pode ser monitorado; ainda não implementado |
