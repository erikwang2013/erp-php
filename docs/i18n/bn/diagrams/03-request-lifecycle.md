# রিকোয়েস্ট লাইফসাইকেল

```mermaid
sequenceDiagram
    actor C as ক্লায়েন্ট
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: HTTPS রিকোয়েস্ট
    N->>MW1: রিকোয়েস্ট ফরোয়ার্ড

    alt Token অনুপস্থিত বা অবৈধ
        MW1-->>C: 401 Unauthorized
    else Token বৈধ
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId সেট
    end

    alt অনুমতি নেই
        MW2-->>C: 403 Forbidden
    else অনুমতি আছে
        MW2->>CTL: কন্ট্রোলারে প্রবেশ
    end

    CTL->>CTL: প্যারামিটার যাচাই
    CTL->>CTL: decodeId(hashid)

    opt সংবেদনশীল অপারেশন
        CTL->>CTL: confirmPassword()
        alt পাসওয়ার্ড ভুল
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: encryptable স্বয়ংক্রিয় ডিক্রিপশন
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: hashid স্ট্রিং

    CTL-->>C: 200 JSON
```
