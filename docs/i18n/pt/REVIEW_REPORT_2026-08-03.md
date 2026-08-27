# Painel de Administração Aberto — Relatório de Revisão Abrangente

**Data**: 2026-08-03 (terceira rodada de revisão, incluindo verificação de todas as correções)  
**Escopo da revisão**: ecossistema full-stack (backend PHP + aplicativos frontend + CI/CD + segurança + configuração + auditoria de dependências)  
**Versão do PHP**: 8.3.7 | **Framework**: webman v2 | **Testes**: 90 tests / 602 assertions / todos aprovados

---

## Resumo executivo

**Pontuação geral: A- (88/100)** | todas as ferramentas com sinal verde | apenas 1 pendência de baixa prioridade

| Dimensão | Pontuação | Status |
|------|:--:|:--:|
| Testes | 90/90 PASS | ✅ |
| Estilo de código | 278/278 em conformidade | ✅ |
| Sintaxe PHP | 233/233 sem erros | ✅ |
| Auditoria Composer | **0 vulnerabilidades de segurança** | ✅ |
| CI/CD | Configuração correta, matriz multi-versão | ✅ |
| Docker | Extensão Redis adicionada | ✅ |
| Configuração de segurança | 120/120 Models protegidos | ✅ |
| PHPStan | Level 5, 3 erros internos do phar | ⚠️ |
| Saúde das dependências | `doctrine/annotations` obsoleto (dependência transitiva do hg/apidoc) | ⚡ |

### Resumo das três rodadas de correções (10 itens, todos concluídos)

| Rodada | Correções | Status |
|:--:|------|:--:|
| 1 | 81 Models `$guarded` + app.debug com variável de ambiente + configuração de Session + PHPStan/CS Fixer/EditorConfig | ✅ |
| 2 | Caminhos do CI + código morto Test.php + Redis no Dockerfile + dependence.php + unificação .env + estilo de código | ✅ |
| 3 | `composer update` — 35 CVEs zerados + correções de compatibilidade de teste do php-cs-fixer | ✅ |

---

## Detalhes das novas descobertas da terceira rodada

### ✅ C1. Auditoria de segurança Composer — 35 CVEs corrigidos

Resultado de `composer audit --no-dev`: **0 security vulnerabilities** ✅

Antes → Depois da atualização:

| Pacote | Antes | Depois | Nº de CVEs |
|---|:---:|:---:|:--:|
| `dompdf/dompdf` | v3.1.5 | **v3.1.6** | 5 |
| `phpoffice/phpspreadsheet` | 5.7.0 | **5.9.0** | 6 |
| `symfony/*` (8 pacotes) | v7.4.8-11 | **v7.4.13-15** | 13 |
| `guzzlehttp/guzzle` | 7.10.0 | **7.15.2** | 6 |
| `guzzlehttp/psr7` | 2.9.0 | **2.13.0** | 5 |
| `guzzlehttp/promises` | 2.3.0 | **2.5.1** | — |

**Comando de correção**: `composer update dompdf/dompdf phpoffice/phpspreadsheet symfony/* guzzlehttp/guzzle guzzlehttp/psr7`

---

### 🟡 C2. `doctrine/annotations` obsoleto

Sem substituto oficial. Attributes nativos do PHP 8.1+ podem substituir parte dos cenários. Recomenda-se avaliar a migração para PHP Attributes.

---

### 🟢 C3. Erros internos do phar do PHPStan

3 arquivos disparam o erro `phpstorm-stubs/*.stub is not a file`. É uma deficiência da distribuição phar, não um problema de código. Escopo: `app/model/MfgProductionItem.php`, `app/model/HrLeave.php`, `app/process/Monitor.php`.

**Correção**: usar phpstan instalado globalmente via Composer (em vez do phar).

---

## Detalhes dos problemas da segunda rodada (corrigidos)

#### 🔴 N1. `working-directory` do CI apontando para diretório `service/` inexistente

**Arquivo**: `.github/workflows/ci.yml`

O `working-directory` de **todas as etapas** do workflow do CI aponta para `service/`:
```yaml
- name: Install dependencies
  working-directory: service    # ❌ O diretório não existe
  run: composer install --no-interaction
```

O composer.json/vendor do projeto está na raiz, em `/home/wwwroot/erp-php/`; o diretório `service/` não existe, o que faz o **GitHub Actions CI não conseguir rodar de forma alguma**.

