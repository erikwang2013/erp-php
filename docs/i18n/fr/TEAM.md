# Organisation de l'équipe (équipe de collaboration IA)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Ce document définit l'équipe de collaboration IA de ce projet : composition des rôles, limites des responsabilités, modes de collaboration et routage des tâches.
> Les règles de coordination associées (SendMessage-First, nommage des agents, cycle de vie) figurent dans `CLAUDE.md` à la racine ; les définitions des rôles dans `.claude/agents/`.

---

## 1. Profil du projet (base de la planification)

| Dimension | État actuel | Implications pour l'équipe |
|------|------|--------------|
| Backend | webman (Workerman) PHP 8.3+, **23 modules métier**, 159 contrôleurs, 63 services, 224 modèles, 227 tables, 11 middlewares (le schéma a `database/install.sql` comme source unique de vérité) | Monolithe grand et complet, réparti par domaines métier pour éviter l'explosion de contexte d'un agent unique |
| Frontend | Flutter **102 routes de menu** (`lib/app/config/menu_config.dart` ; les `getPages` de `main.dart` enregistrent 110 entrées au total = 102 menus + connexion / espace personnel / 6 pages de détail) + HarmonyOS **41 pages** (`main_pages.json`), couvrant tous les modules | Maintenance parallèle des deux plateformes, nécessite des rôles frontend dédiés |
| Ligne de base qualité | PHPUnit 1037 tests / 4962 assertions, PHPStan + baseline, CS-Fixer, matrice multi-versions CI | La discipline existe déjà ; les rôles de test / revue sont directement intégrés au pipeline |
| Matrice de versions | Une seule branche `main` (`lite` / `standard` / `full` supprimées, le commit d'archivage `eea90c0` reste dans l'historique de `main`) | Aucune branche de version à synchroniser, les différences de version se tracent par tag, voir `docs/EDITIONS.md` « Stratégie de branches » |
| Feuille de route | P0~P3 livrés (score global 89/100), entrée dans l'itération quotidienne et la phase d'évolution | Taille de l'équipe adaptée au type de tâche, pas de grand effectif de type projet |
| Infrastructures existantes | `.claude/agents/` (planner / sparc / testing / swarm / consensus), `.claude-flow` (hierarchical-mesh, max 15 agents, coordination consensus), hooks + mémoire | L'équipe se monte directement sur la configuration existante, sans repartir de zéro |

---

## 2. Composition de l'équipe

### 2.1 Équipe noyau (permanente, 5 rôles)

