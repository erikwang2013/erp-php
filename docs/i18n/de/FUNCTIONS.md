# Open-ERP-System — Funktionshandbuch

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Übersicht

Das Open-ERP-System (open-erp) deckt 19 Geschäftsdomänen <!-- stats:modules=19 --> und 163 Datentabellen <!-- stats:tables=163 --> ab und bietet ein Full-Stack-Unternehmensverwaltungssystem von Einkauf/Verkauf/Lager bis Produktion und Fertigung, von Finanzbuchhaltung bis Personalwesen. Internationalisierung: zweisprachige Unterstützung Chinesisch/English, automatischer Sprachwechsel über den Accept-Language-Request-Header.

> API-Dokumentation: Nach dem Start des Dienstes `http://localhost:8787/apidoc` aufrufen, um die interaktive Schnittstellendokumentation anzusehen (automatisch von hg/apidoc erzeugt)

---

## 1. Systemverwaltung

### 1.1 Benutzerverwaltung
- Verwaltung des vollständigen Lebenszyklus von Administratorkonten (Erstellen/Bearbeiten/Löschen/Aktivieren-Deaktivieren)
- Batch-Operationen: Batch-Löschen, Batch-Aktivieren/Deaktivieren
- Excel-Batch-Import von Benutzern, zeilenweise Validierung + Fehlerbericht
- Passwörter werden als bcrypt-Hash gespeichert, für die Passwortänderung ist die Bestätigung des alten Passworts erforderlich
- Sensible Operationen wie Löschen erfordern eine zweite Bestätigung mit dem aktuellen Benutzerpasswort
- Handynummer/E-Mail/Personalausweisnummer werden verschlüsselt gespeichert, in Listen automatisch maskiert

### 1.2 Rollen und Berechtigungen (RBAC)
- Rollenverwaltung: Erstellen/Bearbeiten/Löschen, Slug als eindeutige Kennung
- Berechtigungsbaum: unbegrenzte Baumstruktur, drei Typen — Menü (in Navigation sichtbar), Button (Operation innerhalb der Seite), API (Schnittstellenzugriff)
- Berechtigungskennung-Format: `{method}.{path}`, z. B. `get.admin/product`, `post.admin/user/batch/destroy`
- Rolle-Berechtigung viele-zu-viele-Verknüpfung, Super-Admin überspringt alle Berechtigungsprüfungen
- AdminPermission-Middleware cached Benutzerberechtigungen in Redis (TTL=60s)

### 1.3 Systemkonfiguration
- Schlüssel-Wert-Speicher, unterstützt Gruppenverwaltung
- Werttypen: Zeichenkette/Integer/Boolean/JSON/Array

### 1.4 Betriebsprüfung
- Automatische Protokollierung aller POST/PUT/DELETE-Operationen
- Erfasst den Ausführenden, die Aktion, Methode, Pfad, IP, Parameter (sensible Felder maskiert) und die Zeit
- Automatische Erkennung von 8 Plattform-Quellgeräten (Web/Flutter/HarmonyOS/API usw.)
- Nur-Lese-Abfrage, weder löschbar noch änderbar

### 1.5 Sicherheitsschutz
- 18 Ebenen Tiefenverteidigung: HTTP-Methodeneinschränkung, XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Abfang
- Klick-Captcha (Pflichtprüfung bei Login/Registrierung)
- Redis-Sliding-Window-Rate-Limit (Lua-Atomar, Standard 60 Mal/Minute)
- Kontosperrung: 5 Fehlversuche sperren für 15 Minuten
- Begrenzung paralleler Sitzungen: maximal 3 gültige Tokens pro Benutzer
- CSP-Header, security.txt (RFC 9116)
- Zufällige zweite Verifikation bei sensiblen Operationen (poster-php)

---

## 2. Artikel und Stammdaten

### 2.1 Artikelverwaltung
- Artikelstamm: Code (eindeutig), Name, Barcode, Spezifikation, Basiseinheit, Bild, Beschreibung
- Multi-Spezifikations-SKU: mehrere SKUs pro Artikel, jeweils eigene Codes, Barcodes, Spezifikationsattribute (JSON)
- Multi-Einheiten-Umrechnung: Umrechnungsrate zwischen Basiseinheit und Hilfseinheit
- Preisstrategien: Einkaufspreis, Großhandelspreis, Einzelhandelspreis, Kundenstufenpreis
- ES-Volltextsuche wird unterstützt