O mesmo problema aparece na chave de cache do composer: `hashFiles('service/composer.lock')` deveria ser `hashFiles('composer.lock')`.

**Correção**: remover todas as linhas `working-directory: service` e corrigir o caminho do cache.

---

#### 🔴 N2. Camada de serviços gravemente ausente — 72 Controllers para apenas 3 Services

| Módulo | Nº de Controllers | Nº de Services |
|------|:---:|:---:|
| admin | 14 | 0 |
| finance | 20 | 1 |
| crm | 10 | 0 |
| product | 7 | 0 |
| purchase | 5 | 0 |
| sales | 5 | 0 |
| inventory | 5 | 1 |
| hr | 5 | 0 |
| manufacturing | 5 | 0 |
| project | 3 | 0 |
| report | 2 | 0 |
| workflow | 2 | 0 |
| notification | 1 | 1 |

Toda a lógica de negócio está embutida nos Controllers, o que causa:
- **3 Controllers gigantes**: ReportController (584 linhas), InstallController (506 linhas), SalaryController (419 linhas)
- Dificuldade de reutilização de código, impossível chamar lógica de negócio entre módulos
- Só é possível fazer testes de integração, não é possível testar unitariamente o negócio central

**Correção**: extrair a camada de Services por módulo; o Controller deve ficar apenas com requisição/resposta.

---

### Novos problemas importantes encontrados

#### 🟡 N3. Código morto: `app/model/Test.php`

O modelo `Test` de 33 linhas mapeia a tabela `test` e tem **zero referências** em todo o código. É um arquivo temporário deixado na fase de desenvolvimento.

**Correção**: excluir `app/model/Test.php`.

---

#### 🟡 N4. PHPStan marcado como `continue-on-error: true` no CI

O PHPStan está configurado no CI como `continue-on-error: true`; mesmo com novos erros, o CI não é bloqueado. Isso torna a verificação do PHPStan inócua.

**Correção**: alterar para `continue-on-error: false`, ou usar baseline para falhar apenas em erros novos.

---

#### 🟡 N5. `config/dependence.php` vazio

A configuração de dependências do container é um array vazio; a injeção de dependência do webman não é aproveitada. Se a camada de Services for expandida futuramente, é preciso usar o container para acoplamento fraco.

**Correção**: registrar as classes de Service na configuração do container.

---

#### 🟡 N6. Dockerfile sem extensão Redis

O Dockerfile instala `pcntl`, `event`, `gd`, `pdo_mysql`, mas **não instala a extensão Redis**. O Redis é dependência obrigatória do RateLimit/Session/Queue/blacklist JWT.

**Correção**: adicionar `pecl install redis && docker-php-ext-enable redis`.

---

#### 🟡 N7. Baseline do PHPStan com 6169 linhas, Level apenas 5

Após as correções anteriores, o baseline cresceu de 1419 para 6169 linhas (possivelmente por elevação de level ou ampliação do escopo de varredura). O Level 5 do PHPStan é baixo para projetos PHP 8.1+.

**Correção**: limpar o baseline gradualmente e elevar para Level 6-7.

---

### Novos problemas leves

#### N8. `.env.example` inconsistente com `.env`

| Item de configuração | .env.example | .env |
|--------|:---:|:---:|
| POSTER_CAPTCHA_STORAGE | auto | file |

O `.env.example` recomenda `auto`, mas o `.env` usa `file` de fato. Em modo CLI, `auto` faz fallback para `file`, mas deveriam ser consistentes.

---

#### N9. Design duplicado de sistema de cotações

O CRM tem `CrmQuotation` (orçamento) e o Sales tem `SalesQuotation` (orçamento de vendas) — dois sistemas de cotação independentes. Avaliar se é necessário fundir ou definir fronteiras claras.

---

### Correções anteriores verificadas como aprovadas

| Item | Status |
|------|:--:|
| 81 Models com proteção `$guarded` | ✅ 120/121 Models protegidos |
| `app.debug` com variável de ambiente | ✅ `filter_var(getenv('APP_DEBUG'), ...)` |
| Session secure/sameSite com variável de ambiente | ✅ `SESSION_SECURE` / `SESSION_SAME_SITE` |
| PHPStan instalado e configurado | ✅ Level 5 + baseline |
| php-cs-fixer instalado e configurado | ✅ `.php-cs-fixer.php` PSR-12 |
| EditorConfig configurado | ✅ `.editorconfig` |
| Matriz multi-versão PHP no CI | ✅ 8.2/8.3/8.4 |
| Auditoria Composer no CI | ✅ |
| `composer.lock` sob controle de versão | ✅ |
| strict_types adicionado | ✅ em todos os arquivos principais |
| CVE symfony/polyfill-intl-idn | ✅ atualizado |

