# Open-ERP-System — Funktionshandbuch

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Übersicht

Das Open-ERP-System (open-erp) deckt 23 Geschäftsdomänen <!-- stats:modules=23 --> und 227 Datentabellen <!-- stats:tables=227 --> ab und bietet ein Full-Stack-Unternehmensverwaltungssystem von Einkauf/Verkauf/Lager bis Produktion und Fertigung, von Finanzbuchhaltung bis Personalwesen. Internationalisierung: 13 Sprachen unterstützt (中文/English/日本語/한국어/Deutsch/Français/Español/Português/Русский/العربية/हिन्दी/বাংলা/Bahasa Indonesia), automatischer Sprachwechsel über den Accept-Language-Request-Header.

> API-Dokumentation: Nach dem Start des Dienstes `http://localhost:8788/apidoc` aufrufen, um die interaktive Schnittstellendokumentation anzusehen (automatisch von erikwang2013/apidoc-php erzeugt)

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
- 7 Ebenen Tiefenverteidigung: HTTP-Methodeneinschränkung, XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Abfang
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
### 4.6 Kundenkreditkontrolle (v1.4.0 geliefert)

- Kreditlimitverwaltung: Kreditlimit, Inanspruchnahme und Sperrregeln je Kunde pflegen
- Sperre vor Bestellung/Versand: CreditControlService assertOrderCreate / assertDeliveryCreate / guard lehnt Aufträge über dem Limit ab und gibt den Grund zurück

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
### 5.9 Chargen-/Seriennummern-Rückverfolgung und Verfallsdatumswarnung (v1.4.0 geliefert)

- Rückverfolgungskette: TraceService forward/backward verfolgt Chargenverbleib/-herkunft in beide Richtungen, serial ist das Seriennummern-Journal
- Verfallsdatumswarnung: expiryAlert erinnert an Chargen mit nahendem oder überschrittenem Verfallsdatum

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
### 6.12 Periodenabschluss / Mehrorganisationen-Rechnungswesen / Konsolidierung (v1.4.0 geliefert)

- Abschluss der Erfolgskonten: periodenweise Summierung der Kontobewegungen der Erfolgskonten (Ertragskonten − Aufwandskonten = Jahresüberschuss, status=calculated)
- Der Abschluss erzeugt keine Buchungen (es fehlen die Konfiguration eines Jahresüberschuss-Kontos und Regeln gegen doppelten Abschluss) und es gibt keinen Controller-Endpunkt — nicht als v1.4.0-Lieferung gelistet, bleibt als To-do bestehen
- Mehrorganisationen-Rechnungswesen: CompanyController / LedgerPeriodController für eigenständige Rechnungskreise und Geschäftsperioden (v1.4.0 geliefert)
- Konsolidierungs-Engine (v1.4.0 geliefert): ConsolidationService rateToBase/translateLedger Stichtagskurs-Umrechnung (ohne Kurs wird abgelehnt), addElimination Konzernverrechnung zwischen Tochtergesellschaften (Prüfung der Soll-/Haben-Balance), generateDraft Ausgabe des Abschlusses (für bereits ausgegebene Perioden wird der Snapshot gelesen, ohne Snapshot erfolgt eine Echtzeit-Neuberechnung anhand der geprüften Belege), issue Schutz gegen doppelte Ausgabe, latest/list; die Ergebnisse landen in FinanceConsolidationReport
- Tests: PeriodCloseServiceTest 4 Fälle + ConsolidationServiceTest 3 Fälle

### 6.13 Bestands- und Produktionskostenrechnung (v1.4.0 geliefert)

- Materialausgabe und Kostenverteilung in der Produktion: MaterialIssueController bucht die Materialentnahme aus, CostEntryController + MfgCostService sammeln Material-/Lohn-/Fertigungsgemeinkosten je Fertigungsauftrag
- Buchungsregeln: MfgCostVoucherRule für Fertigstellungskosten und Differenzabgrenzung (verknüpft §12 Produktionsaufträge)

