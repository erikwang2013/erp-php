# Documentation de référence API

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Documentation API

Le projet utilise [hg/apidoc](https://github.com/hg-code/apidoc) pour générer automatiquement une documentation API interactive.

**Accès :** après le démarrage du service, accédez à `http://localhost:8788/apidoc`

**Groupes de documentation :**
| Groupe | Description | Nombre de modules |
|------|------|--------|
| Interfaces d'administration (Admin) | Toutes les interfaces du système de gestion backend | 25 modules |
| Interfaces client (Service API) | Interfaces légères appelées par le mobile / le Web | 3 modules |

**En-têtes globaux :**
| En-tête | Description |
|--------|------|
| `Authorization` | JWT Bearer Token |
| `API-Version` | Numéro de version API (v1) |
| `Accept-Language` | Langue d'internationalisation (zh-CN/en) |

**Convention d'annotations :** toutes les méthodes de contrôleur utilisent les annotations de la série `@Apidoc\*` pour indiquer le nom de l'interface, la description, l'URL, la méthode de requête, les paramètres et la structure de la valeur de retour.

## 1. Présentation

La console d'administration ouverte (open-admin), construite sur webman v2, fournit une API JSON RESTful. Toutes les interfaces d'administration exigent l'authentification JWT et la validation des permissions RBAC ; les interfaces publiques sont routées vers des contrôleurs versionnés via l'en-tête de version API.

- **URL de base** : `http://localhost:8788`
- **Version API** : contrôlée par l'en-tête de requête `API-Version: v1` (v1 par défaut si absent)

> **Aperçu des points de terminaison** : Authentification (5) | Tableau de bord (1) | Utilisateurs (7) | Rôles (4) | Permissions (4) | Configuration (4) | Journaux (1) | Espace personnel (3) | Import-export (3) | Téléversement (1) | Exploitation (4 : health/metrics/docs/security.txt) | Total 37 points de terminaison
- **Authentification** : `Authorization: Bearer <token>` (JWT)
- **Format de réponse** : `{ "code": 0, "message": "success", "data": {...} }`
- **Point de terminaison de documentation** : `GET /api/docs` renvoie la spécification JSON OpenAPI 3.0

### Internationalisation

L'API bascule automatiquement de langue via l'en-tête de requête `Accept-Language` :

| Valeur de l'en-tête | Langue |
|---------|------|
| `zh-CN`, `zh` | Chinois (par défaut) |
| `en`, `en-US` | English |

```bash
# 英文响应
curl -H "Accept-Language: en" http://localhost:8788/admin/product

# 中文响应（默认）
curl http://localhost:8788/admin/product
```

Le champ `message` de la réponse est renvoyé dans la langue correspondante.

### Exigences de requête

- Seules les méthodes `GET` / `POST` / `PUT` / `DELETE` / `OPTIONS` / `HEAD` sont autorisées ; toute autre méthode HTTP (comme TRACE, CONNECT, PATCH) renvoie 405
- Toutes les requêtes `POST` / `PUT` doivent définir `Content-Type: application/json` (sauf téléversement de fichiers), sinon 415 est renvoyé
- La taille du corps de requête ne doit pas dépasser 10 Mo, sinon 413 est renvoyé
- Le filtre de sécurité analyse toutes les entrées de requête contre XSS, l'injection SQL, la traversée de chemin et l'injection de commande ; toute correspondance renvoie 403
- 5 échecs de connexion consécutifs déclenchent le verrouillage du compte (15 minutes) ; pendant le verrouillage, les requêtes de connexion renvoient 429
- Un même utilisateur peut détenir au maximum 3 jetons valides simultanément ; au-delà, le jeton le plus ancien est automatiquement ajouté à la liste noire

## 2. Codes d'erreur

| code | Signification | Scénarios déclencheurs |
|------|------|---------|
| 0 | Succès | |
| 400 | Erreur de paramètre de requête | Format de requête incorrect |
| 401 | Non authentifié | Jeton manquant / expiré / en liste noire |
| 403 | Pas d'autorisation / interception de sécurité | Permissions RBAC insuffisantes / correspondance SecurityFilter |
| 404 | Ressource inexistante | La cible de la consultation / mise à jour / suppression n'existe pas |
| 405 | Méthode de requête non autorisée | Seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés ; les méthodes non standard sont rejetées |
| 413 | Corps de requête trop volumineux | Content-Length supérieur à 10 Mo |
| 415 | Type de média non pris en charge | Content-Type non JSON et non téléversement de fichier pour les requêtes POST/PUT |
| 422 | Échec de validation des paramètres | Champ obligatoire manquant, format incorrect, validation métier non validée |
| 429 | Trop de requêtes | Déclenché par RateLimit / verrouillage de compte (5 échecs de connexion consécutifs → verrouillage 15 minutes) |
| 500 | Erreur interne du serveur | |

## 3. Points de terminaison publics

Tous les points de terminaison publics sont montés sous le groupe `/api` et distribués par le middleware `ApiVersion` vers le contrôleur versionné correspondant selon l'en-tête `API-Version` (par exemple `app\api\v1\controller\AuthController`).

### 3.1 Vérification de santé

```
GET /health
```

- **Authentification** : aucune
- **Limitation de débit** : aucune

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1716249600
  }
}
```

Valeurs de `database`, `redis`, `elasticsearch` : `"ok"` | `"unavailable"`. `elasticsearch` renvoie `"unavailable"` si ES est inaccessible ; si l'état de santé du cluster n'est ni green ni yellow, la valeur réelle du statut est renvoyée (par exemple `"red"`).

### 3.2 Documentation API

```
GET /api/docs
```

- **Authentification** : aucune
- **Limitation de débit** : globale par défaut (60 requêtes/minute)
- **Réponse** : spécification JSON OpenAPI 3.0.3, contenant toutes les définitions de points de terminaison, les paramètres et les schémas

### 3.3 Générer un captcha à clic

```
POST /api/captcha/generate
```

- **Authentification** : aucune
- **En-tête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : globale par défaut (60 requêtes/minute)

**Corps de requête** :
```json
{
  "difficulty": "medium"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| difficulty | string | Non | `easy` / `medium` / `hard`, par défaut `medium` |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "key": "abc123def456",
    "image": "iVBORw0KGgoAAAANSUhEUgAA...",
    "extra": {
      "targets": [
        { "order": 1, "text": "请点击 A" },
        { "order": 2, "text": "请点击 B" }
      ]
    }
  }
}
```

| Champ | Type | Description |
|------|------|------|
| key | string | Identifiant du captcha, à renvoyer lors de la validation |
| image | string | Image PNG encodée en base64 |
| extra.targets[].order | int | Ordre de clic |
| extra.targets[].text | string | Texte d'indication de la cible de clic |

### 3.4 Vérifier le captcha à clic

```
POST /api/captcha/verify
```

- **Authentification** : aucune
- **En-tête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : globale par défaut (60 requêtes/minute)

**Corps de requête** :
```json
{
  "key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| key | string | Oui | Clé du captcha, renvoyée par generate |
| clicks | array{object} | Oui | Tableau de coordonnées de clic, chaque élément contient `x` (int) et `y` (int) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "验证通过",
  "data": { "valid": true }
}
```

En cas d'échec de validation, `code` vaut 422, `message` vaut `"验证失败，请重试"` et `data.valid` vaut `false`.

### 3.5 Connexion

```
POST /api/auth/login
```

- **Authentification** : aucune
- **En-tête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : 10 requêtes/minute (par IP + chemin)

**Corps de requête** :
```json
{
  "username": "admin",
  "password": "123456",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur |
| password | string | Oui | min:6, max:32 | Mot de passe |
| captcha_key | string | Oui | | Clé du captcha |
| clicks | array{object} | Oui | min:2 | Tableau de coordonnées de clic |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "登录成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "a1b2c3d4",
      "username": "admin",
      "real_name": "管理员"
    }
  }
}
```

| Champ | Type | Description |
|------|------|------|
| access_token | string | Jeton d'accès JWT |
| refresh_token | string | Jeton de rafraîchissement JWT |
| expires_in | int | Durée de validité du jeton d'accès (secondes), par défaut 7200 |
| user.id | string | ID utilisateur chiffré par hashid |
| user.username | string | Nom d'utilisateur |
| user.real_name | string | Nom réel |

**Erreurs possibles** :
- 422 : échec de validation des paramètres (champ obligatoire manquant, format incorrect)
- 422 : captcha incorrect, veuillez réessayer
- 401 : nom d'utilisateur ou mot de passe incorrect
- 403 : compte désactivé
- 429 : compte verrouillé, veuillez réessayer dans 15 minutes (déclenché par 5 échecs de connexion consécutifs)

### 3.6 Inscription

```
POST /api/auth/register
```

- **Authentification** : aucune
- **En-tête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : 5 requêtes/minute (par IP + chemin)
- **Interrupteur** : désactivé par défaut (`REGISTRATION_ENABLED=0`) ; renvoie 403 lorsqu'il est désactivé ; doit être activé explicitement dans `.env` (`REGISTRATION_ENABLED=1`)

**Corps de requête** :
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "captcha_key": "abc123def456",
  "clicks": [
    { "x": 120, "y": 85 },
    { "x": 310, "y": 42 }
  ]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 | Mot de passe (stocké avec hash bcrypt) |
| real_name | string | Oui | max:50 | Nom réel |
| captcha_key | string | Oui | | Clé du captcha |
| clicks | array{object} | Oui | min:2 | Tableau de coordonnées de clic |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "注册成功",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200,
    "user": {
      "id": "e5f6g7h8",
      "username": "newuser",
      "real_name": "新用户"
    }
  }
}
```