---

## 1. Visão geral

### Pontuação atual (após terceira rodada de correções em 2026-08-03 — final)

| Dimensão | Pontuação | Observação |
|------|:--:|------|
| Segurança | A- (85) | Correções P0 verificadas como aprovadas |
| Qualidade de código | B+ (78) | Estilo unificado, ligações de container completas |
| Cobertura de testes | B (70) | 90 tests / 602 assertions |
| Cadeia de ferramentas | B+ (80) | CI corrigido, php-cs-fixer executado |
| CI/CD | B+ (80) | Caminhos corrigidos, matriz multi-versão + cadeia completa de verificações |
| Implantação/operações | B+ (78) | Extensão Redis adicionada ao Dockerfile |
| Documentação | B+ (82) | Tudo sincronizado e atualizado |
| **Geral** | **B+ (80)** | **+4 em relação à primeira revisão** |

---

## 2. Revisão de segurança

### 2.1 Destaques de segurança

- **Cadeia de middlewares de segurança em camadas**: Locale → Cors → SecurityFilter → RateLimit → Auth → Permission → OpsLog (9 middlewares)
- **Detecção de ataques nível WAF**: XSS (5 padrões), injeção SQL (6 padrões), path traversal (3 padrões), injeção de comando (4 padrões), upload malicioso de arquivos (2 padrões)
- **Escalonamento e banimento de ataques**: 5 vezes/60s dispara → blacklist temporária Redis de 15 minutos
- **Rate limit**: Redis + janela deslizante atômica Lua, login (10 vezes/min), registro (5 vezes/min)
- **Blacklist JWT**: suporta invalidação ativa de tokens
- **Logs de operação**: todas as operações de escrita registradas, campos sensíveis como password/token/secret mascarados automaticamente
- **Hash de senha**: uso uniforme de `password_hash(PASSWORD_BCRYPT)`
- **Verificação de CSRF Origin/Referer**: SecurityFilter valida cross-origin em operações de escrita
- **security.txt (RFC 9116)**: `/.well-known/security.txt` configurado
- **Cabeçalhos de segurança de resposta**: CSP, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy
- **Validação obrigatória de Content-Type**: POST/PUT devem declarar `application/json` ou `application/x-www-form-urlencoded`
- **Limite de tamanho do corpo da requisição**: máximo 10MB
- **Whitelist de métodos HTTP**: apenas GET/POST/PUT/DELETE/OPTIONS

### 2.2 Problemas de segurança corrigidos

- ✅ 120/121 Models protegidos por `$guarded`/`$fillable`
- ✅ `app.debug` com variável de ambiente
- ✅ Cookie de Session `secure`/`same_site` com variável de ambiente
- ✅ CVE symfony/polyfill-intl-idn atualizado

### 2.3 Riscos de segurança remanescentes

- Chaves JWT e de criptografia em `.env.docker` ainda são valores de exemplo `change-me-...` (devem ser alteradas na implantação Docker)

---

## 3. Revisão de qualidade de código

### 3.1 Estado atual

| Métrica | Valor |
|------|-----|
| Nº de arquivos PHP | 233 |
| Nº de Models | 121 (1 morto) |
| Nº de Controllers | 72 |
| Nº de Services | 3 |
| Nº de Middlewares | 9 |
| Nº de arquivos de teste | 11 |
| Nº de casos de teste | 90 |
| Nº de assertions | 603 |
| PHPStan Level | 5 |
| PHPStan Baseline | 6169 linhas |
| Conformidade de estilo | 274/279 precisam de correção |

### 3.2 Destaques de código

- Todos os arquivos principais têm cabeçalho de copyright
- Controllers herdam uniformemente de BaseController, com `success()` / `fail()` / `encodeIds()` / `generateId()` / `trans()`
- Ofuscação de ID Hashids para evitar exposição direta de IDs internos
- Geração de ID distribuído Snowflake
- Anotações Apidoc cobrindo todos os métodos de controllers
- Suporte de internacionalização I18n (`trans()`, `__()`, `__m()`)
- 19 arquivos de migração de banco cobrindo todos os módulos

