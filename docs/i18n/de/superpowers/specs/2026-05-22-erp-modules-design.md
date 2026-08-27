# ERP-Geschäftsmodul-Designspezifikation

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. Überblick

Auf der bestehenden `service/`-Systemverwaltungsbasis werden die drei Geschäftsdomänen Ein-/Verkauf, Finanzwesen und CRM erweitert, um ein vollständiges ERP-System aufzubauen.
Der gesamte Code wird monolithisch unter `service/app/` bereitgestellt, die Module sind nach Verzeichnissen geschichtet.

### 1.1 Phasenplanung

| Phase | Module | Beschreibung |
|------|------|------|
| Phase 1 | Artikel-Stammdaten + Einkauf + Verkauf + Bestand + Finanzwesen + CRM | Geschlossener Kern-Businesskreislauf |
| Phase 2 | Fertigungsmanagement + Projektmanagement | spätere Erweiterung |

### 1.2 Technologiestack (bestehende Technik übernehmen)

- PHP 8.3+, webman v2, MySQL 8.0+
- Primärschlüssel BIGINT von snowflake-php erzeugt
- IDs auf API-Ebene mit hashids ver-/entschlüsselt
- JWT-Authentifizierung und Verschlüsselung sensibler Daten verwenden vollständig die erikwang2013/*-Paketfamilie
- Tabellenpräfix `erik_`, Soft-Delete, globale Funktionen ohne `\`

---

## 2. Projektstruktur

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

### 2.1 Schichtenverantwortung

| Ebene | Dateiposition | Aufgaben |
|----|----------|------|
| Controller | `app/controller/{module}/` | Parametervalidierung, Antwortformatierung, Service-Aufruf |
| Service | `app/service/{module}/` | Geschäftslogik, modulübergreifende Verknüpfung, Transaktionsmanagement |
| Model | `app/model/` | Datenmodelle, Beziehungen, Query-Scopes, encryptable-trait |

---

## 3. Modul-Funktionsliste

### 3.1 Artikel und Stammdaten

| Funktion | Beschreibung |
|------|------|
| Artikelstamm | Artikelname, Code, Barcode, Kategorie (Baumstruktur), Marke, Spezifikationsattribute |
| Mehrfach-Spezifikations-SKU | Mehrere Spezifikationen je Artikel, jeweils eigene SKU, Barcode, Preis |
| Mehrfacheinheiten-Umrechnung | Umrechnungsrate Basiseinheit ↔ Hilfseinheit |
| Preisstrategie | Einkaufspreis, Großhandelspreis, Einzelhandelspreis, Kundenstufenpreis |
| Kategorienverwaltung | Unbegrenzte Kategorie-Baumstruktur, unterstützt Drag-and-Drop-Sortierung |
| Markenverwaltung | Marken-CRUD |
| Lagerverwaltung | Mehrere Lager, jedes Lager mit mehreren Lagerplätzen |
| Lagerplatzverwaltung | Lagerplätze unter dem Lager, Code eindeutig |
| Lieferantenstamm | Name, Kontakt, Telefon, Adresse, Bankkonto, Steuersatz |
| Kundenstamm | Name, Kontakt, Telefon, Adresse, Kundenstufe, Kreditlimit |

### 3.2 Einkaufsmodul

| Funktion | Beschreibung |
|------|------|
| Einkaufsanfrage | Abteilungen/Mitarbeiter reichen Einkaufsbedarf ein, unterstützt Genehmigungsprozess |
| Einkaufsbestellung | Auf Basis der Anfrage oder direkt erstellt, verknüpft Lieferant, Artikel, Menge, Einzelpreis |
| Einkaufswareneingang | Wareneingang nach Bestellung, erzeugt Einlagerungsschein, unterstützt Teilwareneingang |
| Einkaufsretoure | Rückgabe an den Lieferanten, erzeugt Warenausgangsschein zur Verrechnung |
| Lieferantenabstimmung | Summiert Einkaufsbetrag, bezahlt und Verbindlichkeiten nach Lieferant + Zeitraum |
| Einkaufsabrechnung | Verrechnet Einkaufswareneingang und Zahlungen |

### 3.3 Verkaufsmodul

| Funktion | Beschreibung |
|------|------|
| Angebot | Angebot an den Kunden, unterstützt Umwandlung in Verkaufsauftrag |
| Verkaufsauftrag | Kunde gibt auf, verknüpft Artikel, Menge, Einzelpreis, Rabatt |
| Verkaufsversand | Versand nach Auftrag, erzeugt Warenausgangsschein, unterstützt Teilversand |
| Verkaufsretoure | Rückgabe durch den Kunden, erzeugt Einlagerungsschein zur Verrechnung |
| Kundenabstimmung | Summiert Verkaufsbetrag, erhalten und Forderungen nach Kunde + Zeitraum |
| Verkaufsabrechnung | Verrechnet Verkaufsversand und Zahlungseingänge |
| Verkaufsrohgewinn | Rohgewinn nach Auftrag/Artikel/Kunde berechnen |

### 3.4 Bestandsmodul

| Funktion | Beschreibung |
|------|------|
| Echtzeitbestand | Bestandsmenge nach Lager+Lagerplatz+Charge+SKU |
| Chargenverfolgung | Produktionsdatum, Verfallsdatum, Chargennummer |
| Seriennummernverfolgung | Eindeutige Seriennummer, bei Ein-/Auslagerung erfasst |
| Ein-/Auslagerungsbuchungen | Einheitliches Protokoll aller Bestandsänderungen (Quellbelegnummer + Typ + Menge + Richtung) |
| Bestandsumlagerung | Umlagerung zwischen Lagern/Lagerplätzen, erzeugt Umlagerungs-Ein-/Auslagerungsscheine |
| Inventuraufgaben | Geplante Inventur (nach Lager/Kategorie) + dynamische Inventur (nach SKU) |
| Inventurdifferenzen | Bestandsüberschuss/-verlust erzeugt automatisch Ein-/Auslagerungsbuchungen |
| Bestandswarnungen | Ober-/Untergrenzen nach SKU+Lager, Alarm unterhalb der Untergrenze oder oberhalb der Obergrenze |
| Kostenrechnung | Methode der bewegten gewichteten Durchschnittskosten, Einstandspreis wird bei jeder Einlagerung neu berechnet |

### 3.5 Finanzmodul

| Funktion | Beschreibung |
|------|------|
| Kontenplan | Kontenbaum (Vermögen/Verbindlichkeiten/Eigenkapital/Erträge/Aufwendungen), benutzerdefiniert möglich |
| Forderungen/Verbindlichkeiten | Automatisch aus Verkaufs-/Einkaufsbelegen erzeugt, manuelle Verrechnung |
| Zahlungseingangsbeleg | Zahlungseingang über mehrere Konten und Wege (Bargeld/Bank/WeChat/Alipay) |
| Zahlungsausgangsbeleg | Zahlungsausgang über mehrere Konten und Wege |
| Verrechnung | Zahlungseingang verrechnet Forderungen, Zahlungsausgang verrechnet Verbindlichkeiten |
| Kassen- und Banktagebuch | Einnahmen-/Ausgabenbuchungen nach Konto + Datum |
| Spesenabrechnung | Einreichen→Genehmigen→Auszahlung, verknüpft mit Konto |
| Gewinn- und Verlustrechnung | Summiert monatlich Erträge/Kosten/Aufwendungen/Gewinn |

### 3.6 CRM-Modul

| Funktion | Beschreibung |
|------|------|
| Kundenverwaltung | Kundenstamm (verknüpft mit Kunden der Stammdaten) |
| Kontaktverwaltung | Mehrere Kontakte je Kunde |
| Follow-up-Protokoll | Follow-up-Methode, Zeitpunkt, Inhalt, Plan für nächstes Follow-up |
| Verkaufsfunnel | Phasenkonfiguration + Betragsprognose der Chancen + Phasen-Conversion-Rate |

---

## 4. Datenbanktabellen-Design

Alle Tabellen mit `erik_`-Präfix, `id` BIGINT nicht auto-increment, enthalten `created_at`/`updated_at`/`deleted_at`.

### 4.1 Artikel-Stammdaten

```
erik_product             商品主表
erik_product_sku         商品SKU/规格
erik_product_unit        多单位换算
erik_product_price       价格策略
erik_category            商品分类（树形 parent_id）
erik_brand               品牌
erik_warehouse           仓库
erik_location            库位
erik_supplier            供应商
erik_customer            客户
erik_customer_level      客户等级
```

### 4.2 Einkaufsmodul

```
erik_purchase_apply       采购申请
erik_purchase_apply_item  申请明细
erik_purchase_order       采购订单
erik_purchase_order_item  订单明细
erik_purchase_receive     采购收货主表
erik_purchase_receive_item 收货明细
erik_purchase_return      采购退货主表
erik_purchase_return_item 退货明细
erik_purchase_settlement  供应商结算记录
```

### 4.3 Verkaufsmodul

```
erik_sales_quotation      报价单主表
erik_sales_quotation_item 报价明细
erik_sales_order          销售订单主表
erik_sales_order_item     订单明细
erik_sales_delivery       销售发货主表
erik_sales_delivery_item  发货明细
erik_sales_return         销售退货主表
erik_sales_return_item    退货明细
erik_sales_settlement     客户结算记录
```

### 4.4 Bestandsmodul

```
erik_inventory            实时库存
erik_inventory_batch      批次信息
erik_inventory_serial     序列号记录
erik_inventory_flow       出入库流水
erik_transfer             调拨单主表
erik_transfer_item        调拨明细
erik_check_task           盘点任务
erik_check_detail         盘点明细
erik_inventory_alert_rule 库存预警规则
erik_inventory_alert_log  库存预警日志
erik_cost_record          成本计算记录
```

### 4.5 Finanzmodul

```
erik_finance_account      会计科目
erik_finance_voucher      记账凭证
erik_finance_voucher_item 凭证分录
erik_finance_ar_ap        应收应付明细
erik_finance_receipt      收款单
erik_finance_payment      付款单
erik_finance_cash_journal 现金银行日记账
erik_finance_expense      费用报销单
erik_finance_expense_item 报销明细
erik_finance_profit       利润表快照
erik_finance_bank_account 银行账户
```

### 4.6 CRM-Modul

```
erik_crm_funnel_stage     销售漏斗阶段配置
erik_crm_opportunity      商机
erik_crm_follow_record    跟进记录
erik_crm_contact          联系人
```

---

## 5. API-Routen

Nutzt den `/admin/*`-Namensraum, vollständige Middleware-Kette (Auth → Permission → OperationLog).

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

Die Client-API `/api/v1/*` bietet leichte Schnittstellen (Artikelabfrage, Bestellung, Auftragsstatus usw.) für Flutter App / HarmonyOS.

---

## 6. Modulübergreifende Datenflüsse

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

Implementierung: Nach Abschluss jeder Geschäftsoperation werden Folgeaktionen über Ereignisse ausgelöst, Services werden nicht direkt modulübergreifend aufgerufen.

---

## 7. Excel/PDF-Export

- Alle Listenseiten unterstützen den Parameter `?export=excel` und erzeugen formatierte .xlsx-Dateien
- Dashboard-Panels unterstützen `?export=pdf` und geben PDF-Berichte mit Diagrammen aus
- Sensible Felder (Beträge, Handynummern usw.) werden beim Export über EncryptionService maskiert
- Wiederverwendung der bestehenden ExportController-Basisklasse; die Controller der Module erben sie und implementieren eigene Export-Spaltendefinitionen

---

## 8. Dashboard-Panels

| Panel | Route | Kennzahlen |
|------|------|------|
| Geschäftsübersicht | `/admin/dashboard` | Verkaufs- und Einkaufsvolumen heute/diesen Monat, Forderungen/Verbindlichkeiten, Bestandswert gesamt, Rohgewinn |
| Bestands-Panel | `/admin/dashboard/inventory` | Warnliste, Ein-/Auslagerungstrend, Lagerplatz-Auslastung |
| Verkaufs-Panel | `/admin/dashboard/sales` | Trenddiagramm, Kundenranking, Verkaufsschlager, Funnel-Conversion-Rate |
| Finanz-Panel | `/admin/dashboard/finance` | Einnahmen-/Ausgabentrend, Altersstruktur von Forderungen/Verbindlichkeiten, Cashflow |

Daten werden 5 Minuten in Redis gecacht, Zeitraumwechsel wird unterstützt.

---

## 9. Frontend-Design

| Plattform | Verzeichnis | Framework | Stil |
|----|------|------|------|
| Web-Admin-Backend | `apps/flutter/` (web) | Flutter + GetX | PC-Verwaltungsstil (Seitenleiste+Kopfleiste+Inhaltsbereich) |
| Client-App | `apps/flutter/` (app) | Flutter + GetX | Mobiler nativer Stil |
| HarmonyOS | `apps/harmonyos/` | ArkTS | HarmonyOS-nativ, App-Stil |

Flutter-Code unterscheidet über Routen und Layout die Darstellung für Web-PC und Mobilgeräte.

---

## 10. Implementierungsreihenfolge

| Schritt | Inhalt | Abhängigkeit |
|------|------|------|
| 1 | Datenbank-Migrations-SQL (alle Geschäftstabellen) | Keine |
| 2 | Model-Ebene (Datenmodelle aller Module) | Schritt 1 |
| 3 | Artikel-Stammdatenmodul (CRUD) | Schritt 2 |
| 4 | Einkaufsmodul | Schritt 3 |
| 5 | Verkaufsmodul | Schritt 3 |
| 6 | Bestandsmodul + Kostenrechnung | Schritt 4,5 |
| 7 | Finanzmodul | Schritt 4,5,6 |
| 8 | CRM-Modul | Schritt 3 |
| 9 | Dashboard-Panels | Schritt 4-8 |
| 10 | Excel/PDF-Export | Schritt 4-9 |
| 11 | Client-API (/api/*) | Schritt 4-8 |
| 12 | Flutter-Frontend-Seiten | Schritt 4-10 |
| 13 | HarmonyOS-Frontend-Seiten | Schritt 11 |