### 6.14 Wechselpapiere und Bankabstimmung (v1.4.0 geliefert)

- Vollständiger Lebenszyklus der Wechselpapiere: FinanceBillService store/update/endorse (Indossament)/discount (Diskontierung)/collect (Inkasso)/cash (Einlösung)/reject + dueWarnings Fälligkeitswarnung
- Bankabstimmung: BankReconService importStatement Import der Kontoauszüge, autoReconcile/manualReconcile/unreconcile Abstimmung, reconReport Abstimmungsbericht

### 6.15 Eingangsrechnungspool und E-Rechnung (v1.4.0 geliefert)

- Eingangspool: TaxInvoicePoolService registerOne/registerBatch/verify/check/deduct/deductStats; die Prüfung läuft über MockTaxVerifier (der echte Finanzamtskanal ist der Anpassungspunkt)
- E-Rechnung: EInvoiceService issueInvoice/voidInvoice/issueLogs, über EInvoiceAdapter/MockEInvoiceAdapter (der echte Finanzamtskanal ist der Anpassungspunkt)

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
- Automatische Berichtserzeugung (JSON-Datensnapshot, Snapshot-Tabellen CrmAnalyticsReport/CrmAnalyticsMetric)
- 5 Endpunkte: reports/generate/reportShow/metrics/storeMetric (AnalyticsController)
- Unterstützt monatlich/quartalsweise/jährlich

### 7.6 Mitgliederwert-Engine (v1.4.0 geliefert)

- Mitglieder und Guthaben: MemberService openMember/recharge/consume/refund
- Punkte und Gutscheine: earnPoints/consumePoints/expirePoints Punktejournal + issueCoupon/redeemCoupon Gutscheineinlösung (MemberController / CouponController)

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
### 8.3 Visueller Workflow-Designer (v1.4.0 geliefert)

- Canvas-Komposition: WorkflowDesignerController liest und schreibt Knoten-/Verbindungsdefinitionen (Persistenz in canvas_json)
- Gleiche Quelle wie die Genehmigungs-Engine: nach der Veröffentlichung treibt die Canvas-Definition den Ablauf der Genehmigungskette

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
### 10.4 Projektkosten und Budgetabweichung (v1.4.0 geliefert)

- Kostenerfassung: ProjectCostService createManual manuelle Nacherfassung + generateFromTimesheet Umrechnung nach Arbeitsstunden × Mitarbeitersatz
- Gewinn-/Verlustsicht: projectPnl Projektrohertrag im Vergleich zum Budget

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
### 11.4 Recruiting und Leistungsbeurteilung (v1.4.0 geliefert)

- Recruiting: RecruitService Stellenausschreibung/-schließung, Fortschritt im Kandidaten-Funnel, Interviewaufzeichnungen, Offer-Versand/-Annahme/-Ablehnung
- Leistung: PerformanceService Kennzahlenvorlagen und Beurteilungspläne, 360-Bewertung (submitScore mehrere Bewerter), Zusammenfassung

### 11.5 Schulung, Sozialversicherung und Gehaltsabrechnung (v1.4.0 geliefert)

- Schulung: TrainingService Kurs/Einschreibung/Abschluss/Credit-Journal (employeeCredits)
- Sozialversicherung: SocialSecurityService Basisregeln (createRule/setRate), Mitarbeiterbindung bind/unbind, Probeberechnung calculate und Mitarbeiterdetail employeeSocialDetail
- Gehaltsabrechnung: PayslipService view schreibgeschützte Aufschlüsselung der Gehaltsbestandteile (Bezug/Abzug/Auszahlung, mit Sozialversicherungsergänzung)

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
### 12.6 Arbeitsrückmeldung und Akkordlohn (v1.4.0 geliefert)

- Arbeitsrückmeldung: WorkReportService audit prüft die Arbeitsrückmeldung der Arbeitsgänge
- Akkordlohn: PieceWageService accumulate Akkordjournal / periodSummary periodenweise Zusammenfassung

### 12.7 Subunternehmer-Verrechnung (v1.4.0 geliefert)