### 2.2 Artikelkategorien
- Unbegrenzte Baumstruktur für Kategorien
- Sortierung, Aktivieren/Deaktivieren werden unterstützt
- Drag-&-Drop-Sortierung

### 2.3 Markenverwaltung
- Markenname, Logo, Beschreibung, Sortierung

### 2.4 Lager und Lagerplätze
- Multi-Lager-Verwaltung (Name, Code, Adresse, Verantwortlicher, Kontakttelefon)
- Mehrere Lagerplätze pro Lager (Code innerhalb des Lagers eindeutig)

### 2.5 Lieferantenverwaltung
- Lieferantencode, Name, Kontaktperson, Telefon/E-Mail (verschlüsselt), Adresse
- Bankkontoinformationen (verschlüsselt gespeichert), Steuernummer, Steuersatz
- ES-Volltextsuche

### 2.6 Kundenverwaltung
- Kundencode, Name, Kundenstufe, Kreditlimit
- Kontaktperson/Telefon/E-Mail (verschlüsselt) / Adresse
- Kundenstufe: Name, Standard-Rabattsatz
- ES-Volltextsuche

---

## 3. Einkaufsverwaltung

### 3.1 Einkaufsanfrage
- Abteilung/Person reicht Einkaufsbedarf ein
- Genehmigungsprozess: Ausstehende Genehmigung → Genehmigt/Abgelehnt → In Bestellung umgewandelt
- Anbindung an die Genehmigungsworkflow-Engine möglich

### 3.2 Einkaufsbestellung
- Verknüpft Lieferant, Artikelpositionen (Menge, Stückpreis, Betrag)
- Status: Ausstehende Prüfung → Geprüft → Teilweise empfangen → Empfangen → Storniert
- Kann auf Basis einer Anfrage oder direkt erstellt werden

### 3.3 Wareneingang (modulübergreifende Verknüpfung)
- Wareneingang nach Bestellung, Teil-Lieferungen werden unterstützt
- Der Empfang löst automatisch aus: ① Einlagerung (Bewegte-Durchschnittskosten-Rechnung) ② Erzeugung von Verbindlichkeiten ③ Aktualisierung der empfangenen Menge der Bestellung

### 3.4 Einkaufsretoure
- Rückgabe an den Lieferanten, erzeugt eine Warenausgangs-Stornierung

### 3.5 Lieferantenabrechnung
- Zusammenfassung pro Lieferant: Einkaufsbetrag, bezahlt, Verbindlichkeiten
- Status: Nicht abgerechnet/Teilweise abgerechnet/Abgerechnet

---

## 4. Vertriebsverwaltung

### 4.1 Angebot
- Angebot an den Kunden, Umwandlung in Verkaufsauftrag wird unterstützt
- Status: Entwurf → Angeboten → In Auftrag umgewandelt → Ungültig

### 4.2 Verkaufsauftrag
- Verknüpft Kunde, Artikelpositionen (Menge, Stückpreis, Rabatt)
- Status: Ausstehende Prüfung → Geprüft → Teilweise versendet → Versendet → Storniert

### 4.3 Versand (modulübergreifende Verknüpfung)
- Versand nach Auftrag, Teil-Lieferungen werden unterstützt
- Der Versand löst automatisch aus: ① Warenausgang (zu Bewegte-Durchschnittskosten) ② Erzeugung von Forderungen ③ Aktualisierung der versendeten Menge des Auftrags

### 4.4 Verkaufsretoure
- Kundenretoure, erzeugt eine Wareneingangs-Stornierung

### 4.5 Kundenabrechnung und Rohertrag
- Zusammenfassung pro Kunde: Verkaufsbetrag, erhalten, Forderungen
- Rohertragsberechnung nach Auftrag/Artikel/Kunde

---

## 5. Bestandsverwaltung

### 5.1 Echtzeitbestand
- Vierdimensionale Genauigkeit: Lager + Lagerplatz + Charge + SKU
- Multi-Lager, Multi-Lagerplatz werden unterstützt
- Echtzeit-Bestandsabfrage

### 5.2 Ein-/Ausgangs-Buchungen
- Alle Bestandsänderungen werden einheitlich erfasst (Richtung, Menge, Einstandspreis, Quellbelegnummer, Zeitpunkt)

