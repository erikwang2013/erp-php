# Audit-Bericht — 2026-08-07

**Projekt**: erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select)
**Umfang**: Gesamter Laufzeittest, Tiefenprüfung, Behebung von P0/P1-Problemen
**Anweisung**: „Teste das Ganze, führe es aus, prüfe eingehend, ob es noch Probleme oder Optimierungsmöglichkeiten gibt?"
**Testergebnis**: OK (135 tests, 799 assertions) — alle bestanden

---

## 1. Test- und Laufzeitvalidierungsergebnisse

| Punkt | Ergebnis |
|---|---|
| Komplette PHPUnit-Suite | 135 tests / 799 assertions alle bestanden |
| Dienststart (Port 8787→temporär 8791) | normal gestartet, keine Prozessabstürze |
| /health-Healthcheck | code=0, database/redis/elasticsearch-Felder vollständig |
| Ratenbegrenzungs-Kette | /api/auth/login liefert bei aufeinanderfolgenden Requests 429 |
| JWT-Blacklist / Login-Sperre | funktionsfähig (nach Redis-Reparatur) |
| CS-Fixer | Formatierungsverstöße in 31 Dateien behoben |
| PHPStan | nach Reparatur des beschädigten Caches wieder lauffähig (851 False-Positives durch ORM-Magiemethoden, 75 veraltete Baseline-Einträge) |

---

## 2. P0-Reparaturen (Laufzeitfehler — alle behoben und verifiziert)

### 2.1 support\Redis-Klasse fehlt — Sicherheitsmechanismen versagen still

