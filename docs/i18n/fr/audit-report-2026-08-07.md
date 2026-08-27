# Rapport d'audit — 2026-08-07

**Projet** : erp-php (webman 5.2.0 / PHP 8.3.7 / workerman event-loop : select)
**Périmètre** : test de fonctionnement global, vérification approfondie, correction des problèmes P0/P1
**Directive** : « Testez l'ensemble, exécutez-le, vérifiez en profondeur s'il reste des problèmes ou des optimisations à faire ? »
**Résultat des tests** : OK (135 tests, 799 assertions) — tous réussis

---

## 1. Résultats des tests et de la validation d'exécution

| Élément | Résultat |
|---|---|
| Suite PHPUnit complète | 135 tests / 799 assertions tous réussis |
| Démarrage du service (port 8787→temporaire 8791) | démarrage normal, aucun crash de processus |
| Vérification de santé /health | code=0, champs database/redis/elasticsearch complets |
| Chaîne de limitation de débit | les requêtes consécutives à /api/auth/login renvoient 429 |
| Liste noire JWT / verrouillage de connexion | fonctionnent correctement (après correction Redis) |
| CS-Fixer | 31 fichiers présentant des violations de formatage corrigés |
| PHPStan | reprend après réparation du cache corrompu (851 faux positifs de méthodes magiques ORM, 75 éléments de baseline obsolètes) |

---

## 2. Corrections P0 (pannes d'exécution — toutes corrigées et vérifiées)

### 2.1 Classe support\Redis manquante — mécanismes de sécurité silencieusement désactivés

- **Symptôme** : `support\Redis` n'existe pas (webman/redis n'a jamais été ajouté dans composer.json), 9 fichiers y font référence.
- **Cause racine** : plusieurs `catch (\Throwable)` en fail-open ont avalé les erreurs de classe manquante, désactivant silencieusement la limitation de débit, la liste noire JWT, le verrouillage de connexion et le bannissement — l'interface « semble normale » mais sans aucune protection.
- **Correctif** : `composer require webman/redis` ; `config/redis.php` basé sur des variables d'environnement (REDIS_PASSWORD/HOST/PORT/DATABASE).
- **Validation** : /health renvoie `redis: ok` ; le test de limitation renvoie 429.

### 2.2 Échec de compilation du middleware ApiVersion — toutes les routes /api en 500

- **Symptôme** : `Interface "app\middleware\MiddlewareInterface" not found` — `use Webman\MiddlewareInterface;` manquant.
- **Erreur secondaire après correction** : `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` est une sous-classe de `Webman\Http\Request`, violant le contrat de contravariance des paramètres.
- **Correctif** : passage aux imports `Webman\Http\Request` / `Webman\Http\Response`.

### 2.3 Contravariance des paramètres du middleware AdminAuth — crash du worker sur /admin

- **Symptôme** : /admin/dashboard déclenche une réponse vide du worker (crash de compilation).
- **Cause racine** : même problème de contravariance des paramètres qu'en 2.2.
- **Correctif** : passage à `Webman\Http\Request` / `Webman\Http\Response` (conservation de `support\Redis`).
- **Validation** : renvoie un JSON 401.

### 2.4 Fonction d'aide validator() inexistante — 500 à la connexion

- **Symptôme** : `Call to undefined function validator()`, 105 appels dans 99 fichiers.
- **Correctif** : `composer require illuminate/validation` ; implémentation de la fonction d'aide dans `app/functions.php` (cache statique $factory).
- **Piège rencontré** : le premier paramètre de `Factory::__construct()` doit être un `Translator`, pas un `ArrayLoader`.
- **Reste (P2)** : les messages d'erreur ne sont pas traduits (affichage de `validation.required` au lieu du chinois), il faut ajouter le pack de langue zh_CN.

### 2.5 CORS codé en dur + réponse de prévol sans en-têtes CORS

