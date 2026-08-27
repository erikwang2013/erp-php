# Document de conception de l'architecture de sécurité

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Vue d'ensemble de la défense en profondeur

Le système adopte un modèle de défense en profondeur sur 7 couches, filtrant de l'extérieur vers l'intérieur les requêtes malveillantes, pour garantir que si une couche quelconque échoue, les lignes de défense suivantes prennent le relais.

Toute la chaîne de middleware s'exécute dans l'ordre suivant (voir `config/middleware.php`) :

```
Requête → Cors → SecurityFilter → RateLimit → [middleware de groupe de routes : AdminAuth → AdminPermission → OperationLog] → Controller
```

| Couche | Middleware/mécanisme | Objectif de protection |
|----|--------|---------|
| 1 | SecurityFilter | interception XSS / injection SQL / traversée de chemin / injection de commande / attaques CSRF |
| 2 | Cors | sécurité interdomaines + injection d'en-têtes de sécurité de réponse |
| 3 | RateLimit | limitation de débit à fenêtre glissante Redis, anti-force brute |
| 4 | AdminAuth | authentification JWT + déconnexion par liste noire |
| 5 | AdminPermission | autorisation à granularité method.path RBAC |
| 6 | OperationLog | audit des opérations + traçage de la source |
| 7 | Chiffrement des données | obfuscation des ID Hashids + chiffrement DB Encryptable + chiffrement de transport EncryptionService |

Les trois couches frontend (Flutter) disposent en outre d'une validation d'entrée indépendante ; le backend ne fait pas confiance, chaque couche se défend de manière indépendante.

---

## 2. Moteur de détection d'attaque

### 2.0 Restriction des méthodes HTTP

SecurityFilter valide d'abord la méthode HTTP avant toute détection d'attaque, n'autorisant que les méthodes standard suivantes :

```
GET, POST, PUT, DELETE, OPTIONS, HEAD
```

Les méthodes non standard (telles que TRACE, CONNECT, PATCH, méthodes personnalisées, etc.) renvoient directement **405 Method Not Allowed**, avec un corps de réponse HTML vide, sans entrer dans la détection d'attaque ou la logique métier suivante.

C'est la première ligne de défense de la défense en profondeur, bloquant efficacement :
- les attaques de traçage inter-sites TRACE (XST)
- les abus de proxy tunnel CONNECT
- le sondage de méthodes WebDAV non standard
- l'énumération des méthodes HTTP par les scanners automatisés

### 2.1 XSS (script inter-sites)

Toutes les expressions régulières proviennent de `SecurityFilter::PATTERNS['XSS']`, avec correspondance insensible à la casse.

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Balise de script | `<\s*\/?\s*s\s*c\s*r\s*i\s*p\s*t\b` | `<script>`, `<script >`, `< script>` et autres variantes avec espaces |
| Attribut d'événement | `\bon\w+\s*=\s*[\"\']?\s*(?:javascript\|vbscript):` | événements en ligne type `onclick="javascript:..."` |
| Pseudo-protocole JS | `(?:javascript\|vbscript)\s*:\s*(?:[^\s]*\s*)?(?:eval\|alert\|prompt\|confirm\|document\.cookie\|location\s*=)` | `javascript:eval(...)`, `javascript:alert(1)` etc. |
| XSS par Data URI | `data\s*:\s*text\s*\/\s*html\s*(?:;base64)?\s*,` | `data:text/html,<script>`, `data:text/html;base64,...` etc. |
| Injection de gabarit | `\{\{.*?\}\}` | injection de gabarit serveur/Angular/Vue type `{{constructor}}`, `{{7*7}}` |

### 2.2 Injection SQL

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Requête UNION | `\bUNION\s+(?:ALL\s+)?SELECT\b` | exfiltration de données `UNION SELECT`, `UNION ALL SELECT` |
| Injection OR toujours vrai | `(?:[\"\']\s*OR\s+[\"\']?\s*\d+\s*=\s*\d+\|[\"\']\s*OR\s+[\"\']?1[\"\']?\s*=\s*[\"\']?1)` | `' OR 1=1--`, `" OR '1'='1'` |
| Destruction de structure de table | `\b(?:DROP\|ALTER\|TRUNCATE)\s+(?:TABLE\|DATABASE\|INDEX\|VIEW)\b` | `DROP TABLE users`, `TRUNCATE TABLE logs` |
| Appel de procédure stockée | `\b(?:xp_cmdshell\|sp_executesql\|sp_addsrvrolemember)\b` | exécution de commandes via procédures stockées étendues MSSQL |
| Sondage de métadonnées | `\b(?:INFORMATION_SCHEMA\|sys\.(?:tables\|columns\|databases)\|pg_class\|sqlite_master\|mysql\.(?:user\|db))\b` | sondage de structure de base de données MySQL/PG/SQLite/MSSQL |
| Contournement par commentaire | `(?:[\"\'])\s*(?:--\|#)\s*[\"\']?\s*(?:OR\|AND\|SELECT\|INSERT\|UPDATE\|DELETE\|DROP)` | contournement par commentaire `'-- OR SELECT`, `'# AND UPDATE` |

