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

**Score global actuel 89/100** — la feuille de route complète P0~P3 est terminée, couverture full-stack des 22 modules, prêt pour la production.

| Phase | Durée | Livrables | Statut |
|------|------|--------|------|
| 🔵 **P0** Écosystème frontend | 3-4 semaines | 97 pages Flutter + 34 pages HarmonyOS + 4 composants communs | ✅ |
| 🟢 **P1** Profondeur métier | 4-6 semaines | Moteur financier + moteur de paie + MRP + QMS + WebSocket | ✅ |
| 🟡 **P2** Fiabilité d'exploitation | 1-2 semaines | Migration/rollback + sauvegarde automatique + TraceId + double pilote de file d'attente | ✅ |
| 🟣 **P3** Amélioration de l'expérience | 2-3 semaines | Tableaux de bord BI + EAM + multi-tenant + DMS + 7 nouvelles tables | ✅ |

**Tests** : 513 tests, 2368 assertions (32 skipped) — ALL PASSING. **Flutter** : 0 error, 0 warning.

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
| Sécurité | 18 couches de défense en profondeur (XSS/injection SQL/CSRF/limitation de débit/CSP...) |
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
- Génération de la documentation API : `hg/apidoc` | par annotations, accès via /apidoc

### Frontend
- Flutter 3.x, répertoire source `apps/flutter/`
- Côté Web conçu au style console d'administration PC (et non style d'app mobile)
- Prise en charge des côtés client et administrateur
- HarmonyOS ArkTS, répertoire source `apps/harmonyos/`

## Structure du projet

