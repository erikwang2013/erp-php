# Relaciones ER de la base de datos

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR username UK "Nombre de usuario"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nombre real"
        VARCHAR avatar "URL de avatar"
        VARCHAR email "Almacenamiento cifrado"
        VARCHAR phone "Almacenamiento cifrado"
        VARCHAR id_card "Almacenamiento cifrado"
        TINYINT status "0 deshabilitado 1 habilitado"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Borrado suave"
    }

    erik_admin_role {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR name "Nombre del rol"
        VARCHAR slug UK "Identificador del rol"
        VARCHAR description "Descripción"
        TINYINT status "0 deshabilitado 1 habilitado"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Generado por Snowflake"
        BIGINT parent_id FK "ID del permiso padre"
        VARCHAR name "Nombre del permiso"
        VARCHAR slug "Identificador del permiso"
        TINYINT type "1 menú 2 botón 3 API"
        VARCHAR icon "Icono de menú"
        VARCHAR path "Ruta"
        INT sort "Ordenación"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID de usuario"
        BIGINT role_id PK_FK "ID de rol"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID de rol"
        BIGINT permission_id PK_FK "ID de permiso"
    }

    erik_operation_log {
        BIGINT id PK "Generado por Snowflake"
        BIGINT user_id FK "Usuario que opera"
        VARCHAR action "Acción realizada"
        VARCHAR method "Método de solicitud"
        VARCHAR path "Ruta de solicitud"
        VARCHAR ip "IP de la operación"
        TEXT input "Parámetros de solicitud enmascarados"
        DATETIME created_at "Hora de la operación"
    }

    erik_system_config {
        BIGINT id PK "Generado por Snowflake"
        VARCHAR group_name "Grupo de configuración"
        VARCHAR key_name "Clave de configuración"
        TEXT value "Valor de configuración"
        VARCHAR type "Tipo de valor"
        VARCHAR description "Explicación"
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
