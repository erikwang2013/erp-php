# Système ERP Open — Manuel des fonctionnalités

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Présentation

Le système ERP Open (open-erp) couvre 23 domaines métier <!-- stats:modules=23 --> et 227 tables de données <!-- stats:tables=227 -->, et fournit un système de gestion d'entreprise full-stack, des achats-stocks-ventes à la production, de la comptabilité aux ressources humaines. Internationalisation : 13 langues (中文/English/日本語/한국어/Deutsch/Français/Español/Português/Русский/العربية/हिन्दी/বাংলা/Bahasa Indonesia), bascule automatique via l'en-tête de requête Accept-Language.

> Documentation API : après le démarrage du service, accédez à `http://localhost:8788/apidoc` pour consulter la documentation interactive des interfaces (générée automatiquement par erikwang2013/apidoc-php)

---

## 1. Administration système

### 1.1 Gestion des utilisateurs
- Gestion du cycle de vie complet des comptes administrateurs (création / modification / suppression / activation-désactivation)
- Opérations en masse : suppression en masse, activation / désactivation en masse
- Import en masse des utilisateurs via Excel, validation ligne par ligne + rapport d'erreurs
- Mots de passe stockés avec hash bcrypt ; la modification du mot de passe exige la confirmation de l'ancien mot de passe
- Les opérations sensibles telles que la suppression exigent la double confirmation du mot de passe de l'utilisateur connecté
- Numéro de téléphone / e-mail / numéro de carte d'identité stockés chiffrés, masquage automatique dans les listes

### 1.2 Rôles et permissions (RBAC)
- Gestion des rôles : création / modification / suppression, slug unique
- Arbre de permissions : structure arborescente illimitée, trois types — menu (visible dans la navigation), bouton (action dans une page), API (accès aux interfaces)
- Format de l'identifiant de permission : `{method}.{path}`, par exemple `get.admin/product`, `post.admin/user/batch/destroy`
- Association many-to-many rôle-permission ; le super administrateur contourne tous les contrôles de permission
- Le middleware AdminPermission met en cache les permissions des utilisateurs dans Redis (TTL=60s)

### 1.3 Configuration système
- Stockage clé-valeur, prise en charge de la gestion par groupes
- Types de valeurs : chaîne / entier / booléen / JSON / tableau

### 1.4 Audit des opérations
- Enregistrement automatique de toutes les opérations POST/PUT/DELETE
- Enregistre l'opérateur, l'action, la méthode, le chemin, l'IP, les paramètres (champs sensibles masqués), l'heure
- Détection automatique de la source sur 8 plateformes (Web/Flutter/HarmonyOS/API, etc.)
- Consultation en lecture seule, ni suppression ni modification

