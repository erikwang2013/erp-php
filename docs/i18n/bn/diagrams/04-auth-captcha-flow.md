# অথেন্টিকেশন ও ক্যাপচা ফ্লো

```mermaid
sequenceDiagram
    actor U as ইউজার
    participant CL as ক্লায়েন্ট
    participant SV as সার্ভার
    participant CAP as Captcha
    participant JWT as JWT Service

    rect rgb(230, 240, 255)
    Note over U,CAP: ধাপ ১: ক্যাপচা প্রাপ্তি
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP-->>SV: key, image(base64 PNG), targets
    SV-->>CL: 200 {key, image, extra.targets}
    end

    rect rgb(230, 255, 230)
    Note over U,CAP: ধাপ ২: ইউজার ক্লিক
    CL->>CL: ছবি রেন্ডার, "অনুগ্রহ করে ক্লিক করুন: গাছ→পাখি→ফুল"
    U->>CL: ছবিতে লেখার অবস্থানে পর্যায়ক্রমে ক্লিক
    CL->>CL: clicks সংগ্রহ: [{x,y},{x,y},{x,y}]
    end

    rect rgb(255, 240, 230)
    Note over U,CAP: ধাপ ৩: লগইন যাচাই
    CL->>SV: POST /api/auth/login {username,password,captcha_key,clicks}
    SV->>CAP: captcha_verify(key,'click',clicks)

    alt ক্যাপচা ভুল
        CAP-->>SV: false
        SV-->>CL: 422 ক্যাপচা ভুল
    else ক্যাপচা সঠিক
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt ক্রেডেনশিয়াল ভুল
            SV-->>CL: 401 ইউজারনেম বা পাসওয়ার্ড ভুল
        else ক্রেডেনশিয়াল সঠিক
            SV->>JWT: jwt()->create()
            JWT-->>SV: access_token(2h)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token(14d)
            SV-->>CL: 200 {tokens, user}
        end
    end
    end

    rect rgb(245, 245, 255)
    Note over U,CL: ধাপ ৪: পরবর্তী রিকোয়েস্ট
    CL->>SV: GET /admin/dashboard
    Note right of CL: Authorization: Bearer token
    SV->>JWT: jwt()->verify()
    JWT-->>SV: {sub, username}
    SV-->>CL: 200 {dashboard data}
    end
```
