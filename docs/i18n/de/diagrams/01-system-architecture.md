# System-Topologiearchitektur

```mermaid
flowchart TB
    subgraph clients["Client-Schicht"]
        flutter["Flutter Web<br/>PC-Verwaltungspanel"]
        harmony["HarmonyOS ArkTS<br/>Handy-/Tablet-Client"]
    end

    subgraph gateway["Gateway-Schicht"]
        nginx["Nginx<br/>HTTPS-Reverse-Proxy<br/>Gzip-Komprimierung"]
    end

    subgraph app["Anwendungsebene - webman v2"]
        auth["AdminAuth<br/>JWT-Prüfung"]
        perm["AdminPermission<br/>RBAC-Autorisierung"]
        admin["Admin-Controller<br/>Dashboard/User/Role/Permission"]
        public["Öffentliche Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Speicherschicht"]
        mysql[("MySQL 8.0<br/>Hauptspeicher - erp_-Präfix")]
        es[("Elasticsearch<br/>Volltextsuche - erp_-Präfix")]
        redis[("Redis<br/>Session/Cache/Captcha")]
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
