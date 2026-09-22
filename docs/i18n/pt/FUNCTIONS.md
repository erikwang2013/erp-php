# Sistema ERP Aberto — Manual de funções

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Visão geral

O Sistema ERP Aberto (open-erp) cobre 23 domínios de negócio <!-- stats:modules=23 -->, 227 tabelas de dados <!-- stats:tables=227 -->, oferecendo um sistema de gestão empresarial full-stack que vai de compras/vendas/estoque a produção industrial, e de contabilidade financeira a recursos humanos. Internacionalização: suporte bilíngue Chinês/English, com alternância automática pelo cabeçalho Accept-Language.

> Documentação da API: após iniciar o serviço, acesse `http://localhost:8788/apidoc` para ver a documentação interativa da interface (gerada automaticamente pelo erikwang2013/apidoc-php)

---

## 1. Administração do sistema

### 1.1 Gestão de usuários
- Gestão do ciclo de vida completo da conta de administrador (criar/editar/excluir/habilitar-desabilitar)
- Operações em lote: exclusão em lote, habilitação/desabilitação em lote
- Importação de usuários em lote via Excel, com validação linha a linha + relatório de erros
- Senha armazenada com hash bcrypt; alteração de senha exige confirmação da senha atual
- Operações sensíveis como exclusão exigem segunda confirmação da senha do usuário atual
- Telefone/e-mail/CPF armazenados criptografados, com mascaramento automático nas listagens

### 1.2 Papéis e permissões (RBAC)
- Gestão de papéis: criar/editar/excluir, com slug identificador único
- Árvore de permissões: estrutura hierárquica de níveis ilimitados, com três tipos — menu (visível na navegação), botão (ação na página), API (acesso à interface)
- Formato do identificador de permissão: `{method}.{path}`, ex.: `get.admin/product`, `post.admin/user/batch/destroy`
- Associação muitos-para-muitos papel-permissão; super administrador ignora todas as verificações de permissão
- Middleware AdminPermission armazena as permissões do usuário em cache Redis (TTL=60s)

### 1.3 Configuração do sistema
- Armazenamento chave-valor, com suporte a gerenciamento por grupos
- Tipos de valor: string/inteiro/booleano/JSON/array

### 1.4 Auditoria de operações
- Registro automático de todas as operações POST/PUT/DELETE
- Registro do operador, ação, método, caminho, IP, parâmetros (campos sensíveis mascarados), horário
- Detecção automática da origem em 8 plataformas (Web/Flutter/HarmonyOS/API etc.)
- Consulta somente leitura, sem exclusão ou alteração

### 1.5 Proteção de segurança
- 7 camadas de defesa em profundidade: restrição de métodos HTTP, bloqueio de XSS/Injeção SQL/Path Traversal/Injeção de comandos/CSRF
- Captcha de clique (validação obrigatória no login/registro)
- Rate limiting por janela deslizante no Redis (Lua atômico, padrão 60 vezes/minuto)
- Bloqueio de conta: 5 falhas bloqueiam por 15 minutos
- Limite de sessões simultâneas: no máximo 3 Tokens válidos por usuário
- Cabeçalho CSP, security.txt (RFC 9116)
- Verificação secundária aleatória para operações sensíveis (poster-php)

---

## 2. Produtos e dados básicos

### 2.1 Gestão de produtos
- Ficha do produto: código (único), nome, código de barras, especificação, unidade básica, imagem, descrição
- SKUs multi-especificação: múltiplos SKUs sob o mesmo produto, cada um com código, código de barras e atributos de especificação independentes (JSON)
- Conversão de múltiplas unidades: taxa de conversão entre unidade básica e unidades auxiliares
- Estratégia de preços: preço de compra, preço de atacado, preço de varejo, preço por nível de cliente
- Suporte a busca full-text com ES

### 2.2 Categoria de produtos
- Estrutura de categorias hierárquica de níveis ilimitados
- Suporte a ordenação, habilitação/desabilitação
- Ordenação por arrastar e soltar

### 2.3 Gestão de marcas
- Nome da marca, Logo, descrição, ordenação

### 2.4 Armazéns e localizações
- Gestão de múltiplos armazéns (nome, código, endereço, responsável, telefone de contato)
- Múltiplas localizações por armazém (código único dentro do armazém)

### 2.5 Gestão de fornecedores
- Código do fornecedor, nome, contato, telefone/e-mail (criptografado), endereço
- Dados da conta bancária (armazenados criptografados), inscrição fiscal, alíquota de imposto
- Busca full-text com ES

### 2.6 Gestão de clientes
- Código do cliente, nome, nível do cliente, limite de crédito
- Contato/telefone/e-mail (criptografado)/endereço
- Nível do cliente: nome, taxa de desconto padrão
- Busca full-text com ES

---

## 3. Gestão de compras

### 3.1 Solicitação de compra
- Departamentos/pessoas submetem necessidades de compra
- Fluxo de aprovação: aguardando aprovação → aprovado/rejeitado → convertido em pedido
- Pode ser integrado ao mecanismo de fluxo de aprovação

### 3.2 Pedido de compra
- Vincula fornecedor e itens do produto (quantidade, preço unitário, valor)
- Status: aguardando revisão → revisado → recebimento parcial → recebido → cancelado
- Pode ser criado com base na solicitação ou diretamente

### 3.3 Recebimento de compra (integração entre módulos)
- Recebimento por pedido, com suporte a recebimento parcial
- O recebimento dispara automaticamente: ① entrada no estoque (custeio por média móvel ponderada) ② geração do registro de contas a pagar ③ atualização da quantidade recebida do pedido

