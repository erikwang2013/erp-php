# Open Admin — Document de conception

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Pour les diagrammes d'architecture Mermaid détaillés, voir [ARCHITECTURE.md](ARCHITECTURE.md) (rendu automatique sous GitHub/GitLab/VS Code).

## 1. Architecture système

> **Liste des fonctionnalités** : authentification (login/register/refresh/logout + verrouillage de compte + limitation des sessions) | tableau de bord (cache Redis) | CRUD utilisateurs + opérations en masse + import | rôles et permissions (RBAC) | configuration système | audit des opérations (8 plateformes source) | fichiers (upload + export + masquage) | sécurité (18 couches de défense) | exploitation (health/metrics/docs/Docker/CI)

```
┌──────────────────────────────────────────────────────────────┐
│                        客户端层                               │
│  ┌──────────────────────┐  ┌──────────────────────────────┐  │
│  │  Flutter Web (PC)    │  │  HarmonyOS ArkTS (Mobile)    │  │
│  │  管理后台 (桌面风格)   │  │  客户端 (手机/平板/2in1)      │  │
│  └──────────┬───────────┘  └──────────────┬───────────────┘  │
└─────────────┼──────────────────────────────┼─────────────────┘
              │        HTTPS / JSON          │
              │   Authorization: Bearer JWT  │
┌─────────────┼──────────────────────────────┼─────────────────┐
│             ▼                              ▼                  │
│  ┌──────────────────────────────────────────────────────┐    │
│  │                   API 网关层                          │    │
│  │  AdminAuth(认证) → AdminPermission(授权) → Controller │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              业务逻辑层 (Controller/Service)           │    │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │    │
│  │  │Dashboard │ │  User    │ │  Role    │ │ Export  │ │    │
│  │  │Controller│ │Controller│ │Controller│ │Controller│ │    │
│  │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬────┘ │    │
│  └───────┼────────────┼─────────────┼────────────┼──────┘    │
│          │            │             │            │            │
│  ┌───────┼────────────┼─────────────┼────────────┼──────┐    │
│  │       ▼            ▼             ▼            ▼       │    │
│  │                   Model 层                            │    │
│  │  ┌──────────────────────────────────────────────┐    │    │
│  │  │  Snowflake ID ← encryptable → Encryption     │    │    │
│  │  │  (主键生成)     (DB字段加密)   (API传输加密)    │    │    │
│  │  └──────────────────────────────────────────────┘    │    │
│  └──────────────────────────┬───────────────────────────┘    │
│                             │                                  │
│  ┌──────────────────────────┼───────────────────────────┐    │
│  │              数据存储层                                │    │
│  │  ┌──────────┐  ┌──────────────┐  ┌──────────┐        │    │
│  │  │  MySQL   │  │ Elasticsearch│  │  Redis   │        │    │
│  │  │ (主存储)  │  │ (全文检索)    │  │ (缓存)   │        │    │
│  │  └──────────┘  └──────────────┘  └──────────┘        │    │
│  └──────────────────────────────────────────────────────┘    │
│                       webman v2                               │
└──────────────────────────────────────────────────────────────┘
```

## 2. Architecture backend

### 2.1 Conception en couches

| Couche | Répertoire | Responsabilités |
|---|------|------|
| Routage | `config/route.php` | Mappage URL→contrôleur, liaison des middlewares, routes versionnées |
| Middlewares | `app/middleware/` | Interception des attaques (SecurityFilter), limitation de débit (RateLimit), authentification (JWT), autorisation (RBAC), version d'API (ApiVersion) |
| Contrôleurs | 14 : Dashboard/User/Role/Permission/Config/Log/Profile/Export/Import/Upload/Health/Docs (côté admin) + Captcha/Auth (API v1) | Validation des paramètres de requête, appel de la logique métier, formatage des réponses |
| Services métier | `app/service/` | Logique métier réutilisable (réservé) |
| Modèles de données | `app/model/` | Mapping ORM, relations, chiffrement/déchiffrement des champs |
| Utilitaires communs | `app/common/` | Services Hashids, Snowflake, Encryption |

### 2.2 Cycle de vie d'une requête

