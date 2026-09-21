# Versionsvergleich

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Die Statistiken werden live von `bash scripts/doc-stats.sh` erfasst und in der Dokumentation als `<!-- stats:key=value -->` markiert;
> CI (docs-Job in `.github/workflows/ci.yml`) prüft automatisch die Übereinstimmung der Dokumentation mit den Code-Fakten — Abweichungen werden rot.

Das Open-ERP-System wird in drei Versionen angeboten, um den Anforderungen unterschiedlich großer Unternehmen gerecht zu werden.

---

## Versionsübersicht

| Dimension | Lite (vereinfacht) | Standard | Full (vollständig) |
|------|:---:|:---:|:---:|
| Branch | `lite` | `standard` | `full` |
| Datentabellen | 62 (Planwert) | 72 (Planwert) | 227 <!-- stats:tables=227 --> |
| Controller | 48 (Planwert) | 42 (Planwert) | 159 <!-- stats:controllers=159 --> |
| Geschäftsmodule | 6 (Planwert) | 6 (Planwert) | 23 <!-- stats:modules=23 --> |

> **Statistik-Basis**: Das Repository implementiert derzeit nur die vollständige Version (Full) als einzigen Codebestand; die Spalten Lite/Standard sind Produktplanwerte (es gibt keine entsprechenden Branches, siehe unten „Branch-Strategie") und
> nehmen nicht an der doc-stats-Prüfung teil. Die Zahlen der Full-Spalte werden von `scripts/doc-stats.sh` real gemessen (227 Tabellen / 159 Controller / 23 Geschäftsmodule)
> und stimmen mit der Basis des Anhangs in `docs/FUNCTIONS.md` überein.
> **Branch-Fakten** (real gemessen am 2026-09-22 mit `git branch -a` + `git ls-remote --heads origin`):
> im lokalen Repository und im Remote existiert nur noch der Branch `main`; die drei Branches `lite` / `standard` / `full` wurden **gelöscht**
> (am 2026-08-31 waren noch alle drei vorhanden, sie standen gemeinsam auf dem Commit `eea90c0` vom 2026-08-17, unterschieden sich nicht voneinander und lagen 38 Commits hinter `main`).
> Dieser Archiv-Commit ist weiterhin in der `main`-Historie enthalten (`git merge-base --is-ancestor eea90c0 main` gilt),
> das heißt Versionsunterschiede lassen sich heute nur noch über Commits und Tags nachvollziehen; im Repository gibt es keinen Versionsbranch mehr zum Auschecken.

---

## v1.17.0-Änderungen (2026-09-15)

> Die Versionspositionierung bleibt unverändert: Das Repository implementiert weiterhin nur die vollständige Version (Full) als einzigen Codebestand; die Spalten Lite/Standard sind Produktplanwerte, die zugehörigen Branches sind archiviert und eingefroren.

- **Aus zwei Verwaltungs-Frontends werden drei**: Angular 22 (`apps/angular/`) und React 19 + Vite (`apps/react/`) kommen hinzu
  und stehen gleichberechtigt neben dem bestehenden Flutter 3.x Web (`apps/flutter/`); alle drei Frontends nutzen dieselben Schnittstellen `/admin/v1`, `/api/v1`, `/open/v1`.
- **13 Sprachen auf der gesamten Plattform** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id):
  - Backend-Antwortmeldungen in `resource/translations/<locale>/` — `zh_CN` 565 Einträge, die übrigen 11 Sprachen je 544 Einträge, `en` 30 Einträge
    (Zählweise: Blatteinträge der drei Dateien, die Feldlabels in `attributes` von `validation.php` zählen mit, deren Gruppenschlüssel nicht — `zh_CN` hat 21 Einträge mehr, weil 21 Feldlabels übersetzt sind. Bei `en` gilt „Englisch ist der Key", das Wörterbuch ist nahezu leer)
  - Verwaltungsoberfläche: Angular-Quellwörterbuch 1456 Schlüssel, React 1451 Schlüssel × 11 neue Sprachen; **Laden je Sprache per Lazy-Loading**, jede Sprache wird ein eigener Chunk
  - Generatoren: `scripts/gen-be-locales.mjs` (Backend), `scripts/gen-fe-locales.mjs` (Frontend, `--app angular|react`)
  - Umschaltpunkte: **eigenes Globe-Symbol** in der oberen Leiste + Dropdown im persönlichen Bereich (in beiden Frontends identisch)
- **Flutter und HarmonyOS bleiben bei zwei Sprachen (Chinesisch/Englisch)** und waren nicht Teil dieser Runde.
- **Auswirkung auf die folgende Tabelle**: Spalte Full 163 Tabellen / 122 Controller / 19 Geschäftsmodule → **227 / 159 / 23**;
  die Vollständigkeitsmatrix erhielt eine neue Zeile „Mehrsprachigkeit (i18n)" (Modulzeilen 44 → 45, Backend-API 39 → 40, Geschäftslogik 33 → 34);
  Hinweis: Nach dem Zusammenführen der doppelten Zeile „Multi-Tenant" in der Matrix vom 2026-09-15 fiel die Zahl der Modulzeilen wieder auf 44 (Backend-API 39, Geschäftslogik 33);
  der vorstehende Satz gibt die damalige Inkrement-Zählweise von v1.17.0 wieder und bleibt unverändert stehen.

## v1.4.0-Änderungen (2026-09-05)

> Die Versionspositionierung bleibt unverändert: Das Repository implementiert weiterhin nur die vollständige Version (Full) als einzigen Codebestand; die Spalten Lite/Standard sind Produktplanwerte, die zugehörigen Branches sind archiviert und eingefroren.

- **Versionspfade auf der gesamten Seite**: `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*`;
  Ausnahmen sind nur `GET /api/docs` (OpenAPI-Dokumentation) und der TMS-Webhook; RBAC-Berechtigungspunkte autorisieren über den `method.path` ohne Versionssegment,
  bestehende Rollendaten müssen nicht migriert werden (Commit `3ee1430`; die Steuerung über den `API-Version`-Request-Header wurde bereits vorher entfernt, Commit `8276a1b`).
- **P0 Multi-Organisation und Kostenrechnung**: getrennte Rechnungslegung mehrerer Organisationen (Company/LedgerPeriod), Konsolidierungs-Engine (Umrechnung zum Stichtagskurs + konzerninterne Eliminierung,
  Snapshot bevorzugt in FinanceConsolidationReport abgelegt), Bestands-/Produktionskostenrechnung (Materialentnahme + Kostenverrechnung).
- **P1 Fertigungsausführung und Zusammenarbeit**: Arbeitsrückmeldung/Akkordlohn/Subunternehmer-Verrechnung/Kapazitätslast/Chargen- und Seriennummern-Rückverfolgung (M1/M2/M6/M3), Kreditkontrolle (F7),
  Genehmigungsprozess-Leinwand (B3), Druckvorlagen (B1), HR-Gehälter (H1/H2), QR-Prüfung für Anlagen (E1), Projektkosten (P1).
- **P2 Differenzierung und Ökosystem**: Mitgliedersystem (C1), Wechselregister und Bankabstimmung (F6), Eingangsrechnungspool und E-Rechnung (F5, echte Steuerbehörde ist der Anpassungspunkt),
  Multi-Kanal-Treiber mit Wiederholungsversuchen (B4), benutzerdefinierte Felder (B7), Multi-Tenant-Ablaufabrechnung (B5 — die Tenant-Isolations-Middleware ist weiterhin nicht registriert, teilweise aktiviert),
  Schulung und Sozialversicherung (H3/H4).
- **Funktionsmatrix**: Von 44 Modulzeilen sind 33 doppelt ✅; 21 Zeilen sind mit v1.4.0 gekennzeichnet (davon 1 Zeile teilweise aktiviert), Details siehe §19 in `docs/FUNCTIONS.md`.

> Änderungsdetails siehe `CHANGELOG.md` im Projektstamm.

---

## Funktionsvergleich

### Systemverwaltung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Benutzerverwaltung (CRUD + Batch + Import) | ✔ | ✔ | ✔ |
| Rollen & Berechtigungen (RBAC-Dreistufen-Berechtigungsbaum) | ✔ | ✔ | ✔ |
| Systemkonfiguration (Schlüssel-Wert) | ✔ | ✔ | ✔ |
| Prüfprotokoll (Quellgerät-Erkennung für 8 Plattformen) | ✔ | ✔ | ✔ |
| Datei-Upload / Excel-Export / PDF-Export | ✔ | ✔ | ✔ |
| Health-Check / Prometheus-Metriken | ✔ | ✔ | ✔ |
| JWT-Authentifizierung + Klick-Captcha | ✔ | ✔ | ✔ |
| 7 Ebenen Sicherheitsschutz | ✔ | ✔ | ✔ |
| Internationalisierung (i18n), 13 Sprachen (Angular/React; Flutter/HarmonyOS weiterhin Chinesisch/Englisch) | — | — | ✔ |

### Artikel und Stammdaten

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Artikelstamm + Multi-Spezifikations-SKU | ✔ | ✔ | ✔ |
| Multi-Einheiten-Umrechnung + Preisstrategien | ✔ | ✔ | ✔ |
| Artikelkategorien (Baumstruktur) + Marken | ✔ | ✔ | ✔ |
| Mehrere Lager + mehrere Lagerplätze | ✔ | ✔ | ✔ |
| Lieferanten-/Kundenstamm | ✔ | ✔ | ✔ |

### Einkaufsverwaltung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Einkaufsanfrage + Genehmigung | ✔ | ✔ | ✔ |
| Einkaufsbestellung | ✔ | ✔ | ✔ |
| Wareneingang (automatische Einlagerung + Erzeugung von Verbindlichkeiten) | ✔ | ✔ | ✔ |
| Einkaufsretoure | ✔ | ✔ | ✔ |
| Lieferantenabrechnung | ✔ | ✔ | ✔ |

### Vertriebsverwaltung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Angebot (unterstützt Umwandlung in Auftrag) | ✔ | ✔ | ✔ |
| Verkaufsauftrag | ✔ | ✔ | ✔ |
| Versand (automatische Auslagerung + Erzeugung von Forderungen) | ✔ | ✔ | ✔ |
| Verkaufsretoure | ✔ | ✔ | ✔ |
| Kundenabrechnung + Rohertragsanalyse | ✔ | ✔ | ✔ |

### Bestandsverwaltung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Echtzeitbestand (vierdimensionale Genauigkeit) | ✔ | ✔ | ✔ |
| Ein-/Auslagerungs-Buchungen | ✔ | ✔ | ✔ |
| Chargen-Tracking + Seriennummern-Tracking | ✔ | ✔ | ✔ |
| Bestandsumlagerung | ✔ | ✔ | ✔ |
| Inventurverwaltung (geplant + dynamisch) | ✔ | ✔ | ✔ |
| Bestandswarnungen (Unter-/Obergrenzen) | ✔ | ✔ | ✔ |
| Gleitende Durchschnittskostenrechnung | ✔ | ✔ | ✔ |

### Finanzverwaltung

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Forderungen/Verbindlichkeiten (automatisch erzeugt + verrechnet) | ✔ | ✔ | ✔ |
| Zahlungseingänge / Zahlungsausgänge | ✔ | ✔ | ✔ |
| Kassen- und Banktagebuch | ✔ | ✔ | ✔ |
| Spesenabrechnung (Einreichung → Genehmigung → Auszahlung) | ✔ | ✔ | ✔ |
| Gewinn- und Verlustrechnung | ✔ | ✔ | ✔ |
| Anlagevermögen-Abschreibung | — | — | ✔ |
| Steuerverwaltung (Konfiguration mehrerer Steuerarten) | — | — | ✔ |
| Multi-Währung + Wechselkursverwaltung | — | — | ✔ |
| Budgetverwaltung (Budget vs. Ist-Vergleich) | — | — | ✔ |
| Kosten-/Profit-Center (baumförmige Verrechnung) | — | — | ✔ |

### CRM

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Kundenkontakt-Verwaltung | ✔ | ✔ | ✔ |
| Follow-up-Records | ✔ | ✔ | ✔ |
| Marketingkampagnen | — | — | ✔ |
| Service-Tickets (Priorität + Zuweisung + Lösungsablauf) | — | — | ✔ |
| Kundenanalyse-Berichte | — | — | ✔ |

### Plattformfunktionen

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Genehmigungsworkflow-Engine | — | — | ✔ |
| Benachrichtigungssystem | — | — | ✔ |
| API-Dokumentation (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Erweiterungsmodule

| Funktion | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Projektmanagement (WBS/Gantt/Zeiterfassung) | — | — | ✔ |
| Personalwesen (Organisation/Anwesenheit/Gehälter) | — | — | ✔ |
| Produktion (BOM/MRP/Produktionsaufträge/Arbeitspläne) | — | — | ✔ |
| Benutzerdefinierter Berichts-Builder | — | — | ✔ |

---

## Einsatzszenarien

| Version | Empfohlene Szenarien |
|------|---------|
| **Lite** | Kleine und mittlere Handelsunternehmen mit Einkauf/Verkauf/Lager + grundlegender Finanzierung als Kern, ohne Genehmigungsabläufe und Erweiterungsmodule |
| **Standard** | Gleiche Funktionsumfang-Größenordnung, schlankeres Tabellendesign, geeignet als Basis für kundenspezifische Entwicklung |
| **Full** | Mittelgroße und große Unternehmen, die eine vollständige Plattform aus Einkauf/Verkauf/Lager + Finanzen + CRM + HR + Produktion + Projektmanagement benötigen |

---

## Upgrade-Pfad

| Version | Umfang (Datentabellen / Geschäftsmodule) | Beschreibung |
|------|--------------------------|------|
| Lite (vereinfacht) | 62 Tabellen / 6 Geschäftsmodule (Planwerte) | Keine Genehmigung/Benachrichtigungen/HR/Produktion/Berichte |
| Standard | 72 Tabellen / 6 Geschäftsmodule (Planwerte) | Schlankeres Datenmodell |
| Full (vollständig) | 227 Tabellen <!-- stats:tables=227 --> / 23 Geschäftsmodule <!-- stats:modules=23 --> | Umfassende Unternehmensplattform-Fähigkeiten |

---

## Branch-Strategie (ab 2026-08-27)

> Gilt für die drei Versionsbranches `lite` / `standard` / `full`, konsistent mit dem release-Job der CI (idempotente Versions-Tags).
> **Ergänzung zum Ist-Zustand (real gemessen am 2026-09-22)**: Die drei Branches wurden gelöscht; die restlichen Punkte dieses Abschnitts sind im Sinne von „Archiv = Commits und Tags" zu verstehen,
> es gibt keine Versionsbranches mehr zum Auschecken.

- **`main` ist die einzige Entwicklungsquelle**: Alle Funktionsentwicklungen, Fehlerkorrekturen und Abhängigkeits-Upgrades werden ausnahmslos in `main` gemerged, Commits führt ausschließlich der Lead aus.
- **Versionsbranches werden nur archiviert, nicht gepflegt**: `lite` / `standard` / `full` sind als historische Archivbranches eingefroren, erhalten keine neuen Commits,
  synchronisieren keine Inkremente aus `main` und werden auch nicht zwangsweise aktualisiert oder gepusht (um nicht drei Code-Linien pflegen zu müssen); **nach Ablauf der Einfrierphase wurden die drei Branches gelöscht**,
  die archivierten Inhalte bleiben im `main`-Verlauf unter `eea90c0` erhalten.
- **Versionsunterschiede werden über Versions-Tags festgehalten**: Releases werden vom release-Job der CI idempotent als `vX.Y.Z`-Tag angelegt
  (siehe `scripts/bump-version.sh`); funktionale Unterschiede zwischen den Versionen ergeben sich aus den Tags und der obigen Funktionsvergleichstabelle, nicht aus gepflegten Branch-Code-Linien.
- **Validierung**: Die CI von `main` ist die Release-Validierung, archivierte Branches führen keine eigene CI mehr aus. (Ab 2026-09-15 hängt der release-Job von `docs` + `e2e` ab; der php-Job läuft weiter, blockiert aber kein Release — seine roten Punkte sind CI-spezifische historische Schulden aus Integrationstests, siehe Kommentare in `.github/workflows/ci.yml`.)