- **Correctif** : ajout de `app/common/CorsPolicy.php`, lecture de la liste blanche depuis la variable d'environnement `CORS_ALLOWED_ORIGIN` (séparée par des virgules), écho de l'origine ; aucun en-tête CORS envoyé en cas de non-correspondance.
- **Point clé** : `Route::fallback` ne passe pas par la chaîne de middleware globale, la prévol OPTIONS doit ajouter elle-même les en-têtes CORS — géré dans la fermeture de fallback.
- **En-têtes de sécurité** : suppression du X-XSS-Protection obsolète ; ajout de `connect-src 'self'` au CSP.

### 2.6 FastRoute BadRouteException — masquage de routes

- **Symptôme** : `Static route "/install" is shadowed by previously defined variable route`.
- **Cause racine** : la route générique OPTIONS `/{path:.+}` masque les routes statiques suivantes ; les routes de plugin (apidoc) sont chargées après config/route.php.
- **Correctif** : suppression de la route générique, passage à `Route::fallback` (doit être placé à la fin du fichier de routes) ; `/crm/pool/rules` passe de resource à une route GET explicite, `PoolController::rules()` devient public.

---

## 3. Corrections P1 (qualité du projet)

- **3.1 Cache PHPStan corrompu** : /tmp/phpstan/cache provient du répertoire service/ supprimé (résidu du découpage en microservices), contenant d'anciens chemins absolus causant des erreurs de phar et un blocage à 0 % de CPU. Après vidage du cache et réinstallation, tout fonctionne. Les 851 erreurs sont des faux positifs des méthodes magiques de l'ORM webman ; 75 éléments de baseline pointent vers le répertoire service/ inexistant (P2).
- **3.2 CS-Fixer** : 31 fichiers présentant des violations d'espacement/tri des imports corrigés.
- **3.3 Synchronisation des tests** : `test_cors_response_is_assigned_correctly` mis à jour pour asserter la nouvelle implémentation (withHeaders + CorsPolicy).

---

## 4. Causes racines manquées par l'audit précédent (08-04)

- Les tests ne couvraient pas la **chargeabilité des classes de middleware** ni **l'appelabilité des routes** (class_exists / is_subclass_of ne peuvent pas détecter les imports manquants ni la contravariance des paramètres).
- La correction CORS/X-XSS prétendue par le commit b1fe2de ne correspondait pas au code réel — les conclusions de l'audit reposaient trop sur les messages de commit plutôt que sur la validation par l'exécution.

---

## 5. Liste des changements de cette itération (git status : 41 modifiés + 2 ajoutés)

| Fichier | Changement |
|---|---|
| app/middleware/ApiVersion.php | ajout de use Webman\MiddlewareInterface ; types de paramètres en Webman\Http |
| app/middleware/AdminAuth.php | types de paramètres en Webman\Http |
| app/middleware/Cors.php | refactorisation pour utiliser CorsPolicy ; mise à jour CSP/en-têtes de sécurité |
| app/common/CorsPolicy.php | **Nouveau** : politique de liste blanche CORS |
| config/route.php | route fallback + correction de /crm/pool/rules |
| app/controller/crm/PoolController.php | rules() devient public |
| app/functions.php | ajout de la fonction d'aide validator() |
| config/redis.php | **Nouveau** (généré par composer puis basé sur des variables d'environnement) |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | synchronisation des assertions CORS |
| ~30 autres fichiers | corrections de formatage CS-Fixer |

---

## 6. Suggestions P2 (environnement/à faire, non corrigées)

1. **DB_PASSWORD vide dans .env** — l'authentification root MySQL échoue, `database: unavailable` ; il faut configurer un mot de passe réel.
2. **Conflit sur le port 8787** — occupé par cloud-php/service (projet différent) ; le déploiement en production doit différencier les ports.
3. **Messages d'erreur chinois de validator** — installer un pack de langue ou des messages personnalisés.
4. **Reconstruction de la baseline PHPStan** — 75 chemins pointent vers le répertoire service/ supprimé, nettoyage et reconstruction recommandés.
5. **Audit fail-open** — il est recommandé d'examiner globalement les points d'absorption silencieuse d'erreurs `catch (\Throwable)` (1 conséquence grave découverte cette fois), et de passer en fail-closed ou en journalisation explicite.

---

*Rapport généré : 2026-08-07, service arrêté, port rétabli sur 8787.*
