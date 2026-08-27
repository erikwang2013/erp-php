# Dokument zur Sicherheitsarchitektur

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Panorama der abgestuften Verteidigung

Das System verwendet ein 7-Schichten-Modell der abgestuften Verteidigung (Defense in Depth), das böswillige Anfragen von außen nach innen Schicht für Schicht filtert und sicherstellt, dass bei Ausfall einer beliebigen einzelnen Schicht weiterhin nachgelagerte Verteidigungslinien greifen.

Die gesamte Middleware-Kette wird in folgender Reihenfolge ausgeführt (siehe `config/middleware.php`):

```
Request → Cors → SecurityFilter → RateLimit → [Routengruppen-Middleware: AdminAuth → AdminPermission → OperationLog] → Controller
```

| Schicht | Middleware/Mechanismus | Schutzziele |
|----|--------|---------|
| 1 | SecurityFilter | Blockierung von XSS / SQL-Injection / Pfad-Traversal / Command-Injection / CSRF-Angriffen |
| 2 | Cors | Cross-Origin-Sicherheit + Injektion von Sicherheits-Response-Headern |
| 3 | RateLimit | Redis-Sliding-Window-Ratenbegrenzung, verhindert Brute-Force |
| 4 | AdminAuth | JWT-Authentifizierung + Blacklist-Logout |
| 5 | AdminPermission | RBAC-Autorisierung mit method.path-Granularität |
| 6 | OperationLog | Betriebsprüfung + Quellgerät-Verfolgung |
| 7 | Datenverschlüsselung | Hashids-ID-Verschleierung + Encryptable-DB-Verschlüsselung + EncryptionService-Übertragungsverschlüsselung |

Die Frontend-Ebenen (Flutter) verfügen zusätzlich über eine unabhängige Eingabevalidierung; das Backend vertraut ihnen nicht, jede Ebene verteidigt unabhängig.

---

## 2. Angriffserkennungs-Engine

### 2.0 HTTP-Methodenbeschränkung

SecurityFilter prüft vor allen Angriffserkennungen zuerst die HTTP-Methode und erlaubt nur die folgenden Standardmethoden:

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Nicht standardmäßige Methoden (z. B. TRACE, CONNECT, PATCH, benutzerdefinierte Methoden usw.) liefern direkt **405 Method Not Allowed** mit leerem HTML-Response-Body zurück und gelangen nicht in die nachgelagerte Angriffserkennung oder Geschäftslogik.

Dies ist die erste Verteidigungslinie der abgestuften Verteidigung und blockiert wirksam:
- TRACE Cross-Site-Tracing-Angriffe (XST)
- Missbrauch von CONNECT-Tunnel-Proxys
- Sondierung nicht standardmäßiger WebDAV-Methoden
- HTTP-Methoden-Enumeration durch automatisierte Scanner

### 2.1 XSS Cross-Site-Scripting

Alle regulären Ausdrücke stammen aus `SecurityFilter::PATTERNS['XSS']`, Abgleich ohne Beachtung der Groß-/Kleinschreibung.

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Skript-Tags | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` usw. mit Leerzeichen-Varianten |
| Ereignisattribute | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | Inline-Ereignisse wie `onclick="javascript:..."` |
| JS-Pseudoprotokoll | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` usw. |
| Data-URI-XSS | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` usw. |
| Template-Injection | `\{\{.*?\}\}` | `{{constructor}}`, `{{7*7}}` Server-/Angular-/Vue-Template-Injection |

### 2.2 SQL-Injection

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| UNION-Combine-Query | `\bUNION\s+(?:ALL\s+)?SELECT\b` | `UNION SELECT`, `UNION ALL SELECT` Datenbank-Dump |
| OR-Immer-wahr-Injection | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Tabellenstruktur-Zerstörung | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Stored-Procedure-Aufrufe | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | MSSQL-Erweiterungs-Stored-Procedure-Befehlsausführung |
| Metadaten-Sondierung | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | MySQL/PG/SQLite/MSSQL-Datenbankstruktur-Sondierung |
| Kommentar-Umgehung | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | `'-- OR SELECT`, `'# AND UPDATE` Kommentar-Umgehung |

