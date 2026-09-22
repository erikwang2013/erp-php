# Open-ERP-System (open-erp)

Vollständiges ERP-System auf Basis von webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="open-erp Maskottchen Oktopus" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | Deutsch | [Français](../fr/README.md) | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [English version](../en/README.md) | [Versionsvergleich](EDITIONS.md) | [Architekturdiagramme](ARCHITECTURE.md) | [Systemarchitektur](#systemarchitektur) | [Designdokument](DESIGN.md) | [Sicherheitsarchitektur](SECURITY.md) | [API-Referenz](API.md) | [Funktionshandbuch](FUNCTIONS.md)
## Projektbeschreibung

open-erp ist ein **Open-Source-Vollstack-ERP-System** für kleine und mittlere Unternehmen und deckt die vollständigen Geschäftsdomänen Warenwirtschaft (Einkauf/Vertrieb/Lager), Finanzbuchhaltung, Produktion und Fertigung (BOM/MRP/Arbeitsrückmeldung/Kapazitätslast), CRM, Genehmigungsworkflow, Personalwesen, Benachrichtigungen und benutzerdefinierte Berichte ab. Das Backend basiert auf webman v2 + MySQL 8.0 (Tabellenpräfix `erp_`, global eindeutige Snowflake-Primärschlüssel); die Verwaltungsoberfläche bietet drei Implementierungen: Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`) und Flutter 3.x Web (`apps/flutter/`), ergänzt durch einen nativen HarmonyOS-Client (`apps/harmonyos/`) für Mobilgeräte.

Kern des Designs ist die **Beleggetriebenheit mit automatischer Verknüpfung**: Die Prüfung eines Geschäftsbelegs löst automatisch Lagerbewegungen, die Erzeugung von Forderungen/Verbindlichkeiten und die Kostenverteilung aus; Genehmigungsworkflow und Benachrichtigungen durchziehen alle Schlüsselbelege; MRP berechnet den Materialbedarf aus Verkaufsaufträgen und BOM und erzeugt Einkaufs-/Produktionsvorschläge — so entsteht ein durchgängiger Geschäftskreislauf von der Auftragsannahme über den Wareneingang bis zur Produktionsplanung und zum Finanzabschluss.

## Hinweise zum Projekt

- **Exakte Dezimalrechnung**: Beträge, Mengen und Gewichte werden mit bcmath dezimal berechnet; gleitende Durchschnittskosten, die Verrechnung von Forderungen/Verbindlichkeiten und alle Berichtsausgaben erfolgen mit String-Genauigkeit, ohne Gleitkommafehler
- **Unternehmensgerechte Sicherheitsbasis**: JWT-Token + RBAC-Autorisierung auf Methodenebene, Tiefenverteidigung (L0–L12-Schichtenpanorama + 35 Klassen von Angriffsdetektoren + 7-stufige Middleware-Kette, XSS/SQL-Injection/CSRF/Rate-Limit/CSP usw.), verschlüsselte Speicherung sensibler Felder und verschlüsselte Übertragung über die Schnittstelle, lückenlose Betriebsprüfung
- **Konfigurierbarkeit**: mehrstufiger Genehmigungsworkflow (inkl. Canvas des visuellen Workflow-Designers), Belegdruck-Vorlagen-Engine (Platzhalter-Rendering + PDF-Ausgabe über dompdf + QR-Code-Etiketten), Echtzeit-Sperre für Kundenkreditlimits, vollständige Vorwärts-/Rückwärtsverfolgung von Chargen/Seriennummern
- **Datenrückverfolgbarkeit**: Jede Geschäftsbewegung wird einzeln protokolliert; Lagerchargen und Seriennummern durchlaufen den gesamten Lebenszyklus Einlagerung→Entnahme→Auslagerung→Rückverfolgung, die Kostenrechnung reicht bis auf Belegzeilenebene
- **Einfache Bereitstellung**: Docker Compose v2 startet mit einem Befehl (MySQL/Redis/Elasticsearch), lokal genügt `composer install`
- **Internationalisierung**: 13 Sprachen (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), vollständig für die Backend-Meldungen und die Oberflächen beider Verwaltungs-Frontends Angular/React; die Frontend-Wörterbücher werden je Sprache per Lazy-Loading geladen, das README ist zusätzlich in 12 Sprachversionen verfügbar

## Funktionsübersicht

| Geschäftsbereich | Funktion | Beschreibung |
|--------|------|------|
| 🔐 Authentifizierung | Login/Registrierung/Token-Refresh/Logout | Klick-Captcha + JWT + Blacklist |
| | Kontosperrung | 5 Fehlversuche sperren für 15 Minuten |
| | Begrenzung paralleler Sitzungen | Maximal 3 gültige Token pro Benutzer |
| 📊 Dashboard | Geschäftsübersicht + sechs Dashboards (Vertrieb/Bestand/Finanzen/OMS/WMS/TMS) | 30-Tage-Umsatztrend/Top5-Verkaufsschlager/Auftragsstatus-Verteilung/Forderungs-Verbindlichkeiten-Fälligkeiten + Redis-Cache 5 Minuten |
| 👥 Benutzerverwaltung | CRUD + Massenlöschung/Aktivieren-Deaktivieren | Soft Delete + Passwort-Bestätigung |
| | Excel-Massenimport | Zeilenweise Validierung + Fehlerbericht |
| 🔒 Rollen & Berechtigungen | Rollen-CRUD + Berechtigungsbaum | RBAC-Autorisierung auf method.path-Ebene |
| ⚙ Systemkonfiguration | Schlüssel-Wert-CRUD | Gruppenverwaltung |
| 📋 Prüfprotokoll | Protokollabfrage + Quellgerät-Erkennung | Automatische Erkennung von 8 Plattformen |
| 📁 Dateiverwaltung | Upload/Excel-Export/PDF-Export | Automatische Maskierung sensibler Daten |
| 🛡 Sicherheit | 35 Angriffserkennungen + 7-stufige Middleware-Kette | XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF/Rate-Limit/CSP... |
| 🏥 Betrieb | Health-Check/metrics/API-Dokumentation/security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Artikelverwaltung | Artikelstamm/SKU/Multi-Spezifikation/Multi-Einheit/Kategorien/Marken/Preisstrategien | Mehrstufiger Kategorienbaum + Einheitenumrechnung |
| | Lager & Lagerplätze | Verwaltung mehrerer Lager und Lagerplätze |
| | Lieferanten-/Kundenstamm | Ansprechpartner/Bankkonten/Kreditlimits |
| 📥 Einkaufsverwaltung | Anfrage→Bestellung→Wareneingang→Retoure→Abrechnung | Vollständiger Einkaufsprozess + Genehmigung |
| | Beschaffung per Ausschreibung (Anfrage→Angebot→Zuschlag als Bestellung) | Vergleich mehrerer Lieferanten, Angebote müssen alle Positionen der Anfrage abdecken, Zuschlag wird per Klick zur Bestellung |
| | Lieferantenbewertung | Gesamtpunktzahl 0–100 mit automatischer Einstufung (A ≥ 90 / B ≥ 70 / C) + Bewertungsdimensionen als JSON + Nachvollziehbarkeit des Bewerters |
| 📤 Vertriebsverwaltung | Angebot→Auftrag→Versand→Retoure→Abrechnung | Angebot-zu-Auftrag + Verkaufsrohertrag |
| | Kundenkreditkontrolle | Limit/Zahlungsziel/Sperre verwalten + Überschreitungsblockade bei Auftrag und Versand |
| 🏗 Bestandsverwaltung | Echtzeitbestand/Chargen/Seriennummern/Umlagerung/Inventur/Warnungen | Gleitende Durchschnittskostenrechnung |
| 💰 Finanzverwaltung | Forderungen/Verbindlichkeiten/Ein-/Ausgänge/Tagebuch/Spesen/GuV/Anlagevermögen/Steuern/Multi-Währung/Budget/Kosten- und Profit-Center | Automatische Forderungen/Verbindlichkeiten + Verrechnung + umfassende Finanzverwaltung |
| | Multi-Organisation + konsolidierte Berichte | Mehrere Unternehmen/Buchungskreise + Eliminierungsbuchungen (Equity-/Anschaffungskostenmethode) |
| | Bestands-/Produktionskostenrechnung | Fertigungsentnahme → Arbeits-/Gemeinkostensammlung → Herstellkosten → Kostenabweichungsübertrag |
| | Wechsel + Bankabstimmung | Wechselregister + automatische Abstimmung per Bankkontoauszug-Import |
| | Eingangsrechnungspool + E-Rechnung | Eingangsrechnungsverwaltung + Ausgangskanal (Adapter + Mock-Kanal) |
| 🤝 CRM | Kunden/Kontakte/Follow-up-Records/Marketingkampagnen/Service-Tickets/Analyseberichte/Sales-Funnel/Shared-Pool/Angebote/Verträge | Verwaltung des gesamten Kundenlebenszyklus |
| | Kundenwert-Engine | Guthaben-/Punkte-/Kartenprogramm-Mitgliedschaft |
| ✅ Genehmigungsworkflow | Workflow-Definition/Einreichung/Genehmigen/Ablehnen/Zurückziehen/Meine Genehmigungen | Mehrstufige Genehmigungs-Engine |
| | Visueller Workflow-Designer | Knoten/Verzweigungen/Rücksprung-Kanten auf der Leinwand, nutzt Genehmigungs-Engine |
| 🔔 Benachrichtigungen | Benachrichtigungsliste/Gelesen-Markierung/Ungelesen-Zähler/Alles gelesen | Echtzeit-Push und Statusverfolgung |
| | Multikanal-Benachrichtigungen | SMS/E-Mail-Kanaltreiber (Mock-Kanal + Logging + Retries) |
| 📐 Projektmanagement | Projekte/Aufgaben/Zeiterfassung | Projektfortschritt & Ressourcenverwaltung |
| | Projektkosten & Budget | Stunden × Satz → Projektkostenerfassung + Budgetabweichung |
| 👤 Personalwesen | Abteilungen/Mitarbeiter/Positionen/Anwesenheit/Urlaub/Gehälter | Umfassende Personalverwaltung |
| | Rekrutierung/Leistung/Schulung/Sozialversicherung | Recruiting-Trichter + KPI/360-Bewertung + Kurs-Credits + Beitragsbemessungsregeln & Gehaltsabrechnung |
| 🏭 Produktion | BOM/Produktionsaufträge/Arbeitspläne/Arbeitsplätze/MRP | Materialbedarfsplanung und Produktionsausführung |
| | Arbeitsrückmeldung/Akkordlohn/Subunternehmer-Verrechnung | MES-Arbeitsschrittausführung + Materialausgabe und Verrechnung bei Fremdfertigungsaufträgen |
| | Kapazitätslast-Analyse | Arbeitsplatzkalender + Grobkapazitätsbericht |
| | Chargen-/Seriennummern-Rückverfolgung | Vorwärts-/Rückwärts-Rückverfolgungskette + Ablaufwarnung |
| 📈 Benutzerdefinierte Berichte | Berichtsvorlagen/Datensätze/Felder/Filter/Ausführung/Zeitplanung | Visueller Berichts-Builder |
| 📋 Auftragsverwaltung (OMS) | Multi-Kanal-Aufträge/Fulfillment-Orchestrierung/Reservierung/Zuordnung/Stornierung/RMA-Retouren | Verwaltung des gesamten Auftragslebenszyklus |
| 🏗 Lagerverwaltung (WMS) | Zonen/Lagerplätze/ASN/Wareneingang/Einlagerung/Wellen/Kommissionierung/Verpackung/Versand | Vollständiger Lagerablauf |
| 🚚 Transportverwaltung (TMS) | Spediteure/Dienste/Tarife/Frachtscheine/Tracking/Frachtrechnungen | Multi-Spediteur-Tarifvergleich + Sendungsverfolgung |
| 🛠 Anlagenverwaltung (EAM) | Anlagenstamm/Wartungspläne/Reparaturaufträge/Ersatzteile | Verwaltung des gesamten Anlagenlebenszyklus |
| | QR-Prüf-Regelkreis | Scan-Prüfung, Störungen lösen automatisch Reparaturauftrag aus |
| 🌐 Plattform & Offenheit | API-Pfadversionierung | Admin /admin/v1, Client /api/v1, Open /open/v1 (kein Versions-Header) |
| | Druckvorlagen-Engine | Platzhalter-Rendering + dompdf PDF + QR-Code-Etiketten |
| | Benutzerdefinierte Formularfelder | Mastertabellen-Erweiterung per custom_fields JSON + Validierung |
| | Multi-Tenant-Architektur | erp_tenant-Mandant + TenantScope-Anforderungskontext + Ablauf-Abrechnung (Middleware-Seam reserviert, nicht registriert) |
| | Mehrsprachigkeit | 13 Sprachen (Backend-Meldungen + Oberflächen beider Verwaltungs-Frontends Angular/React), Wörterbücher je Sprache per Lazy-Loading als eigener Chunk, Umschaltpunkte dauerhaft als Globus-Symbol in der oberen Leiste und als Dropdown im persönlichen Bereich |

## ERP-Module

Datenfluss zwischen den Geschäftsmodulen:

- Einkauf-Wareneingang → automatische Einlagerung (gleitende Durchschnittskosten) → automatische Verbindlichkeiten
- Vertriebs-Versand → automatische Auslagerung → automatische Forderungen
- Ein-/Ausgänge → Verrechnung von Forderungen/Verbindlichkeiten → Aktualisierung des Tagebuchs
- Belegprüfung → automatische Aktualisierung des Hauptbuchs (Kontenzusammenfassung) + Detailbuch (Einzelposten)
- Bilanz → automatisch aus den Salden des Hauptbuchs zum Periodenende erzeugt
- Kapitalflussrechnung → automatisch aus den Kassen- und Banktagebüchern erzeugt (Betrieb/Investition/Finanzierung)
- Genehmigungsworkflow → Geschäftsbelege zur Genehmigung → Mehrstufenfluss → Ergebnisrückmeldung an die Fachmodule
- Benachrichtigungen → ausgelöst durch Genehmigung/Warnungen/Systemereignisse → Echtzeit-Push → Benutzer markiert als gelesen
- MRP → basierend auf Verkaufsaufträgen + BOM → Berechnung des Materialbedarfs → Erzeugung von Einkaufs-/Produktionsvorschlägen
- OMS → Import von Multi-Kanal-Aufträgen → Bestandsreservierung (ATP) → Fulfillment erstellen → WMS-Kommissionierung/Verpackung anstoßen
- WMS → Wellen-Aggregation → Kommissionieraufgaben → Kommissionierbestätigung → Verpackung abgeschlossen → TMS-Frachtschein erzeugen
- TMS → Frachttarifvergleich → Frachtschein erstellen → Versand bestätigen (stockOut+AR) → Sendungsverfolgung → Zustellung
- WMS-Eingang → ASN-Vormeldung → Wareneingang → Qualitätsprüfung → Einlagerungsbestätigung (stockIn+AP) → Bestandsaktualisierung
- RMA → Retoure-Antrag → Genehmigung → Retoure-Einlagerung → Erstattung

## Technologie-Stack

| Ebene | Technologie | Beschreibung |
|---|------|------|
| Backend-Framework | webman v2 (workerman) | Hochleistungs-PHP-Framework mit dauerhaft laufenden Prozessen |
| PHP-Version | 8.3+ | |
| Datenbank | MySQL 8.0+ | Tabellenpräfix `erp_`, BIGINT-Primärschlüssel ohne AUTO_INCREMENT |
| Suchmaschine | Elasticsearch | Automatische Index-Synchronisierung beim Schreiben/Löschen über `webman-scout` (optionales Modul, siehe Abschnitt „Volltextsuchmaschine“) |
| Admin-Frontend A | Angular 22 | config-getriebene Ressourcenseiten, `ResourcePage`-Rendering-Engine (`apps/angular/`) |
| Admin-Frontend B | React 19 + Vite | config-getrieben wie Angular + Style-Tokens (`apps/react/`) |
| Admin-Frontend C | Flutter 3.x | Web-Version im PC-Admin-Stil (`apps/flutter/`) |
| Mobile Clients | HarmonyOS ArkTS | Natives HarmonyOS-Client (`apps/harmonyos/`), unterstützt Smartphone/Tablet/2in1 |

## Kernabhängigkeiten

| Paket | Zweck |
|---|------|
| `erikwang2013/snowflake-php` | Snowflake-Algorithmus für global eindeutige BIGINT-Primärschlüssel |
| `erikwang2013/hashids` | ID-Verschlüsselung/-Entschlüsselung in der API-Schicht, verbirgt echte Datenbank-IDs |
| `erikwang2013/jwt-webman` | JWT-Authentifizierung: Token-Ausstellung und -Prüfung |
| `erikwang2013/encryption` | Verschlüsselung sensibler Daten in der Übertragungsschicht |
| `erikwang2013/encryptable` | Automatische Ver-/Entschlüsselung sensibler Felder in der Speicherschicht |
| `erikwang2013/webman-scout` | Elasticsearch-Datensynchronisierung und Volltextsuche |
| `erikwang2013/season` | Nationalflaggen-Daten |
| `erikwang2013/poster-php` | Klick-Captcha-Erzeugung/-Prüfung + Poster-Generierung |
| `erikwang2013/security-php` | Sicherheitsprüfungen |
| `phpoffice/phpspreadsheet` | Excel-Export |
| `barryvdh/laravel-dompdf` | PDF-Export (basiert auf Dompdf) |
| `erikwang2013/apidoc-php` | Automatische API-Dokumentation | Annotationsbasierte Schnittstellendoku, gruppiert nach Admin/Client |

## Internationalisierung

Das System unterstützt **13 Sprachen**: `zh` (Standard), `en`, `ja`, `ko`, `de`, `fr`, `es`, `pt`, `ru`, `ar`, `hi`, `bn`, `id`.

| Ebene | Wörterbuchposition | Umfang |
|---|---------|------|
| Backend-Meldungen | `resource/translations/{Sprache}/` | 13 Sprachverzeichnisse: `zh_CN` 565 Einträge, die übrigen 11 Sprachen je 544 Einträge, `en` 30 Einträge (Zählweise: Blatteinträge der drei Dateien `common/modules/validation`; die Feldlabels in `attributes` von `validation.php` zählen mit, deren Gruppenschlüssel nicht) |
| Angular-Administration | `apps/angular/src/app/core/zh-*.ts` (Quellwörterbuch `zh-en/`, aus 4 Teilen zusammengeführt) | Quellwörterbuch 1456 Schlüssel × 11 neue Sprachen (Schlüsselzahl je Sprache 1:1 zum Quellwörterbuch) |
| React-Administration | `apps/react/src/lib/i18n/zh*.ts` | Quellwörterbuch 1451 Schlüssel × 11 neue Sprachen |

- **Backend: „Englisch ist der Key"**: Die Backend-Message-Keys sind selbst der englische Text; `en` pflegt nur wenige Zuordnungen wie Framework-Regelnamen und benötigt kein vollständiges Wörterbuch
- **Lazy-Loading je Sprache**: Die 12 Frontend-Wörterbücher werden jeweils als eigener Chunk gebündelt und beim Sprachwechsel bei Bedarf geladen, ohne das erste Bild zu belasten
- **Umschaltpunkte**: eigenes Globus-Symbol in der oberen Leiste + Dropdown im persönlichen Bereich (in beiden Frontends Angular/React identisch)
- **Schnittstellenebene**: automatische Erkennung über den Request-Header `Accept-Language` (zh-CN → Chinesisch, en → English, die übrigen Sprachen werden nach Liste zugeordnet), Standard ist Chinesisch
- **Generatoren**: `scripts/gen-be-locales.mjs` (Backend), `scripts/gen-fe-locales.mjs --app angular|react` (Frontend), mit Unterstützung für Wiederaufnahme nach Abbruch

## Projektstruktur

```
open-erp/
├── app/
│   ├── admin/controller/       # Systemverwaltungs-Controller (16)
│   ├── api/v1/controller/      # Client-API (Version im Pfad /api/v1, kein Versions-Request-Header)
│   ├── controller/             # Geschäftsmodul-Controller (139, 23 Domänen)
│   │   ├── product/            # Artikel/Kategorien/Marken/Lager/Lagerplätze/Lieferanten/Kunden (8)
│   │   ├── purchase/           # Einkaufsanfrage/Bestellung/Wareneingang/Retoure/Abrechnung/Angebotsanfrage/Angebot/Lieferantenbewertung (8)
│   │   ├── sales/              # Verkaufsangebot/Auftrag/Versand/Retoure/Abrechnung (5)
│   │   ├── inventory/          # Bestand/Bewegungen/Umlagerung/Inventur/Warnungen (6)
│   │   ├── finance/            # Forderungen-Verbindlichkeiten/Belege/Zahlungen/Journal/Hauptbuch/Nebenbuch/Berichte/Anlagen/Steuern/Mehrwährung/Budget/Kosten- und Profit-Center/Wechselpapiere/Abstimmung/Rechnungen (28)
│   │   ├── crm/                # Verkaufschancen/Follow-up/Funnel/Kontakte/Lead-Pool/Verträge/Angebote/Marketing/Tickets/Analyse (10)
│   │   ├── workflow/           # Workflow-Definition/Genehmigung/Prozessdesigner (3)
│   │   ├── notification/       # In-App-Benachrichtigungen/Kanalversand (2)
│   │   ├── project/            # Projekt/Aufgabe/Arbeitszeit/Kosten (4)
│   │   ├── hr/                 # Abteilung/Mitarbeiter/Position/Anwesenheit/Urlaub/Gehalt/Recruiting/Leistung/Sozialversicherung/Schulung (9)
│   │   ├── manufacturing/      # BOM/Fertigungsauftrag/Arbeitsplan/Arbeitsstation/MRP/Arbeitsrückmeldung/Subunternehmer/Kosten/Kapazität (13)
│   │   ├── report/             # Berichtsvorlage/Datensatz/Ausführung/Zeitplanung (2)
│   │   ├── print/              # Druckvorlagen-Engine (1)
│   │   ├── retail/             # Mitgliederguthaben/Punkte/Gutscheine (2)
│   │   ├── platform/           # Mandanten/benutzerdefinierte Felder (2)
│   │   ├── quality/            # Qualitätsprüfung (5)
│   │   ├── eam/                # Anlagen/Wartung/Reparatur/Ersatzteile/Inspektion (5)
│   │   ├── bi/                 # Business Intelligence (3)
│   │   ├── dms/                # Dokumentenverwaltung (2)
│   │   ├── oms/                # OMS-Auftrag/Fulfillment/RMA/Kanäle (4)
│   │   ├── wms/                # Zone/Lagerplatz/ASN/Wareneingang/Einlagerung/Welle/Kommissionierung/Verpackung (8)
│   │   ├── tms/                # Spediteur/Service/Tarif/Frachtbrief/Tracking/Frachtkostenrechnung (6)
│   │   └── open/               # Schnittstellen der offenen Plattform (1)
│   ├── service/                # Geschäftslogikschicht (64)
│   │   ├── inventory/          # Ein-/Auslagerung + gleitende Durchschnittskosten + Bestandsreservierung/ATP
│   │   ├── finance/            # automatische Erzeugung von Forderungen/Verbindlichkeiten + Verrechnung
│   │   ├── notification/       # Benachrichtigungsversand
│   │   ├── oms/                # Auftragsorchestrierung/Bestandszuteilung/RMA-Lebenszyklus
│   │   ├── wms/                # Wareneingangsprozess (ASN→Wareneingang→Einlagerung) / Warenausgangsprozess (Welle→Kommissionierung→Verpackung)
│   │   └── tms/                # Frachtbriefverwaltung/Frachtratenvergleich/Tracking
│   ├── model/                  # 224 Eloquent-Modelle (modulübergreifend gemeinsam genutzt)
│   ├── middleware/             # 11 Middlewares (ApiVersion entfernt, Version läuft über den Pfad)
│   ├── common/                 # Hashids/Snowflake/Encryption-Services
│   ├── queue/                  # Warteschlangenaufgaben (Redis-Treiber + Verzögerung/Dead Letter)
│   └── process/                # Dauerprozesse (Http / WebSocket / QueueConsumer / Monitor)
├── apps/
│   ├── angular/                # Angular 22 Verwaltung (config-getriebene Ressourcenseiten, ng serve :4200)
│   ├── react/                  # React 19 + Vite Verwaltung (Vite :5173)
│   ├── flutter/                # Flutter plattformübergreifend (Web-PC + iOS/Android/macOS/Windows/Linux)
│   └── harmonyos/              # Nativer HarmonyOS-Client
├── config/                     # Konfigurationsdateien (mit chinesischen Kommentaren)
│   ├── plugin/erikwang2013/apidoc/ # API-Dokumentationskonfiguration (Zugangsdaten aus APIDOC_PASSWORD / APIDOC_SECRET_KEY in .env)
├── database/
│   ├── install.sql              # vollständiges Installations-SQL (227 Tabellen + Seed-Daten)
│   ├── e2e-seed.sql             # minimaler Seed für E2E/CI
│   └── backup/                 # Backup-/Restore-Skripte
├── docs/                       # Architektur-, Design-, Sicherheits- und API-Dokumentation
├── tests/                      # PHPUnit-Tests (113<!-- stats:test_files=113 --> Testdateien , 1046<!-- stats:tests=1046 --> Testmethoden , 5029<!-- stats:assertions=5029 --> Assertions ; Zählweise siehe Anhang in FUNCTIONS.md)
├── resource/
│   └── translations/           # Backend-Nachrichtenwörterbücher in 13 Sprachen (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # chinesische Übersetzung (565 Einträge)
│       ├── en/                 # Englisch ist der Key, nur Framework-Regelnamen usw., 30 Einträge
│       └── ja|ko|de|.../       # die übrigen 11 Sprachen je 544 Einträge (Generator scripts/gen-be-locales.mjs)
├── public/                     # öffentlicher Einstieg
├── runtime/                    # Laufzeitdateien
└── vendor/                     # Composer-Abhängigkeiten
```

## Systemarchitektur

> Klicken Sie auf die Bilder, um die SVG-Originale anzuzeigen. Die Diagramme verwenden englische Bezeichnungen und zeigen die Architektur aller Systemebenen vollständig und klar.

### System-Topologie

![System Architecture](./diagrams/system-architecture-cn.svg)

**Fünf-Schichten-Architektur**: Client-Schicht → Gateway-/Edge-Schicht (Nginx-Reverse-Proxy) → Anwendungsschicht (webman v2 + Middleware-Kette + Authentifizierung/Autorisierung + Geschäftslogik + gemeinsame Dienste) → Datenspeicherschicht (MySQL + Redis + Elasticsearch) → Betriebsschicht (CI/CD + Docker + Prometheus)

### Geschäftsdatenfluss

![Business Flowchart](./diagrams/business-flowchart-cn.svg)

**Verzahnung der sieben Geschäftsbereiche**: Einkauf → Bestand → Vertrieb → Finanzen bilden den Kern der Lieferketten-Schleife; CRM treibt den Vertrieb; die Produktion (MRP) steuert Einkaufs- und Produktionsplanung auf Basis von Verkaufsaufträgen und Stücklisten; Genehmigungsworkflow, Benachrichtigungen, Projektmanagement und Personalwesen begleiten als unterstützende Module den gesamten Prozess.

### Modulübersicht

![Functional Modules](./diagrams/functional-modules-cn.svg)

**23 Geschäftsbereiche, 227 Datentabellen, 159 Controller**: Authentifizierung/Sicherheit, Dashboard, Systemverwaltung, Schutz, Betriebsmonitoring, Artikelverwaltung, Einkauf, Vertrieb, Bestand, Finanzen (14 Untermodule), CRM (10 Untermodule), Genehmigungsworkflow, Benachrichtigungen, Projektmanagement, Personalwesen, Produktion (MRP), benutzerdefinierte Berichte, Auftragsverwaltung (OMS), Lagerverwaltung (WMS), Transportverwaltung (TMS), Qualitätsmanagement (QMS), Anlagenverwaltung (EAM), Dokumentenverwaltung (DMS), BI-Dashboards.

### Anfrage-Lebenszyklus

![Request Lifecycle](./diagrams/request-lifecycle-cn.svg)

**Kompletter Anforderungspfad vom Client zur Datenbank**: Client (Angular/React/Flutter/HarmonyOS) → Nginx-SSL-Terminierung → CORS → Sicherheitsfilter → Rate-Limit → [Admin: JWT-Authentifizierung → RBAC-Berechtigungen → Betriebsprotokoll] → Controller → Service-Schicht → Model-Schicht → Cache/Datenbank/Suchmaschine → JSON-Antwort. Das Diagramm zeigt die Pfade für Cache-Treffer und Cache-Fehltreffer. (Die Schnittstellenversion ist in den URL-Pfad integriert, es gibt keinen eigenen Prüfschritt; die Sprache ermittelt `app/common/I18n.php` aus `Accept-Language`.)

### Sicherheitsarchitektur in der Tiefe

![Security Architecture](./diagrams/security-architecture-cn.svg)

**Panorama der Verteidigung in der Tiefe (L0–L12)**: L0 Physisches Netzwerk → L1 Transportsicherheit → L2 HTTP-Sicherheitsheader → L3 Anforderungsvalidierung → L4 Eingabebereinigung → L5 CSRF-Schutz → L6 Rate-Limit → L7 Authentifizierung (JWT+Captcha+Blacklist+Sitzungskontrolle) → L8 RBAC-Autorisierung → L9 Datenschutz (Transport-Verschlüsselung + Speicher-Verschlüsselung + ID-Verschleierung + Datenmaskierung) → L10 Prüfung & Monitoring → L11 Compliance-Offenlegung → L12 Observability (X-Trace-Id-Verteilte Ablaufverfolgung + Geschäftskennzahlen + Audit-Erweiterung). Die ausführbare Kette der 7 Middleware-Ebenen siehe `docs/SECURITY.md`; die 35 Angriffserkennungen siehe `config/plugin/erikwang2013/security-php/app.php`.

---

## Systemvoraussetzungen

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (nur für Frontend-Entwicklung)
- Node >= 22.22.3 (nur für die Entwicklung der Angular-/React-Administration; untere `engines`-Grenze von Angular CLI 22)
- Elasticsearch >= 7.x oder OpenSearch >= 2.x (optional, für die Index-Synchronisierung erforderlich; ohne Installation bleiben Geschäftsdaten les- und schreibbar)
- DevEco Studio (optional, nur für den Build des HarmonyOS-Clients; per Kommandozeile auch `hvigorw assembleHap`)
## Standardmäßige lokale Domain

Das Projekt verwendet standardmäßig die lokale Domain **`http://erp.test`** (Standard-API-Adresse des Flutter-Clients, Konvention für den Web-Einstieg des Backends; der HarmonyOS-Client zeigt standardmäßig auf den Emulator-Host `http://10.0.2.2:8788`).

- **Lokaler Zugriff**: In der hosts-Datei die Zeile `127.0.0.1 erp.test` ergänzen und den Webserver/Reverse-Proxy auf den Backend-Listening-Port zeigen lassen (Standard `8788`, siehe `APP_HTTP_PORT` in `.env`; im Installationsassistenten oder in `.env` änderbar; WebSocket standardmäßig `8282` entsprechend `APP_WS_PORT`).
- **Deployment-Domain ändern**:
  - Flutter-Build-Injektion: `flutter build web --dart-define=API_BASE_URL=https://deine-domain`
  - HarmonyOS: `BASE_URL` in `apps/harmonyos/entry/src/main/ets/utils/Config.ets` bearbeiten (schreibgeschützte Konstante, Standard `http://10.0.2.2:8788`)
  - Für das Emulator-Debugging kann vorübergehend auf `http://10.0.2.2:8788` zurückgestellt werden (Zugriff auf den Host)
- Alle Schnittstellenversionen liegen bereits im Pfad (`/admin/v1`, `/api/v1`, `/open/v1`), der Client muss nur die Basisadresse konfigurieren.

## Schnellstart

### 1. Abhängigkeiten installieren

```bash
composer install
```

### 2. Umgebungsvariablen konfigurieren

Umgebungsvariablen kopieren und anpassen (optional; ohne Konfiguration werden die Standardwerte aus `config/*.php` verwendet):

```bash
cp .env.example .env
```

Wichtige Konfigurationseinträge:

| Umgebungsvariable | Beschreibung | Standardwert |
|---------|------|--------|
| `JWT_SECRET_KEY` | JWT-Signaturschlüssel (`env_required`: fehlend/leer/schwacher Platzhalter → Start wird verweigert) | `.env.example` enthält einen vorbelegten 48-stelligen Zufallswert |
| `HASHIDS_SALT` | Hashids-Salt (`env_required`) | `.env.example` enthält einen vorbelegten 48-stelligen Zufallswert |
| `ENCRYPTION_KEY` | Hauptschlüssel für die Verschlüsselung in Transport- und Speicherschicht (`env_crypto_key`: AES-256 benötigt 32 Byte, abweichende Länge → Start wird verweigert) | `.env.example` enthält einen vorbelegten 32-stelligen Zufallswert |
| `APIDOC_PASSWORD` / `APIDOC_SECRET_KEY` | Zugriffspasswort und Token-Signaturschlüssel der Dokumentationsseite. Bleiben sie leer oder stehen noch die Platzhalter `CHANGE_ME_*` darin, gelten sie **in jedem Fall als nicht konfiguriert** (die Platzhalter stehen in diesem öffentlichen Repository, ein Übernehmen in die Produktion wäre also ein veröffentlichtes Passwort) → die Dokumentationsseite verweigert den Zugriff, der Start der Anwendung ist nicht betroffen | `.env.example` enthält die Platzhalter `CHANGE_ME_*` (müssen über das unten stehende Generatorskript ersetzt werden) |
| `SNOWFLAKE_DATACENTER_ID` | Rechenzentrums-ID (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | Worker-ID (0-31) | `1` |
| `SCOUT_HOSTS` | ES-Adresse | `http://localhost:9200` |
| `APP_HTTP_PORT` / `APP_WS_PORT` | HTTP-/WebSocket-Listenport des Backends (Reverse Proxy wie Nginx zeigt darauf) | `8788` / `8282` |
| `ANGULAR_DEV_PORT` / `REACT_DEV_PORT` | Ports der Frontend-Dev-Server (`npm run dev`, nur in der Entwicklung) | `4200` / `5173` |
| `NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT` | Von docker-compose auf den Host veröffentlichte Ports (Ports im Container sind fest) | `80` / `443` / `3306` / `9200` |

**In Produktionsumgebungen müssen alle Schlüssel durch Zufallszeichenfolgen ersetzt werden** (`JWT_SECRET_KEY` / `ENCRYPTION_KEY` / `HASHIDS_SALT` usw.: fehlend, leer oder noch ein schwacher Platzhalter wie `change-me`/`xxx` → der Start wird von `env_required` / `env_crypto_key` verweigert, es gibt keine stille Degradierung; für `ENCRYPTION_KEY` gilt zusätzlich eine harte Längenprüfung (AES-256 verlangt 32 Byte, jede andere Länge führt beim Start zum Fehler)):

```bash
# Zufällige Schlüssel erzeugen und in .env schreiben (idempotent, bereits gesetzte Werte werden nicht überschrieben)
bash scripts/gen-env-keys.sh .env
```

### 3. Datenbank initialisieren

**Variante 1: Web-Installationsassistent (empfohlen)**

Nach dem Start des Dienstes `http://localhost:8788/install` aufrufen und den 4-Schritte-Assistenten durchlaufen: Umgebungsprüfung → Datenbankkonfiguration → Admin-Konto → Ein-Klick-Installation. In der Datenbankkonfiguration kann **Demodaten importieren** angehakt werden (Produkte/Spezifikationen/SKUs/Kunden/Lieferanten, ID-Bereich 41…, bereichsweise löschbar); standardmäßig aus — in der Produktion nicht ankreuzen.

**Variante 2: Kommandozeilen-Import**

```bash
mysql -u root -p Datenbankname < database/install.sql
```

`install.sql` ist eine vollständige Einzeldatei-Baseline und enthält alle 227 Tabellenstrukturen sowie Seed-Daten.

**Variante 3: Docker-Umgebung**

Kein manueller Import nötig: `install.sql` ist in den MySQL-Container unter `/docker-entrypoint-initdb.d` eingebunden und wird beim ersten Start automatisch initialisiert.

### 4. Dienst starten

```bash
php start.php start
```

Standardmäßig lauscht der Dienst auf `http://0.0.0.0:8788`.

### 5. Frontend starten (optional)

**Flutter-Admin (Web):**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC-Admin-Stil)
```

**HarmonyOS-Client (Mobil):**

`apps/harmonyos/` mit DevEco Studio öffnen und auf einem echten Gerät oder Simulator ausführen.

### 6. Docker-Compose-Ein-Klick-Deployment (empfohlen für Produktion)

Das Projekt enthält eine vollständige Docker-Orchestrierung mit 5 Diensten: Nginx, PHP (webman app), MySQL, Redis, Elasticsearch.

```bash
# 1. Docker-Umgebungsvariablen konfigurieren
cp .env.docker .env
# 2. Platzhalter-Schluessel durch Zufallswerte ersetzen (idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Alle Dienste starten
docker compose up -d

# 4. Dienststatus prüfen (MySQL importiert database/install.sql beim ersten Start automatisch, keine manuelle Initialisierung nötig)
docker compose ps --format "table {{.Name}}\t{{.Status}}"

# 5. Zugriff (Nginx veröffentlicht ${NGINX_PORT:-80}; webman 8788 nur im Container-Netz, von Nginx per Reverse Proxy)
# http://localhost

# Hinweis zum Zurücksetzen: Wurde zuvor mit einer alten .env gestartet (Datenvolumes enthalten bereits alte Passwörter/Tabellenstrukturen),
# müssen die Volumes zuerst gelöscht werden:
# docker compose down -v   (⚠️ löscht MySQL-/Redis-/ES-Daten, nur zur erstmaligen Fehlerbehebung verwenden)
```

- `Dockerfile`: PHP 8.3 + OPcache + Composer, basierend auf `php:8.3-cli-alpine`
- `docker-compose.yml`: Orchestrierung von 5 Diensten, Netzwerk-Isolation, persistente Datenvolumes
- `.env.docker`: Umgebungsvariablen speziell für Docker

## Bedienung

### 1. Anmelden

Beim ersten Einsatz die Web-Installation `http://localhost:8788/install` aufrufen, um die Installation abzuschließen und ein Administratorkonto anzulegen. Nach der Installation die Konsole öffnen, Zugangsdaten eingeben und das Klick-Captcha lösen.

### 2. Navigation

Nach der Anmeldung über die Seitenleiste in die Module wechseln: Dashboard, Produkte, Einkauf, Verkauf, Lager, Finanzen, CRM, Genehmigungsworkflows, Benachrichtigungen, Projekte, Personal, Fertigung, benutzerdefinierte Berichte, OMS/WMS/TMS, BI-Dashboards und Systemverwaltung (Benutzer/Rollen/Konfiguration/Protokolle). Die Seitenleiste ist am Desktop fixiert und klappt am Handy als Drawer ein.

### 3. Berechtigungen und Sicherheit

- Funktionen und APIs sind über RBAC gesteuert; Menüs und Schnittstellen ohne Berechtigung sind nicht zugänglich (403)
- Sensible Aktionen wie das Löschen von Benutzern/Rollen erfordern die erneute Eingabe des aktuellen Passworts im Request-Body
- Nach dem Abmelden wird das Token sofort auf die Blacklist gesetzt

### 4. Volltextsuchmaschine (optional)

Die Index-Synchronisierung erfolgt über `erikwang2013/webman-scout` (sobald ein Modell das `Searchable`-Trait erhält, wird der Index beim Speichern automatisch synchronisiert). Unterstützt werden **Elasticsearch** und **OpenSearch**, eines von beiden:

**① Passenden Client installieren (Composer-Paket und Treiber müssen zusammenpassen, sonst meldet der Start „Please install the ... client")**

| Engine | Composer-Client |
|---|---|
| Elasticsearch | `composer require elasticsearch/elasticsearch:^9.5` |
| OpenSearch | `composer require opensearch-project/opensearch-php:^2.0` |

**② Treiber in `.env` auswählen**

```ini
# elasticsearch | opensearch (identisch mit dem oben installierten Client)
SCOUT_DRIVER=opensearch
# Indexnamens-Präfix / Shards / Replicas / Chunk-Größe / Soft Delete (für beide Engines gleich)
SCOUT_PREFIX=erp_
SCOUT_SHARDS=1
SCOUT_REPLICAS=0
SCOUT_CHUNK_SIZE=500
SCOUT_SOFT_DELETE=true
```

**③ Verbindungskonfiguration (beide Engines lesen an unterschiedlichen Stellen)**

- **Elasticsearch**: `SCOUT_HOSTS` aus der `.env` (mehrere Knoten kommagetrennt, z. B. `http://localhost:9200`), direkte Verbindung ohne Authentifizierung;
- **OpenSearch**: Das offizielle Image aktiviert standardmäßig das Security-Plugin (selbstsigniertes TLS + Kontozugang), daher läuft die Verbindung über den Abschnitt `opensearch` in `config/scout.php` und liest `SCOUT_HOSTS` nicht:

  ```ini
  # .env
  SCOUT_OPENSEARCH_HOST=https://localhost:9200
  SCOUT_OPENSEARCH_USERNAME=admin
  SCOUT_OPENSEARCH_PASSWORD=dein_passwort
  ```

  Der Abschnitt `opensearch` in `config/scout.php` hat standardmäßig `ssl_verification=false` (lokales selbstsigniertes Zertifikat); in der Produktion sollte `true` gesetzt und ein Zertifikat konfiguriert werden, schwache Passwörter sind zu vermeiden.

> Das Docker Compose dieses Projekts enthält Elasticsearch (Dienst `open-admin-es`): Bei einem Docker-Deployment wählt man **elasticsearch-Treiber + ES-Client**; bei einem externen/separaten OpenSearch-Container **opensearch-Treiber + opensearch-php**.
>
> **Indexumfang**: Alle 224 Modelle unter `app/model/` tragen `Searchable`; beim Schreiben und bei Soft Delete synchronisiert der `ModelObserver` den Index. Bei AdminUser, Customer, Product und Supplier nimmt ein eigenes `toSearchableArray()` nur die Felder der Whitelist in den Index auf, alle übrigen Modelle gehen vollständig (ganze Zeile) hinein.
>
> **Ein nicht erreichbarer Suchdienst beeinträchtigt das Schreiben von Geschäftsdaten nicht** (getestet: zeigt der Treiber auf einen unerreichbaren Port, ist `save()` weiterhin erfolgreich — lediglich ein zusätzlicher Verbindungs-Timeout) — die Suchmaschine ist ein optionales Modul, ohne sie läuft das gesamte Geschäft.
>
> **Hinweis zum Umfang**: Dieses Projekt bindet derzeit nur die **Index-Synchronisierung** ein (Schreiben/Soft Delete synchronisiert); eine Suchschnittstelle oder Suchoberfläche gibt es nicht. Die Filter der Listenansichten laufen über `where`-Abfragen des Backends und nicht über die Suchmaschine.

### 5. Mehrsprachigkeit

Automatische Umschaltung über den Request-Header `Accept-Language`, 13 Sprachen (`zh` als Standard, daneben `en`/`ja`/`ko`/`de`/`fr`/`es`/`pt`/`ru`/`ar`/`hi`/`bn`/`id`); die Angular-/React-Administration hat zusätzlich das Globus-Symbol in der oberen Leiste und ein Dropdown im persönlichen Bereich. Details siehe [Internationalisierung](#internationalisierung).

## Datenbank-Konventionen

- **Tabellenpräfix**: `erp_`
- **Primärschlüssel**: Alle Tabellen verwenden `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT ist verboten**
- **ID-Erzeugung**: Primärschlüssel werden in der Anwendungsschicht über `SnowflakeService::generate()` erzeugt, verteilt eindeutig
- **Pflichtfelder**: Jede Tabelle muss `id`, `created_at`, `updated_at` enthalten
- **Soft Delete**: Tabellen mit Soft Delete fügen `deleted_at DATETIME DEFAULT NULL` hinzu
- **Sensible Felder**: Mobilnummern, E-Mails, Ausweisnummern usw. werden über das Plugin `encryptable` automatisch ver-/entschlüsselt; die Datenbankfelder speichern den Chiffretext in `VARCHAR(500)`

## API-Konventionen

### API-Dokumentation

Das Projekt nutzt `erikwang2013/apidoc-php`, **die Dokumentation wird automatisch aus den Controller-Annotationen erzeugt** und muss nicht separat gepflegt werden:

```bash
php start.php start          # Backend starten
# danach im Browser aufrufen
http://localhost:8788/apidoc
```

- **Zugriffspfad**: `/apidoc` (Routenpräfix des Plugins, siehe `config/plugin/erikwang2013/apidoc/route.php`); dieser Pfad ist in der Rate-Limit-Middleware freigegeben, das Durchblättern der Annotationen wird nicht ausgebremst
- **Abdeckung**: Admin-Schnittstellen (Admin) nach Modulen gruppiert, mit vollständigen Anfrageparametern und Antwortstrukturen; Client-Schnittstellen (Service API) mit Authentifizierung/Captcha/Artikel
- **Wie die Dokumentation neuer Schnittstellen ergänzt wird**: einfach die Annotation am Controller-Verfahren anbringen — nach dem Speichern ist `/apidoc` beim Neuladen sofort aktuell

  ```php
  #[\erikwang2013\apidoc\annotation\Title("Produktliste")]
  #[\erikwang2013\apidoc\annotation\Desc("Produkte seitenweise abfragen")]
  #[\erikwang2013\apidoc\annotation\Url("/admin/v1/product")]
  #[\erikwang2013\apidoc\annotation\Method("GET")]
  #[\erikwang2013\apidoc\annotation\Param(name:"page", type:"int", desc:"Seitennummer")]
  #[\erikwang2013\apidoc\annotation\Returned("code", type:"int", desc:"Geschäftscode, 0=Erfolg")]
  public function index(Request $request): Response { /* ... */ }
  ```

- Soll der Zugriff in der Produktion eingeschränkt werden, siehe `docs/nginx-security.conf`

### Einheitliches Antwortformat

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Geschäftsfehlercodes

| Fehlercode | Bedeutung | Beschreibung |
|-------|------|------|
| `0` | Erfolg | |
| `400` | Ungültige Anfrageparameter | |
| `401` | Nicht angemeldet (Token ungültig oder abgelaufen) | |
| `403` | Keine Berechtigung / Sicherheitsintervention | RBAC-Fehler / SecurityFilter-Angriffserkennung |
| `404` | Ressource nicht gefunden | |
| `422` | Parameter-Validierung fehlgeschlagen | |
| `413` | Anforderungsbody zu groß | SecurityFilter greift, über 10MB |
| `405` | HTTP-Methode nicht erlaubt | SecurityFilter greift, nur GET/POST/PUT/DELETE/OPTIONS/HEAD |
| `415` | Nicht unterstützter Medientyp | SecurityFilter greift, Content-Type ist kein JSON |
| `429` | Zu viele Anfragen | RateLimit ausgelöst / Kontosperrung (5 Login-Fehler sperren 15 Minuten) |
| `500` | Interner Serverfehler | |

### Internationalisierung

Der Request-Header `Accept-Language` schaltet die Sprache automatisch um (zh-CN → Chinesisch, en → English), Standard ist Chinesisch.

### ID-Behandlung

- **IDs in Anfragen/Antworten**: werden mit hashids als Zeichenfolgen verschlüsselt; echte Datenbank-IDs werden nicht offengelegt
- **Schnittstellenpfad**: `GET /admin/v1/user/{hashid}` — `{id}` im Pfad ist eine hashid-Zeichenfolge
- **Datenbankspeicher**: BIGINT-Originalwert, von snowflake erzeugt
- **Frontend-Konvention**: Alle `*_id` (einschließlich der Detailzeilen `items[].*_id`) werden ausnahmslos als **undurchsichtige Zeichenfolge** unverändert zurückgegeben; Umwandlungen mit `Number()`/`parseInt()` oder eine Wahrheitswertprüfung sind verboten — eine hashid kann eine reine Ziffernfolge sein und ist dann wertmäßig nicht von einer nackten ID oder dem Sentinel `0` zu unterscheiden (Vertrag siehe `tests/FieldContractRegressionTest.php`)

### Versionierung der Schnittstellen

Die Schnittstellenversion liegt im URL-Pfad (z. B. `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`), **der Client benötigt keinerlei Versions-Request-Header**:

- Versionierte öffentliche Schnittstellen sind direkt an die jeweilige Controller-Klasse gebunden (`app/api/v1/controller/`)
- Für eine neue Version wird eine neue `/api/vN`-Routengruppe registriert; die Controller liegen versionsweise unter `app/api/vN/`
- Die frühere dynamische `v()`-Auflösung und die `ApiVersion`-Middleware für Request-Header wurden entfernt

### Rate-Limit

Redis-Sliding-Window-Algorithmus, standardmäßig 60 Anfragen/Minute/IP/Route. Sensible Schnittstellen sind strenger:
- Login: 10/Minute
- Registrierung: 5/Minute (standardmäßig deaktiviert, erfordert `REGISTRATION_ENABLED=1`)

Die Antwort-Header enthalten `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. Bei Überschreitung wird 429 mit `Retry-After` zurückgegeben.

### Middleware-Architektur

Globale Middleware gilt für alle Anfragen und wird in dieser Reihenfolge ausgeführt:

```
Cors (CORS-Vorverarbeitung + Antwort-Header)
  → SecurityFilter (HTTP-Methodeneinschränkung/Größe des Anfragekörpers/Content-Type-Prüfung/XSS/SQL-Injection/Pfad-Traversal/Befehlsinjektion/CSRF-Abwehr)
  → RateLimit (Redis-Sliding-Window-Rate-Limit + Kontosperrung: 5 fehlgeschlagene Logins sperren 15 Minuten)
  → TracingId (ID der Kettenverfolgung)
```

Middleware auf Routengruppen: `/admin/v1` trägt `AdminAuth (JWT-Authentifizierung + Blacklist) → AdminPermission (RBAC-Berechtigung) → OperationLog (automatische Protokollierung für POST/PUT/DELETE, inkl. Erkennung des Aufrufer-Endgeräts)`; `/open/v1` trägt `OpenApiAuth`; TMS-Tracking-Callbacks tragen `TrackingSignature`. Die Sprache ermittelt `app/common/I18n.php` aus `Accept-Language` — sie ist keine Middleware.

`/health`, `/api/docs` und `/install` sind öffentliche Endpunkte und durchlaufen nur `Cors → SecurityFilter → RateLimit → TracingId`.

Sicherheitsverstärkungen:
- **Kontosperrung**: Nach 5 aufeinanderfolgenden fehlgeschlagenen Logins wird das Konto 15 Minuten gesperrt; während der Sperrung liefert der Login 429
- **Begrenzung paralleler Sitzungen**: Maximal 3 gültige Token pro Benutzer; bei Überschreitung wird das älteste Token automatisch auf die Blacklist gesetzt
- **security.txt**: `GET /.well-known/security.txt` liefert Sicherheitskontaktinformationen nach RFC 9116
- **Nginx-Sicherheitskonfiguration**: `docs/nginx-security.conf` dient als vollständige Referenz zur Härtung des Reverse-Proxys

### Authentifizierung

Login und Registrierung erfordern zuerst die Prüfung über ein **Klick-Captcha**:

1. Der Client ruft `POST /api/v1/captcha/generate` auf und erhält das Captcha-Bild (base64 PNG) sowie die Liste der anzuklickenden Textziele
2. Der Benutzer klickt die Textpositionen im Bild in der richtigen Reihenfolge an; die Klickkoordinaten `[{x, y}, ...]` werden gesammelt
3. Beim Login werden `captcha_key` und `clicks` mitgesendet; der Server prüft zuerst das Captcha und dann die Anmeldedaten

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Nachfolgende Admin-Schnittstellen benötigen die JWT-Authentifizierung:

```http
Authorization: Bearer <token>
```

Nach erfolgreichem Login wird ein access_token (Gültigkeit 2 Stunden) sowie ein refresh_token (Gültigkeit 14 Tage) zurückgegeben.

Beim Logout wird das Token in der Redis-Blacklist gespeichert und kann bis zum Ablauf nicht wiederverwendet werden. POST /admin/v1/profile/logout

### Zweifache Bestätigung bei sensiblen Operationen

Für sensible Operationen wie das Löschen von Benutzern, Rollen oder Berechtigungen muss im Request-Body das `password` des aktuell angemeldeten Benutzers zur Identitätsbestätigung mitgesendet werden:

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## API-Liste

Die vollständige Schnittstellenliste (öffentliche Schnittstellen / Admin-Schnittstellen / Geschäftsschnittstellen / Client-Schnittstellen) wurde in ein separates Dokument verschoben:

→ [API-Referenz](API.md)

## Frontend-Hinweise

### Angular-Verwaltung (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (Port siehe ANGULAR_DEV_PORT in .env)
npm run build      # tsc --noEmit + ng build, Ausgabe dist/angular
npm run typecheck  # nur Typprüfung
```

- **Node-Versionsanforderung**: Die `engines` von Angular CLI 22 verlangen **Node ≥ 22.22.3** (bei niedrigeren Versionen verweigert `ng build` direkt den Start).
  Ist das lokale Node zu niedrig, per npx temporär festlegen (die gebräuchlichste Build-Variante dieses Repositories, außerhalb der CI stets so):

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  In Umgebungen ohne `npx` (z. B. die Offline-Validierungsmaschine dieses Repositories) stattdessen die mitgelieferte tsc-CLI zur Typprüfung verwenden:
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Entwicklungs-Proxy**: `proxy.conf.js` leitet `/admin` `/api` `/open` `/health` `/metrics` `/install`
  auf `APP_HTTP_PORT` aus `.env` weiter (Standard 8788); bei `ng serve` ist daher **keine** zusätzliche Backend-Adresse nötig
- **Architektur**: config-getrieben — `src/app/config/domains/*.ts` deklariert Menüs und Ressourcenseiten, **eine einzige `ResourcePage`
  rendert alle Geschäftsseiten** (eine neue Ressourcenseite ≈ ein zusätzliches Konfigurationsobjekt, kein Komponentencode nötig)
- **Mehrsprachigkeit**: 13 Sprachen, Wörterbücher je Sprache per Lazy-Loading (jeweils ein eigener Chunk); Umschalten über das Globe-Symbol in der oberen Leiste
- **Selbstprüfung** (alle ohne Browser direkt mit `node` ausführbar): `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### React-Verwaltung (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (Port siehe REACT_DEV_PORT in .env)
npm run build      # tsc --noEmit + vite build, Ausgabe dist/
```

- Wie Angular **config-getrieben**: `src/config/domains/*.ts` deklariert Menüs und Ressourcenseiten,
  die Rendering-Engine liegt in `src/components/ResourcePage.tsx`; die Stil-Tokens in `src/styles/tokens.css`
  (identisch mit `styles/theme.less` auf der Angular-Seite)
- Der Spracheingang liegt auf der Seite **persönlicher Bereich** (auf der Angular-Seite zusätzlich das Globe-Symbol in der oberen Leiste)

### Flutter-Admin (PC-Stil, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (PC-Admin-Stil), unterstützt auch iOS/Android/macOS/Windows/Linux
flutter analyze          # statische Prüfung (wie in der CI)
```

- **Layout**: Seitenleiste (einklappbar, 64px/240px) + obere Leiste + Inhaltsbereich, responsive mit drei Breakpoints (Mobil/Tablet/Desktop)
- **Abdeckung**: 22 Menügruppen, 102 Menü-Routen, 119 Seitendateien (Menü in `lib/app/config/menu_config.dart`, Seiten unter `lib/app/pages/`) — Dashboard, Systemverwaltung, Artikelverwaltung, Geschäftspartner, Einkaufsverwaltung, Vertriebsverwaltung, Bestandsverwaltung, Finanzverwaltung, CRM, Auftragsverwaltung, Lagerverwaltung, Transportverwaltung, Produktion, Qualitätsmanagement, Personalwesen, Projektmanagement, Genehmigungsworkflow, Benachrichtigungszentrum, benutzerdefinierte Berichte, BI-Dashboards, Anlagenverwaltung, Dokumentenverwaltung
- **State-Management**: GetX (`ApiService`-Singleton + `AuthService` Token-Persistenz)
- **Dashboard**: Statistik-Karten, Vertriebstrend-Linie, Top-Artikel, Auftragsstatus-Verteilung, Fälligkeiten der Forderungen/Verbindlichkeiten, Bestandsübersicht (fl_chart)
- **Export**: Excel/PDF-Export (`ExportService`), PDF mit nicht entfernbarer Copyright-Information
- **Batch-Operationen**: Mehrfachauswahl-Löschung, Batch-Aktivieren/Deaktivieren
- **Theme**: Material 3 mit Hell-/Dunkelmodus
- **Internationalisierung**: Chinesisch/Englisch (`lib/l10n/app_zh.arb` als Vorlage, Generierung mit `flutter gen-l10n`)

### HarmonyOS-Mobile-Client (`apps/harmonyos/`)

- **Build**: `apps/harmonyos/` in DevEco Studio öffnen; das Kommandozeilen-Äquivalent lautet
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (erfordert HarmonyOS SDK + command-line-tools, Artefakt `entry/build/default/outputs/default/*.hap`)
- **Seiten**: `entry/src/main/resources/base/profile/main_pages.json` registriert **41 Seiten, alle von der Oberfläche aus erreichbar** (Login, Dashboard, Benutzerliste/-details, Rollen & Berechtigungen, persönlicher Bereich sowie die Untersystemseiten für Artikel/Bestand/Einkauf/Vertrieb/OMS/WMS/TMS/Produktion/Personal/Genehmigung); das Business-Kachelraster des Dashboards bietet **32 Direkteinstiege**, Detailseiten der Untersysteme werden über die Zeilenaktionen der Listen geöffnet
- **Authentifizierung**: JWT Bearer + automatisches unsichtbares Token-Refresh bei 401; bei Fehlschlag automatische Weiterleitung zur Login-Seite
- **Speicher**: Token über AppStorage verwaltet
- **Internationalisierung**: Chinesisch/Englisch (`resources/base/element/string.json` und `resources/en_US/element/string.json`)
- **Netzwerk**: `BASE_URL` in `apps/harmonyos/entry/src/main/ets/utils/Config.ets` ist eine schreibgeschützte Konstante, standardmäßig `http://10.0.2.2:8788` (Emulator-Host); die Projektvorgabe `http://erp.test` gilt für den Flutter-Client und den Backend-Web-Eingang

## Entwicklungsrichtlinien

- Globale Funktionen/Klassen ohne führendes `\`, einheitlich per `use` importieren
- Alle PHP-Dateien müssen den Copyright-Hinweis am Dateianfang enthalten
- Alle Konfigurationsdateien müssen chinesische Kommentare enthalten
- Datenbank-Primärschlüssel müssen von snowflake in der Anwendungsschicht erzeugt werden; AUTO_INCREMENT ist verboten
- Alle IDs in API-Parametern und -Antworten müssen per hashids ver-/entschlüsselt werden
- Die AdminPermission-Middleware cached Benutzerberechtigungen in Redis (TTL=60s), um den N+1-Abfrageflaschenhals zu beseitigen

## Deployment

### Docker Compose (empfohlen)

Im Projektstamm liegt `docker-compose.yml` mit 5 Diensten:

| Dienst | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | Lokal über `Dockerfile` gebaut | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

Das PHP-Image wird über `Dockerfile` auf Basis von `php:8.3-cli` gebaut, mit aktiviertem OPcache.

```bash
cp .env.docker .env
# Platzhalter-Schluessel durch Zufallswerte ersetzen (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

GitHub-Actions-Pipeline für kontinuierliche Integration: `.github/workflows/ci.yml`, mit fünf Jobs:

| Job | Inhalt |
|------|------|
| `php` (Matrix PHP 8.3 / 8.4, mit MySQL 8 + Redis 7 als Services) | composer-Validierung und Sicherheitsaudit → `php -l` → **PHPStan** (Level 5 + Baseline) → **PHP CS Fixer** (dry-run) → Import der vollständigen `install.sql` → **PHPUnit** (inkl. Integrationstests) → pcov-Coverage → Coverage-Schwellen (gesamt ≥ 4 %, `app/service` ≥ 10 %, schrittweise verschärft) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, wird nach Stabilisierung der Umgebung verschärft) |
| `docs` | `bash scripts/doc-stats.sh --check`: prüft die `<!-- stats:key=value -->`-Annotationen in README und docs gegen die tatsächlich im Quellcode gezählten Werte (Controller/Services/Modelle/Tabellen/Testzahlen usw.), Abweichung = rot |
| `e2e` | startet einen echten webman-Dienst → Health-Check → Smoke-Test der HTTP-Kernpfade + Abdeckung der Admin-APIs |
| `release` | nach Push auf `main` und erfolgreichen obigen Jobs: Tag als patch+1 und Release (siehe unten) |

> Umfang der statischen Frontend-Prüfung: Die CI führt derzeit nur Flutter aus; Angular/React (`tsc --noEmit`) und HarmonyOS (`hvigorw assembleHap`) müssen lokal oder in später ergänzten Jobs laufen.

### Release-Prozess (Versionsinkrement)

Nach einem Push auf `main` und erfolgreichem Durchlauf von php / docs / e2e erstellt der `release`-Job in `ci.yml` automatisch ein neues Versions-Tag nach dem Schema **patch+1** des neuesten Tags und pusht es (`v1.1.4` → `v1.1.5`), anschließend wird ein gleichnamiges GitHub-Release angelegt (`--generate-notes` erzeugt die Änderungsbeschreibung automatisch).

- **Auslöser**: nur Push auf `main` (PRs lösen nicht aus; Tag-Pushes passen nicht auf den Branch-Filter und lösen diesen Workflow nicht rekursiv aus)
- **Idempotenz**: Existiert das Tag oder Release bereits im Remote (parallele CI / manuell gesetzt), wird automatisch übersprungen, ohne Fehler
- **Lokale Probe**: `bash scripts/bump-version.sh --check` gibt die nächste Versionsnummer aus (lesend, kein Schreiben ins Remote)

### Datenbank-Backup

Verzeichnis `database/backup/`:

- `backup.sh` — mysqldump + gzip, löscht automatisch Backups, die älter als 30 Tage sind
- `restore.sh` — interaktive Wiederherstellung, listet verfügbare Backups zur Auswahl auf

### Nginx-Sicherheitskonfiguration

Für Produktions-Deployments siehe `docs/nginx-security.conf` zur Härtung des Reverse-Proxys.

## Open Source ist kein Selbstläufer — Unterstützung willkommen

| WeChat | Alipay |
|:---:|:---:|
| ![WeChat](./images/weixinpay.png "WeChat") | ![Alipay](./images/alipay.png "Alipay") |

### Überweisung (Banküberweisung / Global Bank Transfer)

**Begünstigter**

- Name des Begünstigten: WANG KEXUN
- Kontonummer: 881015918251

**Begünstigtenbank**

- ZA Bank SWIFT-Code: AABLHKHHXXX
- Bankname: ZA Bank Limited
- Bankleitzahl: 387
- Bankadresse: Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Korrespondenzbank für grenzüberschreitende Überweisungen (falls erforderlich)**

> Dies sind Informationen zur Korrespondenzbank (Zwischenbank), nicht zur Begünstigtenbank. Bitte fragen Sie Ihre überweisende Bank, ob diese Angaben benötigt werden.

- Überweisung in Hongkong-Dollar, Renminbi und US-Dollar: Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, Bankleitzahl 006, Filiale Hong Kong Branch, Filialnummer 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Überweisung in anderen Währungen: THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Krypto-Spenden (Crypto Donation)

Wenn dieses Projekt Ihnen hilft, scannen Sie gerne den QR-Code, um zu spenden. Vielen Dank!

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
