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
| Datentabellen | 62 (Planwert) | 72 (Planwert) | 163 <!-- stats:tables=163 --> |
| Controller | 48 (Planwert) | 42 (Planwert) | 123 <!-- stats:controllers=122 --> |
| Geschäftsmodule | 6 (Planwert) | 6 (Planwert) | 19 <!-- stats:modules=19 --> |

> **Statistik-Basis**: Das Repository implementiert derzeit nur die vollständige Version (Full) als einzigen Codebestand; die Spalten Lite/Standard sind Produktplanwerte (im Codebestand existieren keine entsprechenden Branches) und
> nehmen nicht an der doc-stats-Prüfung teil. Die Zahlen der Full-Spalte werden von `scripts/doc-stats.sh` real gemessen (163 Tabellen / 123 Controller / 19 Geschäftsmodule)
> und stimmen mit der Basis des Anhangs in `FUNCTIONS.md` überein.

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
| 18 Ebenen Sicherheitsschutz | ✔ | ✔ | ✔ |
| Internationalisierung (i18n), zweisprachig Chinesisch/Englisch | — | — | ✔ |

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
| API-Dokumentation (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full (vollständig) | 163 Tabellen <!-- stats:tables=163 --> / 19 Geschäftsmodule <!-- stats:modules=19 --> | Umfassende Unternehmensplattform-Fähigkeiten |

---

## Branch-Strategie (ab 2026-08)

> Dieses Dokument entspricht der aktuellen Branch-Konvention des Repositorys und gilt für die drei Branches `lite` / `standard` / `full`.

- **`main` ist die einzige Entwicklungsquelle**: Alle Funktionsentwicklungen, Fehlerkorrekturen und Abhängigkeits-Upgrades werden ausschließlich in `main` gemerged.
- **Versionsbranches erhalten nur per cherry-pick bei Releases Updates**: `lite` / `standard` / `full` dienen nicht mehr als eigenständige Entwicklungslinien für tägliche Commits;
  nur bei Releases cherry-pickt der Versionstechniker die entsprechenden Funktionen aus `main` (oder führt bei Bedarf einen einmaligen Gesamt-Merge durch)
  und bewahrt im Branch die jeweilige Zuschnitt-Intention (Modulunterschiede siehe Funktionsvergleichstabelle oben).
- **Zuschnitt-Prinzip**: Ein Versionsbranch ist eine Teilmenge von main. Beim Merge/Port von main-Inhalten gilt: Kollidiert die Änderung mit der Zuschnitt-Logik der Version
  (z. B. Modulunterschiede in EDITIONS.md, Routing-Zuschnitt), so bleibt die Zuschnitt-Intention des Branches erhalten; nicht betroffener Code folgt stets dem Stand von main.
- **Validierung**: Nach einem Merge muss ein Versionsbranch die vollständige Syntaxprüfung `php -l` bestehen; durch den Zuschnitt unanwendbare Tests dürfen mit dokumentiertem Grund übersprungen werden.
- **Release**: Merge/Port der Versionsbranches führt der Versionstechniker durch und committet einen Merge-Commit; Commits in `main` werden ausschließlich vom Lead ausgeführt.
