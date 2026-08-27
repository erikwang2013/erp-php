# ID সম্পূর্ণ লাইফসাইকেল

```mermaid
flowchart LR
    subgraph gen["1. জেনারেশন"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>উদাহরণ: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2. স্টোরেজ"]
        s1["MySQL erp_* টেবিল<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["সংবেদনশীল ফিল্ড encryptable cast<br/>AES-128-ECB এনক্রিপ্টেড স্টোরেজ"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3. ট্রান্সফার"]
        t1["HashidsService::encode(bigint)"]
        t2["hashid স্ট্রিং<br/>উদাহরণ: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4. রিভার্স ডিকোডিং"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
