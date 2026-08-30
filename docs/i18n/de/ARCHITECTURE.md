# Architekturdiagramme und Geschäftslogikdiagramme

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Die folgenden Mermaid-Diagramme werden in GitHub / GitLab / VS Code automatisch gerendert. In anderen Umgebungen bitte mit dem [Mermaid Live Editor](https://mermaid.live/) anzeigen.

---

## 1. Systemtopologie-Architektur

```mermaid
flowchart TB
    subgraph "Client-Schicht"
        A1["Flutter Web<br/>PC-Verwaltungsoberfläche<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Handy-/Tablet-Client"]
    end

    subgraph "Gateway-/Edge-Schicht (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Reverse Proxy + HTTPS + Gzip<br/>Statischer Dateiserver"]
    end

    subgraph "Anwendungsschicht (webman v2)"
        C_LOC["Locale-Middleware<br/>Accept-Language automatische Erkennung"]
        C0["ApiVersion-Middleware<br/>API-Version-Header-Prüfung"]
        C1["AdminAuth-Middleware<br/>JWT-Verifizierung"]
        C2["AdminPermission-Middleware<br/>RBAC-Berechtigungsprüfung"]
        C3["Admin-Controller<br/>Dashboard / User / Role / Permission"]
        C4["Öffentlicher Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Speicherschicht"
        D1[("MySQL 8.0<br/>Hauptspeicher<br/>Tabellenpräfix erp_")]
        D2[("Elasticsearch<br/>Volltextsuche<br/>Indexpräfix erp_")]
        D3[("Redis<br/>Session / Cache<br/>Captcha-Speicher")]
    end

    subgraph "Extern"
        E1["DevEco Studio<br/>HarmonyOS-Build"]
        E2["Flutter SDK<br/>Web-Build"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Backend-Mehrschichtarchitektur

```mermaid
flowchart TD
    subgraph "Routing-Schicht"
        R1["config/route.php<br/>URL → Controller-Zuordnung"]
    end

    subgraph "Middleware-Schicht"
        M_LOC["Locale<br/>Accept-Language automatische Erkennung<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Redis-Fensterratenbegrenzung<br/>X-RateLimit-Response-Header"]
        M_SF["SecurityFilter<br/>Angriffserkennung und -blockierung<br/>XSS/SQL-Injection/Pfad-Traversal/CSRF"]
        M0["ApiVersion<br/>API-Versionsprüfung<br/>Injektion von apiVersion"]
        M1["AdminAuth<br/>JWT-Token-Prüfung<br/>Injektion von adminId"]
        M2["AdminPermission<br/>RBAC-Autorisierung<br/>method.path-Abgleich<br/>Redis 60s Berechtigungscache"]
    end

    subgraph "Controller-Schicht"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + Suche + Paginierung"]
        CT3["RoleController<br/>CRUD + Berechtigungssynchronisation"]
        CT4["PermissionController<br/>CRUD + Baumaufbau"]
        CT5["DashboardController<br/>Statistik/Trend/Verteilung"]
        CT6["ExportController<br/>Excel/PDF-Export"]
        CT7["CaptchaController<br/>Captcha-Generierung/-Prüfung"]
        CT8["AuthController<br/>Login/Registrierung/Refresh"]
    end

    subgraph "Service-Schicht"
        S1["HashidsService<br/>ID-Codierung/-Decodierung"]
        S2["SnowflakeService<br/>Globale eindeutige ID-Generierung"]
        S3["EncryptionService<br/>Verschlüsselung/-Entschlüsselung + Maskierung"]
    end

    subgraph "Model-Schicht"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Treiber-Schicht"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_LOC --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_LOC fill:#13C2C2,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

### Erweiterung der ERP-Geschäftsschicht

Mit der Weiterentwicklung des Systems von einer reinen Verwaltungsoberfläche zu einem vollständigen ERP-System kommen zur Controller- und Service-Schicht folgende Geschäftsmodule hinzu:

| Ebene | Verzeichnis | Beschreibung |
|------|------|------|
| Geschäftscontroller | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70 Stück, nach Modulen gegliedert, verarbeiten Geschäftsanfragen |
| Geschäftsservices | `app/service/{inventory,finance,notification}/` | Lager-Ein-/Ausgang + Kostenrechnung, finanzielle Forderungen/Verbindlichkeiten + Verrechnung, Benachrichtigungsversand |

---

## 3. Request-Lebenszyklus

```mermaid
sequenceDiagram
    participant C as Client
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: HTTPS-Request<br/>Header: API-Version: v1
    N->>MW_LOC: Weiterleitung
    MW_LOC->>MW_LOC: Accept-Language auswerten<br/>locale setzen
    MW_LOC->>MW_SF: bestanden

    alt Nicht standardmäßige HTTP-Methode (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Zulässige Methode (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Methoden-Whitelist-Prüfung bestanden
    end

    alt Angriffserkennung ausgelöst
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: bestanden

    alt Ratenbegrenzung ausgelöst
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: bestanden

    alt Nicht unterstützte Version
        MW0-->>C: 400 Nicht unterstützte API-Version
    else Version gültig
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token fehlt oder ungültig
        MW1-->>C: 401 Unauthorized
    else Token gültig
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Keine Berechtigung
        MW2-->>C: 403 Forbidden
    else Berechtigt
        MW2->>CTL: Controller betreten
    end

    CTL->>CTL: Parameterprüfung (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Sensible Operation (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Falsches Passwort
            CTL-->>C: 422 Passwortprüfung fehlgeschlagen
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast automatische Entschlüsselung
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: Response-JSON erstellen
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Betriebsprotokoll aufzeichnen (POST/PUT/DELETE)
```

---

## 4. Authentifizierungs- und Captcha-Ablauf

```mermaid
sequenceDiagram
    participant U as Benutzer
    participant CL as Client
    participant SV as Server
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Schritt 1: Captcha abrufen ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 Hintergrundbild generieren
    CAP->>CAP: N chinesische Ziele zufällig platzieren
    CAP->>CAP: key generieren, targets speichern
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Schritt 2: Benutzerklick ===
    CL->>CL: Captcha-Bild rendern
    CL->>CL: Hinweis "Bitte in Reihenfolge klicken: Baum → Vogel → Blume"
    U->>CL: Textpositionen im Bild der Reihe nach anklicken
    CL->>CL: clicks sammeln: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Schritt 3: Login ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha falsch
        CAP-->>SV: false
        SV-->>CL: 422 Captcha-Fehler
    else Captcha korrekt
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Zugangsdaten falsch
            SV-->>CL: 401 Benutzername oder Passwort falsch
        else Zugangsdaten korrekt
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Folgeanfragen ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC-Berechtigungsmodell

```mermaid
flowchart LR
    subgraph "Benutzer"
        U1["admin<br/>(Superadministrator)"]
        U2["editor<br/>(Bearbeiter)"]
        U3["viewer<br/>(Nur-Lese)"]
    end

    subgraph "Rollen"
        R1["super_admin<br/>Berechtigungs-Kennung: *"]
        R2["editor<br/>Berechtigungs-Kennung: get.*, post.*"]
        R3["viewer<br/>Berechtigungs-Kennung: get.*"]
    end

    subgraph "Berechtigungen (Baum)"
        P1["dashboard<br/>type=1 Menü"]
        P2["user<br/>type=1 Menü"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Button"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (alle Berechtigungen)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Berechtigungstypen"
        T1["type=1 Menü<br/>steuert Ein-/Ausblenden der Seitenleiste"]
        T2["type=2 Button<br/>steuert Aktionsbuttons auf der Seite"]
        T3["type=3 API<br/>steuert den API-Zugriff"]
    end

    subgraph "Format der Berechtigungs-Kennung"
        F1["{method}.{path}<br/>z. B. get.admin/user<br/>z. B. post.admin/user<br/>z. B. delete.admin/role"]
    end

    subgraph "Prüfablauf"
        J1["Token extrahieren → adminId"]
        J2["Benutzerrollen ermitteln"]
        J3["Alle Berechtigungs-Slugs sammeln"]
        J4["method.path konstruieren"]
        J5{"Übereinstimmung?"}
        J6["Durchlassen"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Ja / slug=*"| J6
        J5 -->|Nein| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Vollständiger ID-Lebenszyklus

```mermaid
flowchart LR
    subgraph "1. Generierung"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>z. B. 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Speicherung"
        S1["MySQL erp_*-Tabellen<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Sensible Felder<br/>encryptable cast<br/>AES-128-ECB-Verschlüsselung"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Übertragung"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid-String<br/>z. B. aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Rückwärts-Decodierung"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Ebenen der Datenverschlüsselung

```mermaid
flowchart TB
    subgraph "Transportschicht-Verschlüsselung (encryption)"
        E1["Client sendet sensible Daten"]
        E2["AES-256-CBC-Verschlüsselung"]
        E3["API-Übertragung als Chiffretext"]
        E4["Server entschlüsselt und verarbeitet"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Speicherschicht-Verschlüsselung (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Schreiben: automatische Verschlüsselung"]
        D3["MySQL VARCHAR(500)<br/>speichert Chiffretext"]
        D4["Lesen: automatische Entschlüsselung"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Anzeigeschicht-Maskierung (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. ER-Beziehungen der Datenbank

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "verschlüsselt"
        VARCHAR phone "verschlüsselt"
        VARCHAR id_card "verschlüsselt"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft Delete"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "Selbstreferenz"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 Menü, 2 Button, 3 API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erp_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "Quellgerät"
        TEXT input "maskiert"
        DATETIME created_at
    }

    erp_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user ||--o{ erp_admin_user_role : "user_id"
    erp_admin_role ||--o{ erp_admin_user_role : "role_id"
    erp_admin_role ||--o{ erp_admin_role_permission : "role_id"
    erp_admin_permission ||--o{ erp_admin_role_permission : "permission_id"
    erp_admin_user ||--o{ erp_operation_log : "user_id"
    erp_admin_permission ||--o{ erp_admin_permission : "parent_id"
```

---

## 9. Export-Geschäftsablauf

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Dateisystem

    Note over C,FS: === Excel-Export ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Daten
    CTL->>CTL: Sensible Felder entschlüsseln
    CTL->>CTL: Maskierung (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet-Aufbau<br/>Kopfzeile blauer Hintergrund, weiße Schrift<br/>dünne Rahmen für Datenzeilen<br/>erste Zeile fixiert<br/>AutoFilter
    CTL->>FS: Schreiben nach runtime/tmp/export_*.xlsx
    CTL-->>C: Datei-Download

    Note over C,FS: === PDF-Export ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Seitenkopf: Titel + Copyright + Zeit<br/>Inhalt: Tabelle oder Karten<br/>Fußzeile: nicht entfernbarer Copyright
    CTL->>CTL: Dompdf-Rendering A4 Querformat
    CTL->>FS: Schreiben nach runtime/tmp/export_*.pdf
    CTL-->>C: Datei-Download
```

---

## 10. Flutter-Web-Komponentenbaum

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Login-Formular<br/>Benutzername/Passwort/Captcha"]
    LF --> CAPTCHA["Klick-Captcha-Komponente<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Klick-Markierung Circle"]

    DB --> SIDEBAR["Seitenleiste NavigationDrawer<br/>einklappbar 64px / 240px<br/>Dashboard/Benutzer/Rollen/Konfiguration/Protokoll"]
    DB --> HEADER["Kopfzeile 56px<br/>Einklapp-Button + Benutzermenü<br/>Logout AlertDialog"]
    DB --> CONTENT["Inhaltsbereich"]
    CONTENT --> DASH["DashboardPage<br/>Statistik-Karten GridView<br/>Trend-Liniendiagramm LineChart<br/>Verteilungs-Kreisdiagramm PieChart<br/>Letzte Aktivitäten ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS-Seitenrouting

```mermaid
flowchart LR
    EA["EntryAbility<br/>Start"]
    EA -->|"Kein Token"| LP["LoginPage<br/>Login-Seite"]
    EA -->|"Token vorhanden"| DP["DashboardPage<br/>Dashboard"]

    LP -->|"Login erfolgreich<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Benutzerliste"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Persönlicher Bereich"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Benutzerdetails/Anlegen/Bearbeiten"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Logout<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Panorama der abgestuften Sicherheitsverteidigung

```mermaid
flowchart TB
    subgraph "Schicht 1: Mensch-zu-Maschine-Verifizierung"
        L1["Klick-Captcha<br/>Click Captcha<br/>Pflicht bei Login/Registrierung"]
    end

    subgraph "Schicht 2: Aktionsbestätigung"
        L2["Passwort-Zweitbestätigung<br/>confirmPassword()<br/>Pflicht bei DELETE-Operationen"]
    end

    subgraph "Schicht 3: Übertragungssicherheit"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Schicht 4: Identitätsauthentifizierung"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "Schicht 5: Berechtigungsautorisierung"
        L5["RBAC<br/>method.path-Granularität<br/>Superadministrator * "]
    end

    subgraph "Schicht 6: Datenschutz"
        L6["API-ID: Hashids-Verschlüsselung<br/>Request-Body: Encryption-Verschlüsselung<br/>Speicherschicht: Encryptable-Verschlüsselung<br/>Export: Maskierung + Copyright"]
    end

    subgraph "Schicht 7: Audit-Nachvollziehbarkeit"
        L7["OperationLog<br/>zeichnet alle Operationen auf<br/>Benutzer/IP/Zeit/Quellgerät/Parameter"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Bereitstellungstopologie

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web-Server"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Statische Dateien<br/>Flutter Web build/"]
    end

    subgraph "Anwendungsserver (horizontal skalierbar)"
        WM1["webman worker 1<br/>:8788"]
        WM2["webman worker 2<br/>:8788"]
        WM3["webman worker N<br/>:8788"]
    end

    subgraph "Datenschicht"
        MYSQL["MySQL 8.0<br/>Master-Replica-Replikation<br/>erp_-Präfix"]
        ES["Elasticsearch 8.x<br/>3-Knoten-Cluster<br/>erp_-Präfix"]
        REDIS["Redis 7.x<br/>Sentinel-Modus<br/>poster:captcha:*"]
    end

    subgraph "Monitoring"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```

---

## 14. Gesamtarchitektur des ERP-Systems

```mermaid
graph TB
    subgraph Client["Client-Schicht"]
        FW["Flutter Web<br/>PC-Verwaltungsoberfläche"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>HarmonyOS-native App"]
    end

    subgraph Gateway["API-Gateway-Schicht"]
        MW["Middleware-Kette<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["Geschäftsmodul-Schicht"]
        direction LR
        Admin["Systemverwaltung<br/>Benutzer/Rollen/Berechtigungen/Konfiguration/Protokoll"]
        Product["Artikelverwaltung<br/>Artikel/Kategorien/Marken/Lager/Supplier/Kunden"]
        Purchase["Einkaufsverwaltung<br/>Antrag→Bestellung→Wareneingang→Retoure→Abrechnung"]
        Sales["Vertriebsverwaltung<br/>Angebot→Bestellung→Versand→Retoure→Abrechnung"]
        Inventory["Lagerverwaltung<br/>Ein-/Ausgang/Chargen/Inventur/Umlagerung/Warnung"]
        Finance["Finanzverwaltung<br/>Konten/Belege/Forderungen-Verbindlichkeiten/Hauptbuch/Detailbuch/Berichte/Erstattungen"]
        CRM["CRM<br/>Kunden/Kontakte/Nachfass/Aufbereitung/Public Pool/Angebote/Verträge"]
        Workflow["Genehmigungs-Workflow<br/>Workflow-Definition/Einreichen/Genehmigen/Ablehnen/Zurückziehen"]
        Notification["Benachrichtigungen<br/>Benachrichtigungsliste/Gelesen/Anzahl ungelesen"]
        Project["Projektverwaltung<br/>Projekte/Aufgaben/Zeiterfassung"]
        HR["Personalwesen<br/>Abteilungen/Mitarbeiter/Positionen/Anwesenheit/Urlaub/Gehalt"]
        Manufacturing["Produktion<br/>BOM/Produktionsaufträge/Arbeitspläne/Arbeitsstationen/MRP"]
        Report["Benutzerdefinierte Berichte<br/>Berichtsvorlagen/Datensätze/Felder/Filter/Zeitpläne"]
    end

    subgraph Service["Geschäfts-Service-Schicht"]
        IS["InventoryService<br/>Ein-/Ausgang + gleitende gewichtete Durchschnittskosten"]
        FS["FinanceService<br/>automatische Erzeugung von Forderungen/Verbindlichkeiten + Verrechnung"]
        NS["NotificationService<br/>einheitlicher Benachrichtigungsversand"]
    end

    subgraph Data["Datenschicht"]
        MySQL["MySQL 8.0<br/>163 Geschäftstabellen"]
        Redis["Redis 7<br/>Cache/Ratenbegrenzung/Session"]
        ES["Elasticsearch 8<br/>Volltextsuche"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Modulübergreifende Datenflüsse

```mermaid
sequenceDiagram
    participant PO as Wareneingang (Einkauf)
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Lagerbestandstabelle
    participant COST as Kostenaufzeichnung
    participant ARAP as Forderungen/Verbindlichkeiten

    PO->>IS: stockIn(Artikel, Menge, Einzelpreis)
    IS->>INV: Echtzeitbestand aktualisieren (mit Sperre)
    IS->>COST: Gleitende gewichtete Durchschnittskosten neu berechnen
    IS-->>PO: Flow-ID zurückgeben
    
    PO->>FS: createAp(Supplier, Betrag)
    FS->>ARAP: Verbindlichkeit erzeugen
    
    Note over PO,ARAP: Analog beim Versand: stockOut + createAr
```

---

## 16. Datenfluss der Lagerkostenrechnung

```mermaid
graph LR
    A[Wareneingang 100 CNY x 10 Stück] --> B[Zugangsbuchung]
    C[Wareneingang 130 CNY x 20 Stück] --> D[Zugangsbuchung]
    B --> E[Bestand: 10 Stück, Kosten 100]
    D --> F[Bestand: 30 Stück, Kosten 120]
    E --> G[Gleitender gewichteter Durchschnitt: 100]
    F --> H[Gleitender gewichteter Durchschnitt: 120]
    H --> I[Abgang wird mit 120 bewertet]
```

---

## 17. Datenfluss des Genehmigungsworkflows

```mermaid
sequenceDiagram
    participant Biz as Geschäftsmodul
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Workflow-Engine
    participant NTF as NotificationService

    Biz->>WF: Genehmigung einreichen (Geschäftsnummer, Modultyp)
    WF->>WFE: Workflow-Definition abgleichen → Genehmigungsinstanz erstellen
    WFE->>APR: Genehmiger des ersten Knotens benachrichtigen
    APR->>NTF: Genehmigungsbenachrichtigung senden
    NTF-->>APR: Benachrichtigung gesendet
    APR->>APR: Genehmiger genehmigt/lehnt ab
    alt Genehmigt
        APR->>WFE: zum nächsten Knoten weiterleiten
        alt Alle Knoten genehmigt
            WFE->>Biz: Callback: Genehmigung erteilt, Geschäftsbelegstatus aktualisieren
        end
    else Abgelehnt
        WFE->>Biz: Callback: Genehmigung abgelehnt
    end
```

---

## 18. Datenfluss der Benachrichtigungen

```mermaid
sequenceDiagram
    participant Event as Ereignisauslöser
    participant NS as NotificationService
    participant DB as Benachrichtigungstabelle
    participant User as Benutzer

    Event->>NS: Benachrichtigung auslösen (Typ, Titel, Inhalt, Empfänger)
    NS->>DB: Benachrichtigungsdatensatz schreiben
    NS-->>User: Push (Interne Nachricht/WebSocket)
    User->>NS: Als gelesen markieren
    NS->>DB: Gelesen-Status aktualisieren
    User->>NS: Ungelesene Anzahl abfragen
    NS-->>User: Ungelesene Anzahl
```

---

## 19. Datenfluss der MRP-Materialbedarfsplanung

```mermaid
sequenceDiagram
    participant SO as Verkaufsauftrag
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Einkaufsvorschlag
    participant MO as Produktionsvorschlag

    SO->>MRP: Bedarf aus Verkaufsauftrag
    MRP->>BOM: BOM aufblättern, Stückliste abrufen
    BOM-->>MRP: Materialien + Standardmengen
    MRP->>INV: Verfügbare Lagermenge abfragen
    INV-->>MRP: Lagermenge
    MRP->>MRP: Nettobedarf = Bruttobedarf - Lagerbestand berechnen
    alt Rohstoffe unzureichend
        MRP->>PO: Einkaufsvorschlag erzeugen
    else Halbfabrikate unzureichend
        MRP->>MO: Produktionsvorschlag erzeugen
    end
```

---

## 20. Zuordnungstabelle der ERP-Module: Controller-Service-Model

> Hinweis zur Service-Schicht: In der Spalte `Kern-Service` sind die bereits ausgelagerten Geschäftsservices des Moduls vermerkt; Module mit der Kennzeichnung **⚠ Controller greift direkt auf Model zu (bekannter Tech-Schuldposten)**
> rufen weiterhin direkt Modell-Abfrage-/Schreibmethoden im Controller auf (`XxxModel::find/where/save` usw.), eine Service-Schicht wurde noch nicht extrahiert. Dies ist ein bekannter Tech-Schuldposten,
> der in der Folge schrittweise nach dem Muster der leichten Service-Schicht-Extraktion P2-F2 (`app/service/AbstractCrudService` generische CRUD-Basisklasse + Modul-Service) abgebaut wird.

| Modul | Controllers (Verzeichnis) | Kern-Service | Wichtigste Models | Tabellen |
|------|-------------------|-------------|-----------|------|
| Systemverwaltung | admin/controller/ (14) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | AdminUser, AdminRole, AdminPermission | 7 |
| Artikelverwaltung | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| Einkaufsverwaltung | controller/purchase/ (5) | InventoryService, FinanceService ⚠ CRUD greift weiterhin direkt zu, bekannter Tech-Schuldposten | PurchaseOrder, PurchaseReceive | 9 |
| Vertriebsverwaltung | controller/sales/ (5) | InventoryService, FinanceService ⚠ CRUD greift weiterhin direkt zu, bekannter Tech-Schuldposten | SalesOrder, SalesDelivery | 9 |
| Lagerverwaltung | controller/inventory/ (5) | InventoryService ⚠ CRUD greift weiterhin direkt zu, bekannter Tech-Schuldposten | Inventory, InventoryFlow, CostRecord | 11 |
| Finanzverwaltung | controller/finance/ (20) | FinanceService ⚠ CRUD greift weiterhin direkt zu, bekannter Tech-Schuldposten | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Genehmigungs-Workflow | controller/workflow/ (2) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Benachrichtigungen | controller/notification/ (1) | NotificationService ⚠ CRUD greift weiterhin direkt zu, bekannter Tech-Schuldposten | Notification, NotificationSetting, NotificationTemplate | 3 |
| Projektverwaltung | controller/project/ (3) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| Personalwesen | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| Produktion | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| Benutzerdefinierte Berichte | controller/report/ (2) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM Anlagenverwaltung | controller/eam/ (4) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS Dokumentenverwaltung | controller/dms/ (2) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI-Dashboard | controller/bi/ (3) | - ⚠ Controller greift direkt auf Model zu, bekannter Tech-Schuldposten | BiDashboard, BiWidget | 2 |

### 20.1 Aufzeichnung der leichten Service-Schicht-Extraktion P2-F2 (crm/hr/manufacturing/product bereits extrahiert)

| Modul | Direktzugriffe im Controller vor der Extraktion | Nach der Extraktion | Neuer Service | Extrahierter Inhalt |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | Generisches CRUD + Vertragsstatus-Übergänge, Angebot-zu-Vertrag, Public-Pool-Zuweisung/Freigabe, Ticket-Zuweisung/Lösung/Antwort, kaskadierende Bereinigung von Detaildatensätzen, Aufbau von Analyseberichtsdaten |
| Personalwesen | 38 | 0 | `app/service/hr/HrService.php` | Generisches CRUD + Stempeln-Zu-spät/Zu-früh-Erkennung, Urlaubsgenehmigung (automatische Urlaubs-Anwesenheitserfassung), Gehalts-Eindeutigkeit/Auszahlungsbetrag-Berechnung/Auszahlung/Massenerzeugung |
| Produktion | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | Generisches CRUD + Auftragsstart/-abschluss-Übergänge, BOM-Versionskopie/Wirksamkeits-Mutual-Exclusion, MRP-Detailerzeugung |
| Artikelverwaltung | 29 | 0 | `app/service/product/ProductService.php` | Generisches CRUD + transaktionale Artikelerstellung (SKU/Preise), feldweises Aktualisieren unter Beibehaltung der Originalwerte, assoziatives Laden von Detaildaten |

Extraktionsmuster: `app/service/AbstractCrudService.php` stellt `list/all/find/create/update/delete/deleteWhere` als generisches CRUD
sowie `normalizePageParams/canTransition` als rein logische Helfer bereit; die Modul-Services erben davon und bündeln die modulspezifische Geschäftslogik.
Controller injizieren die Services über `Container::get(XxxService::class)` (mit class_exists-Fallback), sodass Routen, Parameter und Rückgabestrukturen vollständig unverändert bleiben;
HTTP-Belange wie Hashid-Codierung/-Decodierung, Passwort-Zweitbestätigung und Response-Wrapping verbleiben im Controller.
Die neuen Services sind in `config/dependence.php` registriert (diese Datei ist eine dead config und wird von addDefinitions nicht geladen; der Laufzeit-Container
instanziiert über den class_exists-Fallback, daher bleiben alle Services ohne Konstruktorargumente).

Nicht extrahierte Module (Projektverwaltung 18, Benutzerdefinierte Berichte 18, Einkauf 24, Vertrieb 24, Systemverwaltung 42 usw.) sind in der Tabelle
mit "Controller greift direkt auf Model zu, bekannter Tech-Schuldposten" gekennzeichnet und werden in Folgeiterationen nach demselben Muster extrahiert.

---

## OMS/WMS/TMS-Erweiterungsmodule (2026-08)

### OMS (Order Management System) — 8 Tabellen
- **Auftragserweiterung** (`erp_oms_order`): Multi-Kanal-Aggregation/Erfüllungsstatus/Zahlungsstatus/Priorität
- **Auftragsadresse** (`erp_oms_order_address`): Liefer-/Rechnungsadresse (Länderformate)
- **Erfüllungsaufzeichnungen** (`erp_oms_fulfillment`+`_item`): Zuordnung/kommissioniert/verpackt/versendet Mengenverfolgung
- **RMA** (`erp_oms_rma`+`_item`): vollständiger Lebenszyklus von Rückgabe/Umtausch
- **Bestandsreservierung** (`erp_oms_inventory_reservation`): ATP = physical - reserved
- **Kanäle** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 Tabellen
- **Zonen/Lagerplätze** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **Wareneingang** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Warenausgang** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 Tabellen
- **Spediteure** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Sendungen** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Rechnungen** (`erp_tms_freight_invoice`)

### Datenflüsse
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. Ökosystem-Roadmap (2026-08)

> Detaillierte Designspezifikation: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Basisbewertung (zu Beginn der Roadmap)

> P0~P3 wurden vollständig ausgeliefert, die aktuelle Gesamtbewertung beträgt 89/100 (siehe CLAUDE.md); die folgende Tabelle ist eine Basisschnappschuss-Analyse vor Beginn der Roadmap.

| Dimension | Bewertung | Wichtigste Lücken |
|------|------|----------|
| Backend-API | 85/100 | Mehrere Module sind CRUD-Gerüste, es fehlen Geschäftsberechnungs-Engines |
| Sicherheit | 95/100 | 18 Schichten abgestufter Verteidigung, produktionsbereit |
| Frontend-UI | 20/100 | **Größte Schwäche**: Flutter 12 Seiten decken ~20 % der Module ab, Web-Admin-Panel fehlt |
| Betriebs-Ökosystem | 70/100 | Es fehlen Migrations-Rollback, automatische Backups, Observability |
| Geschäftstiefe | 55/100 | Kernalgorithmen für Finanzen/HR/Produktion nicht implementiert |
| **Gesamt** | **65/100** | |

### 21.2 Vierphasige serielle Roadmap

```
P0(3-4 Wochen) → P1(4-6 Wochen) → P2(1-2 Wochen) → P3(2-3 Wochen) = insgesamt ca. 13 Wochen
```

| Phase | Name | Kern-Lieferumfang |
|------|------|----------|
| **P0** | Frontend-Ökosystem | Flutter-Web-Admin-Panel für alle Module (14 Module, 40+ Seiten), generische Komponentenbibliothek, HarmonyOS-Angleichung |
| **P1** | Geschäftstiefe | Doppelte-Buchführungs-Engine, Gehaltsberechnungs-Engine, MRP-Engine, Qualitätsmanagementmodul, Echtzeit-Benachrichtigungen (WebSocket) |
| **P2** | Betriebliche Zuverlässigkeit | Datenbank-Migrations-Rollback, erweiterte automatische Backups, OpenTelemetry-Tracing, RabbitMQ-Queue-Treiber |
| **P3** | Erlebnisverbesserung | BI-Drag-and-Drop-Dashboards, Anlagenverwaltung (EAM), Multi-Tenant-Isolation, Dokumentenverwaltung (DMS) |

### 21.3 Entwicklung der Middleware-Kette

```
Heute:    Locale → Cors → SecurityFilter → RateLimit → TracingId → {Routengruppen}
Nach P1:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {Routengruppen}
Nach P2:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {Routengruppen}
Nach P3:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {Routengruppen}
```

### 21.4 P0-Zielarchitektur — Flutter-Web-Admin-Panel

| Ebene | Neuer Inhalt |
|------|----------|
| Layout-Ebene | `AdminLayout` PC-Dreispalten-Layout (einklappbare Seitenleiste + Kopfzeile + Inhaltsbereich) |
| Komponenten-Ebene | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Seiten-Ebene | Erweiterung von den vorhandenen 12 Seiten auf vollständige Abdeckung von 14 Modulen mit 40+ Seiten |
| Service-Ebene | Wiederverwendung der vorhandenen `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 P1-Zielarchitektur — Geschäftsberechnungs-Engines

| Engine | Service-Klassen | Kernregeln |
|------|--------|----------|
| Doppelte Buchführung | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Erzwungene Soll/Haben-Gleichgewichtsprüfung, Periodenabschluss mit Ergebnisüberträgen, Multi-Währungs-Umrechnung |
| Gehaltsberechnung | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Sozialversicherungsbasis-Unter-/Obergrenzen, Wohnungsbaufonds-Sätze, progressive Einkommensteuer, Bank-Datenträger |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM-Ebenen-Aufblätterung + Schwund, Low-Level-Code (LLC), Sicherheitsbestand, Losgrößenregeln |
| Qualität | `QmsInspectionService` | IQC-Eingang/IPQC-Prozess/OQC-Ausgang, Drei-Formular-Durchlauf |
| Benachrichtigungen | `WebSocketService`, `ChannelRouter` | Interne Nachricht/E-Mail/WeCom/DingTalk, Multi-Kanal |

### 21.6 Zusammenfassung der Datenmodelländerungen

| Phase | Neue Tabellen | Betroffene Module |
|------|----------|----------|
| P0 | 0 | Reines Frontend, keine Tabellenänderungen |
| P1 | 14 | Finanzen (2) + HR (3) + Produktion (2) + Qualität (5) + Benachrichtigungen (2) |
| P3 | 7 | BI (2) + EAM (3) + DMS (2) |

---

## 22. Multi-Tenant (reservierte Fähigkeit, nicht aktiviert)

> Copyright-Hinweis wie im Dateikopf: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Positionierung und Entscheidung

Multi-Tenant ist in diesem Projekt als **reservierte Fähigkeit** positioniert; in dieser Phase wird es **nicht angeschlossen und nicht aktiviert** (dokumentierte Reduzierung). Im Einklang mit der Planung:
"SaaS-Abrechnung, tenantseitige Selbstregistrierung" und andere "vollständige kommerzielle Multi-Tenant-Lösungen" liegen außerhalb des Projektumfangs; diese Phase behält nur das minimale
Code-Gerüst (Middleware + Model-Trait) und gibt Aktivierungsschritte für eine spätere bedarfsgerechte Aktivierung an.
Hinweis: Das "Multi-Tenant-Isolation" in §21.2 Roadmap P3 wird entsprechend zu "reservierte Fähigkeit (dokumentierte Reduzierung)" angepasst — Gerüst bleibt, keine Verkabelung.

Entscheidungsgrundlage (Review 2026-08):
- Die bestehenden Bereitstellungen sind fast ausnahmslos Single-Tenant; eine Verkabelung würde unnötige Isolationskomplexität und Regressionsrisiken einführen;
- Das aktuelle Gerüst hat technische Defizite (siehe 22.4), "Verkabelung = Isolation" trifft nicht zu; zuerst ist eine Designkorrektur erforderlich;
- Die Isolation erfordert das Hinzufügen von Spalten je Geschäftstabelle unter den 163 Tabellen und das Aktivieren je Model — der Aufwand übersteigt eine "minimale Verkabelung" deutlich.

### 22.2 Aktueller Stand (Code- und Konfigurationsabgleich)

| Position | Aktueller Stand |
|----|------|
| `app/middleware/TenantScope.php` | Vorhanden, nicht registriert; liest den Tenant aus dem `X-Tenant-Id`-Header, lässt bei fehlendem Header direkt durch |
| `app/model/concerns/TenantScope.php` | Vorhanden, von keinem Model verwendet; der globale Scope in `bootTenantScope()` filtert nur, wenn ein Tenant gesetzt ist |
| `config/middleware.php` | Globale Kette: Locale → Cors → SecurityFilter → RateLimit → TracingId, ohne TenantScope |
| `/admin`-Gruppe in `config/route.php` | AdminAuth → AdminPermission → OperationLog, ohne TenantScope |
| JWT-Payload | Nur `sub` / `username` / `token_type`, **kein tenant_id-Claim** (`app/api/v1/controller/AuthController.php`) |
| Datenbank | **Keine tenant_id-Spalte in der gesamten Datenbank** (auch nicht in install.sql) |
| Models | **Kein Model verwendet den TenantScope-Trait** |

### 22.3 Aktivierungsschritte (Referenz für später, in dieser Phase nicht ausgeführt)

1. Middleware registrieren: in der `middleware()` der /admin-Gruppe in `config/route.php`
   `app\middleware\TenantScope::class` anhängen (nach AdminAuth, um authentifizierte Anfragen sicherzustellen).
2. Der Anfragesteller trägt im Request-Header `X-Tenant-Id` (int Tenant-ID) ein.
3. Für zu isolierende Geschäftstabellen die Spalte `tenant_id` hinzufügen (BIGINT + Index) und Bestandsdaten nachtragen;
   Wörterbuch-/Systemtabellen (z. B. `erp_admin_user`, `erp_role`, `erp_permission`) werden nicht isoliert.
4. In den zu isolierenden Model-Klassen `use app\model\concerns\TenantScope;` einbinden — automatische Filterung nach aktuellem Tenant.
5. (Optional) Falls der Tenant aus dem JWT statt aus dem Header kommen soll: das Login-Signierungs-Payload um den Claim `tenant_id` erweitern
   und in der Middleware aus `$payload['tenant_id']` lesen.

### 22.4 Bekannte technische Einschränkungen (müssen vor Aktivierung gelöst werden)

- **Unterbrochene statische Übergabekette (mit PHP 8.3 getestet)**: Die Middleware ruft `setCurrentTenantId()` über den Trait-Namen auf
   und schreibt damit in die statische Kopie des Traits selbst; Model-Klassen, die diesen Trait verwenden, können sie nicht lesen, die Abfrage wird nicht gefiltert.
   Bei der Aktivierung auf eine requestkontextbasierte Injektion umstellen (z. B. `request()->tenantId`).
- **Statische Globalzustands-Interferenz**: Workerman ist ein persistent laufender Prozess; statische Eigenschaften werden über Requests hinweg geteilt; bei aktiviertem Coroutine-Modus
   (Swoole/Swow) kommt es zu tenantübergreifender Dateninterferenz — auf requestgebundene Bindung umstellen (`context()` / Request-Objekt).
- **Datenebenen-Lücke**: Es gibt keine tenant_id-Spalte in der gesamten Datenbank; Migration ist je Tabelle erforderlich; für tenantübergreifend geteilte Wörterbuchtabellen muss ein Befreiungsmechanismus entworfen werden.

### 22.5 Abnahmekriterien

Abnahme dieser Phase = Dokumentation und Code stimmen überein: `config/middleware.php` und `config/route.php` enthalten
keine TenantScope-Registrierung; Middleware und Trait-Kommentare kennzeichnen eindeutig "reservierte Fähigkeit, nicht aktiviert" und geben Aktivierungsschritte an;
dieser Abschnitt entspricht Punkt für Punkt dem Code-Stand.
