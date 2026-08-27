# Feuille de route complète de l'écosystème ERP — spécification de conception

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Établie sur la base du rapport d'audit de l'écosystème du 2026-08-04, couvrant les quatre phases de priorité P0 à P3

---

## 1. Base de référence actuelle

| Dimension | État actuel | Score |
|------|------|------|
| API backend | 14 modules / 80+ contrôleurs / 120+ modèles, squelettes CRUD multi-modules | 85/100 |
| Protection de sécurité | 18 couches de défense en profondeur, CORS/SecurityFilter/RateLimit/JWT/chiffrement | 95/100 |
| UI frontend | Flutter 12 pages, HarmonyOS 9 pages, couverture d'environ 20 % des modules ; panneau d'administration Web manquant | 20/100 |
| Écosystème d'exploitation | Dockerisé, CI terminé, manquent le rollback des migrations, l'automatisation des sauvegardes, l'observabilité | 70/100 |
| Profondeur métier | Tables des modules finance/RH/fabrication bien structurées mais logique métier essentiellement CRUD | 55/100 |
| **Global** | | **65/100** |

---

## 2. Stratégie globale

```
Cascade séquentielle : P0 → P1 → P2 → P3
Les sous-tâches indépendantes au sein de chaque phase peuvent avancer en parallèle
```

### 2.1 Choix technologiques frontend

- **Panneau d'administration Web** : Flutter Web, réutilisant le code existant de `apps/flutter`, style console d'administration PC, gestion d'état GetX
- **Mobile** : Flutter (iOS/Android), partageant le code métier `apps/flutter/lib/app/` avec le Web
- **HarmonyOS** : ArkTS, aligné sur l'ensemble de fonctionnalités Flutter

### 2.2 Stratégie backend

- **Niveau industriel** (classe A) : comptabilité en partie double, calcul de paie, moteur MRP — algorithmes complets, traitement des cas limites suffisant, prêt pour la production
- **Utilisable en cœur** (classe B) : gestion de la qualité, système de notifications, tableaux de bord BI — règles clés implémentées, itérations ultérieures selon les besoins

---

## 3. P0 — Écosystème frontend (3-4 semaines)

> **Objectif** : doter le système d'une interface d'administration utilisable, couvrant tous les modules backend déjà implémentés

### 3.1 Refactorisation de l'architecture du projet Flutter

```
apps/flutter/lib/app/
├── main.dart                      # Point d'entrée, initialisation GetX + Dio
├── routes/
│   └── app_pages.dart             # Enregistrement complet des routes (groupées par module)
├── layouts/
│   └── admin_layout.dart          # Mise en page PC à trois colonnes (barre latérale + barre supérieure + contenu)
├── theme/
│   └── app_theme.dart             # Thème Material 3 (couleur de marque #1677FF)
├── services/
│   ├── api_service.dart           # Singleton Dio + intercepteur JWT + rafraîchissement automatique
│   ├── auth_service.dart          # Gestion de l'état d'authentification
│   ├── captcha_service.dart       # Captcha à clic
│   └── export_service.dart        # Téléchargement d'export Excel/PDF
├── widgets/
│   ├── data_table_wrapper.dart    # Tableau de données générique (pagination/recherche/opérations en masse)
│   ├── form_dialog.dart           # Boîte de dialogue de formulaire générique
│   ├── confirm_dialog.dart        # Boîte de dialogue de double confirmation (saisie du mot de passe)
│   └── stat_card.dart             # Carte statistique
└── pages/
    ├── login/                     # Page de connexion
    ├── dashboard/                 # Tableau de bord (bascule entre 6 panneaux)
    ├── system/
    │   ├── user/                  # Gestion des utilisateurs (dont opérations en masse/import)
    │   ├── role/                  # Rôles + arbre de permissions
    │   ├── config/                # Configuration système
    │   └── log/                   # Journaux d'opérations
    ├── product/                   # Produits/catégories/marques/SKU
    ├── partner/                   # Fournisseurs/clients/entrepôts/emplacements
    ├── purchase/                  # Demandes d'achat/commandes/réceptions/retours/règlements
    ├── sales/                     # Devis de vente/commandes/expéditions/retours/règlements
    ├── inventory/                 # Stocks/mouvements/transferts/inventaires/alertes
    ├── finance/
    │   ├── voucher/               # Pièces comptables
    │   ├── ar_ap/                 # Comptes à recevoir/à payer
    │   ├── receipt_payment/       # Encaissements/décaissements
    │   ├── ledger/                # Grand livre/comptes auxiliaires
    │   ├── report/                # Trois états (résultat/bilan/flux de trésorerie)
    │   ├── asset/                 # Immobilisations
    │   ├── tax/                   # Fiscalité
    │   ├── currency/              # Multi-devises/taux de change
    │   ├── budget/                # Budgets
    │   └── cost_profit/           # Centres de coûts/profits
    ├── crm/
    │   ├── opportunity/           # Entonnoir d'opportunités
    │   ├── contact/               # Contacts
    │   ├── pool/                  # Réserve de clients
    │   ├── contract/              # Contrats
    │   ├── quotation/             # Devis
    │   ├── campaign/              # Campagnes marketing
    │   ├── ticket/                # Tickets de service
    │   └── analytics/             # Analyses clients
    ├── oms/                       # Commandes OMS/exécution/retours/canaux
    ├── wms/                       # Zones et emplacements WMS/réception/mise en rayon/vagues/préparation/emballage
    ├── tms/                       # Transporteurs TMS/tarifs/bons d'expédition/suivis/règlements
    ├── manufacturing/             # BOM/ordres de fabrication/gammes/postes de travail/MRP
    ├── hr/                        # Départements/employés/postes/pointage/congés/paie
    ├── project/                   # Projets/tâches/temps de travail
    ├── workflow/                  # Workflow d'approbation/mes approbations
    ├── notification/              # Centre de notifications
    ├── report/                    # Rapports personnalisés
    └── profile/                   # Espace personnel
```

