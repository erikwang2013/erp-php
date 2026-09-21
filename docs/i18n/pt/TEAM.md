# Planejamento da equipe (equipe de colaboração de IA)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Este documento define a equipe de colaboração de IA deste projeto: composição de papéis, limites de responsabilidades, modos de colaboração e roteamento de tarefas.
> As regras de coordenação complementares (SendMessage-First, nomeação de agentes, ciclo de vida) estão no `CLAUDE.md` da raiz; as definições de papéis estão em `.claude/agents/`.

---

## 1. Perfil do projeto (base do planejamento)

| Dimensão | Situação atual | Significado para a equipe |
|------|------|--------------|
| Back-end | webman (Workerman) PHP 8.3+, **23 módulos de negócio**, 159 controladores, 63 serviços, 224 modelos, 227 tabelas, 11 middlewares (schema com database/install.sql como única fonte de verdade) | Monolito grande e completo; dividir o trabalho por domínio de negócio para evitar estouro de contexto de um único agente |
| Front-end | Flutter **102 rotas de menu** (`lib/app/config/menu_config.dart`; o `getPages` de `main.dart` tem 110 entradas = 102 menus + login/perfil/6 páginas de detalhe) + HarmonyOS **41 páginas** (`main_pages.json`), cobrindo todos os módulos | Manutenção paralela nos dois lados; é preciso um papel dedicado de front-end |
| Baseline de qualidade | PHPUnit 1001 testes / 4726 asserções, PHPStan + baseline, CS-Fixer, matriz de múltiplas versões no CI | Já há disciplina; os papéis de teste/revisão entram direto no pipeline |
| Matriz de versões | Apenas o branch `main` (`lite` / `standard` / `full` foram removidos; o commit de arquivamento `eea90c0` continua no histórico de `main`) | Não há branches de versão a sincronizar; as diferenças de versão são rastreadas por tag, ver «Estratégia de branches» em `docs/EDITIONS.md` |
| Roadmap | P0~P3 entregue (pontuação geral 89/100), em fase de iteração diária e evolução | Tamanho da equipe ajusta-se por tipo de tarefa, não uma estrutura grande por projeto |
| Infraestrutura existente | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, limite de 15 agents, coordenação por consensus), hooks + memória | A equipe é montada sobre a configuração existente, sem recomeçar do zero |

---

## 2. Composição da equipe

### 2.1 Equipe principal (residente, 5 papéis)

| Papel | Agente existente correspondente | Responsabilidades (para este projeto) |
|------|-----------------|--------------------|
| **Lead gerente de projeto** | `planner` / `swarm/hierarchical-coordinator` | Decomposição de requisitos → roteamento → aceite; manter a fila de tarefas dos 23 módulos; decidir os modos pipeline / fan-out / supervisor; retransmitir mensagens entre papéis |
| **Arquiteto de sistemas** | `sparc/architecture` | Design da estrutura de tabelas (227 tabelas, schema com database/install.sql como única fonte de verdade); fluxos de dados entre módulos (recebimento de compra→estoque→contas a pagar, expedição de venda→contas a receber→saída etc.); decisões de limites na divisão em microsserviços |
| **Desenvolvedor back-end** | `core` / customizado `backend-dev` | Implementação de controladores / serviços / modelos; seguir a camada `app/service` e a cadeia de middlewares (Cors→SecurityFilter→RateLimit→TracingId→middlewares de negócio) |
| **Engenheiro de testes** | `testing/tdd-london-swarm` + `production-validator` | Casos PHPUnit primeiro (testes de fronteira do motor); validação de regressão na linha única de `main`; preencher lacunas de cobertura em `tests/` |
| **Revisor de código** | `consensus/security-manager` | PHPStan sem novos itens fora do baseline, conformidade com CS-Fixer, verificação dos padrões de segurança da defesa em profundidade de 7 camadas; guardião do portão de qualidade antes do commit |

### 2.2 Equipe especializada (convocada por tipo de tarefa, 4 papéis)

| Papel | Agente existente correspondente | Cenário de ativação | Tarefas típicas |
|------|-----------------|----------|----------|
| **Especialista em motores de negócio** | customizado `business-engineer` | Módulos algorítmicos como finanças / salários / MRP | Reforço de algoritmos e tratamento de fronteiras dos motores de partidas dobradas, cálculo salarial e MRP (exigência nível A «industrial») |
| **Engenheiro front-end (Flutter)** | customizado `frontend-flutter` | Qualquer alteração envolvendo `apps/flutter/` | Páginas do painel Web, estado GetX, integração ApiService/exportação, manutenção das 102 rotas |
| **Engenheiro front-end (HarmonyOS)** | customizado `frontend-harmonyos` | Qualquer alteração envolvendo `apps/harmonyos/` | Páginas ArkTS, renovação transparente de token, alinhamento do conjunto de funcionalidades com o Flutter (manutenção de 41 páginas) |
| **Engenheiro de segurança/DevOps** | `consensus/security-manager` + `performance-benchmarker` | Endurecimento de segurança, desempenho, implantação | Regressão da defesa em profundidade de 7 camadas, subserviços Docker/gRPC, rollback de migração, observabilidade, métricas Prometheus |

### 2.3 Papéis sob demanda (disparados por tarefa, 2 papéis)

| Papel | Agente existente correspondente | Condição de ativação |
|------|-----------------|----------|
| **Pesquisador** | customizado `researcher` | Antes do design de novos módulos/funcionalidades: pesquisar concorrentes, comparar `API.md`, `FUNCTIONS.md` com as diferenças da implementação e produzir a entrada de design |
| **Coordenador de versões** | customizado `edition-coordinator` | Mudanças na matriz de versões: validação da tabela comparativa de `docs/EDITIONS.md` (as colunas `lite`/`standard` são valores planejados, sem branch correspondente), consistência entre tag de versão e notas de release |