Après une inscription réussie, le jeton JWT est renvoyé directement et l'état de l'utilisateur est activé par défaut (status=1). Ce point de terminaison n'est disponible que lorsque `REGISTRATION_ENABLED=1`.

### 3.7 Rafraîchir le jeton

```
POST /api/auth/refresh
```

- **Authentification** : aucune
- **En-tête** : `API-Version: v1` (obligatoire)
- **Limitation de débit** : globale par défaut (60 requêtes/minute)

**Corps de requête** :
```json
{
  "refresh_token": "eyJhbGciOiJIUzI1NiIs..."
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| refresh_token | string | Oui | refresh_token obtenu à la connexion / l'inscription |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "access_token": "eyJhbGciOiJIUzI1NiIs...",
    "refresh_token": "eyJhbGciOiJIUzI1NiIs...",
    "expires_in": 7200
  }
}
```

Un rafraîchissement réussi renvoie simultanément un nouveau access_token et un nouveau refresh_token ; l'ancien jeton est automatiquement invalidé. Le rafraîchissement met également à jour la dernière heure de connexion et l'IP de l'utilisateur.

**Erreurs possibles** :
- 422 : jeton de rafraîchissement manquant
- 401 : jeton de rafraîchissement invalide ou expiré

### 3.8 Métriques de supervision Prometheus

```
GET /metrics
```

- **Authentification** : aucune
- **Limitation de débit** : aucune
- **Format de réponse** : format texte Prometheus (`text/plain; version=0.0.4`)

Point de terminaison public des métriques de supervision Prometheus, destiné à être collecté par Grafana/Prometheus.

**Exemple de réponse** :
```
# HELP openadmin_http_requests_total Total number of HTTP requests.
# TYPE openadmin_http_requests_total gauge
openadmin_http_requests_total 15234

# HELP openadmin_active_users Number of active users.
# TYPE openadmin_active_users gauge
openadmin_active_users 156

# HELP openadmin_db_connection_status Database connection status (1=ok, 0=fail).
# TYPE openadmin_db_connection_status gauge
openadmin_db_connection_status 1

# HELP openadmin_redis_connection_status Redis connection status (1=ok, 0=fail).
# TYPE openadmin_redis_connection_status gauge
openadmin_redis_connection_status 1

# HELP openadmin_memory_usage_bytes Memory usage in bytes.
# TYPE openadmin_memory_usage_bytes gauge
openadmin_memory_usage_bytes 18874368
```

| Nom de la métrique | Type | Description |
|------|------|------|
| `openadmin_http_requests_total` | gauge | Nombre total cumulé de requêtes HTTP |
| `openadmin_active_users` | gauge | Nombre d'utilisateurs actifs actuels (connectés au cours des 24 dernières heures) |
| `openadmin_db_connection_status` | gauge | État de la connexion à la base de données, 1=normal, 0=anormal |
| `openadmin_redis_connection_status` | gauge | État de la connexion Redis, 1=normal, 0=anormal |
| `openadmin_memory_usage_bytes` | gauge | Mémoire actuellement utilisée par le processus PHP (octets) |

## 4. Tableau de bord

Toutes les interfaces d'administration sont montées sous le groupe `/admin` et passent par trois middlewares : `AdminAuth` (authentification JWT), `AdminPermission` (validation des permissions RBAC), `OperationLog` (enregistrement des opérations).

### 4.1 Données du tableau de bord

```
GET /admin/dashboard
```

- **Authentification** : JWT + RBAC
- **Cache** : Redis 5 minutes

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "stats": [
      {
        "label": "用户总数",
        "value": "1280",
        "icon": "people",
        "color": "#1677FF",
        "trend": 12.5
      },
      {
        "label": "今日新增",
        "value": "23",
        "icon": "person_add",
        "color": "#52C41A"
      },
      {
        "label": "活跃用户",
        "value": "156",
        "icon": "bolt",
        "color": "#FA8C16"
      },
      {
        "label": "操作日志",
        "value": "432",
        "icon": "description",
        "color": "#722ED1"
      }
    ],
    "trends": {
      "dates": ["2026-04-22", "2026-04-23", "..."],
      "series": [
        { "name": "累计用户", "data": [1200, 1210, "..."], "color": "#1677FF" },
        { "name": "操作日志", "data": [45, 52, "..."], "color": "#52C41A" }
      ]
    },
    "distribution": {
      "user_status": [
        { "name": "启用", "value": 1250 },
        { "name": "禁用", "value": 30 }
      ]
    },
    "recent_logs": [
      {
        "id": "hashid...",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "user_name": "admin",
        "created_at": "2026-05-21 10:30:00"
      }
    ]
  }
}
```

| Champs de stats | Type | Description |
|------|------|------|
| label | string | Nom de la métrique |
| value | string | Valeur de la métrique (type chaîne) |
| icon | string | Nom de l'icône Material |
| color | string | Couleur de la carte |
| trend | float? | Taux de croissance journalier en glissement (pourcentage), uniquement présent pour « total des utilisateurs » |

| Champs de trends | Type | Description |
|------|------|------|
| dates | array{string} | Séquence de dates des 30 derniers jours |
| series | array{object} | Données de la courbe de tendance, chaque élément contient name (nom), data (tableau de valeurs), color (couleur) |

## 5. Gestion des utilisateurs

Tous les `id` renvoyés par les interfaces de gestion des utilisateurs sont des chaînes chiffrées par hashid. Le champ du mot de passe est exclu des réponses. Le numéro de téléphone et l'e-mail sont masqués dans les interfaces de liste et renvoyés en clair dans les interfaces de détail (les champs chiffrés en base de données sont automatiquement déchiffrés par le trait Encryptable).

### 5.1 Liste des utilisateurs

```
GET /admin/user
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| keyword | string | Non | | Mot-clé de recherche, correspond au nom d'utilisateur et au nom réel |
| status | int | Non | | Filtre d'état, 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "a1b2c3d4",
        "username": "admin",
        "real_name": "管理员",
        "phone": "138****5678",
        "email": "a***@example.com",
        "status": 1,
        "last_login_at": "2026-05-21 10:30:00",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 100,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | ID utilisateur chiffré par hashid |