### 3.4 Devolução de compra
- Devolução ao fornecedor, gerando baixa de saída de estoque

### 3.5 Liquidação com fornecedores
- Consolidação por fornecedor: valor de compras, pago, a pagar
- Status: não liquidado/liquidado parcialmente/liquidado

---

## 4. Gestão de vendas

### 4.1 Cotação
- Cotação ao cliente, com suporte a conversão em pedido de venda
- Status: rascunho → cotado → convertido em pedido → expirado

### 4.2 Pedido de venda
- Vincula cliente e itens do produto (quantidade, preço unitário, desconto)
- Status: aguardando revisão → revisado → expedição parcial → expedido → cancelado

### 4.3 Expedição de venda (integração entre módulos)
- Expedição por pedido, com suporte a expedição parcial
- A expedição dispara automaticamente: ① saída do estoque (pelo custo médio ponderado) ② geração do registro de contas a receber ③ atualização da quantidade expedida do pedido

### 4.4 Devolução de venda
- Devolução do cliente, gerando entrada de estoque compensatória

### 4.5 Liquidação com clientes e margem bruta
- Consolidação por cliente: valor de vendas, recebido, a receber
- Cálculo da margem bruta por pedido/produto/cliente

---

### 4.6 Controle de crédito do cliente (entregue na v1.4.0)

- Gestão do limite de crédito: mantém por cliente o limite concedido, a ocupação e as regras de bloqueio
- Bloqueio antes do pedido/expedição: CreditControlService assertOrderCreate / assertDeliveryCreate / guard rejeita pedidos acima do limite e devolve o motivo

---

## 5. Gestão de estoque

### 5.1 Estoque em tempo real
- Precisão de quatro dimensões: armazém + localização + lote + SKU
- Suporte a múltiplos armazéns e múltiplas localizações
- Consulta de estoque em tempo real

### 5.2 Fluxo de entrada/saída de estoque
- Todas as variações de estoque registradas de forma unificada (direção, quantidade, custo, documento de origem, horário)

### 5.3 Rastreamento de lotes
- Data de fabricação, data de validade, número do lote
- Registro do lote nas entradas e saídas

### 5.4 Rastreamento de números de série
- Gestão de números de série únicos
- Registro do status nas entradas e saídas (em estoque/expedido)

### 5.5 Custeio
- Método da média móvel ponderada
- Fórmula: novo preço médio = (valor total do estoque anterior + valor total desta entrada) / (quantidade do estoque anterior + quantidade desta entrada)
- Recálculo automático a cada entrada; saídas custeadas pelo preço médio atual

### 5.6 Transferência de estoque
- Transferência entre armazéns/localizações
- Status: aguardando transferência → transferido (saída) → recebido (entrada) → concluído
- Geração automática dos fluxos de saída/entrada

### 5.7 Gestão de inventário (contagem)
- Inventário planejado (por armazém/categoria) + inventário dinâmico (por SKU)
- Registro da quantidade contábil vs. quantidade real
- As diferenças geram automaticamente fluxos de sobra/falta de estoque

### 5.8 Alertas de estoque
- Definição de limites superior/inferior por SKU+armazém
- Registro automático de log de alerta abaixo do limite inferior/acima do limite superior

---

### 5.9 Rastreabilidade de lotes/números de série e alerta de validade (entregue na v1.4.0)

- Cadeia de rastreabilidade: TraceService forward/backward rastreia destino/origem dos lotes nos dois sentidos; o serial mantém o registro de números de série
- Alerta de validade: expiryAlert lembra lotes próximos do vencimento e já vencidos

---

## 6. Gestão financeira

### 6.1 Contas a receber/a pagar
- Geradas automaticamente pelo recebimento de compra/expedição de venda
- Status: não compensado → parcialmente compensado → compensado
- Proteção de idempotência para o mesmo documento de origem

### 6.2 Gestão de recebimentos
- Múltiplas contas (dinheiro/banco/WeChat/Alipay)
- Após a revisão, atualiza automaticamente o saldo da conta e o diário de caixa
- Suporte à compensação de contas a receber

### 6.3 Gestão de pagamentos
- Mesma lógica dos recebimentos, na direção oposta
- Suporte à compensação de contas a pagar

### 6.4 Diário de caixa e bancos
- Registro de fluxos de receita/despesa por conta+data
- Saldo da conta bancária atualizado em tempo real

### 6.5 Reembolso de despesas
- Fluxo: envio → aprovação → pagamento
- Após o pagamento, gera automaticamente o comprovante de pagamento + diário

### 6.6 Demonstração de resultados
- Consolidação mensal: receita operacional, custo operacional, despesas, lucro
- Armazenamento em snapshot (year+month únicos)

### 6.7 Ativo imobilizado
- Ciclo de vida completo do ativo: aquisição → uso → depreciação → baixa
- Depreciação linear: (valor original - valor residual) / meses de uso
- Provisão mensal de depreciação, com geração automática dos registros
- Registros: valor original, valor residual, vida útil, depreciação mensal, depreciação acumulada, valor líquido

### 6.8 Gestão tributária
- Múltiplos impostos: ICMS/IPI/IRPJ/ISS (análogos a IVA/imposto de renda/imposto sobre circulação)
- Alíquotas configuráveis (inclui 4 alíquotas padrão nos dados de seed)
- Vinculação aos documentos de compra/venda, com registro automático do valor do imposto

### 6.9 Multimoeda
- Gestão de moedas: CNY/USD/EUR/JPY (inclui 4 moedas padrão nos dados de seed)
- Identificação da moeda base
- Taxas de câmbio gerenciadas por data de vigência

