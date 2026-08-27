# Especificação de design dos módulos de negócio ERP

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. Visão geral

Sobre a base de administração do sistema `service/` existente, expandir os três domínios de negócio — compras, vendas e inventário, financeiro, CRM — para construir um sistema ERP completo.
Todo o código é implantado de forma monolítica em `service/app/`, com os módulos em camadas por diretório.

### 1.1 Planejamento por fases

| Fase | Módulos | Observação |
|------|------|------|
| Phase 1 | Dados básicos de produtos + Compras + Vendas + Inventário + Financeiro + CRM | Ciclo fechado do negócio principal |
| Phase 2 | Gestão de manufatura + Gestão de projetos | Expansão futura |

### 1.2 Stack de tecnologia (reutilizando o existente)

- PHP 8.3+, webman v2, MySQL 8.0+
- Chaves primárias BIGINT geradas por snowflake-php
- IDs na camada de API criptografados/descriptografados com hashids
- Autenticação JWT, criptografia de dados sensíveis — tudo com os pacotes erikwang2013/*
- Prefixo de tabelas `erp_`, soft deletes, funções globais sem `\`

---

## 2. Estrutura do projeto

```
service/app/
├── admin/controller/          # 系统管理控制器（已有，保持不变）
├── api/v1/controller/         # 客户端API（已有 + 扩展）
├── common/                    # 共享工具（已有 Snowflake/Hashids/Encryption）
├── middleware/                # 全局中间件（已有7个）
├── model/                     # 所有数据模型（跨模块共享）
├── service/                   # 业务逻辑层（按模块分目录）
│   ├── product/               # 商品与基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── controller/                # 业务模块控制器
│   ├── product/               # 商品基础数据
│   ├── purchase/              # 采购
│   ├── sales/                 # 销售
│   ├── inventory/             # 库存
│   ├── finance/               # 财务
│   └── crm/                   # CRM
├── queue/                     # 队列任务（已有 + 业务队列）
├── process/                   # 进程（已有 Http, Monitor）
└── functions.php              # 全局辅助函数（已有）
```

### 2.1 Responsabilidades por camada

| Camada | Local dos arquivos | Responsabilidade |
|----|----------|------|
| Controller | `app/controller/{module}/` | Validação de parâmetros, formatação de resposta, chamada de Services |
| Service | `app/service/{module}/` | Lógica de negócio, integração entre módulos, gestão de transações |
| Model | `app/model/` | Models de dados, relações, escopos de consulta, trait encryptable |

---

## 3. Lista de funcionalidades dos módulos

### 3.1 Produtos e dados básicos

| Funcionalidade | Observação |
|------|------|
| Ficha do produto | Nome, código, código de barras, categoria (em árvore), marca, atributos de especificação |
| SKU multi-especificação | Múltiplas especificações do mesmo produto, cada uma com SKU, código de barras e preço independentes |
| Conversão multi-unidade | Taxa de conversão entre unidade básica ↔ unidades auxiliares |
| Estratégia de preços | Preço de compra, preço de atacado, preço de varejo, preço por nível de cliente |
| Gestão de categorias | Árvore de categorias de níveis ilimitados, com ordenação por arrastar e soltar |
| Gestão de marcas | CRUD de marcas |
| Gestão de armazéns | Múltiplos armazéns, cada armazém com múltiplas localizações |
| Gestão de localizações | Posições de armazenamento sob o armazém, código único |
| Ficha do fornecedor | Nome, contato, telefone, endereço, conta bancária, alíquota de imposto |
| Ficha do cliente | Nome, contato, telefone, endereço, nível do cliente, limite de crédito |

### 3.2 Módulo de compras

| Funcionalidade | Observação |
|------|------|
| Solicitação de compra | Departamentos/pessoas submetem necessidades de compra, suporta fluxo de aprovação |
| Pedido de compra | Baseado na solicitação ou criado diretamente, vinculando fornecedor, produtos, quantidade, preço unitário |
| Recebimento de compra | Recebimento conforme o pedido, gera entrada de estoque, suporta recebimento em lotes |
| Devolução de compra | Devolução ao fornecedor, gera saída de estoque para estorno |
| Conciliação com fornecedor | Resumo por fornecedor + período: valor comprado, pago, a pagar |
| Liquidação de compra | Conciliação entre recebimento de compra e pagamento |

### 3.3 Módulo de vendas

| Funcionalidade | Observação |
|------|------|
| Cotação | Cotação ao cliente, suporta conversão em pedido de venda |
| Pedido de venda | Cliente faz o pedido, vinculando produtos, quantidade, preço unitário, desconto |
| Envio de venda | Envio conforme o pedido, gera saída de estoque, suporta envio em lotes |
| Devolução de venda | Devolução do cliente, gera entrada de estoque para estorno |
| Conciliação com cliente | Resumo por cliente + período: valor vendido, recebido, a receber |
| Liquidação de venda | Conciliação entre envio de venda e recebimento |
| Margem de venda | Cálculo de margem por pedido/produto/cliente |

### 3.4 Módulo de inventário

| Funcionalidade | Observação |
|------|------|
| Estoque em tempo real | Quantidade por armazém + localização + lote + SKU |
| Rastreamento de lote | Data de produção, data de validade, número do lote |
| Rastreamento de serial | Número de série único, registrado na entrada e saída |
| Fluxo de entrada/saída | Log unificado de todas as mudanças de estoque (nº do documento de origem + tipo + quantidade + direção) |
| Transferência de inventário | Transferência entre armazéns/localizações, gera documentos de entrada/saída da transferência |
| Tarefa de contagem | Contagem planejada (por armazém/categoria) + contagem dinâmica (por SKU) |
| Diferença de contagem | Sobras/faltas geram automaticamente fluxos de entrada/saída |
| Alerta de inventário | Limites superior/inferior por SKU + armazém; alerta abaixo do mínimo ou acima do máximo |
| Cálculo de custo | Método de média móvel ponderada; o custo é recalculado a cada entrada |

### 3.5 Módulo financeiro

| Funcionalidade | Observação |
|------|------|
| Plano de contas | Árvore de contas (ativos/passivos/patrimônio líquido/receitas/despesas), personalizável |
| Contas a receber/pagar | Geradas automaticamente por documentos de vendas/compras, conciliação manual |
| Recibo de recebimento | Recebimento multi-conta, multi-método (dinheiro/banco/WeChat/Alipay) |
| Documento de pagamento | Pagamento multi-conta, multi-método |
| Conciliação | Recebimento concilia contas a receber; pagamento concilia contas a pagar |
| Diário de caixa e bancos | Registro de fluxos de receitas/despesas por conta + data |
| Reembolso de despesas | Submissão → aprovação → pagamento, vinculado à conta |
| Demonstração de resultados | Resumo mensal de receitas/custos/despesas/lucro |

### 3.6 Módulo CRM

| Funcionalidade | Observação |
|------|------|
| Gestão de clientes | Ficha do cliente (vinculada ao cliente dos dados básicos) |
| Gestão de contatos | Múltiplos contatos sob o cliente |
| Registro de acompanhamento | Método, hora, conteúdo e próximo plano de acompanhamento |
| Funil de vendas | Configuração de etapas + estimativa de valor de oportunidades + taxa de conversão por etapa |

---

## 4. Design das tabelas do banco de dados

Todas as tabelas com prefixo `erp_`, `id` BIGINT não auto-incrementado, com `created_at`/`updated_at`/`deleted_at`.

### 4.1 Dados básicos de produtos

```
erp_product             商品主表
erp_product_sku         商品SKU/规格
erp_product_unit        多单位换算
erp_product_price       价格策略
erp_category            商品分类（树形 parent_id）
erp_brand               品牌
erp_warehouse           仓库
erp_location            库位
erp_supplier            供应商
erp_customer            客户
erp_customer_level      客户等级
```

### 4.2 Módulo de compras

```
erp_purchase_apply       采购申请
erp_purchase_apply_item  申请明细
erp_purchase_order       采购订单
erp_purchase_order_item  订单明细
erp_purchase_receive     采购收货主表
erp_purchase_receive_item 收货明细
erp_purchase_return      采购退货主表
erp_purchase_return_item 退货明细
erp_purchase_settlement  供应商结算记录
```

### 4.3 Módulo de vendas

```
erp_sales_quotation      报价单主表
erp_sales_quotation_item 报价明细
erp_sales_order          销售订单主表
erp_sales_order_item     订单明细
erp_sales_delivery       销售发货主表
erp_sales_delivery_item  发货明细
erp_sales_return         销售退货主表
erp_sales_return_item    退货明细
erp_sales_settlement     客户结算记录
```

### 4.4 Módulo de inventário

```
erp_inventory            实时库存
erp_inventory_batch      批次信息
erp_inventory_serial     序列号记录
erp_inventory_flow       出入库流水
erp_transfer             调拨单主表
erp_transfer_item        调拨明细
erp_check_task           盘点任务
erp_check_detail         盘点明细
erp_inventory_alert_rule 库存预警规则
erp_inventory_alert_log  库存预警日志
erp_cost_record          成本计算记录
```

### 4.5 Módulo financeiro

```
erp_finance_account      会计科目
erp_finance_voucher      记账凭证
erp_finance_voucher_item 凭证分录
erp_finance_ar_ap        应收应付明细
erp_finance_receipt      收款单
erp_finance_payment      付款单
erp_finance_cash_journal 现金银行日记账
erp_finance_expense      费用报销单
erp_finance_expense_item 报销明细
erp_finance_profit       利润表快照
erp_finance_bank_account 银行账户
```

### 4.6 Módulo CRM

```
erp_crm_funnel_stage     销售漏斗阶段配置
erp_crm_opportunity      商机
erp_crm_follow_record    跟进记录
erp_crm_contact          联系人
```

---

## 5. Rotas da API

Mantém o namespace `/admin/*` com a cadeia completa de middlewares (Auth → Permission → OperationLog).

```
# 商品基础数据
/admin/product/*          商品/分类/品牌 CRUD
/admin/warehouse/*        仓库/库位 CRUD
/admin/supplier/*         供应商 CRUD
/admin/customer/*         客户/客户等级 CRUD

# 采购
/admin/purchase/apply/*      采购申请 + 审批
/admin/purchase/order/*      采购订单
/admin/purchase/receive/*    采购收货
/admin/purchase/return/*     采购退货
/admin/purchase/settlement/* 供应商结算

# 销售
/admin/sales/quotation/*     报价单（含转订单）
/admin/sales/order/*         销售订单
/admin/sales/delivery/*      销售发货
/admin/sales/return/*        销售退货
/admin/sales/settlement/*    客户结算

# 库存
/admin/inventory/*           实时库存查询
/admin/inventory/batch/*     批次管理
/admin/inventory/serial/*    序列号管理
/admin/inventory/flow/*      出入库流水
/admin/inventory/transfer/*  调拨
/admin/inventory/check/*     盘点
/admin/inventory/alert/*     预警规则

# 财务
/admin/finance/account/*     会计科目
/admin/finance/voucher/*     记账凭证
/admin/finance/receipt/*     收款单
/admin/finance/payment/*     付款单
/admin/finance/cash/*        现金银行日记账
/admin/finance/expense/*     费用报销
/admin/finance/report/*      财务报表

# CRM
/admin/crm/opportunity/*     商机
/admin/crm/follow/*          跟进记录
/admin/crm/funnel/*          漏斗阶段配置
/admin/crm/contact/*         联系人

# 仪表盘（扩展）
/admin/dashboard/sales       销售面板
/admin/dashboard/inventory   库存面板
/admin/dashboard/finance     财务面板
```

A API do cliente `/api/v1/*` fornece interfaces leves (consulta de produtos, pedidos, status de pedidos etc.) para o Flutter App / HarmonyOS.

---

## 6. Fluxo de dados entre módulos

```
采购收货 → inventory_flow(入库) → inventory(+数量) → cost_record(重算均价)
       → finance_ar_ap(应付)

销售发货 → inventory_flow(出库) → inventory(-数量) → cost_record(记录成本)
       → finance_ar_ap(应收)

收款单核销 → finance_ar_ap(已收更新) → cash_journal(收入记录)
付款单核销 → finance_ar_ap(已付更新) → cash_journal(支出记录)

盘点差异 → inventory_flow(盘盈入库/盘亏出库) → inventory(调整)

费用报销(已打款) → finance_payment(自动生成) → cash_journal(支出记录)
```

Forma de implementação: após cada operação de negócio concluir, os fluxos a jusante são acionados por eventos; não há chamadas diretas de Service entre módulos.

---

## 7. Exportação Excel/PDF

- Todas as páginas de listagem suportam o parâmetro `?export=excel`, gerando arquivos .xlsx com formatação
- Os painéis do dashboard suportam `?export=pdf`, produzindo relatórios PDF com gráficos
- Campos sensíveis (valores, telefones etc.) são mascarados pelo EncryptionService na exportação
- Reutiliza a classe base ExportController existente; os controllers de cada módulo herdam e implementam suas próprias definições de colunas de exportação

---

## 8. Painéis do dashboard

| Painel | Rota | Indicadores |
|------|------|------|
| Visão geral do negócio | `/admin/dashboard` | Vendas hoje/este mês, compras, a receber/a pagar, valor total do estoque, margem |
| Quadro de inventário | `/admin/dashboard/inventory` | Lista de alertas, tendência de entrada/saída, taxa de ocupação das localizações |
| Quadro de vendas | `/admin/dashboard/sales` | Gráfico de tendência, ranking de clientes, produtos mais vendidos, conversão do funil |
| Quadro financeiro | `/admin/dashboard/finance` | Tendência de receitas/despesas, aging de contas a receber/pagar, fluxo de caixa |

Os dados ficam em cache Redis por 5 minutos, com suporte à troca de intervalo de tempo.

---

## 9. Design do frontend

| Cliente | Diretório | Framework | Estilo |
|----|------|------|------|
| Painel de administração Web | `apps/flutter/` (web) | Flutter + GetX | Painel de administração para PC (barra lateral + barra superior + área de conteúdo) |
| App cliente | `apps/flutter/` (app) | Flutter + GetX | Estilo nativo mobile |
| HarmonyOS | `apps/harmonyos/` | ArkTS | Nativo HarmonyOS, estilo App |

O código Flutter distingue renderização Web/PC e mobile por rotas e verificação de layout.

---

## 10. Ordem de implementação

| Etapa | Conteúdo | Dependência |
|------|------|------|
| 1 | Migração SQL do banco (todas as tabelas de negócio) | Nenhuma |
| 2 | Camada de Models (models de dados de todos os módulos) | Etapa 1 |
| 3 | Módulo de dados básicos de produtos (CRUD) | Etapa 2 |
| 4 | Módulo de compras | Etapa 3 |
| 5 | Módulo de vendas | Etapa 3 |
| 6 | Módulo de inventário + cálculo de custo | Etapas 4,5 |
| 7 | Módulo financeiro | Etapas 4,5,6 |
| 8 | Módulo CRM | Etapa 3 |
| 9 | Painéis do dashboard | Etapas 4-8 |
| 10 | Exportação Excel/PDF | Etapas 4-9 |
| 11 | API do cliente (/api/*) | Etapas 4-8 |
| 12 | Páginas de frontend Flutter | Etapas 4-10 |
| 13 | Páginas de frontend HarmonyOS | Etapa 11 |