### 1.5 Protection de sécurité
- Défense en profondeur sur 7 couches : restriction des méthodes HTTP, interception XSS / injection SQL / traversée de chemin / injection de commande / CSRF
- Captcha à clic (validation obligatoire à la connexion / l'inscription)
- Limitation de débit par fenêtre glissante Redis (atomique via Lua, 60 requêtes/minute par défaut)
- Verrouillage de compte : 5 échecs → verrouillage 15 minutes
- Limite de sessions simultanées : 3 jetons valides maximum par utilisateur
- En-têtes CSP, security.txt (RFC 9116)
- Double vérification aléatoire des opérations sensibles (poster-php)

---

## 2. Produits et données de base

### 2.1 Gestion des produits
- Fiche produit : code (unique), nom, code-barres, spécification, unité de base, image, description
- SKU multi-spécifications : plusieurs SKU pour un même produit, chacun avec son code, code-barres et attributs de spécification (JSON) indépendants
- Conversion multi-unités : taux de conversion entre l'unité de base et les unités auxiliaires
- Stratégie de prix : prix d'achat, prix de gros, prix de détail, prix par niveau de client
- Recherche plein texte ES prise en charge

### 2.2 Catégories de produits
- Structure de catégories arborescente illimitée
- Tri, activation / désactivation pris en charge
- Tri par glisser-déposer

### 2.3 Gestion des marques
- Nom de la marque, logo, description, tri

### 2.4 Entrepôts et emplacements
- Gestion multi-entrepôts (nom, code, adresse, responsable, téléphone de contact)
- Plusieurs emplacements par entrepôt (code unique au sein de l'entrepôt)

### 2.5 Gestion des fournisseurs
- Code, nom, contact, téléphone / e-mail (chiffrés), adresse du fournisseur
- Informations de compte bancaire (stockage chiffré), numéro fiscal, taux de taxe
- Recherche plein texte ES

### 2.6 Gestion des clients
- Code, nom, niveau de client, limite de crédit du client
- Contact / téléphone / e-mail (chiffrés) / adresse
- Niveau de client : nom, taux de remise par défaut
- Recherche plein texte ES

---

## 3. Gestion des achats

### 3.1 Demande d'achat
- Les départements / personnes soumettent les besoins d'achat
- Processus d'approbation : en attente → approuvée / rejetée → convertie en commande
- Peut être raccordée au moteur de workflow d'approbation

### 3.2 Bon de commande d'achat
- Associe le fournisseur et le détail des produits (quantité, prix unitaire, montant)
- États : en attente de validation → validé → partiellement reçu → reçu → annulé
- Peut être créé à partir d'une demande, ou directement

### 3.3 Réception d'achat (interconnexion inter-modules)
- Réception selon la commande, avec prise en charge de réceptions partielles
- La réception déclenche automatiquement : ① entrée en stock (évaluation au coût moyen pondéré mobile) ② génération de l'enregistrement des comptes fournisseurs ③ mise à jour de la quantité reçue de la commande

### 3.4 Retour d'achat
- Retour au fournisseur, génération d'une sortie de stock compensatoire

### 3.5 Règlement fournisseur
- Récapitulatif par fournisseur : montant des achats, déjà payé, à payer
- États : non réglé / partiellement réglé / réglé

---

## 4. Gestion des ventes

### 4.1 Devis
- Devis au client, prise en charge de la conversion en commande de vente
- États : brouillon → devisé → converti en commande → expiré

### 4.2 Commande de vente
- Associe le client et le détail des produits (quantité, prix unitaire, remise)
- États : en attente de validation → validée → partiellement expédiée → expédiée → annulée

### 4.3 Expédition de vente (interconnexion inter-modules)
- Expédition selon la commande, avec prise en charge d'expéditions partielles
- L'expédition déclenche automatiquement : ① sortie de stock (au coût moyen pondéré mobile) ② génération de l'enregistrement des comptes clients ③ mise à jour de la quantité expédiée de la commande

### 4.4 Retour de vente
- Retour client, génération d'une entrée en stock compensatoire

### 4.5 Règlement client et marge
- Récapitulatif par client : montant des ventes, déjà encaissé, à encaisser
- Calcul de la marge par commande / produit / client

### 4.6 Contrôle du crédit client (livré en v1.4.0)

- Gestion des limites de crédit : maintien par client du plafond d'encours, de l'occupation et des règles de blocage
- Blocage avant commande/expédition : CreditControlService assertOrderCreate / assertDeliveryCreate / guard refuse les commandes hors limite et renvoie le motif

---

## 5. Gestion des stocks

### 5.1 Stock en temps réel
- Précision à quatre dimensions : entrepôt + emplacement + lot + SKU
- Prise en charge multi-entrepôts, multi-emplacements
- Consultation du stock en temps réel

### 5.2 Flux d'entrées / sorties de stock
- Toutes les variations de stock sont enregistrées de façon unifiée (sens, quantité, prix de revient, numéro de document source, heure)

### 5.3 Suivi par lots
- Date de production, date de péremption, numéro de lot
- Enregistrement du lot lors des entrées / sorties

### 5.4 Suivi par numéros de série
- Gestion des numéros de série uniques
- Enregistrement de l'état (en stock / sorti) lors des entrées / sorties

### 5.5 Calcul des coûts
- Méthode du coût moyen pondéré mobile
- Formule : nouveau prix moyen = (valeur totale du stock initial + valeur totale de l'entrée) / (quantité du stock initial + quantité de l'entrée)
- Recalcul automatique à chaque entrée ; les sorties sont valorisées au prix moyen courant

### 5.6 Transferts de stock
- Transferts entre entrepôts / entre emplacements
- États : à transférer → transféré (sortie) → transféré (entrée) → terminé
- Génération automatique des flux de sortie / d'entrée du transfert

### 5.7 Gestion des inventaires
- Inventaire planifié (par entrepôt / catégorie) + inventaire dynamique (par SKU)
- Enregistrement du stock comptable vs la quantité réellement comptée
- Les écarts génèrent automatiquement des flux d'excédent / de déficit

### 5.8 Alertes de stock
- Seuils haut / bas configurables par SKU + entrepôt
- Enregistrement automatique d'un journal d'alerte en dessous du seuil bas / au-dessus du seuil haut

### 5.9 Traçabilité par lots/numéros de série et alerte de péremption (livré en v1.4.0)

- Chaîne de traçabilité : TraceService forward/backward remonte et descend la destination/l'origine des lots, registre des numéros de série
- Alerte de péremption : expiryAlert signale les lots proches de la péremption et périmés

---

## 6. Gestion financière

### 6.1 Comptes à recevoir / à payer
- Générés automatiquement par la réception d'achat / l'expédition de vente
- États : non rapproché → partiellement rapproché → rapproché
- Protection d'idempotence pour le même document source

### 6.2 Gestion des encaissements
- Multi-comptes (espèces / banque / WeChat / Alipay)
- Après validation, mise à jour automatique du solde du compte et du journal de caisse
- Prise en charge du rapprochement des comptes à recevoir

### 6.3 Gestion des paiements
- Même logique que les encaissements, sens inverse
- Prise en charge du rapprochement des comptes à payer

### 6.4 Journal de caisse et de banque
- Enregistrement des flux d'entrées / sorties par compte + date
- Mise à jour en temps réel du solde des comptes bancaires

### 6.5 Notes de frais
- Processus : soumission → approbation → paiement
- Après paiement, génération automatique d'un ordre de paiement + écriture de journal

### 6.6 Compte de résultat
- Récapitulatif mensuel : chiffre d'affaires, coût des ventes, frais, résultat
- Stockage en instantané (unique par year+month)

### 6.7 Immobilisations
- Cycle de vie complet de l'actif : acquisition → utilisation → amortissement → cession
- Amortissement linéaire : (valeur d'origine - valeur résiduelle) / nombre de mois d'utilisation
- Amortissement mensuel, génération automatique des enregistrements d'amortissement
- Enregistrements : valeur d'origine, valeur résiduelle, durée d'utilisation, amortissement mensuel, amortissement cumulé, valeur nette

### 6.8 Gestion fiscale
- Multi-types de taxes : TVA / impôt sur les sociétés / impôt sur le revenu des personnes physiques / droits de timbre
- Taux de taxe configurables (dont 4 taux par défaut en données initiales)
- Association aux documents d'achat / de vente, enregistrement automatique du montant de taxe

### 6.9 Multi-devises
- Gestion des devises : CNY/USD/EUR/JPY (dont 4 devises par défaut en données initiales)
- Indicateur de devise de comptabilisation
- Taux de change gérés par date d'effet

### 6.10 Gestion budgétaire
- Élaboration du budget annuel : par centre de coût + compte + mois
- Analyse comparative budget vs réel (taux d'exécution + écart)
- États : brouillon → approuvé → en cours d'exécution → clôturé

### 6.11 Centres de coût / centres de profit
- Structure hiérarchique arborescente
- Regroupement des coûts + répartition des frais
- Comptabilité indépendante des centres de profit

### 6.12 Clôture de période / comptabilité multi-organisations / consolidation (livré en v1.4.0)

- Clôture des pertes et profits de période : regroupement par période des mouvements des comptes de charges et de produits (produits - charges = résultat net, status=calculated)
- La clôture ne génère pas de pièce comptable (absence de configuration du compte de résultat de l'exercice et de règle anti-double clôture) et n'a pas de point de terminaison de contrôleur — non retenue dans la livraison v1.4.0, conservée en todo
- Comptabilité multi-organisations : CompanyController / LedgerPeriodController, entités comptables et périodes comptables indépendantes (livré en v1.4.0)
- Moteur de consolidation (livré en v1.4.0) : ConsolidationService rateToBase/translateLedger conversion aux taux de clôture (refus si un taux manque), addElimination élimination intersociétés (contrôle d'équilibre débit/crédit), generateDraft production des états (lecture de l'instantané pour les périodes déjà émises, recalcul en temps réel à partir des pièces validées en l'absence d'instantané), issue émission anti-doublon, latest/list ; les résultats sont stockés dans FinanceConsolidationReport
- Tests : PeriodCloseServiceTest 4 cas + ConsolidationServiceTest 3 cas

### 6.13 Comptabilité des stocks et des coûts de production (livré en v1.4.0)

- Sortie de matières et regroupement des coûts : MaterialIssueController comptabilise la sortie de matières sur prélèvement, CostEntryController + MfgCostService regroupent par ordre de fabrication les matières/main-d'œuvre/frais de fabrication
- Règles de pièces : MfgCostVoucherRule, pièces de transfert du coût de production terminé et des écarts (en lien avec §12 ordres de fabrication)

### 6.14 Effets et rapprochement bancaire (livré en v1.4.0)

- Cycle de vie complet des effets : FinanceBillService store/update/endorse (endossement)/discount (escompte)/collect (encaissement)/cash (réalisation)/reject + dueWarnings alerte d'échéance
- Rapprochement bancaire : BankReconService importStatement import des relevés, autoReconcile/manualReconcile/unreconcile lettrage, reconReport rapport de rapprochement

### 6.15 Pool de factures d'achat et factures électroniques (livré en v1.4.0)

- Pool d'entrées : TaxInvoicePoolService registerOne/registerBatch/verify/check/deduct/deductStats ; la vérification passe par MockTaxVerifier (le canal réel de l'administration fiscale est un point d'adaptation)
- Factures électroniques : EInvoiceService issueInvoice/voidInvoice/issueLogs, via EInvoiceAdapter/MockEInvoiceAdapter (le canal réel de l'administration fiscale est un point d'adaptation)

---

## 7. CRM

### 7.1 Gestion des clients
- Fiche client (associée au client des données de base)
- Gestion de plusieurs contacts (contact principal marqué)
- Téléphone / e-mail des contacts stockés chiffrés

### 7.2 Enregistrements de suivi
- Modes de suivi : téléphone / visite / e-mail / message / autre
- Enregistre le contenu du suivi, le prochain plan de suivi, la prochaine date de suivi
- Associés au client, au contact

### 7.3 Campagnes marketing
- Cycle de vie complet de la campagne : planifiée → en cours → terminée → annulée
- Multi-canaux : e-mail / SMS / téléphone / événement / réseaux sociaux
- Suivi des clients participants, statistiques de taux de conversion
- Comparaison budget vs dépenses réelles

### 7.4 Tickets de service
- Gestion des tickets : à traiter → en cours de traitement → résolu → clôturé
- Priorités : basse / moyenne / haute / urgente
- Catégories : support technique / réclamation / consultation / retour-échange / autre
- Affectation d'un responsable + réponses (publiques / notes internes)

### 7.5 Rapports d'analyse client
- 6 indicateurs clés : nouveaux clients / clients actifs / taux de rétention / panier moyen / CLV / taux de résolution des tickets
- Génération automatique des rapports (instantané des données JSON)
- Prise en charge mensuel / trimestriel / annuel

### 7.6 Moteur de valeur des membres (livré en v1.4.0)

- Membres et portefeuille : MemberService openMember/recharge/consume/refund
- Points et coupons : earnPoints/consumePoints/expirePoints flux de points + issueCoupon/redeemCoupon utilisation des coupons (MemberController / CouponController)

---

## 8. Moteur de workflow d'approbation

### 8.1 Modèles de workflow
- Chaînes d'approbation configurables : différents processus d'approbation selon le type de document
- Nœuds d'approbation : approbation séquentielle, prise en charge du routage conditionnel (jugement sur des champs comme le montant, le département, etc.)
- Types d'approbateurs : personne désignée / rôle / responsable de département / supérieur hiérarchique direct
- Prise en charge du rejet et du transfert

### 8.2 Opérations d'approbation
- Soumission → approbation par niveaux → acceptation / rejet / retrait
- Liste de mes approbations (en attente + approuvées)
- Traçabilité complète des enregistrements d'approbation

### 8.3 Concepteur visuel de workflow (livré en v1.4.0)

- Composition sur canevas : WorkflowDesignerController lit et écrit la définition des nœuds/liens (persistance dans canvas_json)
- Même moteur que l'approbation : une fois publié, le flux d'approbation suit la définition du canevas

---

## 9. Système de notifications

### 9.1 Gestion des notifications
- Messages internes : états non lu / lu
- Modèles de notification : prise en charge de la substitution de variables (par exemple « Vous avez une approbation en attente de {demandeur} »)
- Multi-canaux : notification interne (implémentée) → e-mail (implémenté via journal de fichiers, SMTP à raccorder) → WeCom / DingTalk (points d'adaptation réservés)
- Préférences de notification de l'utilisateur

### 9.2 Notifications automatiques
- Rappels de tâches d'approbation en attente
- Alertes de stock
- Notifications d'affectation de tickets
- Envoi unifié via NotificationService

---

## 10. Gestion de projets

### 10.1 Projets
- Cycle de vie complet du projet : en planification → en cours → en retard → terminé → annulé
- Priorités : basse / moyenne / haute / urgente
- Comparaison budget du projet vs coût réel
- L'avancement des tâches est agrégé automatiquement en avancement du projet
- Association au client, nomination d'un chef de projet

### 10.2 Décomposition WBS des tâches
- Structure de tâches arborescente (niveaux illimités de tâches parentes / enfants)
- Prise en charge des données de diagramme de Gantt (dépendances de tâches, chronologie)
- États des tâches : à commencer → en cours → terminée → en retard
- Temps estimé vs temps réel

### 10.3 Relevés de temps
- Enregistrement des heures par projet / tâche / personne / date
- Agrégation automatique du temps réel des tâches
- Prise en charge du calcul des coûts du projet

### 10.4 Coûts de projet et écarts budgétaires (livré en v1.4.0)

- Comptabilisation des coûts : ProjectCostService createManual saisie manuelle + generateFromTimesheet conversion selon heures × taux horaire de l'employé
- Vue de rentabilité : projectPnl marge du projet comparée au budget

---

## 11. Gestion des ressources humaines

### 11.1 Structure organisationnelle
- Départements : structure hiérarchique arborescente
- Postes : répartis par département, tri pris en charge
- Fiche employé : code, nom, sexe, date de naissance, date d'embauche, état
- Champs sensibles chiffrés : numéro de téléphone, e-mail, numéro de carte d'identité, numéro de compte bancaire

### 11.2 Gestion du pointage
- Règles de pointage : heures d'arrivée / de départ, tolérance de retard, tolérance de départ anticipé
- Enregistrements de pointage : arrivée / départ, calcul automatique des minutes de retard / de départ anticipé
- États : normal / en retard / départ anticipé / pointage manquant / en congé / en déplacement
- Gestion des congés : congés annuels / personnels / maladie / mariage / maternité / repos compensatoire

### 11.3 Gestion des salaires
- Configuration des éléments de salaire : éléments de revenu / de déduction, assujettissement à l'impôt, montant par défaut
- Calcul du salaire : salaire de base + performance + heures supplémentaires - déductions - impôt sur le revenu = salaire net
- Prise en charge de la génération en masse des salaires mensuels
- Confirmation du versement du salaire

### 11.4 Recrutement et performance (livré en v1.4.0)

- Recrutement : RecruitService publication/clôture de poste, progression de l'entonnoir des candidats, comptes rendus d'entretien, envoi/acceptation/refus d'offre
- Performance : PerformanceService modèles d'indicateurs et plans d'évaluation, notation à 360° (submitScore multi-évaluateurs), synthèse

### 11.5 Formation, protection sociale et bulletins de paie (livré en v1.4.0)

- Formation : TrainingService cours/inscriptions/achèvement/registre de crédits (employeeCredits)
- Protection sociale : SocialSecurityService règles de base (createRule/setRate), rattachement des employés bind/unbind, simulation calculate et détail par employé employeeSocialDetail
- Bulletins de paie : PayslipService view déploie en lecture seule les éléments de salaire (revenus/déductions/net, avec complément de protection sociale)

---

## 12. Production

### 12.1 Nomenclature (BOM)
- BOM produit : produit fini → composants → matières premières, structure arborescente multi-niveaux
- Gestion des versions : brouillon → en vigueur → périmée
- Détail des composants : quantité, unité, taux de perte

### 12.2 Ordre de fabrication
- Création d'un ordre de fabrication à partir d'un BOM
- États : à produire → en production → terminé → annulé
- Quantité planifiée vs quantité réelle
- Dates de début / de fin planifiées vs heures de début / de fin réelles

### 12.3 Gammes
- Définition des étapes de processus par produit
- Chaque étape est associée à un poste de travail et un temps standard
- Tri des étapes

### 12.4 Postes de travail
- Code, nom, capacité du poste de travail (par heure)
- Activation / désactivation

### 12.5 MRP (planification des besoins en matières)
- Calcul des besoins nets : besoins totaux - réceptions planifiées - stock disponible = besoins nets
- Génération des plans par période (année + mois)
- États : brouillon → généré → confirmé

### 12.6 Déclaration d'opérations et salaire à la pièce (livré en v1.4.0)

- Déclaration : WorkReportService audit valide la déclaration d'une opération
- À la pièce : PieceWageService accumulate registre à la pièce / periodSummary synthèse par période

### 12.7 Sous-traitance et rapprochement (livré en v1.4.0)

- SubcontractService : auditIssue validation de la sortie de matières sous-traitée → auditReceive boucle fermée de rapprochement à la réception

### 12.8 Charge de capacité (livré en v1.4.0)

- MfgCapacityService : calendar calendrier de capacité des postes de travail, setException/removeException exceptions de capacité, report analyse de charge

---

## 13. Constructeur de rapports personnalisés

### 13.1 Modèles de rapport
- Champs personnalisés : sélection des champs de table de données, mode d'agrégation (somme / comptage / moyenne / max / min)
- Filtres personnalisés : texte / liste déroulante / plage de dates / plage numérique
- Types de graphiques : tableau / histogramme / courbe / diagramme circulaire / carte d'indicateur KPI
- Regroupement par module (produits / achats / ventes / stocks / finance / CRM / RH / production / projets)

### 13.2 Exécution des rapports
- Génération de SQL dynamique (basée sur la configuration des champs et des filtres)
- Protection par liste blanche des noms de tables (analysés depuis install.sql)
- Instantané du jeu de données de résultat (stockage JSON)

### 13.3 Rapports planifiés
- Fréquence de planification : tous les jours / toutes les semaines / tous les mois
- Configuration des destinataires
- Exécution automatique + stockage des résultats

---

## 14. Tableau de bord

### 14.1 Vue d'ensemble de l'exploitation
- Ventes du jour / du mois, achats du jour / du mois
- Total à encaisser / à payer, valeur totale du stock, marge brute
- Cache Redis 5 minutes

### 14.2 Tableau de bord des ventes
- Tendance des ventes, Top 10 des clients
- Prise en charge de la bascule de plage de temps

### 14.3 Tableau de bord des stocks
- Valeur totale du stock, statistiques d'alertes (seuil bas / seuil haut)
- Tendance des entrées / sorties (par jour / sens)

### 14.4 Tableau de bord financier
- Total à encaisser / à payer, encaissements / paiements du mois
- Récapitulatif des soldes de caisse et de banque

---

## Flux de données inter-modules

```
采购收货 → 自动入库(移动加权平均成本) → 生成应付记录
销售发货 → 自动出库 → 生成应收记录
收付款 → 核销应收应付 → 更新日记账
盘点差异 → 自动生成盈亏出入库流水
审批提交 → 工作流引擎路由 → 逐级审批 → 通知推送
费用报销打款 → 自动生成付款单 + 日记账
资产折旧 → 按月计提 → 成本分摊到成本中心
MRP 运算 → BOM 展开 → 净需求计算 → 生成采购/生产建议
请假审批 → 通过后更新考勤状态
生产完工 → 自动入库(产成品) + 扣减原材料库存
工时记录 → 汇总到任务 → 聚合到项目成本
```

---

## 15. Fonctions d'export

### 15.1 Export Excel
- Toutes les pages de liste prennent en charge ?export=excel
- PhpSpreadsheet génère le .xlsx : en-tête blanc sur fond bleu + première ligne figée + filtre automatique
- Masquage automatique des champs sensibles

### 15.2 Export PDF
- Les panneaux de données du tableau de bord prennent en charge ?export=pdf
- Rendu Dompdf, A4 paysage
- Informations de copyright non supprimables

---

## 16. Gestion des commandes (OMS)

### 16.1 Gestion des commandes
- **Import de commandes multi-canaux** : prise en charge de manual/web/mobile/api/marketplace/edi/pos
- **Informations étendues de la commande** : numéro de commande du canal, boutique, état d'exécution, état de paiement, priorité
- **Allocation de stock** : calcul ATP (quantité pouvant être promise) → pré-réservation de stock (verrou pessimiste anti-suroccupation)
- **Orchestration de l'exécution** : allocation → création de l'exécution → envoi au WMS → préparation / emballage → expédition TMS
- **Annulation de commande** : libération automatique de la pré-réservation de stock

### 16.2 Retours RMA
- Création d'un RMA (retour / échange / réparation) → approbation → retour → réception et entrée en stock (stockIn) → remboursement
- Prise en charge des frais de retour et du montant du remboursement

### 16.3 Gestion des canaux
- Code / nom / type de canal (direct/marketplace/edi/pos)
- Configuration du canal (JSON), état activation / désactivation

---

## 17. Gestion d'entrepôt (WMS)

### 17.1 Zones et emplacements
- **Zones** : zone de réception / zone de stockage / zone de préparation / zone d'emballage / zone d'expédition / zone de retour / zone de contrôle qualité
- **Extension des emplacements** : allée → rayonnage → niveau → emplacement + code-barres / volume / charge / ordre de préparation

### 17.2 Processus d'entrée
- **ASN (avis de pré-arrivée)** : fournisseur → arrivée prévue → transporteur → numéro de suivi
- **Tâche de réception** : réception sur quai → saisie de la quantité reçue → contrôle qualité
- **Tâche de mise en rayon** : génération automatique → affectation → stratégie (fifo/zone_fixed/abc) → confirmation de mise en rayon (stockIn)

### 17.3 Processus de sortie
- **Gestion des vagues** : agrégation de plusieurs commandes → vague de préparation / vague d'expédition → priorité
- **Tâche de préparation** : par commande / par lot / par zone / par vague → affectation → confirmation (quantité réellement prélevée)
- **Tâche d'emballage** : type d'emballage (box/bag/pallet) → poids / dimensions

---

## 18. Gestion du transport (TMS)

### 18.1 Transporteurs
- Code / type de transporteur (express / groupage / camion complet / fret aérien / fret maritime / ferroviaire)
- Services du transporteur : standard/express/overnight/2day/economy + délais
- Configuration API : abstraction custom/shippo/afterShip/17track

### 18.2 Gestion du fret
- **Carte tarifaire** : lieu d'expédition / de destination → tranches de poids → frais de base / frais par kg / supplément carburant
- **Multi-devises** : CNY/USD/EUR, etc., associées à exchange_rate
- **Comparaison du fret** : consultation de tous les tarifs disponibles par pays de destination + poids, tri croissant

### 18.3 Connaissements et suivi
- **Connaissement** : service du transporteur → numéro de suivi → état (à expédier → collecté → en transit → livré / anomalie / retour)
- **Suivi logistique** : rappel webhook → synchronisation automatique de l'état du connaissement
- **Facture de fret** : création → confirmation → paiement → génération des comptes fournisseurs

---

## Annexe : taille du projet

| Dimension | Nombre |
|------|------|
| Modules métier | 23 <!-- stats:modules=23 --> |
| Tables de base de données | 227 <!-- stats:tables=227 --> |
| Modèles de données | 224 <!-- stats:models=224 --> |
| Contrôleurs | 159 <!-- stats:controllers=159 --> |
| Services métier | 64 <!-- stats:services=64 --> |
| Routes API | 837 (générées dynamiquement, voir `scripts/check-endpoints.php`, ne participent pas à la validation doc-stats) |
| Middlewares | 11 <!-- stats:middleware=11 --> |
| Fichiers sources PHP | 483 <!-- stats:php_files=483 --> |
| Script d'installation de la base de données | Fichier unique `database/install.sql` (227 tables, toutes les migrations fusionnées) |
| Pages frontend (Flutter) | 119 (fichiers de page `.dart` sous `apps/flutter/lib/app/pages/` (récursif), mesuré le 2026-09-22, non inclus dans la validation doc-stats) |
| Pages frontend (HarmonyOS) | 52 (fichiers de page `.ets` sous `apps/harmonyos/entry/src/main/ets/pages/` (récursif), mesuré le 2026-09-22, non inclus dans la validation doc-stats) |
| Tests unitaires | 111 fichiers de test <!-- stats:test_files=111 --> / 1001 cas de test <!-- stats:tests=1008 --> / 4726 assertions <!-- stats:assertions=4768 --> (décompte statique : méthodes de test + points d'appel d'assertion, indépendant de l'environnement d'exécution) |

> Les chiffres ci-dessus sont mesurés par `bash scripts/doc-stats.sh` ; les éléments annotés `<!-- stats:key=value -->` sont vérifiés automatiquement par le CI (job docs de `.github/workflows/ci.yml`) pour être cohérents avec les faits du code — toute dérive fait passer au rouge.

---

## 19. Matrice d'achèvement des modules (corrigée le 2026-09-05 ; lot v1.17.0 2026-09-15)

### Légende des états

| Marque | Signification |
|------|------|
| ✅ | Terminé — utilisable en production |
| ⚠️ | Squelette — CRUD terminé, moteur métier / frontend manquant |
| 🔴 | Manquant — non implémenté |
| 🔵 P0 | Phase d'écosystème frontend |
| 🟢 P1 | Phase de profondeur métier |
| 🟡 P2 | Phase de fiabilité d'exploitation |
| 🟣 P3 | Phase d'amélioration de l'expérience |

### Matrice

| Module | API backend | Logique métier | Flutter | HarmonyOS | Phase suivante |
|------|----------|----------|---------|-----------|----------|
| Administration système | ✅ | ✅ | ⚠️ 9/14 | ⚠️ 5 pages | 🔵 P0 |
| Tableau de bord | ✅ | ✅ | ✅ 2 pages | ⚠️ 1 page | 🔵 P0 |
| Données de base produits | ✅ | ✅ | ✅ 7/7 | ⚠️ 1/7 | 🔵 P0 |
| Gestion des achats | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestion des ventes | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Gestion des stocks | ✅ | ✅ | ✅ 5/5 | ⚠️ 1/5 | 🔵 P0 |
| Finance — pièces / comptes à recevoir et à payer | ✅ | ⚠️ | ✅ 16 pages | 🔴 | 🔵 P0 |
| Finance — grand livre / trois états | ⚠️ | 🔴 | ⚠️ 3 pages (profondeur des actions à vérifier) | 🔴 | 🟢 P1 |
| Finance — consolidation | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finance — comptabilité multi-organisations (F1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Finance — clôture de période | 🔴 | ⚠️ | 🔴 | 🔴 | 🟢 P1 |
| Finance — comptabilité des coûts de stock (F3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| CRM tous modules | ✅ | ✅ | ✅ 10/10 | 🔴 | 🔵 P0 |
| OMS Gestion des commandes | ✅ | ✅ | ✅ 4/4 | ⚠️ 4 pages | 🔵 P0 |
| WMS Gestion d'entrepôt | ✅ | ✅ | ⚠️ 7/8 | ⚠️ 7 pages | 🔵 P0 |
| TMS Gestion du transport | ✅ | ✅ | ⚠️ 5/6 | ⚠️ 5 pages | 🔵 P0 |
| Workflow d'approbation | ✅ | ✅ | ⚠️ 2/3 | ⚠️ 1 page | v1.4.0 |
| Système de notifications | ✅ | ✅ | ⚠️ 1/2 | 🔴 | v1.4.0 |
| Contrôle du crédit (F7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Chaîne de traçabilité / péremption (M6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Charge de capacité (M3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Déclaration d'opérations / pièce / rapprochement sous-traitance (M1+M2) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Concepteur de workflow (B3) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Modèles d'impression (B1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Recrutement / performance / formation / protection sociale (H1-H4) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Pointage par scan (E1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Coûts de projet / budget (P1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Effets / rapprochement bancaire (F6) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Pool d'entrées / factures électroniques (F5) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Valeur des membres (C1) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Multi-tenant (B5) | ✅ | ⚠️ | 🔴 | 🔴 | v1.4.0 activation partielle |
| Notification de canaux / champs personnalisés (B4+B7) | ✅ | ✅ | 🔴 | 🔴 | v1.4.0 |
| Gestion de projets | ✅ | ✅ | ✅ 3/3 | 🔴 | 🔵 P0 |
| RH — organisation / pointage / congés | ✅ | ⚠️ | ✅ 5/5 | ⚠️ 3 pages | 🔵 P0 |
| RH — moteur de salaires | ⚠️ | 🔴 | ⚠️ 2 pages | 🔴 | 🟢 P1 |
| Fabrication — BOM / production / MRP | ✅ | ✅ | ⚠️ 5/13 | ⚠️ 5 pages | v1.4.0 |
| Gestion de la qualité | ✅ | ✅ | ✅ 5/5 | 🔴 | 🟢 P1 |
| Rapports personnalisés | ✅ | ⚠️ | ✅ 2/2 | 🔴 | 🔵 P0 |
| Tableaux de bord BI | ✅ | ✅ | ⚠️ 2/3 | 🔴 | 🟣 P3 |
| Gestion des équipements EAM | ✅ | ✅ | ⚠️ 4/5 | 🔴 | v1.4.0 |
| Gestion documentaire DMS | ✅ | ✅ | ⚠️ 1/2 | 🔴 | 🟣 P3 |
| Multilingue (i18n) | ✅ | ✅ | ⚠️ chinois/anglais uniquement | ⚠️ chinois/anglais uniquement | v1.17.0 |
| Observabilité | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |
| Migration / rollback / sauvegarde | ⚠️ | 🔴 | N/A | N/A | 🟡 P2 |

### Statistiques

| Dimension | ✅ terminé | ⚠️ squelette | 🔴 manquant | N/A | Taux d'achèvement |
|------|---------|----------|---------|-----|--------|
| Modules (44) | 33 | 11 | 0 | 0 | 75 % |
| API backend | 39 | 4 | 1 | 0 | 89 % |
| Logique métier | 33 | 7 | 4 | 0 | 75 % |
| Frontend Flutter | 12 | 12 | 18 | 2 | 29 % |
| HarmonyOS | 0 | 13 | 29 | 2 | 0 % (✅ comptés ; 13 lignes ont déjà des pages ⚠️) |

> **Méthodologie (correction du 2026-09-05)** : les lignes de modules comptent « API backend et logique métier toutes deux implémentées » — double ✅ = terminé,
> sinon le module est compté squelette ⚠️ (y compris les lignes « activation partielle » : middleware d'isolation du multi-tenant B5 non enregistré et autres points historiques à compléter, voir les preuves de code) ;
> les lignes API backend / logique métier sont comptées selon les colonnes correspondantes de la matrice, le dénominateur du taux d'achèvement excluant les lignes N/A (observabilité, rollback de migration sans frontend).
> **Colonnes Flutter / HarmonyOS (passées au périmètre « couverture des actions des pages » depuis le 2026-08-27)** : ✅ = le module a des pages et leur nombre de fichiers de page ≥ nombre de contrôleurs backend
> (`n/n` ou nombre de pages indiqué) ; ⚠️ = pages présentes mais nombre de fichiers de page < nombre de contrôleurs backend (couverture partielle) ; 🔴 = aucune page ; **à vérifier** = les pages existent mais
> la profondeur des actions (boucle fermée création-lecture-modification-suppression) n'a pas été contrôlée page par page. **Les nombres de pages de chaque ligne suivent le périmètre du relevé du 2026-08-27** (nombre de fichiers de
> `apps/flutter/lib/app/pages/<module>/` et de `apps/harmonyos/entry/src/main/ets/pages/**`, à l'époque Flutter 107 pages / HarmonyOS 35 pages) ;
> le nouveau relevé complet du 2026-09-22 donne Flutter 119 pages / HarmonyOS 52 pages (**les nombres de pages ligne par ligne n'ont pas été recalculés selon le nouveau périmètre**, les jugements ✅/⚠️ reprennent le relevé du 2026-08-27),
> non pris en compte dans la validation doc-stats backend ; le taux d'achèvement HarmonyOS de 0 % résulte du comptage des ✅ (0/42), 13 lignes ont en réalité déjà des pages (⚠️ couverture partielle), ce n'est pas une colonne entièrement absente.
> **Dédoublonnage du 2026-09-15** : la matrice contenait à l'origine deux lignes « multi-tenant » (ligne `多租户 (B5)` et ligne `多租户`, colonnes de logique métier ✅ / ⚠️ contradictoires),
> fusionnées selon la méthodologie ci-dessus en une seule ligne « Multi-tenant (B5) » comptée ⚠️ (middleware d'isolation non enregistré), lignes de modules 45 → 44.
> **Lot v1.17.0 (2026-09-15)** : ajout de la ligne « multilingue (i18n) » (1 ligne sur 44 annotée v1.17.0) — les dictionnaires backend en 13 langues
> (`resource/translations/<locale>/`, 13 répertoires) et la négociation `Accept-Language` sont ✅ ; les côtés Flutter / HarmonyOS restent en chinois/anglais,
> les deux colonnes frontend sont donc comptées ⚠️ ; les côtés Angular / React disposent déjà des dictionnaires en 13 langues (non pris en compte dans les colonnes de cette matrice).
> **Lot v1.4.0 (2026-09-05)** : 21 lignes sur 44 sont annotées v1.4.0 (dont 1 ligne « activation partielle » = la ligne multi-tenant (B5)),
> couvrant multi-organisations/consolidation/coûts de stock (F1-F3), fabrication M1/M2/M3/M6, crédit F7, effets/rapprochement bancaire/pool d'entrées/factures électroniques F6/F5,
> RH H1-H4, membres C1, plateforme B1-B5/B7, pointage par scan E1, coûts de projet P1 ; preuves dans la section « Preuves de code » ci-dessous.

### Preuves de code (correction du 2026-09-05 ; lot v1.17.0 inclus)

Justifications de cette correction d'achèvement (l'existence des fichiers peut être vérifiée via `bash scripts/doc-stats.sh` et `find`) :

| Module | Correction | Preuves de code |
|------|------|----------|
| Gestion de la qualité | 🔴 → ✅ | `app/controller/quality/` (5 contrôleurs) + `app/service/quality/QmsInspectionService.php` + `tests/QualityModuleTest.php` |
| Tableaux de bord BI | 🔴 → ✅ | `app/controller/bi/` (3 contrôleurs : Dashboard/Dataset/Widget) + `tests/BiModuleTest.php` |
| Gestion des équipements EAM | 🔴 → ✅ (+E1 pointage par scan) | `app/controller/eam/` (5 contrôleurs, dont `EamInspectionController.php`) + `app/service/eam/EamInspectionService.php` + `tests/EamModuleTest.php` |
| Gestion documentaire DMS | 🔴 → ✅ | `app/controller/dms/` (2 contrôleurs) + `tests/DmsModuleTest.php` |
| Multi-tenant | ⚠️ → ⚠️ (activation partielle v1.4.0) | `app/controller/platform/TenantController.php` + `app/service/platform/TenantService.php` (provision/suspend/resume/expireMark/renew/expiryWarnings, facturation à l'échéance livrée) + `tests/Integration/TenantScopeIntegrationTest.php` ; le middleware d'isolation `app/middleware/TenantScope.php` n'est toujours pas enregistré (isolation inactive), d'où une activation partielle |
| Consolidation des états | ⚠️ → ✅ (livré en v1.4.0) | `app/service/finance/ConsolidationService.php` (rateToBase/translateLedger conversion aux taux de clôture, addElimination élimination intersociétés avec contrôle d'équilibre débit/crédit, generateDraft instantané prioritaire/recalcul en temps réel sans instantané, issue anti-doublon, latest/list ; refus si un taux manque) + résultats stockés dans `app/model/FinanceConsolidationReport.php` + `tests/ConsolidationServiceTest.php` (3 cas) |
| Clôture de période | 🔴 → ⚠️ | `app/service/finance/PeriodCloseService.php:21` `closeProfitAndLoss()` (regroupement des comptes de charges et de produits implémenté, sans pièce de clôture ni point de terminaison de contrôleur) + `tests/PeriodCloseServiceTest.php` (4 cas) |
| v1.4.0 — comptabilité multi-organisations (F1) | Nouveau | `app/controller/finance/CompanyController.php` + `LedgerPeriodController.php` (entités comptables et périodes comptables indépendantes) |
| v1.4.0 — stocks et coûts de production (F3) | Nouveau | `app/controller/manufacturing/MaterialIssueController.php` + `CostEntryController.php` + `app/service/manufacturing/MfgCostService.php` + `MfgCostVoucherRule.php` |
| v1.4.0 — exécution de fabrication M1/M2/M3/M6 | Nouveau | `app/service/manufacturing/` (WorkReportService/PieceWageService/SubcontractService/MfgCapacityService) + `app/service/inventory/TraceService.php` (traçabilité par lots/numéros de série et alerte de péremption) + contrôleurs correspondants WorkReport/PieceWage/Subcontract/Capacity |
| v1.4.0 — trésorerie/fiscalité F6/F5 + F7 | Nouveau | `app/service/finance/FinanceBillService.php` + `BankReconService.php` (import des relevés/lettrage automatique et manuel) + `app/service/tax/TaxInvoicePoolService.php` + `EInvoiceService.php` (EInvoiceAdapter/MockEInvoiceAdapter, canal réel de l'administration fiscale réservé comme point d'adaptation) + `app/service/sales/CreditControlService.php` (assertion bloquant les commandes hors limite) |
| v1.4.0 — membres/RH/projet C1/H1-H4/P1 | Nouveau | `app/service/retail/MemberService.php` + `app/controller/retail/` (MemberController/CouponController) + `app/service/hr/` (RecruitService/PerformanceService/TrainingService/SocialSecurityService/PayslipService) + `app/service/project/ProjectCostService.php` |
| v1.4.0 — plateforme/canaux B3/B4/B7/E1 | Nouveau | `app/controller/workflow/WorkflowDesignerController.php` (persistance canvas_json) + `app/service/notification/` (ChannelDriver/ChannelService/MockChannelDriver/MailMockChannelDriver, nouvelle tentative en cas d'échec) + `app/controller/notification/NotificationChannelController.php` (`tests/NotificationChannelTest.php` 5 cas) + `app/controller/platform/CustomFieldController.php` + `app/controller/eam/EamInspectionController.php` (pointage par scan) |
| v1.17.0 — multilingue (i18n) | Nouveau | Backend : `resource/translations/` (13 répertoires de langue zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id, trois fichiers common+modules+validation par langue, 542 entrées pour chacune des 11 langues (zh_CN 533, en 30) ; l'anglais est la clé pour `en`) + `app/common/I18n.php` (`getLocale()` lit la première balise de `Accept-Language` et mappe la sous-balise de langue principale zh*→zh_CN ; `trans()` hors `en` passe par `[langue de la requête, zh_CN, en]`→clé, `en` ne retombe pas sur le chinois) + `config/translation.php` + script de génération `scripts/gen-be-locales.mjs` ; frontend : `apps/react/src/lib/i18n/` (`index.tsx` + `zhEn` + 11 fichiers de langue) et `apps/angular/src/app/core/` (`zh-en/` (index + part1..4) + `zh-{ar,bn,de,es,fr,hi,id,ja,ko,pt,ru}.ts`) (dictionnaires des 11 nouvelles langues chargés paresseusement par langue, Angular 1453 / React 1447 clés) + `scripts/gen-fe-locales.mjs` ; points de bascule = icône globe de la barre supérieure + menu déroulant de l'espace personnel ; Flutter (`apps/flutter/lib/l10n/`) et HarmonyOS (`entry/src/main/resources/`) restent en chinois/anglais |

> Spécification détaillée de la feuille de route : `docs/superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`
