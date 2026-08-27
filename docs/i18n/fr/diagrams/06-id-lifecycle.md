# Cycle de vie complet des ID

```mermaid
flowchart LR
    subgraph gen["1. Génération"]
        g1["SnowflakeService::generate()"]
        g2["datacenter_id(5bits) + worker_id(5bits)<br/>+ timestamp(41bits) + sequence(12bits)"]
        g3["BIGINT(18)<br/>Ex. : 1750123456789"]
        g1 --> g2 --> g3
    end

    subgraph store["2. Stockage"]
        s1["Tables MySQL erp_*<br/>id BIGINT UNSIGNED NOT NULL"]
        s2["Champs sensibles encryptable cast<br/>Stockage chiffré AES-128-ECB"]
        g3 --> s1 --> s2
    end

    subgraph transfer["3. Transmission"]
        t1["HashidsService::encode(bigint)"]
        t2["Chaîne hashid<br/>Ex. : aB3xK9mW2pQ7rT5v"]
        s1 --> t1 --> t2
    end

    subgraph reverse["4. Décodage inverse"]
        r1["HashidsService::decode(hashid)"]
        r2["BIGINT"]
        t2 --> r1 --> r2
    end

    style g1 fill:#1677FF,color:#fff
    style s2 fill:#FA8C16,color:#fff
    style t1 fill:#52C41A,color:#fff
    style r1 fill:#722ED1,color:#fff
```
