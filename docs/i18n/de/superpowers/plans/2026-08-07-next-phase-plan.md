# Planung der nächsten Phase (P4 / Evolutionsphase 1.1)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Erstellt: Systemarchitekt ｜ Datum: 2026-08-07 ｜ Grundlage: drei Vorab-Recherchen (Planung & Lücken / Backend & Qualität / Frontend) + Stichproben-Nachprüfung vor Ort
> Status: Entwurf (zur Prüfung) ｜ Zielversion: 1.1 (Evolutionsphase)

---

## 1. Phasenpositionierung

Die Roadmap P0~P3 ist vollständig geliefert: 22 Geschäftsmodule, 163 Tabellen, 121 Controller, 24 Services, 161 Modelle, 12 Middlewares;
Flutter 96 Seiten + HarmonyOS 34 Seiten; Gesamtbewertung 89/100. **In dieser Phase werden keine neuen Geschäftsdomänen hinzugefügt**, sondern „implementiert, aber nicht geschlossene"
Fähigkeiten werden vervollständigt, Qualitätsschulden abgebaut, Dokumentationsdrift beseitigt und eine langfristig wartbare **1.1-Evolutionsversion** produziert.

Drei Kernbefunde (alle durch Stichproben bestätigt):

1. **Viele Fähigkeiten „existieren, wirken aber nicht"**: Die TenantScope-Middleware und das Modell-Trait sind nicht in `config/middleware.php` registriert (Multi-Mandant ist eine leere Hülle);
   Die Warteschlange ist mit redis/rabbitmq-Dual-Treiber konfiguriert, aber `config/process.php` hat keinen Konsumenten-Prozess; WebSocket-Verbindungen prüfen kein JWT;
   Die OMS/WMS/TMS-Statistiken des Flutter-Dashboards sind hartkodierte Scheinwerte, während die Backend-Endpunkte `/dashboard/oms|wms|tms` bereits existieren, aber nicht aufgerufen werden;
   Das Frontend ruft den nicht existierenden Benachrichtigungs-Endpunkt `/admin/notification/my/read` auf (das Backend hat tatsächlich `/admin/notification/read-all`).
2. **Qualitäts- und Sicherheitsrückstände**: 11 Geschäftsmodule ohne Tests; PHPStan level 5, aber die Baseline unterdrückt 974 Fehler; 137 Tests sind reine Unit-Tests, ohne Integration/E2E/Abdeckung;
   `.env.docker` enthält viele schwache Schlüssel; CI hat nur PHP-Jobs, keinerlei Frontend-Qualitätsgates.
3. **Systematische Dokumentationsdrift**: Testzahlen 132/779→135/799→137/805 über drei Versionen inkonsistent; der FUNCTIONS.md-Anhang weicht stark von der Messung ab;
   EDITIONS.md-Zahlen widersprechen sich; die drei Branches lite/standard/full hinken main 20~41 Commits hinterher.

**Prinzip**: Zuerst „implementiert, aber nicht geschlossen" nachziehen (tote Endpunkte, nicht angeschlossenes TenantScope/Warteschlange, Mock-Dashboard), dann Tests und Qualitätsgates ergänzen,
dann Struktur und Dokumentation optimieren. Alle Aufgaben sind klein und klar umrissen und in einer einzelnen Agent-Sitzung machbar; Unsicheres wird mit „zu verifizieren" markiert.

---

## 2. Lückenanalyse (Zusammenfassung)

Die Lücken aus drei Recherchen werden in **6 Arbeitsgruppen** zusammengefasst. Zu jedem Punkt wird der Beweispfad angegeben.

