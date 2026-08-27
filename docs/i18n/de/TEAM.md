# Teamplanung (KI-Kollaborationsteam)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Dieses Dokument definiert das KI-Kollaborationsteam dieses Projekts: Rollenzusammensetzung, Verantwortungsgrenzen, Kooperationsmodus und Aufgaben-Routing.
> Zugehörige Koordinationsregeln (SendMessage-First, agent-Namensgebung, Lebenszyklus) siehe `CLAUDE.md` im Projektstamm; Rollendefinitionen siehe `.claude/agents/`.

---

## 1. Projektprofil (Planungsgrundlage)

| Dimension | Ist-Zustand | Bedeutung für das Team |
|------|------|--------------|
| Backend | webman (Workerman) PHP 8.3+, **22 Geschäftsmodule**, 121+ Controller, 24 Services, 161 Modelle, 163 Tabellen, 12 Middleware (schema basiert auf database/install.sql als einziger Tatsachenquelle) | Monolith, groß und umfassend; Arbeitsteilung nach Geschäftsdomänen, um Kontextexplosion einzelner agents zu verhindern |
| Frontend | Flutter **97 Seiten** (Web/Mobil) + HarmonyOS **34 Seiten**, deckt alle Module ab | Parallele Pflege beider Enden, dedizierte Frontend-Rolle erforderlich |
| Qualitätsbasis | PHPUnit 137 Tests / 805 Assertions, PHPStan + baseline, CS-Fixer, CI-Multiversionsmatrix | Disziplin vorhanden; Test-/Review-Rolle direkt in die Pipeline eingebunden |
| Versionsmatrix | Drei Branches `lite` / `standard` / `full` (62/72/163 Tabellen) | Änderungen müssen die Synchronisierung über Branches hinweg berücksichtigen, Versionskoordination nötig |
| Roadmap | P0~P3 geliefert (Gesamtbewertung 89/100), Eintritt in tägliche Iterations- und Evolutionsphase | Teamgröße skaliert nach Aufgabentyp, keine projektförmige Großaufstellung |
| Bestehende Infrastruktur | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, Obergrenze 15 agents, consensus-Koordination), hooks + Gedächtnis | Team hängt direkt an der bestehenden Konfiguration, kein Neuanfang |

---

## 2. Teamzusammensetzung

### 2.1 Kernteam (ständig, 5 Rollen)

| Rolle | Entsprechender bestehender agent | Zuständigkeiten (für dieses Projekt) |
|------|-----------------|--------------------|
| **Projektleiter Lead** | `planner` / `swarm/hierarchical-coordinator` | Anforderungszerlegung → Routing → Abnahme; Pflege der 22-Modul-Aufgabenwarteschlange; Entscheidung über pipeline / fan-out / supervisor-Modi; Rollenübergreifende Nachrichtenvermittlung |
| **Systemarchitekt** | `sparc/architecture` | Tabellenstrukturdesign (163 Tabellen, schema mit database/install.sql als einziger Tatsachenquelle); modulübergreifende Datenflüsse (Wareneingang→Bestand→Verbindlichkeiten, Versand→Forderungen→Warenausgang usw.); Entscheidungen zu Mikroservice-Split-Grenzen |
| **Backend-Entwickler** | `core` / benutzerdefiniert `backend-dev` | Implementierung von Controllern / Services / Modellen; Befolgung der `app/service`-Schichtung und der Middleware-Kette (Locale→Cors→SecurityFilter→RateLimit→TracingId→Business-Middleware) |
| **Testingenieur** | `testing/tdd-london-swarm` + `production-validator` | PHPUnit-Fälle zuerst (Engine-Grenzfalltests); Regressionsverifikation über die drei Branches; Schließen von `tests/`-Abdeckungslücken |
| **Code-Reviewer** | `consensus/security-manager` | PHPStan ohne neue baseline-Abweichungen, CS-Fixer-Konformität, Prüfung der 18 Sicherheitsebenen; Wächter des Qualitäts-Gate vor dem Commit |

### 2.2 Fachteam (je nach Aufgabentyp abgerufen, 4 Rollen)

