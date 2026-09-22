# Open Admin (open-admin)

Système de gestion full-stack basé sur webman v2 + Flutter.

![Mascotte pieuvre](images/mascot.svg)

## Avis de droit d'auteur

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

> **Immuable, inamovible, irréversible.** Tout nouveau fichier doit contenir l'avis de droit d'auteur ci-dessus en en-tête de commentaire.

## Feuille de route de l'écosystème

> Spécification de conception : `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
> Document d'architecture : `docs/ARCHITECTURE.md` §21
> Matrice fonctionnelle : `docs/FUNCTIONS.md` §19

**Score global actuel 89/100** — la feuille de route complète P0~P3 est terminée, couverture full-stack des 23 modules, prêt pour la production.

| Phase | Durée | Livrables | Statut |
|------|------|--------|------|
| 🔵 **P0** Écosystème frontend | 3-4 semaines | 102 routes de menu Flutter (`menu_config.dart`) + 41 pages HarmonyOS + 4 composants communs | ✅ |
| 🟢 **P1** Profondeur métier | 4-6 semaines | Moteur financier + moteur de paie + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Fiabilité d'exploitation | 1-2 semaines | Migration/rollback + sauvegarde automatique + TraceId + double pilote de file d'attente | ✅ |
| 🟣 **P3** Amélioration de l'expérience | 2-3 semaines | Tableaux de bord BI + EAM + multi-tenant + DMS + 7 nouvelles tables | ✅ |

**Tests** : 1048<!-- stats:tests=1048 --> tests, 5040<!-- stats:assertions=5040 --> assertions (23 skipped) — ALL PASSING. **Flutter** : 0 error, 0 warning.

## Liste des fonctionnalités

| Domaine | Fonctionnalités |
|----|------|
| Authentification | Connexion/inscription/rafraîchissement/déconnexion + captcha + verrouillage de compte + limitation des sessions |
| Tableau de bord | Vue d'ensemble / tableau de bord des ventes / des stocks / des finances (cache Redis 5 min) |
| Utilisateurs | CRUD + suppression en masse/activation-désactivation + import Excel |
| Rôles et permissions | CRUD + arbre de permissions + authentification RBAC method.path |
| Configuration système | CRUD de paires clé-valeur |
| Audit des opérations | Consultation des journaux + détection automatique de 8 plateformes source |
| Fichiers | Upload + export Excel/PDF (masquage des données sensibles) |
| Sécurité | 7 couches de défense en profondeur (XSS/injection SQL/CSRF/limitation de débit/CSP...) |
| Exploitation | Health check / métriques Prometheus / documentation API / security.txt + Docker + CI/CD |
| Gestion des produits | Produits/SKU/catégories/marques/entrepôts/emplacements/fournisseurs/clients |
| Gestion des achats | Demande→commande→réception→retour→règlement (entrée en stock automatique + génération des comptes à payer) |
| Gestion des ventes | Devis→commande→expédition→retour→règlement (sortie de stock automatique + génération des comptes à recevoir) |
| Gestion des stocks | Stock en temps réel / mouvements / lots / transferts / inventaire / alertes (coût moyen pondéré mobile) |
| Gestion financière | Comptes à recevoir/à payer / pièces / encaissements-décaissements / journaux / grand livre / comptes auxiliaires / 3 états financiers / immobilisations / fiscalité / multi-devises / budgets |
| CRM | Opportunités/suivi/entonnoir/contacts/réserve de clients/contrats/devis/marketing/tickets/analyses |
| Workflow d'approbation | Définition du workflow/soumission/approbation/rejet/retrait/mes approbations |
| Notifications de messages | Liste des notifications/lues/tout marquer comme lu/compteur de non-lues |
| Gestion de projets | Projets/tâches/enregistrement des temps |
| Ressources humaines | Départements/employés/postes/pointage/congés/paie |
| Production | Nomenclature (BOM)/ordres de fabrication/gammes/postes de travail/MRP |
| Rapports personnalisés | Modèles de rapports/ensembles de données/champs/filtres/exécution/planification |
| Gestion des commandes OMS | Commandes multi-canaux/orchestration d'exécution/réservation de stock (ATP)/retours RMA/gestion des canaux |
| Gestion d'entrepôt WMS | Zones et emplacements (hiérarchie + codes-barres)/entrées (ASN→réception→mise en rayon)/sorties (vagues→préparation→emballage) |
| Gestion du transport TMS | Transporteurs/comparaison des tarifs/bons d'expédition/étiquettes/suivi logistique (webhook) |
| Gestion de la qualité QMS | Contrôles IQC/IPQC/OQC + normes de contrôle + traitement des non-conformités |
| Gestion des équipements EAM | Registre des équipements/plans de maintenance/bons de réparation/gestion des pièces de rechange |
| Gestion documentaire DMS | Catégories de documents/documents/gestion des versions |
| Tableaux de bord BI | Disposition des tableaux de bord/composants graphiques |

## Pile technique

### Backend
- PHP 8.3+, webman v2 (workerman/webman)
- Base de données : MySQL 8.0+, préfixe de table `erp_`
- Clé primaire : BIGINT non auto-incrémentée, générée par `erikwang2013/snowflake-php`
- Chiffrement des ID au niveau API : `erikwang2013/hashids`
- Authentification JWT : `erikwang2013/jwt-webman`
- Chiffrement des données sensibles API : `erikwang2013/encryption`
- Chiffrement des champs sensibles en base : `erikwang2013/encryptable`
- Synchronisation et recherche ES : `erikwang2013/webman-scout`
- Drapeaux des pays : `erikwang2013/season`
- Génération de la documentation API : `erikwang2013/apidoc-php` | par annotations, accès via /apidoc

### Frontend
- Flutter 3.x, répertoire source `apps/flutter/`
- Côté Web conçu au style console d'administration PC (et non style d'app mobile)
- Prise en charge des côtés client et administrateur
- HarmonyOS ArkTS, répertoire source `apps/harmonyos/`
- Angular 22 CLI + ng-zorro-antd, répertoire source `apps/angular/` (console d'administration Web)
- React 19 + Vite, répertoire source `apps/react/` (console d'administration Web)
- Quatre fronts sur un backend unique : Angular / React passent comme Flutter par `/admin/v1`, `/api/v1`, `/open/v1`, et en développement chaque dev server proxifie vers webman

### Internationalisation (13 langues)
- Liste des langues : `zh_CN` `en` `ja` `ko` `de` `fr` `es` `pt` `ru` `ar` `hi` `bn` `id`
- Dictionnaires backend : `resource/translations/<locale>/{common,modules,validation}.php`, 13 répertoires de langue ; `zh_CN` 565 entrées, les 11 autres langues 544 entrées chacune, `en` 30 entrées (périmètre : entrées feuilles des trois fichiers ; les libellés de champs `attributes` de `validation.php` sont comptés, leurs clés de groupe non)
  - « l'anglais est la clé » : les fichiers common/modules de `en` restent vides ; les clés de `validation.php` sont des noms de règles du framework, seules les valeurs sont traduites
  - Générateur : `scripts/gen-be-locales.mjs`
- Dictionnaires frontend (Angular) : source `apps/angular/src/app/core/zh-en/part1..4.ts` (1456 entrées) → produit `apps/angular/src/app/core/zh-<code>.ts`
- Dictionnaires frontend (React) : source `apps/react/src/lib/i18n/zhEn.ts` (1451 entrées) → produit `apps/react/src/lib/i18n/zh<Code>.ts`
  - Les dictionnaires des 11 nouvelles langues sont chacun chargés dynamiquement via `import()` dans un chunk distinct ; les entrées manquantes retombent sur le texte chinois d'origine
  - Générateur : `scripts/gen-fe-locales.mjs --app angular|react`
- À l'exécution : changer de langue revient à changer l'en-tête de requête `Accept-Language`, le backend renvoie les libellés selon la langue (`app/common/I18n.php` + `config/translation.php`)

## Structure du projet

```
open-erp/
├── app/
│   ├── admin/controller/       # Contrôleurs de gestion système (16)
│   │   ├── BaseController.php      # Contrôleur de base
│   │   ├── DashboardController.php # Tableau de bord + panneaux ventes/stocks/finances
│   │   ├── UserController.php      # CRUD utilisateurs + opérations en masse
│   │   ├── RoleController.php      # CRUD rôles
│   │   ├── PermissionController.php# CRUD permissions
│   │   ├── ConfigController.php    # CRUD configuration système
│   │   ├── LogController.php       # Consultation des journaux d'opérations
│   │   ├── ProfileController.php   # Espace personnel + déconnexion
│   │   ├── ExportController.php    # Export Excel/PDF
│   │   ├── ImportController.php    # Import Excel des utilisateurs
│   │   ├── UploadController.php    # Upload de fichiers
│   │   ├── HealthController.php    # Health check
│   │   ├── DocsController.php      # Documentation OpenAPI
│   │   └── MetricsController.php   # Métriques de surveillance Prometheus
│   ├── api/v1/controller/      # API client (version dans le chemin /api/v1, sans en-tête de version)
│   │   ├── CaptchaController.php   # Captcha à clic
│   │   ├── AuthController.php      # Connexion/inscription/rafraîchissement
│   │   └── ProductController.php   # Consultation produits (sans prix d'achat)
│   ├── controller/              # Contrôleurs des modules métier (139, dont InstallController / IndexController)
│   │   ├── product/             # Produits/catégories/marques/entrepôts/emplacements/fournisseurs/clients (8)
│   │   ├── purchase/            # Demandes d'achat/commandes/réceptions/retours/règlements (8)
│   │   ├── sales/               # Devis de vente/commandes/expéditions/retours/règlements (5)
│   │   ├── inventory/           # Stocks/mouvements/transferts/inventaires/alertes (6)
│   │   ├── finance/             # Comptes à recevoir/à payer/pièces/encaissements-décaissements/journaux/grand livre/comptes auxiliaires/3 états/immobilisations/fiscalité/multi-devises/budgets/centres de coûts et de profit (28)
│   │   ├── crm/                 # Opportunités/suivis/entonnoirs/contacts/réserve/​devis/​contrats/​marketing/​tickets/​analyses (10)
│   │   ├── workflow/            # Définition de workflow/soumission d'approbation/approbation/rejet/retrait (3)
│   │   ├── notification/        # Liste des notifications/lues/compteur de non-lues (2)
│   │   ├── project/             # Projets/tâches/enregistrement des temps (4)
│   │   ├── hr/                  # Départements/employés/postes/pointage/congés/paie (9)
│   │   ├── manufacturing/       # BOM/ordres de fabrication/gammes/postes de travail/MRP (13)
│   │   ├── report/              # Modèles de rapports/ensembles de données/exécution/planification (2)
│   │   ├── oms/                 # Commandes/exécution/réservation de stock/RMA/canaux (4)
│   │   ├── wms/                 # Zones et emplacements/réception ASN/mise en rayon/vagues/préparation/emballage (8)
│   │   ├── tms/                 # Transporteurs/tarifs/bons d'expédition/étiquettes/suivis (6)
│   │   ├── quality/             # IQC/IPQC/OQC/normes de contrôle/non-conformités (5)
│   │   ├── eam/                 # Équipements/plans de maintenance/bons de réparation/pièces de rechange (5)
│   │   ├── dms/                 # Catégories de documents/documents/versions (2)
│   │   ├── open/                # API ouverte (1)
│   │   ├── platform/            # Champs personnalisés/tenants (2)
│   │   ├── print/               # Modèle d'impression (1)
│   │   ├── retail/              # Coupons/membres (2)
│   │   └── bi/                  # Tableaux de bord BI/composants graphiques (3)
│   ├── service/                 # Couche logique métier (64 fichiers / 63 classes de service, dans le conteneur)
│   │   ├── finance/             # FinanceService : génération automatique des comptes à recevoir/à payer + lettrage des encaissements-décaissements + journaux
│   │   ├── inventory/           # InventoryService : entrées-sorties de stock + calcul du coût moyen pondéré mobile
│   │   ├── notification/        # NotificationService : envoi des notifications
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # Services commandes/entreposage/transport/contrôle qualité/RH/fabrication
│   ├── common/                  # Classes utilitaires communes (6)
│   │   ├── HashidsService.php   # Encodage/décodage des ID
│   │   ├── SnowflakeService.php # Génération des ID Snowflake
│   │   ├── EncryptionService.php# Chiffrement/déchiffrement des données + masquage
│   │   ├── I18n.php             # Traduction internationale
│   │   ├── CorsPolicy.php       # Politique CORS (appelée par middleware/Cors et route.php)
│   │   └── AddressValidator.php # Validation d'adresses (formats postaux multi-pays + champs de formulaire)
│   ├── middleware/              # Middlewares (11)
│   │   ├── Cors.php             # CORS
│   │   ├── SecurityFilter.php   # Interception XSS/injection SQL/traversée de chemins/injection de commandes/CSRF
│   │   ├── RateLimit.php        # Limitation de débit à fenêtre glissante Redis
│   │   ├── AdminAuth.php        # Authentification JWT + liste noire
│   │   ├── AdminPermission.php  # Validation des permissions RBAC
│   │   ├── OperationLog.php     # Enregistrement automatique des journaux d'opérations
│   │   ├── OpenApiAuth.php      # Authentification des interfaces ouvertes (X-API-Key + signature, monté uniquement sur /open/v1)
│   │   ├── TenantScope.php      # Isolation multi-tenant (réservée, non enregistrée, voir ARCHITECTURE.md §22)
│   │   ├── TracingId.php        # TraceId de bout en bout
│   │   ├── TrackingSignature.php# Validation de la signature de requête
│   │   └── StaticFile.php       # Service de fichiers statiques (intégré à webman)
│   ├── model/                   # Modèles de données (224 ; 225 fichiers avec le trait concerns/TenantScope)
│   ├── queue/                   # Tâches de file d'attente
│   └── process/                 # Processus (Http, WebSocket, QueueConsumer, Monitor)
├── apps/
│   ├── flutter/                 # Flutter toutes plateformes (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # Pages métier (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # Mises en page responsives
│   │       └── theme/          # Thème Material 3
│   ├── angular/                 # Angular 22 CLI + ng-zorro-antd, console d'administration Web
│   │   └── src/app/
│   │       ├── core/            # Services ApiService / AuthStore / I18n + dictionnaires de langue (source zh-en/, produits zh-<code>.ts)
│   │       └── config/ layout/ pages/ ui/
│   ├── react/                   # React 19 + Vite, console d'administration Web
│   │   └── src/
│   │       ├── lib/i18n/        # Dictionnaire source zhEn.ts + 11 langues zh<Code>.ts (chargement paresseux par langue)
│   │       └── components/ layout/ pages/ state/ config/domains/ styles/
│   └── harmonyos/              # Client HarmonyOS
├── config/                     # Fichiers de configuration
│   ├── route.php               # Routes + stratégie de version d'API
│   ├── middleware.php           # Enregistrement des middlewares globaux
│   ├── translation.php          # Configuration linguistique
│   └── plugin/erikwang2013/apidoc/ # Configuration de la documentation API (25 modules admin + 3 modules client)
├── database/
│   ├── install.sql              # SQL d'installation complet (227 tables + données de seed, toutes les migrations fusionnées)
│   ├── e2e-seed.sql             # Seed minimal E2E/CI
│   └── backup/                 # Scripts de sauvegarde de base de données
│       ├── backup.sh           # mysqldump+gzip, conservation 30 jours
│       └── restore.sh          # Restauration interactive
├── docs/                       # Documentation
│   ├── ARCHITECTURE.md         # Diagrammes d'architecture Mermaid
│   ├── DESIGN.md               # Document de conception
│   ├── FEATURE_DESIGN.md       # Document de conception fonctionnelle
│   ├── SECURITY.md             # Conception de l'architecture de sécurité
│   ├── API.md                  # Documentation de référence API
│   ├── nginx-security.conf     # Configuration de référence de sécurité Nginx
│   ├── diagrams/               # Diagrammes d'architecture décomposés
│   └── superpowers/            # Spécifications et plans
│       ├── specs/              # Spécifications de conception
│       └── plans/              # Plans d'implémentation
├── public/                     # Point d'entrée public
├── runtime/                    # Fichiers d'exécution
├── tests/                      # Tests
├── vendor/                     # Dépendances Composer
├── CLAUDE.md                   # Ce fichier
├── README.md                   # Documentation chinoise
├── README_EN.md                # Documentation anglaise
├── .env                        # Variables d'environnement (hors contrôle de version)
├── .env.example                # Modèle de variables d'environnement
├── .env.docker                 # Variables d'environnement Docker
├── composer.json               # Dépendances PHP
├── Dockerfile                  # Build Docker (extensions OPcache + event + redis)
├── docker-compose.yml          # Orchestration Docker
└── .github/
    └── workflows/
        └── ci.yml              # Pipeline CI/CD (syntaxe PHP+PHPStan+CS Fixer+PHPUnit+composer audit, matrice multi-versions)