| username | string | Nom d'utilisateur |
| real_name | string | Nom réel |
| phone | string | Numéro de téléphone masqué (format `138****5678`) |
| email | string | E-mail masqué (format `a***@example.com`) |
| status | int | 1=activé, 0=désactivé |
| last_login_at | string | Dernière heure de connexion (datetime) |
| created_at | string | Heure de création (datetime) |

### 5.2 Créer un utilisateur

```
POST /admin/user
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "username": "newuser",
  "password": "123456",
  "real_name": "新用户",
  "phone": "13800138000",
  "email": "newuser@example.com",
  "status": 1
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| username | string | Oui | min:3, max:50 | Nom d'utilisateur (unique) |
| password | string | Oui | min:6, max:32 | Mot de passe (stocké avec bcrypt) |
| real_name | string | Oui | max:50 | Nom réel |
| phone | string | Non | | Numéro de téléphone (stockage chiffré Encryptable) |
| email | string | Non | | E-mail (stockage chiffré Encryptable) |
| status | int | Non | in:0,1 | État, par défaut 1 (activé) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "e5f6g7h8",
    "username": "newuser",
    "real_name": "新用户",
    "phone": "13800138000",
    "email": "newuser@example.com",
    "status": 1,
    "created_at": "2026-05-21 12:00:00"
  }
}
```

**Erreurs possibles** :
- 422 : nom d'utilisateur déjà existant
- 422 : échec de validation des paramètres (champ obligatoire manquant)

### 5.3 Détail d'un utilisateur

```
GET /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré par hashid

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "管理员",
    "phone": "13800138000",
    "email": "admin@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00",
    "last_login_ip": "192.168.1.1",
    "created_at": "2026-01-01 00:00:00",
    "updated_at": "2026-05-20 08:00:00"
  }
}
```

Dans l'interface de détail, `phone` et `email` sont renvoyés en clair (stockage chiffré en base de données, déchiffrement automatique via le cast Encryptable), sans masquage. `password` et `id_card` ne sont jamais présents dans la réponse.

**Erreurs possibles** :
- 404 : utilisateur inexistant

### 5.4 Mettre à jour un utilisateur

```
PUT /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré par hashid

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "password": "",
  "phone": "13900139000",
  "email": "new@example.com",
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel ; si non transmis, la valeur d'origine est conservée |
| password | string | Non | Nouveau mot de passe ; chaîne vide ou non transmise = pas de modification |
| phone | string | Non | Numéro de téléphone |
| email | string | Non | E-mail |
| status | int | Non | 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13900139000",
    "email": "new@example.com",
    "status": 1,
    "updated_at": "2026-05-21 12:30:00"
  }
}
```

**Erreurs possibles** :
- 404 : utilisateur inexistant

### 5.5 Supprimer un utilisateur

```
DELETE /admin/user/{id}
```

- **Authentification** : JWT + RBAC
- **Paramètre de chemin** : `{id}` est l'ID utilisateur chiffré par hashid
- **Opération sensible** : double confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

Exécute une suppression logique (SoftDeletes Eloquent) : la donnée est marquée avec deleted_at sans être physiquement supprimée.

**Erreurs possibles** :
- 404 : utilisateur inexistant
- 422 : opération sensible nécessitant la confirmation du mot de passe (password vide)
- 422 : échec de validation du mot de passe (mot de passe ne correspondant pas)

### 5.6 Suppression en masse des utilisateurs

```
POST /admin/user/batch/destroy
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : double confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "password": "admin_password"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés par hashid |
| password | string | Oui | Mot de passe de l'utilisateur connecté (double confirmation) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": {
    "count": 2
  }
}
```

Exécute une suppression logique ; `data.count` est le nombre réellement supprimé.

**Erreurs possibles** :
- 422 : veuillez sélectionner les utilisateurs à supprimer (ids vide)
- 422 : ID invalide (échec du décodage hashid)
- 422 : échec de validation du mot de passe

### 5.7 Activer / désactiver en masse les utilisateurs

```
POST /admin/user/batch/status
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "ids": ["a1b2c3d4", "e5f6g7h8"],
  "status": 1
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| ids | array{string} | Oui | Tableau d'ID utilisateurs chiffrés par hashid |
| status | int | Oui | 0=désactivé, 1=activé |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "批量启用成功",
  "data": {
    "count": 2
  }
}
```

Le message change dynamiquement selon la valeur de status : `"批量启用成功"` ou `"批量禁用成功"`.

**Erreurs possibles** :
- 422 : veuillez sélectionner les utilisateurs (ids vide)
- 422 : valeur d'état invalide (status n'est ni 0 ni 1)

## 6. Gestion des rôles

### 6.1 Liste des rôles

```
GET /admin/role
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "r1r2r3r4",
        "name": "超级管理员",
        "slug": "super_admin",
        "description": "拥有所有权限",
        "status": 1,
        "users_count": 3,
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 5,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | ID de rôle chiffré par hashid |
| name | string | Nom du rôle |
| slug | string | Identifiant du rôle (unique, utilisé pour le contrôle des permissions) |
| description | string | Description du rôle |
| status | int | 1=activé, 0=désactivé |
| users_count | int | Nombre d'utilisateurs possédant ce rôle |

### 6.2 Créer un rôle

```
POST /admin/role
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "编辑员",
  "slug": "editor",
  "description": "内容编辑角色",
  "status": 1,
  "permission_ids": [1, 5, 12, 15]
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| name | string | Oui | max:50 | Nom du rôle |
| slug | string | Oui | max:50 | Identifiant du rôle |
| description | string | Non | | Description du rôle, chaîne vide par défaut |
| status | int | Non | | État, par défaut 1 |
| permission_ids | array{int} | Non | | Tableau d'ID de permissions (ID INT d'origine, pas des hashids) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "编辑员",
    "slug": "editor",
    "description": "内容编辑角色",
    "status": 1
  }
}
```

### 6.3 Mettre à jour un rôle

```
PUT /admin/role/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "内容编辑",
  "description": "更新后的描述",
  "status": 1,
  "permission_ids": [1, 5, 12, 20]
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom du rôle |
| description | string | Non | Description |
| status | int | Non | 0=désactivé, 1=activé |
| permission_ids | array{int} | Non | Tableau d'ID de permissions ; s'il est transmis, les permissions du rôle sont synchronisées (remplacées) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "r5r6r7r8",
    "name": "内容编辑",
    "slug": "editor",
    "description": "更新后的描述",
    "status": 1
  }
}
```

### 6.4 Supprimer un rôle

```
DELETE /admin/role/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : double confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression dissocie automatiquement le rôle de toutes ses permissions et de tous ses utilisateurs, puis supprime physiquement l'enregistrement du rôle.

## 7. Gestion des permissions

Les permissions utilisent une structure arborescente (auto-association parent_id) et se répartissent en trois types. L'interface de liste renvoie l'arbre de permissions complet.

### 7.1 Arbre de permissions

```
GET /admin/permission
```

