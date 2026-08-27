# Relacionamento ER do banco de dados

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR username UK "Nome de usuário"
        VARCHAR password "Hash bcrypt"
        VARCHAR real_name "Nome real"
        VARCHAR avatar "URL do avatar"
        VARCHAR email "Armazenamento criptografado"
        VARCHAR phone "Armazenamento criptografado"
        VARCHAR id_card "Armazenamento criptografado"
        TINYINT status "0 desativado 1 ativado"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Exclusão lógica"
    }

    erp_admin_role {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR name "Nome do papel"
        VARCHAR slug UK "Identificador do papel"
        VARCHAR description "Descrição"
        TINYINT status "0 desativado 1 ativado"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Gerado por Snowflake"
        BIGINT parent_id FK "ID da permissão pai"
        VARCHAR name "Nome da permissão"
        VARCHAR slug "Identificador da permissão"
        TINYINT type "1 menu 2 botão 3 API"
        VARCHAR icon "Ícone do menu"
        VARCHAR path "Caminho da rota"
        INT sort "Ordenação"
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK "ID do usuário"
        BIGINT role_id PK_FK "ID do papel"
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK "ID do papel"
        BIGINT permission_id PK_FK "ID da permissão"
    }

    erp_operation_log {
        BIGINT id PK "Gerado por Snowflake"
        BIGINT user_id FK "Usuário da operação"
        VARCHAR action "Ação executada"
        VARCHAR method "Método da requisição"
        VARCHAR path "Caminho da requisição"
        VARCHAR ip "IP da operação"
        TEXT input "Parâmetros da requisição mascarados"
        DATETIME created_at "Horário da operação"
    }

    erp_system_config {
        BIGINT id PK "Gerado por Snowflake"
        VARCHAR group_name "Grupo de configuração"
        VARCHAR key_name "Chave de configuração"
        TEXT value "Valor de configuração"
        VARCHAR type "Tipo do valor"
        VARCHAR description "Descrição"
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
