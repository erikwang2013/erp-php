# Spécification de conception des modules métier ERP

```
Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
```

## 1. Aperçu

Sur la base de gestion système existante `service/`, étendre trois domaines métier — achats-ventes-stocks, finance et CRM — pour construire un système ERP complet.
Tout le code est déployé en monolithique sous `service/app/`, les modules sont organisés en couches par répertoire.

### 1.1 Planification par phases

| Phase | Modules | Description |
|------|------|------|
| Phase 1 | Données de base produits + achats + ventes + stocks + finance + CRM | Boucle métier principale |
| Phase 2 | Gestion de la production + gestion de projets | Extension ultérieure |

### 1.2 Pile technique (en continuité de l'existant)

- PHP 8.3+, webman v2, MySQL 8.0+
- Clés primaires BIGINT générées par snowflake-php
- ID chiffrés/déchiffrés au niveau API avec hashids
- Authentification JWT, chiffrement des données sensibles : tout via les paquets erikwang2013/*
- Préfixe de table `erik_`, suppression douce, fonctions globales sans `\`

---

## 2. Structure du projet

```
service/app/
├── admin/controller/          # Contrôleurs de gestion système (existants, inchangés)
├── api/v1/controller/         # API client (existants + extension)
├── common/                    # Outils partagés (Snowflake/Hashids/Encryption existants)
├── middleware/                # Middlewares globaux (7 existants)
├── model/                     # Tous les modèles de données (partagés entre modules)
├── service/                   # Couche logique métier (par répertoire de module)
│   ├── product/               # Produits et données de base
│   ├── purchase/              # Achats
│   ├── sales/                 # Ventes
│   ├── inventory/             # Stocks
│   ├── finance/               # Finance
│   └── crm/                   # CRM
├── controller/                # Contrôleurs des modules métier
│   ├── product/               # Données de base produits
│   ├── purchase/              # Achats
│   ├── sales/                 # Ventes
│   ├── inventory/             # Stocks
│   ├── finance/               # Finance
│   └── crm/                   # CRM
├── queue/                     # Tâches de file d'attente (existantes + files métier)
├── process/                   # Processus (Http, Monitor existants)
└── functions.php              # Fonctions d'assistance globales (existant)
```

### 2.1 Responsabilités par couche

| Couche | Emplacement des fichiers | Responsabilités |
|----|----------|------|
| Controller | `app/controller/{module}/` | Validation des paramètres, formatage des réponses, appel des Services |
| Service | `app/service/{module}/` | Logique métier, coordination inter-modules, gestion des transactions |
| Model | `app/model/` | Modèles de données, relations, scopes de requête, trait encryptable |

---

## 3. Liste des fonctionnalités des modules

### 3.1 Produits et données de base

| Fonctionnalité | Description |
|------|------|
| Fiche produit | Nom, code, code-barres, catégorie (arborescente), marque, attributs de spécification |
| SKU multi-spécifications | Un même produit en plusieurs spécifications, chacune avec son propre SKU, code-barres, prix |
| Conversion multi-unités | Taux de conversion unité de base ↔ unités auxiliaires |
| Stratégie de prix | Prix d'achat, prix de gros, prix de détail, prix par niveau client |
| Gestion des catégories | Arborescence de catégories illimitée, tri par glisser-déposer |
| Gestion des marques | CRUD des marques |
| Gestion des entrepôts | Multi-entrepôts, chaque entrepôt a plusieurs emplacements |
| Gestion des emplacements | Emplacements de stockage sous l'entrepôt, code unique |
| Fiche fournisseur | Nom, contact, téléphone, adresse, compte bancaire, taux de taxe |
| Fiche client | Nom, contact, téléphone, adresse, niveau client, limite de crédit |

### 3.2 Module achats

| Fonctionnalité | Description |
|------|------|
| Demande d'achat | Les départements/personnes soumettent des besoins d'achat, avec flux d'approbation |
| Commande d'achat | Basée sur la demande ou création directe, liée au fournisseur, aux produits, quantités, prix unitaires |
| Réception d'achat | Réception par commande, génération d'un bon d'entrée en stock, réception partielle prise en charge |
| Retour d'achat | Retour au fournisseur, génération d'un bon de sortie de stock en contre-passation |
| Rapprochement fournisseur | Agrégation par fournisseur + période : montant d'achat, payé, à payer |
| Règlement d'achat | Lettrage des réceptions d'achat et des paiements |

### 3.3 Module ventes

| Fonctionnalité | Description |
|------|------|
| Devis | Devis au client, prise en charge de la conversion en commande de vente |
| Commande de vente | Commande du client, liée aux produits, quantités, prix unitaires, remises |
| Expédition de vente | Expédition par commande, génération d'un bon de sortie de stock, expédition partielle prise en charge |
| Retour de vente | Retour du client, génération d'un bon d'entrée en stock en contre-passation |
| Rapprochement client | Agrégation par client + période : montant de vente, reçu, à recevoir |
| Règlement de vente | Lettrage des expéditions de vente et des encaissements |
| Marge brute de vente | Calcul de la marge brute par commande/produit/client |

### 3.4 Module stocks

| Fonctionnalité | Description |
|------|------|
| Stock en temps réel | Quantité de stock par entrepôt + emplacement + lot + SKU |
| Suivi des lots | Date de production, date de péremption, numéro de lot |
| Suivi des numéros de série | Numéro de série unique, enregistré aux entrées/sorties |
| Mouvements d'entrée/sortie | Journal unifié de toutes les variations de stock (numéro de document source + type + quantité + direction) |
| Transfert de stock | Transfert entre entrepôts/emplacements, génération de bons de sortie/entrée de transfert |
| Tâches d'inventaire | Inventaire planifié (par entrepôt/catégorie) + inventaire dynamique (par SKU) |
| Écarts d'inventaire | Excédent/rupture générant automatiquement des mouvements d'entrée/sortie |
| Alertes de stock | Seuils min/max par SKU + entrepôt, alerte sous le minimum ou au-dessus du maximum |
| Comptabilité des coûts | Méthode du coût moyen pondéré mobile, recalcul du prix de revient à chaque entrée |

### 3.5 Module finance

| Fonctionnalité | Description |
|------|------|
| Plan comptable | Arborescence de comptes (actif/passif/capitaux propres/revenus/charges), personnalisable |
| Comptes à recevoir/à payer | Générés automatiquement par les documents de vente/achat, lettrés manuellement |
| Bon d'encaissement | Encaissement multi-comptes, multi-moyens (espèces/banque/WeChat/Alipay) |
| Bon de décaissement | Décaissement multi-comptes, multi-moyens |
| Lettrage | Lettrage des encaissements sur les comptes à recevoir, des décaissements sur les comptes à payer |
| Journal de caisse/banque | Enregistrement des flux de trésorerie par compte + date |
| Remboursement de frais | Soumission → approbation → paiement, lié à un compte |
| Compte de résultat | Agrégation mensuelle des revenus/coûts/charges/profits |

### 3.6 Module CRM

| Fonctionnalité | Description |
|------|------|
| Gestion des clients | Fiches clients (liées au client des données de base) |
| Gestion des contacts | Plusieurs contacts sous un client |
| Enregistrements de suivi | Moyen de suivi, date, contenu, plan de suivi suivant |
| Entonnoir de vente | Configuration des étapes + estimation des montants d'opportunités + taux de conversion par étape |

---

## 4. Conception des tables de base de données

Toutes les tables ont le préfixe `erik_`, `id` BIGINT non auto-incrémenté, avec `created_at`/`updated_at`/`deleted_at`.

### 4.1 Données de base produits

```
erik_product              Table principale des produits
erik_product_sku         SKU/spécifications des produits
erik_product_unit        Conversion multi-unités
erik_product_price       Stratégie de prix
erik_category            Catégories de produits (arborescente parent_id)
erik_brand               Marques
erik_warehouse           Entrepôts
erik_location            Emplacements
erik_supplier            Fournisseurs
erik_customer            Clients
erik_customer_level      Niveaux clients
```

### 4.2 Module achats

```
erik_purchase_apply       Demande d'achat
erik_purchase_apply_item  Détails de la demande
erik_purchase_order       Commande d'achat
erik_purchase_order_item  Détails de la commande
erik_purchase_receive     Table principale de réception d'achat
erik_purchase_receive_item Détails de la réception
erik_purchase_return      Table principale de retour d'achat
erik_purchase_return_item Détails du retour
erik_purchase_settlement  Enregistrements de règlement fournisseur
```

### 4.3 Module ventes

```
erik_sales_quotation      Table principale des devis
erik_sales_quotation_item Détails du devis
erik_sales_order          Table principale des commandes de vente
erik_sales_order_item     Détails de la commande
erik_sales_delivery       Table principale des expéditions de vente
erik_sales_delivery_item  Détails de l'expédition
erik_sales_return         Table principale des retours de vente
erik_sales_return_item    Détails du retour
erik_sales_settlement     Enregistrements de règlement client
```

### 4.4 Module stocks

```
erik_inventory            Stock en temps réel
erik_inventory_batch      Informations de lot
erik_inventory_serial     Enregistrements de numéros de série
erik_inventory_flow       Mouvements d'entrée/sortie
erik_transfer             Table principale des transferts
erik_transfer_item        Détails du transfert
erik_check_task           Tâche d'inventaire
erik_check_detail         Détails de l'inventaire
erik_inventory_alert_rule Règles d'alerte de stock
erik_inventory_alert_log  Journal des alertes de stock
erik_cost_record          Enregistrements de calcul des coûts
```

### 4.5 Module finance

```
erik_finance_account      Plan comptable
erik_finance_voucher      Pièces comptables
erik_finance_voucher_item Écritures de pièce
erik_finance_ar_ap        Détails des comptes à recevoir/à payer
erik_finance_receipt      Bon d'encaissement
erik_finance_payment      Bon de décaissement
erik_finance_cash_journal Journal de caisse/banque
erik_finance_expense      Bon de remboursement de frais
erik_finance_expense_item Détails du remboursement
erik_finance_profit       Instantané du compte de résultat
erik_finance_bank_account Compte bancaire
```

### 4.6 Module CRM

```
erik_crm_funnel_stage     Configuration des étapes de l'entonnoir de vente
erik_crm_opportunity      Opportunités
erik_crm_follow_record    Enregistrements de suivi
erik_crm_contact          Contacts
```

---

## 5. Routes API

En continuité de l'espace de noms `/admin/*`, avec la chaîne complète de middlewares (Auth → Permission → OperationLog).

```
# Données de base produits
/admin/product/*          CRUD produits/catégories/marques
/admin/warehouse/*        CRUD entrepôts/emplacements
/admin/supplier/*         CRUD fournisseurs
/admin/customer/*         CRUD clients/niveaux clients

# Achats
/admin/purchase/apply/*      Demande d'achat + approbation
/admin/purchase/order/*      Commande d'achat
/admin/purchase/receive/*    Réception d'achat
/admin/purchase/return/*     Retour d'achat
/admin/purchase/settlement/* Règlement fournisseur

# Ventes
/admin/sales/quotation/*     Devis (avec conversion en commande)
/admin/sales/order/*         Commande de vente
/admin/sales/delivery/*      Expédition de vente
/admin/sales/return/*        Retour de vente
/admin/sales/settlement/*    Règlement client

# Stocks
/admin/inventory/*           Consultation du stock en temps réel
/admin/inventory/batch/*     Gestion des lots
/admin/inventory/serial/*    Gestion des numéros de série
/admin/inventory/flow/*      Mouvements d'entrée/sortie
/admin/inventory/transfer/*  Transferts
/admin/inventory/check/*     Inventaires
/admin/inventory/alert/*     Règles d'alerte

# Finance
/admin/finance/account/*     Plan comptable
/admin/finance/voucher/*     Pièces comptables
/admin/finance/receipt/*     Bons d'encaissement
/admin/finance/payment/*     Bons de décaissement
/admin/finance/cash/*        Journal de caisse/banque
/admin/finance/expense/*     Remboursements de frais
/admin/finance/report/*      États financiers

# CRM
/admin/crm/opportunity/*     Opportunités
/admin/crm/follow/*          Enregistrements de suivi
/admin/crm/funnel/*          Configuration des étapes de l'entonnoir
/admin/crm/contact/*         Contacts

# Tableau de bord (extension)
/admin/dashboard/sales       Panneau des ventes
/admin/dashboard/inventory   Panneau des stocks
/admin/dashboard/finance     Panneau des finances
```

L'API client `/api/v1/*` fournit des interfaces légères (consultation des produits, passation de commande, statut des commandes, etc.), pour les apps Flutter / HarmonyOS.

---

## 6. Flux de données inter-modules

```
Réception d'achat → inventory_flow (entrée) → inventory (+quantité) → cost_record (recalcul du prix moyen)
                  → finance_ar_ap (à payer)

Expédition de vente → inventory_flow (sortie) → inventory (-quantité) → cost_record (enregistrement du coût)
                    → finance_ar_ap (à recevoir)

Lettrage d'encaissement → finance_ar_ap (mise à jour du reçu) → cash_journal (enregistrement des revenus)
Lettrage de décaissement → finance_ar_ap (mise à jour du payé) → cash_journal (enregistrement des dépenses)

Écart d'inventaire → inventory_flow (entrée excédent / sortie rupture) → inventory (ajustement)

Remboursement de frais (payé) → finance_payment (génération automatique) → cash_journal (enregistrement des dépenses)
```

Mise en œuvre : après chaque opération métier, les actions en aval sont déclenchées par événements, sans appels directs de Services entre modules.

---

## 7. Export Excel/PDF

- Toutes les pages de liste prennent en charge le paramètre `?export=excel`, générant un fichier .xlsx stylé
- Les panneaux du tableau de bord prennent en charge `?export=pdf`, produisant des rapports PDF avec graphiques
- Les champs sensibles (montants, numéros de téléphone, etc.) sont masqués via EncryptionService à l'export
- Réutilisation de la classe de base ExportController existante ; chaque contrôleur de module hérite et implémente ses propres définitions de colonnes d'export

---

## 8. Panneaux du tableau de bord

| Panneau | Route | Indicateurs |
|------|------|------|
| Vue d'ensemble de l'activité | `/admin/dashboard` | Ventes/achats du jour et du mois, comptes à recevoir/à payer, valeur totale des stocks, marge brute |
| Tableau de bord des stocks | `/admin/dashboard/inventory` | Liste des alertes, tendances des entrées/sorties, taux d'occupation des emplacements |
| Tableau de bord des ventes | `/admin/dashboard/sales` | Courbes de tendance, classement des clients, produits les plus vendus, taux de conversion de l'entonnoir |
| Tableau de bord des finances | `/admin/dashboard/finance` | Tendances des revenus/dépenses, ancienneté des comptes à recevoir/à payer, flux de trésorerie |

Données mises en cache Redis 5 minutes, avec bascule de plage temporelle.

---

## 9. Conception frontend

| Plateforme | Répertoire | Framework | Style |
|----|------|------|------|
| Console d'administration Web | `apps/flutter/` (web) | Flutter + GetX | Console d'administration PC (barre latérale + barre supérieure + zone de contenu) |
| App client | `apps/flutter/` (app) | Flutter + GetX | Style natif mobile |
| HarmonyOS | `apps/harmonyos/` | ArkTS | Natif HarmonyOS, style App |

Le code Flutter distingue le rendu Web PC du mobile via les routes et la mise en page.

---

## 10. Ordre d'implémentation

| Étape | Contenu | Dépendances |
|------|------|------|
| 1 | SQL de migration de base de données (toutes les tables métier) | Aucune |
| 2 | Couche Model (modèles de données de tous les modules) | Étape 1 |
| 3 | Module données de base produits (CRUD) | Étape 2 |
| 4 | Module achats | Étape 3 |
| 5 | Module ventes | Étape 3 |
| 6 | Module stocks + comptabilité des coûts | Étapes 4, 5 |
| 7 | Module finance | Étapes 4, 5, 6 |
| 8 | Module CRM | Étape 3 |
| 9 | Panneaux du tableau de bord | Étapes 4-8 |
| 10 | Export Excel/PDF | Étapes 4-9 |
| 11 | API client (/api/*) | Étapes 4-8 |
| 12 | Pages frontend Flutter | Étapes 4-10 |
| 13 | Pages frontend HarmonyOS | Étape 11 |