```

## Chaîne d'exécution des middlewares

```
global :  Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → {middlewares de route}
/health : Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → Controller
/install: Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → Controller
/admin/v1 :  Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api/v1 :    Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → Controller
/open/v1 :   Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → OpenApiAuth → Controller
```

## Renforcements de sécurité

- **Limitation des méthodes HTTP** : SecurityFilter n'autorise que GET/POST/PUT/DELETE/OPTIONS/HEAD, les méthodes non standard renvoient 405
- **En-tête CSP** : Content-Security-Policy + X-Permitted-Cross-Domain-Policies injectés dans toutes les réponses
- **Verrouillage de compte** : après 5 échecs de connexion consécutifs, le compte est verrouillé 15 minutes
- **Limitation des sessions concurrentes** : 3 jetons valides au maximum par utilisateur, le plus ancien est mis en liste noire au-delà
- **security.txt** : point de terminaison `/.well-known/security.txt` conformément à la RFC 9116
- **Configuration de sécurité Nginx** : `docs/nginx-security.conf` référence de durcissement du proxy inverse

## Stratégie de version d'API

La version est placée dans le chemin d'URL (`/admin/v1`, `/api/v1`, `/open/v1`), sans en-tête de version :

```bash
curl http://localhost:8788/api/v1/auth/login
```

Pour ajouter une version, il suffit de créer le répertoire `app/api/{version}/controller/` et d'enregistrer le groupe `/api/v{version}` dans `config/route.php` (le numéro de version n'apparaît que dans le chemin d'URL, les contrôleurs y sont directement liés ; aucun middleware d'en-tête de version — l'ancien middleware `ApiVersion` a été supprimé).

## Stratégie de limitation de débit

Fenêtre glissante Redis (atomique Lua), par défaut 60 requêtes/minute/IP/route :
- Connexion : 10 requêtes/minute
- Inscription : 5 requêtes/minute
- En-têtes de réponse : `X-RateLimit-Limit/Remaining/Reset`, avec `Retry-After` en cas de dépassement

## Conventions de code

### PHP
- Les références de fonctions/classes globales ne sont pas préfixées de `\`, utiliser `use` pour les importer
- Les fichiers de configuration doivent contenir des commentaires chinois expliquant la signification de chaque option
- Tout nouveau fichier `.php` doit commencer par l'avis de droit d'auteur

### Base de données
- Préfixe de table : `erp_`
- Clé primaire `id` : type BIGINT, non auto-incrémentée, générée par snowflake
- Les champs sensibles utilisent le trait `erikwang2013/encryptable` pour le chiffrement/déchiffrement automatique
- Le schéma a `database/install.sql` comme unique source de vérité (SQL en un seul fichier)

### Flutter
- La mise en page Web utilise le style console d'administration PC (barre latérale + barre supérieure + zone de contenu)
- Gestion d'état GetX, singleton `ApiService` (Dio + intercepteur JWT)
- Persistance du jeton avec `shared_preferences`
- Points de rupture responsifs : mobile (< 768 px) et bureau (>= 768 px)

### HarmonyOS
- Utilisation du client HTTP natif `@ohos.net.http`
- Rafraîchissement transparent du jeton : en cas de 401, appel automatique de `/api/v1/auth/refresh`
- En cas d'échec du rafraîchissement, redirection automatique vers la page de connexion

## Dette technique connue

> La liste ci-dessous est mesurée par `grep -rn "new .*Service(" app/controller/` (45 occurrences) et correspond aux faits du code.
> **Pas de refactoring en P5** : instancier directement les services dans les contrôleurs est le mode existant ; seule la nouvelle base de code passe par l'injection de conteneur (`support\Container`), le code existant reste en l'état.

| Module | Services instanciés directement | Description |
|------|-----------|------|
| finance | 22 | comptes à recevoir/à payer, rapprochement, journaux, clôture, consolidation |
| wms | 9 | services de flux : réception, mise en rayon, vagues, préparation, emballage |
| tms | 5 | bons d'expédition, comparaison des tarifs, suivi, factures de fret |
| oms | 3 | exécution, réservation, RMA |
| quality | 2 | contrôle, traitement des non-conformités |
| hr | 2 | paie, pointage |
| platform | 1 | tenants |
| notification | 1 | canaux de notification |

## Déploiement

### Docker Compose (recommandé en production)

Le `docker-compose.yml` à la racine du projet orchestre 5 services :

| Service | Description |
|------|------|
| `nginx` | Proxy inverse Nginx (80/443), service de fichiers statiques |
| `app` | Application webman PHP 8.3, construite via `Dockerfile` (OPcache + event + redis) |
| `mysql` | MySQL 8.0, persistance des données par volume |
| `redis` | Redis 7 Alpine, cache/limitation de débit/Session |
| `elasticsearch` | Elasticsearch 8.x, recherche plein texte |

```bash
cp .env.docker .env
bash scripts/gen-env-keys.sh .env   # génère de vraies clés (les clés de remplacement sont refusées au démarrage)
docker-compose up -d
```

### CI/CD

`.github/workflows/ci.yml` définit le pipeline GitHub Actions (matrice PHP 8.2/8.3/8.4) :

- Contrôle de syntaxe PHP (`php -l`)
- Analyse statique PHPStan (`vendor/bin/phpstan analyse`)
- Contrôle du style de code PHP CS Fixer (`vendor/bin/php-cs-fixer fix --dry-run --diff`)
- Tests unitaires PHPUnit
- Audit de sécurité Composer (`composer audit --no-dev`)

### Sauvegarde de la base de données

`database/backup/backup.sh` — mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours.
`database/backup/restore.sh` — restauration interactive, liste des sauvegardes disponibles au choix.

### Surveillance

Le point de terminaison `GET /metrics` (`MetricsController`) renvoie le format texte Prometheus, avec 5 métriques gauge :
- `openadmin_http_requests_total` — nombre total de requêtes
- `openadmin_active_users` — nombre d'utilisateurs actifs
- `openadmin_db_connection_status` — état de la connexion base de données (0/1)
- `openadmin_redis_connection_status` — état de la connexion Redis (0/1)
- `openadmin_memory_usage_bytes` — utilisation de la mémoire
