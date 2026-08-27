# Relatório de auditoria — 2026-08-07

**Projeto**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**Escopo**: teste geral de execução, inspeção aprofundada, correção de problemas P0/P1
**Instrução**: "Teste tudo, rode, inspecione a fundo para ver se ainda há problemas ou otimizações?"
**Resultado dos testes**: OK (135 tests, 799 assertions) — todos aprovados

---

## 1. Resultados de testes e verificação de execução

| Item | Resultado |
|---|---|
| Suíte completa PHPUnit | 135 tests / 799 assertions todos aprovados |
| Inicialização do serviço (porta 8787→temporária 8791) | Inicialização normal, sem crash de processos |
| Health check /health | code=0, campos database/redis/elasticsearch completos |
| Cadeia de rate limit | Requisições consecutivas a /api/auth/login retornam 429 |
| Blacklist JWT / bloqueio de login | Funcionando normalmente (após correção do Redis) |
| CS-Fixer | 31 arquivos com violações de formatação corrigidos |
| PHPStan | Voltou a funcionar após correção de cache corrompido (851 falsos positivos de métodos mágicos ORM, 75 itens de baseline obsoletos) |

---

## 2. Correções P0 (falhas de runtime — todas corrigidas e verificadas)

### 2.1 Classe support\Redis ausente — mecanismos de segurança silenciosamente inoperantes

