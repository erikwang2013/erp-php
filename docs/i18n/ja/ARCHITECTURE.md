# アーキテクチャ設計図と業務ロジック図

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> 以下の Mermaid 図表は GitHub / GitLab / VS Code で自動レンダリングされます。その他の環境では [Mermaid Live Editor](https://mermaid.live/) で確認してください。

---

## 1. システムトポロジアーキテクチャ

```mermaid
flowchart TB
    subgraph "クライアント層"
        A1["Flutter Web<br/>PC管理バックエンド<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>スマホ/タブレットクライアント"]
    end

    subgraph "ゲートウェイ/エッジ層 (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>リバースプロキシ + HTTPS + Gzip<br/>静的ファイルサービス"]
    end

    subgraph "アプリケーション層 (webman v2)"
        C_LOC["Locale ミドルウェア<br/>Accept-Language 自動検出"]
        C0["ApiVersion ミドルウェア<br/>API-Version ヘッダー検証"]
        C1["AdminAuth ミドルウェア<br/>JWT 検証"]
        C2["AdminPermission ミドルウェア<br/>RBAC 権限チェック"]
        C3["管理側 Controller<br/>Dashboard / User / Role / Permission"]
        C4["公開 Controller v1<br/>Captcha / Auth"]
        C5["Common Services<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "ストレージ層"
        D1[("MySQL 8.0<br/>メインストレージ<br/>テーブルプレフィックス erp_")]
        D2[("Elasticsearch<br/>全文検索<br/>インデックスプレフィックス erp_")]
        D3[("Redis<br/>Session / キャッシュ<br/>Captcha 保存")]
    end

    subgraph "外部"
        E1["DevEco Studio<br/>HarmonyOS ビルド"]
        E2["Flutter SDK<br/>Web ビルド"]
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

## 2. バックエンド階層アーキテクチャ

```mermaid
flowchart TD
    subgraph "ルーティング層 (Route Layer)"
        R1["config/route.php<br/>URL → Controller マッピング"]
    end

    subgraph "ミドルウェア層 (Middleware Layer)"
        M_LOC["Locale<br/>Accept-Language 自動検出<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Redis スライディングウィンドウ制限<br/>X-RateLimit レスポンスヘッダー"]
        M_SF["SecurityFilter<br/>攻撃検知ブロック<br/>XSS/SQLインジェクション/パストラバーサル/CSRF"]
        M0["ApiVersion<br/>API バージョン検証<br/>apiVersion 注入"]
        M1["AdminAuth<br/>JWT Token 検証<br/>adminId 注入"]
        M2["AdminPermission<br/>RBAC 認可<br/>method.path マッチング<br/>Redis 60s 権限キャッシュ"]
    end

    subgraph "コントローラー層 (Controller Layer)"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + 検索 + ページング"]
        CT3["RoleController<br/>CRUD + 権限同期"]
        CT4["PermissionController<br/>CRUD + ツリー構築"]
        CT5["DashboardController<br/>統計/トレンド/分布"]
        CT6["ExportController<br/>Excel/PDF エクスポート"]
        CT7["CaptchaController<br/>認証コード生成/検証"]
        CT8["AuthController<br/>ログイン/登録/リフレッシュ"]
    end

    subgraph "サービス層 (Service Layer)"
        S1["HashidsService<br/>ID エンコード/デコード"]
        S2["SnowflakeService<br/>グローバル一意 ID 生成"]
        S3["EncryptionService<br/>暗号化/復号 + マスキング"]
    end

    subgraph "モデル層 (Model Layer)"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "ドライバー層 (Driver Layer)"
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

### ERP 業務層の拡張

システムが純粋な管理バックエンドから完全な ERP システムへと進化するにつれ、コントローラー層とサービス層に以下の業務モジュールが追加されました：

| 階層 | ディレクトリ | 説明 |
|------|------|------|
| 業務コントローラー | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70 個、モジュールごとに分類され業務リクエストを処理 |
| 業務サービス | `app/service/{inventory,finance,notification}/` | 在庫入出庫+コスト計算、財務の売掛/買掛+消込、通知送信 |

---

## 3. リクエストライフサイクル

```mermaid
sequenceDiagram
    participant C as クライアント
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

    C->>N: HTTPS リクエスト<br/>Header: API-Version: v1
    N->>MW_LOC: 転送
    MW_LOC->>MW_LOC: Accept-Language を解析<br/>locale を設定
    MW_LOC->>MW_SF: 通過

    alt 非標準 HTTP メソッド (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else メソッド合法 (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: メソッドホワイトリストチェック通過
    end

    alt 攻撃検知発動
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: 通過

    alt レート制限発動
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: 通過

    alt サポート外のバージョン
        MW0-->>C: 400 サポート外のAPIバージョン
    else バージョン有効
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Token 欠落または無効
        MW1-->>C: 401 Unauthorized
    else Token 有効
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt 権限なし
        MW2-->>C: 403 Forbidden
    else 権限あり
        MW2->>CTL: コントローラーへ進入
    end

    CTL->>CTL: パラメータ検証 (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt 機密操作 (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt パスワードエラー
            CTL-->>C: 422 パスワード検証失敗
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable cast 自動復号
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash string

    CTL->>CTL: レスポンス JSON を構築
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: 操作ログを記録 (POST/PUT/DELETE)
```

---

## 4. 認証と認証コードのフロー

```mermaid
sequenceDiagram
    participant U as ユーザー
    participant CL as クライアント
    participant SV as サーバー
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === ステップ1: 認証コード取得 ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: 300×200 背景画像を生成
    CAP->>CAP: ランダムに N 個の中国語ターゲットを配置
    CAP->>CAP: key を生成、targets を保存
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === ステップ2: ユーザークリック ===
    CL->>CL: 認証コード画像をレンダリング
    CL->>CL: プロンプト "順番にクリックしてください: 木 → 鳥 → 花"
    U->>CL: 画像内の文字位置を順にクリック
    CL->>CL: clicks を収集: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === ステップ3: ログイン ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt 認証コードエラー
        CAP-->>SV: false
        SV-->>CL: 422 認証コードエラー
    else 認証コード正しい
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt 認証情報エラー
            SV-->>CL: 401 ユーザー名またはパスワードエラー
        else 認証情報正しい
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14d)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === 後続リクエスト ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { dashboard data }
```

---

## 5. RBAC 権限モデル

```mermaid
flowchart LR
    subgraph "ユーザー (User)"
        U1["admin<br/>(スーパー管理者)"]
        U2["editor<br/>(編集者)"]
        U3["viewer<br/>(読み取り専用)"]
    end

    subgraph "ロール (Role)"
        R1["super_admin<br/>権限識別子: *"]
        R2["editor<br/>権限識別子: get.*, post.*"]
        R3["viewer<br/>権限識別子: get.*"]
    end

    subgraph "権限 (Permission) (ツリー)"
        P1["dashboard<br/>type=1 メニュー"]
        P2["user<br/>type=1 メニュー"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 ボタン"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (全権限)"| P1 & P2 & P3 & P4 & P5 & P6
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
    subgraph "権限タイプ"
        T1["type=1 メニュー<br/>サイドバー表示/非表示を制御"]
        T2["type=2 ボタン<br/>ページ操作ボタンを制御"]
        T3["type=3 API<br/>API アクセスを制御"]
    end

    subgraph "権限識別子の形式"
        F1["{method}.{path}<br/>例: get.admin/user<br/>例: post.admin/user<br/>例: delete.admin/role"]
    end

    subgraph "判定フロー"
        J1["Token を抽出 → adminId"]
        J2["ユーザーロールを検索"]
        J3["すべての権限 slug を収集"]
        J4["method.path を構築"]
        J5{"マッチ?"}
        J6["通過"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"はい / slug=*"| J6
        J5 -->|いいえ| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. ID の全ライフサイクル

```mermaid
flowchart LR
    subgraph "1. 生成"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>例: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. 保存"
        S1["MySQL erp_* テーブル<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["機密フィールド<br/>encryptable cast<br/>AES-128-ECB 暗号化"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. 転送"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid 文字列<br/>例: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. 逆デコード"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. データ暗号化の階層

```mermaid
flowchart TB
    subgraph "転送層の暗号化 (encryption)"
        E1["クライアントが機密データを送信"]
        E2["AES-256-CBC 暗号化"]
        E3["API で暗号文を転送"]
        E4["サーバーで復号処理"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "保存層の暗号化 (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["書き込み: 自動暗号化"]
        D3["MySQL VARCHAR(500)<br/>暗号文を保存"]
        D4["読み取り: 自動復号"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "表示層のマスキング (mask)"
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

## 8. データベース ER 関係

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "暗号化"
        VARCHAR phone "暗号化"
        VARCHAR id_card "暗号化"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "ソフト削除"
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
        BIGINT parent_id FK "自己参照"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1メニュー2ボタン3API"
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
        VARCHAR source "取得元"
        TEXT input "マスキング"
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

## 9. エクスポート業務フロー

```mermaid
sequenceDiagram
    participant C as クライアント
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as ファイルシステム

    Note over C,FS: === Excel エクスポート ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: データ
    CTL->>CTL: 機密フィールドを復号
    CTL->>CTL: マスキング処理 (maskPhone/maskEmail)
    CTL->>CTL: PhpSpreadsheet で構築<br/>ヘッダー青背景白文字<br/>データ行細枠線<br/>先頭行固定<br/>自動フィルター
    CTL->>FS: runtime/tmp/export_*.xlsx に書き込み
    CTL-->>C: ファイルダウンロード

    Note over C,FS: === PDF エクスポート ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>ページヘッダー: タイトル+著作権+時間<br/>内容: テーブルまたはカード<br/>ページフッター: 削除不可の著作権
    CTL->>CTL: Dompdf で A4 横向きレンダリング
    CTL->>FS: runtime/tmp/export_*.pdf に書き込み
    CTL-->>C: ファイルダウンロード
```

---

## 10. Flutter Web コンポーネントツリー

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["ログインフォーム<br/>ユーザー名/パスワード/認証コード"]
    LF --> CAPTCHA["クリック認証コードコンポーネント<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>クリックで Circle をマーク"]

    DB --> SIDEBAR["サイドバー NavigationDrawer<br/>折りたたみ可能 64px / 240px<br/>ダッシュボード/ユーザー/ロール/設定/ログ"]
    DB --> HEADER["トップバー 56px<br/>折りたたみボタン + ユーザーメニュー<br/>ログアウト AlertDialog"]
    DB --> CONTENT["コンテンツエリア"]
    CONTENT --> DASH["DashboardPage<br/>統計カード GridView<br/>トレンド折れ線グラフ LineChart<br/>分布円グラフ PieChart<br/>最近の操作 ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. HarmonyOS ページルーティング

```mermaid
flowchart LR
    EA["EntryAbility<br/>起動"]
    EA -->|"Token なし"| LP["LoginPage<br/>ログインページ"]
    EA -->|"Token あり"| DP["DashboardPage<br/>ダッシュボード"]

    LP -->|"ログイン成功<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>ユーザーリスト"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>個人センター"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>ユーザー詳細/新規/編集"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"ログアウト<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. セキュリティ多層防御の全体像

```mermaid
flowchart TB
    subgraph "第1層: 人機認証"
        L1["クリック認証コード<br/>Click Captcha<br/>ログイン/登録で強制"]
    end

    subgraph "第2層: 操作確認"
        L2["パスワード再確認<br/>confirmPassword()<br/>DELETE 操作で必須"]
    end

    subgraph "第3層: 転送セキュリティ"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "第4層: 本人認証"
        L4["JWT HS256<br/>access_token 2h<br/>refresh_token 14d"]
    end

    subgraph "第5層: 権限認可"
        L5["RBAC<br/>method.path 粒度<br/>スーパー管理者 * "]
    end

    subgraph "第6層: データ保護"
        L6["API ID: Hashids 暗号化<br/>リクエストボディ: Encryption 暗号化<br/>保存層: Encryptable 暗号化<br/>エクスポート: マスキング+著作権"]
    end

    subgraph "第7層: 監査追跡"
        L7["OperationLog<br/>すべての操作を記録<br/>ユーザー/IP/時間/取得元/パラメータ"]
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

## 13. デプロイメントトポロジ

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Web サーバー"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["静的ファイル<br/>Flutter Web build/"]
    end

    subgraph "アプリケーションサーバー (水平スケーラブル)"
        WM1["webman worker 1<br/>:8788"]
        WM2["webman worker 2<br/>:8788"]
        WM3["webman worker N<br/>:8788"]
    end

    subgraph "データ層"
        MYSQL["MySQL 8.0<br/>マスタースレーブレプリケーション<br/>erp_ プレフィックス"]
        ES["Elasticsearch 8.x<br/>3 ノードクラスタ<br/>erp_ プレフィックス"]
        REDIS["Redis 7.x<br/>センチネルモード<br/>poster:captcha:*"]
    end

    subgraph "監視"
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

## 14. ERP システム全体アーキテクチャ

```mermaid
graph TB
    subgraph Client["クライアント層"]
        FW["Flutter Web<br/>PC管理バックエンド"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>ネイティブApp"]
    end

    subgraph Gateway["API ゲートウェイ層"]
        MW["ミドルウェアチェーン<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["業務モジュール層"]
        direction LR
        Admin["システム管理<br/>ユーザー/ロール/権限/設定/ログ"]
        Product["商品管理<br/>商品/カテゴリ/ブランド/倉庫/サプライヤー/顧客"]
        Purchase["仕入れ管理<br/>申請→注文→入荷→返品→決済"]
        Sales["販売管理<br/>見積→注文→出荷→返品→決済"]
        Inventory["在庫管理<br/>入出庫/ロット/棚卸/振替/アラート"]
        Finance["財務管理<br/>科目/伝票/売掛買掛/総勘定元帳/明細帳/レポート/経費精算"]
        CRM["CRM<br/>顧客/連絡先/フォロー/ファネル/共有プール/見積/契約"]
        Workflow["承認ワークフロー<br/>ワークフロー定義/提出/承認/拒否/撤回"]
        Notification["メッセージ通知<br/>通知リスト/既読/未読カウント"]
        Project["プロジェクト管理<br/>プロジェクト/タスク/工数記録"]
        HR["人事管理<br/>部門/従業員/役職/勤怠/休暇/給与"]
        Manufacturing["生産製造<br/>BOM/製造オーダー/工順/ワークステーション/MRP"]
        Report["カスタムレポート<br/>レポートテンプレート/データセット/フィールド/フィルター/スケジュール"]
    end

    subgraph Service["業務サービス層"]
        IS["InventoryService<br/>入出庫+移動平均コスト"]
        FS["FinanceService<br/>売掛買掛の自動生成+消込"]
        NS["NotificationService<br/>通知の一括送信"]
    end

    subgraph Data["データ層"]
        MySQL["MySQL 8.0<br/>163 の業務テーブル"]
        Redis["Redis 7<br/>キャッシュ/レート制限/Session"]
        ES["Elasticsearch 8<br/>全文検索"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. モジュール間データフロー

```mermaid
sequenceDiagram
    participant PO as 仕入れ入荷
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as 在庫テーブル
    participant COST as コスト記録
    participant ARAP as 売掛買掛

    PO->>IS: stockIn(商品,数量,単価)
    IS->>INV: リアルタイム在庫を更新(ロック)
    IS->>COST: 移動平均コストを再計算
    IS-->>PO: フローIDを返却
    
    PO->>FS: createAp(サプライヤー,金額)
    FS->>ARAP: 買掛記録を生成
    
    Note over PO,ARAP: 販売出荷も同様: stockOut + createAr
```

---

## 16. 在庫コスト計算データフロー

```mermaid
graph LR
    A[仕入れ入荷 100元×10個] --> B[入庫フロー]
    C[仕入れ入荷 130元×20個] --> D[入庫フロー]
    B --> E[在庫: 10個, コスト100]
    D --> F[在庫: 30個, コスト120]
    E --> G[移動平均: 100]
    F --> H[移動平均: 120]
    H --> I[出庫は120でコスト計算]
```

---

## 17. 承認ワークフローデータフロー

```mermaid
sequenceDiagram
    participant Biz as 業務モジュール
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as ワークフローエンジン
    participant NTF as NotificationService

    Biz->>WF: 承認を提出(業務伝票番号,モジュールタイプ)
    WF->>WFE: ワークフロー定義をマッチング→承認インスタンスを作成
    WFE->>APR: 最初のノード承認者に通知
    APR->>NTF: 承認通知を送信
    NTF-->>APR: 通知送信済み
    APR->>APR: 承認者が承認/拒否
    alt 承認
        APR->>WFE: 次のノードへ遷移
        alt 全ノード通過
            WFE->>Biz: コールバック: 承認通過、業務伝票ステータスを更新
        end
    else 拒否
        WFE->>Biz: コールバック: 承認拒否
    end
```

---

## 18. メッセージ通知データフロー

```mermaid
sequenceDiagram
    participant Event as イベントトリガー源
    participant NS as NotificationService
    participant DB as 通知テーブル
    participant User as ユーザー

    Event->>NS: 通知をトリガー(タイプ,タイトル,内容,受信者)
    NS->>DB: 通知レコードを書き込み
    NS-->>User: プッシュ(サイト内メッセージ/WebSocket)
    User->>NS: 既読マーク
    NS->>DB: 既読状態を更新
    User->>NS: 未読数を照会
    NS-->>User: 未読数
```

---

## 19. MRP 部品所要計画データフロー

```mermaid
sequenceDiagram
    participant SO as 販売注文
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as 仕入れ提案
    participant MO as 生産提案

    SO->>MRP: 販売注文の需要
    MRP->>BOM: BOMを展開して部品リストを取得
    BOM-->>MRP: 部品+標準使用量
    MRP->>INV: 在庫利用可能量を照会
    INV-->>MRP: 在庫数量
    MRP->>MRP: 純所要量 = 総所要量 - 在庫 を計算
    alt 原材料不足
        MRP->>PO: 仕入れ提案を生成
    else 半製品不足
        MRP->>MO: 生産提案を生成
    end
```

---

## 20. ERP モジュール コントローラー-サービス-モデル対応表

> サービス層の説明：`核心Service` 列は、そのモジュールに実装済みの業務サービスを示します；**⚠ コントローラーがモデルを直接参照、既知の技術負債** と記載されたモジュールは、コントローラーが引き続きモデルの照会/書き込みメソッド（`XxxModel::find/where/save` など）を直接呼び出しており、サービス層をまだ抽出しておらず、既知の技術負債に該当します。今後は P2-F2 サービス層軽量抽出パターン（`app/service/AbstractCrudService` 汎用 CRUD 基底クラス + モジュール Service）に沿って段階的に収束させます。

| モジュール | Controllers (ディレクトリ) | コアService | 主要Model | テーブル数 |
|------|-------------------|-------------|-----------|------|
| システム管理 | admin/controller/ (14個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | AdminUser, AdminRole, AdminPermission | 7 |
| 商品管理 | controller/product/ (7個) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| 仕入れ管理 | controller/purchase/ (5個) | InventoryService, FinanceService ⚠CRUDは依然として直接参照、既知の技術負債 | PurchaseOrder, PurchaseReceive | 9 |
| 販売管理 | controller/sales/ (5個) | InventoryService, FinanceService ⚠CRUDは依然として直接参照、既知の技術負債 | SalesOrder, SalesDelivery | 9 |
| 在庫管理 | controller/inventory/ (5個) | InventoryService ⚠CRUDは依然として直接参照、既知の技術負債 | Inventory, InventoryFlow, CostRecord | 11 |
| 財務管理 | controller/finance/ (20個) | FinanceService ⚠CRUDは依然として直接参照、既知の技術負債 | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10個) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| 承認ワークフロー | controller/workflow/ (2個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| メッセージ通知 | controller/notification/ (1個) | NotificationService ⚠CRUDは依然として直接参照、既知の技術負債 | Notification, NotificationSetting, NotificationTemplate | 3 |
| プロジェクト管理 | controller/project/ (3個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| 人事管理 | controller/hr/ (5個) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| 生産製造 | controller/manufacturing/ (5個) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| カスタムレポート | controller/report/ (2個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM 設備管理 | controller/eam/ (4個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS 文書管理 | controller/dms/ (2個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI ダッシュボード | controller/bi/ (3個) | - ⚠コントローラーがモデルを直接参照、既知の技術負債 | BiDashboard, BiWidget | 2 |

### 20.1 P2-F2 サービス層軽量抽出記録（crm/hr/manufacturing/product は抽出済み）

| モジュール | 抽出前のコントローラー直接参照呼び出し数 | 抽出後 | 追加 Service | 抽出内容 |
|------|----------------------|--------|--------------|----------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | 汎用 CRUD + 契約ステータス遷移、見積から契約への変換、共有プールの取得/解放、工単の割り当て/解決/返信、明細のカスケード削除、分析レポートデータ構築 |
| 人事管理 | 38 | 0 | `app/service/hr/HrService.php` | 汎用 CRUD + 出勤打刻の遅刻/早退判定、休暇承認（休暇勤怠を自動生成）、給与の一意性/実支給計算/支給/一括生成 |
| 生産製造 | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | 汎用 CRUD + 工単の開始/完了遷移、BOM バージョン複製/有効化の排他、MRP 明細生成 |
| 商品管理 | 29 | 0 | `app/service/product/ProductService.php` | 汎用 CRUD + 商品トランザクション作成（SKU/価格）、フィールド単位の原値保持更新、詳細の関連ロード |

抽出パターン：`app/service/AbstractCrudService.php` が `list/all/find/create/update/delete/deleteWhere` の汎用 CRUD と `normalizePageParams/canTransition` の純ロジックヘルパーを提供；モジュール Service はこれを継承し、モジュール固有の業務を集約します。コントローラーは `Container::get(XxxService::class)`（class_exists フォールバック）でサービスを注入し、ルート/パラメータ/戻り値構造は完全に不変のまま維持；hashid エンコード/デコード、パスワード再確認、レスポンスラッピングなどの HTTP 関心事は引き続きコントローラーに残します。新 Service は `config/dependence.php` に登録済みです（このファイルは dead config で、addDefinitions ではロードされず、実行時にコンテナの class_exists フォールバックでインスタンス化されるため、すべての Service は引数なしコンストラクタを維持）。

未抽出モジュール（プロジェクト管理 18 回、カスタムレポート 18 回、仕入れ 24 回、販売 24 回、システム管理 42 回など）は表中に「コントローラーがモデルを直接参照、既知の技術負債」と記載済みで、今後のイテレーションで同じパターンに沿って抽出します。

---

## OMS/WMS/TMS 拡張モジュール (2026-08)

### OMS (Order Management System) — 8 tables
- **注文拡張** (`erp_oms_order`)：マルチチャネル集約/履行ステータス/支払いステータス/優先度
- **注文住所** (`erp_oms_order_address`)：受取/請求先住所(多国対応フォーマット)
- **履行記録** (`erp_oms_fulfillment`+`_item`)：割当/ピッキング済み/梱包済み/出荷済み数量の追跡
- **RMA** (`erp_oms_rma`+`_item`)：返品交換の全ライフサイクル
- **在庫予約** (`erp_oms_inventory_reservation`)：ATP = physical - reserved
- **チャネル** (`erp_channel`)：direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 tables
- **エリア・ロケーション** (`erp_wms_zone`, `erp_wms_location`)：zone→aisle→rack→level→bin
- **入庫** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **出庫** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 tables
- **運送会社** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **運送伝票** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **請求書** (`erp_tms_freight_invoice`)

### Data Flow
```
OMS: Channel Order → Inventory Reservation (ATP) → Create Fulfillment → WMS
WMS: Wave → Pick → Pack → TMS Shipment
TMS: Rate Shop → Ship → Confirm (stockOut + AR) → Tracking → Delivery
WMS Inbound: ASN → Receive → Putaway (stockIn + AP)
RMA: Request → Approve → Return → Receive (stockIn) → Refund
```

---

## 21. エコシステムロードマップ (2026-08)

> 詳細設計仕様: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 ベースライン評価（ロードマップ開始時）

> P0〜P3 はすべて納品済みで、現在の総合スコアは 89/100（CLAUDE.md 参照）；下表はロードマップ開始前のベースラインスナップショットです。

| 次元 | スコア | 主なギャップ |
|------|------|----------|
| バックエンド API | 85/100 | 多くのモジュールが CRUD の骨組みのみで、業務計算エンジンが不足 |
| セキュリティ防御 | 95/100 | 18 層の多層防御、本番対応済み |
| フロントエンド UI | 20/100 | **最大の弱点**: Flutter 12 ページでモジュールの約 20% のみカバー、Web 管理パネルが未整備 |
| 運用エコシステム | 70/100 | マイグレーションロールバック、自動バックアップ、可観測性が不足 |
| 業務深度 | 55/100 | 財務/人事/製造のコアアルゴリズムが未実装 |
| **総合** | **65/100** | |

### 21.2 4段階シリアルロードマップ

```
P0(3-4周) → P1(4-6周) → P2(1-2周) → P3(2-3周) = 总计约13周
```

| 段階 | 名称 | 主要納品物 |
|------|------|----------|
| **P0** | フロントエンドエコシステム | Flutter Web 全モジュール管理パネル（14 モジュール 40+ ページ）、汎用コンポーネントライブラリ、HarmonyOS 整合 |
| **P1** | 業務深度 | 財務複式簿記エンジン、給与計算エンジン、MRP エンジン、品質管理モジュール、リアルタイム通知(WebSocket) |
| **P2** | 運用信頼性 | データベースマイグレーションロールバック、自動バックアップ強化、OpenTelemetry トレーシング、RabbitMQ キュードライバー |
| **P3** | 体験向上 | BI ドラッグ可能ダッシュボード、設備管理(EAM)、マルチテナント分離、文書管理(DMS) |

### 21.3 ミドルウェアチェーン進化

```
現状:   Locale → Cors → SecurityFilter → RateLimit → TracingId → {路由组}
P1 後:  Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {路由组}
P2 後:  Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {路由组}
P3 後:  Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {路由组}
```

### 21.4 P0 目標アーキテクチャ — Flutter Web 管理パネル

| 階層 | 追加内容 |
|------|----------|
| レイアウト層 | `AdminLayout` PC 3カラムレイアウト（折りたたみ可能なサイドバー + トップバー + コンテンツエリア） |
| コンポーネント層 | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| ページ層 | 既存の 12 ページから 14 モジュール 40+ ページの全カバレッジへ拡張 |
| サービス層 | 既存の `ApiService`, `AuthService`, `CaptchaService`, `ExportService` を再利用 |

### 21.5 P1 目標アーキテクチャ — 業務計算エンジン

| エンジン | サービスクラス | 主要ルール |
|------|--------|----------|
| 複式簿記 | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | 借方貸方バランスの強制検証、期末損益振替、多通貨レート換算 |
| 給与計算 | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | 社会保険基数の上下限、積立金比率、所得税累進税率、銀行振込支給 |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | BOM の階層展開+ロス、低層コード(LLC)、安全在庫、ロット規則 |
| 品質 | `QmsInspectionService` | IQC 入荷/IPQC 工程/OQC 出荷 の3伝票フロー |
| 通知 | `WebSocketService`, `ChannelRouter` | サイト内/メール/WeChat Work/DingTalk マルチチャネル |

### 21.6 データモデル変更サマリー

| 段階 | 追加テーブル数 | 対象モジュール |
|------|----------|----------|
| P0 | 0 | フロントエンドのみ、テーブル変更なし |
| P1 | 14 | 財務(2) + HR(3) + 製造(2) + 品質(5) + 通知(2) |
| P3 | 7 | BI(2) + EAM(3) + DMS(2) |

---

## 22. マルチテナント（予約機能、未有効化）

> 著作権表示はファイルヘッダーと同じ：Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 位置づけと判断

マルチテナントは本プロジェクトでは**予約機能**として位置づけられ、今回の期間では**接続せず、有効化しない**（ドキュメント化されたデグレード）方針です。計画と一致し、SaaS 課金、テナントのセルフプロビジョニングなどの「マルチテナント完全商業化ソリューション」は本プロジェクトの建設範囲外；今回の期間では最小限のコード骨格（ミドルウェア + モデル Trait）のみ残し、有効化手順を提示して、今後の必要に応じた有効化に備えます。注：§21.2 ロードマップ P3 の「マルチテナント分離」はこれに合わせて「予約機能（ドキュメント化されたデグレード）」に調整され、骨格を残し、接続しません。

判断根拠（2026-08 レビュー）：
- 既存のデプロイはほぼすべてシングルテナントであり、接続すると不要な分離の複雑さとリグレッションリスクが生じる；
- 現在の骨格には技術的欠陥がある（22.4 参照）、「接続すれば即分離」は成立せず、先に設計修正を完了する必要がある；
- 分離には 163 テーブルのうち業務テーブルごとにカラム追加とモデルごとの有効化が必要で、コストが「最小限の接続」をはるかに超える。

### 22.2 現状の事実（コードと設定の確認）

| 項目 | 現状 |
|----|------|
| `app/middleware/TenantScope.php` | 存在するが未登録；`X-Tenant-Id` ヘッダーからテナントを読み取り、ヘッダー欠落時はそのまま通過 |
| `app/model/concerns/TenantScope.php` | 存在するが、使用するモデルなし；`bootTenantScope()` のグローバルスコープはテナント設定後のみフィルタリング |
| `config/middleware.php` | グローバルチェーン：Locale → Cors → SecurityFilter → RateLimit → TracingId、TenantScope なし |
| `config/route.php` /admin グループ | AdminAuth → AdminPermission → OperationLog、TenantScope なし |
| JWT ペイロード | `sub` / `username` / `token_type` のみ、**tenant_id クレームなし**（`app/api/v1/controller/AuthController.php`） |
| データベース | **全テーブルに tenant_id カラムなし**（install.sql にもなし） |
| モデル | **TenantScope trait を使用するモデルは存在しない** |

### 22.3 有効化手順（予約用の参考、今回の期間では実行しない）

1. ミドルウェアを登録：`config/route.php` の /admin グループの `middleware()` に `app\middleware\TenantScope::class` を追加（AdminAuth の後に配置し、認証済みであることを保証）。
2. リクエスト側はリクエストヘッダーに `X-Tenant-Id`（int テナントID）を付与。
3. 分離が必要な業務テーブルに `tenant_id` カラム（BIGINT + インデックス）を追加し、既存データをバックフィル；辞書/システムテーブル（例：`erp_admin_user`、`erp_role`、`erp_permission`）は分離しない。
4. 分離が必要なモデルクラスで `use app\model\concerns\TenantScope;` を記述し、現在のテナントで自動フィルタリング。
5. （任意）リクエストヘッダーではなく JWT からテナントを取得する場合：ログイン発行ペイロードを拡張して `tenant_id` クレームを追加し、ミドルウェアで `$payload['tenant_id']` から読み取る。

### 22.4 既知の技術的制限（有効化前に必ず解決）

- **静的受け渡しチェーンの断裂（PHP 8.3 実測）**：ミドルウェアが trait 名で `setCurrentTenantId()` を呼び出すと trait 自身の静的コピーに書き込まれ、その trait を使用するモデルクラスからは読み取れず、クエリはフィルタリングされません。有効化時はリクエストコンテキストベースの注入（例：`request()->tenantId`）に変更する必要があります。
- **静的グローバル状態の干渉**：Workerman は常駐プロセスのため、静的プロパティがリクエスト間で共有されます；コルーチンモード（Swoole/Swow）を有効化するとテナント間のデータ干渉が発生するため、リクエストレベルバインド（`context()` / リクエストオブジェクト）に変更する必要があります。
- **データプレーンのギャップ**：全テーブルに tenant_id カラムがないため、テーブルごとのマイグレーションが必要；テナント間で共有される辞書テーブルには免除メカニズムの設計が必要。

### 22.5 受入基準

今回の受入基準 = ドキュメントとコードの一致：`config/middleware.php` と `config/route.php` に TenantScope の登録が含まれない；ミドルウェアと Trait のコメントに「予約機能、未有効化」と明記され、有効化手順が提示されている；本節の記述がコードの現状と1件ずつ対応している。
