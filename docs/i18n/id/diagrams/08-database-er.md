# Relasi ER Database

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Dibuat Snowflake"
        VARCHAR username UK "Nama pengguna"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nama asli"
        VARCHAR avatar "URL avatar"
        VARCHAR email "Penyimpanan terenkripsi"
        VARCHAR phone "Penyimpanan terenkripsi"
        VARCHAR id_card "Penyimpanan terenkripsi"
        TINYINT status "0 nonaktif 1 aktif"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Soft delete"
    }

    erp_admin_role {
        BIGINT id PK "Dibuat Snowflake"
        VARCHAR name "Nama peran"
        VARCHAR slug UK "Identitas peran"
        VARCHAR description "Deskripsi"
        TINYINT status "0 nonaktif 1 aktif"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Dibuat Snowflake"
        BIGINT parent_id FK "ID izin induk"
        VARCHAR name "Nama izin"
        VARCHAR slug "Identitas izin"
        TINYINT type "1 menu 2 tombol 3 API"
        VARCHAR icon "Ikon menu"
        VARCHAR path "Jalur rute"
        INT sort "Urutan"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK "ID pengguna"
        BIGINT role_id PK_FK "ID peran"
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK "ID peran"
        BIGINT permission_id PK_FK "ID izin"
    }

    erp_operation_log {
        BIGINT id PK "Dibuat Snowflake"
        BIGINT user_id FK "Pengguna operasi"
        VARCHAR action "Aksi operasi"
        VARCHAR method "Metode permintaan"
        VARCHAR path "Jalur permintaan"
        VARCHAR ip "IP operasi"
        TEXT input "Parameter permintaan di-masking"
        DATETIME created_at "Waktu operasi"
    }

    erp_system_config {
        BIGINT id PK "Dibuat Snowflake"
        VARCHAR group_name "Grup konfigurasi"
        VARCHAR key_name "Kunci konfigurasi"
        TEXT value "Nilai konfigurasi"
        VARCHAR type "Tipe nilai"
        VARCHAR description "Keterangan"
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