---

## 3. Modos de colaboração

### 3.1 Regras gerais (seguindo o CLAUDE.md da raiz)

- **SendMessage-First**: os agentes comunicam-se diretamente via SendMessage, sem polling e sem estado mutável compartilhado;
- **Nomeação obrigatória**: todo agente deve ser nomeado (`name: "role"`);
- **Um único spawn**: subtarefas independentes são levantadas em background de uma só vez; o Lead para e aguarda o resultado, sem fazer polling de status;
- **Mensagem com instrução obrigatória**: cada prompt deve indicar «após concluir, envie SendMessage para quem, com o quê».

### 3.2 Três topologias de orquestração

| Modo | Fluxo | Cenário de uso |
|------|------|----------|
| **Pipeline** | Lead → Arquiteto → Back-end → Testes → Revisão | Desenvolvimento de funcionalidades com dependência sequencial (novos módulos, fluxos de dados entre módulos) |
| **Fan-out** | Lead → A, B, C → consolidação no Lead | Trabalho paralelo mutuamente independente (várias páginas, pesquisa de vários módulos) |
| **Supervisor** | Lead ↔ membros em várias rodadas | Trabalho complexo com coordenação contínua (divisão em microsserviços, refatoração em grande escala) |

### 3.3 Tabela de roteamento de tarefas

| Tipo de tarefa | Orquestração | Papéis participantes |
|----------|------|----------|
| Novo módulo / nova funcionalidade (ex.: aprofundamento de DMS, BI) | pipeline | Lead → Arquiteto (design de tabelas) → Back-end → Testes → Revisão |
| Algoritmo de motor (partidas dobradas / salários / MRP) | pipeline + TDD | Lead → Especialista em motores de negócio (design) → Testes (casos de fronteira primeiro) → Revisão |
| Páginas front-end (Flutter / HarmonyOS em paralelo) | fan-out | Lead → 2× front-end + back-end (alinhamento de API) em paralelo → consolidação no Lead |
| Fluxo de dados entre módulos (compra→estoque→contas a pagar etc.) | pipeline | Lead → Arquiteto → Back-end → Testes → Revisão |
| Divisão em microsserviços / refatoração em grande escala | supervisor | Lead ↔ Arquiteto + Back-end + Revisão em várias rodadas |
| Ações específicas de segurança / desempenho | aprofundamento em thread única | Lead → Engenheiro de segurança/DevOps → Revisão |
| Correção de bug (arquivo único / 1-2 linhas) | fora da equipe | Lead resolve diretamente, ou 1 agente conclui |
| Diferenças de tags de versão / lançamento de versão | pipeline | Lead → Coordenador de versões → Testes (regressão na linha única de `main`) → Revisão |

### 3.4 Portão de qualidade (obrigatório antes do commit, guardado pelo revisor)

```
phpunit            # 1001 testes / 4726 asserções tudo verde; novos casos acompanham a alteração
phpstan            # não é permitido nenhum problema novo fora do baseline
php-cs-fixer       # --dry-run aprovado
composer audit     # sem vulnerabilidades de dependência de alto risco
```

Alterações envolvendo banco de dados devem passar pelo arquiteto (227 tabelas, schema com database/install.sql como única fonte de verdade); alterações de front-end devem rodar `flutter analyze` do Flutter com 0 error / 0 warning.

---

## 4. Tamanho recomendado da equipe

| Forma de trabalho | Tamanho recomendado | Observação |
|----------|----------|------|
| Manutenção diária / pequenos reparos | 1-2 pessoas | O Lead resolve diretamente, evitando orquestração excessiva |
| Iteração de um módulo | 3 pessoas | Lead + Back-end + Testes |
| Funcionalidade entre módulos | 4-5 pessoas | Lead + Arquiteto + Back-end + Testes + Revisão |
| Front-end em dois lados em paralelo | 4-5 pessoas | Lead + Flutter + HarmonyOS + Back-end (API) + Testes |
| Motor / refatoração complexa | 5-7 pessoas | O conjunto acima + especialista em motores de negócio ou segurança/DevOps |

> Compatível com `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, estratégia de coordenação `consensus`); uma única tarefa não excede o limite.

---

## 5. Passos de implantação

1. **Completar as definições de papéis**: `.claude/agents/` já tem planner / sparc / testing / swarm / consensus; faltam cinco definições: `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator`; adicionar um arquivo no formato YAML/MD existente para concluir a montagem;
2. **Fixar o roteamento**: gravar a tabela de roteamento da §3.3 na lógica de routing de `.claude-flow/hooks`, para que o hook `UserPromptSubmit` distribua automaticamente a tarefa ao papel correspondente;
3. **Domínios de memória**: `.claude-flow` já tem `agentScopes` habilitados (`defaultScope: project`); recomenda-se arquivar por quatro domínios `backend / frontend / ops / security`, evitando que o contexto do motor financeiro contamine tarefas de front-end;
4. **Execução piloto**: escolher uma tarefa entre módulos (ex.: aprofundamento de DMS ou iteração do painel BI) e rodar um ciclo completo conforme o roteamento da §3.3, validando a cadeia de mensagens e o portão antes de generalizar.

---

## 6. Registro de alterações

| Data | Alteração |
|------|------|
| 2026-08-07 | Versão inicial: equipe principal 5 + especializada 4 + sob demanda 2, com base na situação atual de 22 módulos (P0~P3 entregue, 89/100) |