- SubcontractService: auditIssue Prüfung der Subunternehmer-Materialausgabe → auditReceive Wareneingangsverrechnung als geschlossener Kreislauf

### 12.8 Kapazitätslast (v1.4.0 geliefert)

- MfgCapacityService: calendar Kapazitätskalender der Arbeitsstationen, setException/removeException Kapazitätsausnahmen, report Lastanalyse

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
- 30-Tage-Verkaufstrend (mit Auffüllung der Nullwerte), Top-5-Bestseller (Bestellmenge der letzten 30 Tage), Verteilung der Bestellstatus (ausstehende Prüfung/geprüft/teilweise versandt/versandt/storniert)

### 14.3 Bestands-Dashboard
- Lagerwert, Warnstatistik (unter Untergrenze/über Obergrenze)
- Ein-/Ausgangstrend (nach Tag/Richtung)

### 14.4 Finanz-Dashboard
- Forderungs-/Verbindlichkeitsgesamtbeträge, Zahlungseingänge/-ausgänge des Monats
- Zusammenfassung der Kassen- und Banksalden
- Zusammenfassung der Fälligkeitsstruktur von Forderungen/Verbindlichkeiten (Eimer nicht fällig / überfällig 1-30/31-60/61-90/90+ Tage, nach nicht verrechnetem Saldo)

---

## Modulübergreifender Datenfluss

