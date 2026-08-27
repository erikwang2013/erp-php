# Alur Autentikasi dan Captcha

```mermaid
sequenceDiagram
    actor U as Pengguna
    participant CL as Klien
    participant SV as Server
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: Langkah 1: Mendapatkan captcha
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: Langkah 2: Pengguna mengklik
    CL->>CL: Render gambar, petunjuk "klik: pohon→burung→bunga"
    U->>CL: Klik posisi teks dalam gambar secara berurutan
    CL->>CL: Kumpulkan clicks:[{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: Langkah 3: Verifikasi login
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt Captcha salah
        CAP-->>SV: false
        SV-->>CL: 422 Captcha salah
    else Captcha benar
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Kredensial salah
            SV-->>CL: 401 Nama pengguna atau kata sandi salah
        else Kredensial benar
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: Langkah 4: Permintaan berikutnya
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
