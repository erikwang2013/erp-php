# Arquitetura de topologia do sistema

```mermaid
flowchart TB
    subgraph clients["Camada de clientes"]
        flutter["Flutter Web<br/>Painel de administração PC"]
        harmony["HarmonyOS ArkTS<br/>Cliente celular/tablet"]
    end

    subgraph gateway["Camada de gateway"]
        nginx["Nginx<br/>Proxy reverso HTTPS<br/>Compressão Gzip"]
    end

    subgraph app["Camada de aplicação - webman v2"]
        auth["AdminAuth<br/>Verificação JWT"]
        perm["AdminPermission<br/>Autorização RBAC"]
        admin["Controller de administração<br/>Dashboard/User/Role/Permission"]
        public["Controller público<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["Camada de armazenamento"]
        mysql[("MySQL 8.0<br/>Armazenamento principal - prefixo erik_")]
        es[("Elasticsearch<br/>Busca de texto completo - prefixo erik_")]
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