- **Authentification** : JWT + RBAC

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": [
    {
      "id": "p1p2p3p4",
      "parent_id": "0",
      "name": "用户管理",
      "slug": "/admin/user",
      "type": 1,
      "icon": "people",
      "path": "/user",
      "sort": 1,
      "created_at": "2026-01-01 00:00:00",
      "children": [
        {
          "id": "p5p6p7p8",
          "parent_id": "p1p2p3p4",
          "name": "用户列表",
          "slug": "/admin/user/index",
          "type": 2,
          "icon": "",
          "path": "/user/index",
          "sort": 1
        }
      ]
    }
  ]
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | Chiffré par hashid |
| parent_id | string | hashid de la permission parente, « 0 » indique un nœud racine |
| name | string | Nom de la permission |
| slug | string | Identifiant de la permission (identifiant de route / de bouton) |
| type | int | 1=menu, 2=bouton, 3=interface |
| icon | string | Icône du menu (nom d'icône Material) |
| path | string | Chemin de route frontend |
| sort | int | Valeur de tri (croissant) |
| children | array? | Liste des sous-permissions (récursive), ce champ est absent s'il n'y a pas de nœud enfant |

### 7.2 Créer une permission

```
POST /admin/permission
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "parent_id": 0,
  "name": "系统设置",
  "slug": "/admin/config",
  "type": 1,
  "icon": "settings",
  "path": "/config",
  "sort": 99
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| parent_id | int | Non | | ID de la permission parente (type INT d'origine), par défaut 0 |
| name | string | Oui | max:50 | Nom de la permission |
| slug | string | Oui | max:100 | Identifiant de la permission |
| type | int | Oui | in:1,2,3 | 1=menu, 2=bouton, 3=interface |
| icon | string | Non | | Icône du menu, vide par défaut |
| path | string | Non | | Chemin de route frontend, vide par défaut |
| sort | int | Non | | Valeur de tri, par défaut 0 |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "p9p0a1b2",
    "parent_id": "0",
    "name": "系统设置",
    "slug": "/admin/config",
    "type": 1,
    "icon": "settings",
    "path": "/config",
    "sort": 99
  }
}
```

### 7.3 Mettre à jour une permission

```
PUT /admin/permission/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "name": "系统配置",
  "icon": "tune",
  "path": "/system/config",
  "sort": 50
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| name | string | Non | Nom de la permission |
| icon | string | Non | Icône |
| path | string | Non | Chemin de route |
| sort | int | Non | Valeur de tri |

### 7.4 Supprimer une permission

```
DELETE /admin/permission/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : double confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "删除成功",
  "data": []
}
```

La suppression cascade vers toutes les sous-permissions (enregistrements dont `parent_id` = ID de la permission courante) et dissocie toutes les associations de rôles.

## 8. Configuration système

La configuration système est unique par combinaison `group` + `key`.

### 8.1 Liste des configurations

```
GET /admin/config
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| group | string | Non | | Filtre par groupe de configuration |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "c1c2c3c4",
        "group": "system",
        "key": "site_name",
        "value": "开放管理后台",
        "type": "string",
        "description": "站点名称",
        "created_at": "2026-01-01 00:00:00"
      }
    ],
    "total": 20,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| group | string | Groupe de configuration (par exemple `system`, `email`, `storage`) |
| key | string | Clé de configuration |
| value | string | Valeur de configuration |
| type | string | Indication du type de valeur (`string`, `integer`, `boolean`, `json`, etc.) |
| description | string | Description de la configuration |

### 8.2 Créer une configuration

```
POST /admin/config
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "group": "email",
  "key": "smtp_host",
  "value": "smtp.example.com",
  "type": "string",
  "description": "SMTP 服务器地址"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| group | string | Oui | max:100 | Groupe de configuration |
| key | string | Oui | max:100 | Clé de configuration (unique dans le groupe) |
| value | string | Oui | | Valeur de configuration |
| type | string | Non | | Type de valeur, `string` par défaut |
| description | string | Non | | Description de la configuration, vide par défaut |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "创建成功",
  "data": {
    "id": "c5c6c7c8",
    "group": "email",
    "key": "smtp_host",
    "value": "smtp.example.com",
    "type": "string",
    "description": "SMTP 服务器地址"
  }
}
```

**Erreurs possibles** :
- 422 : élément de configuration déjà existant (même group + key)

### 8.3 Mettre à jour une configuration

```
PUT /admin/config/{id}
```

- **Authentification** : JWT + RBAC

**Corps de requête** :
```json
{
  "value": "smtp.newhost.com",
  "type": "string",
  "description": "更新后的 SMTP 地址"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| value | string | Non | Met à jour la valeur de configuration |
| type | string | Non | Met à jour le type de valeur |
| description | string | Non | Met à jour le texte de description |

### 8.4 Supprimer une configuration

```
DELETE /admin/config/{id}
```

- **Authentification** : JWT + RBAC
- **Opération sensible** : double confirmation du mot de passe requise

**Corps de requête** :
```json
{
  "password": "admin_password"
}
```

Supprime physiquement l'enregistrement de configuration.

## 9. Journaux d'opérations

Les journaux d'opérations sont des interfaces en lecture seule, écrits automatiquement par le middleware `OperationLog` à chaque requête POST/PUT/DELETE ; les champs stockés incluent `user_id`, `action`, `method`, `path`, `ip`, `source`, `input`.

### 9.1 Liste des journaux d'opérations

```
GET /admin/log
```

- **Authentification** : JWT + RBAC

**Paramètres de requête** :

| Paramètre | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| page | int | Non | 1 | Numéro de page |
| limit | int | Non | 15 | Nombre d'éléments par page |
| user_id | int | Non | | Filtre précis par ID utilisateur (type INT d'origine) |
| action | string | Non | | Filtre précis par action |
| path | string | Non | | Filtre flou par chemin de requête |
| start_date | string | Non | | Date de début (format Y-m-d) |
| end_date | string | Non | | Date de fin (format Y-m-d) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "success",
  "data": {
    "list": [
      {
        "id": "l1l2l3l4",
        "user_name": "admin",
        "action": "用户登录",
        "method": "POST",
        "path": "/api/auth/login",
        "ip": "192.168.1.1",
        "source": "web",
        "input": "{\"username\":\"admin\"}",
        "created_at": "2026-05-21 10:30:00"
      }
    ],
    "total": 500,
    "page": 1,
    "limit": 15
  }
}
```

| Champ | Type | Description |
|------|------|------|
| id | string | hashid |
| user_name | string | Nom d'utilisateur de l'opération (obtenu via l'association user ; les opérations non connectées affichent « système ») |
| action | string | Description de l'action |
| method | string | Méthode HTTP (POST/PUT/DELETE) |
| path | string | Chemin de requête |
| ip | string | IP du client |
| source | string | Source de la requête |
| input | string | Chaîne JSON des paramètres de requête (sans les fichiers) |
| created_at | string | Heure de l'opération (datetime) |

## 10. Espace personnel

Les interfaces de l'espace personnel nécessitent uniquement l'authentification JWT (pas de validation des permissions RBAC — le middleware `AdminPermission` doit les ajouter à sa liste blanche).

### 10.1 Mettre à jour les informations personnelles

```
PUT /admin/profile
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "real_name": "新姓名",
  "phone": "13800138000",
  "email": "me@example.com"
}
```

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| real_name | string | Non | Nom réel |
| phone | string | Non | Numéro de téléphone (stockage chiffré Encryptable) |
| email | string | Non | E-mail (stockage chiffré Encryptable) |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "更新成功",
  "data": {
    "id": "a1b2c3d4",
    "username": "admin",
    "real_name": "新姓名",
    "phone": "13800138000",
    "email": "me@example.com",
    "status": 1,
    "last_login_at": "2026-05-21 10:30:00"
  }
}
```

Dans la réponse, `phone` et `email` sont renvoyés en clair ; `password` et `id_card` sont retirés.

### 10.2 Modifier le mot de passe

```
PUT /admin/profile/password
```

- **Authentification** : JWT

**Corps de requête** :
```json
{
  "old_password": "current_pass",
  "new_password": "new_pass_123"
}
```

| Champ | Type | Obligatoire | Règle de validation | Description |
|------|------|------|---------|------|
| old_password | string | Oui | | Mot de passe actuel |
| new_password | string | Oui | min:6, max:32 | Nouveau mot de passe |

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "密码修改成功",
  "data": []
}
```

