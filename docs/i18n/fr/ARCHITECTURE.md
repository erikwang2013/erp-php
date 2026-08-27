# Diagrammes d'architecture et de logique métier

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Les diagrammes Mermaid suivants se rendent automatiquement dans GitHub / GitLab / VS Code. Pour les autres environnements, utilisez le [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Topologie du système

```mermaid
flowchart TB
    subgraph "Couche client"
        A1["Flutter Web<br/>Console d'administration PC<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Client mobile/tablette"]
    end

    subgraph "Couche passerelle/edge (Nginx Edge)"
        B1["Nœud Nginx Edge<br/>Docker nginx:alpine<br/>Proxy inverse + HTTPS + Gzip<br/>Service de fichiers statiques"]
    end

    subgraph "Couche application (webman v2)"
        C_LOC["Middleware Locale<br/>Détection automatique Accept-Language"]
        C0["Middleware ApiVersion<br/>Validation de l'en-tête API-Version"]
        C1["Middleware AdminAuth<br/>Validation JWT"]
        C2["Middleware AdminPermission<br/>Vérification des permissions RBAC"]
        C3["Contrôleurs Admin<br/>Dashboard / User / Role / Permission"]
        C4["Contrôleurs publics v1<br/>Captcha / Auth"]
        C5["Services communs<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Couche de stockage"
        D1[("MySQL 8.0<br/>Stockage principal<br/>Préfixe de table erp_")]
        D2[("Elasticsearch<br/>Recherche plein texte<br/>Préfixe d'index erp_")]
        D3[("Redis<br/>Session / Cache<br/>Stockage Captcha")]
    end

    subgraph "Externe"
        E1["DevEco Studio<br/>Build HarmonyOS"]
        E2["Flutter SDK<br/>Build Web"]
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

## 2. Architecture en couches du backend

```mermaid
flowchart TD
    subgraph "Couche routage Route Layer"
        R1["config/route.php<br/>Mapping URL → Controller"]
    end

    subgraph "Couche middleware Middleware Layer"
        M_LOC["Locale<br/>Détection automatique Accept-Language<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Limitation de débit à fenêtre glissante Redis<br/>En-têtes de réponse X-RateLimit"]
        M_SF["SecurityFilter<br/>Interception de détection d'attaque<br/>XSS/Injection SQL/Traversée de chemin/CSRF"]
        M0["ApiVersion<br/>Validation de version API<br/>Injection de apiVersion"]
        M1["AdminAuth<br/>Validation du jeton JWT<br/>Injection de adminId"]
        M2["AdminPermission<br/>Autorisation RBAC<br/>Correspondance method.path<br/>Cache des permissions Redis 60 s"]
    end

    subgraph "Couche contrôleur Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + recherche + pagination"]
        CT3["RoleController<br/>CRUD + synchronisation des permissions"]
        CT4["PermissionController<br/>CRUD + construction de l'arbre"]
        CT5["DashboardController<br/>Statistiques/tendances/répartition"]
        CT6["ExportController<br/>Export Excel/PDF"]
        CT7["CaptchaController<br/>Génération/validation du captcha"]
        CT8["AuthController<br/>Connexion/inscription/rafraîchissement"]
    end

    subgraph "Couche service Service Layer"
        S1["HashidsService<br/>Encodage/décodage d'ID"]
        S2["SnowflakeService<br/>Génération d'ID globale unique"]
        S3["EncryptionService<br/>Chiffrement/déchiffrement + masquage"]
    end

    subgraph "Couche modèle Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Couche pilote Driver Layer"
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

### Extension de la couche métier ERP

Au fur et à mesure que le système évolue d'une simple console d'administration vers un système ERP complet, les couches contrôleur et service intègrent les modules métier suivants :

| Couche | Répertoire | Description |
|------|------|------|
| Contrôleurs métier | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70, répartis par module, traitent les requêtes métier |
| Services métier | `app/service/{inventory,finance,notification}/` | entrées-sorties de stock + calcul des coûts, comptes à recevoir et à payer + rapprochement, envoi de notifications |

---

