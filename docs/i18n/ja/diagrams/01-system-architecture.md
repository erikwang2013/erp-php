# システム・トポロジー・アーキテクチャ

```mermaid
flowchart TB
    subgraph clients["クライアント層"]
        flutter["Flutter Web<br/>PC管理バックエンド"]
        harmony["HarmonyOS ArkTS<br/>スマホ/タブレットクライアント"]
    end

    subgraph gateway["ゲートウェイ層"]
        nginx["Nginx<br/>HTTPSリバースプロキシ<br/>Gzip圧縮"]
    end

    subgraph app["アプリケーション層 - webman v2"]
        auth["AdminAuth<br/>JWT検証"]
        perm["AdminPermission<br/>RBAC認可"]
        admin["管理側Controller<br/>Dashboard/User/Role/Permission"]
        public["公開Controller<br/>Captcha/Auth"]
        common["Common Services<br/>Hashids/Snowflake/Encryption"]
    end

    subgraph storage["ストレージ層"]
        mysql[("MySQL 8.0<br/>メインストレージ - erp_プレフィックス")]
        es[("Elasticsearch<br/>全文検索 - erp_プレフィックス")]
        redis[("Redis<br/>Session/キャッシュ/Captcha")]
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
