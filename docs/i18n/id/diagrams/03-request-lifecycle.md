# Siklus Hidup Permintaan

```mermaid
sequenceDiagram
    actor C as Klien
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: Permintaan HTTPS
    N->>MW1: Teruskan permintaan

    alt Token hilang atau tidak valid
        MW1-->>C: 401 Unauthorized
    else Token valid
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: Set $request->adminId
    end

    alt Tanpa izin
        MW2-->>C: 403 Forbidden
    else Memiliki izin
        MW2->>CTL: Masuk ke controller
    end

    CTL->>CTL: Validasi parameter
    CTL->>CTL: decodeId(hashid)

    opt Operasi sensitif
        CTL->>CTL: confirmPassword()
        alt Kata sandi salah
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Dekripsi otomatis encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: string hashid

    CTL-->>C: 200 JSON
```
