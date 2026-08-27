# Sistema ERP Aberto — Documento de design de funcionalidades

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Visão geral do sistema

O Sistema ERP Aberto (open-erp) é um sistema de planejamento de recursos empresariais full-stack construído sobre webman v2 + Flutter, cobrindo catorze grandes domínios de negócio: administração do sistema, compras/vendas/estoque, finanças, CRM, fluxo de aprovação, notificações de mensagens, gestão de projetos, recursos humanos, produção industrial e relatórios personalizados.

### 1.1 Objetivos de design
- Implantação monolítica, design modular
- Todos os IDs gerados por snowflake + transmissão criptografada com hashids
- Criptografia dupla de dados sensíveis (camada de transmissão AES-256-CBC + camada de armazenamento AES-128-ECB)
- Custeio por média móvel ponderada
- Integração automática entre módulos (compra→contas a pagar, venda→contas a receber, recebimentos/pagamentos→compensação)

### 1.2 Restrições técnicas
- PHP 8.3+, MySQL 8.0+, Redis 7, Elasticsearch 8
- Prefixo de tabelas erp_, chave primária BIGINT não incremental
- Versão da API controlada pelo cabeçalho de requisição API-Version
- Autenticação JWT + permissões RBAC
- Funções globais sem prefixo `\`

---

## 2. Módulo de administração do sistema

### 2.1 Gestão de usuários
- CRUD de administradores, com habilitação/desabilitação em lote e soft delete em lote
- Importação em lote via Excel (validação linha a linha + relatório de erros)
- Senha com hash bcrypt; alteração de senha exige confirmação da senha atual
- Operação de exclusão exige segunda confirmação da senha do usuário atual
- Telefone/e-mail/CPF armazenados criptografados, com mascaramento automático nas listagens

### 2.2 Papéis e permissões (RBAC)
- CRUD de papéis, slug identificador único
- Árvore de permissões (parent_id autorreferente de níveis ilimitados), tipos: menu/botão/API
- Formato do identificador de permissão: {method}.{path} (ex.: get.admin/product, post.admin/user/batch/destroy)
- Associação muitos-para-muitos papel-permissão
- Super administrador (super_admin) ignora todas as verificações de permissão
- Middleware AdminPermission armazena permissões em cache Redis (TTL=60s)

### 2.3 Configuração do sistema
- Armazenamento chave-valor, com suporte a grupos
- Tipos de valor: string|int|bool|json|array

### 2.4 Auditoria de operações
- Registro automático de todas as operações POST/PUT/DELETE
- Registro: operador, ação, método, caminho, IP, parâmetros (campos sensíveis mascarados), horário
- Detecção automática da origem em 8 plataformas (Web/Flutter/HarmonyOS/API etc.)
- Apenas consulta; não é possível excluir/alterar

### 2.5 Proteção de segurança
- 18 camadas de defesa em profundidade (detalhes em SECURITY.md)
- SecurityFilter: restrição de métodos HTTP + bloqueio de XSS/Injeção SQL/Path Traversal/Injeção de comandos/CSRF
- RateLimit: rate limit por janela deslizante no Redis (Lua atômico, 60 vezes/minuto)
- Captcha de clique (obrigatório no login/registro)
- Bloqueio de conta: 5 falhas bloqueiam por 15 minutos
- Limite de sessões simultâneas: no máximo 3 Tokens por usuário
- Cabeçalho CSP, security.txt (RFC 9116)
- Segunda verificação aleatória de operações sensíveis com poster-php

---

## 3. Produtos e dados básicos

### 3.1 Gestão de produtos
- Ficha do produto: código (único), nome, código de barras, especificação, unidade básica, imagem, descrição
- SKUs multi-especificação: múltiplos SKUs sob o mesmo produto, cada um com código, código de barras e atributos de especificação independentes (JSON)
- Conversão de múltiplas unidades: unidade básica ↔ unidade auxiliar, taxa de conversão
- Estratégia de preços: preço de compra, preço de atacado, preço de varejo, preço por nível de cliente
- Categoria de produtos: estrutura hierárquica de níveis ilimitados, com ordenação por arrastar e soltar
- Gestão de marcas: nome da marca, Logo, descrição

### 3.2 Armazéns e localizações
- Gestão de múltiplos armazéns (nome, código, endereço, responsável)
- Múltiplas localizações por armazém (código único dentro do armazém)
- Telefone de contato do armazém armazenado criptografado

### 3.3 Fornecedores/clientes
- Ficha do fornecedor: código, nome, contato, telefone/e-mail (criptografado), endereço, dados bancários
- Ficha do cliente: código, nome, nível do cliente, limite de crédito
- Nível do cliente: nome, taxa de desconto padrão
- Fornecedores/clientes com suporte a busca full-text ES

---

## 4. Módulo de compras

### 4.1 Fluxo de compra
Solicitação → Aprovação → Pedido → Recebimento → Liquidação

### 4.2 Solicitação de compra
- Departamentos/pessoas submetem necessidades de compra
- Status: aguardando aprovação → aprovado/rejeitado → convertido em pedido
- Suporte a operações do aprovador

### 4.3 Pedido de compra
- Vincula fornecedor e itens do produto (quantidade, preço unitário, valor)
- Status: aguardando revisão → revisado → recebimento parcial → recebido → cancelado
- Pode ser criado com base na solicitação de compra ou diretamente

### 4.4 Recebimento de compra (integração entre módulos)
- Recebimento por pedido, com suporte a recebimento parcial
- Disparo automático no recebimento:
  1. InventoryService.stockIn() → atualiza o estoque em tempo real + recalcula a média móvel ponderada
  2. FinanceService.createAp() → gera o registro de contas a pagar
  3. Atualiza a quantidade recebida e o status do pedido
- Suporte a registro de localização e número de lote

### 4.5 Devolução de compra
- Devolução ao fornecedor, gerando baixa de saída de estoque
- Vincula o documento de recebimento

### 4.6 Liquidação com fornecedores
- Consolidação por fornecedor: valor de compras, pago, a pagar
- Status da liquidação: não liquidado/parcialmente liquidado/liquidado

---

## 5. Módulo de vendas

### 5.1 Fluxo de venda
Cotação → Pedido → Expedição → Liquidação

### 5.2 Cotação
- Cotação ao cliente, com suporte a conversão em pedido de venda
- Status: rascunho → cotado → convertido em pedido → expirado

### 5.3 Pedido de venda
- Vincula cliente e itens do produto (quantidade, preço unitário, desconto)
- Status: aguardando revisão → revisado → expedição parcial → expedido → cancelado
- Suporte a valor de desconto

### 5.4 Expedição de venda (integração entre módulos)
- Expedição por pedido, com suporte a expedição parcial
- Disparo automático na expedição:
  1. InventoryService.stockOut() → baixa no estoque (usando o custo médio ponderado)
  2. FinanceService.createAr() → gera o registro de contas a receber
  3. Atualiza a quantidade expedida e o status do pedido

### 5.5 Devolução de venda
- Devolução do cliente, gerando entrada de estoque compensatória

### 5.6 Liquidação com clientes e margem bruta
- Consolidação por cliente: valor de vendas, recebido, a receber
- Margem bruta de vendas: cálculo por pedido/produto/cliente

---

## 6. Módulo de estoque

### 6.1 Gestão de estoque
- Estoque em tempo real: precisão de quatro dimensões armazém+localização+lote+SKU
- Fluxo de entrada/saída: todas as variações de estoque registradas de forma unificada (direção, quantidade, custo, documento de origem)
- Rastreamento de lotes: data de fabricação, data de validade
- Rastreamento de números de série: número de série único, status registrado nas entradas e saídas (em estoque/expedido)

### 6.2 Custeio
- Método da média móvel ponderada
- Fórmula: novo preço médio = (valor total do estoque anterior + valor total desta entrada) / (quantidade do estoque anterior + quantidade desta entrada)
- Recálculo automático a cada entrada; saídas custeadas pelo preço médio atual
- Cadeia completa de registros de custo (preço médio antes da variação → preço médio após a variação)

### 6.3 Transferência de estoque
- Transferência entre armazéns/localizações
- Status: aguardando transferência → transferido (saída) → recebido (entrada) → concluído
- Geração automática dos fluxos de saída/entrada

### 6.4 Gestão de inventário (contagem)
- Inventário planejado (por armazém/categoria) + inventário dinâmico (por SKU)
- Registro da quantidade contábil vs. quantidade real
- As diferenças do inventário geram automaticamente fluxos de sobra/falta de estoque

### 6.5 Alertas de estoque
- Definição de limites superior/inferior por SKU+armazém
- Registro automático de log de alerta abaixo do limite inferior/acima do limite superior

---

## 7. Módulo financeiro

### 7.1 Plano de contas
- Árvore de contas: cinco grandes classes — ativo/passivo/patrimônio líquido/receitas/despesas
- Código da conta único
- Direção do saldo: débito/crédito

### 7.2 Voucher contábil
- Número do voucher, data, histórico
- Partidas dobradas: cada lançamento contém valor de débito e valor de crédito (débitos e créditos devem ser iguais)
- Status: rascunho → revisado

### 7.3 Razão geral
- Consolidação por conta contábil + período contábil (ano/mês)
- Registros: saldo inicial de débito/crédito, movimentações do período de débito/crédito, saldo final de débito/crédito
- Saldo final = saldo inicial ± movimentações do período (conforme a direção do saldo da conta)
- Atualização automática após a revisão do voucher
- Suporte a filtros por ano/mês/conta

### 7.4 Razão auxiliar
- Cada lançamento de voucher da conta especificada registrado individualmente
- Inclui: número do voucher, direção (débito/crédito), valor, saldo, histórico, data
- Suporte a consulta por conta + intervalo de datas
- Sincronizado com os lançamentos dos vouchers

### 7.5 Balanço patrimonial
- Gerado por período contábil (mensal/anual)
- Consolidação automática dos saldos do razão geral:
  - Contas de ativo (1) → ativo total = ativo circulante + ativo não circulante
  - Contas de passivo (2) → passivo total = passivo circulante + passivo não circulante
  - Contas de patrimônio líquido (3) → patrimônio líquido dos proprietários
  - Identidade contábil: ativo = passivo + patrimônio líquido
- Suporte a snapshot (dados completos em JSON)
- Sem snapshot, gera automaticamente a partir do razão geral

### 7.6 Demonstração de fluxo de caixa
- Gerada por período contábil (mensal/anual)
- Três classificações:
  - Fluxo de caixa das atividades operacionais (recebimentos de vendas - pagamentos de compras - despesas)
  - Fluxo de caixa das atividades de investimento
  - Fluxo de caixa das atividades de financiamento
- Saldo inicial/final de caixa = soma dos saldos inicial/final de todas as contas bancárias
- Geração automática pela consolidação do diário de caixa e bancos
- Suporte a snapshot (dados completos em JSON)

### 7.7 Contas a receber/a pagar
- Geradas automaticamente pelo recebimento de compra/expedição de venda
- A receber: tipo=AR, vincula cliente, origem=documento de expedição de venda
- A pagar: tipo=AP, vincula fornecedor, origem=documento de recebimento de compra
- Status: não compensado → parcialmente compensado → compensado
- O mesmo documento de origem não pode gerar registros repetidos (proteção de idempotência)

### 7.8 Gestão de recebimentos
- Múltiplas contas (dinheiro/banco/WeChat/Alipay)
- Após a revisão, atualiza automaticamente o saldo da conta bancária e o diário de caixa
- Compensação: selecionar registros a receber e informar o valor da compensação (não pode exceder o saldo não compensado)
- O status de compensação parcial flui automaticamente

### 7.9 Gestão de pagamentos
- Mesma lógica dos recebimentos, na direção oposta
- Compensação de contas a pagar

### 7.10 Diário de caixa e bancos
- Registro de cada receita/despesa por conta+data
- Registro do saldo após a variação
- Saldo da conta bancária atualizado em tempo real

### 7.11 Reembolso de despesas
- Fluxo: envio → aprovação → pagamento
- Vincula a conta de despesa
- Após o pagamento, gera automaticamente o comprovante de pagamento + diário

### 7.12 Demonstração de resultados
- Consolidação mensal: receita operacional, custo operacional, despesas, lucro
- Armazenamento em snapshot (year+month únicos)

### 7.13 Depreciação de ativo imobilizado
- Gestão do ciclo de vida completo do ativo: aquisição → uso → depreciação → baixa
- Método de depreciação: linear ((valor original - valor residual) / meses de uso)
- Provisão mensal de depreciação, com geração automática dos registros
- Registros: valor original, valor residual, vida útil, depreciação mensal, depreciação acumulada, valor líquido

### 7.14 Gestão tributária
- Suporte a múltiplos impostos: IVA/imposto de renda de pessoa jurídica/imposto de renda de pessoa física/imposto sobre circulação
- Alíquotas configuráveis com flexibilidade
- Vinculação aos documentos de compra/venda, com registro automático do valor do imposto

### 7.15 Multimoeda
- Gestão de moedas: CNY/USD/EUR/JPY etc.
- Identificação da moeda base
- Taxas de câmbio gerenciadas por data de vigência
- Suporte a conversão de moeda estrangeira

### 7.16 Gestão orçamentária
- Elaboração do orçamento anual: por centro de custo + conta + mês
- Análise comparativa orçamento vs. realizado
- Cálculo da taxa de execução + análise de variação
- Status: rascunho → aprovado → em execução → encerrado

### 7.17 Centro de custo/centro de lucro
- Estrutura hierárquica em árvore
- Acumulação de custos + rateio de despesas
- Apuração independente do centro de lucro

---

## 8. Módulo CRM

### 8.1 Gestão de clientes
- Ficha do cliente vinculada ao cliente dos dados básicos
- Múltiplos contatos por cliente (marcação do contato principal)
- Telefone/e-mail dos contatos armazenados criptografados

### 8.2 Registros de acompanhamento
- Formas de acompanhamento: telefone/visita/e-mail/mensagem/outros
- Registro do conteúdo do acompanhamento, próximo acompanhamento planejado, data do próximo acompanhamento
- Vincula cliente, contato e oportunidade

### 8.3 Campanhas de marketing
- Ciclo de vida completo da campanha: planejada → em andamento → concluída → cancelada
- Múltiplos canais: e-mail/SMS/telefone/eventos/redes sociais
- Acompanhamento dos clientes participantes, estatísticas de taxa de conversão
- Comparação de orçamento vs. gasto real

### 8.4 Tickets de serviço
- Gestão de tickets: aguardando tratamento → em tratamento → resolvido → encerrado
- Prioridades: baixa/média/alta/urgente
- Categorias: suporte técnico/reclamação/consulta/troca-devolução/outros
- Atribuição do responsável + respostas (públicas/anotações internas)
- Estatísticas de taxa de resolução

### 8.5 Relatórios analíticos de clientes
- 6 indicadores principais: novos clientes/clientes ativos/taxa de retenção/ticket médio/CLV/taxa de resolução de tickets
- Geração automática de relatórios (snapshot JSON dos dados)
- Suporte a mensal/trimestral/anual

### 8.6 Funil de vendas
- Configuração de estágios: contato inicial (10%) → confirmação de necessidade (30%) → proposta de cotação (50%) → negociação comercial (70%) → fechamento (100%) → perda do negócio (0%)
- Oportunidade: cliente, estágio atual, valor estimado, probabilidade de fechamento, data prevista de fechamento, responsável
- Status da oportunidade: perdida/em andamento/fechada
- Acompanhamento da movimentação entre estágios

### 8.7 Pool compartilhado de clientes
- Pool compartilhado de clientes: clientes sem dono atribuído ou sem acompanhamento dentro do prazo entram automaticamente no pool
- Regras de reciclagem: dias de reciclagem automática sem acompanhamento configurados por nível de cliente
- Limite máximo de retirada por pessoa, evitando a estagnação de recursos de clientes
- Operações de retirada/liberação/reciclagem com registro em fluxo
- Estimula a atividade da equipe de vendas e evita clientes estagnados

### 8.8 Gestão de cotações do CRM
- Fluxo de cotação interno do CRM, independente do módulo de vendas
- Status: rascunho → enviada → confirmada pelo cliente → convertida em contrato → expirada
- Suporte a validade da cotação
- Suporte à conversão direta em contrato (`to-contract`)
- Vincula cliente e oportunidade

### 8.9 Gestão de contratos
- Ciclo de vida completo do contrato: rascunho → aguardando aprovação → aprovado → em execução → concluído/encerrado
- Vincula cliente, oportunidade e cotação
- Itens do contrato: produto/quantidade/preço unitário/valor
- Registro da data de assinatura, datas de início/fim
- Conteúdo das cláusulas do contrato (campo grande TEXT)
- Atribuição do responsável

---

## 9. Módulo de fluxo de aprovação

### 9.1 Definição do fluxo de trabalho
- Nome, descrição e módulos aplicáveis do fluxo de trabalho
- Configuração de cadeias de aprovação com múltiplos nós
- Cada nó define aprovador/papel de aprovação e estratégia de aprovação (aprovação conjunta/qualquer um)

### 9.2 Fluxo de aprovação
- Envio do documento de negócio para aprovação → criação automática da instância de aprovação
- Tramitação pelos nós predefinidos, cada nó processado em sequência pelo aprovador
- Operações de aprovação: envio (iniciado a partir do módulo de negócio), aprovação, rejeição, retirada
- O resultado da aprovação chama de volta o módulo de negócio para atualizar o status do documento
- Lista de minhas aprovações: pendentes/concluídas

### 9.3 Registros de aprovação
- Rastreamento completo da cadeia de aprovação: cada etapa registra aprovador, operação, parecer e horário
- A instância de aprovação vincula o número do documento de negócio

---

## 10. Módulo de notificações de mensagens

### 10.1 Gestão de notificações
- Lista de notificações: ordem cronológica inversa, exibição paginada
- Tipos de notificação: notificação de aprovação, aviso do sistema, alerta de negócio
- Marcar como lida: marcação individual / marcar todas como lidas
- Contagem de não lidas: quantidade de mensagens não lidas em tempo real

### 10.2 Modelos de notificação
- Modelos de notificação predefinidos (título + placeholders de conteúdo)
- Categorias de modelos: aprovação/alerta/sistema
- Configuração de notificações: preferências de canal por usuário

### 10.3 Serviço de notificações
- Interface unificada de envio do NotificationService
- Suporte à extensão multicanal (mensagem no sistema/e-mail/SMS/WebSocket)

---

## 11. Módulo de gestão de projetos

### 11.1 Gestão de projetos
- CRUD de projetos: nome, descrição, status, datas de início/fim, responsável
- Status do projeto: planejando → em andamento → concluído → arquivado
- Gestão de membros do projeto: adicionar/remover membros

### 11.2 Gestão de tarefas
- CRUD de tarefas: título, descrição, prioridade, status, data limite
- Vincula o projeto, com suporte a tarefas pai/filho
- Status da tarefa: a iniciar → em andamento → concluída → encerrada
- Atribuição de tarefas: responsável designado

### 11.3 Registro de horas
- Registro de horas por tarefa: data, duração, descrição
- Consolidação das estatísticas de horas por projeto

---

## 12. Módulo de recursos humanos

### 12.1 Estrutura organizacional
- Gestão de departamentos: estrutura em árvore, nome do departamento, código, responsável, departamento pai
- Gestão de cargos: nome do cargo, código, departamento de vínculo, status

### 12.2 Gestão de funcionários
- Ficha do funcionário: código, nome, sexo, telefone (criptografado), e-mail (criptografado), data de admissão, departamento, cargo
- Status: ativo/desligado
- Vincula a conta de usuário do sistema

### 12.3 Gestão de ponto
- Marcação de ponto: marcação de entrada e saída, com registro do horário
- Consulta de ponto: por funcionário + intervalo de datas
- Regras de ponto: horário de trabalho, limiares de atraso/saída antecipada

### 12.4 Gestão de afastamentos
- CRUD de afastamentos: tipo (ausência pessoal/doença/férias etc.), horários de início/fim, motivo
- Fluxo de aprovação: envio → aprovação do chefe do departamento → aprovação/rejeição
- Status: aguardando aprovação → aprovado → rejeitado

### 12.5 Gestão de salários
- Itens salariais: salário base/desempenho/auxílios/deduções etc., com forma de cálculo
- Pagamento de salário: geração mensal da folha, vinculada ao funcionário
- Status do pagamento: aguardando pagamento → pago

---

## 13. Módulo de produção industrial

### 13.1 BOM (lista de materiais)
- Definição do BOM: produto pai, materiais filhos, quantidade padrão, unidade, operação
- Níveis do BOM: suporte à expansão de BOM multinível
- Gestão de versões: registros de revisão do BOM

### 13.2 Ordens de produção
- CRUD de ordens de produção: produto, quantidade planejada, datas planejadas de início/fim
- Status: aguardando produção → em produção → concluída → encerrada
- Operações de início/conclusão: registro dos horários reais de início/fim
- Detalhes de produção: lista de retirada de materiais (baseada na expansão do BOM)

### 13.3 Roteiros de produção
- Definição do roteiro: produto, sequência de operações, tempo padrão de cada operação
- Vincula BOM e estações de trabalho

### 13.4 Estações de trabalho
- CRUD de estações de trabalho: nome, código, tipo, capacidade, status
- Vincula as operações do roteiro

### 13.5 MRP (planejamento de necessidades de materiais)
- Plano MRP: cálculo das necessidades de materiais com base em pedidos de venda/planos de produção + BOM
- Geração automática de sugestões de compra (quando faltar matéria-prima) e sugestões de produção (quando faltar semiacabado)
- Detalhes do MRP: material, necessidade bruta, disponibilidade do estoque, necessidade líquida, quantidade sugerida do pedido
- Status do plano: rascunho → gerado → ordens de compra/produção emitidas

---

## 14. Módulo de relatórios personalizados

### 14.1 Definição de relatórios
- CRUD de modelos de relatório: nome, descrição, dataset, campos, condições de filtro, tipo de gráfico
- Datasets: consultas SQL predefinidas ou métodos de modelo
- Campos do relatório: nome da coluna, nome de exibição, tipo de dados, ordenação
- Filtros: campo, operador, valor padrão

### 14.2 Execução de relatórios
- Execução do relatório com geração dos dados: aplicação das condições de filtro, ordenação, paginação
- Exibição do resultado: tabela ou gráfico (renderizado pelo front-end)
- Suporte a exportação

### 14.3 Agendamento
- Tarefas agendadas de relatórios: relatório especificado, frequência de execução (cron), destinatários
- Status do agendamento: habilitado/desabilitado
- Consulta do histórico de execuções

---

## 15. Dashboards

### 15.1 Visão geral operacional
- Vendas e compras de hoje/do mês
- Total a receber/a pagar, valor total do estoque, margem bruta
- Dados em cache Redis por 5 minutos

### 15.2 Painel de vendas
- Tendência de vendas, Top 10 de clientes
- Análise de conversão do funil do CRM

### 15.3 Painel de estoque
- Valor total do estoque, estatísticas de alertas
- Tendência de entrada/saída (por dia/direção)

### 15.4 Painel financeiro
- Total a receber/a pagar, recebimentos e pagamentos do mês
- Consolidação dos saldos de caixa e bancos

---

## 16. Internacionalização (i18n)

### 16.1 Detecção automática de idioma
- Reconhecimento automático pelo cabeçalho `Accept-Language` (zh-CN → chinês, en → inglês)
- O middleware Locale é executado em primeiro lugar na cadeia de middlewares globais
- Cadeia de fallback: idioma atual → fallback_locale configurado → retorna a chave original

### 16.2 Arquivos de tradução
- Diretório: `resource/translations/{locale}/`
- Mensagens comuns: `common.php` (41 chaves: sucesso/falha/criação/atualização/exclusão/validação etc.)
- Nomes de módulos: `modules.php` (69 chaves: produtos/compras/vendas/estoque/finanças/CRM etc.)
- Regras de validação: `validation.php` (11 regras + 10 rótulos de campos)

### 16.3 Formas de uso
- Dentro do controlador: `$this->trans('created')`
- Função global: `__('modules.product')`, `__m('finance')`
- Nome do módulo: `__('modules.product')` → Produto / Product

---

## 17. Função de exportação

### 17.1 Exportação Excel
- Todas as páginas de listagem suportam ?export=excel
- Geração de .xlsx com PhpSpreadsheet
- Cabeçalho azul com texto branco + primeira linha congelada + largura automática de colunas
- Mascaramento automático de campos sensíveis

### 17.2 Exportação PDF
- O painel de dados do dashboard suporta ?export=pdf
- Renderização com Dompdf, A4 paisagem
- Informações de copyright não removíveis
