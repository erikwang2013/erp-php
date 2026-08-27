# ডেটাবেস ER সম্পর্ক

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake জেনারেটেড"
        VARCHAR username UK "ইউজারনেম"
        VARCHAR password "bcrypt হ্যাশ"
        VARCHAR real_name "প্রকৃত নাম"
        VARCHAR avatar "অবতার URL"
        VARCHAR email "এনক্রিপ্টেড স্টোরেজ"
        VARCHAR phone "এনক্রিপ্টেড স্টোরেজ"
        VARCHAR id_card "এনক্রিপ্টেড স্টোরেজ"
        TINYINT status "0 নিষ্ক্রিয় 1 সক্রিয়"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "সফট ডিলিট"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake জেনারেটেড"
        VARCHAR name "রোল নাম"
        VARCHAR slug UK "রোল চিহ্ন"
        VARCHAR description "বিবরণ"
        TINYINT status "0 নিষ্ক্রিয় 1 সক্রিয়"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake জেনারেটেড"
        BIGINT parent_id FK "প্যারেন্ট পারমিশন ID"
        VARCHAR name "পারমিশন নাম"
        VARCHAR slug "পারমিশন চিহ্ন"
        TINYINT type "1 মেনু 2 বাটন 3 API"
        VARCHAR icon "মেনু আইকন"
        VARCHAR path "রাউট পাথ"
        INT sort "সাজানো"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK "ইউজার ID"
        BIGINT role_id PK_FK "রোল ID"
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK "রোল ID"
        BIGINT permission_id PK_FK "পারমিশন ID"
    }

    erp_operation_log {
        BIGINT id PK "Snowflake জেনারেটেড"
        BIGINT user_id FK "অপারেটিং ইউজার"
        VARCHAR action "অপারেশন অ্যাকশন"
        VARCHAR method "রিকোয়েস্ট মেথড"
        VARCHAR path "রিকোয়েস্ট পাথ"
        VARCHAR ip "অপারেশন IP"
        TEXT input "রিকোয়েস্ট প্যারামিটার ডিসেনসিটাইজড"
        DATETIME created_at "অপারেশন সময়"
    }

    erp_system_config {
        BIGINT id PK "Snowflake জেনারেটেড"
        VARCHAR group_name "কনফিগ গ্রুপ"
        VARCHAR key_name "কনফিগ কী"
        TEXT value "কনফিগ মান"
        VARCHAR type "মানের ধরন"
        VARCHAR description "ব্যাখ্যা"
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