### 3.2 Développement des composants génériques

| Composant | Fonctionnalité | Scénarios d'utilisation |
|------|------|----------|
| `DataTableWrapper` | Pagination/tri/recherche par mots-clés/filtrage par statut/sélection en masse/configuration des colonnes | Toutes les pages de liste |
| `FormDialog` | Rendu de formulaire dynamique/validation des champs/soumission/fermeture | Toutes les boîtes de dialogue de création/modification |
| `ConfirmDialog` | Saisie de double confirmation par mot de passe | Toutes les opérations de suppression |
| `StatCard` | Valeur/flèche de tendance/titre | Tableaux de bord |
| `BreadcrumbNav` | Fil d'Ariane | Pages profondes |
| `FileUploader` | Upload par glisser-déposer/progression/aperçu | Imports/téléversement d'images |

### 3.3 Complétion HarmonyOS

Aligner sur l'ensemble de pages Flutter, compléter : OMS/WMS/TMS/fabrication/RH/approbation/notification/rapports.

### 3.4 Critères d'acceptation P0

- [ ] Le panneau d'administration Flutter Web couvre les 14 modules complets
- [ ] Toutes les pages de liste CRUD utilisables (pagination/recherche/filtres)
- [ ] Tous les formulaires de création/modification utilisables (validation/soumission)
- [ ] Double confirmation par mot de passe pour les suppressions
- [ ] Rafraîchissement automatique JWT transparent
- [ ] Mise en page responsive PC/tablette/mobile adaptée
- [ ] Nombre de pages HarmonyOS ≥ 80 % du nombre de pages Flutter

---

## 4. P1 — Profondeur métier (4-6 semaines)

> **Objectif** : faire passer les modules cœur du squelette CRUD à de véritables moteurs de calcul métier

### 4.1 Moteur de comptabilité en partie double (niveau industriel)

```
app/service/finance/
├── DoubleEntryService.php        # Validation de l'équilibre débit/crédit + génération automatique des écritures
├── PeriodCloseService.php        # Clôture de période (report du résultat/des coûts)
├── AccountBalanceService.php     # Consolidation des soldes de comptes (mensuel/trimestriel/annuel)
├── ConsolidationService.php      # États consolidés multi-devises (conversion des taux de change)
└── FinancialRatioService.php     # Calcul automatique des ratios financiers

app/controller/finance/
├── PeriodCloseController.php     # Opérations de clôture de période
├── AccountBalanceController.php  # Consultation des soldes de comptes
└── FinancialRatioController.php  # Consultation des analyses de ratios
```

**Règles clés** :
- À l'enregistrement d'une pièce, application stricte « pas de débit sans crédit, débits toujours égaux aux crédits »
- Les pièces validées ne peuvent être modifiées, annulation par écriture rouge
- Clôture de période : soldes des comptes de charges/produits → bénéfice de l'exercice, prise en charge de la clôture en plusieurs étapes
- Multi-devises : conversion au taux de fin de période, calcul automatique des écarts de change

### 4.2 Moteur de calcul de paie (niveau industriel)