### 6.10 Gestão orçamentária
- Elaboração do orçamento anual: por centro de custo + conta contábil + mês
- Análise comparativa orçamento vs. realizado (taxa de execução + variação)
- Status: rascunho → aprovado → em execução → encerrado

### 6.11 Centro de custo/centro de lucro
- Estrutura hierárquica em árvore
- Acumulação de custos + rateio de despesas
- Apuração independente do centro de lucro

---

### 6.12 Fechamento de período / contabilidade multi-organização / consolidação de relatórios (entregue na v1.4.0)

- Fechamento de resultado do período: agrega por período os saldos das contas de resultado (receitas - despesas = lucro líquido, status=calculated)
- O fechamento não gera lançamento contábil (faltam a configuração da conta de lucros acumulados e a regra anti-duplicação) e não tem endpoint em controller — não entrou na entrega da v1.4.0 e segue pendente
- Contabilidade multi-organização: CompanyController / LedgerPeriodController com entidade contábil independente e período contábil (entregue na v1.4.0)
- Motor de relatórios consolidados (entregue na v1.4.0): ConsolidationService rateToBase/translateLedger converte pela taxa de fechamento (recusa quando falta a taxa), addElimination elimina entre subsidiárias (com validação do equilíbrio débito/crédito), generateDraft emite o relatório (períodos já emitidos leem o snapshot; sem snapshot recalcula em tempo real pelos lançamentos aprovados), issue impede emissão duplicada, latest/list; o resultado fica em FinanceConsolidationReport
- Testes: PeriodCloseServiceTest 4 casos + ConsolidationServiceTest 3 casos

### 6.13 Custeio de estoque e de produção (entregue na v1.4.0)

- Requisição de material e apropriação de custos: MaterialIssueController lança a saída de materiais, e CostEntryController + MfgCostService apropriam material / mão de obra / custos indiretos por ordem de produção
- Regra de lançamento: MfgCostVoucherRule gera o lançamento do custo dos produtos acabados e da transferência de variações (integração com a §12 Ordens de produção)

### 6.14 Títulos e conciliação bancária (entregue na v1.4.0)

- Ciclo de vida completo dos títulos: FinanceBillService store/update/endorse (endosso)/discount (desconto)/collect (cobrança)/cash (resgate)/reject + dueWarnings de alerta de vencimento
- Conciliação bancária: BankReconService importStatement importa o extrato, autoReconcile/manualReconcile/unreconcile fazem a baixa e reconReport gera o relatório de conciliação

### 6.15 Pool de notas de entrada e faturamento eletrônico (entregue na v1.4.0)

- Pool de entradas: TaxInvoicePoolService registerOne/registerBatch/verify/check/deduct/deductStats; a verificação usa MockTaxVerifier (o canal real da autoridade fiscal é um ponto de adaptação)
- Faturamento eletrônico: EInvoiceService issueInvoice/voidInvoice/issueLogs, via EInvoiceAdapter/MockEInvoiceAdapter (o canal real da autoridade fiscal é um ponto de adaptação)

---

## 7. CRM

### 7.1 Gestão de clientes
- Ficha do cliente (vinculada ao cliente dos dados básicos)
- Gestão de múltiplos contatos (marcação do contato principal)
- Telefone/e-mail dos contatos armazenados criptografados

### 7.2 Registros de acompanhamento
- Formas de acompanhamento: telefone/visita/e-mail/mensagem/outros
- Registro do conteúdo do acompanhamento, próximo acompanhamento planejado, data do próximo acompanhamento
- Vincula cliente e contato

### 7.3 Campanhas de marketing
- Ciclo de vida completo da campanha: planejada → em andamento → concluída → cancelada
- Múltiplos canais: e-mail/SMS/telefone/eventos/redes sociais
- Acompanhamento dos clientes participantes, estatísticas de taxa de conversão
- Comparação de orçamento vs. gasto real

### 7.4 Tickets de serviço
- Gestão de tickets: aguardando tratamento → em tratamento → resolvido → encerrado
- Prioridades: baixa/média/alta/urgente
- Categorias: suporte técnico/reclamação/consulta/troca-devolução/outros
- Atribuição do responsável + respostas (públicas/anotações internas)

### 7.5 Relatórios analíticos de clientes
- 6 indicadores principais: novos clientes/clientes ativos/taxa de retenção/ticket médio/CLV/taxa de resolução de tickets
- Geração automática de relatórios (snapshot JSON dos dados)
- Suporte a mensal/trimestral/anual

---

### 7.6 Motor de valor do cliente (entregue na v1.4.0)

- Membros e saldo armazenado: MemberService openMember/recharge/consume/refund
- Pontos e cupons: earnPoints/consumePoints/expirePoints no extrato de pontos + issueCoupon/redeemCoupon para baixa de cupons (MemberController / CouponController)

---

## 8. Mecanismo de fluxo de aprovação

### 8.1 Modelos de fluxo de trabalho
- Cadeias de aprovação configuráveis: diferentes fluxos de aprovação por tipo de documento
- Nós de aprovação: aprovação sequencial, com roteamento condicional (por valor, departamento etc.)
- Tipos de aprovador: pessoa específica/papel/chefe do departamento/superior imediato
- Suporte a rejeição e delegação

### 8.2 Operações de aprovação
- Envio → aprovação em cascata → aprovação/rejeição/retirada
- Lista de minhas aprovações (pendentes + concluídas)
- Rastreamento completo dos registros de aprovação

---

### 8.3 Designer visual de fluxos (entregue na v1.4.0)

- Orquestração em canvas: WorkflowDesignerController lê e grava definições de nós/arestas (persistidas em canvas_json)
- Mesma origem do motor de aprovação: após a publicação, a definição do canvas dirige o fluxo da cadeia de aprovação

