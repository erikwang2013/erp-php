# طبقات تشفير البيانات

```mermaid
flowchart TB
    subgraph transport["تشفير طبقة النقل - encryption"]
        e1["إرسال العميل للبيانات الحساسة"]
        e2["تشفير AES-256-CBC"]
        e3["نقل نص مشفر عبر API"]
        e4["فك التشفير ومعالجة على الخادم"]
        e1 --> e2 --> e3 --> e4
    end

    subgraph storage["تشفير طبقة التخزين - encryptable"]
        d1["إعدادات Model casts<br/>email=>Encryptable::class<br/>phone=>Encryptable::class<br/>id_card=>Encryptable::class"]
        d2["تشفير تلقائي عند الكتابة"]
        d3["تخزين النص المشفر في MySQL VARCHAR(500)"]
        d4["فك تشفير تلقائي عند القراءة"]
        d1 --> d2 --> d3 --> d4
    end

    subgraph mask["إخفاء طبقة العرض"]
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
