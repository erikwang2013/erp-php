# Ciclo de vida completo do ID

```mermaid
flowchart LR
    subgraph gen["1. Geração"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bit) + worker_id(5bit)<br/>+ timestamp(41bit) + sequence(12bit)"]
        g3["BIGINT(18)<br/>Ex.: 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2. Armazenamento"]
        s1["Tabelas MySQL erik_*<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["Campo sensível encryptable cast<br/>Armazenamento criptografado AES-128-ECB"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3. Transmissão"]
        t1["HashidsService::encode(bigint)"]
        t2["string hashid<br/>Ex.: aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4. Decodificação reversa"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
