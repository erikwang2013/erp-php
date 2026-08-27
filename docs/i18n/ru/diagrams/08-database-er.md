# Связи ER базы данных

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Генерация Snowflake"
        VARCHAR username UK "Имя пользователя"
        VARCHAR password "bcrypt-хэш"
        VARCHAR real_name "Настоящее имя"
        VARCHAR avatar "URL аватара"
        VARCHAR email "Шифрованное хранение"
        VARCHAR phone "Шифрованное хранение"
        VARCHAR id_card "Шифрованное хранение"
        TINYINT status "0 отключён 1 включён"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Мягкое удаление"
    }

    erik_admin_role {
        BIGINT id PK "Генерация Snowflake"
        VARCHAR name "Название роли"
        VARCHAR slug UK "Идентификатор роли"
        VARCHAR description "Описание"
        TINYINT status "0 отключена 1 включена"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Генерация Snowflake"
        BIGINT parent_id FK "ID родительского права"
        VARCHAR name "Название права"
        VARCHAR slug "Идентификатор права"
        TINYINT type "1 меню 2 кнопка 3 API"
        VARCHAR icon "Иконка меню"
        VARCHAR path "Путь маршрута"
        INT sort "Сортировка"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID пользователя"
        BIGINT role_id PK_FK "ID роли"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID роли"
        BIGINT permission_id PK_FK "ID права"
    }

    erik_operation_log {
        BIGINT id PK "Генерация Snowflake"
        BIGINT user_id FK "Пользователь операции"
        VARCHAR action "Действие"
        VARCHAR method "Метод запроса"
        VARCHAR path "Путь запроса"
        VARCHAR ip "IP операции"
        TEXT input "Параметры запроса с маскированием"
        DATETIME created_at "Время операции"
    }

    erik_system_config {
        BIGINT id PK "Генерация Snowflake"
        VARCHAR group_name "Группа конфигурации"
        VARCHAR key_name "Ключ конфигурации"
        TEXT value "Значение конфигурации"
        VARCHAR type "Тип значения"
        VARCHAR description "Пояснение"
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