### 2.3 Pfad-Traversal

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Verzeichnis-Rückverfolgung | `\.\.[\/\\\\]{2,}` | `../`, `..\`, `....//` mehrstufige Verzeichnis-Rückverfolgung |
| Sondierung sensibler Dateien | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` usw. |
| Null-Byte-Abschneidung | `%00` | `../../../etc/passwd%00.jpg` Umgehung der Endungsprüfung |

### 2.4 Command-Injection

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Pipe/Semikolon-Befehle | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Backtick-Substitution | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| $()-Substitution | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Remote-Download-Pipe | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF Cross-Site-Request-Forgery

Die Prüflogik ist in `SecurityFilter::checkCsrf()` implementiert:

```php
// Nur POST/PUT/DELETE lösen die Prüfung aus
// Origin-Header und Referer beide leer → durchlassen (Nicht-Browser-Clients)
// Origin nicht leer → Origin-Domain mit Host vergleichen
```

Vergleichsregeln:
- Präfix `www.` des Host entfernen, dann exakter Vergleich mit der Origin-Domain
- Ist der Host eine Parent-Domain der Origin (z. B. `Origin: app.example.com`, `Host: example.com` — löst `str_contains($originHost, '.' . $hostOnly)` aus), durchlassen
- Weder exakte Übereinstimmung noch Subdomain → 403 zurückgeben, als CSRF-Angriff bewertet

Hinweis: Nicht-Browser-Clients (z. B. curl ohne Origin/Referer) werden direkt durchgelassen; der CSRF-Schutz wirkt nur in Browser-Umgebungen.

### 2.6 Böswillige Datei-Uploads

| Erkennungsmuster | Regulärer Ausdruck | Abgewehrte Angriffe |
|----------|------|-----------|
| Doppel-Endungs-Tarnung | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | `shell.php.png`, `shell.phar.jpg` Umgehung der Whitelist |
| PHP-Endung | `\.php\s*$/m` | Direkte Übergabe von `.php`-Pfaden in Request-Parametern |

---

## 3. Angriffseskalation und IP-Blacklist

SecurityFilter verfügt über einen eingebauten Angriffseskalationsmechanismus, der fortlaufende Scan-Angriffe derselben IP verhindert.

### Eskalationsablauf

```
1. Treffer des Scans → Redis INCR security_escalate:{ip} = 1, TTL=60s
2. Treffer des Scans → INCR → 2
...
5. Treffer des Scans → INCR → 5
    → Sperre auslösen: SETEX security_ban:{ip} 900 1
    → Zähler löschen: DEL security_escalate:{ip}
    → Sicherheitsprotokoll schreiben: [SECURITY] IP banned 15min
