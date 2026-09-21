# Comparaison des versions

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Les statistiques sont collectées en temps réel par `bash scripts/doc-stats.sh` et annotées dans les documents sous la forme `<!-- stats:key=value -->` ;
> le CI (job docs de `.github/workflows/ci.yml`) vérifie automatiquement la cohérence entre la documentation et le code — toute dérive fait passer au rouge.

Le système ERP Open est proposé en trois versions pour répondre aux besoins d'entreprises de tailles différentes.

---

## Vue d'ensemble des versions

| Dimension | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Branche | `lite` | `standard` | `full` |
| Tables de données | 62 (valeur planifiée) | 72 (valeur planifiée) | 227 <!-- stats:tables=227 --> |
| Contrôleurs | 48 (valeur planifiée) | 42 (valeur planifiée) | 159 <!-- stats:controllers=159 --> |
| Modules métier | 6 (valeur planifiée) | 6 (valeur planifiée) | 23 <!-- stats:modules=23 --> |

> **Méthodologie** : le dépôt n'implémente actuellement que la version complète (Full) en une seule base de code ; les colonnes Lite/Standard sont des valeurs de planification produit
> (aucune branche correspondante, voir « Stratégie de branches » ci-dessous) et ne participent pas à la validation doc-stats.
> Les chiffres de la colonne Full sont mesurés par `scripts/doc-stats.sh` (227 tables / 159 contrôleurs / 23 modules métier),
> conformément à l'annexe de `docs/FUNCTIONS.md`.
> **État des branches** (mesuré le 2026-09-22 via `git branch -a` + `git ls-remote --heads origin`) :
> le dépôt local comme le dépôt distant ne contiennent plus que la branche `main` ; les trois branches `lite` / `standard` / `full` ont été **supprimées**
> (le 2026-08-31, les trois branches coexistaient encore, toutes arrêtées sur le commit `eea90c0` du 2026-08-17, sans différence entre elles et avec 38 commits de retard sur `main`).
> Ce commit d'archivage reste dans l'historique de `main` (`git merge-base --is-ancestor eea90c0 main` est vrai),
> c'est-à-dire que les différences de version ne se tracent plus désormais que par les commits et les tags : le dépôt n'a plus aucune branche de version à checkout.

---

## Changements v1.17.0 (2026-09-15)

> Le positionnement des versions reste inchangé : le dépôt n'implémente toujours que la version complète (Full) en une seule base de code ; les colonnes Lite/Standard sont des valeurs de planification produit, leurs branches ayant été archivées puis gelées.

- **La console d'administration passe de deux à trois implémentations** : Angular 22 (`apps/angular/`) et React 19 + Vite (`apps/react/`) rejoignent
  le Flutter 3.x Web existant (`apps/flutter/`) ; les trois fronts partagent les mêmes interfaces `/admin/v1`, `/api/v1`, `/open/v1`.
