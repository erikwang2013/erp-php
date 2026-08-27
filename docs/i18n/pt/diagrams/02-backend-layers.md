# Arquitetura em camadas do backend

```mermaid
flowchart TD
    subgraph route["Camada de rotas"]
        r1["config/route.php<br/>Mapeamento URL→Controller"]
    end

    subgraph middleware["Camada de middlewares"]
        m1["AdminAuth<br/>Validação de Token JWT<br/>Injeta adminId"]
        m2["AdminPermission<br/>Autorização RBAC<br/>Correspondência method.path"]
    end

    subgraph controller["Camada de controllers"]
        base["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        user["UserController"]
        role["RoleController"]
        perm["PermissionController"]
        dash["DashboardController"]
        export["ExportController"]
        captcha["CaptchaController"]
        auth["AuthController"]
    end

    subgraph service["Camada de serviços"]
        s1["HashidsService<br/>Codificação/decodificação de ID"]
        s2["SnowflakeService<br/>Geração global de ID"]
        s3["EncryptionService<br/>Criptografia/descriptografia + mascaramento"]
    end

    subgraph model["Camada de modelos"]
        md1["AdminUser<br/>encryptable casts"]
        md2["AdminRole"]
        md3["AdminPermission"]
        md4["OperationLog"]
        md5["SystemConfig"]
    end

    subgraph driver["Camada de drivers"]
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