**Erreurs possibles** :
- 422 : veuillez saisir l'ancien et le nouveau mot de passe
- 422 : ancien mot de passe incorrect
- 422 : le nouveau mot de passe doit comporter 6 à 32 caractères

### 10.3 Déconnexion

```
POST /admin/profile/logout
```

- **Authentification** : JWT

**Corps de requête** : aucun (pas de requestBody, le jeton est lu depuis l'en-tête Authorization)

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "已登出",
  "data": []
}
```

Logique de déconnexion : décode le JWT pour obtenir la durée de validité restante (exp - now), écrit le hash md5 du jeton dans la liste noire Redis `jwt_blacklist:{md5}` avec TTL = durée de validité restante. Les jetons en liste noire sont interceptés par le middleware `AdminAuth` et renvoient 401.

Sans jeton, renvoie 401. Si le jeton est expiré / invalide (exception de décodage), la déconnexion est quand même considérée comme réussie.

## 11. Import et export

### 11.1 Export Excel

```
POST /admin/export/excel
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`)

**Corps de requête** :
```json
{
  "table": "admin_user",
  "columns": ["username", "real_name", "phone", "status", "created_at"],
  "conditions": {
    "status": 1
  },
  "title": "用户列表导出"
}
```

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| table | string | Non | `admin_user` | Nom de la table à exporter. Prise en charge : `admin_user`, `operation_log`, `admin_role`, `system_config` |
| columns | array{string} | Non | | Tableau des noms de colonnes à exporter ; vide = toutes les colonnes de la table |
| conditions | object | Non | `{}` | Conditions de filtre, paires clé-valeur utilisées dans le WHERE quand la valeur n'est pas vide |
| title | string | Non | `数据导出` | Titre Excel (affiché comme nom de la feuille) |

**Tables et colonnes prises en charge** :

| table | Colonnes disponibles |
|-------|-------|
| `admin_user` | id, username, real_name, phone, email, status, last_login_at, last_login_ip, created_at |
| `operation_log` | id, user_id, action, method, path, ip, created_at |
| `admin_role` | id, name, slug, description, status, created_at |
| `system_config` | id, group, key, value, type, description, created_at |

Les champs sensibles `phone`, `email`, `id_card` sont automatiquement masqués à l'export. Limite de 10000 lignes. La première ligne Excel est figée et le filtre automatique est activé.

### 11.2 Export PDF

```
POST /admin/export/pdf
```

- **Authentification** : JWT + RBAC
- **Type de réponse** : téléchargement de fichier (`application/pdf`, A4 paysage)

**Corps de requête** :
```json
{
  "type": "dashboard",
  "title": "管理仪表盘报告",
  "data": {
    "stats": [
      { "label": "用户总数", "value": "1280" }
    ]
  }
}
```

Ou en mode tableau :
```json
{
  "type": "table",
  "title": "用户列表",
  "data": {
    "columns": ["用户名", "真实姓名", "状态"],
    "rows": [
      ["admin", "管理员", "启用"],
      ["editor", "编辑员", "启用"]
    ]
  }
}
```

| Champ | Type | Obligatoire | Valeur par défaut | Description |
|------|------|------|------|------|
| type | string | Non | `table` | Type d'export : `table` / `dashboard` |
| title | string | Non | `数据导出` | Titre du PDF |
| data | object | Non | `{}` | Données à exporter |

Avec `type=dashboard`, `data` doit contenir le tableau `stats` (rendu sous forme de cartes) ; avec `type=table`, `data` doit contenir les tableaux `columns` et `rows`.

Le modèle PDF contient les informations de copyright et l'horodatage de l'export.

### 11.3 Import d'utilisateurs (Excel)

```
POST /admin/import/users
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data` (téléversement de fichier)

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Format `.xlsx` ou `.xls` |

**Exigences de colonnes Excel** :

| Nom de colonne | Obligatoire | Description |
|------|------|------|
| username | Oui | Nom d'utilisateur (unique) |
| password | Oui | Mot de passe (stocké avec hash bcrypt) |
| real_name | Oui | Nom réel |
| phone | Non | Numéro de téléphone |
| email | Non | E-mail |
| status | Non | État, par défaut 1 |

La ligne 1 contient les titres de colonnes (insensibles à la casse), les données commencent à la ligne 2.

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "导入完成",
  "data": {
    "total": 10,
    "success": 8,
    "failed": 2,
    "errors": [
      { "row": 3, "reason": "用户名为空" },
      { "row": 7, "reason": "用户名 zhangsan 已存在" }
    ]
  }
}
```

