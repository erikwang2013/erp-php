# Modelo de permissões RBAC

## Relação usuário-permissão

```mermaid
flowchart LR
    subgraph users["Usuários"]
        u1["admin(superadministrador)"]
        u2["editor(edita)"]
        u3["viewer(somente leitura)"]
    end

    subgraph roles["Papéis"]
        r1["super_admin<br/>Identificador de permissão: *"]
        r2["editor<br/>Identificador de permissão: get.* post.*"]
        r3["viewer<br/>Identificador de permissão: get.*"]
    end

    subgraph permissions["Permissões (árvore)"]
        p1["dashboard(menu)"]
        p2["user(menu)"]
        p3["get.admin/user(API)"]
        p4["post.admin/user(API)"]
        p5["delete.admin/user(API)"]
        p6["export.excel(botão)"]
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

## Fluxo de decisão de permissão

```mermaid
flowchart TD
    start["Requisição chega"] --> extract["Extrai Token→adminId"]
    extract --> findRoles["Consulta papéis do usuário"]
    findRoles --> collectSlug["Coleta todos permission.slug"]
    collectSlug --> buildKey["Constrói method.path"]
    buildKey --> check{"slug==* ou<br/>slug corresponde?"}
    check -->|"Sim"| allow["200 Permite"]
    check -->|"Não"| deny["403 Forbidden"]

    style allow fill:#52C41A,color:#fff
    style deny fill:#FF4D4F,color:#fff
```

## Tipos de permissão

```mermaid
flowchart LR
    t1["type=1 Menu<br/>Controla exibição da barra lateral"]
    t2["type=2 Botão<br/>Controla botões de ação"]
    t3["type=3 API<br/>Controla acesso à interface"]

    style t1 fill:#1677FF,color:#fff
    style t2 fill:#FA8C16,color:#fff
    style t3 fill:#52C41A,color:#fff
```