### 5.3 Chargen-Tracking
- Produktionsdatum, Verfallsdatum, Chargennummer
- Charge wird bei Ein-/Ausgang erfasst

### 5.4 Seriennummern-Tracking
- Verwaltung eindeutiger Seriennummern
- Status (im Lager/ausgegeben) wird bei Ein-/Ausgang erfasst

### 5.5 Kostenrechnung
- Methode der bewegten Durchschnittskosten
- Formel: Neuer Durchschnittspreis = (Gesamtwert des alten Bestands + Gesamtwert des aktuellen Wareneingangs) / (alte Bestandsmenge + aktuelle Wareneingangsmenge)
- Bei jedem Wareneingang automatische Neuberechnung, beim Warenausgang Kosten zu aktuellem Durchschnittspreis

### 5.6 Bestandsumlagerung
- Umlagerung zwischen Lagern/Lagerplätzen
- Status: Ausstehende Umlagerung → Ausgelagert → Eingelagert → Abgeschlossen
- Automatische Erzeugung der Auslagerungs-/Einlagerungs-Buchungen

### 5.7 Inventurverwaltung
- Geplante Inventur (nach Lager/Kategorie) + dynamische Inventur (nach SKU)
- Erfasst Buchbestand vs. Istbestand
- Differenzen erzeugen automatisch Überbestands-/Fehlbestands-Buchungen

### 5.8 Bestandswarnungen
- Unter-/Obergrenzen pro SKU+Lager
- Bei Unterschreitung der Untergrenze/Überschreitung der Obergrenze wird automatisch ein Warnprotokoll erfasst

---

## 6. Finanzverwaltung

### 6.1 Forderungen/Verbindlichkeiten
- Automatisch durch Wareneingang/Versand erzeugt
- Status: Nicht verrechnet → Teilweise verrechnet → Verrechnet
- Idempotenzschutz für Belege gleicher Quelle

### 6.2 Zahlungseingänge
- Mehrere Konten (Bargeld/Bank/WeChat/Alipay)
- Nach der Prüfung automatische Aktualisierung des Kontosaldo und des Kassenjournals
- Verrechnung von Forderungen wird unterstützt

### 6.3 Zahlungsausgänge
- Gleiche Logik wie Zahlungseingänge, Richtung umgekehrt
- Verrechnung von Verbindlichkeiten wird unterstützt

### 6.4 Kassen- und Banktagebuch
- Einnahmen-/Ausgaben-Buchungen pro Konto + Datum
- Echtzeit-Aktualisierung des Bankkontosaldos

### 6.5 Spesenabrechnung
- Prozess: Einreichung → Genehmigung → Auszahlung
- Nach der Auszahlung automatische Erzeugung des Zahlungsbelegs + Tagebuch

### 6.6 Gewinn- und Verlustrechnung
- Monatliche Zusammenfassung: Betriebseinnahmen, Betriebskosten, Aufwendungen, Gewinn
- Snapshot-Speicherung (year+month eindeutig)

### 6.7 Anlagevermögen
- Vollständiger Lebenszyklus des Vermögenswerts: Anschaffung → Nutzung → Abschreibung → Veräußerung
- Lineare Abschreibung: (Anschaffungswert - Restwert) / Nutzungsmonate
- Monatliche Abschreibungsabgrenzung, automatische Erzeugung der Abschreibungsaufzeichnungen
- Erfasst: Anschaffungswert, Restwert, Nutzungsdauer, monatlicher Abschreibungsbetrag, kumulierte Abschreibung, Nettobuchwert

### 6.8 Steuerverwaltung
- Mehrere Steuerarten: Mehrwertsteuer/Körperschaftsteuer/Einkommensteuer/Stempelsteuer
- Flexible Steuersatzkonfiguration (einschließlich 4 Standard-Steuersatz-Seed-Daten)
- Mit Einkaufs-/Verkaufsbelegen verknüpft, automatische Erfassung des Steuerbetrags

### 6.9 Multi-Währung
- Währungsverwaltung: CNY/USD/EUR/JPY (einschließlich 4 Standard-Währungs-Seed-Daten)
- Kennzeichnung der Basiswährung
- Wechselkursverwaltung nach Gültigkeitsdatum

