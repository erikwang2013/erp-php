# Топологическая архитектура системы

```mermaid
flowchart TB
    subgraph clients["Клиентский слой"]
        flutter["Flutter Web<br/>Панель администрирования ПК"]
        harmony["HarmonyOS ArkTS<br/>Мобильный/планшетный клиент"]
    end

    subgraph gateway["Шлюз"]
        nginx["Nginx<br/>Обратный прокси HTTPS<br/>Gzip-сжатие"]
    end

    subgraph app["Прикладной слой - webman v2"]
        auth["AdminAuth<br/>Проверка JWT"]
        perm["AdminPermission<br/>Проверка прав RBAC"]
        admin["Контроллеры админ-панели<br/>Dashboard/User/Role/Permission"]
        public["Публичные контроллеры<br/>Captcha/Auth"]
        common["Общие сервисы<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Слой хранения"]
        mysql[("MySQL 8.0<br/>Основное хранилище - префикс erp_")]
        es[("Elasticsearch<br/>Полнотекстовый поиск - префикс erp_")]
        redis[("Redis<br/>Сессии/кэш/Captcha")]
    end

    flutter --> nginx
    harmony --> nginx
    nginx --> auth
    auth --> perm
    perm --> admin
    auth --> public
    admin --> common
    public --> common
    admin --> mysql
    public --> mysql
    admin --> es
    public --> es
    auth --> redis
    public --> redis

    style flutter fill:#1677FF,color:#fff
    style harmony fill:#1677FF,color:#fff
    style nginx fill:#722ED1,color:#fff
    style auth fill:#FA8C16,color:#fff
    style perm fill:#FA8C16,color:#fff
    style common fill:#52C41A,color:#fff
    style mysql fill:#1890FF,color:#fff
    style es fill:#1890FF,color:#fff
    style redis fill:#1890FF,color:#fff
```
