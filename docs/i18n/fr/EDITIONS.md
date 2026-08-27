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
| Tables de données | 62 (valeur planifiée) | 72 (valeur planifiée) | 163 <!-- stats:tables=163 --> |
| Contrôleurs | 48 (valeur planifiée) | 42 (valeur planifiée) | 123 <!-- stats:controllers=122 --> |
| Modules métier | 6 (valeur planifiée) | 6 (valeur planifiée) | 19 <!-- stats:modules=19 --> |

> **Méthodologie** : le dépôt n'implémente actuellement que la version complète (Full) en une seule base de code ; les colonnes Lite/Standard sont des valeurs de planification produit (aucune branche correspondante dans le code) et ne participent pas à la validation doc-stats. Les chiffres de la colonne Full sont mesurés par `scripts/doc-stats.sh` (163 tables / 123 contrôleurs / 19 modules métier), conformément à l'annexe de `docs/FUNCTIONS.md`.

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
| Protection de sécurité sur 18 couches | ✔ | ✔ | ✔ |
| Internationalisation (i18n) bilingue chinois / anglais | — | — | ✔ |

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
| Documentation API (hg/apidoc) | ✔ | ✔ | ✔ |

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
| Full | 163 tables <!-- stats:tables=163 --> / 19 modules métier <!-- stats:modules=19 --> | Capacités complètes de plateforme d'entreprise |

---

## Stratégie de branches (à partir d'août 2026)

> Ce document correspond à la convention de branches de la version actuelle du dépôt, applicable aux trois branches `lite` / `standard` / `full`.

- **`main` est la seule source de développement** : tout développement de fonctionnalités, correctif de défauts et mise à niveau de dépendances est fusionné dans `main`.
- **Les branches de version ne reçoivent que des cherry-picks lors des sorties** : `lite` / `standard` / `full` ne servent plus de lignes de développement indépendantes pour les commits quotidiens ; à chaque sortie, un ingénieur de version cherry-picke depuis `main` les fonctionnalités correspondantes (ou effectue une fusion globale si nécessaire), et conserve l'intention de découpage propre à chaque branche (les différences de modules sont présentées dans le tableau de comparaison ci-dessus).
- **Principe de découpage** : une branche de version = un sous-ensemble de main. Lors de la fusion / migration du contenu de main, si un conflit porte sur la logique de découpage de la version (par exemple les différences de modules d'EDITIONS.md, le découpage des routes), l'intention de découpage de la branche est conservée ; pour le reste, la version de main fait foi.
- **Validation** : après fusion, la branche de version doit passer la vérification complète de syntaxe `php -l` ; les tests devenus inapplicables à cause du découpage peuvent être ignorés avec une raison enregistrée.
- **Publication** : la fusion / migration des branches de version est effectuée par l'ingénieur de version qui soumet le merge commit ; les commits de `main` sont exécutés par le Lead.