### 6.10 Budgetverwaltung
- Jahresbudgetplanung: nach Kostenstelle + Konto + Monat
- Budget-vs.-Ist-Vergleichsanalyse (Ausführungsrate + Abweichung)
- Status: Entwurf → Genehmigt → In Ausführung → Geschlossen

### 6.11 Kosten-/Profit-Center
- Baumförmige Hierarchiestruktur
- Kostenzuordnung + Aufwandsumlage
- Unabhängige Abrechnung der Profit-Center

---

## 7. CRM

### 7.1 Kundenverwaltung
- Kundenstamm (mit Kunden der Stammdaten verknüpft)
- Verwaltung mehrerer Kontaktpersonen (Kennzeichnung der primären Kontaktperson)
- Kontakttelefon/E-Mail verschlüsselt gespeichert

### 7.2 Follow-up-Aufzeichnungen
- Follow-up-Methoden: Telefon/Besuch/E-Mail/Nachricht/Sonstige
- Erfasst Follow-up-Inhalt, geplante nächste Follow-up-Aktion, Zeitpunkt des nächsten Follow-ups
- Verknüpft Kunde, Kontaktperson

### 7.3 Marketingkampagnen
- Vollständiger Lebenszyklus der Kampagne: Geplant → Laufend → Abgeschlossen → Storniert
- Mehrere Kanäle: E-Mail/SMS/Telefon/Events/Soziale Medien
- Teilnehmer-Tracking, Konversionsraten-Statistik
- Budget-vs.-Ist-Ausgabenvergleich

### 7.4 Service-Tickets
- Ticketverwaltung: Ausstehend → In Bearbeitung → Gelöst → Geschlossen
- Priorität: Niedrig/Mittel/Hoch/Dringend
- Kategorien: Technischer Support/Beschwerde/Beratung/Umtausch-Retoure/Sonstige
- Zuordnung eines Bearbeiters + Antworten (öffentlich/intern als Notiz)

### 7.5 Kundenanalyse-Berichte
- 6 Kernkennzahlen: Neukunden/Aktive Kunden/Bindungsrate/Durchschnittlicher Auftragswert/CLV/Ticket-Lösungsrate
- Automatische Berichtserzeugung (JSON-Datensnapshot)
- Unterstützt monatlich/quartalsweise/jährlich

---

## 8. Genehmigungsworkflow-Engine

### 8.1 Workflow-Vorlagen
- Konfigurierbare Genehmigungsketten: unterschiedliche Genehmigungsprozesse pro Belegart
- Genehmigungsknoten: sequenzielle Genehmigung, bedingtes Routing wird unterstützt (Prüfung von Feldern wie Betrag/Abteilung)
- Genehmiger-Typen: Bestimmte Person/Rolle/Abteilungsleiter/Direkter Vorgesetzter
- Ablehnen, Weiterleiten werden unterstützt

### 8.2 Genehmigungsoperationen
- Einreichen → stufenweise Genehmigung → Genehmigen/Ablehnen/Zurückziehen
- Meine Genehmigungsliste (ausstehend + erledigt)
- Vollständige Nachverfolgung der Genehmigungsaufzeichnungen

---

## 9. Nachrichtensystem

