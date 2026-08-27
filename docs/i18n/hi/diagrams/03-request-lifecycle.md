# अनुरोध जीवनचक्र

```mermaid
sequenceDiagram
    actor C as क्लाइंट
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS अनुरोध
    N->>MW1: अनुरोध फॉरवर्ड

    alt Token अनुपस्थित या अमान्य
        MW1-->>C: 401 Unauthorized
    else Token मान्य
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId सेट करें
    end

    alt कोई अनुमति नहीं
        MW2-->>C: 403 Forbidden
    else अनुमति है
        MW2->>CTL: कंट्रोलर में प्रवेश
    end

    CTL->>CTL: पैरामीटर सत्यापन
    CTL->>CTL: decodeId(hashid)

    opt संवेदनशील ऑपरेशन
        CTL->>CTL: confirmPassword()
        alt पासवर्ड गलत
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable स्वतः डिक्रिप्शन
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid स्ट्रिंग

    CTL-->>C: 200 JSON
```