```

### Verhalten während der Sperre

Jede Anfrage prüft beim Eintritt in den SecurityFilter zuerst `isBanned()`:

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Alle Anfragen einer gesperrten IP (auch legitime) liefern innerhalb von 15 Minuten direkt 403 zurück und überspringen die gesamte nachgelagerte Geschäftslogik.

### Konfigurationskonstanten

| Konstante | Wert | Bedeutung |
|------|-----|------|
| ESCALATE_LIMIT | 5 | Auslöse-Schwellwert innerhalb des 60-Sekunden-Fensters |
| ESCALATE_WINDOW | 60 | Zählerfenster (Sekunden) |
| BAN_DURATION | 900 | Dauer der Blacklist (Sekunden), d. h. 15 Minuten |

### Sicherheitsprotokoll

Dateiposition: `runtime/logs/security.log`

Beispiel für das Protokollformat:
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Begrenzung der Request-Body-Größe

`Content-Length > 10MB` liefert direkt 413 Payload Too Large zurück — Schutz vor DoS-Angriffen mit übermäßig großen Request-Bodies.

### Content-Type-Prüfung

POST/PUT-Requests **müssen** `Content-Type` als `application/json` oder `application/x-www-form-urlencoded` deklarieren, andernfalls wird 415 Unsupported Media Type zurückgegeben. Datei-Upload-Requests (mit file-Feld) überspringen diese Prüfung.

---

## 4. Sicherheits-Response-Header

Alle Header werden in der `Cors`-Middleware injiziert und über `$response->withHeaders()` an jede Response angehängt.

| Header | Wert | Wirkung |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | Erlaubt Cross-Origin von beliebigen Quellen (Intranet-Verwaltungsoberflächen-Szenario) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | Zulässige Methodenmenge |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | Zulässige benutzerdefinierte Header |
| Access-Control-Max-Age | `86400` | Cache der Preflight-Requests 24 Stunden |
| X-Content-Type-Options | `nosniff` | Verbietet MIME-Sniffing des Browsers |
| X-Frame-Options | `DENY` | Verbietet jede iframe-Einbettung, schützt vor Clickjacking |
| X-XSS-Protection | `1; mode=block` | Aktiviert den eingebauten XSS-Filter des Browsers und blockiert das Seitenrendering |
| Referrer-Policy | `strict-origin-when-cross-origin` | Gleiche Quelle sendet vollständige URL, Cross-Origin nur die Domain |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | Deaktiviert Kamera/Mikrofon/Standort-APIs siteweit |

OPTIONS-Preflight-Requests liefern direkt eine leere 204-Response zurück und betreten die nachgelagerte Middleware-Kette nicht.

### 4.2 Content-Security-Policy (CSP)

Wird zusammen mit den anderen Sicherheitsheadern in der Cors-Middleware injiziert und bietet abgestufte Verteidigung, indem die Ressourcenquellen begrenzt werden, die der Browser laden und ausführen darf.

| Header | Wert | Wirkung |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | Begrenzt die Quellen von Skripten/Styles/Bildern/Verbindungen/Frames/Formularen usw. |
| X-Permitted-Cross-Domain-Policies | `none` | Verbietet das Laden von Cross-Domain-Policy-Dateien für Adobe Flash/PDF usw. |

Kernpunkte der CSP-Policy:
- `default-src 'self'`: Standardmäßig nur gleichnamige Ressourcen erlaubt
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'`: Erlaubt gleichnamige Skripte + Inline-Skripte (für Flutter Web erforderlich) + eval (für Flutter-Web-Debugging erforderlich)
- `frame-ancestors 'none'`: Verbietet iframe-Einbettung durch beliebige Seiten — doppelte Absicherung mit X-Frame-Options: DENY
- `base-uri 'self'`: Begrenzt `<base>`-Tags auf gleichnamige Quellen
- `form-action 'self'`: Begrenzt Formular-Submissions auf gleichnamige Quellen

---

## 5. Ratenbegrenzungs-Strategie

### Algorithmus

Redis Sorted Set Sliding Window + Lua-atomares Skript, Schlüsseloperationen:

```lua
-- 1. Alte Einträge außerhalb des Fensters aufräumen
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Aktuelle Fensterzählung prüfen
local count = redis.call('ZCARD', KEYS[1])
-- 3. Bei Überschreitung {0, count} zurückgeben, sonst ZADD und {1, count+1} zurückgeben
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- Zufalls-Suffix verhindert Überschreiben in derselben Millisekunde
redis.call('EXPIRE', KEYS[1], window + 10)
```

Das Lua-Skript wird serverseitig in Redis single-threaded ausgeführt — **von Natur aus atomar**, wodurch die TOCTOU-Race-Condition (Time-of-check to Time-of-use) eliminiert wird.

### Ratenbegrenzungs-Konfiguration

| Route | Limit | Fenster | Szenario |
|------|------|------|------|
| Standard (alle Routen) | 60 Anfragen/Minute | 60s | Allgemeine API |
| `/api/auth/login` | 10 Anfragen/Minute | 60s | Login (Schutz vor Brute-Force) |
| `/api/auth/register` | 5 Anfragen/Minute | 60s | Registrierung (Schutz vor Massenregistrierung; standardmäßig deaktiviert, erfordert `REGISTRATION_ENABLED=1`) |

### Response-Header

Bei ausgelöster Ratenbegrenzung wird HTTP 429 mit JSON-Body zurückgegeben:
```json
{"code": 429, "message": "请求过于频繁，请稍后再试", "data": []}
```

Alle Responses (auch normale) tragen die folgenden Header:

| Header | Beschreibung |
|----|------|
| X-RateLimit-Limit | Maximale Anzahl erlaubter Requests im aktuellen Fenster |
| X-RateLimit-Remaining | Verbleibende verfügbare Requests im aktuellen Fenster |
| X-RateLimit-Reset | Unix-Zeitstempel der Fensterzurücksetzung |
| Retry-After | Nur bei Ratenbegrenzung enthalten, empfohlene Wartezeit in Sekunden |

### Degradationsstrategie

