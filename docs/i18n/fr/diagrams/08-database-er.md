# Relations ER de la base de données

```mermaid
erDiagram
    erik_admin_user {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR username UK "Nom d'utilisateur"
        VARCHAR password "Hachage bcrypt"
        VARCHAR real_name "Nom réel"
        VARCHAR avatar "URL de l'avatar"
        VARCHAR email "Stockage chiffré"
        VARCHAR phone "Stockage chiffré"
        VARCHAR id_card "Stockage chiffré"
        TINYINT status "0 désactivé 1 activé"
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "Suppression logique"
    }

    erik_admin_role {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR name "Nom du rôle"
        VARCHAR slug UK "Identifiant du rôle"
        VARCHAR description "Description"
        TINYINT status "0 désactivé 1 activé"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_permission {
        BIGINT id PK "Généré par Snowflake"
        BIGINT parent_id FK "ID de la permission parente"
        VARCHAR name "Nom de la permission"
        VARCHAR slug "Identifiant de la permission"
        TINYINT type "1 menu 2 bouton 3 API"
        VARCHAR icon "Icône du menu"
        VARCHAR path "Chemin de routage"
        INT sort "Tri"
        DATETIME created_at
        DATETIME updated_at
    }

    erik_admin_user_role {
        BIGINT user_id PK_FK "ID utilisateur"
        BIGINT role_id PK_FK "ID rôle"
    }

    erik_admin_role_permission {
        BIGINT role_id PK_FK "ID rôle"
        BIGINT permission_id PK_FK "ID permission"
    }

    erik_operation_log {
        BIGINT id PK "Généré par Snowflake"
        BIGINT user_id FK "Utilisateur opérant"
        VARCHAR action "Action effectuée"
        VARCHAR method "Méthode de requête"
        VARCHAR path "Chemin de requête"
        VARCHAR ip "IP de l'opération"
        TEXT input "Paramètres de requête masqués"
        DATETIME created_at "Heure de l'opération"
    }

    erik_system_config {
        BIGINT id PK "Généré par Snowflake"
        VARCHAR group_name "Groupe de configuration"
        VARCHAR key_name "Clé de configuration"
        TEXT value "Valeur de configuration"
        VARCHAR type "Type de valeur"
        VARCHAR description "Description"
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