### 9.1 Benachrichtigungsverwaltung
- In-System-Nachrichten: Ungelesen/Gelesen-Status
- Benachrichtigungsvorlagen: Variablenersetzung wird unterstützt (z. B. „Sie haben eine ausstehende Genehmigung von {Antragsteller}")
- Mehrere Kanäle: In-System-Benachrichtigung (umgesetzt) → E-Mail (datei-Log-getrieben umgesetzt, SMTP ausstehend) → WeChat Work/DingTalk (Adapterpunkte vorbehalten)
- Benutzerpräferenzen für Benachrichtigungen

### 9.2 Automatische Benachrichtigungen
- Erinnerung an ausstehende Genehmigungen
- Push bei Bestandswarnungen
- Benachrichtigung bei Ticket-Zuweisung
- Einheitlicher Versand über NotificationService

---

## 10. Projektmanagement

### 10.1 Projekt
- Vollständiger Lebenszyklus des Projekts: In Planung → Laufend → Verspätet → Abgeschlossen → Storniert
- Priorität: Niedrig/Mittel/Hoch/Dringend
- Projektbudget-vs.-Ist-Kostenvergleich
- Aufgabenfortschritt wird automatisch zum Projektfortschritt aggregiert
- Verknüpft Kunde, weist Projektleiter zu

### 10.2 WBS-Aufgabenzerlegung
- Baumstruktur der Aufgaben (unbegrenzte Eltern-Kind-Verschachtelung)
- Gantt-Diagramm-Datensupport (Aufgabenabhängigkeiten, Zeitachse)
- Aufgabenstatus: Ausstehend → Laufend → Abgeschlossen → Verspätet
- Geschätzte vs. tatsächliche Arbeitszeit

### 10.3 Zeiterfassung
- Arbeitszeiterfassung nach Projekt/Aufgabe/Person/Datum
- Automatische Zusammenfassung der tatsächlichen Aufgabenarbeitszeit
- Unterstützt Projektkostenrechnung

---

## 11. Personalwesen

### 11.1 Organisationsstruktur
- Abteilungen: Baumförmige Hierarchiestruktur
- Positionen: nach Abteilung gegliedert, Sortierung wird unterstützt
- Mitarbeiterstamm: Code, Name, Geschlecht, Geburtsdatum, Eintrittsdatum, Status
- Verschlüsselung sensibler Felder: Handynummer, E-Mail, Personalausweisnummer, Bankkonto

### 11.2 Anwesenheitsverwaltung
- Anwesenheitsregeln: Arbeitsbeginn/-ende, Toleranz bei Verspätung, Toleranz bei frühem Gehen
- Stempelaufzeichnungen: Arbeitsbeginn-/Arbeitsende-Stempel, automatische Berechnung der Verspätungs-/Frühgehens-Minuten
- Status: Normal/Verspätet/Früh gegangen/Stempel fehlt/Beurlaubt/Dienstreise
- Urlaubsverwaltung: Jahresurlaub/Sonderurlaub/Krankheitsurlaub/Hochzeitsurlaub/Mutterschutz/Zeitausgleich

### 11.3 Gehaltsverwaltung
- Gehaltsposten-Konfiguration: Einnahmenposten/Abzugsposten, steuerpflichtig oder nicht, Standardbetrag
- Gehaltsabrechnung: Grundgehalt + Leistung + Überstunden - Abzüge - Einkommensteuer = Auszahlungsbetrag
- Unterstützt Batch-Erzeugung monatlicher Gehälter
- Gehaltszahlungsbestätigung

---

## 12. Produktion und Fertigung

### 12.1 BOM-Stückliste
- Produkt-BOM: Endprodukt → Komponenten → Rohstoffe, mehrstufige Baumstruktur
- Versionsverwaltung: Entwurf → Aktiv → Ungültig
- Komponentenpositionen: Verbrauchsmenge, Einheit, Ausschussrate

### 12.2 Produktionsaufträge
- Produktionsauftrag auf Basis der BOM erstellen
- Status: Ausstehende Produktion → In Produktion → Abgeschlossen → Storniert
- Planmenge vs. Istmenge
- Geplantes Start-/Enddatum vs. tatsächliche Start-/Endzeit

### 12.3 Arbeitspläne
- Prozessablauf pro Produkt definieren
- Jeder Arbeitsschritt verknüpft eine Workstation und Standardarbeitszeit
- Sortierung der Arbeitsschritte

### 12.4 Workstations
- Workstation-Code, Name, Kapazität (pro Stunde)
- Aktivieren/Deaktivieren

### 12.5 MRP Materialbedarfsplanung
- Nettobedarf-Berechnung: Gesamtbedarf - geplante Zugänge - vorhandener Bestand = Nettobedarf
- Planerzeugung nach Periode (Jahr + Monat)
- Status: Entwurf → Erzeugt → Bestätigt

---

## 13. Benutzerdefinierter Berichts-Builder

### 13.1 Berichtsvorlagen
- Benutzerdefinierte Felder: Auswahl von Datentabellenfeldern, Aggregationsmethode (Summe/Anzahl/Durchschnitt/Maximum/Minimum)
- Benutzerdefinierte Filter: Text/Dropdown/Datumsbereich/Zahlenbereich
- Diagrammtypen: Tabelle/Säulendiagramm/Liniendiagramm/Kreisdiagramm/KPI-Kennzahlenkarte
- Nach Modul gruppiert (Produkt/Einkauf/Vertrieb/Bestand/Finanzen/CRM/HR/Fertigung/Projekt)

### 13.2 Berichtsausführung
- Dynamische SQL-Erzeugung (basierend auf Feld- und Filterkonfiguration)
- Tabellennamen-Whitelist-Schutz (aus install.sql geparst)
- Ergebnis-Datensatz-Snapshot (JSON-Speicherung)

### 13.3 Zeitgesteuerte Berichte
- Planungsfrequenz: Täglich/Wöchentlich/Monatlich
- Empfängerkonfiguration
- Automatische Ausführung + Speicherung der Ergebnisse

---

## 14. Dashboards

### 14.1 Geschäftsübersicht
- Verkaufs-/Einkaufsbeträge heute/diesen Monat
- Forderungs-/Verbindlichkeitsgesamtbeträge, Lagerwert, Rohertrag
- Redis-Cache 5 Minuten

### 14.2 Vertriebs-Dashboard
- Verkaufstrend, Kunden-Ranking Top10
- Zeitraumwechsel wird unterstützt

### 14.3 Bestands-Dashboard
- Lagerwert, Warnstatistik (unter Untergrenze/über Obergrenze)
- Ein-/Ausgangstrend (nach Tag/Richtung)

### 14.4 Finanz-Dashboard
- Forderungs-/Verbindlichkeitsgesamtbeträge, Zahlungseingänge/-ausgänge des Monats
- Zusammenfassung der Kassen- und Banksalden

---

## Modulübergreifender Datenfluss

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

## 15. Exportfunktionen

### 15.1 Excel-Export
- Alle Listen unterstützen ?export=excel
- PhpSpreadsheet erzeugt .xlsx, Kopfzeile blau mit weißer Schrift + eingefrorene erste Zeile + AutoFilter
- Sensible Felder werden automatisch maskiert

### 15.2 PDF-Export
- Dashboard-Datenpanels unterstützen ?export=pdf
- Dompdf-Rendering, A4 quer
- Nicht entfernbarer Copyright-Hinweis

---

## 16. Auftragsverwaltung (OMS)

### 16.1 Auftragsverwaltung
- **Mehrkanal-Auftragsimport**: Unterstützt manual/web/mobile/api/marketplace/edi/pos
- **Erweiterte Auftragsinformationen**: Kanalauftragsnummer, Shop, Erfüllungsstatus, Zahlungsstatus, Priorität
- **Bestandszuordnung**: ATP-Berechnung (verfügbare Menge) → Bestandsreservierung (pessimistisches Sperren gegen Überverkauf)
- **Erfüllungs-Orchestrierung**: Zuordnung → Erfüllung erstellen → an WMS übergeben → Kommissionieren/Packen → TMS-Versand
- **Auftragsstornierung**: automatische Freigabe der Bestandsreservierung

### 16.2 RMA Umtausch/Retoure
- RMA-Erstellung (Retoure/Umtausch/Reparatur) → Genehmigung → Rückversand → Wareneingang (stockIn) → Rückerstattung
- Verwaltung von Retoure-Versandkosten und Erstattungsbeträgen wird unterstützt

### 16.3 Kanalverwaltung
- Kanalcode/Name/Typ (direct/marketplace/edi/pos)
- Kanal-Konfiguration (JSON), Status Aktivieren-Deaktivieren

---

## 17. Lagerverwaltung (WMS)

### 17.1 Zonen und Lagerplätze
- **Zonen**: Wareneingangszone/Lagerzone/Kommissionierzone/Packzone/Versandzone/Retourenzone/Qualitätsprüfzone
- **Erweiterte Lagerplätze**: Gang → Regal → Ebene → Stellplatz-Hierarchie + Barcode/Volumen/Tragfähigkeit/Kommissionierreihenfolge

### 17.2 Wareneingangsprozess
- **ASN (Vorab-Lieferankündigung)**: Lieferant → voraussichtlicher Wareneingang → Spediteur → Tracking-Nummer
- **Empfangstask**: Rampenempfang → Erfassung der Istmenge → Qualitätsprüfung
- **Einlagerungstask**: automatische Erzeugung → Zuordnung → Strategie (fifo/zone_fixed/abc) → Einlagerung bestätigen (stockIn)

### 17.3 Warenausgangsprozess
- **Wellenverwaltung**: Aggregation mehrerer Aufträge → Kommissionierwellen/Versandwellen → Priorität
- **Kommissioniertask**: nach Beleg/Batch/Zone/Welle kommissionieren → Zuordnung → Bestätigung (Ist-Kommissioniermenge)
- **Packtask**: Verpackungstyp (box/bag/pallet) → Gewicht/Abmessungen

---

## 18. Transportverwaltung (TMS)

### 18.1 Spediteure
- Spediteurcode/Typ (Express/Teilladung/Komplettladung/Luftfracht/Seefracht/Bahn)
- Spediteur-Services: standard/express/overnight/2day/economy + Laufzeit
- API-Konfiguration: Abstraktion von custom/shippo/afterShip/17track

### 18.2 Frachtkostenverwaltung
- **Tarifkarten**: Versandort/Zielort → Gewichtsstufen → Grundgebühr/Preis pro kg/Kraftstoffzuschlag
- **Multi-Währung**: CNY/USD/EUR usw., verknüpft mit exchange_rate
- **Frachtkostenvergleich**: alle verfügbaren Tarife für Zielland + Gewicht abfragen, aufsteigend sortieren

### 18.3 Frachtbriefe und Tracking
- **Frachtbrief**: Spediteur-Service → Tracking-Nummer → Status (Ausstehender Versand → Abgeholt → In Transport → Zugestellt/Abweichung/Rücksendung)
- **Sendungsverfolgung**: webhook-Callback → automatische Synchronisierung des Frachtbriefstatus
- **Frachtrechnung**: Erstellung → Bestätigung → Zahlung → AP-Erzeugung

---

## Anhang: Projektumfang

| Dimension | Anzahl |
|------|------|
| Geschäftsmodule | 19 <!-- stats:modules=19 --> |
| Datenbanktabellen | 163 <!-- stats:tables=163 --> |
| Datenmodelle | 161 <!-- stats:models=161 --> |
| Controller | 123 <!-- stats:controllers=122 --> |
| Business-Services | 27 <!-- stats:services=27 --> |
| API-Routen | 198 (dynamisch erzeugt, siehe `scripts/check-endpoints.php`, nimmt nicht an der doc-stats-Prüfung teil) |
| Middleware | 11 <!-- stats:middleware=11 --> |
| PHP-Quelldateien | 343 <!-- stats:php_files=339 --> |
| Datenbank-Installationsskript | Einzeldatei `database/install.sql` (163 Tabellen, alle Migrationen bereits integriert) |
| Frontend-Seiten (Flutter) | 7 (Frontend-Statistik, nicht in der doc-stats-Prüfung enthalten) |
| Frontend-Seiten (HarmonyOS) | 4 (Frontend-Statistik, nicht in der doc-stats-Prüfung enthalten) |
| Unit-Tests | 50 Testdateien <!-- stats:test_files=59 --> / 442 Testfälle / 2238 Assertions (tests/assertions schwanken mit PHP-Patchversionen und Erweiterungen, nehmen nicht an der exakten stats-Prüfung teil) |

> Die obigen Zahlen werden real von `bash scripts/doc-stats.sh` gemessen; mit `<!-- stats:key=value -->` markierte Einträge werden von CI
> (docs-Job in `.github/workflows/ci.yml`) automatisch auf Übereinstimmung mit den Code-Fakten geprüft — Abweichungen werden rot.

---

## 19. Modul-Vollständigkeitsmatrix (Stand 2026-08-16)

### Statuslegende

| Markierung | Bedeutung |
|------|------|
| ✅ | Abgeschlossen — produktionsreif |
| ⚠️ | Skelett — CRUD abgeschlossen, es fehlen Business-Engine/Frontend |
| 🔴 | Fehlt — nicht implementiert |
| 🔵 P0 | Phase Frontend-Ökosystem |
| 🟢 P1 | Phase Business-Tiefe |
| 🟡 P2 | Phase Betriebszuverlässigkeit |
| 🟣 P3 | Phase Erlebnisverbesserung |

### Matrix

| Modul | Backend-API | Businesslogik | Flutter | HarmonyOS | Nächste Phase |
|------|----------|----------|---------|-----------|----------|
| Systemverwaltung | ✅ | ✅ | ⚠️ 7/10 | ⚠️ 4/10 | 🔵 P0 |
| Dashboards | ✅ | ✅ | ⚠️ Basis | ⚠️ Basis | 🔵 P0 |
| Artikel-Stammdaten | ✅ | ✅ | ⚠️ 3/7 | ⚠️ 1/7 | 🔵 P0 |
| Einkaufsverwaltung | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Vertriebsverwaltung | ✅ | ⚠️ | ⚠️ 1/5 | ⚠️ 1/5 | 🔵 P0 |
| Bestandsverwaltung | ✅ | ✅ | ⚠️ Basis | ⚠️ Basis | 🔵 P0 |
| Finanzen — Belege/Forderungen-Verbindlichkeiten | ✅ | ⚠️ | ⚠️ 2/10 | 🔴 | 🔵 P0 |
| Finanzen — Hauptbuch/Drei Abschlüsse | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Finanzen — Periodenabschluss/Konsolidierung | 🔴 | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| CRM Gesamtmodul | ✅ | ✅ | ⚠️ 1/8 | 🔴 | 🔵 P0 |
| OMS Auftragsverwaltung | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| WMS Lagerverwaltung | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| TMS Transportverwaltung | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Genehmigungsworkflow | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| Benachrichtigungssystem | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Projektmanagement | ✅ | ✅ | 🔴 | 🔴 | 🔵 P0 |
| HR — Organisation/Anwesenheit/Urlaub | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| HR — Gehalts-Engine | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Fertigung — BOM/Produktion/MRP | ⚠️ | 🔴 | 🔴 | 🔴 | 🟢 P1 |
| Qualitätsmanagement | ✅ | ✅ | 🔴 | 🔴 | 🟢 P1 |
| Benutzerdefinierte Berichte | ✅ | ⚠️ | 🔴 | 🔴 | 🔵 P0 |
| BI-Dashboards | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Anlagenverwaltung EAM | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Multi-Mandantenfähigkeit | ⚠️ | ⚠️ | 🔴 | 🔴 | 🟣 P3 |
| Dokumentenverwaltung DMS | ✅ | ✅ | 🔴 | 🔴 | 🟣 P3 |
| Beobachtbarkeit | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Migrations-Rollback/Backup | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Statistik

| Dimension | ✅ Abgeschlossen | ⚠️ Skelett | 🔴 Fehlt | N/A | Fertigstellungsrate |
|------|---------|----------|---------|-----|--------|
| Module (27) | 14 | 12 | 1 | 0 | 52% |
| Backend-API | 19 | 7 | 1 | 0 | 70% |
| Businesslogik | 14 | 7 | 6 | 0 | 52% |
| Flutter-Frontend | 0 | 8 | 17 | 2 | 0% |
| HarmonyOS | 0 | 6 | 19 | 2 | 0% |

> **Statistik-Methodik (Stand 2026-08-16)**: Modulzeilen werden als „Backend-API und Businesslogik beide implementiert" gezählt;
> die beiden Zeilen Backend-API / Businesslogik werden nach der jeweiligen Matrixspalte gezählt (diesmal wurden QMS/EAM/DMS/BI laut Code-Stand auf ✅ korrigiert,
> Multi-Mandantenfähigkeit auf ⚠️, Belege siehe untenstehende „Code-Beweise"); Flutter / HarmonyOS sind Arbeitsaufwands-Statistiken der Frontend-Seiten
> (die 2 Zeilen Beobachtbarkeit, Migrations-Rollback sind mit N/A markiert), nicht in der Backend-doc-stats-Prüfung enthalten.

### Code-Beweise (Stand 2026-08-16)

Grundlage dieser Vollständigkeitskorrektur (Dateiexistenz kann durch `bash scripts/doc-stats.sh` und `find` belegt werden):

| Modul | Korrektur | Code-Beweis |
|------|------|----------|
| Qualitätsmanagement | 🔴 → ✅ | `app/controller/quality/` (5 Controller) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| BI-Dashboards | 🔴 → ✅ | `app/controller/bi/` (3 Controller: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Anlagenverwaltung EAM | 🔴 → ✅ | `app/controller/eam/` (4 Controller) + `tests/EamModuleTest.php` |
| Dokumentenverwaltung DMS | 🔴 → ✅ | `app/controller/dms/` (2 Controller) + `tests/DmsModuleTest.php` |
| Multi-Mandantenfähigkeit | 🔴 → ⚠️ | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` + `tests/Integration/TenantScopeIntegrationTest.php` (bekannter Mangel: statische Mandanten-ID wird nicht über Modelle propagiert, daher Skelett statt abgeschlossen) |

> Detaillierte Roadmap-Designspezifikation: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