Bei Redis-Störungen (Verbindungs-Timeout, nicht verfügbar usw.) gilt **fail-open**:

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, alle Anfragen durchlassen
}
```

Besser kurzzeitig auf den Ratenbegrenzungsschutz verzichten, als normale Geschäftsanfragen zu blockieren.

### 5.4 Kontosperrmechanismus

Die Login-Schnittstelle hat zusätzlich zur Ratenbegrenzung einen **Kontosperrmechanismus**, der gezieltes Brute-Force gegen bestimmte Benutzer verhindert.

**Sperrablauf**:

```
Login-Fehler → Redis INCR account_lockout:{userId} TTL=900s
5 aufeinanderfolgende Fehler → Redis SETEX account_locked:{userId} 900 1
            → 429 "账号已被锁定，请15分钟后再试" zurückgeben
            → Zähler löschen: DEL account_lockout:{userId}
```

**Verhalten während der Sperre**:

Während der Sperre liefern alle Login-Anfragen direkt 429 zurück, ohne Passwortprüfung — Brute-Force-Versuche werden vollständig blockiert.

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | Maximale Anzahl aufeinanderfolgender Fehlversuche |
| LOCKOUT_DURATION | 900 | Sperrdauer (Sekunden), d. h. 15 Minuten |

Hinweis: Die Kontosperre basiert auf `userId` statt auf der IP; ein IP-Wechsel des Angreifers kann die Sperre daher nicht umgehen. Zusammen mit der IP-Ratenbegrenzung (10 Anfragen/Minute) ergibt sich ein doppelter Schutz:
- IP-Ebene: 10 Anfragen/Minute-Ratenbegrenzung blockiert verteiltes Brute-Force
- Kontenebene: Sperre nach 5 Fehlversuchen blockiert gezieltes Brute-Force

---

## 6. Authentifizierung und Autorisierung

### 6.1 JWT-Authentifizierung

Implementiert in der AdminAuth-Middleware, eingebunden in die Routengruppen, die Authentifizierung erfordern.

**Parameterkonfiguration** (`config/plugin/erikwang2013/jwt/jwt`, per `.env` injiziert):

| Parameter | Wert | Beschreibung |
|------|-----|------|
| Algorithmus | HS256 | Symmetrische HMAC-SHA256-Signatur |
| Schlüssel | `JWT_SECRET` | Per Umgebungsvariable injiziert, in Produktion zu ändern |
| access_token TTL | 7200s (2h) | `JWT_TTL` |
| refresh_token TTL | 1209600s (14d) | `JWT_REFRESH_TTL` |
| Aussteller | `open-admin` | `JWT_ISSUER` |
| Zielgruppe | `open-admin` | `JWT_AUDIENCE` |

**Token-Extraktion**: Aus dem Header `Authorization: Bearer <token>` extrahieren, Präfix `Bearer ` entfernen, um das rohe JWT zu erhalten.

**Authentifizierungsablauf**:
1. Leeres Token → direkt 401 `{"code": 401, "message": "未登录"}`
2. Redis-Blacklist `jwt_blacklist:{md5(token)}` prüfen → Treffer → 401 `Token已失效，请重新登录`
3. JWT-Dekodierung → Fehler (abgelaufen/Signatur stimmt nicht) → 401 `Token已过期或无效`
4. Erfolg → `$request->adminId` und `$request->adminUsername` injizieren

**Blacklist-Mechanismus**: Beim Logout wird `md5(token)` in Redis geschrieben, TTL wird auf die verbleibende JWT-Gültigkeitsdauer gesetzt. Bei Redis-Ausfall wird die Blacklist-Prüfung übersprungen (fail-open); dann bleibt ein ausgeloggtes Token kurzzeitig verwendbar, aber die kurze JWT-Gültigkeitsdauer (2h) selbst dient als Fallback-Schutz.

### 6.2 Limitierung gleichzeitiger Sitzungen

Um Missbrauch eines geleakten Tokens auf mehreren Geräten zu verhindern, begrenzt das System die Anzahl der gleichzeitig gültigen Token desselben Benutzers.

**Limitierungslogik**:

```
Login erfolgreich → neues Token ausstellen
         → Anzahl gültiger Token des aktuellen Benutzers abfragen: Redis SCARD user_tokens:{userId}
         → Ist die Anzahl >= 3 (MAX_CONCURRENT_SESSIONS):
            → Nach Erstellungszeitpunkt aufsteigend sortieren, ältestes Token entfernen:
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → Neues Token zur Menge hinzufügen: Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Konfigurationskonstanten**:

