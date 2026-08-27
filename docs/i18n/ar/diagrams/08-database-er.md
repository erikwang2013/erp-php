# علاقات ER لقاعدة البيانات

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "توليد Snowflake"
        VARCHAR username UK "اسم المستخدم"
        VARCHAR password "تجزئة bcrypt"
        VARCHAR real_name "الاسم الحقيقي"
        VARCHAR avatar "رابط الصورة الرمزية"
        VARCHAR email "تخزين مشفر"
        VARCHAR phone "تخزين مشفر"
        VARCHAR id_card "تخزين مشفر"
        TINYINT status "0 معطل 1 مفعل"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "حذف ناعم"
    }

    erik_admin_role {
        BIGINT id PK "توليد Snowflake"
        VARCHAR name "اسم الدور"
        VARCHAR slug UK "معرف الدور"
        VARCHAR description "الوصف"
        TINYINT status "0 معطل 1 مفعل"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "توليد Snowflake"
        BIGINT parent_id FK "معرف الصلاحية الأب"
        VARCHAR name "اسم الصلاحية"
        VARCHAR slug "معرف الصلاحية"
        TINYINT type "1 قائمة 2 زر 3 API"
        VARCHAR icon "أيقونة القائمة"
        VARCHAR path "مسار التوجيه"
        INT sort "الترتيب"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "معرف المستخدم"
        BIGINT role_id PK_FK "معرف الدور"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "معرف الدور"
        BIGINT permission_id PK_FK "معرف الصلاحية"
    }

    erik_operation_log {
        BIGINT id PK "توليد Snowflake"
        BIGINT user_id FK "المستخدم المُنفذ"
        VARCHAR action "إجراء العملية"
        VARCHAR method "طريقة الطلب"
        VARCHAR path "مسار الطلب"
        VARCHAR ip "IP العملية"
        TEXT input "معاملات الطلب مع الإخفاء"
        DATETIME created_at "وقت العملية"
    }

    erik_system_config {
        BIGINT id PK "توليد Snowflake"
        VARCHAR group_name "مجموعة التكوين"
        VARCHAR key_name "مفتاح التكوين"
        TEXT value "قيمة التكوين"
        VARCHAR type "نوع القيمة"
        VARCHAR description "الشرح"
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