### Arbeitsgruppe A: Geschäftsprozess-Schließung (höchste Priorität)

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| A1 | Frontend ruft für „alle als gelesen markieren" der Benachrichtigungen einen nicht existierenden Endpunkt auf | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` ruft `/admin/notification/my/read` auf; Backend-Route ist `POST /admin/notification/read-all` in `config/route.php:250` | Bestätigt |
| A2 | Dashboard-OMS/WMS/TMS-Statistiken sind Mock-Scheinwerte, Requests ohne JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (unabhängiges Dio mit `baseUrl: http://localhost:8787`, ohne Interceptor; `omsStats/wmsStats/tmsStats` hartkodiert; Kommentar "Mock values for now"); echte Backend-Endpunkte in `config/route.php:231-233` | Bestätigt |
| A3 | TenantScope-Middleware und Modell-Trait nicht angeschlossen, Multi-Mandant ist eine leere Hülle | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` existieren; die globale Kette in `config/middleware.php` registriert nur Locale/Cors/SecurityFilter/RateLimit/TracingId, auch in den route.php-Gruppen keine Referenz | Bestätigt |
| A4 | Warteschlange mit Dual-Treiber, aber ohne Konsumenten-Prozess, Ende-zu-Ende wirkungslos | `config/queue.php` (Standard redis, optional rabbitmq); `config/process.php` enthält nur die drei Prozesse webman/socket/monitor | Bestätigt |
| A5 | WebSocket ohne Authentifizierung | Kommentar "could validate JWT here" in `app/process/WebSocket.php:23`; `:47-50` auth-Nachricht liefert direkt success:true zurück, ohne Token-Prüfung | Bestätigt |
| A6 | Paginierungs-Parameter von 25 HarmonyOS-Listenseiten wirkungslos (`${this.page}` in einfachen Anführungszeichen wird nicht interpoliert) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (stichprobenartig geprüft); 24 weitere Stellen mit gleichem Muster | Bestätigt (Liste noch vollständig abzugleichen) |
| A7 | Geschäftsaktions-Endpunkte in großem Umfang nicht ans Frontend angebunden (Abrechnung/Drei Abschlüsse/Erfüllung/Genehmigung/Gehaltsberechnung usw.) | Ergebnis der Abdeckungsmatrix-Recherche; z. B.: Einkauf/Verkauf ohne Abrechnungsseite, Finanzen ohne 13 Endpunkte, CRM ohne follow/funnel/Vertragsfluss | Zu verifizieren (Liste Modul für Modul abzugleichen) |
| A8 | Formulare vieler Geschäftsseiten haben nur die generischen name/code-Felder | Recherche-Ergebnis (Verkaufsauftrag/Beleg erstellen füllt nur Name/Code) | Zu verifizieren (Seite für Seite abzugleichen) |

### Arbeitsgruppe B: Wiederaufbau des Testsystems

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| B1 | 11 Geschäftsmodule ohne Tests: crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | Die 19 Testdateien in `tests/` decken nur admin/finance/inventory/oms/wms/tms/notification/hr/mrp/Sicherheits-Basisklassen ab; die obigen 11 Module haben keine eigenen Testdateien — von diesen werden crm/eam/dms/quality/report/workflow in keiner Testdatei **auch nur erwähnt**; project/purchase/sales/product/bi werden nur zufällig von allgemeinen Basisklassentests oder Nachbarmodul-Tests referenziert (ControllerPatternTest-Mustersampling, bootstrap.php-Routenliste, InventoryServiceTest erwähnt purchase/product im Einlagerungskontext, in DoubleEntryServiceTest ist "bi" ein Teilstring von debit_amount), jeweils keine eigentliche Abdeckung | Bestätigt |
| B2 | Keine Integration/E2E/Abdeckung; 137 Tests / 805 Assertions sind reine Unit-Tests (in unter 1.2s gemessen, rein im Speicher) | `vendor/bin/phpunit` gemessen: "OK (137 tests, 805 assertions)" | Bestätigt |
| B3 | PHPStan level 5, aber Baseline unterdrückt 974 Fehler | `phpstan-baseline.neon` gemessen: 974 message-Knoten | Bestätigt |
| B4 | CI ohne Abdeckungserfassung, ohne Integrationstest-Job | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, nur composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Bestätigt |
| B5 | purchase/sales-Controller mit hartkodierten Service-Abhängigkeiten | `app/controller/sales/DeliveryController.php:142-143`, `app/controller/purchase/ReceiveController.php:142-143` (in beiden Dateien `use`-Deklarationen bei :15-16, `new InventoryService()/new FinanceService()`-Instanziierung bei :142-143) | Bestätigt |

### Arbeitsgruppe C: Infrastruktur- und Sicherheits-Governance

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| C1 | Schwache Schlüssel in `.env.docker` | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest` (.env.docker:15,32,37,51,67,81) | Bestätigt |
| C2 | Strengen-Umgebungsvariablen-Prüfung unvollständig | Recherche: nur ENCRYPTION_KEY durchläuft env_required | Zu verifizieren (config/jwt.php, encryption.php prüfen) |
| C3 | fail-open verschluckt Fehler stillschweigend | Recherche-Ergebnis; Umfang zu auditieren (leere try/catch, catch ohne Logging) | Zu verifizieren (grep-Audit erforderlich) |
| C4 | backup-validator.sh und migrationsweises `_rollback.sql` fehlen | `find` im gesamten Repository ohne Treffer; keines der 29 SQL-Migrationen in `database/migrations/` hat eine zugehörige Rollback-Datei | Bestätigt |
| C5 | Benachrichtigungskanäle als Stub (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Bestätigt |
| C6 | Monitoring-Lücke: keine Metriken für Warteschlangen-Stau/WebSocket-Verbindungszahl | `app/admin/controller/MetricsController.php` hat derzeit 5 Gauges | Teilweise bestätigt |

### Arbeitsgruppe D: Versionsmatrix und Dokumentations-Governance

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| D1 | Branches lite/standard/full hinken main 20~41 Commits hinterher | `git rev-list --left-right --count main...lite|standard|full` gemessen: 41/41/20 behind, und lite/standard haben je 6~7 ahead-eigene Commits | Bestätigt |
| D2 | EDITIONS.md-Zahlen widersprechen sich | Übersichtstabelle: Controller 48/42/70, Geschäftsmodule 6/6/12; im Upgrade-Pfad-Abschnitt stehen aber 12/12/19 Module, 163 Tabellen; passt nicht zu den gemessenen 121 Controllern | Bestätigt |
| D3 | FUNCTIONS.md-Anhang driftet | Anhang schreibt 11 Dateien/90 Methoden/168 Assertions/9 Middlewares/22 Migrationen; gemessen: 19~20 Dateien/137 Tests/805 Assertions/12 Middlewares/29 Migrationen | Bestätigt |
| D4 | Testzahlen über drei Versionen driftend (132/779→135/799→137/805) | Dokumenthistorie und git-Commits | Bestätigt |
| D5 | Fertigstellungsmatrix markiert QMS/EAM/DMS/BI mit 🔴, obwohl der Code existiert | Matrix nahe `docs/FUNCTIONS.md:555` vs. bereits implementiert in `app/controller/{quality,eam,dms,bi}/` | Bestätigt |
| D6 | Controller-Zählweise uneinheitlich: docs/CLAUDE.md schreibt "104 Geschäftscontroller", gemessen insgesamt 122 | `find app -path '*/controller/*.php' | wc -l` = 122 (inkl. admin 14 + api 3 + Geschäft 104 + Index/Install); Recherche-Zählweise 121 | Bestätigt (Zählweisen-Differenz) |
| D7 | Migrations-Zählweise: Recherche 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (nummeriert bis 000030, 000007/000008 fehlen) | Bestätigt (29 ist die Messung) |

### Arbeitsgruppe E: Frontend-Qualität und -Abgleich

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| E1 | CI ohne flutter analyze/test/build, ohne hvigor-Build | `.github/workflows/ci.yml` nur PHP-Jobs | Bestätigt |
| E2 | README behauptet, CI enthalte Flutter-Statikanalyse, was nicht den Tatsachen entspricht | `README.md:635` „Flutter-Statikanalyse (flutter analyze)" vs. kein solcher Schritt in ci.yml | Bestätigt |
| E3 | Flutter hat nur 1 Smoke-Test | `apps/flutter/test/widget_test.dart` ist die einzige Testdatei | Bestätigt |
| E4 | HarmonyOS-Token wird nicht persistiert (AppStorage nur im Speicher, Kaltstart zurück zur Login-Seite) | Recherche-Ergebnis (`apps/harmonyos/entry/src/main/ets/service/ApiService.ets` zu prüfen) | Zu verifizieren |
| E5 | 25 HarmonyOS-Seiten nach Vorlage, schreibgeschützte name/code-Listen ohne Anlegen/Bearbeiten/Löschen | OrderListPage.ets vollständig (65 Zeilen) stichprobenartig geprüft: nur name/code-schreibgeschützte Liste | Bestätigt |
| E6 | Unzureichende Frontend-Abdeckungstiefe (siehe A7/A8) | wie oben | Zu verifizieren |

### Arbeitsgruppe F: API-Schichtung und Architektur-Governance (niedrige Priorität, nach Kräften)

| # | Lücke | Beweispfad | Status |
|---|------|----------|------|
| F1 | /api-Versionierung mit nur 3 Controllern, Geschäft komplett im /admin-Monolithen | `app/api/v1/controller/` enthält nur Captcha/Auth/Product | Bestätigt |
| F2 | Controller von 10 Modulen fragen Modelle direkt ab, ohne Service-Schicht | Recherche-Ergebnis (crm/product-Controller nutzen Modelle direkt für Abfragen) | Teilweise bestätigt (vollständiges Audit ausstehend) |
| F3 | purchase/sales instanziieren Services hartkodiert mit `new` statt Dependency Injection | Beweis B5 | Bestätigt |

---

## 3. Phasenplanung

Nach Priorität in drei Lose aufgeteilt (P0→P1→P2), **jede Phase unabhängig veröffentlichbar, alle Abnahmekriterien quantifizierbar**. Gesamtdauer ca. **8~9 Wochen** (Parallelitätsannahme: geschätzt mit **2~3 Entwicklern parallel + Agent-Team-Kollaboration**; Einzelaufgaben zusammen ca. **77 Personentage** — P0 ≈12,5d, P1 ≈29,5d, P2 ≈35d — bei serieller Ausführung durch eine Person etwa 15 Wochen. Parallelitäts-Begründung: A1/A4/A5 u. a. kleine Backend-Aufgaben sind voneinander unabhängig und parallelisierbar; B1-Modultests lassen sich in Teilaufgaben aufteilen und parallelisieren; B/C-Gruppe und E/D-Gruppe können phasenübergreifend überlappen; Flutter/HarmonyOS-Frontend-Aufgaben und Backend-Aufgaben blockieren sich gegenseitig nicht; explizite Abhängigkeiten zwischen Aufgaben siehe §5).

**Nummerierungssystem**: Die Phasen-Aufgabennummern entsprechen eins zu eins den Lücken-Nummern aus §2 (A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3); dabei werden D6/D7 (Controller- und Migrations-Zählweise) in Aufgabe D3 vereinheitlicht, E2 (unwahre README-Angabe) in die E1-Abnahme übernommen, E6 (Abdeckungstiefe) in A7-2 übernommen, F1 (/api-Versionierung) ist ausdrücklich nicht Teil dieser Phase (siehe §6); außerdem gibt es eine i18n-Aufgabe entsprechend der Recherche „Flutter i18n unvollständig", nicht in der Lückentabelle nummeriert.

### 3.1 Los P0: Schließungs-Baseline (Woche 1~2)

**Ziel**: Tote Endpunkte und Scheindaten beseitigen, die vorhandenen nicht angeschlossenen Fähigkeiten (TenantScope/Warteschlange/WebSocket) zu nutzbaren Zuständen bringen oder klar downgraden.

| Aufgabe | Inhalt | Betroffener Bereich | Abnahmekriterien | Dauer |
|------|------|----------|----------|------|
| A1 | „Alle als gelesen markieren" der Benachrichtigungen reparieren: Frontend ruft stattdessen `POST /admin/notification/read-all` auf (oder Backend ergänzt Alias-Route, eine von beiden, Frontend-Änderung empfohlen) | `notification_page.dart` + `config/route.php` | Manueller/automatischer Aufruf erfolgreich; 1 neue PHPUnit-Assertion, dass die Route existiert | 0.5d |
| A2 | Dashboard an echte Daten anschließen: unabhängiges Dio entfernen, auf ApiService umstellen (JWT-Interceptor); die drei Tabs OMS/WMS/TMS rufen `/dashboard/oms\|wms\|tms` auf; hartkodierte Scheinwerte löschen; Redis-5m-Cache-Semantik beibehalten | `dashboard_controller.dart` + zugehörige Seiten | Im eingeloggten Zustand zeigen die drei Dashboard-Tabs echte Backend-Daten, im Network-Panel 200 mit Authorization-Header sichtbar; Mock-Kommentare löschen | 2d |
| A3 | TenantScope anschließen: in der `/admin`-Routengruppe registrieren; Tenant-ID aus dem JWT-Claim oder dem `X-Tenant-Id`-Header (**Entscheidungspunkt**, siehe §5); Modell-Trait ist fertig, keine großen Änderungen nötig | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | Daten zweier Mandanten gegenseitig unsichtbar (neuer Integrationstest); ohne Tenant-Header 400 statt stillschweigendem Durchlassen; **Alternativ-Downgrade**: falls der Zeitpunkt ungeeignet erscheint, stattdessen in der Doku klar vermerken „Multi-Mandant ist eine reservierte Fähigkeit" und Aktivierungsschritte angeben, Abnahme = Doku und Code konsistent | 2d |
| A4 | Warteschlange Ende-zu-Ende: in config/process.php einen `redis-queue`-Konsumentenprozess ergänzen (Standard-redis-Treiber); eine beobachtbare Smoke-Aufgabe ergänzen (z. B. asynchrones Schreiben des Betriebsprotokolls); Doku beschreibt die Umstellungsschritte auf rabbitmq | `config/process.php`、`app/queue/` | Nach dem Start ist der Konsumentenprozess online (`php start.php status`); nach Einlieferung der Smoke-Aufgabe tritt der Ziel-Nebeneffekt innerhalb von 5s auf | 1d |
| A5 | WebSocket-Authentifizierung: JWT bei Verbindungsaufbau/`auth`-Nachricht prüfen (AdminAuth-Logik wiederverwenden), bei ungültigem Token auth_result:false zurückgeben und trennen; Doku synchronisieren | `app/process/WebSocket.php` + Frontend-Verbindungsstelle | Verbindungen ohne/gefälschtem Token werden abgelehnt; gültiges Token verbindet erfolgreich; 1 neuer Test deckt ab | 1d |
| A6 | HarmonyOS-Paginierung reparieren: 25 Stellen mit Einfach-Anführungszeichen-Interpolation auf Template-Strings/Konkatenation umstellen; page erhöhen + Laden am Listenende + Pull-to-Refresh; einheitliche Paginierungskomponente extrahieren | `apps/harmonyos/entry/src/main/ets/pages/**` (25 Dateien) | grep über das gesamte Repository ohne Reste des `${this.page}`-Einfach-Anführungszeichen-Musters; Seitenwechsel-Request-Parameter korrekt; Build erfolgreich | 2d |
| A7-1 | Tote Endpunkte vollständig auf Null: auf Basis der Recherche-Abdeckungsmatrix einen automatischen Vergleich „Frontend-URL × Backend-Route" laufen lassen (Skript extrahiert Flutter/HarmonyOS-Request-Strings vs. `config/route.php`), Rest-Differenzliste ausgeben | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | Vergleichsskript-Ergebnis wird eingecheckt (docs/); in der Differenzliste „vom Frontend aufgerufen, aber im Backend nicht vorhanden" auf Null (nicht vorhanden, aber berechtigt → Whitelist-Vermerk) | 2d |
| A8-1 | Hochwertige Formulare um Felder ergänzen: Einkaufs-/Verkaufsaufträge, Belegseiten um geschäftskritische Felder ergänzen (Beträge/Daten/Geschäftspartner/Positionszeilen), nur auffüllen, keine Formular-Engine | Zugehörige Flutter-Seiten | Formulare können vollständige Belege mit Geschäftsfeldern erstellen, API 200 | 2d |

**P0-Abnahmezusammenfassung**: A1~A6 alle umgesetzt; tote-Endpunkte-Liste auf Null; CI komplett grün; keine neue Dokumentationsdrift (Änderungen aktualisieren die Funktionsliste in docs/CLAUDE.md mit).

### 3.2 Los P1: Test- und Sicherheitsbaseline (Woche 3~5)

**Ziel**: Das Testsystem von „reinen Unit-Tests" auf „Unit + Integration + Abdeckung" aufrüsten, Sicherheitsschwächen auf Null.

| Aufgabe | Inhalt | Betroffener Bereich | Abnahmekriterien | Dauer |
|------|------|----------|----------|------|
| B1 | Tests für 11 Geschäftsmodule ergänzen: pro Modul Service-/Modellschicht-Tests, Abdeckung von CRUD + Kernaktionen (Abrechnung, Genehmigungsfluss, QS-Prozess, Geräte-Workorders usw.) | `tests/` (neue Testdateien für crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow) | Neu ≥150 Tests / ≥500 Assertions; 11 Module je ≥10 Tests; `vendor/bin/phpunit` komplett grün | 2w |
| B2 | Integrationstests: die vorhandenen mysql8/redis7-Services der CI nutzen, neue Integrationstest-Gruppe (CRUD gegen echte DB + Transaktions-Rollback + TenantScope-Isolations-Verifikation + Warteschlangen-Smoke) | `tests/Integration/` + Gruppierung in `phpunit.xml` | Integrationsgruppe in CI komplett grün; lokal mit `--group=integration` ausführbar | 1w |
| B3 | E2E-Smoke: echtes HTTP durch health→login→Kern-CRUD→Dashboard, skriptbasiert | `tests/E2E/` (curl/php-Skripte) | Neuer CI-Job läuft 10 Kernketten durch, Fehler = rot | 2d |
| B4 | Abdeckung: phpunit --coverage anschließen, Schwelle setzen (Geschäftsschicht ≥40 %, gesamt ≥30 %, zu verifizieren, ob CI die xdebug-Erfassung unterstützt) | `phpunit.xml`、`ci.yml` | CI produziert Abdeckungsbericht; unter der Schwelle = Fehlschlag | 1d |
| B5 | Controller auf Services umstellen (4 Hochfrequenz-Module): in finance/inventory/sales/purchase-Controllern `new` entfernen, Services aus dem Container holen (`support\Container`), Weg für B1-Tests bereiten | `app/controller/{finance,inventory,sales,purchase}/**` | Keine Reste von `new InventoryService/FinanceService`; vorhandene Tests komplett grün | 3d |
| C1 | Schwache Schlüssel auf Null: `.env.docker`/`.env.example` auf Zufalls-Platzhalter + strenge Startprüfung umstellen (fehlend/gleich Platzhalter = Start verweigern); CI um `env-Prüfung`-Schritt ergänzen | `.env*`、`config/*.php`、`ci.yml` | Start mit `change-me` schlägt direkt fehl mit Anleitung; neue Docker-Instanz generiert automatisch Zufallsschlüssel | 1d |
| C2 | Strengen-Umgebungsvariablen-Prüfung erweitern: JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD in env_required aufnehmen (zuerst Ist-Zustand von config/jwt.php prüfen, zu verifizieren) | `config/*.php` | Start schlägt bei fehlendem Schlüsselschüssel fehl, Fehlermeldung eindeutig auf Chinesisch | 1d |
| C3 | fail-open-Audit: leere catches/catches ohne Logging per grep finden, auf fail-closed + Logging (inkl. TraceId) umstellen | gesamtes app/ | Audit-Liste eingecheckt; Reparaturen durch Tests oder Logs belegt | 2d |
| C4 | Migrations-Governance: `database/backup/backup-validator.sh` ergänzen (automatische Wiederherstellungsprüfung nach Backup) + 29 migrationsweise `_rollback.sql` (Tabellenstruktur aus install.sql rückgeschlossen) | `database/` | validator-Skript läuft gegen Backup-Dateien durch (Backup→Wiederherstellung→Vergleich Tabellen-/Zeilenzahlen); neben jeder Migrationsdatei liegt gleichnamiges `_rollback.sql` | 2d |
| C5 | Benachrichtigungskanäle umsetzen (entspricht Lücke C5): mindestens einen nutzbaren Kanal durchgängig schalten (empfohlen email: Senden über SMTP-Treiber oder Datei-Log-Treiber); falls der Zeitpunkt ungeeignet erscheint, klar dokumentiert auf „nur In-App-Nachrichten + reservierte email/wecom/dingtalk-Adapterpunkte" downgraden und Anschlussschritte angeben (eine von beiden, explizite Entscheidung erforderlich) | `app/service/notification/ChannelRouter.php` + neue Treiberklasse + docs | E-Mail-Treiber: ChannelRouter liefert nach erfolgreichem Versand true (Test mit Log-Treiber-Assertion); bei Downgrade: Kommentar in ChannelRouter.php:23 und docs markieren eindeutig „reserviert", Mehrdeutigkeit von "stub for future implementation" beseitigen | 1.5d |
| C6 | Metriken ergänzen: Warteschlangen-Stau (redis LLEN), Anzahl der WebSocket-Onlineverbindungen | `MetricsController.php` | `/metrics` gibt 2 neue Gauges aus | 1d |

**P1-Abnahmezusammenfassung**: Testgesamtanzahl ≥287 (137+150); Abdeckungsbericht produziert und über der Schwelle; Start schlägt bei schwachen/fehlenden Schlüsseln fehl; validator- und Rollback-Skripte vorhanden; mindestens ein Benachrichtigungskanal nutzbar oder Downgrade klar dokumentiert; neue CI-Jobs Integration/E2E/Abdeckung komplett grün.

### 3.3 Los P2: Dokumentation, Versionsmatrix und Frontend-Tiefe (Woche 6~8)

**Ziel**: Dokumentationszahlen vollständig mit den Code-Fakten abgleichen (automatische Prüfung), Versionsmatrix wieder vertrauenswürdig, Frontend um hochwertige Tiefe ergänzen.

| Aufgabe | Inhalt | Betroffener Bereich | Abnahmekriterien | Dauer |
|------|------|----------|----------|------|
| D1 | Drei-Branch-Synchronisierung: main in lite/standard/full mergen, Konflikte lösen, CI der drei Branches komplett grün; **Entscheidungspunkt**: danach Strategie „main als einzige Entwicklungsquelle, Versionsbranches nur per cherry-pick bei Releases" | git drei Branches + ci.yml | behind=0 bei allen drei Branches; CI der drei Branches jeweils grün; Konfliktlösungen dokumentiert | 1w |
| D2 | EDITIONS.md neu schreiben: Messung als Maßstab (Tabellen-/Controller-/Modulzahlen aus dem Code-Zählskript), sich widersprechende Absätze löschen | `docs/EDITIONS.md` | Alle Dokumentzahlen stimmen mit der Skriptausgabe überein | 1d |
| D3 | Dokumentationsstatistik automatisieren: `scripts/doc-stats.sh` schreiben (Controller-/Service-/Modell-/Migrations-/Test-/Middleware-Zählung + phpunit-Ausgabe), FUNCTIONS.md-Anhang referenziert dessen Ausgabe; zugleich D6 (Controller-Zählweise 104/121/122) und D7 (Migrations-Zählweise 22/29/30) auf die alleinige Skript-Zählweise vereinheitlichen | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | Skriptausgabe und Dokumentation konsistent; alle Zahlen in README/docs per Skript reproduzierbar (inkl. Vereinheitlichung der Controller-/Migrations-Zählweise) | 2d |
| D4 | Fertigstellungsmatrix korrigieren: tatsächlich implementierte Punkte wie QMS/EAM/DMS/BI auf ✅ setzen, mit Code-Beweis | `docs/FUNCTIONS.md` | Matrix entspricht dem `app/controller/`-Verzeichnis eins zu eins, kein 🔴/✅-Fehlstand | 1d |
| D5 | CI-Dokumentationsprüf-Job: doc-stats laufen lassen und mit der Dokumentation vergleichen, Drift = rot | `ci.yml` + Skript | Nach Manipulation einer Zahl wird CI rot (Selbsttest-Demo) | 1d |
| E1 | Flutter-CI-Job: flutter analyze + flutter test + build web, in ci.yml aufnehmen | `ci.yml`、`apps/flutter/` | Alle drei Schritte grün; README.md:635-Angabe entspricht der Realität | 1d |
| E3 | Flutter-Tests erweitern: ApiService-Interceptor/401-Refresh, AuthService-Abläufe, kritische Formularvalidierungen, ≥20 Widget-/Unit-Tests | `apps/flutter/test/` | `flutter test` komplett grün, ≥20 Tests | 1w |
| E4 | HarmonyOS-Token-Persistenz: AppStorage auf dauerhafte Persistenz + Kaltstart-Wiederherstellung + 401-Refresh-Logik (zuerst Ist-Zustand von ApiService prüfen, zu verifizieren) | `apps/harmonyos/.../service/ApiService.ets` | Login-Zustand bleibt nach Prozess-Kill und Neustart erhalten; abgelaufenes Token wird automatisch erneuert | 2d |
| E5 | Kernseiten in HarmonyOS um Anlegen/Bearbeiten/Löschen ergänzen: nach Wert sortiert (aus Einkauf/Verkauf/Bestand/Finanzen/OMS je 2~3 Listenseiten), pro Seite Aktionen und Formulare für Neu/Editieren/Löschen ergänzen | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | Ausgewählte ≥10 Listenseiten haben Anlegen/Bearbeiten/Löschen und funktionieren gegen das Backend; hvigor-Build erfolgreich (ohne HarmonyOS-SDK-Umgebung mit „auf CI-Umgebung warten" markieren) | 1w |
| i18n | Minimales Flutter-i18n (entspricht Recherche „Flutter i18n unvollständig"): ApiService-Fehlermeldungen und Login-/Navigations-/Dashboard-Schlüsseltexte an i18n anschließen (arb-Dateien, gekoppelt mit dem Backend `app/common/I18n.php`); **nur minimal machbar, keine vollständige Seiten-Text-Umstellung** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | Kritische Fehlermeldungen und ≥10 Seiten-Texte per Sprachumschaltung wechselbar (en/zh); `flutter test` komplett grün | 2d |
| A7-2 | Frontend-Tiefenabdeckung: nach der A7-1-Vergleichsliste Seiten für Schlüsselendpunkte ergänzen — Einkaufs-/Verkaufsabrechnung, Finanz-Drei-Abschlüsse/Jahresabschluss/Bankkonten, CRM follow/funnel/Vertragsfluss usw. | `apps/flutter/lib/app/pages/**` | Hochprioritäre Punkte der Vergleichsliste „im Backend vorhanden, im Frontend nicht abgedeckt" (Abrechnung/Drei Abschlüsse/Erfüllung/Genehmigung/Gehalt) auf Null | 1w |
| F2/F3 | Leichte Service-Schicht-Extraktion (optional, nach Kräften): für die 3~5 Module mit den meisten direkten Modellabfragen eine dünne Service-Schicht + Dependency Injection extrahieren; **ausdrücklich kein erzwungener Voll-Refactor** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Controller der extrahierten Module ohne direkte Modellabfragen; vorhandene Tests komplett grün; für nicht extrahierte Module in der Doku „Controller fragen Modelle direkt ab, bekannte technische Schuld" vermerken | 1w |

**P2-Abnahmezusammenfassung**: Drei Branches synchronisiert und CI grün; docs-Zahlen per Skript reproduzierbar; CI enthält Flutter-Job und Dokumentationsprüfung; Flutter ≥20 Tests; HarmonyOS-Persistenz + ≥10 Seiten mit Anlegen/Bearbeiten/Löschen; hochprioritäre Endpunkt-Abdeckung auf Null.

---

## 4. Abnahmekriterien (Zusammenfassung, alle verifizierbar)

- **Endpunkte**: A1-Benachrichtigungsendpunkt, A2 `/dashboard/oms|wms|tms`, A7-Hochprioritätsendpunkte sind alle per curl mit JWT aufrufbar und liefern 200/Geschäftsdaten.
- **Tests**: `vendor/bin/phpunit` komplett grün (≥287 Tests); `flutter test` komplett grün (≥20); Integrations-/E2E-Jobs in CI grün.
- **Sicherheit**: Start mit `change-me`-Schlüsseln schlägt fehl; WebSocket lehnt ungültige Tokens ab; keine leeren catches, die Fehler stillschweigend verschlucken (Audit-Liste).
- **Kanäle/i18n**: mindestens ein Benachrichtigungskanal nutzbar oder Downgrade klar dokumentiert; Flutter-Kernfehlermeldungen und ≥10 Texte zwischen Chinesisch und Englisch umschaltbar (minimal machbar).
- **CI**: alle Jobs in `.github/workflows/ci.yml` grün (PHP-Matrix + Integration + Abdeckung + flutter + Dokumentationsprüfung).
- **Dokumentation**: Ausgabe von `scripts/doc-stats.sh` stimmt mit allen docs-Zahlen überein (Drift = CI rot).
- **Branches**: `git rev-list --left-right --count main...lite|standard|full` jeweils `0 0`.
- **Frontend**: HarmonyOS ohne Reste von `${this.page}` in einfachen Anführungszeichen; Kaltstart behält Login; Kernseiten-Anlegen/Bearbeiten/Löschen funktioniert gegen das Backend.

---

## 5. Abhängigkeiten und Risiken

**Abhängigkeiten**:
- Gruppe A (Schließung) → Gruppe B (Tests): die Tests von B1/B2 müssen gegen **tatsächlich nutzbare** Endpunkte laufen, daher behebt P0 zuerst tote Endpunkte und Anschlüsse, P1 ergänzt dann Tests.
- B5 (Controller auf Services) → B1 (Tests): **bereitet nur den Tests der vier abgedeckten Module finance/inventory/sales/purchase den Weg** (nach Entfernen des `new`-Hardcodings können Services Mock-injiziert werden; davon sind purchase/sales Null-Test-Module, finance/inventory haben bereits Tests und können nebenbei verbessert werden); die Tests der übrigen Null-Test-Module (crm/eam/dms/quality/project/product/bi/report/workflow) **hängen nicht** von B5 ab und können parallel zu B5 laufen.
- D1 (Branch-Synchronisierung) → D3/D5 (Dokumentationsprüfung): erst nach der Synchronisierung ist main die einzige Tatsachenquelle, erst dann kann die Dokumentations-Zählweise eindeutig sein.
- E1 (Flutter-CI) → E3 (Testerweiterung): erst mit Gate hat die Testerweiterung Schutzbedeutung.

**Risiken und Gegenmaßnahmen**:
| Risiko | Auswirkung | Gegenmaßnahme |
|------|------|------|
| TenantScope-Anschluss betrifft alle /admin-Abfragen, kann Daten-Sichtbarkeits-Regressionen einführen | Hoch | Integrationstests zuerst; Mandant aus JWT-Claim (keine Frontend-Änderung nötig); oder innerhalb P0 auf „in der Doku als reserviert markiert" downgraden und explizit entscheiden |
| Merge-Konflikte der Drei-Branch-Synchronisierung können Regressionen einführen | Mittel-hoch | Zuerst main komplett grün; nach dem Merge erst lieferbar, wenn die CI der drei Branches jeweils grün ist; Konfliktlösungen dokumentiert |
| Warteschlangen-Konsumentenprozess in manchen Umgebungen (rabbitmq) nicht verfügbar | Mittel | Standard-redis-Treiber (CI hat bereits redis7), rabbitmq nur dokumentierte Umstellungsschritte |
| WebSocket-Authentifizierungsänderung bricht vorhandene Clients | Mittel | Frontend und Backend im selben Meilenstein koordiniert ändern; ungültige Tokens ablehnen, ohne gültige Sitzungen zu beeinträchtigen |
| Abdeckungsmatrix/Formularfeld-Liste sind Recherche-Ergebnisse, teils „zu verifizieren" | Mittel | A7-1 macht zuerst das automatisierte Vergleichsskript, Skript-Ergebnis ist maßgeblich, Seiten nicht nach Gefühl ergänzen |
| Umfang der Service-Schicht-Umbaus läuft aus dem Ruder | Mittel | klar nur 3~5 Module extrahieren, kein erzwungener Voll-Umbau; keine vollständige /api-Versionierung (F1 nicht in dieser Phase) |
| Abdeckungsschwelle in CI-Umgebung nicht verfügbar (xdebug nicht installiert) | Niedrig | zuerst lokal Bericht + Dokumentations-Schwelle, CI-Erfassungsfähigkeit nach „zu verifizieren" anschließen |
| HarmonyOS-CI (hvigor) benötigt HarmonyOS-SDK, öffentliche CI-Umgebung verfügt möglicherweise nicht darüber | Mittel | mit „auf CI-Umgebung warten" markieren; lokale Build-Verifikation ist maßgeblich, blockiert keine anderen Aufgaben |

---

## 6. Ausdrücklich nicht durchgeführt

In Fortführung der Ausschlüsse aus §12 der Roadmap, außer es treten starke Gründe auf (separate Prüfung und Projektantrag erforderlich):
- ❌ Microservice-Aufteilung / K8s-Deployment (Experiment bleibt in `.claude/worktrees/microservices-split/`, nicht in den Hauptstrang aufgenommen)
- ❌ AI/ML-Fähigkeiten (Prognosen, intelligente Empfehlungen, NLP)
- ❌ Native Apps (iOS/Android nativ) — Flutter deckt bereits alle Plattformen ab
- ❌ GraphQL-Schnittstelle
- ❌ Hardware-Integration (IoT/Scanner/Direktanschluss von Druckgeräten)
- ❌ Vollständige Multi-Mandant-Kommerzialisierung (SaaS-Abrechnung, Mandanten-Self-Service-Aktivierung) — diese Phase nur minimale Anbindung oder dokumentierte Reservierung
- ❌ Vollständige /api-Versionierung (F1) — Geschäft bleibt in /admin, nur als Architekturschuld dokumentiert
- ❌ Vollständige Service-Schicht-Umbau und vollständige Formular-Neuerstellung — Extraktion nach Wert sortiert, kein „Big-Bang"-Refactor
- ❌ Vollständige HarmonyOS-Seitenergänzung — nur Anlegen/Bearbeiten/Löschen hochwertiger Kernseiten
- ❌ Vollständige Flutter-i18n-Textumstellung — diese Phase nur minimal machbar (Fehlermeldungen + ≥10 kritische Texte), vollständige Seiten-Mehrsprachigkeit für spätere Versionen

---

## 7. Meilenstein-Empfehlungen

| Meilenstein | Zeitpunkt | Inhalt | Exit-Kriterium |
|--------|------|------|----------|
| **M1 Schließungs-Baseline** | Ende Woche 2 | Gruppe A komplett: tote Endpunkte auf Null, Dashboard mit echten Daten, TenantScope/Warteschlange/WebSocket umgesetzt, HarmonyOS-Paginierungsreparatur | P0-Abnahmezusammenfassung vollständig bestanden |
| **M2 Qualitätsbaseline** | Ende Woche 5 | Gruppe B komplett + Sicherheitspunkte der Gruppe C: 11-Modul-Tests, Integration/E2E/Abdeckung, schwache Schlüssel auf Null, fail-open-Audit, Migrations-Governance, Benachrichtigungskanäle | P1-Abnahmezusammenfassung vollständig bestanden |
| **M3 Frontend-Qualität** | Ende Woche 6 | Gruppe E: Flutter-CI-Job + Testerweiterung, HarmonyOS-Token-Persistenz und Kernseiten-Anlegen/Bearbeiten/Löschen | flutter-CI grün, Persistenz wirksam, ≥10 Seiten mit Anlegen/Bearbeiten/Löschen |
| **M4 Versions- und Dokumentations-Governance** | Ende Woche 7 | Gruppe D: Drei-Branch-Synchronisierung, EDITIONS/FUNCTIONS-Neuschreibung, doc-stats-Automatisierung + CI-Prüfung | Branches synchronisiert, Dokumentationsdrift = rot |
| **M5 Tiefenabdeckung** | Ende Woche 8 | A7-2-Frontendtiefe + leichte Service-Schicht-Extraktion der Gruppe F | Hochprioritäre Endpunkt-Abdeckung auf Null, extrahierte Module ohne direkte Modellabfragen |
| **M6 1.1-Release** | Ende Woche 9 | Vollständige Regression, Release-Notizen (CHANGELOG), finale Dokumentationsprüfung, Archivierung | Alle Meilenstein-Exit-Kriterien bestanden (harte Kennzahlen): Testgesamtanzahl ≥287 und phpunit komplett grün, Abdeckungsbericht über der Schwelle, alle Jobs in ci.yml grün (PHP-Matrix + Integration + Abdeckung + flutter + Dokumentationsprüfung), Drei-Branch-Synchronisierung 0 0, tote-Endpunkte-Liste auf Null, doc-stats-Drifts-rot-Mechanismus wirksam; CHANGELOG- und Dokument-Endprüfung bestanden; Review-Wiederholungsprüfung nur als Referenz, keine Punktschwelle |

---

## Anhang: In dieser Planung stichprobenartig verifizierte Schlüsseldateien

- `config/middleware.php`、`config/route.php` (:231-233 Dashboard-Endpunkte, :248-251 Benachrichtigungsrouten, :387-415 Middleware-Gruppierung)
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php` (:23、:47-50)
- `app/service/notification/ChannelRouter.php` (:23 stub)
- `app/controller/sales/DeliveryController.php` (:142-143)、`app/controller/purchase/ReceiveController.php` (:142-143, die `new`-Instanziierung beider Dateien liegt hier; `use`-Deklarationen bei :15-16)
- `app/api/v1/controller/` (nur 3 Controller)
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (Mock-Statistiken + unabhängiges Dio)
- `apps/flutter/lib/app/pages/notification/notification_page.dart` (:43 toter Endpunkt)
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets` (:24 Interpolations-Bug)
- `tests/` (19-Testdateien-Liste)、`vendor/bin/phpunit` gemessen 137/805
- `phpstan-baseline.neon` (974 message)
- `.github/workflows/ci.yml` (nur PHP-Jobs)、`README.md` (:635 unwahre Angabe)
- `.env.docker` (schwache Schlüssel)、`database/migrations/` (29 Stück, ohne _rollback)
- `docs/EDITIONS.md` (widersprüchlich)、`docs/FUNCTIONS.md` (Anhang driftet)、`docs/CLAUDE.md` (104 vs. gemessene 122 Controller-Zählweise)
- git-Branches `lite/standard/full` (behind 41/41/20)

> Zählweise-Erläuterung: Controller gemessen mit `find app -path '*/controller/*.php'` = 122 (inkl. admin 14 + api 3 + Geschäftscontroller + Index/Install); Recherche-Zählweise 121, docs/CLAUDE.md-Geschäftszählweise 104, die drei Unterschiede stammen aus unterschiedlichen Zählumfängen und wurden in D6 als Governance-Punkt zur Vereinheitlichung aufgenommen.