---

## 9. Sistema de notificações de mensagens

### 9.1 Gestão de notificações
- Mensagens no sistema: status não lida/lida
- Modelos de notificação: suporte a substituição de variáveis (ex.: "Há uma aprovação pendente de {solicitante}")
- Múltiplos canais: notificação no sistema (implementada) → e-mail (implementada via driver de arquivo de log, SMTP a conectar) → WeCom/DingTalk (pontos de adaptação reservados)
- Preferências de notificação do usuário

### 9.2 Notificações automáticas
- Lembretes de tarefas de aprovação pendentes
- Envio de alertas de estoque
- Notificações de atribuição de tickets
- Envio unificado via NotificationService

---

## 10. Gestão de projetos

### 10.1 Projetos
- Ciclo de vida completo do projeto: planejando → em andamento → atrasado → concluído → cancelado
- Prioridades: baixa/média/alta/urgente
- Comparação de orçamento do projeto vs. custo real
- Agregação automática do progresso das tarefas no progresso do projeto
- Vincula cliente e gerente de projeto designado

### 10.2 Decomposição de tarefas WBS
- Estrutura de tarefas em árvore (tarefas pai/filho de níveis ilimitados)
- Suporte a dados de gráfico de Gantt (dependências de tarefas, linha do tempo)
- Status da tarefa: a iniciar → em andamento → concluída → atrasada
- Horas estimadas vs. horas reais

### 10.3 Registro de horas
- Registro de horas por projeto/tarefa/pessoa/data
- Consolidação automática das horas reais das tarefas
- Suporte à apuração de custos do projeto

---

### 10.4 Custo e desvio orçamentário de projetos (entregue na v1.4.0)

- Lançamento de custos: ProjectCostService createManual para lançamento manual + generateFromTimesheet converte por horas × taxa do funcionário
- Visão de resultado: projectPnl compara a margem bruta do projeto com o orçamento

---

## 11. Gestão de recursos humanos

### 11.1 Estrutura organizacional
- Departamentos: estrutura hierárquica em árvore
- Cargos: por departamento, com suporte a ordenação
- Ficha do funcionário: código, nome, sexo, data de nascimento, data de admissão, status
- Campos sensíveis criptografados: telefone, e-mail, CPF, conta bancária

### 11.2 Gestão de ponto
- Regras de ponto: horários de entrada/saída, tolerância de atraso, tolerância de saída antecipada
- Registros de marcação: marcação de entrada/saída, cálculo automático dos minutos de atraso/saída antecipada
- Status: normal/atrasado/saída antecipada/falta de marcação/ausência/viagem a trabalho
- Gestão de afastamentos: férias/ausência pessoal/doença/casamento/licença-maternidade/banco de horas

### 11.3 Gestão de salários
- Configuração de itens salariais: itens de rendimento/deduções, sujeito a imposto ou não, valor padrão
- Cálculo salarial: salário base + desempenho + horas extras - deduções - imposto de renda = valor líquido
- Suporte à geração em lote do salário mensal
- Confirmação do pagamento do salário

---

### 11.4 Recrutamento e desempenho (entregue na v1.4.0)

- Recrutamento: RecruitService publica/encerra vagas, avança o funil de candidatos, registra entrevistas e envia/aceita/recusa ofertas
- Desempenho: PerformanceService com modelos de indicadores e planos de avaliação, pontuação 360 (submitScore com múltiplos avaliadores) e consolidação

### 11.5 Treinamento, previdência social e holerite (entregue na v1.4.0)

- Treinamento: TrainingService com registro de cursos/inscrições/conclusões/créditos (employeeCredits)
- Previdência social: SocialSecurityService com regras de base (createRule/setRate), vínculo e desvínculo de funcionários bind/unbind, simulação calculate e detalhamento por funcionário employeeSocialDetail
- Holerite: PayslipService view abre os itens salariais em modo somente leitura (proventos/descontos/líquido, com complemento de previdência social)

---

## 12. Produção industrial

### 12.1 Lista de materiais BOM
- BOM do produto: produto final → componentes → matéria-prima, estrutura hierárquica multinível
- Gestão de versões: rascunho → vigente → inválida
- Detalhes dos componentes: quantidade, unidade, taxa de perda

### 12.2 Ordens de produção
- Criação de ordens de produção com base no BOM
- Status: aguardando produção → em produção → concluída → cancelada
- Quantidade planejada vs. quantidade real
- Datas de início/fim planejadas vs. horários de início/fim reais

### 12.3 Roteiros de produção
- Definição do fluxo de operações por produto
- Cada operação vinculada a uma estação de trabalho e tempo padrão
- Ordenação das operações

### 12.4 Estações de trabalho
- Código da estação, nome, capacidade (por hora)
- Habilitação/desabilitação

### 12.5 MRP — Planejamento de necessidades de materiais
- Cálculo da necessidade líquida: necessidade total - recebimento planejado - estoque atual = necessidade líquida
- Geração do plano por período (ano+mês)
- Status: rascunho → gerado → confirmado

---

### 12.6 Apontamento de operações e salário por peça (entregue na v1.4.0)

- Apontamento: WorkReportService audit audita o apontamento de operações
- Salário por peça: PieceWageService accumulate no registro por peça / periodSummary consolidado por período

### 12.7 Terceirização e baixa (entregue na v1.4.0)

- SubcontractService: auditIssue audita a saída de material para terceiros → auditReceive fecha o ciclo com a baixa no recebimento

### 12.8 Carga de capacidade (entregue na v1.4.0)

- MfgCapacityService: calendar com o calendário de capacidade das estações de trabalho, setException/removeException para exceções de capacidade e report com a análise de carga