- **13 langues sur toute la plateforme** (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id) :
  - Messages de réponse backend `resource/translations/<locale>/` — `zh_CN` 565 entrées, les 11 autres langues 544 entrées chacune, `en` 30 entrées
    (périmètre : entrées feuilles des trois fichiers, les libellés de champs `attributes` de `validation.php` sont comptés, leurs clés de groupe non — `zh_CN` en compte 21 de plus pour avoir traduit 21 libellés de champs. La ligne `en` suit la règle « l'anglais est la clé », son dictionnaire est quasi vide)
  - Interface des consoles d'administration : dictionnaire source Angular 1456 clés, React 1451 clés × 11 nouvelles langues ; **chargement paresseux par langue**, chaque langue formant un chunk distinct
  - Générateurs : `scripts/gen-be-locales.mjs` (backend), `scripts/gen-fe-locales.mjs` (frontend, `--app angular|react`)
  - Points de bascule : **icône globe dédiée** dans la barre supérieure + menu déroulant de l'espace personnel (identiques sur les deux fronts)
- **Flutter et HarmonyOS restent en chinois/anglais**, non inclus dans cette itération.
- **Impact sur le tableau ci-dessous** : colonne Full 163 tables / 122 contrôleurs / 19 modules métier → **227 / 159 / 23** ;
  la matrice d'achèvement gagne une ligne « multilingue (i18n) » (lignes de modules 44 → 45, API backend 39 → 40, logique métier 33 → 34) ;
  note : après fusion le 2026-09-15 de la ligne « multi-tenant » dupliquée dans la matrice, les lignes de modules redescendent à 44 (API backend 39, logique métier 33),
  la phrase ci-dessus reflète l'incrément tel qu'il était au moment de la v1.17.0 et est conservée telle quelle.

## Changements v1.4.0 (2026-09-05)

> Le positionnement des versions reste inchangé : le dépôt n'implémente toujours que la version complète (Full) en une seule base de code ; les colonnes Lite/Standard sont des valeurs de planification produit, leurs branches ayant été archivées puis gelées.

- **Versionnage des chemins sur tout le site** : `/admin/*` → `/admin/v1/*`, `/api/*` → `/api/v1/*`, `/open/*` → `/open/v1/*` ;
  seules exceptions `GET /api/docs` (documentation OpenAPI) et le webhook TMS ; l'authentification RBAC se fait sur le `method.path` privé du segment de version,
  aucune migration des données de rôles existantes (commit `3ee1430` ; le contrôle par en-tête de requête `API-Version` avait été retiré auparavant, commit `8276a1b`).
- **P0 multi-organisations et comptabilité analytique** : comptabilité séparée par organisation (Company/LedgerPeriod), moteur de consolidation (conversion aux taux de clôture + élimination intersociétés,
  instantané prioritairement stocké dans FinanceConsolidationReport), comptabilité des stocks et des coûts de production (sortie de matières + regroupement des coûts).
- **P1 exécution de fabrication et collaboration** : déclaration d'opérations/salaire à la pièce/sous-traitance entrante-sortante/charge de capacité/traçabilité par lots et numéros de série (M1/M2/M6/M3), contrôle du crédit (F7),
  canevas de workflow d'approbation (B3), modèles d'impression (B1), paie RH (H1/H2), pointage par scan des équipements (E1), coûts de projet (P1).
- **P2 différenciation et écosystème** : programme de fidélité (C1), registre des effets et rapprochement bancaire (F6), pool de factures d'achat et factures électroniques (F5, l'adaptation à l'administration fiscale réelle reste à faire),
  canaux multi-pilotes avec nouvelle tentative en cas d'échec (B4), champs personnalisés (B7), facturation à l'échéance multi-tenant (B5 — le middleware d'isolation des tenants n'est toujours pas enregistré, activation partielle),
  formation et protection sociale (H3/H4).
- **Matrice fonctionnelle** : sur 44 lignes de modules, 33 lignes ont un double ✅ ; 21 lignes sont annotées v1.4.0 (dont 1 partiellement activée), voir `docs/FUNCTIONS.md` §19.

> Le détail des changements se trouve dans le `CHANGELOG.md` à la racine du dépôt.

---

## Comparaison des fonctionnalités

### Administration système

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion des utilisateurs (CRUD + en masse + import) | ✔ | ✔ | ✔ |
| Rôles et permissions (arbre RBAC à trois niveaux) | ✔ | ✔ | ✔ |
| Configuration système (paires clé-valeur) | ✔ | ✔ | ✔ |
| Audit des opérations (détection de la source sur 8 plateformes) | ✔ | ✔ | ✔ |
| Téléversement de fichiers / export Excel / export PDF | ✔ | ✔ | ✔ |
| Vérification de santé / métriques Prometheus | ✔ | ✔ | ✔ |
| Authentification JWT + captcha à clic | ✔ | ✔ | ✔ |
| Protection de sécurité sur 7 couches | ✔ | ✔ | ✔ |
| Internationalisation (i18n) 13 langues (Angular/React ; Flutter/HarmonyOS restent chinois/anglais) | — | — | ✔ |

### Produits et données de base

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Fiche produit + SKU multi-spécifications | ✔ | ✔ | ✔ |
| Conversion multi-unités + stratégie de prix | ✔ | ✔ | ✔ |
| Catégories de produits (arborescentes) + marques | ✔ | ✔ | ✔ |
| Multi-entrepôts + multi-emplacements | ✔ | ✔ | ✔ |
| Fiches fournisseurs / clients | ✔ | ✔ | ✔ |

### Gestion des achats

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Demande d'achat + approbation | ✔ | ✔ | ✔ |
| Bon de commande d'achat | ✔ | ✔ | ✔ |
| Réception d'achat (entrée en stock automatique + génération des comptes fournisseurs) | ✔ | ✔ | ✔ |
| Retour d'achat | ✔ | ✔ | ✔ |
| Règlement fournisseur | ✔ | ✔ | ✔ |

### Gestion des ventes

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Devis (conversion en commande prise en charge) | ✔ | ✔ | ✔ |
| Commande de vente | ✔ | ✔ | ✔ |
| Expédition de vente (sortie de stock automatique + génération des comptes clients) | ✔ | ✔ | ✔ |
| Retour de vente | ✔ | ✔ | ✔ |
| Règlement client + analyse de la marge | ✔ | ✔ | ✔ |

### Gestion des stocks

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Stock en temps réel (précision à quatre dimensions) | ✔ | ✔ | ✔ |
| Flux d'entrées / sorties de stock | ✔ | ✔ | ✔ |
| Suivi par lots + suivi par numéros de série | ✔ | ✔ | ✔ |
| Transferts de stock | ✔ | ✔ | ✔ |
| Gestion des inventaires (planifiés + dynamiques) | ✔ | ✔ | ✔ |
| Alertes de stock (alerte de seuil haut/bas) | ✔ | ✔ | ✔ |
| Évaluation du coût moyen pondéré mobile | ✔ | ✔ | ✔ |

### Gestion financière

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Comptes à recevoir / à payer (génération automatique + rapprochement) | ✔ | ✔ | ✔ |
| Reçus d'encaissement / ordres de paiement | ✔ | ✔ | ✔ |
| Journal de caisse et de banque | ✔ | ✔ | ✔ |
| Notes de frais (soumission → approbation → paiement) | ✔ | ✔ | ✔ |
| Compte de résultat | ✔ | ✔ | ✔ |
| Amortissement des immobilisations | — | — | ✔ |
| Gestion fiscale (configuration multi-types de taxes) | — | — | ✔ |
| Multi-devises + gestion des taux de change | — | — | ✔ |
| Gestion budgétaire (comparaison budget vs réel) | — | — | ✔ |
| Centres de coût / centres de profit (calcul arborescent) | — | — | ✔ |

### CRM

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion des contacts clients | ✔ | ✔ | ✔ |
| Enregistrements de suivi | ✔ | ✔ | ✔ |
| Gestion des campagnes marketing | — | — | ✔ |
| Tickets de service (priorité + affectation + processus de résolution) | — | — | ✔ |
| Rapports d'analyse client | — | — | ✔ |

### Capacités de plateforme

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Moteur de workflow d'approbation | — | — | ✔ |
| Système de notifications | — | — | ✔ |
| Documentation API (erikwang2013/apidoc-php) | ✔ | ✔ | ✔ |

### Modules d'extension

| Fonctionnalité | Lite | Standard | Full |
|------|:---:|:---:|:---:|
| Gestion de projets (WBS / diagramme de Gantt / temps) | — | — | ✔ |
| Ressources humaines (organisation / pointage / salaires) | — | — | ✔ |
| Production (BOM / MRP / ordres / gammes) | — | — | ✔ |
| Constructeur de rapports personnalisés | — | — | ✔ |

---

## Cas d'utilisation

| Version | Scénarios recommandés |
|------|---------|
| **Lite** | Entreprises commerciales petites et moyennes, centrées sur les achats-stocks-ventes et la finance de base, sans besoin de workflow d'approbation ni de modules d'extension |
| **Standard** | Même périmètre fonctionnel, modèle de données allégé, adapté comme base de développement personnalisé |
| **Full** | Entreprises moyennes et grandes, nécessitant une plateforme full-stack complète : achats-stocks-ventes + finance + CRM + RH + production + gestion de projets |

---

## Chemin de mise à niveau

| Version | Taille (tables de données / modules métier) | Description |
|------|--------------------------|------|
| Lite | 62 tables / 6 modules métier (valeur planifiée) | Pas d'approbation / notifications / RH / production / rapports |
| Standard | 72 tables / 6 modules métier (valeur planifiée) | Modèle de données plus allégé |
| Full | 227 tables <!-- stats:tables=227 --> / 23 modules métier <!-- stats:modules=23 --> | Capacités complètes de plateforme d'entreprise |

---

## Stratégie de branches (à partir du 2026-08-27)

> S'appliquait aux trois branches de version `lite` / `standard` / `full`, en cohérence avec le job CI release (tags de version idempotents).
> **Complément d'état (mesuré le 2026-09-22)** : les trois branches ont été supprimées, les points restants de cette section s'entendent donc comme « archivage = commits et tags »,
> il n'existe plus aucune branche de version à checkout.

- **`main` est l'unique source de développement** : tout développement de fonctionnalités, correctif de défauts et mise à niveau de dépendances est fusionné dans `main`, les commits étant exécutés par le Lead.
- **Les branches de version ne sont qu'archivées, jamais maintenues** : `lite` / `standard` / `full` sont gelées comme branches d'archive historiques, ne reçoivent plus de nouveaux commits,
  ne sont plus synchronisées avec les incréments de `main`, et ne font l'objet ni de mise à jour forcée ni de push (pour éviter de maintenir trois lignes de code) ; **à la fin de la période de gel, les trois branches ont été supprimées**,
  leur contenu archivé subsistant dans l'historique de `main` au commit `eea90c0`.
- **Les différences de version sont enregistrées par des tags** : la publication est assurée par le job CI release qui crée de façon idempotente le tag `vX.Y.Z` courant
  (voir `scripts/bump-version.sh`) ; les différences fonctionnelles entre versions se lisent dans les tags et le tableau de comparaison fonctionnelle ci-dessus, et non dans des lignes de code de branches maintenues.
- **Validation** : le CI de `main` fait office de validation de publication, les branches archivées ne lancent plus de CI dédiée. (Depuis le 2026-09-15, le job release dépend de `docs` + `e2e` ; le job php continue de s'exécuter mais ne bloque pas la publication — ses points rouges relèvent d'une dette historique de tests d'intégration propre au CI, voir les commentaires de `.github/workflows/ci.yml`.)