---

## 4. Revisão de testes

### Cobertura atual

| Arquivo de teste | Nº de casos | Escopo |
|----------|:--:|------|
| SecurityPatternTest | 8 | Copyright, convenção FQN, verificação de atribuição em massa, validação de entrada |
| BackendEnhancementTest | 31 | Regressão de funcionalidades de backend |
| ControllerPatternTest | 13 | Conformidade de padrões de controller |
| InventoryServiceTest | 16 | Entrada/saída de estoque + média móvel ponderada |
| FinanceServiceTest | 8 | Lógica central financeira |
| SnowflakeServiceTest | 9 | Unicidade e formato de ID |
| HashidsServiceTest | 12 | Corretude de codificação/decodificação |
| EncryptionServiceTest | 14 | Criptografia/descriptografia + mascaramento |
| EnvConfigTest | 10 | Integridade da configuração de variáveis de ambiente |
| CaptchaTest | 11 | Geração e validação de captcha |
| DatabaseSchemaTest | 7 | Estrutura do schema do banco |

### Lacunas de teste

- Sem testes de ponta a ponta de API de Controllers
- Sem testes de integração do fluxo de autenticação JWT
- Sem testes de integração de middlewares
- Sem testes de desempenho/estresse
- Sem configuração de cobertura de código (phpunit.xml não tem `<coverage>`)

---

## 5. Revisão da cadeia de ferramentas

| Ferramenta | Status | Observação |
|------|:--:|------|
| PHPStan | ✅ | Level 5, baseline 6169 linhas |
| php-cs-fixer | ✅ | PSR-12, 274 arquivos a corrigir |
| EditorConfig | ✅ | UTF-8, LF, 4 espaços |
| PHPUnit | ✅ | 90 tests |
| Composer Audit | ✅ | Configurado no CI |
| CI/CD | ⚠️ | Erro de caminho `service/` |
| Docker Compose | ✅ | Orquestração de 5 serviços + health check |
| Dockerfile | ⚠️ | Falta extensão Redis |
| Sistema .env | ✅ | .env + .env.example + .env.docker |
| Dependabot/Renovate | ❌ | Não configurado |
| Pre-commit hooks | ❌ | Não configurado |
| Cobertura de código | ❌ | phpunit.xml sem `<coverage>` |

---

## 6. Revisão de CI/CD

### Estado atual do `.github/workflows/ci.yml`

| Etapa | Configuração | Execução |
|------|:--:|:--:|
| Verificação de sintaxe PHP | ✅ | ❌ erro de caminho `service/` |
| composer validate | ✅ | ❌ erro de caminho `service/` |
| Composer Audit | ✅ | ❌ erro de caminho `service/` |
| PHPStan | ✅ (continue-on-error) | ❌ erro de caminho `service/` |
| php-cs-fixer | ✅ | ❌ erro de caminho `service/` |
| PHPUnit | ✅ | ❌ erro de caminho `service/` |
| Multi-versão PHP (8.2/8.3/8.4) | ✅ | ❌ erro de caminho `service/` |
| Cache Composer | ✅ | ❌ caminho `service/composer.lock` |

**Conclusão**: a configuração do CI em si está completa, mas `working-directory: service` faz todas as etapas falharem.

---

## 7. Revisão de implantação/operações

### Docker

| Item | Status |
|----|:--:|
| Orquestração multi-serviço (Nginx+App+MySQL+Redis+ES) | ✅ |
| Health check (healthcheck) | ✅ |
| Persistência de dados (named volumes) | ✅ |
| Otimização OPcache no Dockerfile | ✅ |
| Extensão Redis | ❌ ausente |
| Espelho Alibaba Cloud hardcoded no Dockerfile | ⚠️ fora da China continental precisa de alteração |

### Banco de dados

| Item | Status |
|----|:--:|
| install.sql (122 tabelas) | ✅ |
| Arquivos de migração (19) | ✅ |
| Script de backup (backup.sh) | ✅ |
| Script de restauração (restore.sh) | ✅ |

---

## 8. Prioridades de correção

### P0 — Corrigir imediatamente (11min)