---

## 13. Construtor de relatórios personalizados

### 13.1 Modelos de relatório
- Campos personalizados: seleção de campos de tabelas de dados, agregações (soma/contagem/média/máximo/mínimo)
- Filtros personalizados: texto/dropdown/intervalo de datas/intervalo numérico
- Tipos de gráfico: tabela/colunas/linhas/pizza/indicador KPI
- Agrupamento por módulo (produtos/compras/vendas/estoque/finanças/CRM/RH/industrial/projetos)

### 13.2 Execução de relatórios
- Geração dinâmica de SQL (com base na configuração de campos e filtros)
- Proteção por lista branca de nomes de tabelas (analisada do install.sql)
- Snapshot do conjunto de resultados (armazenado em JSON)

### 13.3 Relatórios agendados
- Frequência de agendamento: diária/semanal/mensal
- Configuração de destinatários
- Execução automática + armazenamento dos resultados

---

## 14. Painéis (Dashboards)

### 14.1 Visão geral operacional
- Vendas e compras de hoje/do mês
- Total a receber/a pagar, valor total do estoque, margem bruta
- Cache Redis por 5 minutos

### 14.2 Painel de vendas
- Tendência de vendas, Top 10 de clientes
- Suporte à alternância de intervalo de tempo

### 14.3 Painel de estoque
- Valor total do estoque, estatísticas de alertas (abaixo do limite inferior/acima do limite superior)
- Tendência de entrada/saída (por dia/direção)

### 14.4 Painel financeiro
- Total a receber/a pagar, recebimentos e pagamentos do mês
- Consolidação dos saldos de caixa e bancos

---

## Fluxo de dados entre módulos

```
采购收货 → 自动入库(移动加权平均成本) → 生成应付记录
销售发货 → 自动出库 → 生成应收记录
收付款 → 核销应收应付 → 更新日记账
盘点差异 → 自动生成盈亏出入库流水
审批提交 → 工作流引擎路由 → 逐级审批 → 通知推送
费用报销打款 → 自动生成付款单 + 日记账
资产折旧 → 按月计提 → 成本分摊到成本中心
MRP 运算 → BOM 展开 → 净需求计算 → 生成采购/生产建议
请假审批 → 通过后更新考勤状态
生产完工 → 自动入库(产成品) + 扣减原材料库存
工时记录 → 汇总到任务 → 聚合到项目成本
```

---

## 15. Função de exportação

### 15.1 Exportação Excel
- Todas as páginas de listagem suportam ?export=excel
- Geração de .xlsx com PhpSpreadsheet, cabeçalho azul com texto branco + primeira linha congelada + filtro automático
- Mascaramento automático de campos sensíveis

### 15.2 Exportação PDF
- O painel de dados do dashboard suporta ?export=pdf
- Renderização com Dompdf, A4 paisagem
- Informações de copyright não removíveis

---

## 16. Gestão de pedidos (OMS)

### 16.1 Gestão de pedidos
- **Importação de pedidos multicanal**: suporte a manual/web/mobile/api/marketplace/edi/pos
- **Informações estendidas do pedido**: número do pedido no canal, loja, status de atendimento, status de pagamento, prioridade
- **Alocação de estoque**: cálculo do ATP (quantidade prometível) → reserva de estoque (bloqueio pessimista contra sobrevenda)
- **Orquestração de atendimento**: alocação → criação do atendimento → envio ao WMS → separação/embalagem → expedição via TMS
- **Cancelamento de pedido**: liberação automática da reserva de estoque

### 16.2 RMA — troca/devolução
- Criação do RMA (devolução/troca/conserto) → aprovação → devolução → recebimento com entrada no estoque (stockIn) → reembolso
- Suporte à gestão de frete de devolução e valor do reembolso

### 16.3 Gestão de canais
- Código/nome/tipo do canal (direct/marketplace/edi/pos)
- Configuração do canal (JSON), status habilitar-desabilitar

---

## 17. Gestão de armazém (WMS)

### 17.1 Zonas e localizações
- **Zonas**: recebimento/armazenamento/separacão/embalagem/expedição/devolução/inspeção de qualidade
- **Extensão de localizações**: hierarquia corredor→prateleira→nível→posição + código de barras/volume/capacidade de carga/ordem de separação

### 17.2 Fluxo de entrada
- **ASN (aviso prévio de chegada)**: fornecedor→chegada prevista→transportadora→número de rastreamento
- **Tarefa de recebimento**: recebimento no doca→registro da quantidade recebida→inspeção de qualidade
- **Tarefa de putaway (armazenamento)**: geração automática→atribuição→estratégia (fifo/zone_fixed/abc)→confirmação de armazenamento (stockIn)

### 17.3 Fluxo de saída
- **Gestão de ondas (wave)**: agregação de múltiplos pedidos→onda de separação/onda de expedição→prioridade
- **Tarefa de separação (picking)**: por documento/lote/zona/onda→atribuição→confirmação (quantidade real separada)
- **Tarefa de embalagem**: tipos de embalagem (box/bag/pallet)→peso/dimensões

---

## 18. Gestão de transporte (TMS)

### 18.1 Transportadoras
- Código da transportadora/tipo (expressa/fracionada/carga fechada/aérea/marítima/ferroviária)
- Serviços da transportadora: standard/express/overnight/2day/economy + prazo
- Configuração de API: abstração custom/shippo/afterShip/17track

### 18.2 Gestão de fretes
- **Tabela de tarifas**: origem/destino→faixas de peso→tarifa base/tarifa por kg/sobretaxa de combustível
- **Multimoeda**: CNY/USD/EUR etc., vinculada à exchange_rate
- **Comparação de fretes**: consulta de todas as tarifas disponíveis por país de destino+peso, ordenação crescente

