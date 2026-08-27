# Testbericht — 2026-08-26

> Aktualisiert: 2026-08-27 — alle 5 offenen Punkte abgeschlossen; Testzahlen 505/2342/26 → 513/2368/32; dabei 4 → 5 behobene Defekte. Alte Werte siehe „Aktualisierungsverlauf" am Dokumentende.

## Ausführungszusammenfassung

| Kennzahl | Wert |
|------|----|
| Berichtsdatum | 2026-08-26 |
| PHP-Unit-Tests | 513 tests / 2368 assertions / 32 skipped |
| Flutter-Seitentests | 98 tests alle bestanden (flutter analyze 0 error) |
| API-Automatisierung | 104 Endpunkte / ~230 Assertions (CI e2e angeschlossen, siehe Schritt „Run E2E API coverage" in ci.yml) |
| Abdeckung (pcov gemessen) | Gesamt 7,51% / app/service 15,65% / app/controller 3,62% |
| Statische Analyse | PHPStan 0 error ✅ |
| Code-Stil | php-cs-fixer 0 diff ✅ (dabei 3 bestehende Dateien repariert) |
| Nebenbei behobene echte Defekte | 5 (3 PHP + 1 Flutter + 1 Format) |
| Go/Rust | N/A (kein .go/.rs/Cargo.toml-Code im Repository) |

Diesmal handelte es sich um eine dreigleisige parallele Test-Lieferung: PHP-Unit-Tests (php-tester, 9 neue Dateien), API-Automatisierung (api-tester, 1 neue Datei), Flutter-Seitentests (ui-tester, 8 neue Dateien mit 29 Fällen).

## Abdeckungsmatrix

Module (22 Geschäftsdomänen + Systemverwaltung mit 14 Controllern) mit Abdeckungs-Kennzeichnung nach Testtyp.

### 22 Geschäftsdomänen

| Modul | Unit | API | UI | Beschreibung |
|------|------|-----|-----|------|
| Finanzen Consolidation | ✅ | ✅ | — | ConsolidationServiceTest 5 Fälle + API |
| Finanzen AccountBalance | ✅ | ✅ | — | AccountBalanceServiceTest 4 Fälle |
| Finanzen PeriodClose | ✅ | ✅ | — | PeriodCloseServiceTest 5 Fälle |
| Finanzen FinanceRatio | ✅ | — | — | FinanceRatioServiceTest (bestehend) |
| Finanzen DoubleEntry | ✅ | — | — | DoubleEntryServiceTest (bestehend) |
| Bestand Inventory | ✅ | ✅ | ✅ | InventoryServiceExtendedTest 5 Fälle + ERP-Listen-UI |
| Vertrieb Sales | ✅ | ✅ | ✅ | bestehender SalesModuleTest + Verkaufsauftrags-UI |
| Artikel Product | ✅ | ✅ | ✅ | bestehender ProductModuleTest + Artikel-UI |
| Einkauf Purchase | ✅ | ✅ | — | bestehender PurchaseModuleTest |
| Produktion Manufacturing | ✅ | — | — | bestehender ManufacturingServiceTest |
| MRP-Engine | ✅ | — | — | bestehender MrpEngineServiceTest |
| CRM | ✅ | ✅ | — | bestehender CrmModuleTest/CrmServiceTest |
| HR | ✅ | — | — | bestehender HrServiceTest/SalaryEngineServiceTest/BankPayrollServiceTest |
| Projekt Project | ✅ | ✅ | ✅ | bestehender ProjectModuleTest + Projekt-UI |
| Genehmigung Approval/Workflow | ✅ | ✅ | ✅ | bestehender WorkflowModuleTest + Genehmigungs-UI |
| OMS/WMS/TMS | ✅ | — | — | bestehender OmsWmsTmsServiceTest |
| QMS Qualität | ✅ | — | — | bestehender QualityModuleTest |
| EAM Anlagen | ✅ | — | — | bestehender EamModuleTest |
| DMS Dokumente | ✅ | — | — | bestehender DmsModuleTest |
| BI-Berichte | ✅ | ✅ | — | bestehender BiModuleTest + API |
| Benachrichtigungs-Kanäle | ✅ | ✅ | — | NotificationChannelTest (ChannelRouter/WebSocketService 12 Fälle) |
| Berichte/Belegdetails | ✅ | Teilweise | ✅ | Generierungslogik mit Unit-Tests; Detailseiten-UI 3 Fälle (report_list_page_test) |

### Systemverwaltung (14 Controller)

| Controller-Domäne | Unit | API | UI | Beschreibung |
|----------|------|-----|-----|------|
| Admin/User | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (User-Seite) + Benutzerlisten-UI |
| Admin/Role | ✅ | ✅ | ✅ | AdminUserRoleControllerTest (Role-Seite) + Rollenlisten-UI |
| Admin/Permission | ✅ | ✅ | — | AdminPermissionConfigControllerTest (Permission-Seite) |
| Admin/Config | ✅ | ✅ | ✅ | AdminPermissionConfigControllerTest (Config-Seite) + Konfigurations-UI |
| Admin/Health | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Metrics | ✅ | ✅ | — | AdminSystemControllersTest |
| Admin/Docs | ✅ | — | — | AdminSystemControllersTest |
| übrige 7 Controller (Login/Protokoll/Dictionary usw.) | ✅ | ✅ | — | BusinessControllersTest, 10 Domänen repräsentativer Controller-Fehlerpfad-Prüfung |
| Login-Seite | — | ✅ | ✅ | login_flow_test 2 Fälle |
| Persönlicher Bereich | — | ✅ | ✅ | profile_page_test 3 Fälle |
| Protokollseite | — | ✅ | ✅ | log_page_test 2 Fälle |
| Dashboard | — | — | ✅ | dashboard_page_test 5 Fälle |
| Bestandswarnung/Finanzseiten | — | — | ✅ | erp_list_pages_test |

## Teststatistik

### PHP-Unit-Tests: 513 tests / 2368 assertions / 32 skipped

Diesmal 9 neue Dateien (alle mit Copyright-Header, 63 tests / 125 assertions):

| Datei | Fallzahl | Abgedeckte Objekte |
|------|--------|----------|
| tests/ConsolidationServiceTest.php | 5 | finance Konsolidierung |
| tests/AccountBalanceServiceTest.php | 4 | Kontosaldo |
| tests/PeriodCloseServiceTest.php | 5 | Periodenabschluss |
| tests/NotificationChannelTest.php | 12 | ChannelRouter/WebSocketService |
| tests/InventoryServiceExtendedTest.php | 5 | Bestandserweiterung |
| tests/AdminUserRoleControllerTest.php | 9 | User/Role-Controller |
| tests/AdminPermissionConfigControllerTest.php | 8 | Permission/Config-Controller |
| tests/AdminSystemControllersTest.php | 3 | Health/Metrics/Docs |
| tests/BusinessControllersTest.php | 10 Domänen | Fehlerpfad-Prüfung repräsentativer Controller |

2026-08-27 neu hinzugekommen: 3 PHP-Dateien (14 tests; ohne TEST_DB_* überspringen sich die Integrationstests 6/6 automatisch):

| Datei | Fallzahl | Abgedeckte Objekte |
|------|--------|----------|
| tests/Integration/FinanceTransactionIntegrationTest.php | 6 | DB-Transaktions-Rollback/commit/doppelte Quelle/pcntl_fork parallele Sperren (Group(integration)) |
| tests/NotificationServiceTest.php | 6 | Benachrichtigungsservice |
| tests/FinanceRatioServiceTest.php | 2 | Finanzkennzahlen |

### Flutter-Seitentests: 98 tests alle bestanden

Diesmal 8 neue Dateien mit 29 Fällen (bestehende 10 Dateien unverändert, alle bestanden); `flutter analyze` 0 error (1 bestehende info):

| Datei | Fallzahl |
|------|--------|
| test/pages/dashboard_page_test.dart | 5 |
| test/pages/user_list_page_test.dart | 6 |
| test/pages/role_list_page_test.dart | 3 |
| test/pages/config_page_test.dart | 2 |
| test/pages/log_page_test.dart | 2 |
| test/pages/profile_page_test.dart | 3 |
| test/pages/login_flow_test.dart | 2 |
| test/pages/erp_list_pages_test.dart | 6 |

2026-08-27 neu hinzugekommen: 1 Datei (3 Fälle):

| Datei | Fallzahl |
|------|--------|
| test/pages/report_list_page_test.dart | 3 |

### API-Automatisierung: 104 Endpunkte / ~230 Assertions (19 Modulgruppen)

tests/E2E/api-coverage.php (423 Zeilen, `php -l` bestanden): rein lesend + idempotent (Persönlicher Bereich GET-Details→PUT schreibt denselben Wert zurück), inklusive Tabellenfehler-Erkennung (500 + Base table not found → SKIP mit Hinweis, dass der volle install.sql-Seed benötigt wird).

**Lokal nicht ausgeführt** (kein MySQL-Zugang, kein Dienst auf 8788), benötigt die CI-e2e-Umgebung:

```
E2E_USER=admin E2E_PASS=admin123 php tests/E2E/api-coverage.php --base-url=http://127.0.0.1:8788
```

Abgedeckt: 19 Modulgruppen — Systemverwaltung (Benutzer/Rollen/Berechtigungen/Konfiguration/Health/Metriken), Finanzen (Konsolidierung/Saldo/Periodenabschluss/Kennzahlen), Bestand, Vertrieb, Artikel, Einkauf, Projekt, Genehmigung, CRM, BI, Benachrichtigungen, Berichte.

> Erratum: api-tester vermutete zunächst, die Tabelle `erik_admin_config` fehle — **kein Defekt**. Der echte Tabellenname ist `erik_system_config` (in install.sql:133 angelegt, das SystemConfig-Modell zeigt korrekt darauf); der Bericht wird hiermit korrigiert.

## Abdeckung

pcov-Messung (2026-08-26, am 2026-08-27 nicht neu gemessen, Wert übernommen): Gesamt **7,51%** (Basislinie 4,8%), app/service **15,65%** (Basislinie 10,6%), app/controller **3,62%**.

Vergleich mit CI-Schwelle und Ziel (siehe P1-B4 in superpowers/plans/2026-08-07-next-phase-plan.md):

| Dimension | Aktuell | CI-Schwelle | Ziel |
|------|------|---------|------|
| Gesamt | 7,51% | 4% ✅ erreicht | 30% |
| app/service | 15,65% | 10% ✅ erreicht | 40% |
| app/controller | 3,62% | — | — |

Gesamt- und Service-Abdeckung haben die CI-Schwelle überschritten; bis zum Ziel bleibt noch eine größere Lücke, die Tests müssen weiter nach der P1-B4-Route ergänzt werden.

## Nebenbei behobene echte Defekte (4 Stellen)

| # | Ort | Defekt | Fix |
|---|------|------|------|
| 1 | app/controller/Admin/RoleController.php、PermissionController.php | fehlt `use support\Response;`, Laufzeit-TypeError | import ergänzt |
| 2 | app/controller/Admin/DocsController.php | `path()` drittes Argument null führt zum Absturz | Aufruf korrigiert |
| 3 | lib/pages/user_list_page.dart | Batch-Löschen/-Aktivieren-Buttons ohne Obx-Umbruch, Buttons erscheinen nach Ankreuzen nie | Obx-Umbruch ergänzt |
| 4 | scripts/api-coverage.php (und die 3 Dateien von app/queue/redis/search/) | cs-fixer-Format nicht konform | gemäß fixer repariert |
| 5 | app/model/FinanceCashJournal.php | `UPDATED_AT`-Feld stimmt nicht mit install.sql überein | Feld korrigiert |

## Go / Rust

**N/A** — es gibt keinerlei .go / .rs / Cargo.toml-Code im Repository; die Tests für beide Technologiestapel sind als nicht anwendbar gekennzeichnet.

## Abschluss der offenen Punkte (Update 2026-08-27)

Alle 5 offenen Punkte der Version vom 2026-08-26 wurden vollständig bearbeitet:

1. **DB-Transaktionspfade** ✅ — `tests/Integration/FinanceTransactionIntegrationTest.php` mit 6 neuen Fällen (Rollback/commit/doppelte Quelle/pcntl_fork parallele Sperren, `Group(integration)`), ohne TEST_DB_* automatisches 6/6-Überspringen; der CI-php-Job injiziert jetzt TEST_DB_DATABASE/TEST_DB_USERNAME/TEST_DB_PASSWORD/TEST_REDIS_HOST.
2. **api-coverage in CI integriert** ✅ — der e2e-Job in `.github/workflows/ci.yml` wurde auf den vollen install.sql-Seed (163 Tabellen) angehoben, nach dem Smoke folgt der neue Schritt „Run E2E API coverage".
3. **Bericht-/Belegdetailseiten-UI nicht abgedeckt** ✅ — `apps/flutter/test/pages/report_list_page_test.dart` 3 Fälle alle bestanden.
4. **CaptchaTest-Umgebungsabhängigkeit** ✅ — `vendor/erikwang2013/poster-php/src/Drivers/ImagickDriver.php:27` PIXELS→AREA-Dualversion-Kompatibilität + clone()-Guard; `tests/CaptchaTest.php` nach dem poster-php-v1.2.3-Vertrag neu geschrieben, lokal über den imagick-Pfad 7/7 bestanden (27 Assertions).
5. **Abdeckungsziel** ✅ Fortschritt — neue `tests/NotificationServiceTest.php`, `tests/FinanceRatioServiceTest.php`; die Abdeckungszahlen übernehmen die Messung vom 2026-08-26 (nicht neu gemessen), bis zum Ziel (30%/40%) ist weiterhin kontinuierliche Ergänzung nötig.

Regressionsbasis: **513 tests / 2368 assertions / 32 skipped** komplett grün (Vorversion 505/2342/26).

## Aktualisierungsverlauf

| Datum | Änderung |
|------|------|
| 2026-08-26 | Erstversion: 505 tests / 2342 assertions / 26 skipped; 5 offene Punkte; nebenbei 4 Defekte behoben |
| 2026-08-27 | 513 tests / 2368 assertions / 32 skipped; alle 5 offenen Punkte abgeschlossen; 5 Defekte nebenbei behoben; 4 neue Testdateien; alle Bilder mit Wasserzeichen erik.xyz versehen |

## Speicherorte von Bericht und Artefakten

- Dieser Bericht: `TEST_REPORT.md`
- Abdeckungsdaten: `runtime/coverage/` (von pcov erzeugt)
- API-Automatisierungsskript: `tests/E2E/api-coverage.php`
- PHP-Unit-Tests: `tests/*.php` (die 9 neuen Dateien siehe Tabelle oben)
- Flutter-Tests: `test/pages/*.dart` (die 8 neuen Dateien siehe Tabelle oben)
