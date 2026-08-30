# परिनियोजन टोपोलॉजी

```mermaid
flowchart TB
    subgraph dns["DNS/CDN"]
        domain["erik.xyz"]
    end

    subgraph web["Web सर्वर"]
        nginx["Nginx :443 HTTPS<br/>:80→443 redirect<br/>gzip on"]
        static["स्टैटिक फ़ाइलें<br/>Flutter Web build/"]
    end

    subgraph app["एप्लिकेशन सर्वर (क्षैतिज रूप से स्केलेबल)"]
        wm1["webman worker 1 :8788"]
        wm2["webman worker 2 :8788"]
        wm3["webman worker N :8788"]
    end

    subgraph data["डेटा परत"]
        mysql[("MySQL 8.0<br/>मास्टर-स्लेव रेप्लिकेशन<br/>erp_ उपसर्ग")]
        es[("Elasticsearch 8.x<br/>3 नोड क्लस्टर<br/>erp_ उपसर्ग")]
        redis[("Redis 7.x<br/>सेंटिनल मोड<br/>poster:captcha:*")]
    end

    subgraph monitor["निगरानी"]
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