- **Sintoma**: `support\Redis` não existe (composer.json nunca incluiu webman/redis), 9 arquivos o referenciam.
- **Causa raiz**: vários `catch (\Throwable)` com design fail-open engoliram os erros de classe ausente, fazendo rate limit, blacklist JWT, bloqueio de login e banimentos falharem silenciosamente — a interface "parecia normal" mas não tinha nenhuma proteção.
- **Correção**: `composer require webman/redis`; `config/redis.php` com variáveis de ambiente (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Verificação**: /health retorna `redis: ok`; teste de rate limit retorna 429.

### 2.2 Falha de compilação do middleware ApiVersion — todas as rotas /api com 500

- **Sintoma**: `Interface "app\middleware\MiddlewareInterface" not found` — falta `use Webman\MiddlewareInterface;`.
- **Segundo erro após correção**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` é uma subclasse de `Webman\Http\Request`, violando o contrato de contravariância de parâmetros.
- **Correção**: passou a usar imports `Webman\Http\Request` / `Webman\Http\Response`.

### 2.3 Contravariância de parâmetros no middleware AdminAuth — worker crash na rota /admin

- **Sintoma**: /admin/dashboard dispara Empty reply no worker (crash de compilação).
- **Causa raiz**: mesmo problema de contravariância do 2.2.
- **Correção**: passou a usar `Webman\Http\Request` / `Webman\Http\Response` (mantendo `support\Redis`).
- **Verificação**: retorna 401 JSON.

### 2.4 Função auxiliar validator() inexistente — login com 500

- **Sintoma**: `Call to undefined function validator()`, 105 chamadas em 99 arquivos.
- **Correção**: `composer require illuminate/validation`; `app/functions.php` implementa a função auxiliar (cache estático $factory).
- **Armadilha**: o primeiro parâmetro de `Factory::__construct()` deve ser um `Translator`, não `ArrayLoader`.
- **Pendência (P2)**: mensagens de erro não traduzidas (exibem `validation.required` em vez de chinês), é necessário complementar o pacote de idioma zh_CN.

### 2.5 CORS hardcoded + resposta de preflight perdendo cabeçalhos CORS

- **Correção**: novo `app/common/CorsPolicy.php`, lê a whitelist (separada por vírgulas) da variável de ambiente `CORS_ALLOWED_ORIGIN`, faz echo da origin; sem correspondência, não envia cabeçalhos CORS.
- **Ponto crítico**: `Route::fallback` não passa pela cadeia global de middlewares; o preflight OPTIONS precisa anexar os cabeçalhos CORS por conta própria — tratado no closure do fallback.
- **Cabeçalhos de segurança**: removido X-XSS-Protection obsoleto; CSP ganhou `connect-src 'self'`.

### 2.6 FastRoute BadRouteException — sombreamento de rotas

- **Sintoma**: `Static route "/install" is shadowed by previously defined variable route`.
- **Causa raiz**: a rota curinga OPTIONS `/{path:.+}` sombreava rotas estáticas posteriores; rotas de plugin (apidoc) são carregadas depois de config/route.php.
- **Correção**: removida a rota curinga, substituída por `Route::fallback` (deve ficar no final do arquivo de rotas); `/crm/pool/rules` mudou de resource para rota GET explícita, `PoolController::rules()` tornou-se public.

---

## 3. Correções P1 (qualidade de engenharia)

- **3.1 Cache PHPStan corrompido**: /tmp/phpstan/cache veio do diretório service/ removido (resíduo da divisão em microserviços), contendo caminhos absolutos antigos que causavam erros do phar e travamento com CPU 0%. Após limpar o cache e reinstalar, voltou a funcionar. 851 erros são falsos positivos de métodos mágicos do ORM webman; 75 itens de baseline apontam para o diretório service/ inexistente (P2).
- **3.2 CS-Fixer**: 31 arquivos com violações de espaçamento/ordem de use corrigidos.
- **3.3 Sincronização de testes**: `test_cors_response_is_assigned_correctly` atualizado para validar a nova implementação (withHeaders + CorsPolicy).

---

## 4. Causas raiz omitidas na auditoria anterior (08-04)

- Os testes não cobriam a **carregabilidade das classes de middleware** e a **invocabilidade das rotas** (class_exists / is_subclass_of não capturam `use` ausente e contravariância de parâmetros).
- O commit b1fe2de alegava correções de CORS/X-XSS que não correspondiam ao código real — a conclusão da auditoria confiou demais nas mensagens de commit em vez de verificação por execução.

---

## 5. Lista de alterações desta rodada (git status: 41 modificados + 2 novos)

| Arquivo | Alteração |
|---|---|
| app/middleware/ApiVersion.php | Adicionado use Webman\MiddlewareInterface; tipos de parâmetros alterados para Webman\Http |
| app/middleware/AdminAuth.php | Tipos de parâmetros alterados para Webman\Http |
| app/middleware/Cors.php | Refatorado para usar CorsPolicy; atualização de CSP/cabeçalhos de segurança |
| app/common/CorsPolicy.php | **Novo**: política de whitelist CORS |
| config/route.php | Rota fallback + correção de /crm/pool/rules |
| app/controller/crm/PoolController.php | rules() alterado para public |
| app/functions.php | Nova função auxiliar validator() |
| config/redis.php | **Novo** (com variáveis de ambiente após geração pelo composer) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | Sincronização das assertions de CORS |
| ~30 outros arquivos | Correções de formatação CS-Fixer |

---

## 6. Sugestões P2 (ambiente/pendências, não corrigidas)

1. **.env DB_PASSWORD vazio** — autenticação root do MySQL falha, `database: unavailable`; é necessário configurar uma senha real.
2. **Conflito de porta 8787** — ocupada por cloud-php/service (projeto diferente); a implantação em produção precisa distinguir.
3. **Mensagens de erro chinesas do validator** — é necessário instalar pacote de idioma ou mensagens personalizadas.
4. **Reconstrução do baseline PHPStan** — 75 caminhos apontam para o diretório service/ removido; recomenda-se limpar e reconstruir.
5. **Auditoria fail-open** — recomenda-se revisão global dos pontos de engolir erros silenciosos `catch (\Throwable)` (nesta rodada foi encontrado 1 caso de consequência grave); alterar para fail-closed ou log explícito.

---

*Relatório gerado: 2026-08-07, serviço parado, porta restaurada para 8787.*
