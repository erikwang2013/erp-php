# Open ERP — Document de conception fonctionnelle

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## 1. Présentation du système

Le système Open ERP (open-erp) est un système de planification des ressources d'entreprise (ERP) full-stack construit sur webman v2 + Flutter, couvrant quatorze grands domaines métier : gestion système, achats-ventes-stocks, finance, CRM, workflow d'approbation, notifications de messages, gestion de projets, ressources humaines, production, et rapports personnalisés.

### 1.1 Objectifs de conception
- Déploiement monolithique, conception modulaire
- Tous les ID générés par snowflake + transmission chiffrée via hashids
- Double chiffrement des données sensibles (AES-256-CBC en couche de transport + AES-128-ECB en couche de stockage)
- Comptabilité au coût moyen pondéré mobile
- Enchaînements automatiques inter-modules (achats→comptes à payer, ventes→comptes à recevoir, encaissements-décaissements→lettrage)

### 1.2 Contraintes techniques
- PHP 8.3+, MySQL 8.0+, Redis 7, Elasticsearch 8
- Préfixe de table erp_, clé primaire BIGINT non auto-incrémentée
- Version d'API contrôlée par l'en-tête de requête API-Version
- Authentification JWT + permissions RBAC
- Pas de préfixe `\` pour les fonctions globales

---

## 2. Module de gestion système

### 2.1 Gestion des utilisateurs
- CRUD administrateur, prise en charge de l'activation/désactivation en masse et de la suppression logique en masse
- Import en masse Excel (validation ligne par ligne + rapport d'erreurs)
- Mot de passe haché avec bcrypt, la modification du mot de passe exige la confirmation de l'ancien mot de passe
- Les opérations de suppression exigent une double confirmation par le mot de passe de l'utilisateur courant
- Téléphone/email/numéro de carte d'identité stockés chiffrés, masquage automatique dans les listes

### 2.2 Rôles et permissions (RBAC)
- CRUD des rôles, identifiant unique slug
- Arbre de permissions (auto-référence parent_id de niveau illimité), types : menu/bouton/API
- Format de l'identifiant de permission : {method}.{path} (par ex. get.admin/product, post.admin/user/batch/destroy)
- Association many-to-many rôle-permission
- Le super administrateur (super_admin) contourne tous les contrôles de permission
- Le middleware AdminPermission met en cache les permissions dans Redis (TTL=60 s)

### 2.3 Configuration système
- Stockage clé-valeur, prise en charge des groupes
- Types de valeur : string|int|bool|json|array

### 2.4 Audit des opérations
- Enregistrement automatique de toutes les opérations POST/PUT/DELETE
- Enregistrement : opérateur, action, méthode, chemin, IP, paramètres (masquage des champs sensibles), heure
- Détection automatique de 8 plateformes source (Web/Flutter/HarmonyOS/API, etc.)
- Consultation uniquement, pas de suppression ni de modification

### 2.5 Protection de la sécurité
- 18 couches de défense en profondeur (voir SECURITY.md)
- SecurityFilter : limitation des méthodes HTTP + interception XSS/injection SQL/traversée de chemins/injection de commandes/CSRF
- RateLimit : limitation de débit à fenêtre glissante Redis (atomique Lua, 60 requêtes/minute)
- Captcha à clic (obligatoire à la connexion/inscription)
- Verrouillage de compte : 5 échecs → verrouillage 15 minutes
- Limitation des sessions concurrentes : 3 jetons maximum par utilisateur
- En-tête CSP, security.txt (RFC 9116)
- Double vérification aléatoire des opérations sensibles via poster-php

---

## 3. Produits et données de base

### 3.1 Gestion des produits
- Fiche produit : code (unique), nom, code-barres, spécifications, unité de base, image, description
- SKU multi-spécifications : plusieurs SKU sous un même produit, chacun avec son code, code-barres et attributs de spécification (JSON) indépendants
- Conversion multi-unités : unité de base ↔ unité auxiliaire, taux de conversion
- Stratégies de prix : prix d'achat, prix de gros, prix de détail, prix par niveau de client
- Catégories de produits : structure arborescente de niveau illimité, prise en charge du tri par glisser-déposer
- Gestion des marques : nom, logo, description

### 3.2 Entrepôts et emplacements
- Gestion de plusieurs entrepôts (nom, code, adresse, responsable)
- Plusieurs emplacements par entrepôt (code unique dans l'entrepôt)
- Téléphone de l'entrepôt stocké chiffré

### 3.3 Fournisseurs/clients
- Fiche fournisseur : code, nom, contact, téléphone/email (chiffrés), adresse, coordonnées bancaires
- Fiche client : code, nom, niveau de client, limite de crédit
- Niveaux de client : nom, taux de remise par défaut
- Recherche plein texte ES pour les fournisseurs et les clients

---

## 4. Module achats

### 4.1 Flux d'achat
Demande → approbation → commande → réception → règlement

### 4.2 Demande d'achat
- Les départements/personnes soumettent leurs besoins d'achat
- Statuts : en attente d'approbation → approuvée/rejetée → convertie en commande
- Prise en charge des opérations des approbateurs

### 4.3 Commande d'achat
- Liée au fournisseur, lignes de produits (quantité, prix unitaire, montant)
- Statuts : en attente de validation → validée → partiellement réceptionnée → réceptionnée → annulée
- Peut être créée à partir d'une demande d'achat ou directement

### 4.4 Réception d'achat (enchaînement inter-modules)
- Réception selon la commande, prise en charge des réceptions partielles
- À la réception, déclenchement automatique :
  1. InventoryService.stockIn() → mise à jour du stock en temps réel + recalcul du coût moyen pondéré mobile
  2. FinanceService.createAp() → génération de l'écriture de compte à payer
  3. Mise à jour de la quantité reçue et du statut de la commande
- Prise en charge de l'enregistrement des emplacements et des numéros de lot

### 4.5 Retour d'achat
- Retour au fournisseur, génération de l'annulation de sortie de stock
- Lié au bon de réception

### 4.6 Règlement fournisseur
- Agrégation par fournisseur : montant des achats, payé, à payer
- Statuts de règlement : non réglé/partiellement réglé/réglé

---

## 5. Module ventes

### 5.1 Flux de vente
Devis → commande → expédition → règlement

### 5.2 Devis
- Devis adressé au client, prise en charge de la conversion en commande de vente
- Statuts : brouillon → devisé → converti en commande → expiré

### 5.3 Commande de vente
- Liée au client, lignes de produits (quantité, prix unitaire, remise)
- Statuts : en attente de validation → validée → partiellement expédiée → expédiée → annulée
- Prise en charge des montants de remise

### 5.4 Expédition de vente (enchaînement inter-modules)
- Expédition selon la commande, prise en charge des expéditions partielles
- À l'expédition, déclenchement automatique :
  1. InventoryService.stockOut() → déduction du stock (au coût moyen pondéré mobile)
  2. FinanceService.createAr() → génération de l'écriture de compte à recevoir
  3. Mise à jour de la quantité expédiée et du statut de la commande

### 5.5 Retour de vente
- Retour du client, génération de l'annulation d'entrée en stock

### 5.6 Règlement client et marge brute
- Agrégation par client : montant des ventes, encaissé, à recevoir
- Marge brute de vente : calculée par commande/produit/client

---

## 6. Module stocks

### 6.1 Gestion des stocks
- Stock en temps réel : précision à 4 dimensions entrepôt+emplacement+lot+SKU
- Mouvements d'entrée/sortie : tous les changements de stock sont enregistrés de manière unifiée (sens, quantité, coût unitaire, numéro de document source)
- Traçabilité des lots : date de production, date de péremption
- Traçabilité des numéros de série : numéro de série unique, enregistrement du statut à l'entrée/sortie (en stock/sorti)

### 6.2 Calcul des coûts
- Méthode du coût moyen pondéré mobile
- Formule : nouveau coût moyen = (valeur totale du stock existant + valeur totale de l'entrée) / (quantité du stock existant + quantité entrée)
- Recalcul automatique à chaque entrée, coût comptabilisé au coût moyen courant à la sortie
- Chaîne complète d'enregistrement des coûts (coût moyen avant → coût moyen après)

### 6.3 Transfert de stock
- Transferts entre entrepôts/emplacements
- Statuts : en attente de transfert → transféré sortie → transféré entrée → terminé
- Génération automatique des mouvements d'entrée/sortie de transfert

### 6.4 Gestion des inventaires
- Inventaire planifié (par entrepôt/catégorie) + inventaire dynamique (par SKU)
- Enregistrement de la quantité comptable vs quantité réellement comptée
- Les écarts d'inventaire génèrent automatiquement des mouvements d'entrée/sortie de surplus/déficit

### 6.5 Alertes de stock
- Seuils haut/bas par SKU+entrepôt
- Enregistrement automatique d'un journal d'alerte en dessous du seuil bas / au-dessus du seuil haut

---

## 7. Module finance

### 7.1 Plan comptable
- Arbre des comptes : cinq grandes classes — actifs/passifs/capitaux propres/produits/charges
- Code de compte unique
- Sens du solde : débit/crédit

### 7.2 Pièces comptables
- Numéro de pièce, date, intitulé
- Comptabilité en partie double : chaque ligne contient un montant au débit et un montant au crédit (débit = crédit obligatoirement)
- Statuts : brouillon → validée

### 7.3 Grand livre
- Agrégation par compte comptable + période comptable (année/mois)
- Enregistrements : solde initial débiteur/créditeur, mouvements de la période débit/crédit, solde final débiteur/créditeur
- Solde final = solde initial ± mouvements de la période (selon le sens du solde du compte)
- Mise à jour automatique après validation de la pièce
- Filtres par année/mois/compte

### 7.4 Livre auxiliaire (grand livre détaillé)
- Chaque ligne de pièce d'un compte donné est enregistrée individuellement
- Contient : numéro de pièce, sens (débit/crédit), montant, solde, intitulé, date
- Recherche par compte + plage de dates
- Mise à jour synchronisée avec les lignes de pièces

### 7.5 Bilan
- Généré par période comptable (mensuelle/annuelle)
- Agrégation automatique des soldes du grand livre :
  - Comptes d'actif (1) → actif total = actif courant + actif non courant
  - Comptes de passif (2) → passif total = passif courant + passif non courant
  - Comptes de capitaux propres (3) → capitaux propres
  - Identité fondamentale : actif = passif + capitaux propres
- Prise en charge de la sauvegarde d'instantanés (données JSON complètes)
- Génération automatique depuis le grand livre en l'absence d'instantané

### 7.6 Tableau des flux de trésorerie
- Généré par période comptable (mensuelle/annuelle)
- Trois catégories :
  - Flux de trésorerie d'exploitation (encaissements de ventes - décaissements d'achats - dépenses)
  - Flux de trésorerie d'investissement
  - Flux de trésorerie de financement
- Solde de trésorerie initial/final = total des soldes initiaux/finaux de tous les comptes bancaires
- Généré automatiquement par agrégation du journal de caisse et banques
- Prise en charge de la sauvegarde d'instantanés (données JSON complètes)

### 7.7 Comptes à recevoir/à payer
- Générés automatiquement à partir des réceptions d'achat/expéditions de vente
- À recevoir : type=recevoir, lié au client, source=bon d'expédition de vente
- À payer : type=payer, lié au fournisseur, source=bon de réception d'achat
- Statuts : non lettré → partiellement lettré → lettré
- Aucune génération en double pour un même document source (protection d'idempotence)

### 7.8 Gestion des encaissements
- Comptes multiples (espèces/banque/WeChat/Alipay)
- Après validation, mise à jour automatique du solde du compte bancaire et du journal de caisse
- Lettrage : sélection d'une écriture à recevoir, saisie du montant à lettrer (sans dépasser le solde non lettré)
- Transition automatique du statut de lettrage partiel

### 7.9 Gestion des décaissements
- Même logique que les encaissements, sens inverse
- Lettrage des écritures à payer

### 7.10 Journal de caisse et banques
- Enregistrement de chaque entrée/sortie de fonds par compte + date
- Enregistrement du solde après variation
- Mise à jour en temps réel du solde du compte bancaire

### 7.11 Remboursement de frais
- Flux : soumission → approbation → paiement
- Lié au compte de charges
- Après paiement, génération automatique d'un bon de paiement + journal

### 7.12 Compte de résultat
- Agrégation mensuelle : chiffre d'affaires, coûts d'exploitation, charges, résultat
- Stockage d'instantanés des données (unicité year+month)

### 7.13 Amortissement des immobilisations
- Gestion du cycle de vie complet : acquisition → utilisation → amortissement → cession
- Méthode d'amortissement : linéaire ((valeur d'origine - valeur résiduelle) / nombre de mois d'utilisation)
- Amortissement provisionné mensuellement, génération automatique des écritures d'amortissement
- Enregistrements : valeur d'origine, valeur résiduelle, durée d'utilisation, amortissement mensuel, amortissement cumulé, valeur nette

### 7.14 Gestion fiscale
- Multi-taxes : TVA/impôt sur les sociétés/impôt sur le revenu des personnes physiques/droit de timbre
- Taux configurables librement
- Lié aux documents d'achat/vente, enregistrement automatique des montants de taxe

### 7.15 Multi-devises
- Gestion des devises : CNY/USD/EUR/JPY, etc.
- Identification de la devise de référence
- Taux de change gérés par date d'effet
- Prise en charge de la conversion des devises étrangères

### 7.16 Gestion budgétaire
- Élaboration du budget annuel : par centre de coûts + compte + mois
- Analyse comparative budget vs réel
- Calcul du taux d'exécution + analyse des écarts
- Statuts : brouillon → approuvé → en cours → clôturé

### 7.17 Centres de coûts/centres de profit
- Structure hiérarchique arborescente
- Regroupement des coûts + répartition des charges
- Comptabilité indépendante des centres de profit

---

## 8. Module CRM

### 8.1 Gestion des clients
- Fiche client liée aux clients des données de base
- Plusieurs contacts par client (marquage du contact principal)
- Téléphone/email des contacts stockés chiffrés

### 8.2 Enregistrements de suivi
- Modes de suivi : téléphone/visite/email/message/autre
- Enregistrement du contenu du suivi, du prochain suivi prévu et de sa date
- Lié au client, au contact, à l'opportunité

### 8.3 Campagnes marketing
- Cycle de vie complet de la campagne : planifiée → en cours → terminée → annulée
- Multi-canaux : email/SMS/téléphone/événement/réseaux sociaux
- Suivi des clients participants, statistiques de conversion
- Comparaison budget vs dépenses réelles

### 8.4 Tickets de service
- Gestion des tickets : à traiter → en cours → résolu → clôturé
- Priorités : basse/moyenne/haute/urgente
- Catégories : assistance technique/réclamation/consultation/retours/autre
- Attribution d'un responsable + réponses (publiques/notes internes)
- Statistiques de taux de résolution

### 8.5 Rapports d'analyse client
- 6 indicateurs clés : nouveaux clients/clients actifs/taux de rétention/panier moyen/CLV/taux de résolution des tickets
- Génération automatique des rapports (instantanés JSON)
- Prise en charge mensuel/trimestriel/annuel

### 8.6 Entonnoir de vente
- Configuration des étapes : premier contact (10 %) → confirmation du besoin (30 %) → proposition et devis (50 %) → négociation commerciale (70 %) → conclusion (100 %) → perdu (0 %)
- Opportunité : client, étape actuelle, montant estimé, probabilité de conclusion, date de conclusion prévue, responsable
- Statuts de l'opportunité : perdue/en cours/conclue
- Suivi des mouvements d'étapes

### 8.7 Réserve de clients (pool public)
- Réserve de clients : les clients sans propriétaire ou non suivis après expiration entrent automatiquement dans le pool
- Règles de récupération : délais de récupération automatique sans suivi selon le niveau du client
- Limite du nombre maximal de récupérations par personne, pour éviter l'immobilisation des ressources clients
- Les opérations de récupération/libération/recyclage sont toutes consignées
- Favorise l'activité de l'équipe commerciale et évite l'immobilisation des clients

### 8.8 Gestion des devis CRM
- Flux de devis interne au CRM, indépendant du module ventes
- Statuts : brouillon → envoyé → confirmé par le client → converti en contrat → expiré
- Prise en charge de la durée de validité du devis
- Prise en charge de la conversion directe en contrat (`to-contract`)
- Lié au client et à l'opportunité

### 8.9 Gestion des contrats
- Cycle de vie complet du contrat : brouillon → en attente d'approbation → approuvé → en cours → terminé/résilié
- Lié au client, à l'opportunité, au devis
- Détails du contrat : produit/quantité/prix unitaire/montant
- Enregistrement de la date de signature, des dates de début/fin
- Contenu des clauses (grand champ TEXT)
- Attribution d'un responsable

---

## 9. Module workflow d'approbation

### 9.1 Définition du workflow
- Nom du workflow, description, module applicable
- Configuration de chaînes d'approbation multi-nœuds
- Chaque nœud désigne un approbateur/rôle d'approbation et une stratégie d'approbation (co-signature/signature unique)

### 9.2 Flux d'approbation
- Soumission d'un document métier à l'approbation → création automatique d'une instance d'approbation
- Cheminement selon les nœuds prédéfinis, chaque approbateur traite à son tour
- Opérations d'approbation : soumission (initiée depuis le module métier), approbation, rejet, retrait
- Le résultat d'approbation rappelle le module métier pour mettre à jour le statut du document
- Ma liste d'approbations : en attente/approuvées

### 9.3 Enregistrements d'approbation
- Traçabilité complète de la chaîne d'approbation : chaque étape enregistre l'approbateur, l'opération, l'avis et l'heure
- L'instance d'approbation est liée au numéro de document métier

---

## 10. Module notifications de messages

### 10.1 Gestion des notifications
- Liste des notifications : ordre chronologique inverse, affichage paginé
- Types de notifications : notifications d'approbation, annonces système, alertes métier
- Marquage comme lu : par message / tout marquer comme lu
- Compteur de non-lues : nombre de messages non lus en temps réel

### 10.2 Modèles de notifications
- Modèles de notification prédéfinis (titre + espaces réservés de contenu)
- Catégories de modèles : approbation/alerte/système
- Paramètres de notification : préférences de canaux par utilisateur

### 10.3 Service de notifications
- Interface d'envoi unifiée NotificationService
- Prise en charge de l'extension multi-canaux (messagerie interne/email/SMS/WebSocket)

---

## 11. Module gestion de projets

### 11.1 Gestion des projets
- CRUD projet : nom, description, statut, dates de début/fin, responsable
- Statuts du projet : planification → en cours → terminé → archivé
- Gestion des membres du projet : ajout/suppression de membres

### 11.2 Gestion des tâches
- CRUD tâche : titre, description, priorité, statut, date limite
- Liée au projet, prise en charge des tâches parentes/enfants
- Statuts de la tâche : à démarrer → en cours → terminée → clôturée
- Affectation des tâches : désignation d'un responsable

### 11.3 Enregistrement des temps
- Saisie du temps par tâche : date, durée, description
- Statistiques des temps agrégés par projet

---

## 12. Module ressources humaines

### 12.1 Structure organisationnelle
- Gestion des départements : structure arborescente, nom du département, code, responsable, département parent
- Gestion des postes : nom du poste, code, département d'appartenance, statut

### 12.2 Gestion des employés
- Fiche employé : code, nom, sexe, téléphone (chiffré), email (chiffré), date d'embauche, département, poste
- Statuts : en poste/départ
- Lié au compte utilisateur système

### 12.3 Gestion du pointage
- Badgeage : pointage à l'arrivée, pointage au départ, enregistrement des heures
- Consultation du pointage : par employé + plage de dates
- Règles de pointage : horaires de travail, seuils de retard/départ anticipé

### 12.4 Gestion des congés
- CRUD congé : type (congé personnel/maladie/annuel, etc.), période, motif
- Flux d'approbation : soumission → approbation par le responsable du département → approuvé/rejeté
- Statuts : en attente d'approbation → approuvé → rejeté

### 12.5 Gestion de la paie
- Rubriques de paie : salaire de base/performance/indemnités/déductions, etc., mode de calcul
- Versement de la paie : génération mensuelle des bulletins de paie, liés à l'employé
- Statuts de versement : à verser → versé

---

## 13. Module production

### 13.1 BOM (nomenclature)
- Définition de la BOM : produit parent, matières enfants, quantité standard, unité, opérations
- Niveaux de BOM : prise en charge du développement multi-niveaux de la BOM
- Gestion des versions : historique des révisions de la BOM

### 13.2 Ordres de fabrication
- CRUD ordre de fabrication : produit, quantité planifiée, dates planifiées de début/fin
- Statuts : à produire → en production → terminé → clôturé
- Opérations de lancement/achèvement : enregistrement des heures réelles de début/fin
- Détails de production : liste de prise de matières (basée sur le développement de la BOM)

### 13.3 Gammes de fabrication
- Définition des gammes : produit, séquence d'opérations, temps standard de chaque opération
- Liées à la BOM et aux postes de travail

### 13.4 Postes de travail
- CRUD poste de travail : nom, code, type, capacité, statut
- Liés aux opérations des gammes

### 13.5 MRP (planification des besoins en matières)
- Plan MRP : calcul des besoins en matières à partir des commandes de vente/plans de production + BOM
- Génération automatique de suggestions d'achat (en cas de pénurie de matières premières) et de suggestions de production (en cas de pénurie de semi-finis)
- Détails MRP : matière, besoin brut, disponibilité du stock, besoin net, quantité de commande suggérée
- Statuts du plan : brouillon → généré → suggestions d'achat/production publiées

---

## 14. Module rapports personnalisés

### 14.1 Définition des rapports
- CRUD modèle de rapport : nom, description, ensemble de données, champs, conditions de filtre, type de graphique
- Ensemble de données : requête SQL prédéfinie ou méthode de modèle
- Champs du rapport : nom de colonne, nom d'affichage, type de données, tri
- Filtres : champ, opérateur, valeur par défaut

### 14.2 Exécution des rapports
- Exécution du rapport pour générer les données : application des filtres, du tri, pagination
- Affichage des résultats : tableau ou graphique (rendu côté frontend)
- Prise en charge de l'export

### 14.3 Planification
- Tâches planifiées de rapports : rapport désigné, fréquence d'exécution (cron), destinataires
- Statuts de la planification : activée/désactivée
- Consultation de l'historique d'exécution

---

## 15. Tableaux de bord

### 15.1 Vue d'ensemble de l'activité
- Ventes et achats du jour/du mois
- Total à recevoir/à payer, valeur totale du stock, marge brute
- Données mises en cache Redis 5 minutes

### 15.2 Tableau de bord des ventes
- Tendance des ventes, Top 10 des clients
- Analyse de conversion de l'entonnoir CRM

### 15.3 Tableau de bord des stocks
- Valeur totale du stock, statistiques d'alertes
- Tendance des entrées/sorties (par jour/sens)

### 15.4 Tableau de bord financier
- Total à recevoir/à payer, encaissements-décaissements du mois
- Synthèse des soldes de caisse et banques

---

## 16. Internationalisation (i18n)

### 16.1 Détection automatique de la langue
- Identification automatique via l'en-tête de requête `Accept-Language` (zh-CN → chinois, en → anglais)
- Le middleware Locale s'exécute en première position dans la chaîne de middlewares globaux
- Chaîne de repli : langue courante → fallback_locale configuré → renvoi de la clé d'origine

### 16.2 Fichiers de traduction
- Répertoire : `resource/translations/{locale}/`
- Messages généraux : `common.php` (41 clés : succès/échec/création/mise à jour/suppression/validation, etc.)
- Noms de modules : `modules.php` (69 clés : produits/achats/ventes/stocks/finance/CRM, etc.)
- Règles de validation : `validation.php` (11 règles + 10 libellés de champs)

### 16.3 Utilisation
- Dans les contrôleurs : `$this->trans('created')`
- Fonctions globales : `__('modules.product')`, `__m('finance')`
- Noms de modules : `__('modules.product')` → 商品 / Product

---

## 17. Fonctions d'export

### 17.1 Export Excel
- Toutes les pages de liste prennent en charge ?export=excel
- Génération .xlsx avec PhpSpreadsheet
- En-têtes bleu fond blanc + première ligne figée + largeur de colonne automatique
- Masquage automatique des champs sensibles

### 17.2 Export PDF
- Les panneaux de données du tableau de bord prennent en charge ?export=pdf
- Rendu Dompdf, A4 paysage
- Informations de droit d'auteur inamovibles