### 18.3 Conhecimentos de transporte e rastreamento
- **Conhecimento de transporte**: serviço da transportadora→número de rastreamento→status (aguardando envio→coletado→em trânsito→entregue/exceção/devolvido)
- **Rastreamento logístico**: callback webhook→sincronização automática do status do conhecimento
- **Fatura de frete**: criação→confirmação→pagamento→geração de AP

---

## Apêndice: Escala do projeto

| Dimensão | Quantidade |
|------|------|
| Módulos de negócio | 23 <!-- stats:modules=23 --> |
| Tabelas do banco de dados | 227 <!-- stats:tables=227 --> |
| Modelos de dados | 224 <!-- stats:models=224 --> |
| Controladores | 159 <!-- stats:controllers=159 --> |
| Serviços de negócio | 64 <!-- stats:services=64 --> |
| Rotas da API | 837 (geradas dinamicamente, ver `scripts/check-endpoints.php`, não participam da validação do doc-stats) |
| Middlewares | 11 <!-- stats:middleware=11 --> |
| Arquivos-fonte PHP | 483 <!-- stats:php_files=483 --> |
| Script de instalação do banco | Arquivo único `database/install.sql` (227 tabelas, todas as migrações incorporadas) |
| Páginas front-end (Flutter) | 119 (medido em 2026-09-22: arquivos de página `.dart` em `apps/flutter/lib/app/pages/` (recursivo), não incluído na validação do doc-stats) |
| Páginas front-end (HarmonyOS) | 52 (medido em 2026-09-22: arquivos de página `.ets` em `apps/harmonyos/entry/src/main/ets/pages/` (recursivo), não incluído na validação do doc-stats) |
| Testes unitários | 113 arquivos de teste <!-- stats:test_files=113 --> / 1044<!-- stats:tests=1044 --> casos de teste / 5020<!-- stats:assertions=5020 --> asserções (contagem estática: número de métodos de teste + pontos de chamada de asserção, independente do ambiente de execução) |

> Os números acima são medidos por `bash scripts/doc-stats.sh`; os itens marcados com `<!-- stats:key=value -->` são validados automaticamente pelo CI
> (job docs em `.github/workflows/ci.yml`) contra os fatos do código — qualquer divergência fica vermelha.

---

## 19. Matriz de completude dos módulos (correção de 2026-09-05; lote v1.17.0 de 2026-09-15)

### Legenda de status

| Marcação | Significado |
|------|------|
| ✅ | Concluído — pronto para produção |
| ⚠️ | Esqueleto — CRUD concluído, faltam mecanismos de negócio/front-end |
| 🔴 | Ausente — não implementado |
| 🔵 P0 | Fase do ecossistema front-end |
| 🟢 P1 | Fase de profundidade de negócio |
| 🟡 P2 | Fase de confiabilidade operacional |
| 🟣 P3 | Fase de melhoria da experiência |

### Matriz