- **Symptom**: `support\Redis` existiert nicht (webman/redis wurde nie in composer.json aufgenommen), 9 Dateien referenzieren es.
- **Grundursache**: Mehrere `catch (\Throwable)`-fail-open-Designs verschluckten den fehlenden Klassenfehler, wodurch Ratenbegrenzung, JWT-Blacklist, Login-Sperre und IP-Sperre still versagten — die Schnittstellen „scheinen normal" zu sein, haben aber keinerlei Schutz.
- **Reparatur**: `composer require webman/redis`; `config/redis.php` auf Umgebungsvariablen umgestellt (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Verifizierung**: /health liefert `redis: ok`; Ratenbegrenzungstest liefert 429.

### 2.2 ApiVersion-Middleware-Kompilierungsfehler — alle /api-Routen 500

- **Symptom**: `Interface "app\middleware\MiddlewareInterface" not found` — `use Webman\MiddlewareInterface;` fehlt.
- **Zweitfehler nach Reparatur**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` ist eine Unterklasse von `Webman\Http\Request`, was den Kontravarianz-Vertrag der Parameter verletzt.
- **Reparatur**: Importe auf `Webman\Http\Request` / `Webman\Http\Response` umgestellt.

### 2.3 AdminAuth-Middleware-Parametervarianz — /admin-Routen crashen Worker

- **Symptom**: /admin/dashboard löst Empty reply des Workers aus (Kompilierungsabsturz).
- **Grundursache**: dasselbe Parametervarianz-Problem wie 2.2.
- **Reparatur**: Importe auf `Webman\Http\Request` / `Webman\Http\Response` umgestellt (`support\Redis` beibehalten).
- **Verifizierung**: liefert 401 JSON.

### 2.4 Hilfsfunktion validator() existiert nicht — Login 500

- **Symptom**: `Call to undefined function validator()`, 105 Aufrufstellen in 99 Dateien.
- **Reparatur**: `composer require illuminate/validation`; Hilfsfunktion in `app/functions.php` implementiert (statischer $factory-Cache).
- **Stolperfalle**: Der erste Parameter von `Factory::__construct()` muss ein `Translator` sein, kein `ArrayLoader`.
- **Nachzügler (P2)**: Fehlermeldungen nicht übersetzt (zeigt `validation.required` statt Chinesisch), zh_CN-Sprachpaket erforderlich.

### 2.5 CORS hartcodiert + Preflight-Response ohne CORS-Header

- **Reparatur**: Neues `app/common/CorsPolicy.php`, liest die Whitelist aus der Umgebungsvariable `CORS_ALLOWED_ORIGIN` (kommagetrennt), Origin-Echo; bei keinem Treffer werden keine CORS-Header gesendet.
- **Kernpunkt**: `Route::fallback` durchläuft die globale Middleware-Kette nicht — die OPTIONS-Preflight-Response muss die CORS-Header selbst anhängen; im fallback-Closure behandelt.
- **Sicherheitsheader**: Veraltetes X-XSS-Protection entfernt; CSP um `connect-src 'self'` erweitert.

### 2.6 FastRoute BadRouteException — Routen-Überschattung

- **Symptom**: `Static route "/install" is shadowed by previously defined variable route`.
- **Grundursache**: Die OPTIONS-Wildcard-Route `/{path:.+}` überschattet nachgelagerte statische Routen; Plugin-Routen (apidoc) werden nach config/route.php geladen.
- **Reparatur**: Wildcard-Route entfernt, stattdessen `Route::fallback` verwendet (muss am Ende der Routendatei stehen); `/crm/pool/rules` von resource auf explizite GET-Route umgestellt, `PoolController::rules()` auf public geändert.

---

## 3. P1-Reparaturen (Engineering-Qualität)

- **3.1 PHPStan-Cache beschädigt**: /tmp/phpstan/cache stammt aus dem gelöschten service/-Verzeichnis (Rest der Mikroservice-Aufteilung) und enthält alte absolute Pfade, die phar-Fehler und CPU-0%-Hänger verursachen. Nach Cache-Löschung und Neuinstallation wieder funktionsfähig. 851 Fehler sind False-Positives durch webman-ORM-Magiemethoden; 75 Baseline-Pfade zeigen auf ein nicht existierendes service/-Verzeichnis (P2).
- **3.2 CS-Fixer**: Verstöße bei Leerzeichen/use-Sortierung in 31 Dateien behoben.
- **3.3 Tests synchronisiert**: `test_cors_response_is_assigned_correctly` auf die neue Implementierung aktualisiert (withHeaders + CorsPolicy).

---

## 4. Grundursachen, die der vorherige Audit (08-04) übersehen hat

- Die Tests deckten die **Ladbarheit der Middleware-Klassen** und **Aufrufbarkeit der Routen** nicht ab (class_exists / is_subclass_of können fehlende use-Imports und Parametervarianz nicht erkennen).
- Der Commit b1fe2de behauptete CORS/X-XSS-Reparaturen, die nicht dem tatsächlichen Code entsprachen — die Audit-Schlussfolgerung stützte sich zu sehr auf Commit-Informationen statt auf Laufzeitvalidierung.

---

## 5. Änderungsliste dieser Runde (git status: 41 geändert + 2 neu)

| Datei | Änderung |
|---|---|
| app/middleware/ApiVersion.php | use Webman\MiddlewareInterface ergänzt; Parametertypen auf Webman\Http umgestellt |
| app/middleware/AdminAuth.php | Parametertypen auf Webman\Http umgestellt |
| app/middleware/Cors.php | auf CorsPolicy umgebaut; CSP/Sicherheitsheader aktualisiert |
| app/common/CorsPolicy.php | **neu**: CORS-Whitelist-Strategie |
| config/route.php | Fallback-Route + /crm/pool/rules-Korrektur |
| app/controller/crm/PoolController.php | rules() auf public geändert |
| app/functions.php | Hilfsfunktion validator() neu |
| config/redis.php | **neu** (nach composer-Generierung umgebungsvariablisiert) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS-Assertions synchronisiert |
| übrige ~30 Dateien | CS-Fixer-Formatierungsreparaturen |

---

## 6. P2-Empfehlungen (Umgebung/offene Punkte, nicht behoben)

1. **.env DB_PASSWORD leer** — MySQL-root-Authentifizierung schlägt fehl, `database: unavailable`; echtes Passwort konfigurieren.
2. **Port-8787-Konflikt** — von cloud-php/service belegt (anderes Projekt); in der Produktionsbereitstellung unterscheiden.
3. **validator chinesische Fehlermeldungen** — Sprachpaket installieren oder eigene messages definieren.
4. **PHPStan-Baseline neu aufbauen** — 75 Pfade zeigen auf das gelöschte service/-Verzeichnis, Bereinigung und Neuaufbau empfohlen.
5. **fail-open-Audit** — Empfehlung: global nach still verschluckten `catch (\Throwable)`-Fehlerstellen suchen (in dieser Runde wurde 1 Stelle mit schwerwiegender Auswirkung gefunden), auf fail-closed oder explizite Protokollierung umstellen.

---

*Bericht erstellt: 2026-08-07, Dienst gestoppt, Port auf 8787 zurückgesetzt.*
