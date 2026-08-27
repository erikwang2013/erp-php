# डेटाबेस ER संबंध

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake जनरेशन"
        VARCHAR username UK "उपयोगकर्ता नाम"
        VARCHAR password "bcrypt हैश"
        VARCHAR real_name "वास्तविक नाम"
        VARCHAR avatar "अवतार URL"
        VARCHAR email "एन्क्रिप्टेड स्टोरेज"
        VARCHAR phone "एन्क्रिप्टेड स्टोरेज"
        VARCHAR id_card "एन्क्रिप्टेड स्टोरेज"
        TINYINT status "0 अक्षम 1 सक्षम"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "सॉफ्ट डिलीट"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake जनरेशन"
        VARCHAR name "भूमिका नाम"
        VARCHAR slug UK "भूमिका टैग"
        VARCHAR description "विवरण"
        TINYINT status "0 अक्षम 1 सक्षम"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake जनरेशन"
        BIGINT parent_id FK "मूल अनुमति ID"
        VARCHAR name "अनुमति नाम"
        VARCHAR slug "अनुमति टैग"
        TINYINT type "1 मेनू 2 बटन 3 API"
        VARCHAR icon "मेनू आइकन"
        VARCHAR path "रूट पथ"
        INT sort "क्रम"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK "उपयोगकर्ता ID"
        BIGINT role_id PK_FK "भूमिका ID"
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK "भूमिका ID"
        BIGINT permission_id PK_FK "अनुमति ID"
    }

    erp_operation_log {
        BIGINT id PK "Snowflake जनरेशन"
        BIGINT user_id FK "संचालन उपयोगकर्ता"
        VARCHAR action "ऑपरेशन क्रिया"
        VARCHAR method "अनुरोध विधि"
        VARCHAR path "अनुरोध पथ"
        VARCHAR ip "ऑपरेशन IP"
        TEXT input "अनुरोध पैरामीटर मास्किंग"
        DATETIME created_at "ऑपरेशन समय"
    }

    erp_system_config {
        BIGINT id PK "Snowflake जनरेशन"
        VARCHAR group_name "कॉन्फ़िगरेशन समूह"
        VARCHAR key_name "कॉन्फ़िगरेशन कुंजी"
        TEXT value "कॉन्फ़िगरेशन मान"
        VARCHAR type "मान प्रकार"
        VARCHAR description "विवरण"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user ||--o{ erp_admin_user_role : user_id
    erp_admin_role ||--o{ erp_admin_user_role : role_id
    erp_admin_role ||--o{ erp_admin_role_permission : role_id
    erp_admin_permission ||--o{ erp_admin_role_permission : permission_id
    erp_admin_user ||--o{ erp_operation_log : user_id
    erp_admin_permission ||--o{ erp_admin_permission : parent_id
```
