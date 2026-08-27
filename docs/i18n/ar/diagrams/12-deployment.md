# طوبولوجيا النشر

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["خادم الويب"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["الملفات الثابتة<br/>Flutter Web build/"]
    end

    subgraph app["خادم التطبيق (قابل للتوسع الأفقي)"]
        wm1["webman worker 1 :8787"]
        wm2["webman worker 2 :8787"]
        wm3["webman worker N :8787"]
    end

    subgraph data["طبقة البيانات"]
        mysql[("MySQL 8.0<br/>نسخ رئيسي-تابع<br/>بادئة erp_")]
        es[("Elasticsearch 8.x<br/>عنقود من 3 عقد<br/>بادئة erp_")]
        redis[("Redis 7.x<br/>وضع الحارس<br/>poster:captcha:*")]
    end

    subgraph monitor["المراقبة"]
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
