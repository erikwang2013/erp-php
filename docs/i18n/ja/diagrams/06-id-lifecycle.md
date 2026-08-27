# ID 全ライフサイクル

```mermaid
flowchart LR
    subgraph gen["1.生成"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>例: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2.保存"]
        s1["MySQL erp_* テーブル<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["機密フィールド encryptable cast<br/>AES-128-ECB 暗号化保存"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3.転送"]
        t1["HashidsService::encode(bigint)"]
        t2["hashid文字列<br/>例: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4.逆デコード"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