| Konstante | Wert | Bedeutung |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | Maximale Anzahl gleichzeitiger Token pro Benutzer |

**Abgemeldet-Szenario**: Beim Login auf einem 4. Gerät wird das Token des 1. Geräts erzwungen auf die Blacklist gesetzt; Folgeanfragen liefern 401 "Token已失效，请重新登录".

Beim Logout wird das aktuelle Token aus der Menge entfernt. Läuft ein Token natürlich ab, verfällt der Redis-Key automatisch und die Mengenmitglieder reduzieren sich entsprechend.

### 6.3 RBAC-Berechtigungsmodell

Implementiert in der AdminPermission-Middleware.

**Datenmodell**: Drei-Ebenen-Verknüpfung User -> Role -> Permission

- `erik_admin_user` (Benutzertabelle)
- `erik_admin_user_role` (Benutzer-Rollen-Verknüpfungstabelle)
- `erik_admin_role` (Rollentabelle)
- `erik_admin_role_permission` (Rollen-Berechtigungs-Verknüpfungstabelle)
- `erik_admin_permission` (Berechtigungstabelle)

**Berechtigungstypen**:
| type | Bedeutung | Beispiel |
|------|------|------|
| 1 | Menü-Berechtigung | steuert die Sichtbarkeit der linken Navigation |
| 2 | Button-Berechtigung | steuert Aktionsbuttons auf der Seite (Anlegen/Bearbeiten/Löschen) |
| 3 | API-Berechtigung | steuert Backend-Schnittstellenaufrufe |

Format der API-Berechtigungs-Kennung: `{method}.{path}`

Zum Beispiel:
- `post.admin/user` — Benutzer anlegen
- `put.admin/user` — Benutzer bearbeiten
- `delete.admin/user` — Benutzer löschen
- `get.admin/user` — Benutzerliste anzeigen

**Autorisierungsablauf**:
1. `$request->adminId` leer → durchlassen (Route ohne Authentifizierungs-Vorstufe konfiguriert)
2. Benutzer → Rollen (deaktivierte Rollen mit `status=0` überspringen) → Berechtigungsliste abrufen
3. Superadministrator (`slug = '*'`) → direkt durchlassen
4. `strtolower(method) . '.' . trim(path, '/')` konstruieren → mit der Berechtigungsliste vergleichen
5. Keine Übereinstimmung → 403 `{"code": 403, "message": "无权限访问"}`

**Zweitbestätigung**: BaseController bietet die Methode `confirmPassword()`, sensible Operationen (Benutzer löschen, Datenexport usw.) verlangen auf Controller-Ebene zusätzlich die Eingabe des aktuellen Passworts, um unbefugte Operationen nach einer Session-Übernahme zu verhindern.

---

## 7. Audit-Protokolle

### 7.1 Betriebsprotokoll

Die OperationLog-Middleware zeichnet für POST / PUT / DELETE-Requests automatisch Betriebsprotokolle auf. GET-Requests werden nicht aufgezeichnet.

**Aufgezeichnete Felder**:

| Feld | Quelle | Beschreibung |
|------|------|------|
| id | SnowflakeService::generate() | Globale eindeutige ID |
| user_id | `$request->adminId` | ID des Ausführenden, 0 bei nicht eingeloggt |
| action | `$request->method()` | entspricht method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | Request-Pfad |
| ip | `$request->getRealIp()` | Echte Client-IP |
| source | detectSource() | Client-Quellplattform |
| input | Request-Body (maskiertes JSON) | Vom Vorgang übermittelte Daten |
| created_at | `date('Y-m-d H:i:s')` | Zeitpunkt der Operation |

**Filterung sensibler Felder**: Der Request-Body wird rekursiv durchlaufen, die Werte folgender Felder werden durch `***` ersetzt:

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Quellgerät-Erkennung** (`detectSource()`): nach Priorität:

1. Zuerst den benutzerdefinierten Header `X-Client-Platform` lesen (explizite Deklaration nativer Clients)
2. Fallback auf Ableitung aus dem User-Agent-String (Erkennungsreihenfolge der Methode `detectSource()`):

| Plattform | UA-Schlüsselwörter |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | Fallback-Standardwert |

**Fehlertoleranz**: Eine Ausnahme beim Protokollschreiben blockiert die Geschäftsanfrage nicht (`catch (\Throwable)` still verschluckt).