```
客户端请求
  │
  ▼
webman HTTP Server (workerman)
  │
  ▼
Route 匹配
  │
  ▼
中间件链:
  SecurityFilter ──────► HTTP方法检查 → 405 (仅允许 GET/POST/PUT/DELETE/OPTIONS/HEAD)
  │                     XSS/SQL注入/路径遍历/命令注入/CSRF 攻击拦截 (403)
  ▼
  RateLimit ───────────► Redis 滑动窗口限流
  │ (失败返回 429 + Retry-After 头)
  ▼
  ApiVersion ─────────► API-Version 头校验，注入 $request->apiVersion
  │ (失败返回 400)
  ▼
  AdminAuth ──────────► JWT 验证，注入 $request->adminId
  │ (失败返回 401)
  ▼
  AdminPermission ────► RBAC 权限校验（Redis 60s 缓存）
  │ (失败返回 403)
  ▼
  OperationLog ───────► 操作日志记录 (POST/PUT/DELETE)，自动检测来源端
  │
  ▼
Controller::method()
  │
  ├─► 参数验证 (validator)
  ├─► 敏感操作确认 (confirmPassword)
  ├─► decodeId() — hashid → BIGINT
  ├─► Model 操作 (自动 encryptable 加解密)
  ├─► encodeId() — BIGINT → hashid
  └─► Response JSON
```

### 2.3 Cycle de vie des ID

```
生成 (Snowflake) → 存储 (MySQL BIGINT) → 传输 (Hashids 编码) → 外部 (hash 字符串)
                                                                    │
                            HashidsService::decode() ←──────────────┘
```

### 2.4 Système de chiffrement des données

```
传输层 (encryption)     — AES-256-CBC，独立密钥
存储层 (encryptable)    — AES-128-ECB，独立密钥，Model $casts 自动处理
展示层 (mask)           — 手机号: 138****1234，邮箱: a***@example.com
```

## 3. Conception de la base de données

### 3.1 Relations ER

```
erp_admin_user ──┬── erp_admin_user_role ──┬── erp_admin_role
  (用户)           │    (用户-角色关联)         │     (角色)
                  │                          │
                  │                    erp_admin_role_permission
                  │                     (角色-权限关联)
                  │                          │
                  │                          ▼
                  │                    erp_admin_permission
                  │                      (权限/菜单)
                  │
                  ▼
           erp_operation_log
             (操作日志)

erp_system_config (系统配置) — 独立表
```

### 3.2 Structure des tables principales

| Nom de table | Nombre de champs | Description |
|------|-------|------|
| `erp_admin_user` | 14 | Utilisateurs administrateurs, phone/email/id_card stockés chiffrés, suppression logique prise en charge |
| `erp_admin_role` | 7 | Rôles, slug unique |
| `erp_admin_permission` | 10 | Arbre de permissions (parent_id auto-référence), type : 1=menu 2=bouton 3=API |
| `erp_admin_user_role` | 2 | Table d'association many-to-many utilisateur-rôle |
| `erp_admin_role_permission` | 2 | Table d'association many-to-many rôle-permission |
| `erp_system_config` | 8 | Configuration clé-valeur, unicité conjointe group+key |
| `erp_operation_log` | 9 | Journal d'audit des opérations (inclut la plateforme source) |

### 3.3 Norme des clés primaires

- Type : `BIGINT UNSIGNED NOT NULL`
- Caractéristiques : **non auto-incrémentée**, générée au niveau applicatif par l'algorithme Snowflake
- Avantages : unicité globale, adaptée aux environnements distribués, croissance tendancielle favorable aux index, ne divulgue pas le volume d'activité
- Configuration : datacenter_id(0-31) + worker_id(0-31), prise en charge de 1024 nœuds concurrents

## 4. Conception de l'API

### 4.1 Normes d'URL

```
接口公开:  /api/captcha/{generate|verify}
           /api/auth/{login|register|refresh}

管理端:   /admin/{resource}[/{hashid}]
          /admin/export/{excel|pdf}

路由 des ressources:
  GET    /admin/user          → 列表
  POST   /admin/user          → 创建
  GET    /admin/user/{hashid} → 详情
  PUT    /admin/user/{hashid} → 更新
  DELETE /admin/user/{hashid} → 删除（需密码确认）

配置 système:  /admin/config[/{hashid}]
Journal d'opérations:  /admin/log
Espace personnel:  /admin/profile[/password|/logout]
Import:     /admin/import/users
Upload:     /admin/upload
En masse:   /admin/user/batch/{destroy|status}
Documentation:  /api/docs     (OpenAPI 3.0)
Health:     /health
```

