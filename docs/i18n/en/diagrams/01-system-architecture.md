# System Topology Architecture

```mermaid
flowchart TB
    subgraph clients["Client Layer"]
        flutter["Flutter Web<br/>PC Admin Panel"]
        harmony["HarmonyOS ArkTS<br/>Mobile/Tablet Client"]
    end

    subgraph gateway["Gateway Layer"]
        nginx["Nginx<br/>HTTPS Reverse Proxy<br/>Gzip Compression"]
    end

    subgraph app["Application Layer - webman v2"]
        auth["AdminAuth<br/>JWT Verification"]
        perm["AdminPermission<br/>RBAC Authorization"]
        admin["Admin Controllers<br/>Dashboard/User/Role/Permission"]
        public["Public Controllers<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Storage Layer"]
        mysql[("MySQL 8.0<br/>Primary Storage - erik_ prefix")]
        es[("Elasticsearch<br/>Full-text Search - erik_ prefix")]
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
