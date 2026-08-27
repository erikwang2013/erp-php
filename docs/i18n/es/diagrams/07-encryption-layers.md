# Capas de cifrado de datos

```mermaid
flowchart TB
    subgraph transport["Cifrado de la capa de transporte - encryption"]
        e1["El cliente envía datos sensibles"]
        e2["Cifrado AES-256-CBC"]
        e3["Cifrado de transmisión por API"]
        e4["El servidor descifra y procesa"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["Cifrado de la capa de almacenamiento - encryptable"]
        d1["Configuración de casts del Model<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["Cifrado automático al escribir"]
        d3["Almacenamiento del cifrado en MySQL VARCHAR(500)"]
        d4["Descifrado automático al leer"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["Enmascarado en la capa de presentación"]
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