## 3. Cycle de vie d'une requête

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

    C->>N: Requête HTTPS<br/>Header: API-Version: v1
    N->>MW_LOC: Transmission
    MW_LOC->>MW_LOC: Analyse Accept-Language<br/>Définition de locale
    MW_LOC->>MW_SF: Validé

    alt Méthode HTTP non standard (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Méthode légale (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Vérification de la liste blanche de méthodes réussie
    end

    alt Déclenchement de la détection d'attaque
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Validé

    alt Déclenchement de la limitation de débit
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Validé

    alt Version non prise en charge
        MW0-->>C: 400 Version API non prise en charge
    else Version valide
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Jeton manquant ou invalide
        MW1-->>C: 401 Unauthorized
    else Jeton valide
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Pas d'autorisation
        MW2-->>C: 403 Forbidden
    else Autorisation accordée
        MW2->>CTL: Entrée dans le contrôleur
    end

    CTL->>CTL: Validation des paramètres (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Opération sensible (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Mot de passe incorrect
            CTL-->>C: 422 Échec de la validation du mot de passe
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Déchiffrement automatique encryptable cast
    MDL->>DB: SELECT
    DB-->>MDL: Ligne
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: Chaîne de hash

    CTL->>CTL: Construction du JSON de réponse
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Enregistrement du journal des opérations (POST/PUT/DELETE)
```

---

## 4. Flux d'authentification et de captcha

```mermaid
sequenceDiagram
    participant U as Utilisateur
    participant CL as Client
    participant SV as Serveur
    participant JWT as Service JWT
    participant CAP as Service Captcha

    Note over U,CAP: === Étape 1 : obtenir le captcha ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Génération d'une image de fond 300×200
    CAP->>CAP: Placement aléatoire de N cibles chinoises
    CAP->>CAP: Génération de la clé, stockage des targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Étape 2 : clic de l'utilisateur ===
    CL->>CL: Rendu de l'image du captcha
    CL->>CL: Invite "Cliquez dans l'ordre : arbre → oiseau → fleur"
    U->>CL: Clique successivement sur les mots dans l'image
    CL->>CL: Collecte clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Étape 3 : connexion ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Captcha incorrect
        CAP-->>SV: false
        SV-->>CL: 422 Erreur de captcha
    else Captcha correct
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Identifiants erronés
            SV-->>CL: 401 Nom d'utilisateur ou mot de passe incorrect
        else Identifiants corrects
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14j)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Requêtes ultérieures ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. Modèle d'autorisation RBAC

```mermaid
flowchart LR
    subgraph "Utilisateurs User"
        U1["admin<br/>(super administrateur)"]
        U2["editor<br/>(éditeur)"]
        U3["viewer<br/>(lecture seule)"]
    end

    subgraph "Rôles Role"
        R1["super_admin<br/>Identifiants de permission : *"]
        R2["editor<br/>Identifiants de permission : get.*, post.*"]
        R3["viewer<br/>Identifiants de permission : get.*"]
    end

    subgraph "Permissions Permission (arbre)"
        P1["dashboard<br/>type=1 Menu"]
        P2["user<br/>type=1 Menu"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 Bouton"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (toutes les permissions)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "Types de permissions"
        T1["type=1 Menu<br/>Contrôle l'affichage/masquage de la barre latérale"]
        T2["type=2 Bouton<br/>Contrôle les boutons d'action de la page"]
        T3["type=3 API<br/>Contrôle l'accès aux interfaces"]
    end

    subgraph "Format des identifiants de permission"
        F1["{method}.{path}<br/>Ex. : get.admin/user<br/>Ex. : post.admin/user<br/>Ex. : delete.admin/role"]
    end

    subgraph "Processus de décision"
        J1["Extraction du jeton → adminId"]
        J2["Recherche des rôles de l'utilisateur"]
        J3["Collecte de tous les slugs de permission"]
        J4["Construction de method.path"]
        J5{"Correspondance ?"}
        J6["Autorisation"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"Oui / slug=*"| J6
        J5 -->|Non| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Cycle de vie complet des ID

```mermaid
flowchart LR
    subgraph "1. Génération"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bits)<br/>+ worker_id(5bits)<br/>+ timestamp(41bits)<br/>+ sequence(12bits)"]
        G3["BIGINT(18)<br/>Ex. : 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Stockage"
        S1["Tables MySQL erp_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Champs sensibles<br/>encryptable cast<br/>Chiffrement AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Transmission"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["Chaîne hashid<br/>Ex. : aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Décodage inverse"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Couches de chiffrement des données

```mermaid
flowchart TB
    subgraph "Chiffrement de la couche de transport (encryption)"
        E1["Le client envoie des données sensibles"]
        E2["Chiffrement AES-256-CBC"]
        E3["Texte chiffré transmis via l'API"]
        E4["Déchiffrement et traitement côté serveur"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Chiffrement de la couche de stockage (encryptable)"
        D1["$casts du Model<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Écriture : chiffrement automatique"]
        D3["MySQL VARCHAR(500)<br/>Stockage du texte chiffré"]
        D4["Lecture : déchiffrement automatique"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Masquage de la couche d'affichage (mask)"
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

## 8. Relations ER de la base de données

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "Chiffré"
        VARCHAR phone "Chiffré"
        VARCHAR id_card "Chiffré"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Suppression logique"
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
        BIGINT parent_id FK "Auto-référence"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1 menu 2 bouton 3 API"
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
        VARCHAR source "Plateforme source"
        TEXT input "Masqué"
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

## 9. Processus métier d'export

```mermaid
sequenceDiagram
    participant C as Client
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Système de fichiers

    Note over C,FS: === Export Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Données
    CTL->>CTL: Déchiffrement des champs sensibles
    CTL->>CTL: Traitement de masquage (maskPhone/maskEmail)
    CTL->>CTL: Construction PhpSpreadsheet<br/>En-tête bleu sur fond blanc<br/>Lignes de données à bordure fine<br/>Gel de la première ligne<br/>Filtre automatique
    CTL->>FS: Écriture runtime/tmp/export_*.xlsx
    CTL-->>C: Téléchargement du fichier

    Note over C,FS: === Export PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>En-tête : titre + copyright + heure<br/>Contenu : tableau ou carte<br/>Pied de page : copyright non supprimable
    CTL->>CTL: Rendu Dompdf A4 paysage
    CTL->>FS: Écriture runtime/tmp/export_*.pdf
    CTL-->>C: Téléchargement du fichier
```

---

## 10. Arborescence des composants Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Formulaire de connexion<br/>Nom d'utilisateur/mot de passe/captcha"]
    LF --> CAPTCHA["Composant captcha à clic<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Marqueurs Circle au clic"]

    DB --> SIDEBAR["NavigationDrawer latéral<br/>Repliable 64px / 240px<br/>Tableau de bord/utilisateurs/rôles/config/logs"]
    DB --> HEADER["Barre supérieure 56px<br/>Bouton de repli + menu utilisateur<br/>AlertDialog de déconnexion"]
    DB --> CONTENT["Zone de contenu"]
    CONTENT --> DASH["DashboardPage<br/>Cartes de statistiques GridView<br/>Graphique de tendance LineChart<br/>Graphique en secteurs PieChart<br/>Opérations récentes ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Routage des pages HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Démarrage"]
    EA -->|"Sans Token"| LP["LoginPage<br/>Page de connexion"]
    EA -->|"Avec Token"| DP["DashboardPage<br/>Tableau de bord"]

    LP -->|"Connexion réussie<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Liste des utilisateurs"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Espace personnel"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Détails/création/modification d'utilisateur"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Déconnexion<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Vue d'ensemble de la défense en profondeur de la sécurité

```mermaid
flowchart TB
    subgraph "Couche 1 : Vérification homme-machine"
        L1["Captcha à clic<br/>Click Captcha<br/>Obligatoire à la connexion/inscription"]
    end

    subgraph "Couche 2 : Confirmation des opérations"
        L2["Double confirmation du mot de passe<br/>confirmPassword()<br/>Obligatoire pour les opérations DELETE"]
    end

    subgraph "Couche 3 : Sécurité du transport"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Couche 4 : Authentification d'identité"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14j"]
    end

    subgraph "Couche 5 : Autorisation des permissions"
        L5["RBAC<br/>Granularité method.path<br/>Super administrateur * "]
    end

    subgraph "Couche 6 : Protection des données"
        L6["ID d'interface : chiffrement Hashids<br/>Corps de requête : chiffrement Encryption<br/>Couche de stockage : chiffrement Encryptable<br/>Export : masquage + copyright"]
    end

    subgraph "Couche 7 : Traçabilité d'audit"
        L7["OperationLog<br/>Enregistre toutes les opérations<br/>Utilisateur/IP/heure/plateforme source/paramètres"]
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

## 13. Topologie de déploiement

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Serveur web"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirection<br/>gzip on"]
        STA["Fichiers statiques<br/>Flutter Web build/"]
    end

    subgraph "Serveur applicatif (extensible horizontalement)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Couche de données"
        MYSQL["MySQL 8.0<br/>Réplication maître-esclave<br/>Préfixe erp_"]
        ES["Elasticsearch 8.x<br/>Cluster de 3 nœuds<br/>Préfixe erp_"]
        REDIS["Redis 7.x<br/>Mode sentinelle<br/>poster:captcha:*"]
    end

    subgraph "Surveillance"
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

## 14. Architecture globale du système ERP

```mermaid
graph TB
    subgraph Client["Couche client"]
        FW["Flutter Web<br/>Console d'administration PC"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>Application native HarmonyOS"]
    end

    subgraph Gateway["Couche passerelle API"]
        MW["Chaîne de middleware<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["Couche des modules métier"]
        direction LR
        Admin["Gestion système<br/>Utilisateurs/rôles/permissions/config/logs"]
        Product["Gestion des produits<br/>Produits/catégories/marques/entrepôts/fournisseurs/clients"]
        Purchase["Gestion des achats<br/>Demande→commande→réception→retour→règlement"]
        Sales["Gestion des ventes<br/>Devis→commande→expédition→retour→règlement"]
        Inventory["Gestion des stocks<br/>Entrées-sorties/lots/inventaires/transferts/alertes"]
        Finance["Gestion financière<br/>Comptes/pièces/comptes à recevoir et à payer/grand livre/auxiliaire/rapports/notes de frais"]
        CRM["CRM<br/>Clients/contacts/suivi/entonnoir/bassin commun/devis/contrats"]
        Workflow["Flux d'approbation<br/>Définition du flux/soumission/approbation/refus/retrait"]
        Notification["Notifications<br/>Liste de notifications/lues/compteur de non-lues"]
        Project["Gestion de projet<br/>Projets/tâches/enregistrement des heures"]
        HR["Ressources humaines<br/>Départements/employés/postes/présences/congés/salaires"]
        Manufacturing["Production<br/>BOM/ordres de production/gammes/postes de travail/MRP"]
        Report["Rapports personnalisés<br/>Modèles de rapport/datasets/champs/filtres/planifications"]
    end

    subgraph Service["Couche des services métier"]
        IS["InventoryService<br/>Entrées-sorties + coût moyen pondéré mobile"]
        FS["FinanceService<br/>Génération automatique des comptes à recevoir et à payer + rapprochement"]
        NS["NotificationService<br/>Envoi unifié des notifications"]
    end

    subgraph Data["Couche de données"]
        MySQL["MySQL 8.0<br/>163 tables métier"]
        Redis["Redis 7<br/>Cache/limitation de débit/Session"]
        ES["Elasticsearch 8<br/>Recherche plein texte"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Flux de données inter-modules

```mermaid
sequenceDiagram
    participant PO as Réception d'achat
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Table des stocks
    participant COST as Enregistrements de coûts
    participant ARAP as Comptes à recevoir et à payer

    PO->>IS: stockIn(produit,quantité,prix unitaire)
    IS->>INV: Mise à jour du stock temps réel (avec verrou)
    IS->>COST: Recalcul du coût moyen pondéré mobile
    IS-->>PO: Renvoi de l'ID de flux
    
    PO->>FS: createAp(fournisseur,montant)
    FS->>ARAP: Génération de l'enregistrement à payer
    
    Note over PO,ARAP: Expédition de vente de même : stockOut + createAr
```

---

## 16. Flux de données du calcul des coûts de stock

```mermaid
graph LR
    A[Réception d'achat 100 ¥×10 unités] --> B[Flux d'entrée]
    C[Réception d'achat 130 ¥×20 unités] --> D[Flux d'entrée]
    B --> E[Stock : 10 unités, coût 100]
    D --> F[Stock : 30 unités, coût 120]
    E --> G[Moyenne pondérée mobile : 100]
    F --> H[Moyenne pondérée mobile : 120]
    H --> I[Sortie comptabilisée au coût 120]
```

---

## 17. Flux de données du flux d'approbation

```mermaid
sequenceDiagram
    participant Biz as Module métier
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Moteur de flux de travail
    participant NTF as NotificationService

    Biz->>WF: Soumission de l'approbation (numéro métier, type de module)
    WF->>WFE: Correspondance avec la définition du flux → création de l'instance d'approbation
    WFE->>APR: Notification du premier approbateur de nœud
    APR->>NTF: Envoi de la notification d'approbation
    NTF-->>APR: Notification envoyée
    APR->>APR: L'approbateur approuve/refuse
    alt Approbation
        APR->>WFE: Passage au nœud suivant
        alt Tous les nœuds approuvés
            WFE->>Biz: Rappel : approbation réussie, mise à jour du statut du document métier
        end
    else Refus
        WFE->>Biz: Rappel : approbation refusée
    end
```

---

## 18. Flux de données des notifications

```mermaid
sequenceDiagram
    participant Event as Source de déclenchement d'événement
    participant NS as NotificationService
    participant DB as Table des notifications
    participant User as Utilisateur

    Event->>NS: Déclenchement de la notification (type, titre, contenu, destinataires)
    NS->>DB: Écriture de l'enregistrement de notification
    NS-->>User: Push (message interne/WebSocket)
    User->>NS: Marquage comme lu
    NS->>DB: Mise à jour du statut lu
    User->>NS: Consultation du compteur de non-lues
    NS-->>User: Nombre de non-lues
```

---

## 19. Flux de données MRP (planification des besoins en matériaux)

```mermaid
sequenceDiagram
    participant SO as Commande de vente
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Suggestion d'achat
    participant MO as Suggestion de production

    SO->>MRP: Besoins de la commande de vente
    MRP->>BOM: Explosion de la BOM pour obtenir la liste des matériaux
    BOM-->>MRP: Matériaux + quantités standard
    MRP->>INV: Consultation de la quantité disponible en stock
    INV-->>MRP: Quantité en stock
    MRP->>MRP: Calcul des besoins nets = besoins bruts - stock
    alt Matières premières insuffisantes
        MRP->>PO: Génération de la suggestion d'achat
    else Produits semi-finis insuffisants
        MRP->>MO: Génération de la suggestion de production
    end
```

---

## 20. Table de correspondance modules ERP contrôleur-service-modèle

> Note sur la couche service : la colonne `Service central` indique les services métier déjà extraits pour ce module ; les modules marqués **⚠ Le contrôleur interroge directement les modèles, dette technique connue** voient leurs contrôleurs appeler directement les méthodes d'interrogation/écriture des modèles (`XxxModel::find/where/save` etc.) sans couche service extraite — dette technique connue, à résorber progressivement selon le modèle d'extraction légère P2-F2 de la couche service (`app/service/AbstractCrudService` base CRUD générique + Service par module).

| Module | Controllers (répertoire) | Service central | Modèles principaux | Nombre de tables |
|------|-------------------|-------------|-----------|------|
| Gestion système | admin/controller/ (14) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | AdminUser, AdminRole, AdminPermission | 7 |
| Gestion des produits | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| Gestion des achats | controller/purchase/ (5) | InventoryService, FinanceService ⚠CRUD encore en requête directe, dette technique connue | PurchaseOrder, PurchaseReceive | 9 |
| Gestion des ventes | controller/sales/ (5) | InventoryService, FinanceService ⚠CRUD encore en requête directe, dette technique connue | SalesOrder, SalesDelivery | 9 |
| Gestion des stocks | controller/inventory/ (5) | InventoryService ⚠CRUD encore en requête directe, dette technique connue | Inventory, InventoryFlow, CostRecord | 11 |
| Gestion financière | controller/finance/ (20) | FinanceService ⚠CRUD encore en requête directe, dette technique connue | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Flux d'approbation | controller/workflow/ (2) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Notifications | controller/notification/ (1) | NotificationService ⚠CRUD encore en requête directe, dette technique connue | Notification, NotificationSetting, NotificationTemplate | 3 |
| Gestion de projet | controller/project/ (3) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| Ressources humaines | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| Production | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| Rapports personnalisés | controller/report/ (2) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| Gestion d'équipements EAM | controller/eam/ (4) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| Gestion documentaire DMS | controller/dms/ (2) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| Tableaux de bord BI | controller/bi/ (3) | - ⚠Le contrôleur interroge directement les modèles, dette technique connue | BiDashboard, BiWidget | 2 |

### 20.1 Journal d'extraction légère de la couche service P2-F2 (crm/hr/manufacturing/product extraits)

| Module | Appels directs du contrôleur avant extraction | Après extraction | Nouveaux Services | Contenu extrait |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | CRUD générique + transitions de statut de contrat, devis→contrat, réclamation/libération du bassin commun, assignation/résolution/réponse des tickets, nettoyage en cascade des lignes de détail, construction des données des rapports d'analyse |
| Ressources humaines | 38 | 0 | `app/service/hr/HrService.php` | CRUD générique + détection retard/départ anticipé des pointages, approbation des congés (génération automatique des pointages de congé), unicité du salaire/calcul du net/versement/génération en masse |
| Production | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | CRUD générique + transitions de début/fin d'ordre de travail, copie des versions BOM/mutualité exclusive d'activation, génération des lignes de détail MRP |
| Gestion des produits | 29 | 0 | `app/service/product/ProductService.php` | CRUD générique + création transactionnelle du produit (SKU/prix), mise à jour par champs en conservant les valeurs d'origine, chargement associé des détails |

Modèle d'extraction : `app/service/AbstractCrudService.php` fournit le CRUD générique `list/all/find/create/update/delete/deleteWhere`
et les assistants de logique pure `normalizePageParams/canTransition` ; les Services de module en héritent et y déposent la logique métier spécifique.
Les contrôleurs injectent le service via `Container::get(XxxService::class)` (repli class_exists), en conservant strictement la structure des routes/paramètres/réponses ;
l'encodage/décodage hashid, la double confirmation du mot de passe, l'emballage des réponses et autres préoccupations HTTP restent dans le contrôleur.
Les nouveaux Services sont enregistrés dans `config/dependence.php` (ce fichier est une config morte, non chargée par addDefinitions ; l'instanciation se fait via le
repli class_exists du conteneur à l'exécution, d'où des Services tous sans constructeur).

Les modules non extraits (gestion de projet 18 fois, rapports personnalisés 18 fois, achats 24 fois, ventes 24 fois, gestion système 42 fois etc.) sont marqués
« le contrôleur interroge directement les modèles, dette technique connue » dans le tableau, et seront extraits selon le même modèle lors des itérations suivantes.

---

## Modules d'extension OMS/WMS/TMS (2026-08)

### OMS (Order Management System) — 8 tables
- **Extension de commande** (`erp_oms_order`) : agrégation multicanal/statut de traitement/statut de paiement/priorité
- **Adresses de commande** (`erp_oms_order_address`) : adresses de livraison/facturation (format multilingue)
- **Enregistrements de traitement** (`erp_oms_fulfillment`+`_item`) : suivi des quantités allouées/prélevées/emballées/expédiées
- **RMA** (`erp_oms_rma`+`_item`) : cycle de vie complet des retours et échanges
- **Réservation de stock** (`erp_oms_inventory_reservation`) : ATP = physical - reserved
- **Canaux** (`erp_channel`) : direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tables
- **Zones et emplacements** (`erp_wms_zone`, `erp_wms_location`) : zone→aisle→rack→level→bin
- **Entrées** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Sorties** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 tables
- **Transporteurs** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Connaissements** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Factures** (`erp_tms_freight_invoice`)

### Flux de données
```
OMS : Commande du canal → Réservation de stock (ATP) → Création du traitement → WMS
WMS : Vague → Prélèvement → Emballage → Envoi TMS
TMS : Comparaison des tarifs → Expédition → Confirmation (stockOut + AR) → Suivi → Livraison
Entrée WMS : ASN → Réception → Rangement (stockIn + AP)
RMA : Demande → Approbation → Retour → Réception (stockIn) → Remboursement
```

---

## 21. Feuille de route de l'écosystème (2026-08)

> Spécifications de conception détaillées : `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Évaluation de base (au lancement de la feuille de route)

> P0~P3 entièrement livrés, score global actuel 89/100 (voir CLAUDE.md) ; le tableau ci-dessous est la photographie de base avant le lancement de la feuille de route.

| Dimension | Score | Écart clé |
|------|------|----------|
| API backend | 85/100 | plusieurs modules sont des squelettes CRUD, il manque les moteurs de calcul métier |
| Protection de sécurité | 95/100 | défense en profondeur sur 18 couches, prête pour la production |
| UI frontend | 20/100 | **plus grande lacune** : les 12 pages Flutter couvrent ~20 % des modules, pas de panneau d'administration Web |
| Écosystème d'exploitation | 70/100 | manquent rollback de migration, sauvegarde automatique, observabilité |
| Profondeur métier | 55/100 | algorithmes centraux finance/RH/fabrication non implémentés |
| **Global** | **65/100** | |

### 21.2 Feuille de route séquentielle en quatre phases

```
P0(3-4 semaines) → P1(4-6 semaines) → P2(1-2 semaines) → P3(2-3 semaines) = environ 13 semaines au total
```

| Phase | Nom | Livraison centrale |
|------|------|----------|
| **P0** | Écosystème frontend | panneau d'administration Flutter Web tous modules (14 modules 40+ pages), bibliothèque de composants génériques, alignement HarmonyOS |
| **P1** | Profondeur métier | moteur de comptabilité en partie double, moteur de calcul des salaires, moteur MRP, module qualité, notifications temps réel (WebSocket) |
| **P2** | Fiabilité opérationnelle | rollback des migrations de base de données, sauvegarde automatique renforcée, traçage OpenTelemetry, moteur de file de messages RabbitMQ |
| **P3** | Expérience renforcée | tableaux de bord BI par glisser-déposer, gestion d'équipements (EAM), isolation multi-tenant, gestion documentaire (DMS) |

### 21.3 Évolution de la chaîne de middleware

```
Actuel :   Locale → Cors → SecurityFilter → RateLimit → TracingId → {groupe de routes}
Après P1 :  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {groupe de routes}
Après P2 :  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {groupe de routes}
Après P3 :  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {groupe de routes}
```

### 21.4 Architecture cible P0 — Panneau d'administration Flutter Web

| Couche | Contenu ajouté |
|------|----------|
| Couche de disposition | `AdminLayout` disposition PC à trois colonnes (barre latérale repliable + barre supérieure + zone de contenu) |
| Couche de composants | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Couche de pages | extension des 12 pages actuelles vers une couverture complète de 14 modules 40+ pages |
| Couche de services | réutilisation des `ApiService`, `AuthService`, `CaptchaService`, `ExportService` existants |

### 21.5 Architecture cible P1 — Moteurs de calcul métier

| Moteur | Classe de service | Règles clés |
|------|--------|----------|
| Comptabilité en partie double | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | validation forcée de l'équilibre débit-crédit, clôture et report des résultats de fin de période, conversion des taux multi-devises |
| Calcul des salaires | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | bornes de base de la sécurité sociale, taux du fonds de logement, barème progressif de l'impôt sur le revenu, virement bancaire |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | explosion BOM couche par couche + pertes, code de niveau bas (LLC), stock de sécurité, règles par lots |
| Qualité | `QmsInspectionService` | circulation des trois documents IQC entrée/IPQC process/OQC sortie |
| Notifications | `WebSocketService`, `ChannelRouter` | multicanal interne/e-mail/WeCom/DingTalk |

### 21.6 Récapitulatif des modifications du modèle de données

| Phase | Nouvelles tables | Modules concernés |
|------|----------|----------|
| P0 | 0 | frontend pur, aucun changement de table |
| P1 | 14 | Finance (2) + RH (3) + Fabrication (2) + Qualité (5) + Notifications (2) |
| P3 | 7 | BI (2) + EAM (3) + DMS (2) |

---

## 22. Multi-tenant (capacité réservée, non activée)

> Mention de copyright comme en tête de fichier : Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Positionnement et décision

Dans ce projet, le multi-tenant est positionné comme **capacité réservée** : il n'est **pas branché, pas activé** dans cette itération (dégradation documentée). Conformément à la planification :
la facturation SaaS, l'auto-ouverture des locataires et autres « offres commerciales complètes du multi-tenant » ne font pas partie du périmètre de construction de ce projet ; cette itération ne conserve que le squelette
de code minimal (middleware + Trait de modèle) avec les étapes d'activation, pour une mise en service ultérieure à la demande.
Note : le « isolation multi-tenant » du P3 de la feuille de route §21.2 est en conséquence ajusté en « capacité réservée (dégradation documentée) », en conservant le squelette sans branchement.

Base de décision (revue 2026-08) :
- les déploiements existants sont presque tous mono-tenant ; le branchement introduirait une complexité d'isolation inutile et un risque de régression ;
- le squelette actuel présente des défauts techniques (voir 22.4) ; « branché = isolé » n'est pas vérifié, une correction de conception est d'abord nécessaire ;
- l'isolation exige d'ajouter des colonnes à chacune des tables métier parmi les 163 tables et d'activer le trait sur chaque modèle, un coût bien supérieur à un « branchement minimal ».

### 22.2 Faits actuels (vérification du code et de la configuration)

| Élément | État actuel |
|----|------|
| `app/middleware/TenantScope.php` | existe, non enregistré ; lit le locataire depuis l'en-tête `X-Tenant-Id`, autorise directement si l'en-tête est absent |
| `app/model/concerns/TenantScope.php` | existe, aucun modèle ne l'utilise ; le scope global `bootTenantScope()` ne filtre qu'une fois le locataire défini |
| `config/middleware.php` | chaîne globale : Locale → Cors → SecurityFilter → RateLimit → TracingId, sans TenantScope |
| `config/route.php` groupe /admin | AdminAuth → AdminPermission → OperationLog, sans TenantScope |
| Payload JWT | uniquement `sub` / `username` / `token_type`, **aucune déclaration tenant_id** (`app/api/v1/controller/AuthController.php`) |
| Base de données | **aucune colonne tenant_id dans toute la base** (install.sql non plus) |
| Modèles | **aucun modèle n'utilise le trait TenantScope** |

### 22.3 Étapes d'activation (référence réservée, non exécutées dans cette itération)

1. Enregistrer le middleware : dans `config/route.php`, ajouter au groupe /admin `middleware()`
   `app\middleware\TenantScope::class` (placé après AdminAuth, pour garantir l'authentification).
2. Le demandeur porte `X-Tenant-Id` (ID de locataire int) dans l'en-tête de requête.
3. Ajouter la colonne `tenant_id` (BIGINT + index) aux tables métier à isoler et réinjecter les données existantes ;
   les tables de dictionnaire/système (comme `erp_admin_user`, `erp_role`, `erp_permission`) ne sont pas isolées.
4. Utiliser `app\model\concerns\TenantScope;` dans les classes de modèles à isoler, pour filtrer automatiquement selon le locataire courant.
5. (Optionnel) si le locataire doit être lu depuis le JWT plutôt que l'en-tête : étendre le payload de connexion avec une déclaration `tenant_id`,
   et lire `$payload['tenant_id']` dans le middleware.

### 22.4 Limitations techniques connues (à résoudre avant l'activation)

- **Chaîne de transmission statique rompue (testé sur PHP 8.3)** : le middleware appelle `setCurrentTenantId()` via le nom du trait,
  qui écrit dans la copie statique du trait lui-même ; les classes de modèles utilisant ce trait ne peuvent pas la lire, les requêtes ne sont donc pas filtrées.
  À l'activation, passer à une injection basée sur le contexte de requête (comme `request()->tenantId`).
- **Interférence de l'état global statique** : Workerman est un processus résident, les propriétés statiques sont partagées entre requêtes ; en mode coroutine
  (Swoole/Swow), une interférence de données inter-locataires se produirait ; passer à une liaison au niveau requête (`context()` / objet requête).
- **Lacune du plan de données** : aucune colonne tenant_id dans toute la base, nécessite une migration table par table ; les tables de dictionnaire partagées inter-locataires nécessitent un mécanisme d'exemption à concevoir.

### 22.5 Critères d'acceptation

Acceptation de cette itération = cohérence entre documentation et code : `config/middleware.php` et `config/route.php` ne contiennent pas
d'enregistrement TenantScope ; les commentaires du middleware et du Trait indiquent clairement « capacité réservée, non activée » avec les étapes d'activation ;
chaque point de cette section correspond à l'état du code.