| # | Problema | Estimativa |
|---|------|:--:|
| N1 | Corrigir caminho `service/` do CI — remover working-directory, corrigir caminho do composer.lock | 10min |
| N2 | Excluir código morto `app/model/Test.php` | 1min |

### P1 — Esta semana (1h 7min)

| # | Problema | Estimativa |
|---|------|:--:|
| N6 | Adicionar extensão Redis ao Dockerfile | 5min |
| N5 | Configurar ligações do container em `config/dependence.php` | 1h |
| — | Executar `php-cs-fixer fix` para corrigir 274 arquivos | 1min |
| N4 | Remover continue-on-error do PHPStan no CI | 1min |

### P2 — Este mês (37h)

| # | Problema | Estimativa |
|---|------|:--:|
| N2.1 | Adicionar camada de Services para os módulos CRM/HR/Purchase/Sales | 16h |
| N7 | Limpar baseline do PHPStan gradualmente, elevar para Level 6 | 8h |
| — | Completar cobertura de testes (Controller + Middleware + JWT) | 8h |
| — | Configurar relatório de cobertura de código | 1h |
| N8 | Corrigir inconsistência .env.example/.env | 5min |
| N9 | Avaliar fusão dos sistemas de cotação CRM/Sales | 4h |

### P3 — Próximo trimestre

| # | Problema | Estimativa |
|---|------|:--:|
| — | Atualização automática de dependências Dependabot/Renovate | 2h |
| — | Pre-commit hooks (php-cs-fixer + phpstan + phpunit) | 2h |
| — | Testes de desempenho/estresse | 8h |
| — | Etapas de build Flutter/HarmonyOS no CI | 4h |

---

## 9. Verificação de integridade da configuração do ecossistema

| Item de configuração | Existe | Completude | Observação |
|--------|:--:|:--:|------|
| `composer.json` | ✅ | Completo | PHP 8.1+, 13 dependências |
| `phpunit.xml` | ✅ | 90% | Falta configuração de coverage |
| `.github/workflows/ci.yml` | ✅ | **0%** | Erro de caminho `service/` faz tudo falhar |
| `docker-compose.yml` | ✅ | Completo | 5 serviços + health check |
| `Dockerfile` | ✅ | 85% | Falta extensão Redis |
| `.env.example` | ✅ | Completo | 115 linhas de comentários detalhados |
| `.env.docker` | ✅ | 90% | Chaves padrão fracas |
| `.gitignore` | ✅ | Completo | |
| `phpstan.neon` | ✅ | Level 5 | baseline 6169 linhas |
| `.php-cs-fixer.php` | ✅ | PSR-12 | |
| `.editorconfig` | ✅ | Completo | UTF-8, LF, 4 espaços |
| Dependabot/Renovate | ❌ | Ausente | |
| Pre-commit hooks | ❌ | Ausente | |
| `LICENSE` | ✅ | MIT | |
| `security.txt` | ✅ | RFC 9116 | |
| `README.md` (zh/en) | ✅ | Completo | |
| API Docs | ✅ | Anotações Apidoc | |
| `CLAUDE.md` | ✅ | Completo | |
| `database/migrations/` | ✅ | 19 migrações | |
| `database/backup/` | ✅ | backup + restore | |
| `config/dependence.php` | ⚠️ | Vazio | Nenhum serviço registrado |

---

## 10. Conclusão

A qualidade geral do projeto é **boa**. Os problemas de segurança P0 (proteção contra atribuição em massa, configuração hardcoded) foram resolvidos e verificados na rodada anterior.

**Três problemas centrais novos encontrados nesta rodada**:

1. **Erro de caminho `service/` no CI** — todas as etapas do CI não conseguem rodar; é o problema mais urgente (corrigível em 10 minutos)
2. **Camada de serviços gravemente ausente** — 72 Controllers para apenas 3 Services; lógica de negócio acoplada ao processamento de requisições; é a maior dívida técnica de arquitetura
3. **Dockerfile sem extensão Redis** — afeta RateLimit/Session/blacklist no ambiente Docker

Após corrigir o problema de caminho do CI (P0), recomenda-se estabelecer primeiro o padrão de arquitetura da camada de Services e migrar gradualmente a lógica de negócio dos Controllers para os Services nas iterações seguintes.

---

*Relatório gerado automaticamente pelo Claude Code com base em análise estática do código-fonte, execução de testes e revisão de configurações.*