```
app/service/hr/
├── SalaryEngineService.php       # Moteur principal de calcul de paie
├── SocialInsuranceService.php    # Calcul des cotisations sociales (retraite/maladie/chômage/accidents du travail/maternité)
├── HousingFundService.php        # Calcul du fonds de logement
├── TaxCalculatorService.php      # Calcul de l'impôt sur le revenu à taux progressif
└── BankPayrollService.php        # Export des fichiers de paiement bancaire groupé

app/controller/hr/
└── PayrollController.php         # Calcul/émission/consultation de la paie
```

**Règles clés** :
- Plancher et plafond des assiettes de cotisations sociales (ajustés chaque année par les villes, configurables)
- Assiette du fonds de logement + taux de cotisation (5 %-12 %, configurable)
- Barème d'impôt progressif (3 %-45 %, déclaration annuelle de régularisation)
- Format de paiement bancaire : prise en charge des banques principales ICBC/BOC/CCB/CMB, etc.
- Génération des bulletins de paie (avec tous les détails)

### 4.3 Moteur MRP (niveau industriel)

```
app/service/manufacturing/
├── MrpEngineService.php           # Moteur principal de calcul MRP
├── DemandForecastService.php      # Consolidation des besoins (commandes + prévisions + stock de sécurité)
├── NetRequirementService.php      # Calcul des besoins nets (besoins bruts - stock disponible - stock en transit)
├── BomExplosionService.php        # Éclatement de la nomenclature (couche par couche jusqu'aux matières premières)
└── OrderSuggestionService.php     # Génération des commandes suggérées (achat/production/sous-traitance)

app/model/
├── MfgMrpRunLog.php              # Journal d'exécution MRP
└── MfgOrderSuggestion.php        # Commandes suggérées
```

**Règles clés** :
- Éclatement de la nomenclature couche par couche, en tenant compte du taux de perte
- Besoin net = besoin brut - stock disponible - stock en transit + quantités déjà allouées + stock de sécurité
- Code de niveau bas (LLC) garantissant qu'un même article n'est calculé qu'une seule fois
- Calcul à rebours de la date de commande suggérée à partir du délai
- Règles de lot : lot fixe/lot économique/à la demande

### 4.4 Gestion de la qualité (utilisable en cœur)

```
app/controller/quality/
├── InspectionStandardController.php  # Normes de contrôle
├── IncomingCheckController.php       # Contrôle à la réception IQC
├── ProcessCheckController.php        # Contrôle en cours de fabrication IPQC
├── FinalCheckController.php          # Contrôle à la sortie OQC
└── NonconformityController.php       # Traitement des produits non conformes

app/model/
├── QualityInspectionStandard.php
├── QualityIqcRecord.php
├── QualityIpcqRecord.php
├── QualityOqcRecord.php
└── QualityNonconformity.php
```

### 4.5 Système de notifications en temps réel (utilisable en cœur)

```
app/service/notification/
├── WebSocketService.php           # Gestion des connexions WebSocket + push
├── ChannelRouter.php              # Routage multi-canaux (interne/e-mail/WeCom/DingTalk)
├── TemplateRenderer.php           # Rendu des modèles de notification

app/process/
└── WebSocket.php                  # Processus WebSocket

app/controller/notification/
├── WebSocketController.php        # Traitement des événements WebSocket
└── ChannelConfigController.php    # Configuration des canaux de notification
```

**Règles clés** :
- WebSocket basé sur le protocole natif workerman
- Modèles de notification : remplacement à l'exécution des variables d'espace réservé `{order_code}`
- Priorité des canaux : interne → e-mail → WeCom → DingTalk, configurable

### 4.6 Critères d'acceptation P1

- [ ] À l'enregistrement d'une pièce, débits et crédits inégaux → retour d'erreur
- [ ] Les résultats du moteur de paie sont cohérents avec le calcul manuel (sondage sur 10 bulletins mensuels)
- [ ] Le calcul du besoin net MRP est cohérent avec le calcul manuel sous Excel
- [ ] Les trois documents de contrôle qualité (IQC/IPQC/OQC) circulent complètement
- [ ] Latence des notifications WebSocket < 2 secondes
- [ ] Tous les nouveaux services couverts par des tests PHPUnit (algorithmes clés ≥ 95 %)

---

## 5. P2 — Fiabilité d'exploitation (1-2 semaines)

> **Objectif** : capacités d'exploitation de niveau production

### 5.1 Rollback des migrations de base de données