### 4.2 Stratégie de version d'API

La version de l'API est contrôlée par l'en-tête de requête, **et n'apparaît pas dans le chemin d'URL** :

```http
API-Version: v1
```

| Mécanisme | Description |
|------|------|
| Version par défaut | Sans en-tête `API-Version`, la version par défaut est `v1` |
| Validation | Validée par le middleware `ApiVersion`, les versions non prises en charge renvoient 400 |
| Routage | La fonction d'aide `v()` résout dynamiquement la classe de contrôleur selon la version |
| Répertoires | Les contrôleurs sont organisés par version : `app/api/{version}/controller/` |

Exemple d'extension — ajout d'une API v2 :
1. Créer `app/api/v2/controller/AuthController.php`
2. Ajouter `'v2'` à la constante `SUPPORTED` du middleware `ApiVersion`
3. Aucune modification de la définition des routes

```bash
# Utilisation de v1
curl -H "API-Version: v1" /api/auth/login

# Utilisation de v2
curl -H "API-Version: v2" /api/auth/login

# Sans en-tête, défaut v1
curl /api/auth/login
```

### 4.3 Stratégie de limitation de débit

Algorithme de fenêtre glissante basé sur Redis Sorted Set, exécuté en script Lua atomique :

| Interface | Limite |
|------|------|
| Par défaut | 60 requêtes/minute/IP/route |
| POST /api/auth/login | 10 requêtes/minute |
| POST /api/auth/register | 5 requêtes/minute |

En cas de dépassement, renvoie 429 ; les en-têtes de réponse contiennent X-RateLimit-Limit / Remaining / Reset / Retry-After.

### 4.4 Réponse unifiée

```json
{
  "code": 0,
  "message": "success",
  "data": { ... }
}
```

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 0 | Succès | Réponse normale |
| 400 | Erreur de paramètres | Format de requête incorrect |
| 401 | Non authentifié | Jeton manquant/expiré/invalide |
| 403 | Sans permission | Le rôle de l'utilisateur ne contient pas la permission requise |
| 404 | Inexistant | Ressource introuvable |
| 422 | Échec de validation | Paramètres de formulaire non conformes / échec de confirmation du mot de passe |
| 500 | Erreur serveur | Exception inattendue |

### 4.5 Flux d'authentification (avec captcha à clic)

```
客户端                               服务端
  │                                    │
  │  ① POST /api/captcha/generate     │ captcha_create('click')
  │◄── {key, image(base64), targets}  │
  │                                    │
  │  ② 用户点击图中文字位置              │
  │                                    │
  │  ③ POST /api/auth/login           │
  │     {username, password,          │
  │      captcha_key, clicks}         │
  │────────────────────────────────►  │
  │                                    │ ① captcha_verify()
  │                                    │ ② password_verify()
  │                                    │ ③ jwt()->create()
  │◄── {access_token, refresh_token}  │
  │                                    │
  │  ④ GET /admin/dashboard           │
  │     Authorization: Bearer xxx     │
  │────────────────────────────────►  │ AdminAuth → AdminPermission
  │◄── 200 {dashboard data}           │
```

### 4.6 Modèle de permissions (RBAC)

```
  用户 ──┬── 角色 ──┬── 权限
  User     Role      Permission
                 │
                 ├── type=1: 菜单 (控制侧边栏可见)
                 ├── type=2: 按钮 (控制页面内操作)
                 └── type=3: API  (控制接口访问)

  权限标识格式: {method}.{path}
  例: get.admin/user  post.admin/user  delete.admin/user
  超级管理员标识: * (跳过所有权限检查)
```

### 4.7 Double confirmation des opérations sensibles

Les opérations sensibles comme la suppression d'utilisateurs, de rôles ou de permissions exigent de transmettre le mot de passe de l'utilisateur courant dans le corps de la requête pour ré-authentifier l'identité :