| Módulo | API back-end | Lógica de negócio | Flutter | HarmonyOS | Próxima fase |
|------|----------|----------|---------|-----------|----------|
| Administração do sistema | ✅ | ✅ | ⚠️ 9/14 | ⚠️ 5 páginas | 🔵 P0 |
| Dashboards | ✅ | ✅ | ✅ 2 páginas | ⚠️ 1 página | 🔵 P0 |
| Dados básicos de produtos | ✅ | ✅ | ✅ 7/7 | ⚠️ 1/7 | 🔵 P0 |
| Gestão de compras | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestão de vendas | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestão de estoque | ✅ | ✅ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Finanças — lançamentos/contas a receber e a pagar | ✅ | ⚠️ | ✅ 16 páginas | 🔴 | 🔵 P0 |
| Finanças — razão geral/três demonstrações | ⚠️ | 🔴 | ⚠️ 3 páginas (profundidade das ações a verificar) | 🔴 | 🟢 P1 |
| Finanças — consolidação de relatórios | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finanças — contabilidade multi-organização (F1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finanças — fechamento de período | 🔴 | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Finanças — custeio de estoque (F3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| CRM completo | ✅ | ✅ | ✅ 10/10 | 🔴 | 🔵 P0 |
| OMS — gestão de pedidos | ✅ | ✅ | ✅ 4/4 | ⚠️ 4 páginas | 🔵 P0 |
| WMS — gestão de armazém | ✅ | ✅ | ⚠️ 7/8 | ⚠️ 7 páginas | 🔵 P0 |
| TMS — gestão de transporte | ✅ | ✅ | ⚠️ 5/6 | ⚠️ 5 páginas | 🔵 P0 |
| Fluxo de aprovação | ✅ | ✅ | ⚠️ 2/3 | ⚠️ 1 página | v1.4.0 |
| Sistema de notificações | ✅ | ✅ | ⚠️ 1/2 | 🔴 | v1.4.0 |
| Controle de crédito (F7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Cadeia de rastreabilidade/validade próxima (M6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Carga de capacidade (M3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Apontamento de operações/salário por peça/baixa de terceirização (M1+M2) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Designer de fluxos (B3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Modelos de impressão (B1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Recrutamento/desempenho/treinamento/previdência (H1-H4) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Inspeção por leitura de QR (E1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Custo/orçamento de projeto (P1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Títulos/conciliação bancária (F6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Pool de entradas/faturamento eletrônico (F5) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Valor do cliente (C1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Multi-tenant (B5) | ✅ | ⚠️ | 🔴 | 🔴 | v1.4.0, ativação parcial |
| Notificações de canal/campos personalizados (B4+B7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Gestão de projetos | ✅ | ✅ | ✅ 3/3 | 🔴 | 🔵 P0 |
| RH — organização/ponto/férias | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 3 páginas | 🔵 P0 |
| RH — motor de salários | ⚠️ | 🔴 | ⚠️ 2 páginas | 🔴 | 🟢 P1 |
| Manufatura — BOM/produção/MRP | ✅ | ✅ | ⚠️ 5/13 | ⚠️ 5 páginas | v1.4.0 |
| Gestão da qualidade | ✅ | ✅ | ✅ 5/5 | 🔴 | 🟢 P1 |
| Relatórios personalizados | ✅ | ⚠️ | ✅ 2/2 | 🔴 | 🔵 P0 |
| Painéis BI | ✅ | ✅ | ⚠️ 2/3 | 🔴 | 🟣 P3 |
| Gestão de equipamentos EAM | ✅ | ✅ | ⚠️ 4/5 | 🔴 | v1.4.0 |
| Gestão de documentos DMS | ✅ | ✅ | ⚠️ 1/2 | 🔴 | 🟣 P3 |
| Multilíngue (i18n) | ✅ | ✅ | ⚠️ apenas zh/en | ⚠️ apenas zh/en | v1.17.0 |
| Observabilidade | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Rollback de migração/backup | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Estatísticas

| Dimensão | ✅ Concluído | ⚠️ Esqueleto | 🔴 Ausente | N/A | Taxa de conclusão |
|------|---------|----------|---------|-----|--------|
| Módulos (44) | 33 | 11 | 0 | 0 | 75% |
| API back-end | 39 | 4 | 1 | 0 | 89% |
| Lógica de negócio | 33 | 7 | 4 | 0 | 75% |
| Front-end Flutter | 12 | 12 | 18 | 2 | 29% |
| HarmonyOS | 0 | 13 | 29 | 2 | 0% (contagem de ✅; 13 linhas já têm páginas ⚠️) |

> **Critério das estatísticas (correção de 2026-09-05)**: as linhas de módulo contam como «API back-end e lógica de negócio ambas implementadas» — ✅ duplo = concluído,
> e qualquer linha que não chegue a ✅ duplo conta como esqueleto ⚠️ (incluindo as linhas de «ativação parcial», como o middleware de isolamento do multi-tenant B5 ainda não registrado e outros pendentes históricos, ver as evidências de código);
> as linhas API back-end / lógica de negócio são contadas pelas colunas correspondentes da matriz, e o denominador da taxa de conclusão exclui as linhas N/A (observabilidade e rollback de migração não têm front-end).
> **Colunas Flutter / HarmonyOS (critério de «cobertura de ações da página» desde 2026-08-27)**: ✅ = o módulo tem página e o número de arquivos de página ≥ o número de controllers do back-end
> (`n/n` ou contagem de páginas); ⚠️ = tem página mas o número de arquivos de página < o número de controllers do back-end (cobertura parcial); 🔴 = sem página; **a verificar** = a página existe, mas a
> profundidade das ações (ciclo completo de criação/edição/exclusão/consulta) não foi conferida página a página. **A contagem de páginas de cada linha é o retrato de 2026-08-27** (`apps/flutter/lib/app/pages/<módulo>/`
> e `apps/harmonyos/entry/src/main/ets/pages/**`, na época 107 páginas Flutter / 35 páginas HarmonyOS);
> a remedição global de 2026-09-22 dá 119 páginas Flutter / 52 páginas HarmonyOS (**a contagem por linha não foi recalculada no novo critério**; a decisão ✅/⚠️ segue o retrato de 2026-08-27),
> fora da validação do doc-stats do backend; a taxa de conclusão do HarmonyOS em 0% deve-se à contagem de ✅ (0/42), com 13 linhas já tendo páginas (⚠️ cobertura parcial) — não é ausência da coluna inteira.
> **Deduplicação de 2026-09-15**: a matriz continha duas linhas «multi-tenant» (`多租户 (B5)` e `多租户`, com a coluna de lógica de negócio ✅ / ⚠️ contraditória entre si),
> já mescladas no critério acima em uma única linha «Multi-tenant (B5)» contada como ⚠️ (middleware de isolamento não registrado), com as linhas de módulo indo de 45 → 44.
> **Lote v1.17.0 (2026-09-15)**: acrescentada a linha «Multilíngue (i18n)» (1 das 44 linhas marcada como v1.17.0) — os dicionários de 13 idiomas no backend
> (`resource/translations/<locale>/`, 13 diretórios) e a negociação por `Accept-Language` contam como ✅; Flutter e HarmonyOS continuam com dois idiomas (chinês/inglês),
> portanto as duas colunas de front-end contam ⚠️; Angular e React já têm dicionários de 13 idiomas (não participam das colunas desta matriz).
> **Lote v1.4.0 (2026-09-05)**: 21 das 44 linhas estão marcadas como v1.4.0 (incluindo 1 linha de «ativação parcial» = a linha Multi-tenant (B5)),
> cobrindo multi-organização/consolidação de relatórios/custeio de estoque (F1-F3), manufatura M1/M2/M3/M6, crédito F7, títulos/conciliação bancária/pool de entradas/faturamento eletrônico F6/F5,
> RH H1-H4, membros C1, plataforma B1-B5/B7, inspeção E1 e custo de projeto P1; as evidências estão em «Evidências de código» abaixo.

### Evidências de código (correção de 2026-09-05; incluindo o lote v1.17.0)

Base das correções de completude desta versão (a existência dos arquivos pode ser comprovada por `bash scripts/doc-stats.sh` e `find`):

| Módulo | Correção | Evidências de código |
|------|------|----------|
| Gestão da qualidade | 🔴 → ✅ | `app/controller/quality/` (5 controllers) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Painéis BI | 🔴 → ✅ | `app/controller/bi/` (3 controllers: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Gestão de equipamentos EAM | 🔴 → ✅ (+E1 inspeção) | `app/controller/eam/` (5 controllers, incluindo `EamInspectionController.php`) + `app/service/eam/EamInspectionService.php` + `tests/EamModuleTest.php` |
| Gestão de documentos DMS | 🔴 → ✅ | `app/controller/dms/` (2 controllers) + `tests/DmsModuleTest.php` |
| Multi-tenant | ⚠️ → ⚠️ (v1.4.0, ativação parcial) | `app/controller/platform/TenantController.php` + `app/service/platform/TenantService.php` (provision/suspend/resume/expireMark/renew/expiryWarnings com cobrança por vencimento já entregues) + `tests/Integration/TenantScopeIntegrationTest.php`; o middleware de isolamento `app/middleware/TenantScope.php` continua não registrado (o isolamento não está em vigor), por isso é ativação parcial |
| Consolidação de relatórios | ⚠️ → ✅ (entregue na v1.4.0) | `app/service/finance/ConsolidationService.php` (rateToBase/translateLedger com conversão pela taxa de fechamento, addElimination com eliminação entre subsidiárias e validação do equilíbrio débito/crédito, generateDraft com snapshot preferencial / recálculo em tempo real sem snapshot, issue anti-duplicação, latest/list; recusa quando falta a taxa) + resultado em `app/model/FinanceConsolidationReport.php` + `tests/ConsolidationServiceTest.php` (3 casos) |
| Fechamento de período | 🔴 → ⚠️ | `app/service/finance/PeriodCloseService.php:21` `closeProfitAndLoss()` (agregação das contas de resultado já implementada; não gera lançamento de fechamento e não tem endpoint em controller) + `tests/PeriodCloseServiceTest.php` (4 casos) |
| v1.4.0 — contabilidade multi-organização (F1) | novo | `app/controller/finance/CompanyController.php` + `LedgerPeriodController.php` (entidade contábil independente e período contábil) |
| v1.4.0 — estoque e custo de produção (F3) | novo | `app/controller/manufacturing/MaterialIssueController.php` + `CostEntryController.php` + `app/service/manufacturing/MfgCostService.php` + `MfgCostVoucherRule.php` |
| v1.4.0 — execução de manufatura M1/M2/M3/M6 | novo | `app/service/manufacturing/` (WorkReportService/PieceWageService/SubcontractService/MfgCapacityService) + `app/service/inventory/TraceService.php` (rastreabilidade de lotes/números de série e alerta de validade) + os controllers correspondentes WorkReport/PieceWage/Subcontract/Capacity |
| v1.4.0 — recursos e tributos financeiros F6/F5 + F7 | novo | `app/service/finance/FinanceBillService.php` + `BankReconService.php` (importação de extrato/conciliação automática e manual) + `app/service/tax/TaxInvoicePoolService.php` + `EInvoiceService.php` (EInvoiceAdapter/MockEInvoiceAdapter, autoridade fiscal real como ponto de adaptação reservado) + `app/service/sales/CreditControlService.php` (bloqueio por asserção de pedidos acima do limite) |
| v1.4.0 — membros/RH/projetos C1/H1-H4/P1 | novo | `app/service/retail/MemberService.php` + `app/controller/retail/` (MemberController/CouponController) + `app/service/hr/` (RecruitService/PerformanceService/TrainingService/SocialSecurityService/PayslipService) + `app/service/project/ProjectCostService.php` |
| v1.4.0 — plataforma/canais B3/B4/B7/E1 | novo | `app/controller/workflow/WorkflowDesignerController.php` (persistência em canvas_json) + `app/service/notification/` (ChannelDriver/ChannelService/MockChannelDriver/MailMockChannelDriver, com novas tentativas em caso de falha) + `app/controller/notification/NotificationChannelController.php` (`tests/NotificationChannelTest.php` 5 casos) + `app/controller/platform/CustomFieldController.php` + `app/controller/eam/EamInspectionController.php` (inspeção por leitura de QR) |
| v1.17.0 — multilíngue (i18n) | novo | Backend: `resource/translations/` (13 diretórios de idioma zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id, cada idioma com os três arquivos common+modules+validation, cada um dos 11 idiomas com 542 entradas (zh_CN 533, en 30); em `en` o inglês é a chave) + `app/common/I18n.php` (`getLocale()` resolve a primeira tag de `Accept-Language` e mapeia a sub-tag do idioma principal zh*→zh_CN; `trans()` para idiomas diferentes de en percorre `[idioma da requisição, zh_CN, en]`→key e, para `en`, não cai no chinês) + `config/translation.php` + script gerador `scripts/gen-be-locales.mjs`; frontend: `apps/react/src/lib/i18n/` (`index.tsx` + `zhEn` + 11 arquivos de idioma) e `apps/angular/src/app/core/` (`zh-en/` (index + part1..4) + `zh-{ar,bn,de,es,fr,hi,id,ja,ko,pt,ru}.ts`) (dicionários dos 11 novos idiomas carregados sob demanda por idioma, com Angular 1453 / React 1447 chaves) + `scripts/gen-fe-locales.mjs`; ponto de troca = ícone globe na barra superior + menu suspenso do centro pessoal; Flutter (`apps/flutter/lib/l10n/`) e HarmonyOS (`entry/src/main/resources/`) continuam com dois idiomas (chinês/inglês) |

> Especificação detalhada do roteiro de design: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
