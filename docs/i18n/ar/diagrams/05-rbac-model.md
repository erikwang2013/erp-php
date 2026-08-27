# نموذج صلاحيات RBAC

## علاقة المستخدم-الدور-الصلاحية

```mermaid
flowchart LR
    subgraph users["المستخدمون"]
        u1["admin(مدير فائق)"]
        u2["editor(محرر)"]
        u3["viewer(قراءة فقط)"]
    end

    subgraph roles["الأدوار"]
        r1["super_admin<br/>معرف الصلاحية: *"]
        r2["editor<br/>معرف الصلاحية: get.* post.*"]
        r3["viewer<br/>معرف الصلاحية: get.*"]
    end

    subgraph permissions["الصلاحيات (شجرة)"]
        p1["dashboard(قائمة)"]
        p2["user(قائمة)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(زر)"]
    end

    u1 --> r1
    u2 --> r2
    u3 --> r3
    r1 --> p1 & p2 & p3 & p4 & p5 & p6
    r2 --> p1 & p2 & p3 & p4
    r3 --> p1 & p3
    p2 --> p3 & p4 & p5
    p1 --> p6

    style u1 fill:#1677FF,color:#fff
    style r1 fill:#FA8C16,color:#fff
    style p1 fill:#52C41A,color:#fff
```

## تدفق الحكم على الصلاحية

```mermaid
flowchart TD
    start["وصول الطلب"] --> extract["استخراج Token→adminId"]
    extract --> findRoles["الاستعلام عن أدوار المستخدم"]
    findRoles --> collectSlug["جمع كل permission.slug"]
    collectSlug --> buildKey["بناء method.path"]
    buildKey --> check{"slug==* أو<br/>slug مطابق؟"}
    check -->|"نعم"| allow["200 السماح"]
    check -->|"لا"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## أنواع الصلاحيات

```mermaid
flowchart LR
    t1["type=1 قائمة<br/>التحكم في عرض الشريط الجانبي"]
    t2["type=2 زر<br/>التحكم في أزرار العمليات"]
    t3["type=3 API<br/>التحكم في الوصول للواجهات"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