```
客户端                           服务端
  │                                │
  │  DELETE /admin/user/{hashid}  │
  │  { password: "******" }       │
  │────────────────────────────►  │
  │                                │ confirmPassword(adminId, password)
  │                                │ → 密码错误返回 422
  │                                │ → 密码正确继续执行
  │◄── 200 { code: 0 }           │
```

Avant de déclencher une opération de suppression, le frontend affiche une boîte de dialogue de confirmation, recueille le mot de passe de l'utilisateur puis envoie la requête.

## 5. Conception frontend

### 5.1 Console d'administration Flutter Web

```
┌────────────────────────────────────────────────┐
│  Header (56px)                                 │
│  ☰ 菜单按钮           🔔 消息  👤 管理员  ▼    │
├──────────┬─────────────────────────────────────┤
│ Sidebar  │  Content Area                       │
│ (64/240) │                                     │
│          │  ┌──────────────┐ ┌──────────┐     │
│ 📊 仪表盘│  │ 统计卡片×4    │ │ 趋势图   │     │
│ 👥 用户  │  └──────────────┘ └──────────┘     │
│ 🔒 角色  │  ┌──────┐ ┌────────────────┐       │
│ ⚙ 配置  │  │饼图  │ │ 最近操作日志    │       │
│ 📋 日志  │  └──────┘ └────────────────┘       │
└──────────┴─────────────────────────────────────┘
```

Caractéristiques : barre latérale repliable, double thème Material 3, tableaux de données haute densité, boîtes de dialogue Dialog, interactions au survol de la souris

### 5.2 Côté mobile HarmonyOS

Routage des pages :

| Page | Route | Description |
|------|------|------|
| LoginPage | `pages/LoginPage` | Nom d'utilisateur + mot de passe + connexion avec captcha à clic |
| DashboardPage | `pages/DashboardPage` | Cartes statistiques + opérations récentes |
| UserListPage | `pages/UserListPage` | Liste des utilisateurs, recherche + rafraîchissement par glissement + chargement en remontant |
| UserDetailPage | `pages/UserDetailPage` | Création/édition/consultation/suppression (confirmation par AlertDialog) |
| ProfilePage | `pages/ProfilePage` | Espace personnel, déconnexion (confirmation par AlertDialog) |

Flux de données : Page ← DataService ← ApiService (JWT Bearer) ← HTTP ← webman

## 6. Conception de la sécurité

### 6.1 Défense en profondeur

