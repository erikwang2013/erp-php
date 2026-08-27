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
| Tabelas de dados | 62 (valor planejado) | 72 (valor planejado) | 163 <!-- stats:tables=163 --> |
| Controladores | 48 (valor planejado) | 42 (valor planejado) | 123 <!-- stats:controllers=122 --> |
| Módulos de negócio | 6 (valor planejado) | 6 (valor planejado) | 19 <!-- stats:modules=19 --> |

> **Critério das estatísticas**: o repositório implementa atualmente apenas a versão Full (um único código); as colunas Lite/Standard são valores planejados do produto (não existem branches correspondentes no código),
> e não participam da validação do doc-stats. Os números da coluna Full são medidos por `scripts/doc-stats.sh` (163 tabelas / 123 controladores / 19 módulos de negócio),
> consistentes com o apêndice de `FUNCTIONS.md`.

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
| 18 camadas de proteção de segurança | ✔ | ✔ | ✔ |
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
| Documentação da API (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full | 163 tabelas <!-- stats:tables=163 --> / 19 módulos de negócio <!-- stats:modules=19 --> | Capacidade completa de plataforma empresarial |

---

## Estratégia de branches (a partir de 2026-08)

> Este documento corresponde à convenção de branches da versão atual do repositório, aplicável aos três branches `lite` / `standard` / `full`.

- **`main` é a única fonte de desenvolvimento**: todo desenvolvimento de funcionalidades, correção de defeitos e atualização de dependências é mesclado em `main`.
- **Os branches de versão recebem apenas cherry-pick em releases**: `lite` / `standard` / `full` não são mais linhas de desenvolvimento independentes para commits diários;
  na release, o engenheiro de versões faz cherry-pick das funcionalidades correspondentes a partir de `main` (ou faz uma fusão completa conforme necessário),
  preservando nos branches as respectivas intenções de corte (as diferenças de módulos estão na tabela de comparação acima).
- **Princípio de corte**: o branch de versão é um subconjunto de `main`. Ao mesclar/portar conteúdo de `main`, se o conflito recair na lógica de corte da versão
  (como as diferenças de módulos em EDITIONS.md, corte de rotas), preserva-se a intenção de corte do branch; código não relacionado segue sempre a versão de `main`.
- **Validação**: após a fusão, o branch de versão deve passar na verificação de sintaxe completa `php -l`; testes que não se aplicam por causa do corte podem ser pulados com a devida justificativa registrada.
- **Release**: a fusão/portabilidade do branch de versão é feita pelo engenheiro de versões com um commit de merge; os commits em `main` são executados uniformemente pelo Lead.