| Rôle | Agent existant correspondant | Responsabilités (pour ce projet) |
|------|-----------------|--------------------|
| **Chef de projet Lead** | `planner` / `swarm/hierarchical-coordinator` | Décomposition des besoins → routage → recette ; maintien de la file de tâches des 23 modules ; décision pipeline / fan-out / supervisor ; relais de messages entre rôles |
| **Architecte système** | `sparc/architecture` | Conception des structures de tables (227 tables, schéma avec `database/install.sql` comme source unique de vérité) ; flux de données inter-modules (réception d'achat → stocks → comptes à payer, expédition de vente → comptes à recevoir → sortie de stock, etc.) ; décisions de limites de découpage microservices |
| **Développeur backend** | `core` / `backend-dev` personnalisé | Implémentation des contrôleurs / services / modèles ; respect de la stratification `app/service` et de la chaîne de middlewares (Cors→SecurityFilter→RateLimit→TracingId→middlewares métier) |
| **Ingénieur de test** | `testing/tdd-london-swarm` + `production-validator` | Cas PHPUnit d'abord (tests de limites des moteurs) ; validation de régression sur la ligne unique `main` ; complétion des lacunes de couverture `tests/` |
| **Réviseur de code** | `consensus/security-manager` | Zéro nouvelle entrée PHPStan baseline, conformité CS-Fixer, contrôle du schéma de sécurité sur 7 couches ; gardien de la porte qualité avant soumission |

### 2.2 Équipe spécialisée (mobilisée selon le type de tâche, 4 rôles)

| Rôle | Agent existant correspondant | Scénario d'activation | Tâches typiques |
|------|-----------------|----------|----------|
| **Expert en moteurs métier** | `business-engineer` personnalisé | Modules algorithmiques : finance / salaires / MRP | Renforcement algorithmique et traitement des cas limites des moteurs de comptabilité en partie double, de calcul des salaires, de MRP (exigence « niveau industriel » de la classe A) |
| **Ingénieur frontend (Flutter)** | `frontend-flutter` personnalisé | Toute modification impliquant `apps/flutter/` | Pages de la console Web, état GetX, interconnexion ApiService/export, maintenance des 102 routes |
| **Ingénieur frontend (HarmonyOS)** | `frontend-harmonyos` personnalisé | Toute modification impliquant `apps/harmonyos/` | Pages ArkTS, rafraîchissement transparent du jeton, alignement fonctionnel avec Flutter (maintenance des 41 pages) |
| **Ingénieur sécurité / DevOps** | `consensus/security-manager` + `performance-benchmarker` | Durcissement de sécurité, performance, déploiement | Régression des 7 couches de protection, sous-services Docker/gRPC, migration / rollback, observabilité, métriques Prometheus |

### 2.3 Rôles à la demande (déclenchés par les tâches, 2 rôles)

| Rôle | Agent existant correspondant | Condition d'activation |
|------|-----------------|----------|
| **Chercheur** | `researcher` personnalisé | Avant la conception d'un nouveau module / d'une nouvelle fonctionnalité : étude des concurrents, comparaison de `docs/API.md`, `docs/FUNCTIONS.md` avec l'implémentation, production des entrées de conception |
| **Coordinateur de versions** | `edition-coordinator` personnalisé | Toute modification de la matrice de versions : validation du tableau comparatif `docs/EDITIONS.md` (les colonnes `lite`/`standard` sont des valeurs de planification, sans branche correspondante), cohérence des tags de version et des notes de publication |

---

## 3. Modes de collaboration

### 3.1 Règles générales (en application du CLAUDE.md racine)

- **SendMessage-First** : les agents communiquent directement via SendMessage, sans polling, sans état mutable partagé ;
- **Nommage obligatoire** : chaque agent doit être nommé (`name: "role"`) ;
- **Spawn unique** : les sous-tâches indépendantes sont lancées en arrière-plan en une seule fois, le Lead s'arrête et attend les résultats, sans poller l'état ;
- **Message obligatoire** : chaque prompt précise « à qui SendMessage une fois terminé, et quoi envoyer ».

### 3.2 Trois topologies d'orchestration

| Mode | Flux | Cas d'utilisation |
|------|------|----------|
| **Pipeline** | Lead → architecte → backend → test → revue | Développement de fonctionnalités à dépendances séquentielles (nouveau module, flux de données inter-modules) |
| **Fan-out** | Lead → A, B, C → Lead consolide | Travaux parallèles indépendants (multi-pages, études multi-modules) |
| **Supervisor** | Lead ↔ membres, allers-retours multiples | Travaux complexes à coordination continue (découpage microservices, refonte à grande échelle) |

### 3.3 Table de routage des tâches

| Type de tâche | Orchestration | Rôles participants |
|----------|------|----------|
| Nouveau module / nouvelle fonctionnalité (par exemple approfondissement DMS, BI) | pipeline | Lead → architecte (conception des tables) → backend → test → revue |
| Algorithme de niveau moteur (partie double / salaires / MRP) | pipeline + TDD | Lead → expert en moteurs métier (conception) → test (cas limites d'abord) → revue |
| Pages frontend (Flutter / HarmonyOS en parallèle) | fan-out | Lead → frontend ×2 + backend (alignement API) en parallèle → Lead consolide |
| Flux de données inter-modules (achats→stocks→comptes à payer, etc.) | pipeline | Lead → architecte → backend → test → revue |
| Découpage microservices / refonte à grande échelle | supervisor | Lead ↔ architecte + backend + revue, plusieurs allers-retours |
| Projet sécurité / performance | approfondissement mono-thread | Lead → ingénieur sécurité / DevOps → revue |
| Correctif de bug (fichier unique / 1-2 lignes) | hors équipe | Traité directement par le Lead, ou par 1 agent |
| Différences de tag de version / publication de version | pipeline | Lead → coordinateur de versions → test (régression sur la ligne unique `main`) → revue |

### 3.4 Porte qualité (obligatoire avant soumission, gardée par le réviseur)

```
phpunit            # 1037 tests / 4962 assertions au vert, les nouveaux cas sont livrés avec la modification
phpstan            # aucune nouvelle remontée hors baseline autorisée
php-cs-fixer       # --dry-run passé
composer audit     # aucune vulnérabilité de dépendance à haut risque
```

Toute modification touchant la base de données doit passer par l'architecte (227 tables, schéma avec `database/install.sql` comme source unique de vérité) ; toute modification frontend doit passer le `flutter analyze` à 0 error / 0 warning.

---

## 4. Taille d'équipe recommandée

| Mode de travail | Taille recommandée | Description |
|----------|----------|------|
| Maintenance quotidienne / petites corrections | 1-2 personnes | Traitement direct par le Lead, éviter la sur-orchestration |
| Itération sur module unique | 3 personnes | Lead + backend + test |
| Fonctionnalité inter-modules | 4-5 personnes | Lead + architecte + backend + test + revue |
| Frontend double plateforme en parallèle | 4-5 personnes | Lead + Flutter + HarmonyOS + backend (API) + test |
| Moteur / refonte complexe | 5-7 personnes | Le tout ci-dessus + expert en moteurs métier ou sécurité/DevOps |

> Compatible avec `.claude-flow/config.yaml` (`maxAgents: 15`, `hierarchical-mesh`, stratégie de coordination `consensus`) ; l'occupation d'une tâche unique ne dépasse pas le plafond.

---

## 5. Étapes de mise en œuvre

1. **Compléter les définitions de rôles** : `.claude/agents/` contient déjà planner / sparc / testing / swarm / consensus ; il manque cinq définitions : `business-engineer`, `frontend-flutter`, `frontend-harmonyos`, `researcher`, `edition-coordinator` ; un fichier chacun au format YAML/MD existant suffit pour le montage ;
2. **Figer le routage** : écrire la table de routage §3.3 dans la logique de routage des hooks `.claude-flow/hooks`, pour que le hook `UserPromptSubmit` route automatiquement les tâches vers le rôle correspondant ;
3. **Mémoire par domaine** : `.claude-flow` a déjà activé `agentScopes` (`defaultScope: project`) ; archiver par domaines `backend / frontend / ops / security` recommandé, pour éviter que le contexte du moteur financier ne pollue les tâches frontend ;
4. **Essai pilote** : choisir une tâche inter-modules (par exemple l'approfondissement DMS ou l'itération des tableaux de bord BI), la router complètement selon §3.3, valider la chaîne de messages et la porte qualité avant généralisation.

---

## 6. Journal des modifications

| Date | Changements |
|------|------|
| 2026-08-07 | Version initiale : sur la base de l'état des 22 modules (P0~P3 livrés, 89/100), équipe noyau 5 + spécialisée 4 + à la demande 2 |