| Rolle | Entsprechender bestehender agent | Aktivierungsszenario | Typische Aufgaben |
|------|-----------------|----------|----------|
| **Business-Engine-Experte** | benutzerdefiniert `business-engineer` | Algorithmus-Module wie Finanzen / Gehalt / MRP | Verstärkung und Grenzfallbehandlung der Algorithmen für Doppelte Buchführung, Gehaltsberechnung, MRP-Engine (Anforderung der Stufe A „Industriequalität") |
| **Frontend-Ingenieur (Flutter)** | benutzerdefiniert `frontend-flutter` | Jede Änderung, die `apps/flutter/` betrifft | Web-Admin-Panelseiten, GetX-State, ApiService/Export-Verknüpfung, Pflege der 97 Seiten |
| **Frontend-Ingenieur (HarmonyOS)** | benutzerdefiniert `frontend-harmonyos` | Jede Änderung, die `apps/harmonyos/` betrifft | ArkTS-Seiten, nahtlose Token-Erneuerung, Ausrichtung an der Flutter-Funktionsmenge (Pflege der 34 Seiten) |
| **Security/DevOps-Ingenieur** | `consensus/security-manager` + `performance-benchmarker` | Sicherheitshärtung, Performance, Deployment | Regression der 18 Schutzebenen, Docker/gRPC-Subservices, Migrations-Rollback, Beobachtbarkeit, Prometheus-Metriken |

### 2.3 On-Demand-Rollen (aufgabengetriggert, 2 Rollen)

| Rolle | Entsprechender bestehender agent | Aktivierungsbedingung |
|------|-----------------|----------|
| **Researcher** | benutzerdefiniert `researcher` | Vor dem Design neuer Module/neuer Funktionen: Wettbewerber recherchieren, `API.md`, `FUNCTIONS.md` mit der Implementierung abgleichen, Design-Input liefern |
| **Versionskoordinator** | benutzerdefiniert `edition-coordinator` | Bei Unterschieden von `lite/standard/full`: Synchronisierung der drei Branches, Matrix-Prüfung von `EDITIONS.md`, Regression zwischen Branches |

---

## 3. Kooperationsmodus

### 3.1 Allgemeine Regeln (gemäß CLAUDE.md im Projektstamm)

- **SendMessage-First**: agents kommunizieren direkt per SendMessage, kein Polling, kein geteilter veränderlicher Zustand;
- **Namensgebung verpflichtend**: jeder agent muss benannt werden (`name: "role"`);
- **Einmaliges Spawnen**: unabhängige Teilaufgaben werden einmalig im Hintergrund gestartet, Lead stoppt und wartet auf Ergebnisse, kein Status-Polling;
- **Nachricht verpflichtend**: jeder prompt muss angeben „nach Abschluss SendMessage an wen, mit welchem Inhalt".

### 3.2 Drei Orchestrierungs-Topologien

| Modus | Ablauf | Anwendungsszenario |
|------|------|----------|
| **Pipeline** | Lead → Architekt → Backend → Test → Review | Funktionsentwicklung mit sequenziellen Abhängigkeiten (neue Module, modulübergreifende Datenflüsse) |
| **Fan-out** | Lead → A, B, C → Lead aggregiert | Voneinander unabhängige parallele Arbeit (mehrere Seiten, mehrere Modul-Recherchen) |
| **Supervisor** | Lead ↔ Mitglieder, mehrere Runden | Komplexe Arbeit mit kontinuierlicher Koordination (Mikroservice-Split, großflächige Refactorings) |

### 3.3 Aufgaben-Routing-Tabelle

| Aufgabentyp | Orchestrierung | Beteiligte Rollen |
|----------|------|----------|
| Neues Modul / neue Funktion (z. B. DMS, BI-Vertiefung) | pipeline | Lead → Architekt (Tabellendesign) → Backend → Test → Review |
| Engine-Algorithmen (Doppelte Buchführung / Gehalt / MRP) | pipeline + TDD | Lead → Business-Engine-Experte (Design) → Test (Grenzfall-Fälle zuerst) → Review |
| Frontend-Seiten (Flutter / HarmonyOS parallel) | fan-out | Lead → Frontend×2 + Backend (API-Abgleich) parallel → Lead aggregiert |
| Modulübergreifende Datenflüsse (Einkauf→Bestand→Verbindlichkeiten usw.) | pipeline | Lead → Architekt → Backend → Test → Review |
| Mikroservice-Split / großflächiges Refactoring | supervisor | Lead ↔ Architekt + Backend + Review, mehrere Runden |
| Sicherheits-/Performance-Spezialthemen | Einstrang-Tiefenbearbeitung | Lead → Security/DevOps-Ingenieur → Review |
| Bug-Fixes (einzelne Datei / 1-2 Zeilen) | nicht im Team | Lead bearbeitet direkt, oder 1 agent erledigt es |
| Dreigliedrige Branch-Unterschiede / Versionsrelease | pipeline | Lead → Versionskoordinator → Test (branchübergreifende Regression) → Review |

### 3.4 Qualitäts-Gate (vor dem Commit verpflichtend, vom Reviewer bewacht)

```
phpunit            # 137 Tests / 805 Assertions komplett grün, neue Fälle mit der Änderung einreichen
phpstan            # keine neuen Probleme außerhalb der baseline zulässig
php-cs-fixer       # --dry-run bestanden
composer audit     # keine Hochrisiko-Abhängigkeitslücken
```

Änderungen mit Datenbankbezug müssen durch den Architekten (163 Tabellen, schema mit database/install.sql als einziger Tatsachenquelle); Änderungen am Frontend müssen Flutter `flutter analyze` mit 0 error / 0 warning bestehen.

---

## 4. Empfehlung zur Teamgröße

| Arbeitsform | Empfohlene Größe | Beschreibung |
|----------|----------|------|
| Tägliche Wartung / kleine Reparaturen | 1-2 Personen | Lead bearbeitet direkt, Über-Orchestrierung vermeiden |
| Iteration eines einzelnen Moduls | 3 Personen | Lead + Backend + Test |
| Modulübergreifende Funktionen | 4-5 Personen | Lead + Architekt + Backend + Test + Review |
| Parallele Entwicklung beider Frontends | 4-5 Personen | Lead + Flutter + HarmonyOS + Backend (API) + Test |
| Engine-Level / komplexes Refactoring | 5-7 Personen | Alle oben genannten + Business-Engine-Experte oder Security/DevOps |

> Kompatibel mit `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, `consensus`-Koordinationsstrategie); ein einzelner Auftrag überschreitet die Obergrenze nicht.

---

## 5. Umsetzungsschritte

1. **Rollendefinitionen ergänzen**: `.claude/agents/` enthält bereits planner / sparc / testing / swarm / consensus; es fehlen die fünf Definitionen `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator`; im vorhandenen YAML/MD-Format jeweils eine Datei ergänzen und der Anschluss ist fertig;
2. **Routing verfestigen**: die §3.3-Routingtabelle in die routing-Logik von `.claude-flow/hooks` schreiben, damit der `UserPromptSubmit`-Hook Aufgaben automatisch an die entsprechende Rolle delegiert;
3. **Gedächtnis nach Domänen**: `.claude-flow` hat bereits `agentScopes` aktiviert (`defaultScope: project`); Empfehlung: Archivierung nach den vier Domänen `backend / frontend / ops / security`, um Kontextverschmutzung der Finanz-Engine in Frontend-Aufgaben zu vermeiden;
4. **Pilotlauf**: eine modulübergreifende Aufgabe auswählen (z. B. DMS-Vertiefung oder BI-Dashboard-Iteration), nach §3.3 einmal komplett durchlaufen lassen, nach Verifikation der Nachrichtenkette und des Gates verallgemeinern.

---

## 6. Änderungsverlauf

| Datum | Änderung |
|------|------|
| 2026-08-07 | Erstversion: auf Basis des 22-Modul-Ist-Zustands (P0~P3 geliefert, 89/100) Team aus Kern 5 + Fach 4 + On-Demand 2 erstellt |
