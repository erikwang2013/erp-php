# دورة حياة الطلب

```mermaid
sequenceDiagram
    actor C as العميل
    participant N as Nginx
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Controller
    participant SVC as Service
    participant MDL as Model
    participant DB as MySQL

    C->>N: طلب HTTPS
    N->>MW1: تحويل الطلب

    alt Token مفقود أو غير صالح
        MW1-->>C: 401 Unauthorized
    else Token صالح
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: تعيين $request->adminId
    end

    alt لا صلاحية
        MW2-->>C: 403 Forbidden
    else لديه صلاحية
        MW2->>CTL: الدخول إلى المتحكم
    end

    CTL->>CTL: التحقق من المعاملات
    CTL->>CTL: decodeId(hashid)

    opt عملية حساسة
        CTL->>CTL: confirmPassword()
        alt كلمة المرور خاطئة
            CTL-->>C: 422
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: فك تشفير تلقائي encryptable
    MDL->>DB: SELECT
    DB-->>MDL: Row
    MDL-->>CTL: Model

    CTL->>SVC: encodeId()
    SVC-->>CTL: سلسلة hashid

    CTL-->>C: 200 JSON
```