```
open-erp/
├── app/
│   ├── admin/controller/       # Contrôleurs de gestion système (14)
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
│   ├── api/v1/controller/      # API client (contrôle par en-tête de version)
│   │   ├── CaptchaController.php   # Captcha à clic
│   │   ├── AuthController.php      # Connexion/inscription/rafraîchissement
│   │   └── ProductController.php   # Consultation produits (sans prix d'achat)
│   ├── controller/              # Contrôleurs des modules métier (104, dont InstallController)
│   │   ├── product/             # Produits/catégories/marques/entrepôts/emplacements/fournisseurs/clients (7)
│   │   ├── purchase/            # Demandes d'achat/commandes/réceptions/retours/règlements (5)
│   │   ├── sales/               # Devis de vente/commandes/expéditions/retours/règlements (5)
│   │   ├── inventory/           # Stocks/mouvements/transferts/inventaires/alertes (5)
│   │   ├── finance/             # Comptes à recevoir/à payer/pièces/encaissements-décaissements/journaux/grand livre/comptes auxiliaires/3 états/immobilisations/fiscalité/multi-devises/budgets/centres de coûts et de profit (20)
│   │   ├── crm/                 # Opportunités/suivis/entonnoirs/contacts/réserve/​devis/​contrats/​marketing/​tickets/​analyses (10)
│   │   ├── workflow/            # Définition de workflow/soumission d'approbation/approbation/rejet/retrait (2)
│   │   ├── notification/        # Liste des notifications/lues/compteur de non-lues (1)
│   │   ├── project/             # Projets/tâches/enregistrement des temps (3)
│   │   ├── hr/                  # Départements/employés/postes/pointage/congés/paie (5)
│   │   ├── manufacturing/       # BOM/ordres de fabrication/gammes/postes de travail/MRP (5)
│   │   ├── report/              # Modèles de rapports/ensembles de données/exécution/planification (2)
│   │   ├── oms/                 # Commandes/exécution/réservation de stock/RMA/canaux (4)
│   │   ├── wms/                 # Zones et emplacements/réception ASN/mise en rayon/vagues/préparation/emballage (8)
│   │   ├── tms/                 # Transporteurs/tarifs/bons d'expédition/étiquettes/suivis (6)
│   │   ├── quality/             # IQC/IPQC/OQC/normes de contrôle/non-conformités (5)
│   │   ├── eam/                 # Équipements/plans de maintenance/bons de réparation/pièces de rechange (4)
│   │   ├── dms/                 # Catégories de documents/documents/versions (2)
│   │   └── bi/                  # Tableaux de bord BI/composants graphiques (3)
│   ├── service/                 # Couche logique métier (enregistrée dans le conteneur, 24)
│   │   ├── finance/             # FinanceService : génération automatique des comptes à recevoir/à payer + lettrage des encaissements-décaissements + journaux
│   │   ├── inventory/           # InventoryService : entrées-sorties de stock + calcul du coût moyen pondéré mobile
│   │   ├── notification/        # NotificationService : envoi des notifications
│   │   └── oms/ wms/ tms/ quality/ hr/ manufacturing/  # Services commandes/entreposage/transport/contrôle qualité/RH/fabrication
│   ├── common/                  # Classes utilitaires communes (enregistrées dans le conteneur, 4)
│   │   ├── HashidsService.php   # Encodage/décodage des ID
│   │   ├── SnowflakeService.php # Génération des ID Snowflake
│   │   ├── EncryptionService.php# Chiffrement/déchiffrement des données + masquage
│   │   └── I18n.php             # Traduction internationale
│   ├── middleware/              # Middlewares (12)
│   │   ├── Locale.php           # Détection automatique de la langue Accept-Language
│   │   ├── Cors.php             # CORS
│   │   ├── SecurityFilter.php   # Interception XSS/injection SQL/traversée de chemins/injection de commandes/CSRF
│   │   ├── RateLimit.php        # Limitation de débit à fenêtre glissante Redis
│   │   ├── ApiVersion.php       # Validation de la version d'API
│   │   ├── AdminAuth.php        # Authentification JWT + liste noire
│   │   ├── AdminPermission.php  # Validation des permissions RBAC
│   │   ├── OperationLog.php     # Enregistrement automatique des journaux d'opérations
│   │   ├── TenantScope.php      # Isolation multi-tenant (appel statique)
│   │   ├── TracingId.php        # TraceId de bout en bout
│   │   ├── TrackingSignature.php# Validation de la signature de requête
│   │   └── StaticFile.php       # Service de fichiers statiques (intégré à webman)
│   ├── model/                   # Modèles de données (161)
│   ├── queue/                   # Tâches de file d'attente
│   └── process/                 # Processus (Http, Monitor)
├── apps/
│   ├── flutter/                 # Flutter toutes plateformes (Web/iOS/Android/macOS/Windows/Linux)
│   │   └── lib/app/
│   │       ├── pages/           # Pages métier (dashboard/login/user/role/config/log/profile + ERP)
│   │       ├── services/        # ApiService + AuthService + CaptchaService + ExportService
│   │       ├── layouts/        # Mises en page responsives
│   │       └── theme/          # Thème Material 3
│   └── harmonyos/              # Client HarmonyOS
├── config/                     # Fichiers de configuration
│   ├── route.php               # Routes + stratégie de version d'API
│   ├── middleware.php           # Enregistrement des middlewares globaux
│   ├── translation.php          # Configuration linguistique
│   └── plugin/hg/apidoc/        # Configuration de la documentation API (25 modules admin + 3 modules client)
├── database/
│   ├── install.sql              # SQL d'installation complet (163 tables + données de seed, toutes les migrations fusionnées)
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
global :  Locale → Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → {middlewares de route}
/health : Locale → Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → Controller
/install: Locale → Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → Controller
/admin :  Locale → Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → AdminAuth → AdminPermission → OperationLog → Controller
/api :    Locale → Cors → SecurityFilter(contrôle de méthode→405) → RateLimit → TracingId → ApiVersion → Controller
```

## Renforcements de sécurité

- **Limitation des méthodes HTTP** : SecurityFilter n'autorise que GET/POST/PUT/DELETE/OPTIONS/HEAD, les méthodes non standard renvoient 405
- **En-tête CSP** : Content-Security-Policy + X-Permitted-Cross-Domain-Policies injectés dans toutes les réponses
- **Verrouillage de compte** : après 5 échecs de connexion consécutifs, le compte est verrouillé 15 minutes
- **Limitation des sessions concurrentes** : 3 jetons valides au maximum par utilisateur, le plus ancien est mis en liste noire au-delà
- **security.txt** : point de terminaison `/.well-known/security.txt` conformément à la RFC 9116
- **Configuration de sécurité Nginx** : `docs/nginx-security.conf` référence de durcissement du proxy inverse

## Stratégie de version d'API

La version est contrôlée par l'en-tête de requête `API-Version` (par défaut `v1`), et n'apparaît pas dans l'URL :

```bash
curl -H "API-Version: v1" http://localhost:8787/api/auth/login
```

Pour ajouter une version, il suffit de créer le répertoire `app/api/{version}/controller/` et de l'enregistrer dans le middleware `ApiVersion`.

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
- Rafraîchissement transparent du jeton : en cas de 401, appel automatique de `/api/auth/refresh`
- En cas d'échec du rafraîchissement, redirection automatique vers la page de connexion

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
