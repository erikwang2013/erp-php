# データベース ER 関係

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Snowflake生成"
        VARCHAR username UK "ユーザー名"
        VARCHAR password "bcryptハッシュ"
        VARCHAR real_name "実名"
        VARCHAR avatar "アバターURL"
        VARCHAR email "暗号化保存"
        VARCHAR phone "暗号化保存"
        VARCHAR id_card "暗号化保存"
        TINYINT status "0無効 1有効"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "ソフト削除"
    }

    erik_admin_role {
        BIGINT id PK "Snowflake生成"
        VARCHAR name "役割名"
        VARCHAR slug UK "役割識別子"
        VARCHAR description "説明"
        TINYINT status "0無効 1有効"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Snowflake生成"
        BIGINT parent_id FK "親権限ID"
        VARCHAR name "権限名"
        VARCHAR slug "権限識別子"
        TINYINT type "1メニュー 2ボタン 3API"
        VARCHAR icon "メニューアイコン"
        VARCHAR path "ルートパス"
        INT sort "並び順"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ユーザーID"
        BIGINT role_id PK_FK "役割ID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "役割ID"
        BIGINT permission_id PK_FK "権限ID"
    }

    erik_operation_log {
        BIGINT id PK "Snowflake生成"
        BIGINT user_id FK "操作ユーザー"
        VARCHAR action "操作アクション"
        VARCHAR method "リクエストメソッド"
        VARCHAR path "リクエストパス"
        VARCHAR ip "操作IP"
        TEXT input "リクエストパラメータ(マスキング)"
        DATETIME created_at "操作時間"
    }

    erik_system_config {
        BIGINT id PK "Snowflake生成"
        VARCHAR group_name "設定グループ"
        VARCHAR key_name "設定キー"
        TEXT value "設定値"
        VARCHAR type "値タイプ"
        VARCHAR description "説明"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user ||--o{ erik_admin_user_role : user_id
    erik_admin_role ||--o{ erik_admin_user_role : role_id
    erik_admin_role ||--o{ erik_admin_role_permission : role_id
    erik_admin_permission ||--o{ erik_admin_role_permission : permission_id
    erik_admin_user ||--o{ erik_operation_log : user_id
    erik_admin_permission ||--o{ erik_admin_permission : parent_id
```