### 2.3 Traversée de chemin

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Remontée de répertoire | `\.\.[\/\\\\]{2,}` | remontée multi-niveaux `../`, `..\`, `....//` |
| Sondage de fichiers sensibles | `\/(?:etc\/(?:passwd\|shadow\|hosts)\|proc\/self\|boot\.ini\|win\.ini\|WEB-INF\|\.env\|\.git\/)` | `/etc/passwd`, `/proc/self/environ`, `.env`, `.git/HEAD` etc. |
| Troncature par octet nul | `%00` | contournement de la validation d'extension `../../../etc/passwd%00.jpg` |

### 2.4 Injection de commande

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Commande par pipe/point-virgule | `[;\|&]\s*(?:ls\|cat\|rm\|wget\|curl\|nc\|bash\|sh\|cmd\|powershell\|python\|perl)\b` | `;cat /etc/passwd`, `\|bash` |
| Substitution par backticks | `` `[^`]*\b(?:cat\|ls\|id\|whoami\|pwd\|rm\|wget\|curl)\b[^`]*` `` | `` `cat /etc/passwd` `` |
| Substitution $() | `\$\(\s*(?:cat\|ls\|id\|whoami\|rm\|wget\|curl)\b` | `$(whoami)`, `$(cat flag)` |
| Pipe de téléchargement distant | `(?:wget\|curl)\s+.*(?:\b-o\b\|\b-O\b\|pipe\|bash\|python).*\bhttps?:\/\/` | `wget URL -O - \| bash`, `curl URL \| python` |

### 2.5 CSRF (falsification de requête inter-sites)

La logique de validation est implémentée dans `SecurityFilter::checkCsrf()` :

```php
// Seuls POST/PUT/DELETE déclenchent la validation
// Origin et Referer tous deux vides → autorisation (client non-navigateur)
// Origin non vide → analyse du domaine Origin et comparaison avec Host
```

Règles de comparaison :
- après suppression du préfixe `www.` du Host, comparaison exacte avec le domaine de l'Origin
- si le Host est un domaine parent de l'Origin (ex. `Origin: app.example.com`, `Host: example.com` — déclenche `str_contains($originHost, '.' . $hostOnly)`), autorisation
- ni correspondance exacte ni sous-domaine → 403, jugé attaque CSRF

Remarque : les clients non-navigateurs (comme curl sans Origin/Referer) sont directement autorisés ; la protection CSRF n'est efficace qu'en environnement navigateur.

### 2.6 Téléversement de fichiers malveillants

| Motif de détection | Expression régulière | Attaque défendue |
|----------|------|-----------|
| Déguisement par double extension | `\.(?:php\d?\|phtml\|phar\|cgi\|pl\|py\|jsp\|asp)x?\.(?:png\|jpg\|gif\|pdf)` | contournement de la liste blanche `shell.php.png`, `shell.phar.jpg` |
| Extension PHP | `\.php\s*$/m` | passage direct d'un chemin `.php` dans les paramètres de requête |

---

## 3. Escalade d'attaque et liste noire IP

SecurityFilter intègre un mécanisme d'escalade d'attaque pour empêcher une même IP de scanner en continu.

### Processus d'escalade

```
1re détection → Redis INCR security_escalate:{ip} = 1, TTL=60s
2e détection → INCR → 2
...
5e détection → INCR → 5
    → bannissement : SETEX security_ban:{ip} 900 1
    → vidage du compteur DEL security_escalate:{ip}
    → journal de sécurité : [SECURITY] IP banned 15min
```

### Comportement pendant le bannissement

Chaque requête vérifie d'abord `isBanned()` en entrant dans SecurityFilter :

```php
if (Redis::get("security_ban:{$ip}")) {
    return response('<h1>403 Forbidden</h1>', 403);
}
```

Pendant 15 minutes, toutes les requêtes de l'IP bannie (y compris les requêtes légitimes) renvoient directement 403, en sautant entièrement la logique métier suivante.

### Constantes de configuration

| Constante | Valeur | Signification |
|------|-----|------|
| ESCALATE_LIMIT | 5 | seuil de déclenchements dans la fenêtre de 60 s |
| ESCALATE_WINDOW | 60 | fenêtre du compteur (secondes) |
| BAN_DURATION | 900 | durée de la liste noire (secondes), soit 15 minutes |

### Journal de sécurité

Emplacement du fichier : `runtime/logs/security.log`

Exemple de format de journal :
```
2026-05-20 14:32:11 [SECURITY] XSS attack blocked | IP: 192.168.1.100 | Path: /admin/user | Field: body.username | Source: body | Payload: <script>alert(1)</script>
2026-05-20 14:32:15 [SECURITY] IP banned 15min | IP: 192.168.1.100 | Triggers: 5
```

### Limite de taille du corps de requête

`Content-Length > 10MB` renvoie directement 413 Payload Too Large, contre les attaques DoS par corps de requête surdimensionné.

### Validation du Content-Type

Les requêtes POST/PUT **doivent** déclarer un `Content-Type` `application/json` ou `application/x-www-form-urlencoded`, sinon 415 Unsupported Media Type est renvoyé. Les requêtes de téléversement de fichiers (avec champ file) sautent cette vérification.

---

## 4. En-têtes de sécurité de réponse

Tous les en-têtes sont injectés dans le middleware `Cors`, ajoutés à chaque réponse via `$response->withHeaders()`.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Access-Control-Allow-Origin | `*` | autorise toute origine interdomaine (scénario console d'administration en intranet) |
| Access-Control-Allow-Methods | `GET,POST,PUT,DELETE,OPTIONS` | ensemble des méthodes autorisées |
| Access-Control-Allow-Headers | `Authorization,Content-Type,API-Version` | en-têtes personnalisés autorisés |
| Access-Control-Max-Age | `86400` | cache de la requête de prévol 24 h |
| X-Content-Type-Options | `nosniff` | interdit le sniffing MIME du navigateur |
| X-Frame-Options | `DENY` | interdit tout embedding iframe, contre le détournement de clic |
| X-XSS-Protection | `1; mode=block` | active le filtre XSS intégré du navigateur et bloque le rendu de la page |
| Referrer-Policy | `strict-origin-when-cross-origin` | URL complète en même origine, domaine seul en interdomaine |
| Permissions-Policy | `camera=(), microphone=(), geolocation=()` | désactive sur tout le site les API caméra/micro/géolocalisation |

Les requêtes de prévol OPTIONS renvoient directement une réponse vide 204, sans entrer dans la chaîne de middleware suivante.

### 4.2 Content-Security-Policy (CSP)

Injecté avec les autres en-têtes de sécurité dans le middleware Cors, il fournit une défense en profondeur en limitant les sources de ressources que le navigateur peut charger et exécuter.

| En-tête | Valeur | Rôle |
|----|-----|------|
| Content-Security-Policy | `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'` | limite les sources des ressources script/style/image/connexion/frame/formulaire |
| X-Permitted-Cross-Domain-Policies | `none` | interdit le chargement de fichiers de politique interdomaines Adobe Flash/PDF etc. |

Points clés de la politique CSP :
- `default-src 'self'` : par défaut, seules les ressources de même origine
- `script-src 'self' 'unsafe-inline' 'unsafe-eval'` : autorise les scripts de même origine + scripts en ligne (nécessaire pour Flutter Web) + eval (nécessaire pour le débogage Flutter Web)
- `frame-ancestors 'none'` : interdit l'embedding iframe par toute page, double sécurité avec X-Frame-Options: DENY
- `base-uri 'self'` : limite la balise `<base>` à la même origine
- `form-action 'self'` : limite la soumission des formulaires à la même origine

---

## 5. Politique de limitation de débit

### Algorithme

Fenêtre glissante Redis Sorted Set + script atomique Lua, opérations clés :

```lua
-- 1. Suppression des anciens enregistrements hors fenêtre
redis.call('ZREMRANGEBYSCORE', KEYS[1], 0, windowStart)
-- 2. Vérification du compte de la fenêtre courante
local count = redis.call('ZCARD', KEYS[1])
-- 3. Renvoie {0, count} si dépassement, sinon ZADD et renvoie {1, count+1}
if count >= limit then return {0, count} end
redis.call('ZADD', KEYS[1], now, now . '.' . random)  -- suffixe aléatoire pour éviter l'écrasement à la même milliseconde
redis.call('EXPIRE', KEYS[1], window + 10)
```

Le script Lua s'exécute en un seul thread côté serveur Redis, **naturellement atomique**, éliminant les courses TOCTOU (Time-of-check to Time-of-use).

### Configuration de la limitation de débit

| Route | Limite | Fenêtre | Scénario |
|------|------|------|------|
| Défaut (toutes les routes) | 60 fois/minute | 60 s | API générale |
| `/api/auth/login` | 10 fois/minute | 60 s | connexion (anti-force brute) |
| `/api/auth/register` | 5 fois/minute | 60 s | inscription (anti-inscription de masse ; désactivée par défaut, nécessite `REGISTRATION_ENABLED=1`) |

### En-têtes de réponse

En cas de déclenchement de la limitation, HTTP 429 avec corps JSON est renvoyé :
```json
{"code": 429, "message": "Trop de requêtes, veuillez réessayer plus tard", "data": []}
```

Toutes les réponses (y compris normales) portent les en-têtes suivants :

| En-tête | Description |
|----|------|
| X-RateLimit-Limit | nombre maximal de requêtes autorisées dans la fenêtre courante |
| X-RateLimit-Remaining | nombre de requêtes restantes dans la fenêtre courante |
| X-RateLimit-Reset | horodatage Unix de réinitialisation de la fenêtre |
| Retry-After | présent uniquement en cas de limitation, secondes d'attente recommandées |

### Stratégie de dégradation

En cas d'anomalie Redis (timeout de connexion, indisponibilité, etc.) : **fail-open** :

```php
try {
    $result = Redis::eval($lua, ...);
} catch (\Throwable $e) {
    return $handler($request); // Redis down, toutes les requêtes autorisées
}
```

Mieux vaut perdre temporairement la protection de limitation que de bloquer les requêtes métier légitimes.

### 5.4 Mécanisme de verrouillage de compte

En plus de la limitation de débit, l'interface de connexion dispose d'un mécanisme de **verrouillage de compte** contre la force brute ciblée sur un utilisateur précis.

**Processus de verrouillage** :

```
Échec de connexion → Redis INCR account_lockout:{userId} TTL=900s
5 échecs consécutifs → Redis SETEX account_locked:{userId} 900 1
            → renvoi 429 "Compte verrouillé, réessayez dans 15 minutes"
            → vidage du compteur DEL account_lockout:{userId}
```

**Comportement pendant le verrouillage** :

Pendant le verrouillage, toutes les requêtes de connexion renvoient directement 429, sans validation du mot de passe, bloquant totalement les tentatives de force brute.

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_LOGIN_ATTEMPTS | 5 | nombre maximal d'échecs consécutifs |
| LOCKOUT_DURATION | 900 | durée du verrouillage (secondes), soit 15 minutes |

Remarque : le verrouillage est basé sur `userId` et non sur l'IP ; changer d'IP ne contourne donc pas le verrouillage. Combiné à la limitation IP (10/minute), il forme une double protection :
- Niveau IP : limitation 10/minute contre la force brute distribuée
- Niveau compte : verrouillage après 5 échecs contre la force brute ciblée

---

## 6. Authentification et autorisation

### 6.1 Authentification JWT

Implémentée par le middleware AdminAuth, montée sur les groupes de routes nécessitant une authentification.

**Configuration des paramètres** (`config/plugin/erikwang2013/jwt/jwt`, injectée par `.env`) :

| Paramètre | Valeur | Description |
|------|-----|------|
| Algorithme | HS256 | signature symétrique HMAC-SHA256 |
| Clé | `JWT_SECRET` | injectée par variable d'environnement, à remplacer en production |
| TTL access_token | 7200 s (2 h) | `JWT_TTL` |
| TTL refresh_token | 1209600 s (14 j) | `JWT_REFRESH_TTL` |
| Émetteur | `open-admin` | `JWT_ISSUER` |
| Audience | `open-admin` | `JWT_AUDIENCE` |

**Extraction du jeton** : extrait de l'en-tête `Authorization: Bearer <token>`, suppression du préfixe `Bearer ` pour obtenir le JWT brut.

**Processus d'authentification** :
1. jeton vide → 401 direct `{"code": 401, "message": "Non connecté"}`
2. vérification de la liste noire Redis `jwt_blacklist:{md5(token)}` → hit → 401 `Jeton invalide, veuillez vous reconnecter`
3. décodage JWT → échec (expiré/signature non correspondante) → 401 `Jeton expiré ou invalide`
4. succès → injection de `$request->adminId` et `$request->adminUsername`

**Mécanisme de liste noire** : à la déconnexion, `md5(token)` est écrit dans Redis avec un TTL égal à la durée de validité restante du JWT. En cas de panne Redis, la vérification de liste noire est sautée (fail-open) ; le jeton déconnecté reste utilisable un court moment, mais la courte durée de validité du JWT (2 h) sert de filet de sécurité.

### 6.2 Limitation des sessions concurrentes

Pour éviter l'abus multi-appareils après fuite d'un jeton, le système limite le nombre de jetons valides qu'un même utilisateur peut détenir simultanément.

**Logique de limitation** :

```
Connexion réussie → émission d'un nouveau jeton
         → comptage des jetons valides de l'utilisateur courant : Redis SCARD user_tokens:{userId}
         → si le compte >= 3 (MAX_CONCURRENT_SESSIONS) :
            → tri par date de création croissante, suppression du jeton le plus ancien :
              Redis SREM user_tokens:{userId} <oldest_token_id>
              Redis SETEX jwt_blacklist:{md5(oldest_token)} TTL remaining
         → ajout du nouveau jeton à l'ensemble : Redis SADD user_tokens:{userId} <new_token_id>
            Redis SET user_token:{token_id} {userId} EX {TTL}
```

**Constantes de configuration** :

| Constante | Valeur | Signification |
|------|-----|------|
| MAX_CONCURRENT_SESSIONS | 3 | nombre maximal de jetons concurrents par utilisateur |

**Scénario d'expulsion** : lorsqu'un utilisateur se connecte sur un 4e appareil, le jeton du 1er appareil est forcé en liste noire et ses requêtes ultérieures renvoient 401 « Jeton invalide, veuillez vous reconnecter ».

À la déconnexion, le jeton courant est retiré de l'ensemble. À l'expiration naturelle d'un jeton, la clé Redis expire automatiquement et le membre de l'ensemble disparaît.

### 6.3 Modèle d'autorisation RBAC

Implémenté par le middleware AdminPermission.

**Modèle de données** : association à trois niveaux User -> Role -> Permission

- `erp_admin_user` (table des utilisateurs)
- `erp_admin_user_role` (table d'association utilisateur-rôle)
- `erp_admin_role` (table des rôles)
- `erp_admin_role_permission` (table d'association rôle-permission)
- `erp_admin_permission` (table des permissions)

**Types de permissions** :
| type | Signification | Exemple |
|------|------|------|
| 1 | Permission de menu | contrôle la visibilité de la navigation de gauche |
| 2 | Permission de bouton | contrôle les boutons d'action de la page (créer/modifier/supprimer) |
| 3 | Permission d'API | contrôle l'appel des interfaces backend |

Format de l'identifiant de permission API : `{method}.{path}`

Par exemple :
- `post.admin/user` — créer un utilisateur
- `put.admin/user` — modifier un utilisateur
- `delete.admin/user` — supprimer un utilisateur
- `get.admin/user` — consulter la liste des utilisateurs

**Processus d'autorisation** :
1. `$request->adminId` vide → autorisation (la route n'a pas de prérequis d'authentification)
2. récupération de l'utilisateur → rôles (saut des rôles désactivés `status=0`) → liste des permissions
3. super administrateur (`slug = '*'`) → autorisation directe
4. construction de `strtolower(method) . '.' . trim(path, '/')` → comparaison avec la liste des permissions
5. échec de correspondance → 403 `{"code": 403, "message": "Accès non autorisé"}`

**Double confirmation** : BaseController fournit la méthode `confirmPassword()` ; les opérations sensibles (suppression d'utilisateur, export de données, etc.) exigent au niveau Controller la saisie du mot de passe courant, contre les opérations non autorisées après détournement de session.

---

## 7. Journaux d'audit

### 7.1 Journal des opérations

Le middleware OperationLog enregistre automatiquement les opérations pour les requêtes POST / PUT / DELETE. Les requêtes GET ne sont pas journalisées.

**Champs enregistrés** :

| Champ | Source | Description |
|------|------|------|
| id | SnowflakeService::generate() | ID global unique |
| user_id | `$request->adminId` | ID de l'opérateur, 0 si non connecté |
| action | `$request->method()` | équivalent à method |
| method | `$request->method()` | POST / PUT / DELETE |
| path | `$request->path()` | chemin de la requête |
| ip | `$request->getRealIp()` | IP réelle du client |
| source | detectSource() | plateforme source du client |
| input | corps de la requête (JSON masqué) | données soumises par l'opération |
| created_at | `date('Y-m-d H:i:s')` | heure de l'opération |

**Filtrage des champs sensibles** : parcours récursif du corps de requête ; la valeur des champs suivants est remplacée par `***` :

`password`, `old_password`, `new_password`, `new_password_confirmation`, `token`, `secret`, `access_token`, `refresh_token`

**Détection de la source** (`detectSource()`) : par ordre de priorité :

1. lecture prioritaire de l'en-tête personnalisé `X-Client-Platform` (déclaré explicitement par les clients natifs)
2. repli sur l'inférence à partir de la chaîne User-Agent (ordre de détection de la méthode `detectSource()`) :

| Plateforme | Mots-clés UA |
|------|----------|
| iPadOS | `iPad` |
| macOS | `Macintosh`, `Mac OS` |
| Windows | `Windows` |
| Linux | `Linux` |
| iOS | `iPhone`, `iOS`, `CFNetwork` |
| Android | `Android` |
| HarmonyOS | `HarmonyOS`, `OpenHarmony` |
| Web | valeur par défaut de repli |

**Tolérance aux pannes** : une anomalie d'écriture de journal ne bloque pas la requête métier (`catch (\Throwable)` avalé silencieusement).

### 7.2 Journal de sécurité

**Emplacement du fichier** : `runtime/logs/security.log`

**Contenu enregistré** :
- journaux d'interception d'attaque : catégorie d'attaque, IP, chemin, champ, source, extrait de payload (200 premiers caractères)
- notifications de bannissement IP : IP bannie, nombre de déclenchements

La permission de journal est `FILE_APPEND | LOCK_EX`, garantissant une écriture concurrente sûre.

---

## 8. Protection des données

Le système adopte une stratégie de protection des données en trois couches, correspondant aux trois phases de circulation des données.

### 8.1 Couche de transport — EncryptionService

`EncryptionService` utilise le paquet `erikwang2013/encryption` pour chiffrer/déchiffrer les champs sensibles des requêtes/réponses API.

**Détails techniques** :
- Algorithme : `aes-256-cbc-hmac` (avec signature HMAC intégrée anti-altération)
- Clé : variable d'environnement `ENCRYPTION_KEY`, alignée automatiquement sur 32 octets
- Usage : transport entre le client et l'API de champs tels que numéro de téléphone, numéro de carte d'identité

**Méthodes utilitaires de masquage** :
- `maskPhone('13812341234')` → `138****1234`
- `maskEmail('abc@example.com')` → `a***@example.com` (nom d'utilisateur de plus de 2 caractères) ou `a**@example.com`

### 8.2 Couche de stockage — Cast Encryptable

Le modèle `AdminUser` utilise le cast Eloquent `Erikwang2013\Encryptable\Encryptable`, champs concernés :

- `email` → cast en Encryptable, chiffrement/déchiffrement automatique
- `phone` → cast en Encryptable, chiffrement/déchiffrement automatique
- `id_card` → cast en Encryptable, chiffrement/déchiffrement automatique

À l'écriture en base de données, les données sont automatiquement chiffrées en texte chiffré ; à la lecture, déchiffrées en clair. Le type de colonne en base est `VARCHAR(500)`, le texte chiffré est stocké en base64.

**Système de clés** : indépendant du chiffrement de couche transport (`ENCRYPTION_KEY`), la couche stockage utilise `ENCRYPTABLE_KEY` — la fuite d'une clé ne compromet pas l'autre couche.

Rotation des clés : la variable d'environnement `ENCRYPTION_PREVIOUS_KEYS` prend en charge une liste de clés historiques (séparées par des virgules) ; à la lecture des anciennes données, on tente de déchiffrer avec les clés historiques, et à l'écriture on rechiffre avec la clé courante.

### 8.3 Couche d'affichage — Obfuscation des ID et masquage

**Obfuscation des ID Hashids** : `HashidsService` utilise le paquet `erikwang2013/hashids`.

- les ID BIGINT de base de données renvoyés par l'API externe sont encodés en chaînes de hash (ex. `xK3mN9qR2pL7wV8b`)
- le client envoie la chaîne de hash dans ses requêtes, le backend décode automatiquement en ID d'origine
- le sel `HASHIDS_SALT` est injecté par variable d'environnement ; des sels différents donnent des résultats d'encodage/décodage totalement différents
- longueur minimale du hash : 16 caractères, jeu de caractères alphanumérique de 62
- BaseController fournit les méthodes pratiques `encodeId()`, `decodeId()`, `encodeIds()`

**Masquage à l'export** : lors des exports Excel/PDF (ExportController), les champs sensibles sont uniformément masqués :
- numéro de téléphone : `138****1234`
- e-mail : `a***@example.com`
- carte d'identité : entièrement couvert par `********`

---

## 9. Gestion des clés

Toutes les clés sont injectées via les variables d'environnement `.env` ; les fichiers de configuration les lisent avec `getenv()` et disposent de valeurs par défaut de repli intégrées (sûres uniquement en environnement de développement).

| Variable d'environnement | Usage | Paquet | Exigence de production |
|----------|------|-----|---------|
| JWT_SECRET | clé de signature JWT | erikwang2013/jwt-webman | chaîne aléatoire de 64+ caractères |
| JWT_ALGORITHM | algorithme de signature JWT | idem | conserver HS256 |
| HASHIDS_SALT | sel d'encodage des ID | erikwang2013/hashids | chaîne aléatoire |
| SNOWFLAKE_DATACENTER_ID | ID de centre de données (0-31) | erikwang2013/snowflake-php | conserver la valeur par défaut pour un site unique |
| ENCRYPTION_KEY | clé de chiffrement de couche transport API | erikwang2013/encryption | chaîne aléatoire de 32 octets |
| ENCRYPTABLE_KEY | clé de chiffrement de couche stockage DB | erikwang2013/encryptable | chaîne aléatoire de 32 octets, différente de la clé de transport |

**Exigences de sécurité** :
- le fichier `.env` est ajouté à `.gitignore`, toute soumission au dépôt est strictement interdite
- `.env.example` est un fichier modèle public, sans clé réelle
- en production, **toutes** les clés par défaut **doivent** être remplacées par des chaînes aléatoires
- génération de clés recommandée : `openssl rand -base64 32`

### Isolation du stockage des clés

| Couche | Clé de configuration | Variable d'environnement de clé |
|----|--------|-------------|
| Chiffrement de transport | `config/encryption.php` → `key` | `ENCRYPTION_KEY` |
| Chiffrement de stockage | `config/encryptable.php` → `key` | `ENCRYPTABLE_KEY` |
| Obfuscation des ID | `config/hashids.php` → `connections.main.salt` | `HASHIDS_SALT` |
| Signature JWT | `config/plugin/erikwang2013/jwt/jwt` | `JWT_SECRET` |

---

## 10. security.txt (RFC 9116)

Le système fournit à `/.well-known/security.txt` un point d'accès d'informations de contact de sécurité conforme à la norme RFC 9116, facilitant aux chercheurs en sécurité le signalement rapide de vulnérabilités.

**Mode d'accès** :

```
GET /.well-known/security.txt
```

**Contenu de la réponse** :

```text
Contact: mailto:security@erik.xyz
Expires: 2027-05-20T00:00:00.000Z
Preferred-Languages: zh, en
Canonical: https://erik.xyz/.well-known/security.txt
Policy: https://erik.xyz/security-policy
```

**Description des champs** :

| Champ | Description |
|------|------|
| Contact | coordonnées de signalement des vulnérabilités de sécurité |
| Expires | date d'expiration du fichier, à mettre à jour régulièrement |
| Preferred-Languages | langues de communication préférées |
| Canonical | URL canonique de ce fichier |
| Policy | lien vers la politique de sécurité / de divulgation des vulnérabilités |

Ce point d'accès n'est soumis à aucun middleware de limitation de débit, d'authentification, etc. ; tout le monde peut y accéder directement.

---

## 11. Configuration de sécurité Nginx

Le projet fournit `docs/nginx-security.conf` comme configuration de référence de durcissement du proxy inverse Nginx en production.

**Mesures de sécurité incluses** :

| Élément de configuration | Rôle |
|--------|------|
| `server_tokens off` | masque le numéro de version Nginx |
| `client_max_body_size 10m` | limite la taille du corps de requête, en coordination avec SecurityFilter |
| `limit_req_zone` | limitation de fréquence des requêtes au niveau Nginx |
| `limit_conn_zone` | limitation du nombre de connexions concurrentes |
| en-têtes de sécurité `add_header` | ajout au niveau Nginx d'en-têtes de sécurité tels que X-XSS-Protection |
| `if ($request_method)` | rejet au niveau Nginx des méthodes HTTP non standard |
| Configuration SSL/TLS | configuration moderne TLS 1.2/1.3, désactivation des suites de chiffrement faibles |
| Masquage des en-têtes backend | `proxy_hide_header` supprime les en-têtes sensibles tels que la version webman |

**Utilisation** : fusionnez la configuration de `docs/nginx-security.conf` dans votre bloc server Nginx, en ajustant selon votre domaine et vos chemins de certificats réels.

---

## 12. Modèle de menaces

### 12.1 Menaces couvertes

| Type de menace | Vecteur d'attaque | Couches de défense |
|----------|---------|---------|
| Abus de méthode HTTP | attaques XST TRACE/TRACK, proxy tunnel CONNECT, sondage de méthodes WebDAV | whitelist de méthodes 405 SecurityFilter (GET/POST/PUT/DELETE/OPTIONS/HEAD) |
| Force brute ciblée | essais répétés de mot de passe contre un utilisateur précis | verrouillage de compte (5 échecs → 15 min) + RateLimit (connexion 10/min) + Captcha |
| Force brute | essais répétés de nom d'utilisateur/mot de passe depuis des IP distribuées | RateLimit (connexion 10/min) + Captcha |
| XSS (script inter-sites) | `<script>`, onerror, javascript: | SecurityFilter (5 motifs) + en-tête de réponse X-XSS-Protection + CSP |
| Injection SQL | UNION SELECT, OR 1=1, contournement par commentaire | SecurityFilter (6 motifs) + requêtes paramétrées Eloquent ORM |
| CSRF (falsification de requête inter-sites) | requêtes émises par des sites malveillants | validation Origin/Referer SecurityFilter |
| Traversée de chemin | `../../etc/passwd` | motifs de traversée de chemin SecurityFilter + whitelist d'extensions UploadController |
| Injection de commande | `;ls`, `` `whoami` ``, `$(cat ...)` | SecurityFilter (4 motifs) |
| Détournement de session | vol du jeton JWT | JWT à courte validité (2 h) + déconnexion par liste noire + double confirmation du mot de passe pour les opérations sensibles |
| Énumération d'ID | parcours d'ID numériques pour deviner le volume de données | obfuscation Hashids en chaînes aléatoires |
| Fuite de données | extraction de la DB / homme du milieu / fuite de journaux | chiffrement/masquage en trois couches + filtrage des champs sensibles OperationLog |
| Attaque DoS | corps de requête surdimensionné / requêtes à haute fréquence | limite de corps 10 Mo + RateLimit 60/min + liste noire IP |
| Élévation de privilèges | accès d'un utilisateur à faible privilège aux interfaces d'administration | autorisation à granularité method.path RBAC |
| Attaque par téléversement de fichier | double extension shell.php.png | détection de fichiers malveillants SecurityFilter |

### 12.2 Limites connues

| Limite | Périmètre d'impact | Mesures d'atténuation |
|------|---------|---------|
| La protection CSRF n'est efficace qu'en navigateur | les clients non-navigateurs (curl, Postman, applications mobiles) peuvent sauter la vérification Origin/Referer | les clients non-navigateurs ne sont naturellement pas exposés au CSRF ; on s'appuie sur l'authentification JWT en lieu et place des cookies |
| En cas d'indisponibilité Redis, limitation et liste noire dégradent en fail-open | un attaquant peut contourner la limitation de débit et l'interception à haute fréquence | surveiller et alerter la disponibilité Redis ; la courte validité JWT sert de filet de sécurité |
| Pas de moteur WAF indépendant | SecurityFilter utilise des correspondances regex `@preg_match`, pas un moteur de règles WAF dédié | en production, placer en amont Nginx ModSecurity ou un WAF Cloudflare |
| Le JWT sans état ne peut pas être révoqué activement | impossible de révoquer côté serveur un jeton non expiré (hors liste noire) | liste noire + TTL court de 2 h réduisent la fenêtre de risque |
| La liste noire IP n'est stockée qu'en mémoire | la liste noire est perdue après redémarrage de Redis | la durée de bannissement n'est que de 15 minutes, impact limité |
| Pas de limitation spécifique sur les points d'accès administrateur | les interfaces administrateur partagent la limite par défaut de 60/min avec les interfaces ordinaires | la fréquence d'opération des administrateurs est naturellement faible, pas de distinction nécessaire pour l'instant |
| `@preg_match` supprime les erreurs | échec silencieux sur entrée regex malformée | `preg_last_error()` peut être surveillé, non implémenté actuellement |