```
database/migrations/
├── migrate.sh                    # Script de migration avant
└── rollback.sh                   # Script de rollback (exécution en ordre inverse des fichiers de migration)
```

Chaque fichier de migration reçoit un fichier `_rollback.sql` correspondant.

### 5.2 Renforcement de la sauvegarde et de la restauration

```
database/backup/
├── backup.sh                     # Existant
├── restore.sh                    # Existant
├── auto-backup.sh                # Nouveau : sauvegarde cron planifiée + alertes
└── backup-validator.sh           # Nouveau : validation de l'intégrité des fichiers de sauvegarde
```

### 5.3 Observabilité

```
app/service/observability/
├── TracerService.php             # Traçage OpenTelemetry
└── MetricCollector.php           # Collecte des métriques métier
```

- ID de trace au niveau requête (exposé via l'en-tête de réponse `X-Trace-Id`)
- Métriques métier clés : volume de commandes, taux d'exécution, rotation des stocks en jours

### 5.4 Mise à niveau de la file de messages

File Redis existante → prise en charge de RabbitMQ comme pilote optionnel :

```
config/queue.php                  # Configuration du pilote de file (redis/rabbitmq)
```

### 5.5 Critères d'acceptation P2

- [ ] Les scripts de rollback s'exécutent et la validation de l'intégrité des données réussit
- [ ] Le cron de sauvegarde automatique se déclenche correctement
- [ ] Le Trace ID traverse toute la chaîne de requêtes
- [ ] Le pilote RabbitMQ est commutable sans perte de messages

---

## 6. P3 — Amélioration de l'expérience (2-3 semaines)

> **Objectif** : fonctionnalités avancées et meilleure expérience utilisateur

### 6.1 Tableaux de bord BI

```
app/controller/bi/
├── DashboardController.php       # Tableaux de bord configurables
├── WidgetController.php          # CRUD des composants graphiques
└── DatasetController.php         # Gestion des ensembles de données

app/model/
├── BiDashboard.php
├── BiWidget.php
└── BiDataset.php
```

- Tableaux de bord à disposition par glisser-déposer
- Composants : graphiques à barres/linéaires/camemberts/cartes de données/tableaux
- Réutilisation du mécanisme d'ensembles de données de `app/controller/report/`

### 6.2 Gestion des équipements (EAM)

```
app/controller/eam/
├── EquipmentController.php       # Registre des équipements
├── MaintenancePlanController.php # Plans de maintenance
├── RepairOrderController.php     # Bons de réparation
└── SparePartController.php       # Gestion des pièces de rechange
```

### 6.3 Multi-tenant

```
app/middleware/TenantScope.php    # Middleware d'isolation des locataires
app/model/concerns/TenantScope.php # Trait Eloquent de portée des locataires
```

- Base de données partagée + isolation par `tenant_id`
- Vue multi-locataires pour le super administrateur

### 6.4 Gestion documentaire (DMS)

```
app/controller/dms/
├── DocumentController.php        # CRUD des documents + gestion des versions
├── CategoryController.php        # Catégories de documents
└── ApprovalController.php        # Approbation et publication des documents
```

### 6.5 Critères d'acceptation P3

- [ ] Les tableaux de bord BI ont une disposition personnalisable par glisser-déposer
- [ ] Boucle fermée registre des équipements → plan de maintenance → bon de réparation
- [ ] Le locataire A ne peut pas accéder aux données du locataire B
- [ ] L'historique des versions de documents est traçable

---

## 7. Récapitulatif des modifications du modèle de données

### Nouvelles tables P0

Aucune nouvelle table ; l'écosystème frontend n'implique pas de modification du schéma backend.

### Nouvelles tables P1

| Nom de table | Utilisation | Phase |
|------|------|------|
| `erp_finance_period_close` | Enregistrements de clôture de période | P1 |
| `erp_finance_account_balance` | Instantanés des soldes de comptes | P1 |
| `erp_hr_salary_config` | Configuration du calcul de paie | P1 |
| `erp_hr_social_insurance_config` | Configuration des assiettes de cotisations sociales | P1 |
| `erp_hr_housing_fund_config` | Configuration du fonds de logement | P1 |
| `erp_mfg_mrp_run_log` | Journaux d'exécution MRP | P1 |
| `erp_mfg_order_suggestion` | Commandes suggérées | P1 |
| `erp_quality_inspection_standard` | Normes de contrôle | P1 |
| `erp_quality_iqc_record` | Contrôle à la réception IQC | P1 |
| `erp_quality_ipqc_record` | Contrôle en cours de fabrication IPQC | P1 |
| `erp_quality_oqc_record` | Contrôle à la sortie OQC | P1 |
| `erp_quality_nonconformity` | Produits non conformes | P1 |
| `erp_notification_channel_config` | Configuration des canaux de notification | P1 |
| `erp_notification_template` | Modèles de notification | P1 |

### Nouvelles tables P3

| Nom de table | Utilisation | Phase |
|------|------|------|
| `erp_bi_dashboard` | Tableaux de bord BI | P3 |
| `erp_bi_widget` | Composants BI | P3 |
| `erp_eam_equipment` | Registre des équipements | P3 |
| `erp_eam_maintenance_plan` | Plans de maintenance | P3 |
| `erp_eam_repair_order` | Bons de réparation | P3 |
| `erp_dms_document` | Documents contrôlés | P3 |
| `erp_dms_document_version` | Versions de documents | P3 |

---

## 8. Récapitulatif des modifications de la couche service

| Service | Actuel | Modification P1 | Modification P2 | Modification P3 |
|------|------|---------|---------|---------|
| FinanceService | CRUD | Ajout de DoubleEntryService, PeriodCloseService, AccountBalanceService | — | — |
| Paie | Aucun | Ajout de SalaryEngineService, SocialInsuranceService, HousingFundService, TaxCalculatorService | — | — |
| Fabrication | CRUD | Ajout de MrpEngineService, BomExplosionService, NetRequirementService | — | — |
| Qualité | Aucun | Ajout de QmsInspectionService | — | — |
| Notifications | Basique | Ajout de WebSocketService, ChannelRouter | — | — |
| Observabilité | Processus Monitor | — | Ajout de TracerService, MetricCollector | — |
| BI | Aucun | — | — | Ajout de BiDashboardService |
| Équipements | Aucun | — | — | Ajout de EamService |

---

## 9. Modifications de la chaîne de middlewares

```
Actuel : Locale → Cors → SecurityFilter → RateLimit → {groupe de routes}

P0 : aucune modification
P1 : + WebSocketUpgrade (mise à niveau des connexions WebSocket sur le chemin /ws)
P2 : + TracingId (injection de X-Trace-Id)
P3 : + TenantScope (isolation multi-tenant)
```

---

## 10. Jalons et livrables

| Jalon | Date | Livrable |
|--------|------|--------|
| M0 — Base de référence actuelle | 2026-08-04 | Rapport d'audit `audit-report-2026-08-04.md` |
| M1 — P0 terminé | +3 semaines | Panneau d'administration Flutter Web tous modules |
| M2 — P1 terminé | +8 semaines | Moteur financier + moteur de paie + moteur MRP + qualité + notifications |
| M3 — P2 terminé | +10 semaines | Rollback des migrations + sauvegarde automatique + Trace + mise à niveau de la file |
| M4 — P3 terminé | +13 semaines | Tableaux de bord BI + gestion des équipements + multi-tenant + gestion documentaire |

---

## 11. Risques et mesures d'atténuation

| Risque | Impact | Mesure d'atténuation |
|------|------|----------|
| Les performances de Flutter Web sont inférieures au JS natif | Ralentissement des grands tableaux de données | Pagination côté client + défilement virtuel + Web Worker |
| Évolutions réglementaires du moteur de paie | Résultats de calcul non conformes | Cotisations sociales/taux d'impôt configurables, non codés en dur |
| Dépassement de délai du calcul MRP sur grands volumes | Interruption du calcul | Traitement par lots + rappel de progression |
| Nombre excessif de connexions WebSocket longues | Pression sur la mémoire du serveur | workerman naturellement haute concurrence + limitation du nombre de connexions |
| Omission de l'isolation multi-tenant | Fuite de données | Middleware global TenantScope + couverture par les tests |

---

## 12. Ce qui ne sera pas fait (exclusions explicites)

- ❌ Pas de découpage en microservices — l'architecture monolithique actuelle suffit, la complexité est regroupée dans la couche Service
- ❌ Pas d'introduction de Kubernetes — Docker Compose suffit pour l'échelle actuelle
- ❌ Pas de fonctionnalités IA/ML — hors de la feuille de route du MVP
- ❌ Pas de développement d'apps natives iOS/Android distinctes — Flutter multiplateforme couvre déjà le besoin
- ❌ Pas d'introduction de GraphQL — l'API RESTful suffit, la stratégie de version d'API est mature
- ❌ Pas de signature électronique/intégration matérielle WMS (PDA/lecteurs de codes-barres) — niveau logiciel pur