```
Wareneingang Einkauf → automatische Einlagerung (gleitende Durchschnittskosten) → Verbindlichkeitsbeleg erzeugen
Versand Verkauf → automatische Auslagerung → Forderungsbeleg erzeugen
Zahlungseingang/-ausgang → Verrechnung von Forderungen/Verbindlichkeiten → Tagebuch aktualisieren
Inventurdifferenz → automatische Erzeugung von Gewinn-/Verlust-Bewegungen
Genehmigungsantrag → Routing der Workflow-Engine → stufenweise Genehmigung → Benachrichtigungsversand
Auszahlung Spesenabrechnung → automatische Erzeugung von Zahlungsbeleg + Tagebuch
Anlagenabschreibung → monatliche Rückstellung → Kostenzuordnung auf Kostenstellen
MRP-Lauf → BOM-Auflösung → Nettobedarfsrechnung → Erzeugung von Einkaufs-/Produktionsvorschlägen
Urlaubsgenehmigung → Aktualisierung des Anwesenheitsstatus nach Genehmigung
Fertigstellung Produktion → automatische Einlagerung (Fertigerzeugnisse) + Abbuchung des Rohmaterialbestands
Arbeitszeitbuchung → Zusammenführung auf die Aufgabe → Aggregation zu Projektkosten
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
| Geschäftsmodule | 23 <!-- stats:modules=23 --> |
| Datenbanktabellen | 227 <!-- stats:tables=227 --> |
| Datenmodelle | 224 <!-- stats:models=224 --> |
| Controller | 159 <!-- stats:controllers=159 --> |
| Business-Services | 64 <!-- stats:services=64 --> |
| API-Routen | 837 (dynamisch erzeugt, siehe `scripts/check-endpoints.php`, nimmt nicht an der doc-stats-Prüfung teil) |
| Middleware | 11 <!-- stats:middleware=11 --> |
| PHP-Quelldateien | 483 <!-- stats:php_files=483 --> |
| Datenbank-Installationsskript | Einzeldatei `database/install.sql` (227 Tabellen, alle Migrationen bereits integriert) |
| Frontend-Seiten (Flutter) | 119 (Stand 2026-09-22, rekursive Dateizählung; nicht in der doc-stats-Prüfung enthalten) |
| Frontend-Seiten (HarmonyOS) | 52 (Stand 2026-09-22, rekursive Dateizählung; nicht in der doc-stats-Prüfung enthalten) |
| Unit-Tests | 111 Testdateien <!-- stats:test_files=111 --> / 1001 Testfälle <!-- stats:tests=1008 --> / 4726 Assertions <!-- stats:assertions=4768 --> (statische Zählung: Zahl der Testmethoden + Zahl der Assertion-Aufrufstellen, unabhängig von der Laufzeitumgebung) |

> Die obigen Zahlen werden real von `bash scripts/doc-stats.sh` gemessen; mit `<!-- stats:key=value -->` markierte Einträge werden von CI
> (docs-Job in `.github/workflows/ci.yml`) automatisch auf Übereinstimmung mit den Code-Fakten geprüft — Abweichungen werden rot.

---

## 19. Modul-Vollständigkeitsmatrix (Korrektur 2026-09-05; v1.17.0-Batch 2026-09-15)

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
| Systemverwaltung | ✅ | ✅ | ⚠️ 9/14 | ⚠️ 5 Seiten | 🔵 P0 |
| Dashboards | ✅ | ✅ | ✅ 2 Seiten | ⚠️ 1 Seite | 🔵 P0 |
| Artikel-Stammdaten | ✅ | ✅ | ✅ 7/7 | ⚠️ 1/7 | 🔵 P0 |
| Einkaufsverwaltung | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Vertriebsverwaltung | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Bestandsverwaltung | ✅ | ✅ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Finanzen — Belege/Forderungen-Verbindlichkeiten | ✅ | ⚠️ | ✅ 16 Seiten | 🔴 | 🔵 P0 |
| Finanzen — Hauptbuch/Drei Abschlüsse | ⚠️ | 🔴 | ⚠️ 3 Seiten (Aktionstiefe zu prüfen) | 🔴 | 🟢 P1 |
| Finanzen — Berichtskonsolidierung | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finanzen — Multi-Organisations-Rechnungswesen (F1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finanzen — Periodenabschluss | 🔴 | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Finanzen — Bestandskostenrechnung (F3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| CRM Gesamtmodul | ✅ | ✅ | ✅ 10/10 | 🔴 | 🔵 P0 |
| OMS Auftragsverwaltung | ✅ | ✅ | ✅ 4/4 | ⚠️ 4 Seiten | 🔵 P0 |
| WMS Lagerverwaltung | ✅ | ✅ | ⚠️ 7/8 | ⚠️ 7 Seiten | 🔵 P0 |
| TMS Transportverwaltung | ✅ | ✅ | ⚠️ 5/6 | ⚠️ 5 Seiten | 🔵 P0 |
| Genehmigungsworkflow | ✅ | ✅ | ⚠️ 2/3 | ⚠️ 1 Seite | v1.4.0 |
| Benachrichtigungssystem | ✅ | ✅ | ⚠️ 1/2 | 🔴 | v1.4.0 |
| Kreditkontrolle (F7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Rückverfolgbarkeitskette/Verfallsnähe (M6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Kapazitätslast (M3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Arbeitsrückmeldung/Akkordlohn/Subunternehmer-Verrechnung (M1+M2) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Workflow-Designer (B3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Druckvorlagen (B1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Recruiting/Leistung/Schulung/Sozialversicherung (H1-H4) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Prüfungs-QR-Scan (E1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Projektkosten/Budget (P1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Wechselpapiere/Bankabstimmung (F6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Eingangsrechnungspool/E-Rechnung (F5) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Mitgliederwert (C1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Multi-Mandant (B5) | ✅ | ⚠️ | 🔴 | 🔴 | v1.4.0 teilweise aktiviert |
| Kanalbenachrichtigung/benutzerdefinierte Felder (B4+B7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Projektmanagement | ✅ | ✅ | ✅ 3/3 | 🔴 | 🔵 P0 |
| HR — Organisation/Anwesenheit/Urlaub | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 3 Seiten | 🔵 P0 |
| HR — Gehalts-Engine | ⚠️ | 🔴 | ⚠️ 2 Seiten | 🔴 | 🟢 P1 |
| Fertigung — BOM/Produktion/MRP | ✅ | ✅ | ⚠️ 5/13 | ⚠️ 5 Seiten | v1.4.0 |
| Qualitätsmanagement | ✅ | ✅ | ✅ 5/5 | 🔴 | 🟢 P1 |
| Benutzerdefinierte Berichte | ✅ | ⚠️ | ✅ 2/2 | 🔴 | 🔵 P0 |
| BI-Dashboards | ✅ | ✅ | ⚠️ 2/3 | 🔴 | 🟣 P3 |
| Anlagenverwaltung EAM | ✅ | ✅ | ⚠️ 4/5 | 🔴 | v1.4.0 |
| Dokumentenverwaltung DMS | ✅ | ✅ | ⚠️ 1/2 | 🔴 | 🟣 P3 |
| Mehrsprachigkeit (i18n) | ✅ | ✅ | ⚠️ nur ZH/EN | ⚠️ nur ZH/EN | v1.17.0 |
| Beobachtbarkeit | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Migrations-Rollback/Backup | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Statistik

| Dimension | ✅ Abgeschlossen | ⚠️ Skelett | 🔴 Fehlt | N/A | Fertigstellungsrate |
|------|---------|----------|---------|-----|--------|
| Module (44) | 33 | 11 | 0 | 0 | 75% |
| Backend-API | 39 | 4 | 1 | 0 | 89% |
| Businesslogik | 33 | 7 | 4 | 0 | 75% |
| Flutter-Frontend | 12 | 12 | 18 | 2 | 29% |
| HarmonyOS | 0 | 13 | 29 | 2 | 0 % (✅-Zählung; 13 Zeilen haben bereits Seiten ⚠️) |

> **Statistik-Methodik (Korrektur 2026-09-05)**: Modulzeilen werden als „Backend-API und Businesslogik beide implementiert" gezählt —
> doppeltes ✅ = abgeschlossen, alles darunter zählt als Skelett ⚠️ (einschließlich der Zeile „teilweise aktiviert":
> Multi-Mandant B5 mit nicht registrierter Isolations-Middleware und weiteren historischen Lücken, siehe Code-Beweise);
> die beiden Zeilen Backend-API / Businesslogik werden nach der jeweiligen Matrixspalte gezählt, der Nenner der
> Fertigstellungsrate zieht die N/A-Zeilen ab (Beobachtbarkeit und Migrations-Rollback haben kein Frontend).
> **Spalten Flutter / HarmonyOS (ab 2026-08-27 auf die Zählweise „Seitenaktions-Abdeckung" umgestellt)**: ✅ = für das Modul
> existieren Seiten und die Zahl der Seitendateien ist ≥ der Zahl der Backend-Controller (`n/n` oder Seitenangabe); ⚠️ = Seiten
> vorhanden, aber Seitendateien < Backend-Controller (Teilabdeckung); 🔴 = keine Seiten; **zu prüfen** = Seiten vorhanden, die
> Aktionstiefe (durchgängiges CRUD) wurde aber nicht Seite für Seite verifiziert. **Die Seitenzahlen der einzelnen Zeilen folgen
> dem Snapshot vom 2026-08-27** (Dateizahlen unter `apps/flutter/lib/app/pages/<Modul>/` und
> `apps/harmonyos/entry/src/main/ets/pages/**`, damals Flutter 107 Seiten / HarmonyOS 35 Seiten);
> die Nachmessung vom 2026-09-22 ergab insgesamt Flutter 119 Seiten / HarmonyOS 52 Seiten (**die Zeilenzahlen wurden nach der
> neuen Zählweise nicht neu berechnet**, die ✅/⚠️-Bewertung folgt weiter dem Snapshot vom 2026-08-27) und ist nicht in die
> Backend-doc-stats-Prüfung einbezogen; die Fertigstellungsrate von HarmonyOS von 0 % stammt aus der ✅-Zählung (0/42),
> tatsächlich haben 13 Zeilen bereits Seiten (⚠️ Teilabdeckung), es fehlt also nicht die ganze Spalte.
> **Deduplizierung 2026-09-15**: Die Matrix enthielt ursprünglich zwei Zeilen „Multi-Mandant" (`Multi-Mandant (B5)` und
> `Multi-Mandant`, deren Businesslogik-Spalte sich mit ✅ / ⚠️ widersprach) und wurde nach der obigen Methodik zu einer Zeile
> „Multi-Mandant (B5)" mit ⚠️ zusammengeführt (Isolations-Middleware nicht registriert), Modulzeilen 45 → 44.
> **v1.17.0-Batch (2026-09-15)**: neue Zeile „Mehrsprachigkeit (i18n)" (1 der 44 Zeilen mit v1.17.0 markiert) — Backend mit
> 13 Sprachwörterbüchern (`resource/translations/<locale>/`, 13 Verzeichnisse) und `Accept-Language`-Aushandlung ✅;
> Flutter / HarmonyOS bleiben bei zwei Sprachen (Chinesisch/Englisch), daher beide Frontend-Spalten ⚠️; Angular / React
> verfügen bereits über 13-Sprachen-Wörterbücher (nicht Teil der Spalten dieser Matrix).
> **v1.4.0-Batch (2026-09-05)**: 21 der 44 Zeilen sind mit v1.4.0 markiert (davon 1 Zeile „teilweise aktiviert" = Zeile
> Multi-Mandant (B5)), abgedeckt sind Multi-Organisation/Konsolidierung/Bestandskosten (F1-F3), Fertigung M1/M2/M3/M6,
> Kredit F7, Wechsel/Bank/Eingangspool/E-Rechnung F6/F5, HR H1-H4, Mitglieder C1, Plattform B1-B5/B7, Prüfungs-QR E1,
> Projektkosten P1; Belege siehe „Code-Beweise" unten.

### Code-Beweise (Korrektur 2026-09-05; inkl. v1.17.0-Batch)

Grundlage dieser Vollständigkeitskorrektur (Dateiexistenz kann durch `bash scripts/doc-stats.sh` und `find` belegt werden):

| Modul | Korrektur | Code-Beweis |
|------|------|----------|
| Qualitätsmanagement | 🔴 → ✅ | `app/controller/quality/` (5 Controller) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| BI-Dashboards | 🔴 → ✅ | `app/controller/bi/` (3 Controller: Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Anlagenverwaltung EAM | 🔴 → ✅ (+E1 Prüfung) | `app/controller/eam/` (5 Controller, inkl. `EamInspectionController.php`) + `app/service/eam/EamInspectionService.php` + `tests/EamModuleTest.php` |
| Dokumentenverwaltung DMS | 🔴 → ✅ | `app/controller/dms/` (2 Controller) + `tests/DmsModuleTest.php` |
| Multi-Mandant | ⚠️ → ⚠️ (v1.4.0 teilweise aktiviert) | `app/controller/platform/TenantController.php` + `app/service/platform/TenantService.php` (provision/suspend/resume/expireMark/renew/expiryWarnings; Ablaufabrechnung ist geliefert) + `tests/Integration/TenantScopeIntegrationTest.php`; die Isolations-Middleware `app/middleware/TenantScope.php` ist weiterhin nicht registriert (Isolation nicht wirksam), daher teilweise aktiviert |
| Berichtskonsolidierung | ⚠️ → ✅ (v1.4.0 geliefert) | `app/service/finance/ConsolidationService.php` (rateToBase/translateLedger mit Stichtagskurs-Umrechnung, addElimination für konzerninterne Eliminierung mit Prüfung der Bilanzgleichheit, generateDraft mit Snapshot-Vorrang / Echtzeit-Neuberechnung ohne Snapshot, issue mit Duplikatschutz, latest/list; ohne Kurs wird abgelehnt) + Ergebnis in `app/model/FinanceConsolidationReport.php` + `tests/ConsolidationServiceTest.php` (3 Fälle) |
| Periodenabschluss | 🔴 → ⚠️ | `app/service/finance/PeriodCloseService.php:21` `closeProfitAndLoss()` (Zusammenfassung der Erfolgskonten implementiert, erzeugt aber keinen Abschlussbeleg und hat keinen Controller-Endpunkt) + `tests/PeriodCloseServiceTest.php` (4 Fälle) |
| v1.4.0 — Multi-Organisations-Rechnungswesen (F1) | neu | `app/controller/finance/CompanyController.php` + `LedgerPeriodController.php` (getrennte Rechnungslegungssubjekte und Rechnungsperioden) |
| v1.4.0 — Bestands- und Produktionskostenrechnung (F3) | neu | `app/controller/manufacturing/MaterialIssueController.php` + `CostEntryController.php` + `app/service/manufacturing/MfgCostService.php` + `MfgCostVoucherRule.php` |
| v1.4.0 — Fertigungsausführung M1/M2/M3/M6 | neu | `app/service/manufacturing/` (WorkReportService/PieceWageService/SubcontractService/MfgCapacityService) + `app/service/inventory/TraceService.php` (Chargen-/Seriennummern-Rückverfolgung und Verfallsdatumswarnung) + zugehörige Controller WorkReport/PieceWage/Subcontract/Capacity |
| v1.4.0 — Finanzmittel/Steuern F6/F5 + F7 | neu | `app/service/finance/FinanceBillService.php` + `BankReconService.php` (Kontoauszugsimport / automatische und manuelle Abstimmung) + `app/service/tax/TaxInvoicePoolService.php` + `EInvoiceService.php` (EInvoiceAdapter/MockEInvoiceAdapter, die echte Steuerbehörde ist der vorbereitete Anpassungspunkt) + `app/service/sales/CreditControlService.php` (Abfangen von Bestellungen über dem Limit per Assertion) |
| v1.4.0 — Mitglieder/HR/Projekt C1/H1-H4/P1 | neu | `app/service/retail/MemberService.php` + `app/controller/retail/` (MemberController/CouponController) + `app/service/hr/` (RecruitService/PerformanceService/TrainingService/SocialSecurityService/PayslipService) + `app/service/project/ProjectCostService.php` |
| v1.4.0 — Plattform/Kanäle B3/B4/B7/E1 | neu | `app/controller/workflow/WorkflowDesignerController.php` (canvas_json-Persistenz) + `app/service/notification/` (ChannelDriver/ChannelService/MockChannelDriver/MailMockChannelDriver, Wiederholung bei Fehlschlag) + `app/controller/notification/NotificationChannelController.php` (`tests/NotificationChannelTest.php` 5 Fälle) + `app/controller/platform/CustomFieldController.php` + `app/controller/eam/EamInspectionController.php` (Prüfungs-QR-Scan) |
| v1.17.0 — Mehrsprachigkeit (i18n) | neu | Backend: `resource/translations/` (13 Sprachverzeichnisse zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id, je Sprache die drei Dateien common+modules+validation, 11 Sprachen mit je 542 Einträgen (zh_CN 533, en 30); bei en gilt „Englisch ist der Key") + `app/common/I18n.php` (`getLocale()` wertet das erste Tag aus `Accept-Language` aus, Mapping der Hauptsprach-Subtags zh*→zh_CN; `trans()` geht für Nicht-en über `[Anfragesprache, zh_CN, en]`→Key, bei en wird kein Chinesisch hinterlegt) + `config/translation.php` + Generatorskript `scripts/gen-be-locales.mjs`; Frontend: `apps/react/src/lib/i18n/` (`index.tsx` + `zhEn` + 11 Sprachdateien) und `apps/angular/src/app/core/` (`zh-en/` (index + part1..4) + `zh-{ar,bn,de,es,fr,hi,id,ja,ko,pt,ru}.ts`) (die Wörterbücher der 11 neuen Sprachen werden je Sprache per Lazy-Loading geladen, Angular 1453 / React 1447 Schlüssel) + `scripts/gen-fe-locales.mjs`; Umschaltpunkte = Globus-Symbol in der oberen Leiste + Dropdown im persönlichen Bereich; Flutter (`apps/flutter/lib/l10n/`) und HarmonyOS (`entry/src/main/resources/`) bleiben bei zwei Sprachen (Chinesisch/Englisch) |
> Detaillierte Roadmap-Designspezifikation: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
