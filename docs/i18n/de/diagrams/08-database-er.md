# Datenbank-ER-Beziehungen

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "von Snowflake erzeugt"
        VARCHAR username UK "Benutzername"
        VARCHAR password "bcrypt-Hash"
        VARCHAR real_name "Realname"
        VARCHAR avatar "Avatar-URL"
        VARCHAR email "verschlüsselt gespeichert"
        VARCHAR phone "verschlüsselt gespeichert"
        VARCHAR id_card "verschlüsselt gespeichert"
        TINYINT status "0 deaktiviert 1 aktiviert"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft-Delete"
    }

    erik_admin_role {
        BIGINT id PK "von Snowflake erzeugt"
        VARCHAR name "Rollenname"
        VARCHAR slug UK "Rollen-ID"
        VARCHAR description "Beschreibung"
        TINYINT status "0 deaktiviert 1 aktiviert"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "von Snowflake erzeugt"
        BIGINT parent_id FK "übergeordnete Berechtigungs-ID"
        VARCHAR name "Berechtigungsname"
        VARCHAR slug "Berechtigungs-ID"
        TINYINT type "1 Menü 2 Schaltfläche 3 API"
        VARCHAR icon "Menüsymbol"
        VARCHAR path "Routenpfad"
        INT sort "Sortierung"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "Benutzer-ID"
        BIGINT role_id PK_FK "Rollen-ID"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "Rollen-ID"
        BIGINT permission_id PK_FK "Berechtigungs-ID"
    }

    erik_operation_log {
        BIGINT id PK "von Snowflake erzeugt"
        BIGINT user_id FK "ausführender Benutzer"
        VARCHAR action "Aktionsbezeichnung"
        VARCHAR method "Anfragemethode"
        VARCHAR path "Anfragepfad"
        VARCHAR ip "Aktions-IP"
        TEXT input "Anfrageparameter maskiert"
        DATETIME created_at "Aktionszeitpunkt"
    }

    erik_system_config {
        BIGINT id PK "von Snowflake erzeugt"
        VARCHAR group_name "Konfigurationsgruppe"
        VARCHAR key_name "Konfigurationsschlüssel"
        TEXT value "Konfigurationswert"
        VARCHAR type "Werttyp"
        VARCHAR description "Erläuterung"
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
