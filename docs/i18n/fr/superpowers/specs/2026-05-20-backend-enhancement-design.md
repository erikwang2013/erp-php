# Sous-projet A : amélioration du backend — spécification de conception

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Périmètre

Il s'agit de l'amélioration du backend, avec 15 points fonctionnels au total, impliquant 9 nouveaux fichiers + 4 fichiers modifiés.

---

## Liste des fichiers ajoutés/modifiés

```
app/middleware/
├── OperationLog.php          # Nouveau : enregistrement automatique des journaux d'opérations
├── Cors.php                  # Nouveau : CORS
└── RateLimit.php             # Nouveau : limitation de débit Redis
app/admin/controller/
├── ConfigController.php      # Nouveau : CRUD de configuration système
├── LogController.php         # Nouveau : consultation des journaux d'opérations
├── ProfileController.php     # Nouveau : espace personnel (avec déconnexion)
├── UploadController.php      # Nouveau : upload de fichiers
├── ImportController.php      # Nouveau : import Excel des utilisateurs
└── HealthController.php      # Nouveau : health check
app/model/
├── AdminUser.php             # Modifié : ajout des traits SoftDeletes + Searchable
└── OperationLog.php          # Modifié : ajout de public $timestamps = false
app/middleware/
└── AdminAuth.php             # Modifié : validation de la liste noire JWT
app/admin/controller/
├── DashboardController.php   # Modifié : statistiques en temps réel depuis la base de données
└── UserController.php        # Modifié : ajout d'actions par lot
config/
└── route.php                 # Modifié : nouvelles routes + middlewares
```

---

## 1. Middlewares

### 1.1 Middleware CORS

**Fichier** : `app/middleware/Cors.php`

- Les requêtes de pré-vérification OPTIONS renvoient directement 204
- Les requêtes non pré-vérifiées reçoivent l'en-tête de réponse `Access-Control-Allow-Origin: *`
- En-têtes autorisés : `Authorization, Content-Type, API-Version`
- Cache maximal : 86400 secondes

Montage : middleware global (`config/middleware.php`)

### 1.2 Middleware de limitation de débit

**Fichier** : `app/middleware/RateLimit.php`

- Stockage : fenêtre glissante Redis Sorted Set
- Défaut : 60 requêtes/minute/IP/route
- Interfaces sensibles :
  - `/api/auth/login` : 10 requêtes/minute
  - `/api/auth/register` : 5 requêtes/minute
- En cas de dépassement : `429 Too Many Requests`

Montage : middleware global (`config/middleware.php`), après Cors, avant ApiVersion

### 1.3 Middleware de journaux d'opérations

**Fichier** : `app/middleware/OperationLog.php`

- N'enregistre que POST/PUT/DELETE
- Champs enregistrés : user_id, action, method, path, ip, input(JSON)
- Écriture asynchrone après la réponse (non bloquant)

Montage : groupe de routes `/admin`, après AdminPermission

### 1.4 Chaîne d'exécution des middlewares globaux

```
Toutes les requêtes :
  Cors → RateLimit → ApiVersion → {middlewares de route} → Controller

Requêtes /admin/* :
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Déconnexion (liste noire JWT)

**Fichier** : `app/middleware/AdminAuth.php` (modifié)

**Principe** : le JWT est par nature sans état ; à la déconnexion, le jeton est ajouté à la liste noire Redis, et AdminAuth vérifie d'abord la liste noire.

**Refonte d'AdminAuth** :
- Ajout au début de `process()` : vérifier dans l'ensemble Redis `jwt_blacklist` si le jeton courant est sur la liste noire
- Jeton sur la liste noire → renvoyer 401

**Route de déconnexion** (sous l'espace personnel) :

| Méthode | Route | Description |
|------|------|------|
| `POST` | `/admin/profile/logout` | Ajoute le jeton Bearer courant à la liste noire Redis, TTL = durée de validité restante du jeton |

**Logique de Logout** :
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Nouveaux contrôleurs et refontes existantes

### 2.1 CRUD de configuration système (`ConfigController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/config` | Liste paginée, filtrable par `group`, pagination `page`/`limit` |
| `store()` | POST `/admin/config` | Crée un élément de configuration, obligatoire : group, key, value |
| `update()` | PUT `/admin/config/{id}` | Met à jour value/type/description de l'élément |
| `destroy()` | DELETE `/admin/config/{id}` | Supprime l'élément, nécessite `confirmPassword()` |

### 2.2 Consultation des journaux d'opérations (`LogController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `index()` | GET `/admin/log` | Liste paginée, filtres : user_id, action, path, created_at (plage) |

