# बैकएंड परत आर्किटेक्चर

```mermaid
flowchart TD
    subgraph route["रूटिंग परत"]
        r1["config/route.php<br/>URL→Controller मैपिंग"]
    end

    subgraph middleware["मिडलवेयर परत"]
        m1["AdminAuth<br/>JWT Token सत्यापन<br/>adminId इंजेक्ट"]
        m2["AdminPermission<br/>RBAC प्रमाणीकरण<br/>method.path मिलान"]
    end

    subgraph controller["कंट्रोलर परत"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["सेवा परत"]
        s1["HashidsService<br/>ID एन्कोड/डिकोड"]
        s2["SnowflakeService<br/>वैश्विक ID जनरेशन"]
        s3["EncryptionService<br/>एन्क्रिप्शन/डिक्रिप्शन+मास्किंग"]
    end

    subgraph model["मॉडल परत"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["ड्राइवर परत"]
        d1["MySQL PDO"]
        d2["Elasticsearch HTTP"]
        d3["Redis"]
    end

    r1 --> m1 --> m2
    m2 --> user & role & perm & dash & export
    m1 --> captcha & auth
    base -.->|extends| user & role & perm & dash & export
    user & role & perm & dash & export & captcha & auth --> s1 & s2 & s3
    user & role & perm & dash & export & captcha & auth --> md1 & md2 & md3 & md4 & md5
    md1 & md2 & md3 & md4 & md5 --> d1
    md1 --> d2
    captcha --> d3

    style r1 fill:#722ED1,color:#fff
    style m1 fill:#FA8C16,color:#fff
    style m2 fill:#FA8C16,color:#fff
    style base fill:#1677FF,color:#fff
    style s1 fill:#52C41A,color:#fff
    style s2 fill:#52C41A,color:#fff
    style s3 fill:#52C41A,color:#fff
```
