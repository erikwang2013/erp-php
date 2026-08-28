# Sistema ERP Aberto — Manual de funções

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Visão geral

O Sistema ERP Aberto (open-erp) cobre 19 domínios de negócio <!-- stats:modules=19 -->, 163 tabelas de dados <!-- stats:tables=163 -->, oferecendo um sistema de gestão empresarial full-stack que vai de compras/vendas/estoque a produção industrial, e de contabilidade financeira a recursos humanos. Internacionalização: suporte bilíngue Chinês/English, com alternância automática pelo cabeçalho Accept-Language.

> Documentação da API: após iniciar o serviço, acesse `http://localhost:8787/apidoc` para ver a documentação interativa da interface (gerada automaticamente pelo hg/apidoc)

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
- 18 camadas de defesa em profundidade: restrição de métodos HTTP, bloqueio de XSS/Injeção SQL/Path Traversal/Injeção de comandos/CSRF
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
| Módulos de negócio | 19 <!-- stats:modules=19 --> |
| Tabelas do banco de dados | 163 <!-- stats:tables=163 --> |
| Modelos de dados | 161 <!-- stats:models=161 --> |
| Controladores | 123 <!-- stats:controllers=122 --> |
| Serviços de negócio | 27 <!-- stats:services=27 --> |
| Rotas da API | 198 (geradas dinamicamente, ver `scripts/check-endpoints.php`, não participam da validação do doc-stats) |
| Middlewares | 11 <!-- stats:middleware=11 --> |
| Arquivos-fonte PHP | 343 <!-- stats:php_files=339 --> |
| Script de instalação do banco | Arquivo único `database/install.sql` (163 tabelas, todas as migrações incorporadas) |
| Páginas front-end (Flutter) | 7 (estatística do front-end, não incluída na validação do doc-stats) |
| Páginas front-end (HarmonyOS) | 4 (estatística do front-end, não incluída na validação do doc-stats) |
| Testes unitários | 50 arquivos de teste <!-- stats:test_files=59 --> / 442 casos de teste / 2238 asserções (tests/assertions flutuam com as versões de patch do PHP e extensões, não participam da validação precisa do stats) |

> Os números acima são medidos por `bash scripts/doc-stats.sh`; os itens marcados com `<!-- stats:key=value -->` são validados automaticamente pelo CI
> (job docs em `.github/workflows/ci.yml`) contra os fatos do código — qualquer divergência fica vermelha.

---

## 19. Matriz de completude dos módulos (correção de 2026-08-16)

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
| Administração do sistema | ✅ | ✅ | ⚠️ 7/10 | ⚠️ 4/10 | 🔵 P0 |
| Dashboards | ✅ | ✅ | ⚠️ Básico | ⚠️ Básico | 🔵 P0 |
| Dados básicos de produtos | ✅ | ✅ | ⚠️ 3/7 | ⚠️ 1/7 | 🔵 P0 |
| Gestão de compras | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestão de vendas | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestão de estoque | ✅ | ✅ | ⚠️ Básico | ⚠️ Básico | 🔵 P0 |
| Finanças — vouchers/contas a receber e a pagar | ✅ | ⚠️ | ⚠️ 2/10 | 🔴 | 🔵 P0 |
| Finanças — razão geral/três demonstrações | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Finanças — fechamento de exercício/consolidação | 🔴 | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| CRM completo | ✅ | ✅ | ⚠️ 1/8 | 🔴 | 🔵 P0 |
| OMS — gestão de pedidos | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| WMS — gestão de armazém | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| TMS — gestão de transporte | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Fluxo de aprovação | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Sistema de notificações | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Gestão de projetos | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| RH — organização/ponto/afastamentos | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| RH — motor de salários | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Industrial — BOM/produção/MRP | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Gestão da qualidade | ✅ | ✅ | 🔴 | 🔴 | 🟢 P1 |
| Relatórios personalizados | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| Painéis BI | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Gestão de equipamentos EAM | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Multitenancy | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟣 P3 |
| Gestão de documentos DMS | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Observabilidade | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Rollback de migração/backup | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Estatísticas

| Dimensão | ✅ Concluído | ⚠️ Esqueleto | 🔴 Ausente | N/A | Taxa de conclusão |
|------|---------|----------|---------|-----|--------|
| Módulos (27) | 14 | 12 | 1 | 0 | 52% |
| API back-end | 19 | 7 | 1 | 0 | 70% |
| Lógica de negócio | 14 | 7 | 6 | 0 | 52% |
| Front-end Flutter | 0 | 8 | 17 | 2 | 0% |
| HarmonyOS | 0 | 6 | 19 | 2 | 0% |

> **Critério das estatísticas (correção de 2026-08-16)**: as linhas de módulos contam como «API back-end e lógica de negócio ambas implementadas»;
> as linhas API back-end / lógica de negócio são contadas pelas colunas correspondentes da matriz (nesta versão, QMS/EAM/DMS/BI foram corrigidos para ✅
> conforme o estado atual do código, e multitenancy para ⚠️, evidências na seção «Evidências de código» abaixo); Flutter / HarmonyOS são estatísticas
> de trabalho das páginas front-end (as 2 linhas observabilidade e rollback de migração marcadas como N/A), não incluídas na validação do doc-stats do back-end.

### Evidências de código (correção de 2026-08-16)

Base das correções de completude desta versão (a existência dos arquivos pode ser comprovada por `bash scripts/doc-stats.sh` e `find`):

| Módulo | Correção | Evidências de código |
|------|------|----------|
| Gestão da qualidade | 🔴 → ✅ | `app/controller/quality/` (5 controladores) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Painéis BI | 🔴 → ✅ | `app/controller/bi/` (3 controladores: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Gestão de equipamentos EAM | 🔴 → ✅ | `app/controller/eam/` (4 controladores) + `tests/EamModuleTest.php` |
| Gestão de documentos DMS | 🔴 → ✅ | `app/controller/dms/` (2 controladores) + `tests/DmsModuleTest.php` |
| Multitenancy | 🔴 → ⚠️ | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` + `tests/Integration/TenantScopeIntegrationTest.php` (defeito conhecido: o ID estático do tenant não se propaga pelos modelos, por isso é esqueleto e não conclusão) |

> Especificação detalhada do roteiro de design: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