| Couche | Mesure |
|------|------|
| Limitation des méthodes | Liste blanche des méthodes HTTP du SecurityFilter, seuls GET/POST/PUT/DELETE/OPTIONS/HEAD autorisés, les méthodes non standard renvoient 405 |
| Interception des attaques | Middleware SecurityFilter, détection et interception XSS/injection SQL/traversée de chemins/injection de commandes/CSRF |
| Vérification homme-machine | Captcha à clic (Click Captcha), validation obligatoire à la connexion/inscription |
| Verrouillage de compte | Après 5 échecs de connexion consécutifs, verrouillage du compte 15 minutes, renvoie 429 pendant le verrouillage |
| Limitation des sessions | 3 jetons concurrents au maximum par utilisateur, le jeton le plus ancien passe automatiquement en liste noire au-delà |
| Limitation de débit | Middleware RateLimit, fenêtre glissante Redis, atomique en Lua |
| CSP | En-tête Content-Security-Policy limitant l'origine des ressources, contre le XSS et l'injection de données |
| Confirmation des opérations | Les opérations sensibles comme la suppression exigent de saisir à nouveau le mot de passe de l'utilisateur courant |
| Transport | HTTPS + jeton Bearer JWT |
| ID d'interface | Chiffrés par Hashids, impossibles à déduire en externe vers l'ID réel |
| Corps de requête | Chiffrement AES-256-CBC des champs sensibles |
| Base de données | Clé primaire BIGINT (sans exposition de l'auto-incrément) |
| Base de données | Stockage chiffré AES-128-ECB des champs sensibles |
| Authentification | JWT HS256, expiration 2 h + refresh token |
| Autorisation | RBAC, contrôle de permission à granularité method.path |
| Audit | OperationLog enregistre toutes les opérations (y compris la détection automatique de la plateforme source) |

### 6.2 Gestion des clés

```
JWT_SECRET          → 环境变量注入，64位随机字符串
HASHIDS_SALT        → 唯一盐值，泄漏后需全局更换
ENCRYPTION_KEY      → API 传输加密密钥，32字节
ENCRYPTABLE_KEY     → DB 存储加密密钥，与传输密钥独立
SCOUT_HOSTS         → ES 地址，内网部署
```

### 6.3 Protection des données sensibles

| Scénario | Champ | Mesure |
|------|------|------|
| Affichage en liste | phone | Masquage : 138****1234 |
| Affichage en liste | email | Masquage : a***@example.com |
| Consultation du détail | phone/email | Nécessite l'interface de déchiffrement |
| Export Excel | phone/email | Export après masquage |
| Export PDF | tous les champs | Masquage + filigrane de droit d'auteur inamovible |
| Stockage | phone/email/id_card | Chiffrés en texte chiffré par encryptable |

## 7. Conception des exports

### 7.1 Export Excel

```
请求: POST /admin/export/excel { table, columns, conditions, title }
  → fetchExportData() 查询数据 (limit 10000)
  → 脱敏敏感字段
  → PhpSpreadsheet 构建（蓝底白字表头 + 冻结首行 + 自动筛选）
  → 写入 runtime/tmp/ → download 响应
```

### 7.2 Export PDF

```
请求: POST /admin/export/pdf { type: table|dashboard, title, data }
  → buildPdfHtml() HTML + 内联CSS + 页头版权 + 页脚不可移除版权
  → Dompdf 渲染 A4 横向
  → 写入 runtime/tmp/ → download 响应
```

## 8. Architecture de déploiement

### 8.1 Topologie recommandée

```
Nginx (:443 HTTPS) → webman worker × N (:8788) → MySQL + ES + Redis
                    静态文件: Flutter Web build/
```

### 8.2 Docker Compose (recommandé en production)

Le `docker-compose.yml` à la racine du projet orchestre tous les services de la topologie ci-dessus :

| Service | Image/build | Ports | Description |
|------|----------|------|------|
| `nginx` | nginx:alpine | 80, 443 | Proxy inverse + fichiers statiques + Gzip |
| `app` | build via `Dockerfile` local | 8788 | PHP 8.3 + OPcache + webman |
| `mysql` | mysql:8.0 | 3306 | Base de données principale, persistance par volume |
| `redis` | redis:7-alpine | 6379 | Cache / limitation de débit / captcha |
| `elasticsearch` | elasticsearch:8.x | 9200 | Recherche plein texte |

Avant le démarrage, remplacer les clés de `docker-compose.yml` — `JWT_SECRET`, `HASHIDS_SALT`, `ENCRYPTION_KEY` — par des chaînes aléatoires.

```bash
cp .env.docker .env
docker-compose up -d
```

### 8.3 CI/CD

L'intégration continue GitHub Actions est définie dans `.github/workflows/ci.yml` :
- Contrôle de syntaxe PHP (`php -l`)
- Tests unitaires PHPUnit
- Analyse statique Flutter (`flutter analyze`)

### 8.4 Sauvegarde de la base de données

`database/backup/backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours.
`database/backup/restore.sh` — sélection interactive et restauration des sauvegardes.

### 8.5 Surveillance

Le point de terminaison `GET /metrics` (`MetricsController`) expose 5 métriques gauge au format texte Prometheus : nombre total de requêtes HTTP, nombre d'utilisateurs actifs, état des connexions base de données/Redis, utilisation de la mémoire.

### 8.6 Exigences d'environnement

| Composant | Version minimale | Configuration recommandée |
|------|---------|---------|
| PHP | 8.3+ | 8.3+ avec OPcache activé |
| MySQL | 8.0+ | 8.0+ réplication maître-esclave |
| Elasticsearch | 7.x | 8.x cluster de 3 nœuds |
| Redis | 6.x | 7.x mode sentinelle |
| Nginx | 1.20+ | Proxy inverse + gzip + SSL |
| Flutter SDK | 3.41+ | Dernière version stable |
| HarmonyOS | API 12 | DevEco Studio 5.x |