### 7.2 Sicherheitsprotokoll

**Dateiposition**: `runtime/logs/security.log`

**Aufgezeichneter Inhalt**:
- Blockierungsprotokoll von Angriffen: Angriffskategorie, IP, Pfad, Feld, Quelle, Payload-Ausschnitt (erste 200 Zeichen)
- IP-Sperrbenachrichtigung: gesperrte IP, Anzahl der Auslöser

Die Protokollberechtigung ist `FILE_APPEND | LOCK_EX`, was eine nebenläufigkeitssichere Schreibweise gewährleistet.

---

## 8. Datenschutz

Das System verfolgt eine Drei-Ebenen-Datenschutzstrategie, die den drei Phasen des Datenflusses entspricht.

### 8.1 Übertragungsschicht — EncryptionService

`EncryptionService` verwendet das Paket `erikwang2013/encryption` zur Ver- und Entschlüsselung sensibler Felder in API-Requests/-Responses.

**Technische Details**:
- Algorithmus: `aes-256-cbc-hmac` (mit eingebauter HMAC-Signatur gegen Manipulation)
- Schlüssel: Umgebungsvariable `ENCRYPTION_KEY`, automatisch auf 32 Byte ausgerichtet
- Verwendung: Übertragung von Feldern wie Telefonnummer und Personalausweisnummer zwischen Client und API

**Maskierungshilfsmethoden**:
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (Benutzername über 2 Zeichen) oder `a**@example.com`

### 8.2 Speicherschicht — Encryptable Cast

Das `AdminUser`-Model verwendet den Eloquent-Cast `Erikwang2013\Encryptable\Encryptable`, für folgende Felder:

- `email` → Cast auf Encryptable, automatische Ver-/Entschlüsselung
- `phone` → Cast auf Encryptable, automatische Ver-/Entschlüsselung
- `id_card` → Cast auf Encryptable, automatische Ver-/Entschlüsselung

Beim Schreiben in die Datenbank automatisch als Chiffretext verschlüsselt, beim Lesen automatisch als Klartext entschlüsselt. Der Speicherspaltentyp in der Datenbank ist `VARCHAR(500)`, der Chiffretext wird als base64 gespeichert.

**Schlüsselsystem**: Unabhängig von der Übertragungsschicht-Verschlüsselung (`ENCRYPTION_KEY`) wird `ENCRYPTABLE_KEY` verwendet; ein kompromittierter Schlüssel führt nicht zum Versagen der anderen Schicht.

Schlüsselrotation: Die Umgebungsvariable `ENCRYPTION_PREVIOUS_KEYS` unterstützt eine Liste historischer Schlüssel (kommagetrennt); beim Lesen alter Daten wird der Entschlüsselungsversuch mit historischen Schlüsseln unternommen, beim Zurückschreiben wird mit dem aktuellen Schlüssel neu verschlüsselt.

### 8.3 Anzeigeschicht — ID-Verschleierung und Maskierung

**Hashids-ID-Verschleierung**: `HashidsService` verwendet das Paket `erikwang2013/hashids`.

- Die von der externen API zurückgegebenen Datenbank-BIGINT-IDs werden als Hash-Strings codiert (z. B. `xK3mN9qR2pL7wV8b`)
- Der Client übermittelt den Hash-String in Requests, das Backend dekodiert automatisch zur Original-ID
- Salt-Wert wird über die Umgebungsvariable `HASHIDS_SALT` injiziert; unterschiedliche Salts ergeben vollständig unterschiedliche Codier-/Decodierergebnisse
- Minimale Hash-Länge 16 Zeichen, Zeichensatz mit 62 alphanumerischen Zeichen
- BaseController bietet die Komfortmethoden `encodeId()`, `decodeId()`, `encodeIds()`

**Export-Maskierung**: Beim Excel/PDF-Export (ExportController) werden sensible Felder einheitlich maskiert:
- Telefonnummer: `138****1234`
- E-Mail: `a***@example.com`
- Personalausweisnummer: vollständig überdeckt als `********`

---

## 9. Schlüsselverwaltung

Alle Schlüssel werden über Umgebungsvariablen in `.env` injiziert; die Konfigurationsdateien lesen mit `getenv()` und besitzen eingebaute Fallback-Standardwerte (nur in Entwicklungsumgebungen sicher).