| Champ | Type | Description |
|------|------|------|
| total | int | Nombre total de lignes (hors ligne de titre) |
| success | int | Nombre d'imports réussis |
| failed | int | Nombre d'échecs |
| errors | array | Détail des échecs, chaque élément contient row (numéro de ligne Excel) et reason (raison de l'échec) |

## 12. Téléversement de fichiers

```
POST /admin/upload
```

- **Authentification** : JWT + RBAC
- **Type de requête** : `multipart/form-data`

**Champs du formulaire** :

| Champ | Type | Obligatoire | Description |
|------|------|------|------|
| file | file | Oui | Fichier à téléverser |

**Types de fichiers autorisés** : `jpg`, `jpeg`, `png`, `gif`, `pdf`, `xlsx`, `docx`
**Taille de fichier maximale** : 10 Mo

**Exemple de réponse** :
```json
{
  "code": 0,
  "message": "上传成功",
  "data": {
    "url": "/upload/2026-05-21/abc123def456.png"
  }
}
```

Les fichiers sont stockés dans des répertoires par date `public/upload/{Y-m-d}/`, le nom de fichier étant `md5(uniqid) + extension d'origine`. `url` est un chemin relatif par rapport à la racine du site.

**Erreurs possibles** :
- 422 : veuillez sélectionner un fichier (aucun téléversement)
- 422 : type de fichier non pris en charge
- 422 : la taille du fichier ne peut pas dépasser 10 Mo
- 500 : échec du téléversement (fichier invalide)

## 13. En-têtes de réponse

Toutes les interfaces (injectés au niveau du middleware global) incluent les en-têtes de réponse suivants :

| En-tête | Description |
|----|------|
| `X-RateLimit-Limit` | Limite de débit (nombre de requêtes) |
| `X-RateLimit-Remaining` | Nombre de requêtes restantes |
| `X-RateLimit-Reset` | Horodatage de réinitialisation de la fenêtre de débit |
| `Retry-After` | Renvoyé uniquement en cas de limitation de débit, secondes d'attente recommandées |
| `X-Content-Type-Options` | `nosniff` (par défaut webman, interdit le MIME sniffing) |
| `X-Frame-Options` | `DENY` (fourni par le middleware CORS / la configuration de base de webman) |

Détails de la limitation de débit :
- Limite globale par défaut : 60 requêtes/minute / IP+chemin
- Point de terminaison de connexion `/api/auth/login` : 10 requêtes/minute
- Point de terminaison d'inscription `/api/auth/register` : 5 requêtes/minute
- Algorithme de fenêtre glissante atomique Redis (Lua ZSET), évite les courses TOCTOU
- Si Redis est indisponible, fail open (laisser passer), ne bloque pas les requêtes

## 14. Flux d'authentification

Séquence d'authentification complète :

```
1. 客户端请求 POST /api/captcha/generate
   (请求头: API-Version: v1)
    ↓
   服务端返回: key + base64 图片 + 点击目标提示
   
2. 用户点击图片目标位置，前/客户端收集点击坐标
   
3. 客户端请求 POST /api/auth/login
   (请求头: API-Version: v1, Content-Type: application/json)
   请求体: { username, password, captcha_key, clicks: [{x,y}, ...] }
    ↓
   服务端:
   a. 参数校验 → 422
   b. 校验验证码 → 422
   c. 校验用户凭证 → 401
   d. 检查账号状态 → 403
   e. 签发 JWT (access + refresh) → 200
   f. 更新 last_login_at / last_login_ip
    ↓
   客户端保存: access_token, refresh_token, expires_in

4. 后续请求携带 JWT
   请求头: Authorization: Bearer <access_token>
    ↓
   AdminAuth 中间件:
   a. 提取 Bearer token
   b. 检查黑名单 (Redis jwt_blacklist:{md5}) → 401
   c. 解码 JWT，校验过期 → 401
   d. 设置 $request->adminId = sub 字段
    ↓
   AdminPermission 中间件:
   a. 对资源路由解析权限标识
   b. 查询用户角色 → 角色权限，进行匹配
   c. 无权限 → 403
    ↓
   Controller 处理请求
    ↓
   Response + X-RateLimit-* 头

5. Access Token 过期前刷新
   客户端请求 POST /api/auth/refresh
   请求体: { refresh_token: "..." }
    ↓
   服务端解码 refresh_token → 签发新 access + refresh
    ↓
   客户端更新本地令牌

6. 登出
   客户端请求 POST /admin/profile/logout
   请求头: Authorization: Bearer <access_token>
    ↓
   服务端:
   a. 解码 JWT 获取剩余 TTL
   b. 写入 Redis 黑名单: jwt_blacklist:{md5(token)} = 1, TTL = 剩余有效期
   c. 返回成功
```

### Structure JWT

- **access_token** : `{ sub: <user_id>, username: "<name>" }`, TTL par défaut de 7200 secondes (contrôlé par `default_expire` de la configuration JWT)
- **refresh_token** : `{ sub: <user_id>, token_type: "refresh" }`, TTL par défaut de 1209600 secondes (contrôlé par `refresh_expire` de la configuration JWT, soit 14 jours)

### Gestion de la sécurité

- Les mots de passe sont stockés hachés avec `PASSWORD_BCRYPT`
- Les champs sensibles (phone, email, id_card) utilisent `erikwang2013/encryptable` pour un chiffrement / déchiffrement transparent au niveau de la base de données
- Les ID de la couche API utilisent `erikwang2013/hashids` pour un transport chiffré, évitant d'exposer la séquence des ID snowflake d'origine
- SecurityFilter analyse globalement XSS, l'injection SQL, la traversée de chemin et l'injection de commande ; la même IP 5 fois / 60 secondes → liste noire temporaire de 15 minutes
- Les opérations sensibles (suppression d'utilisateur, de rôle, de permission, de configuration) exigent la double confirmation du mot de passe de l'utilisateur connecté
- Limite de sessions simultanées : 3 jetons valides maximum par utilisateur ; le 4e appareil connecté force le jeton le plus ancien en liste noire
- Verrouillage de compte : 5 échecs de connexion consécutifs déclenchent un verrouillage de 15 minutes, renvoie 429 pendant le verrouillage

## 15. Déploiement et exploitation

### Docker Compose

`docker-compose.yml` à la racine du projet orchestre 5 services (Nginx, application webman, MySQL, Redis, Elasticsearch). PHP est construit via le `Dockerfile` (basé sur `php:8.3-cli`, OPcache activé).

```bash
cp .env.docker .env
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` définit le pipeline d'intégration continue GitHub Actions :
- Vérification de syntaxe `php -l`
- Tests unitaires PHPUnit
- Analyse statique `flutter analyze`

### Sauvegarde de la base de données

Le répertoire `database/backup/` fournit des scripts de sauvegarde et de restauration :
- `backup.sh` — sauvegarde compressée mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes existantes pour sélection

### Configuration de sécurité Nginx

Pour un déploiement en production, reportez-vous à `docs/nginx-security.conf` pour le durcissement du reverse proxy.

## 16. Points de terminaison API métier (ERP)

Tous les points de terminaison métier sont sous le groupe `/admin` et passent par trois middlewares : `AdminAuth` (authentification JWT), `AdminPermission` (validation des permissions RBAC), `OperationLog` (enregistrement des opérations).

> Nombre total de points de terminaison : Produits (17) | Achats (8) | Ventes (6) | Stocks (6) | Finance (17) | CRM (13) | Workflow (6) | Notifications (4) | Projets (3) | RH (9) | Production (7) | Rapports (4) | Tableau de bord (3) | Client (2) | Total 105 points de terminaison

Les points de terminaison à interconnexion inter-modules sont marqués 🔗.

### 16.1 Gestion des produits (Product Management)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/product | Liste des produits (pagination + recherche + filtre catégorie / état) |
| POST | /admin/product | Créer un produit (avec SKU et prix) |
| GET | /admin/product/{id} | Détail d'un produit (avec catégorie / marque / SKU / prix / unité) |
| PUT | /admin/product/{id} | Mettre à jour un produit |
| DELETE | /admin/product/{id} | Supprimer un produit (suppression logique, confirmation du mot de passe requise) |
| GET | /admin/category | Liste des catégories (arborescente) |
| POST | /admin/category | Créer une catégorie |
| PUT | /admin/category/{id} | Mettre à jour une catégorie |
| DELETE | /admin/category/{id} | Supprimer une catégorie |
| GET | /admin/brand | Liste des marques |
| POST | /admin/brand | Créer une marque |
| GET | /admin/warehouse | Liste des entrepôts |
| POST | /admin/warehouse | Créer un entrepôt |
| GET | /admin/location | Liste des emplacements |
| GET | /admin/warehouse/{id}/locations | Liste des emplacements d'un entrepôt |
| GET | /admin/supplier | Liste des fournisseurs (recherche ES) |
| POST | /admin/supplier | Créer un fournisseur |
| GET | /admin/customer | Liste des clients (recherche ES) |
| POST | /admin/customer | Créer un client |

### 16.2 Gestion des achats (Purchase)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/purchase/apply | Liste des demandes d'achat |
| POST | /admin/purchase/apply | Créer une demande d'achat |
| GET | /admin/purchase/order | Liste des commandes d'achat |
| POST | /admin/purchase/order | Créer une commande d'achat |
| 🔗 POST | /admin/purchase/receive | Créer un bon de réception (entrée en stock automatique + génération des comptes fournisseurs) |
| GET | /admin/purchase/receive | Liste des bons de réception |
| GET | /admin/purchase/receive/{id} | Détail d'un bon de réception |
| POST | /admin/purchase/return | Créer un bon de retour |
| GET | /admin/purchase/settlement | Liste des règlements fournisseurs |

### 16.3 Gestion des ventes (Sales)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/sales/quotation | Liste des devis |
| POST | /admin/sales/quotation | Créer un devis |
| GET | /admin/sales/order | Liste des commandes de vente |
| POST | /admin/sales/order | Créer une commande de vente |
| 🔗 POST | /admin/sales/delivery | Créer un bon d'expédition (sortie de stock automatique + génération des comptes clients) |
| GET | /admin/sales/delivery | Liste des bons d'expédition |
| GET | /admin/sales/settlement | Liste des règlements clients |

### 16.4 Gestion des stocks (Inventory)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/inventory | Stock en temps réel (dimensions entrepôt / emplacement / lot / SKU) |
| GET | /admin/inventory/flow | Flux d'entrées / sorties de stock |
| GET | /admin/inventory/transfer | Liste des bons de transfert |
| POST | /admin/inventory/transfer | Créer un bon de transfert |
| GET | /admin/inventory/check | Liste des tâches d'inventaire |
| POST | /admin/inventory/check | Créer une tâche d'inventaire |
| GET | /admin/inventory/alert | Règles d'alerte de stock |

### 16.5 Gestion financière (Finance)

| Méthode | Chemin | Description |
|------|------|------|
| POST | /admin/finance/voucher | Créer une écriture comptable |
| GET | /admin/finance/ar-ap | Liste des comptes à recevoir / à payer |
| POST | /admin/finance/receipt | Créer un reçu d'encaissement |
| POST | /admin/finance/payment | Créer un ordre de paiement |
| GET | /admin/finance/cash-journal | Journal de caisse et de banque |
| GET | /admin/finance/expense | Liste des notes de frais |
| POST | /admin/finance/expense | Soumettre une demande de note de frais |
| GET | /admin/finance/report/profit | Compte de résultat |
| GET | /admin/finance/general-ledger | Grand livre (résumé par compte + période) |
| GET | /admin/finance/subsidiary-ledger | Livre auxiliaire (détail par écriture du compte) |
| GET | /admin/finance/report/balance-sheet | Bilan (avec génération automatique) |
| GET | /admin/finance/report/cash-flow | Tableau des flux de trésorerie (exploitation / investissement / financement) |
| GET | /admin/finance/bank-account | Liste des comptes bancaires |
| GET/POST/PUT/DELETE | /admin/finance/asset | Immobilisations CRUD + calcul des amortissements |
| GET/POST | /admin/finance/tax-rate | Configuration des taux de taxe |
| GET | /admin/finance/tax-record | Enregistrements fiscaux |
| GET/POST/PUT/DELETE | /admin/finance/currency | Gestion des devises |
| GET/POST/PUT/DELETE | /admin/finance/exchange-rate | Gestion des taux de change |
| GET/POST/PUT/DELETE | /admin/finance/budget | Gestion budgétaire (avec comparaison budget vs réel) |
| GET/POST/PUT/DELETE | /admin/finance/cost-center | Centres de coût (structure arborescente) |
| GET/POST/PUT/DELETE | /admin/finance/profit-center | Centres de profit (structure arborescente) |

### 16.6 CRM

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/crm/opportunity | Liste des opportunités |
| POST | /admin/crm/opportunity | Créer une opportunité |
| GET | /admin/crm/follow | Liste des enregistrements de suivi |
| POST | /admin/crm/follow | Créer un enregistrement de suivi |
| GET | /admin/crm/funnel | Configuration des étapes de l'entonnoir |
| GET | /admin/crm/contact | Liste des contacts |
| POST | /admin/crm/contact | Créer un contact |
| GET | /admin/crm/pool | Liste des clients du pool commun |
| POST | /admin/crm/pool/claim/{id} | Revendiquer un client du pool commun |
| POST | /admin/crm/pool/release/{id} | Libérer un client vers le pool commun |
| GET/POST | /admin/crm/pool/rules | CRUD des règles du pool commun |
| GET | /admin/crm/contract | Liste des contrats |
| POST | /admin/crm/contract | Créer un contrat |
| GET | /admin/crm/contract/{id} | Détail d'un contrat |
| PUT | /admin/crm/contract/{id} | Mettre à jour un contrat |
| DELETE | /admin/crm/contract/{id} | Supprimer un contrat |
| GET | /admin/crm/quotation | Liste des devis CRM |
| POST | /admin/crm/quotation | Créer un devis CRM |
| POST | /admin/crm/quotation/{id}/to-contract | 🔗 Conversion devis en contrat |
| GET/POST/PUT/DELETE | /admin/crm/campaign | Campagnes marketing |
| GET/POST/PUT/DELETE | /admin/crm/ticket | Tickets de service |
| POST | /admin/crm/ticket/{id}/assign | Affecter un ticket |
| POST | /admin/crm/ticket/{id}/resolve | Résoudre un ticket |
| GET/POST | /admin/crm/analytics/report | Rapports d'analyse client |
| GET/POST | /admin/crm/analytics/metric | Métriques d'analyse |

### 16.7 Workflow d'approbation (Workflow)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/workflow | Liste des définitions de workflow |
| POST | /admin/workflow | Créer une définition de workflow |
| GET | /admin/workflow/{id} | Détail d'un workflow |
| PUT | /admin/workflow/{id} | Mettre à jour un workflow |
| DELETE | /admin/workflow/{id} | Supprimer un workflow |
| POST | /admin/workflow/{id}/submit | 🔗 Soumettre une approbation (créer une instance d'approbation) |
| POST | /admin/approval/{id}/approve | Approuver |
| POST | /admin/approval/{id}/reject | Rejeter |
| POST | /admin/approval/{id}/withdraw | Retirer |
| ANY | /admin/approval/my | Liste de mes approbations (en attente / approuvées) |

### 16.8 Notifications (Notification)

| Méthode | Chemin | Description |
|------|------|------|
| ANY | /admin/notification/my | Liste de mes notifications (pagination, tri chronologique décroissant) |
| POST | /admin/notification/{id}/read | Marquer une notification comme lue |
| POST | /admin/notification/read-all | Tout marquer comme lu |
| ANY | /admin/notification/unread-count | Nombre de notifications non lues |

### 16.9 Gestion de projets (Project)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/project | Liste des projets |
| POST | /admin/project | Créer un projet |
| GET | /admin/project/{id} | Détail d'un projet |
| PUT | /admin/project/{id} | Mettre à jour un projet |
| DELETE | /admin/project/{id} | Supprimer un projet |
| GET | /admin/project/task | Liste des tâches |
| POST | /admin/project/task | Créer une tâche |
| PUT | /admin/project/task/{id} | Mettre à jour une tâche |
| DELETE | /admin/project/task/{id} | Supprimer une tâche |
| GET | /admin/project/timesheet | Liste des relevés de temps |
| POST | /admin/project/timesheet | Saisir un relevé de temps |
| PUT | /admin/project/timesheet/{id} | Mettre à jour un relevé de temps |
| DELETE | /admin/project/timesheet/{id} | Supprimer un relevé de temps |

### 16.10 Gestion des ressources humaines (HR)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/hr/department | Liste des départements (arborescente) |
| POST | /admin/hr/department | Créer un département |
| PUT | /admin/hr/department/{id} | Mettre à jour un département |
| DELETE | /admin/hr/department/{id} | Supprimer un département |
| GET | /admin/hr/employee | Liste des employés |
| POST | /admin/hr/employee | Créer un employé |
| PUT | /admin/hr/employee/{id} | Mettre à jour un employé |
| DELETE | /admin/hr/employee/{id} | Supprimer un employé |
| GET | /admin/hr/position | Liste des postes |
| POST | /admin/hr/position | Créer un poste |
| PUT | /admin/hr/position/{id} | Mettre à jour un poste |
| DELETE | /admin/hr/position/{id} | Supprimer un poste |
| ANY | /admin/hr/attendance | Consultation des enregistrements de pointage |
| POST | /admin/hr/attendance/clock-in | Pointer à l'arrivée |
| POST | /admin/hr/attendance/clock-out | Pointer au départ |
| ANY | /admin/hr/leave | Liste des congés |
| POST | /admin/hr/leave | Soumettre une demande de congé |
| GET | /admin/hr/leave/{id} | Détail d'un congé |
| PUT | /admin/hr/leave/{id} | Mettre à jour un congé |
| DELETE | /admin/hr/leave/{id} | Supprimer un congé |
| POST | /admin/hr/leave/{id}/approve | 🔗 Approuver un congé |
| GET | /admin/hr/salary | Liste des salaires |
| POST | /admin/hr/salary | Générer une fiche de salaire |
| PUT | /admin/hr/salary/{id} | Mettre à jour un salaire |
| DELETE | /admin/hr/salary/{id} | Supprimer un salaire |
| POST | /admin/hr/salary/{id}/pay | Verser le salaire |
| ANY | /admin/hr/salary-item | Liste des éléments de salaire |
| POST | /admin/hr/salary-item | Créer un élément de salaire |
| GET | /admin/hr/salary-item/{id} | Détail d'un élément de salaire |
| PUT | /admin/hr/salary-item/{id} | Mettre à jour un élément de salaire |
| DELETE | /admin/hr/salary-item/{id} | Supprimer un élément de salaire |

### 16.11 Production (Manufacturing)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/mfg/bom | Liste des BOM |
| POST | /admin/mfg/bom | Créer un BOM |
| PUT | /admin/mfg/bom/{id} | Mettre à jour un BOM |
| DELETE | /admin/mfg/bom/{id} | Supprimer un BOM |
| GET | /admin/mfg/production | Liste des ordres de fabrication |
| POST | /admin/mfg/production | Créer un ordre de fabrication |
| PUT | /admin/mfg/production/{id} | Mettre à jour un ordre de fabrication |
| DELETE | /admin/mfg/production/{id} | Supprimer un ordre de fabrication |
| POST | /admin/mfg/production/{id}/start | Lancer la production |
| POST | /admin/mfg/production/{id}/complete | Terminer la production |
| GET | /admin/mfg/routing | Liste des gammes |
| POST | /admin/mfg/routing | Créer une gamme |
| PUT | /admin/mfg/routing/{id} | Mettre à jour une gamme |
| DELETE | /admin/mfg/routing/{id} | Supprimer une gamme |
| GET | /admin/mfg/workstation | Liste des postes de travail |
| POST | /admin/mfg/workstation | Créer un poste de travail |
| PUT | /admin/mfg/workstation/{id} | Mettre à jour un poste de travail |
| DELETE | /admin/mfg/workstation/{id} | Supprimer un poste de travail |
| GET | /admin/mfg/mrp | Liste des plans MRP |
| POST | /admin/mfg/mrp | Créer un plan MRP |
| PUT | /admin/mfg/mrp/{id} | Mettre à jour un plan MRP |
| DELETE | /admin/mfg/mrp/{id} | Supprimer un plan MRP |
| POST | /admin/mfg/mrp/{id}/generate | 🔗 Exécuter le MRP pour générer des suggestions d'achat / de production |

### 16.12 Rapports personnalisés (Report Builder)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/report | Liste des modèles de rapport |
| POST | /admin/report | Créer un modèle de rapport |
| GET | /admin/report/{id} | Détail d'un modèle de rapport |
| PUT | /admin/report/{id} | Mettre à jour un modèle de rapport |
| DELETE | /admin/report/{id} | Supprimer un modèle de rapport |
| POST | /admin/report/{id}/execute | Exécuter le rapport pour générer les données |
| ANY | /admin/report/{id}/result | Résultat de l'exécution du rapport |
| GET | /admin/report/schedule | Liste des planifications |
| POST | /admin/report/schedule | Créer une planification |
| PUT | /admin/report/schedule/{id} | Mettre à jour une planification |
| DELETE | /admin/report/schedule/{id} | Supprimer une planification |

### 16.13 Tableau de bord (Dashboard)

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/dashboard/sales | Tableau de bord des ventes |
| GET | /admin/dashboard/inventory | Tableau de bord des stocks |
| GET | /admin/dashboard/finance | Tableau de bord financier |

### 16.14 API client (Client API)

Les interfaces client sont montées sous le groupe `/api` et nécessitent l'en-tête `API-Version`. Les informations produit ne contiennent pas le prix d'achat.

| Méthode | Chemin | Description |
|------|------|------|
| GET | /api/product | Liste des produits (sans prix d'achat) |
| GET | /api/product/{hashid} | Détail d'un produit (avec prix de détail / de gros, sans prix d'achat) |

### 16.15 Gestion des commandes OMS

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/oms/order | Liste des commandes OMS |
| POST | /admin/oms/order | Créer une commande OMS |
| 🔗 POST | /admin/oms/order/{id}/allocate | Allocation de stock (pré-réservation) |
| 🔗 POST | /admin/oms/order/{id}/fulfill | Créer l'exécution |
| POST | /admin/oms/order/{id}/cancel | Annuler la commande (libération de la pré-réservation) |
| POST | /admin/oms/rma/{id}/approve | Approuver un RMA |
| POST | /admin/oms/rma/{id}/refund | Rembourser un RMA |

### 16.16 Gestion d'entrepôt WMS

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/wms/zone | Liste des zones (CRUD) |
| GET | /admin/wms/location | Liste des emplacements WMS (CRUD) |
| GET | /admin/wms/asn | Liste des ASN (CRUD) |
| POST | /admin/wms/receiving/{id}/complete | Terminer la réception → génération automatique des tâches de mise en rayon |
| POST | /admin/wms/putaway/{id}/complete | Confirmer la mise en rayon → déclenche stockIn |
| POST | /admin/wms/wave/{id}/release | Libérer la vague → génération des tâches de préparation |
| POST | /admin/wms/pick/{id}/start | Commencer la préparation |
| POST | /admin/wms/pick/{id}/confirm | Confirmer la préparation |
| POST | /admin/wms/pack/{id}/complete | Emballage terminé |

### 16.17 Gestion du transport TMS

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/tms/carrier | Liste des transporteurs (CRUD) |
| GET | /admin/tms/service | Services des transporteurs (CRUD) |
| GET | /admin/tms/freight-rate | Tarifs de fret (CRUD) |
| GET | /admin/tms/shipment | Liste des connaissements (CRUD) |
| 🔗 POST | /admin/tms/shipment/{id}/ship | Confirmer l'expédition (stockOut + comptes clients) |
| POST | /admin/tms/tracking/callback | Webhook de suivi du transporteur |
| POST | /admin/tms/freight-invoice/{id}/pay | Payer la facture de fret (génération des comptes fournisseurs) |

### 16.18 Extensions du tableau de bord

| Méthode | Chemin | Description |
|------|------|------|
| GET | /admin/dashboard/oms | KPI OMS (à traiter / préparation en cours / expédiés aujourd'hui / RMA) |
| GET | /admin/dashboard/wms | KPI WMS (à recevoir / à mettre en rayon / à préparer / à emballer) |
| GET | /admin/dashboard/tms | KPI TMS (à expédier / en transit / signés / anomalies) |

### 16.19 Explications des interconnexions inter-modules

Les points de terminaison suivants déclenchent des interconnexions automatiques inter-modules, marqués 🔗 :

| Point de terminaison | Actions d'interconnexion |
|------|---------|
| 🔗 POST /admin/purchase/receive | Appelle automatiquement InventoryService.stockIn() pour mettre à jour le stock et recalculer le coût moyen pondéré mobile ; appelle FinanceService.createAp() pour générer l'enregistrement des comptes fournisseurs |
| 🔗 POST /admin/sales/delivery | Appelle automatiquement InventoryService.stockOut() pour déduire le stock (au coût moyen pondéré mobile) ; appelle FinanceService.createAr() pour générer l'enregistrement des comptes clients |