Pas de création/modification/suppression, les journaux sont enregistrés automatiquement par le middleware.

### 2.3 Espace personnel (`ProfileController`)

Hérite de `BaseController`. Opère sur l'utilisateur connecté (`$request->adminId`).

| Méthode | Route | Description |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Met à jour real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Change le mot de passe, nécessite old_password, new_password, new_password_confirmation |

### 2.4 Upload de fichiers (`UploadController`)

Hérite de `BaseController`.

| Méthode | Route | Description |
|------|------|------|
| `upload()` | POST `/admin/upload` | Reçoit un fichier, prend en charge image/jpeg/png/gif/pdf/xlsx/docx |

- 10 Mo maximum
- Chemin de stockage : `public/upload/{date}/{hash}.{ext}`
- Retour : `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Données réelles du tableau de bord

**Fichier** : `app/admin/controller/DashboardController.php` (modifié)

Remplacer les fausses données codées en dur par des statistiques en temps réel depuis la base de données :

| Indicateur | Source | Description |
|------|------|------|
| Nombre total d'utilisateurs | `AdminUser::count()` | Sans la suppression douce |
| Nouveaux aujourd'hui | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Nombre total de rôles | `AdminRole::count()` | |
| Nombre total de permissions | `AdminPermission::count()` | |
| Données de tendance | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Nouveaux par jour sur les 7 derniers jours |
| Données de répartition | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Répartition par statut |
| Opérations récentes | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Les 10 dernières entrées de journaux d'opérations |

### 2.6 Opérations par lot sur les utilisateurs

**Fichier** : `app/admin/controller/UserController.php` (modifié, nouvelles méthodes)

| Méthode | Route | Description |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Suppression par lot, corps `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Activation/désactivation par lot, corps `{ ids: [hashid, ...], status: 1|0 }` |

- Chaque id est d'abord converti en BIGINT via `decodeId()`
- `batchDestroy()` doit passer la validation `confirmPassword()`

### 2.7 Import de données

**Fichier** : `app/admin/controller/ImportController.php` (nouveau)

| Méthode | Route | Description |
|------|------|------|
| `users()` | POST `/admin/import/users` | Upload d'un fichier Excel, création d'utilisateurs en masse |

Flux :
1. Recevoir le fichier `.xlsx`
2. Analyse PhpSpreadsheet, colonnes attendues : `username, password, real_name, phone, email, status`
3. Validation ligne par ligne + création (ID généré par snowflake, mot de passe bcrypt, phone/email chiffrés par encryption)
4. Retour du résultat : `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Health check

**Fichier** : `app/admin/controller/HealthController.php` (nouveau)

`GET /health` (sans authentification, non compté dans les journaux d'opérations) :

Renvoie l'état de connexion de chaque composant :
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- En cas d'échec de détection d'un composant, le champ correspondant contient la chaîne de description de l'erreur
- La route n'a pas de préfixe `/admin`, enregistrée séparément au niveau global

---

## 3. Corrections de modèles

### 3.1 Horodatages d'OperationLog

**Fichier** : `app/model/OperationLog.php` (modifié)

La table `erik_operation_log` n'a que la colonne `created_at` (pas de `updated_at`). Le `save()` par défaut d'Eloquent tente d'écrire `updated_at`, ce qui provoque une erreur SQL.

Correctif : `public $timestamps = false;` + spécification manuelle de `created_at` à l'écriture.

### 3.2 Refonte du modèle AdminUser

- Ajout du trait `Searchable`
- Implémentation de `toSearchableArray()` : renvoie username, real_name
- `UserController::index()` utilise `AdminUser::search($kw)->get()` au lieu de LIKE MySQL quand un mot-clé est détecté

ES doit d'abord créer l'index, via les commandes Scout :

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Modifications de routes

Nouvelles routes dans `config/route.php` :

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

Enregistrement des middlewares globaux dans `config/middleware.php` :

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Codes d'erreur complémentaires

| code | Signification | Scénario de déclenchement |
|------|------|---------|
| 429 | Requêtes trop fréquentes | Déclenché par RateLimit |

---

## 6. Hors périmètre de cette itération

- Système de notifications (nécessite une file de messages + une infrastructure de push frontend)
- Pages frontend Flutter (sous-projet B)
- Rafraîchissement du jeton HarmonyOS (sous-projet C)