| Umgebungsvariable | Verwendung | Paket | Produktionsanforderung |
|----------|------|-----|---------|
| JWT_SECRET | JWT-Signaturschlüssel | erikwang2013/jwt-webman | Zufallsstring mit 64+ Zeichen |
| JWT_ALGORITHM | JWT-Signaturalgorithmus | wie oben | HS256 beibehalten |
| HASHIDS_SALT | Salt für ID-Codierung | erikwang2013/hashids | Zufallsstring |
| SNOWFLAKE_DATACENTER_ID | Rechenzentrums-ID (0-31) | erikwang2013/snowflake-php | bei Einzelstandort Standard beibehalten |
| ENCRYPTION_KEY | Verschlüsselungsschlüssel der API-Übertragungsschicht | erikwang2013/encryption | Zufallsstring mit 32 Byte |
| ENCRYPTABLE_KEY | Verschlüsselungsschlüssel der DB-Speicherschicht | erikwang2013/encryptable | Zufallsstring mit 32 Byte, verschieden vom Übertragungsschlüssel |

**Sicherheitsanforderungen**:
- Die Datei `.env` ist in `.gitignore` aufgenommen, eine Einreichung ins Versionsverwaltungs-Repository ist streng verboten
- `.env.example` ist eine öffentliche Vorlagendatei und enthält keine echten Schlüssel
- In Produktion **müssen** alle Standard-Schlüssel durch Zufallsstrings ersetzt werden
- Empfehlung: Schlüssel mit `openssl rand -base64 32` generieren

### Isolation der Schlüsselspeicherung

| Schicht | Konfigurationsschlüssel | Schlüssel-Umgebungsvariable |
|----|--------|-------------|
| Übertragungsverschlüsselung | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Speicherverschlüsselung | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| ID-Verschleierung | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| JWT-Signatur | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Das System stellt unter `/.well-known/security.txt` einen RFC-9116-konformen Endpunkt für Sicherheitskontaktinformationen bereit, damit Sicherheitsforscher beim Auffinden von Schwachstellen schnell einen Meldekanal finden.

**Zugriff**:

```
GET /.well-known/security.txt
```

**Response-Inhalt**:

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Feldbeschreibung**:

| Feld | Beschreibung |
|------|------|
| Contact | Kontakt für die Meldung von Sicherheitsschwachstellen |
| Expires | Ablaufzeit der Datei, regelmäßig zu aktualisieren |
| Preferred-Languages | Bevorzugte Kommunikationssprachen |
| Canonical | Kanonische URL dieser Datei |
| Policy | Link zur Sicherheitsrichtlinie/Offenlegungsrichtlinie für Schwachstellen |

Dieser Endpunkt unterliegt keinen Middleware-Einschränkungen wie Ratenbegrenzung oder Authentifizierung; jeder kann direkt darauf zugreifen.

---

## 11. Nginx-Sicherheitskonfiguration

Das Projekt stellt `docs/nginx-security.conf` als Referenzkonfiguration zur Sicherheitshärtung des Nginx-Reverse-Proxys in Produktionsumgebungen bereit.

**Enthaltene Sicherheitsmaßnahmen**:

| Konfigurationselement | Wirkung |
|--------|------|
| `server_tokens off` | versteckt die Nginx-Versionsnummer |
| `client_max_body_size 10m` | begrenzt die Request-Body-Größe, wirkt mit SecurityFilter zusammen |
| `limit_req_zone` | Anfragefrequenzbegrenzung auf Nginx-Ebene |
| `limit_conn_zone` | Begrenzung gleichzeitiger Verbindungen |
| `add_header`-Sicherheitsheader | fügt auf Nginx-Ebene Sicherheitsheader wie X-XSS-Protection hinzu |
| `if ($request_method)` | lehnt nicht standardmäßige HTTP-Methoden auf Nginx-Ebene ab |
| SSL/TLS-Konfiguration | moderne TLS-1.2/1.3-Konfiguration, schwache Cipher-Suiten deaktiviert |
| Verstecken von Backend-Headern | `proxy_hide_header` entfernt sensible Header wie die webman-Version |

**Verwendung**: Die Konfiguration aus `docs/nginx-security.conf` in Ihren Nginx-Server-Block übernehmen und an die tatsächliche Domain und Zertifikatspfade anpassen.

---

## 12. Bedrohungsmodell

### 12.1 Abgewehrte Bedrohungen

