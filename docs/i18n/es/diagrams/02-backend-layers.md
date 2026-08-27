# Arquitectura de capas del backend

```mermaid
flowchart TD
    subgraph route["Capa de rutas"]
        r1["config/route.php<br/>Mapeo URL→Controller"]
    end

    subgraph middleware["Capa de middleware"]
        m1["AdminAuth<br/>Verificación de token JWT<br/>Inyección de adminId"]
        m2["AdminPermission<br/>Autorización RBAC<br/>Coincidencia method.path"]
    end

    subgraph controller["Capa de controladores"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["Capa de servicios"]
        s1["HashidsService<br/>Codificación/decodificación de ID"]
        s2["SnowflakeService<br/>Generación global de ID"]
        s3["EncryptionService<br/>Cifrado/descifrado + enmascarado"]
    end

    subgraph model["Capa de modelos"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["Capa de drivers"]
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
