# デプロイ・トポロジー

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Webサーバー"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["静的ファイル<br/>Flutter Web build/"]
    end

    subgraph app["アプリケーションサーバー(水平拡張可)"]
        wm1["webman worker 1 :8788"]
        wm2["webman worker 2 :8788"]
        wm3["webman worker N :8788"]
    end

    subgraph data["データ層"]
        mysql[("MySQL 8.0<br/>マスター/スレーブレプリケーション<br/>erp_プレフィックス")]
        es[("Elasticsearch 8.x<br/>3ノードクラスター<br/>erp_プレフィックス")]
        redis[("Redis 7.x<br/>センチネルモード<br/>poster:captcha:*")]
    end

    subgraph monitor["監視"]
        grafana["Grafana+Prometheus"]
    end

    domain --> nginx
    nginx --> static
    nginx --> wm1
    nginx --> wm2
    nginx --> wm3
    wm1 & wm2 & wm3 --> mysql
    wm1 & wm2 & wm3 --> es
    wm1 & wm2 & wm3 --> redis
    wm1 & wm2 & wm3 --> grafana

    style nginx fill:#722ED1,color:#fff
    style wm1 fill:#1677FF,color:#fff
    style wm2 fill:#1677FF,color:#fff
    style wm3 fill:#1677FF,color:#fff
    style mysql fill:#1890FF,color:#fff
    style es fill:#1890FF,color:#fff
    style redis fill:#1890FF,color:#fff
```