| Bedrohungstyp | Angriffsvektor | Verteidigungsebenen |
|----------|---------|---------|
| Missbrauch von HTTP-Methoden | TRACE/TRACK-XST-Angriffe, CONNECT-Tunnel-Proxys, WebDAV-Methodensondierung | SecurityFilter 405-Methoden-Whitelist (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Gezieltes Brute-Force | wiederholte Passwortversuche gegen bestimmte Benutzer | Kontosperre (nach 5 Fehlversuchen 15 Minuten) + RateLimit (Login 10/min) + Captcha |
| Brute-Force | verteilte IPs versuchen wiederholt Benutzername/Passwort | RateLimit (Login 10/min) + Captcha |
| XSS Cross-Site-Scripting | `<script>`, onerror, javascript: | SecurityFilter (5 Muster) + X-XSS-Protection-Response-Header + CSP |
| SQL-Injection | UNION SELECT, OR 1=1, Kommentar-Umgehung | SecurityFilter (6 Muster) + parameterisierte Eloquent-ORM-Abfragen |
| CSRF Cross-Site-Request-Forgery | böswillige Websites senden Requests im Namen des Benutzers | Origin/Referer-Prüfung im SecurityFilter |
| Pfad-Traversal | `../../etc/passwd` | Pfad-Traversal-Muster im SecurityFilter + Endungs-Whitelist im UploadController |
| Command-Injection | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 Muster) |
| Session-Hijacking | Diebstahl von JWT-Tokens | kurze JWT-Gültigkeit (2h) + Blacklist-Logout + Passwort-Zweitbestätigung bei sensiblen Operationen |
| ID-Enumeration | Durchlaufen numerischer IDs zur Abschätzung der Datenmenge | Hashids-Verschleierung als Zufallsstrings |
| Datenleak | DB-Dump / Man-in-the-Middle / Protokoll-Leak | Drei-Ebenen-Verschlüsselung/Maskierung + Sensible-Felder-Filterung im OperationLog |
| DoS-Angriffe | übermäßig große Request-Bodies / hochfrequente Requests | Request-Body-10MB-Begrenzung + RateLimit 60/min + IP-Blacklist |
| Privilege-Escalation | Benutzer mit niedrigen Rechten greifen auf Admin-Schnittstellen zu | RBAC-Autorisierung mit method.path-Granularität |
| Datei-Upload-Angriffe | shell.php.png-Doppelendung | Erkennung böswilliger Dateien im SecurityFilter |

### 12.2 Bekannte Einschränkungen

| Einschränkung | Betroffener Bereich | Minderungsmaßnahmen |
|------|---------|---------|
| CSRF-Schutz wirkt nur im Browser | Nicht-Browser-Clients (curl, Postman, mobile Apps) können die Origin/Referer-Prüfung überspringen | Nicht-Browser-Clients sind von Natur aus nicht CSRF-anfällig; stattdessen JWT-Authentifizierung statt Cookies |
| Bei Redis-Ausfall degradieren Ratenbegrenzung und Blacklist auf fail-open | Angreifer können Ratenbegrenzung und Hochfrequenz-Blockierung umgehen | Redis-Verfügbarkeit überwachen und alarmieren; kurze JWT-Gültigkeit als Fallback |
| Keine eigenständige WAF-Engine | SecurityFilter verwendet `@preg_match`-Regex-Abgleich, keine dedizierte WAF-Regelengine | In Produktion Nginx ModSecurity oder Cloudflare WAF vorgeschaltet empfohlen |
| JWT ist zustandslos und kann nicht aktiv widerrufen werden | Token kann vor Ablauf nicht serverseitig aktiv widerrufen werden (außer Blacklist) | Blacklist + kurze 2h-TTL reduzieren das Risikofenster |
| IP-Blacklist nur im Speicher | Blacklist geht bei Redis-Neustart verloren | Ban-Dauer nur 15 Minuten, Auswirkung begrenzt |
| Admin-Endpunkte ohne spezielle Ratenbegrenzung | Admin-Schnittstellen teilen sich die Standardbegrenzung von 60/min mit normalen Schnittstellen | Admin-Operationsfrequenz ist von Natur aus niedrig, vorerst keine Unterscheidung nötig |
| `@preg_match` unterdrückt Fehler | stilles Versagen bei fehlerhaften Regex-Eingaben | `preg_last_error()` könnte überwacht werden, derzeit nicht implementiert |
