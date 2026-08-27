# Camadas de criptografia de dados

```mermaid
flowchart TB
    subgraph transport["Criptografia da camada de transporte - encryption"]
        e1["Cliente envia dados sensíveis"]
        e2["Criptografia AES-256-CBC"]
        e3["Texto cifrado transmitido pela API"]
        e4["Servidor descriptografa e processa"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Criptografia da camada de armazenamento - encryptable"]
        d1["Configuração de casts do Model<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Criptografia automática na escrita"]
        d3["MySQL VARCHAR(500) armazena texto cifrado"]
        d4["Descriptografia automática na leitura"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Mascaramento da camada de exibição"]
        m1["phone: 138****1234"]
        m2["email: a***@example.com"]
        m3["id_card: ********"]
        d4 --> m1 & m2 & m3
    end

    e4 --> d1

    style e2 fill:#1677FF,color:#fff
    style d2 fill:#FA8C16,color:#fff
    style m1 fill:#52C41A,color:#fff
```
