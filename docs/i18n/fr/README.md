# Système ERP Open (open-erp)

ERP full-stack basé sur webman v2 + Flutter.

<div align="center"><img src="images/mascot.svg" alt="Mascotte poulpe d'open-erp Xiao Bazhua" width="150"></div>

<div align="center">🌐 [中文](../../../README.md) | [English](../en/README.md) | [한국어](../ko/README.md) | [Русский](../ru/README.md) | [Deutsch](../de/README.md) | Français | [Español](../es/README.md) | [Português](../pt/README.md) | [हिन्दी](../hi/README.md) | [العربية](../ar/README.md) | [বাংলা](../bn/README.md) | [Bahasa Indonesia](../id/README.md) | [日本語](../ja/README.md)</div>

> [Version anglaise](../en/README.md) | [Comparaison des éditions](EDITIONS.md) | [Diagramme d'architecture](ARCHITECTURE.md) | [Diagramme d'architecture système](#diagramme-darchitecture-système) | [Document de conception](DESIGN.md) | [Architecture de sécurité](SECURITY.md) | [Référence API](API.md) | [Manuel des fonctionnalités](FUNCTIONS.md)

## Présentation du projet

open-erp est un **système ERP full-stack open source** destiné aux PME, couvrant les achats-ventes-stocks (achats / ventes / stocks), la comptabilité, la production (BOM/MRP/déclaration d'opérations/charge de capacité), le CRM, le workflow d'approbation, les ressources humaines, les notifications et les rapports personnalisés. Le backend repose sur webman v2 + MySQL 8.0 (préfixe de table `erp_`, clé primaire globale unique Snowflake) ; le côté administration propose trois implémentations — Angular 22 (`apps/angular/`), React 19 + Vite (`apps/react/`) et Flutter 3.x Web (`apps/flutter/`) — complétées par un client natif HarmonyOS (`apps/harmonyos/`) pour le mobile.

Le système est conçu autour du principe **piloté par les documents, avec interconnexions automatiques** : la validation d'un document métier déclenche automatiquement les mouvements de stock, la génération des comptes à recevoir/à payer et l'imputation des coûts ; le workflow d'approbation et les notifications traversent tous les documents clés ; le MRP calcule les besoins en matériaux à partir des commandes de vente et des nomenclatures et génère des propositions d'achat/production, formant une boucle métier de bout en bout, de la prise de commande à la réception d'achat, et du lancement en production à la clôture comptable.

## Description du projet

- **Calcul décimal exact** : les valeurs métier (montants, quantités, pondérations) reposent sur l'arithmétique décimale bcmath ; le coût moyen pondéré mobile, le lettrage des comptes à recevoir/à payer et les états sortent en précision chaîne, sans erreur de virgule flottante
- **Socle de sécurité d'entreprise** : jeton JWT + authentification RBAC au niveau des méthodes, défense en profondeur (panorama L0–L12 + 35 détecteurs d'attaque + chaîne de 7 middlewares : XSS/injection SQL/CSRF/limitation de débit/CSP, etc.), chiffrement du stockage des champs sensibles et chiffrement du transport des interfaces, traçabilité complète des opérations
- **Capacités configurables** : workflow d'approbation multi-niveaux (avec canevas de conception visuelle), moteur de modèles d'impression de documents (rendu par placeholders + PDF via dompdf + étiquettes avec QR code), blocage en temps réel du crédit client, traçabilité avant/arrière de bout en bout par lots et numéros de série
- **Données traçables** : chaque mouvement métier est enregistré ligne par ligne, les lots et numéros de série couvrent tout le cycle de vie entrée→prélèvement→sortie→traçabilité, et le calcul des coûts descend au niveau de la ligne de document
- **Déploiement simple** : démarrage en une commande avec Docker Compose v2 (MySQL/Redis/Elasticsearch), ou exécution directe en local après `composer install`
- **Internationalisation** : 13 langues (zh/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id), couvrant les messages backend et les interfaces des deux consoles Angular/React ; les dictionnaires frontend sont chargés paresseusement par langue, et le README est également disponible en 12 langues

## Liste des fonctionnalités

| Domaine métier | Fonctionnalité | Description |
|--------|------|------|
| 🔐 Authentification | Connexion / inscription / rafraîchissement de jeton / déconnexion | Captcha à clic + JWT + liste noire |
| | Verrouillage de compte | 5 échecs → verrouillage 15 minutes |
| | Limite de sessions simultanées | 3 jetons valides maximum par utilisateur |
| 📊 Tableau de bord | Vue d'ensemble + six tableaux de bord (ventes / stocks / finances / OMS / WMS / TMS) | Cache Redis 5 minutes |
| 👥 Gestion des utilisateurs | CRUD + suppression en masse / activation-désactivation | Suppression logique + double confirmation du mot de passe |
| | Import Excel en masse | Validation ligne par ligne + rapport d'erreurs |
| 🔒 Rôles et permissions | CRUD des rôles + arbre de permissions | RBAC granularité method.path |
| ⚙ Configuration système | CRUD clé-valeur | Gestion par groupes |
| 📋 Audit des opérations | Consultation des journaux + détection de la source | Reconnaissance automatique de 8 plateformes |
| 📁 Gestion des fichiers | Téléversement / export Excel / export PDF | Masquage automatique des données sensibles |
| 🛡 Protection de sécurité | 35 détecteurs d'attaque + chaîne de 7 middlewares | XSS / injection SQL / traversée de chemin / injection de commande / CSRF / limitation de débit / CSP… |
| 🏥 Exploitation | Vérification de santé / metrics / documentation API / security.txt | Prometheus + OpenAPI 3.0 |
| 📦 Gestion des produits | Fiche produit / SKU / multi-spécifications / multi-unités / catégories / marques / stratégie de prix | Arbre de catégories multi-niveaux + conversion multi-unités |
| | Entrepôts et emplacements | Gestion multi-entrepôts et multi-emplacements |
| | Fiches fournisseurs / clients | Contacts / comptes bancaires / limites de crédit |
| 📥 Gestion des achats | Demande → commande → réception → retour → règlement | Processus d'achat complet + approbation |
| | Sourcing (appel d'offres → devis → attribution en commande) | Comparaison entre plusieurs fournisseurs, les devis doivent couvrir toutes les lignes de l'appel d'offres, attribution convertie en commande d'achat en un clic |
| | Évaluation des fournisseurs | Note totale 0–100 avec classement automatique (A ≥ 90 / B ≥ 70 / C) + dimensions d'évaluation en JSON + traçabilité de l'évaluateur |
| 📤 Gestion des ventes | Devis → commande → expédition → retour → règlement | Conversion devis en commande + marge brute des ventes |
| | Contrôle du crédit client | Gestion des limites / délais de paiement / gel + blocage des commandes et expéditions hors limite ou en retard |
| 🏗 Gestion des stocks | Stock en temps réel / lots / numéros de série / transferts / inventaire / alertes | Évaluation du coût moyen pondéré mobile |
| 💰 Gestion financière | Comptes clients / fournisseurs / encaissements / décaissements / journaux / notes de frais / compte de résultat / immobilisations / fiscalité / multi-devises / budgets / centres de coût et de profit | Génération automatique des comptes à recevoir et à payer + rapprochement + gestion financière complète |
| | Multi-organisation + états consolidés | Comptabilité multi-sociétés + écritures d'élimination (mise en équivalence / méthode du coût) |
| | Valorisation des stocks / coûts de production | Sortie de matières → collecte main-d'œuvre / frais généraux → coûts des produits finis → transfert des écarts de coûts |
| | Effets de commerce + rapprochement bancaire | Registre des effets + rapprochement automatique sur import de relevé bancaire |
| | Pool de factures d'achat + facturation électronique | Gestion des factures d'achat + canal d'émission (adaptateur + canal Mock) |
| 🤝 CRM | Clients / contacts / suivi / campagnes marketing / tickets de service / rapports d'analyse / entonnoir de vente / pool commun / devis / contrats | Gestion du cycle de vie complet du client |
| | Moteur de valeur client | Programmes membres prépayés / points / coupons |
| ✅ Workflow d'approbation | Définition des workflows / soumission / approbation / refus / retrait / mes approbations | Moteur de workflow multi-étapes |
| | Concepteur de workflow visuel | Configuration sur canevas des nœuds / branches / retours, réutilise le moteur d'approbation |
| 🔔 Notifications | Liste / marquer comme lu / compteur non lu / tout marquer comme lu | Envoi en temps réel et suivi des états |
| | Notifications multicanal | Pilotes de canaux SMS / e-mail (canal Mock + journaux + relances) |
| 📐 Gestion de projets | Projets / tâches / relevés de temps | Suivi de l'avancement et gestion des ressources |
| | Coûts et budget de projet | Heures × taux → collecte des coûts projet + écarts budgétaires |
| 👤 Ressources humaines | Départements / employés / postes / pointage / congés / salaires | Gestion complète du personnel |
| | Recrutement / performance / formation / sécurité sociale | Entonnoir de recrutement + évaluation KPI/360 + crédits de formation + règles d'assiette de cotisations et bulletin de salaire |
| 🏭 Production | Nomenclature / ordres de fabrication / gammes / postes de travail / MRP | Planification des besoins en matières et exécution de la production |
| | Comptes rendus d'opération / salaire à la pièce / régularisation sous-traitance | Exécution des opérations MES + sortie de matières et régularisation des ordres de sous-traitance |
| | Analyse de charge de capacité | Calendrier des postes de travail + rapport de charge brute |
| | Traçabilité lots / numéros de série | Chaîne de traçabilité amont / aval + alerte de péremption |
| 📈 Rapports personnalisés | Modèles / jeux de données / champs / filtres / exécution / planification | Constructeur de rapports visuel |
| 📋 Gestion des commandes (OMS) | Commandes multi-canaux / orchestration de l'exécution / pré-réservation de stock / allocation / annulation / retours RMA | Gestion du cycle de vie complet des commandes |
| 🏗 Gestion d'entrepôt (WMS) | Zones / emplacements / ASN / réception / mise en rayon / vagues / préparation / emballage / expédition | Processus d'exploitation d'entrepôt complet |
| 🚚 Gestion du transport (TMS) | Transporteurs / services / tarifs / connaissements / suivi logistique / factures de fret | Comparaison des tarifs multi-transporteurs + suivi des colis |
| 🛠 Gestion des équipements (EAM) | Fiches équipements / plans de maintenance / ordres de réparation / pièces détachées | Gestion du cycle de vie complet des équipements |
| | Inspection par scan en boucle fermée | Scan d'inspection, les anomalies déclenchent automatiquement un ordre de maintenance |
| 🌐 Plateforme et ouverture | Versionnage des chemins d'API | Admin /admin/v1, client /api/v1, open /open/v1 (sans en-tête de version) |
| | Moteur de gabarits d'impression | Rendu par espaces réservés + PDF dompdf + étiquettes QR |
| | Champs personnalisés de formulaires | Extension JSON custom_fields des tables maîtres + validation |
| | Architecture multi-tenant | Tenant erp_tenant + contexte de requête TenantScope + facturation à expiration (seam middleware réservé, non enregistré) |

## Modules ERP

Flux de données entre les modules métier :

- Réception d'achat → entrée en stock automatique (coût moyen pondéré mobile) → génération automatique des comptes fournisseurs
- Expédition de vente → sortie de stock automatique → génération automatique des comptes clients
- Encaissements / décaissements → rapprochement des comptes à recevoir et à payer → mise à jour des journaux
- Validation des écritures → mise à jour automatique du grand livre (résumé par compte) + livre auxiliaire (enregistrement détaillé)
- Bilan → génération automatique à partir des soldes de fin de période du grand livre
- Tableau des flux de trésorerie → génération automatique à partir des journaux de caisse et de banque (trois catégories : exploitation / investissement / financement)
- Workflow d'approbation → soumission des documents métier → circulation multi-étapes → retour du résultat à l'approbation du module métier
- Notifications → déclenchement par approbation / alerte / événement système → envoi en temps réel → l'utilisateur marque comme lu
- MRP → basé sur les commandes de vente + nomenclature → calcul des besoins en matières → génération de suggestions d'achat / de production
- OMS → import des commandes multi-canaux → pré-réservation de stock (ATP) → création de l'exécution → envoi de la préparation / de l'emballage au WMS
- WMS → agrégation en vagues → tâches de préparation → confirmation de préparation → emballage terminé → déclenchement de la création du connaissement TMS
- TMS → comparaison des tarifs de fret → création du connaissement → confirmation d'expédition (stockOut + comptes clients) → suivi logistique → signature de réception
- Entrée WMS → ASN (pré-arrivée) → réception → contrôle qualité → confirmation de mise en rayon (stockIn + comptes fournisseurs) → mise à jour du stock
- RMA → demande de retour → approbation → retour en stock → remboursement

## Pile technique

| Couche | Technologie | Description |
|---|------|------|
| Framework backend | webman v2 (workerman) | Framework PHP haute performance à processus résidents |
| Version PHP | 8.3+ | |
| Base de données | MySQL 8.0+ | Préfixe de table `erp_`, clés primaires BIGINT non auto-incrémentées |
| Moteur de recherche | Elasticsearch | Synchronisation automatique de l'index à l'écriture/à la suppression via `webman-scout` (composant optionnel, voir la section « Recherche plein texte ») |
| Frontend d'administration | Flutter 3.x | Style console d'administration PC sur le Web (`apps/flutter/`) |
| Application mobile | HarmonyOS ArkTS | Client natif HarmonyOS (`apps/harmonyos/`), prend en charge téléphone / tablette / 2-en-1 |

## Dépendances principales

| Paquet | Usage |
|---|------|
| `erikwang2013/snowflake-php` | Algorithme Snowflake pour générer des clés primaires BIGINT uniques globales |
| `erikwang2013/hashids` | Chiffrement / déchiffrement des ID au niveau API, masque les vrais ID de base de données |
| `erikwang2013/jwt-webman` | Émission et validation des jetons d'authentification JWT |
| `erikwang2013/encryption` | Chiffrement / déchiffrement des données sensibles au niveau de la couche de transport |
| `erikwang2013/encryptable` | Chiffrement / déchiffrement automatique des champs sensibles au niveau de la couche de stockage |
| `erikwang2013/webman-scout` | Synchronisation Elasticsearch et recherche plein texte |
| `erikwang2013/season` | Données des drapeaux nationaux |
| `erikwang2013/poster-php` | Génération et validation du captcha à clic + génération d'affiches |
| `erikwang2013/security-php` | Contrôles des outils de sécurité |
| `phpoffice/phpspreadsheet` | Export Excel |
| `barryvdh/laravel-dompdf` | Export PDF (basé sur Dompdf) |
| `erikwang2013/apidoc-php` | Génération automatique de la documentation API | Documentation d'interface annotée, groupée par admin / client |

## Internationalisation

| Niveau | Emplacement du dictionnaire | Volume |
|---|---------|------|
| Messages backend | `resource/translations/{langue}/` | 13 répertoires de langue : `zh_CN` 565 entrées, les 11 autres langues 544 entrées chacune, `en` 30 entrées (décompte : entrées feuilles des trois fichiers `common/modules/validation` ; les libellés de champs de `attributes` dans `validation.php` sont comptés, leurs clés de groupe non) |
| Console Angular | `apps/angular/src/app/core/zh-*.ts` (dictionnaire source `zh-en/`, fusion de 4 fragments) | Dictionnaire source 1456 clés × 11 nouvelles langues (nombre de clés identique à la source pour chaque langue) |
| Console React | `apps/react/src/lib/i18n/zh*.ts` | Dictionnaire source 1451 clés × 11 nouvelles langues |

## Structure du projet

```
open-erp/
├── app/
│   ├── admin/controller/       # 系统管理控制器 (16 个)
│   ├── api/v1/controller/      # 客户端 API（版本置于路径 /api/v1，无版本请求头）
│   ├── controller/             # 业务模块控制器 (139 个，23 域)
│   │   ├── product/            # 商品/分类/品牌/仓库/库位/供应商/客户 (8 个)
│   │   ├── purchase/           # 采购申请/订单/收货/退货/结算/询价/报价/供应商评估 (8 个)
│   │   ├── sales/              # 销售报价/订单/发货/退货/结算 (5 个)
│   │   ├── inventory/          # 库存/流水/调拨/盘点/预警 (6 个)
│   │   ├── finance/            # 应收应付/凭证/收付款/日记账/总账/明细账/报表/资产/税务/多币种/预算/成本利润中心/票据/对账/发票 (28 个)
│   │   ├── crm/                # 商机/跟进/漏斗/联系人/公海池/合同/报价/营销/工单/分析 (10 个)
│   │   ├── workflow/           # 工作流定义/审批/流程设计器 (3 个)
│   │   ├── notification/       # 站内通知/渠道发送 (2 个)
│   │   ├── project/            # 项目/任务/工时/成本 (4 个)
│   │   ├── hr/                 # 部门/员工/职位/考勤/请假/薪资/招聘/绩效/社保/培训 (9 个)
│   │   ├── manufacturing/      # BOM/工单/工艺/工作站/MRP/报工/委外/成本/产能 (13 个)
│   │   ├── report/             # 报表模板/数据集/执行/定时调度 (2 个)
│   │   ├── print/              # 打印模板引擎 (1 个)
│   │   ├── retail/             # 会员储值/积分/卡券 (2 个)
│   │   ├── platform/           # 多租户/自定义字段 (2 个)
│   │   ├── quality/            # 质检 (5 个)
│   │   ├── eam/                # 设备/保养/维修/备件/点检 (5 个)
│   │   ├── bi/                 # 商业智能 (3 个)
│   │   ├── dms/                # 文档管理 (2 个)
│   │   ├── oms/                # OMS订单/履约/RMA/渠道 (4 个)
│   │   ├── wms/                # 库区/库位/ASN/收货/上架/波次/拣货/打包 (8 个)
│   │   ├── tms/                # 承运商/服务/费率/运单/轨迹/运费发票 (6 个)
│   │   └── open/               # 开放平台接口 (1 个)
│   ├── service/                # 业务逻辑层 (64 个)
│   │   ├── inventory/          # 出入库 + 移动加权平均成本核算 + 库存预占/ATP
│   │   ├── finance/            # 应收应付自动生成 + 核销
│   │   ├── notification/       # 通知发送服务
│   │   ├── oms/                # 订单编排/库存分配/RMA生命周期
│   │   ├── wms/                # 入库流程(ASN→收货→上架) / 出库流程(波次→拣货→打包)
│   │   └── tms/                # 运单管理/运费比价/物流轨迹
│   ├── model/                  # 224 个 Eloquent 模型（多模块共用）
│   ├── middleware/             # 11 个中间件（ApiVersion 已移除，版本走路径）
│   ├── common/                 # Hashids/Snowflake/Encryption 服务
│   └── queue/                  # 队列任务
├── apps/
│   ├── angular/                # Angular 22 管理端（config 驱动资源页，ng serve :4200）
│   ├── react/                  # React 19 + Vite 管理端（Vite :5173）
│   ├── flutter/                # Flutter 跨平台（Web PC + iOS/Android/macOS/Windows/Linux）
│   └── harmonyos/              # HarmonyOS 原生客户端
├── config/                     # 配置文件（含中文注释）
│   ├── plugin/erikwang2013/apidoc/ # API 文档配置
├── database/
│   ├── install.sql              # 完整安装SQL（227张表 + 种子数据）
│   ├── e2e-seed.sql             # E2E/CI 最小种子
│   └── backup/                 # 备份/恢复脚本
├── docs/                       # 架构、设计、安全、API 文档
├── tests/                      # PHPUnit 测试（<!-- stats:test_files=111 --> 个测试文件，<!-- stats:tests=1008 --> 个测试方法，<!-- stats:assertions=4768 --> 条断言）
├── resource/
│   └── translations/           # 13 语种后端消息词典 (zh_CN/en/ja/ko/de/fr/es/pt/ru/ar/hi/bn/id)
│       ├── zh_CN/              # 中文翻译 (565 条)
│       ├── en/                 # 英文即 key，仅框架规则名等 30 条
│       └── ja|ko|de|.../       # 其余 11 语种各 544 条（生成器 scripts/gen-be-locales.mjs）
├── public/                     # 公共入口
├── runtime/                    # 运行时文件
└── vendor/                     # Composer 依赖
```

## Diagramme d'architecture système

> Cliquez sur l'image pour voir le SVG original. Les diagrammes utilisent des noms en anglais et présentent clairement l'architecture du système à tous les niveaux.

### Topologie du système

![System Architecture](diagrams/system-architecture-cn.svg)

**Architecture en cinq couches** : couche client → couche de périphérie de passerelle (reverse proxy Nginx) → couche applicative (webman v2 + chaîne de middleware + authentification et autorisation + logique métier + services communs) → couche de stockage de données (MySQL + Redis + Elasticsearch) → couche d'exploitation (CI/CD + Docker + Prometheus)

### Diagramme de flux de données métier

![Business Flowchart](diagrams/business-flowchart-cn.svg)

**Interconnexion des sept domaines métier** : les achats → stocks → ventes → finances forment la boucle centrale de la chaîne d'approvisionnement ; la gestion de la relation client pilote les ventes ; le MRP de production planifie les achats et la production à partir des commandes de vente et des nomenclatures ; le workflow d'approbation, les notifications, la gestion de projets et les ressources humaines sont des modules de support qui traversent tout le processus.

### Vue d'ensemble des modules fonctionnels

![Functional Modules](diagrams/functional-modules-cn.svg)

**23 domaines métier, 227 tables de données, 159 contrôleurs** : couvrent l'authentification et la sécurité, le tableau de bord, l'administration système, la protection de sécurité, la supervision d'exploitation, la gestion des produits, les achats, les ventes, les stocks, la finance (14 sous-modules), le CRM (10 sous-modules), le workflow d'approbation, les notifications, la gestion de projets, les ressources humaines, la production (MRP), les rapports personnalisés, la gestion des commandes (OMS), la gestion d'entrepôt (WMS), la gestion du transport (TMS), la gestion de la qualité (QMS), la gestion des équipements (EAM), la gestion documentaire (DMS), les tableaux de bord BI.

### Cycle de vie d'une requête

![Request Lifecycle](diagrams/request-lifecycle-cn.svg)

**Chemin complet d'une requête du client à la base de données** : client (Angular/React/Flutter/HarmonyOS) → terminaison SSL Nginx → gestion CORS → filtre de sécurité → limitation de débit → [Admin : authentification JWT → permissions RBAC → journal des opérations] → contrôleur → couche de services → couche de modèles → cache / base de données / moteur de recherche → réponse JSON. Le diagramme inclut les deux chemins : cache hit et cache miss. (La version des interfaces est intégrée au chemin d'URL, sans étape de validation distincte ; la langue est résolue par `app/common/I18n.php` à partir de `Accept-Language`.)

### Architecture de défense en profondeur

![Security Architecture](diagrams/security-architecture-cn.svg)

**Panorama de la défense en profondeur (L0–L12)** : L0 réseau physique → L1 sécurité du transport → L2 en-têtes de sécurité HTTP → L3 validation des requêtes → L4 assainissement des entrées → L5 protection CSRF → L6 limitation de débit → L7 authentification (JWT + captcha + liste noire + contrôle de session) → L8 autorisation RBAC → L9 protection des données (chiffrement du transport + chiffrement du stockage + obscurcissement des ID + masquage des données) → L10 audit et supervision → L11 divulgation de conformité → L12 observabilité (traçage distribué X-Trace-Id + métriques métier + audit renforcé). La chaîne exécutable des 7 middlewares est décrite dans `docs/SECURITY.md` ; les 35 détecteurs d'attaque dans `config/plugin/erikwang2013/security-php/app.php`.

---

## Prérequis

- PHP >= 8.3
- Composer 2.x
- MySQL >= 8.0
- Flutter >= 3.41 (requis uniquement pour le développement frontend)
- Node >= 22.22.3 (requis uniquement pour le développement des consoles d'administration Angular/React ; borne inférieure `engines` d'Angular CLI 22)
- Elasticsearch >= 7.x ou OpenSearch >= 2.x (optionnel, nécessaire pour la synchronisation de l'index ; sans installation, la lecture et l'écriture métier restent possibles)
- DevEco Studio (optionnel, requis uniquement pour compiler le client HarmonyOS ; en ligne de commande, `hvigorw assembleHap` convient aussi)

## Domaine local par défaut

Le projet utilise par défaut le domaine local **`http://erp.test`** (adresse API par défaut du client Flutter, convention d'entrée Web du backend ; le client HarmonyOS pointe par défaut vers l'hôte de l'émulateur `http://10.0.2.2:8788`).

- **Accès local** : ajoutez une ligne `127.0.0.1 erp.test` dans le fichier hosts, et faites pointer le serveur Web / le proxy inverse vers le port d'écoute du backend (par défaut `8788`, voir `APP_HTTP_PORT` dans `.env`, modifiable dans l'assistant d'installation ou dans `.env` ; le WebSocket utilise par défaut `8282`, soit `APP_WS_PORT`).
- **Changer le domaine de déploiement** :
  - Injection au build Flutter : `flutter build web --dart-define=API_BASE_URL=https://votre-domaine`
  - HarmonyOS : modifiez le `BASE_URL` de `apps/harmonyos/entry/src/main/ets/utils/Config.ets` (constante en lecture seule, par défaut `http://10.0.2.2:8788`)
  - Pour déboguer sur émulateur, on peut revenir temporairement à `http://10.0.2.2:8788` (accès à la machine hôte)
- Toutes les versions d'API figurent déjà dans le chemin (`/admin/v1`, `/api/v1`, `/open/v1`), le client n'a donc qu'à configurer l'adresse racine.

## Démarrage rapide

### 1. Installer les dépendances

```bash
composer install
```

### 2. Configurer les variables d'environnement

Copiez et modifiez les variables d'environnement (optionnel — sans configuration, les valeurs par défaut de `config/*.php` sont utilisées) :

```bash
cp .env.example .env
```

Paramètres clés :

| Variable d'environnement | Description | Valeur par défaut |
|---------|------|--------|
| `JWT_SECRET_KEY` | Clé de signature JWT (`env_required` : absente/vide ou valeur d'exemple faible → démarrage refusé) | `.env.example` fournit une valeur aléatoire de 48 caractères |
| `HASHIDS_SALT` | Sel Hashids (`env_required`) | `.env.example` fournit une valeur aléatoire de 48 caractères |
| `ENCRYPTION_KEY` | Clé principale du chiffrement de la couche transport et de la couche stockage (`env_crypto_key` : AES-256 exige 32 octets, toute autre longueur → démarrage refusé) | `.env.example` fournit une valeur aléatoire de 32 caractères |
| `SNOWFLAKE_DATACENTER_ID` | ID du centre de données (0-31) | `1` |
| `SNOWFLAKE_WORKER_ID` | ID du nœud de travail (0-31) | `1` |
| `SCOUT_HOSTS` | Adresse ES | `http://localhost:9200` |
| `APP_HTTP_PORT` / `APP_WS_PORT` | Ports d'écoute HTTP/WebSocket du backend (le proxy inverse type Nginx pointe dessus) | `8788` / `8282` |
| `ANGULAR_DEV_PORT` / `REACT_DEV_PORT` | Ports des serveurs de développement frontend (`npm run dev`, développement uniquement) | `4200` / `5173` |
| `NGINX_PORT` / `NGINX_SSL_PORT` / `MYSQL_PORT` / `ES_PORT` | Ports publiés sur l'hôte par docker-compose (les ports dans les conteneurs sont fixes) | `80` / `443` / `3306` / `9200` |

**En production, remplacez impérativement toutes les clés par des chaînes aléatoires** (`JWT_SECRET_KEY` / `ENCRYPTION_KEY` / `HASHIDS_SALT` etc. : absente, vide ou encore une valeur d'exemple faible du type `change-me`/`xxx` → le démarrage est refusé par `env_required` / `env_crypto_key`, sans dégradation silencieuse ; `ENCRYPTION_KEY` fait en outre l'objet d'un contrôle de longueur strict (AES-256 exige 32 octets, toute autre longueur provoque une erreur au démarrage)) :

### 3. Initialiser la base de données

**Méthode 1 : Assistant d'installation Web (recommandé)**

Après le démarrage du service, accédez à `http://localhost:8788/install` et suivez les 4 étapes : vérification de l'environnement → configuration de la base de données → compte administrateur → installation en un clic. L'étape de configuration de la base de données propose une case **importer des données de démonstration** (produits/spécifications/SKU/clients/fournisseurs, plage d'ID 41…, supprimable par plage) ; décochée par défaut — ne pas la cocher en production.

**Méthode 2 : Import en ligne de commande**

```bash
mysql -u root -p nom_de_la_base < database/install.sql
```

`install.sql` est une base complète en un seul fichier et contient la structure des 227 tables ainsi que les données initiales.

**Méthode 3 : Environnement Docker**

```bash
```

### 4. Démarrer le service

```bash
php start.php start
```

Écoute par défaut sur `http://0.0.0.0:8788`.

### 5. Démarrer le frontend (optionnel)

**Console d'administration Flutter (Web) :**

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (style console d'administration PC)
```

**Client HarmonyOS (mobile) :**

Ouvrez le répertoire `apps/harmonyos/` avec DevEco Studio et exécutez sur un appareil réel ou un émulateur.

### 6. Déploiement en un clic avec Docker Compose (recommandé pour la production)

Le projet fournit une orchestration Docker complète avec 5 services : Nginx, PHP (application webman), MySQL, Redis, Elasticsearch.

```bash
# 1. Configurer les variables d'environnement Docker
cp .env.docker .env

# 2. Remplacer les clés de remplacement par des valeurs aléatoires (JWT_SECRET_KEY/ENCRYPTION_KEY/HASHIDS_SALT etc., idempotent)
bash scripts/gen-env-keys.sh .env

# 3. Démarrer tous les services (Docker Compose v2 requis : `docker compose` ; la v1 est obsolète et incompatible avec le protocole http+docker)
docker compose up -d

# 4. Vérifier l'état des services (MySQL importe automatiquement database/install.sql au premier démarrage, aucune initialisation manuelle)
docker compose ps --format "table {{.Name}}\t{{.Status}}"

# 5. Accéder (Nginx publie ${NGINX_PORT:-80} ; webman 8788 reste sur le réseau interne du conteneur, derrière le reverse proxy Nginx)
# http://localhost

# Astuce de réinitialisation : si le démarrage a déjà eu lieu avec un ancien .env (le volume de données a figé les anciens mots de passe / l'ancien schéma), supprimez d'abord le volume :
# docker compose down -v   (⚠️ supprime les données MySQL/Redis/ES, à n'utiliser que pour un premier dépannage)
```

- `Dockerfile` : PHP 8.3 + OPcache + Composer, basé sur `php:8.3-cli-alpine`
- `docker-compose.yml` : orchestration des 5 services, isolation réseau, volumes de données persistants
- `.env.docker` : variables d'environnement dédiées à Docker

## Utilisation

### 1. Connexion

Lors de la première utilisation, ouvrez l'installeur web `http://localhost:8788/install` pour terminer l'installation et créer un compte administrateur. Une fois installé, ouvrez la console, saisissez vos identifiants et validez le captcha à clic pour vous connecter.

### 2. Navigation

Après connexion, accédez aux modules via la barre latérale : tableau de bord, produits, achats, ventes, stocks, finances, CRM, flux d'approbation, notifications, projets, RH, fabrication, rapports personnalisés, OMS/WMS/TMS, tableaux de bord BI et administration système (utilisateurs/rôles/config/journaux). La barre latérale est fixe sur ordinateur et se replie en tiroir sur mobile.

### 3. Permissions et sécurité

- Les fonctions et API sont contrôlées par RBAC ; les menus et interfaces sans permission sont inaccessibles (403)
- Les opérations sensibles (suppression d'utilisateur/rôle) nécessitent de confirmer le mot de passe courant dans le corps de la requête
- Après déconnexion, le jeton est immédiatement mis sur liste noire

### 4. Multilingue

Bascule automatique via l'en-tête `Accept-Language` (zh-CN / en), le chinois par défaut.

### 5. Recherche plein texte (optionnelle)

La synchronisation de l'index passe par `erikwang2013/webman-scout` (dès qu'un modèle utilise le trait `Searchable`, l'index est mis à jour automatiquement à l'enregistrement). **Elasticsearch** et **OpenSearch** sont pris en charge.

**Périmètre de l'index** : les 224 modèles de `app/model/` portent `Searchable` ; à l'écriture et à la suppression logique, le `ModelObserver` synchronise l'index. Pour AdminUser, Customer, Product et Supplier, un `toSearchableArray()` dédié ne met en index que les champs de la liste blanche ; tous les autres modèles sont indexés en entier (ligne complète).

**Un moteur injoignable n'empêche pas l'écriture des données métier** (testé : en pointant le pilote vers un port injoignable, `save()` réussit toujours — seul un délai d'attente de connexion s'ajoute) — le moteur de recherche est un composant optionnel, tout le métier tourne sans lui.

**Précision de périmètre** : le projet n'intègre pour l'instant que la **synchronisation de l'index** (écriture/suppression logique) ; aucune interface ni écran de recherche n'est fourni. Les filtres des listes passent par des requêtes `where` côté backend et non par le moteur de recherche.

## Règles de base de données

- **Préfixe de table** : `erp_`
- **Clé primaire** : la clé primaire de toutes les tables est `id BIGINT UNSIGNED NOT NULL`, **AUTO_INCREMENT interdit**
- **Génération des ID** : les clés primaires sont générées par `SnowflakeService::generate()` au niveau applicatif, uniques en environnement distribué
- **Champs obligatoires** : chaque table doit contenir `id`, `created_at`, `updated_at`
- **Suppression logique** : les tables nécessitant une suppression logique ajoutent `deleted_at DATETIME DEFAULT NULL`
- **Champs sensibles** : numéro de téléphone, e-mail, numéro de carte d'identité, etc. chiffrés / déchiffrés automatiquement via le plugin `encryptable` ; le champ de base de données utilise `VARCHAR(500)` pour stocker le texte chiffré

## Règles API

### Documentation API

Le projet utilise `erikwang2013/apidoc-php` pour générer automatiquement la documentation des interfaces, accessible sur `/apidoc`.

- Interfaces d'administration (Admin) : 25 groupes de modules, avec paramètres de requête et structures de réponse complets
- Interfaces client (Service API) : 3 groupes — authentification / captcha / produits
- Toutes les interfaces indiquent les en-têtes globaux : authentification JWT, internationalisation, etc.

### Format de réponse unifié

```json
{
    "code": 0,
    "message": "success",
    "data": {}
}
```

### Codes d'erreur métier

| Code d'erreur | Signification | Description |
|-------|------|------|
| `0` | Succès | |
| `400` | Erreur de paramètre de requête | |
| `401` | Non authentifié (jeton invalide ou expiré) | |
| `403` | Pas d'autorisation / interception de sécurité | Échec RBAC / détection d'attaque SecurityFilter |
| `404` | Ressource inexistante | |
| `422` | Échec de validation des paramètres | |
| `413` | Corps de requête trop volumineux | Déclenché par SecurityFilter, au-delà de 10 Mo |
| `405` | Méthode de requête non autorisée | Déclenché par SecurityFilter, seuls GET/POST/PUT/DELETE/OPTIONS/HEAD sont autorisés |
| `415` | Type de média non pris en charge | Déclenché par SecurityFilter, Content-Type non JSON |
| `429` | Trop de requêtes | Déclenché par RateLimit / verrouillage de compte (5 échecs de connexion → verrouillage 15 minutes) |
| `500` | Erreur interne du serveur | |

### Internationalisation

L'en-tête de requête `Accept-Language` change automatiquement la langue (zh-CN → Chinois, en → Anglais), le chinois étant la langue par défaut.

### Traitement des ID

- **ID dans les requêtes / réponses** : chiffrés en chaîne via hashids, les vrais ID de base de données ne sont jamais exposés
- **Chemins d'interface** : `GET /admin/v1/user/{hashid}` — le `{id}` du chemin est une chaîne hashid
- **Stockage en base de données** : valeur BIGINT d'origine, générée par snowflake

### Versionnement des interfaces

La version des interfaces se trouve dans le chemin d'URL (par ex. `/admin/v1/*`, `/api/v1/*`, `/open/v1/*`) ; **le client n'a besoin d'aucun en-tête de version** :

- Les interfaces publiques versionnées sont liées directement à la classe de contrôleur correspondante (`app/api/v1/controller/`)
- Pour une nouvelle version, on enregistre un nouveau groupe de routes `/api/vN` ; les contrôleurs sont rangés par version sous `app/api/vN/`
- L'ancienne résolution dynamique `v()` et le middleware `ApiVersion` (en-tête de requête) ont été supprimés

### Limitation de débit

Algorithme de fenêtre glissante basé sur Redis, par défaut 60 requêtes/minute/IP/route. Interfaces sensibles plus strictes :
- Connexion : 10 requêtes/minute
- Inscription : 5 requêtes/minute (désactivée par défaut, nécessite `REGISTRATION_ENABLED=1`)

Les en-têtes de réponse contiennent `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `X-RateLimit-Reset`. En cas de dépassement, renvoie 429 avec `Retry-After`.

### Architecture des middlewares

Les middlewares globaux s'appliquent à toutes les requêtes, exécutés dans l'ordre :

```
Cors (pré-traitement CORS + en-têtes de réponse)
  → SecurityFilter (limitation des méthodes HTTP/taille du corps de requête/vérification du Content-Type/XSS/injection SQL/traversée de chemin/injection de commandes/blocage CSRF)
  → RateLimit (limitation à fenêtre glissante Redis + verrouillage de compte : 5 échecs de connexion → verrouillage 15 minutes)
  → TracingId (ID de traçage de la chaîne)
```

Middlewares de groupe de routes : `/admin/v1` porte `AdminAuth (authentification JWT + liste noire) → AdminPermission (autorisation RBAC) → OperationLog (journalisation automatique des POST/PUT/DELETE, avec détection de l'origine)` ; `/open/v1` porte `OpenApiAuth` ; les callbacks de suivi TMS portent `TrackingSignature`. La langue est résolue par `app/common/I18n.php` à partir de `Accept-Language` — ce n'est pas un middleware.

`/health`, `/api/docs` et `/install` sont des points de terminaison publics, soumis uniquement à `Cors → SecurityFilter → RateLimit → TracingId`.

Renforcements de sécurité :
- **Verrouillage de compte** : après 5 échecs de connexion consécutifs, le compte est automatiquement verrouillé pendant 15 minutes ; les connexions pendant le verrouillage renvoient 429
- **Limite de sessions simultanées** : 3 jetons valides maximum par utilisateur ; au-delà, le jeton le plus ancien est automatiquement ajouté à la liste noire
- **security.txt** : `GET /.well-known/security.txt` fournit les informations de contact de sécurité standard RFC 9116
- **Configuration de sécurité Nginx** : voir `docs/nginx-security.conf` pour un exemple complet de durcissement du reverse proxy

### Authentification

La connexion et l'inscription nécessitent d'abord la validation du **captcha à clic** :

1. Le client appelle `POST /api/v1/captcha/generate` pour obtenir l'image du captcha (PNG base64) et la liste des cibles textuelles
2. L'utilisateur clique dans l'ordre sur les positions du texte correspondant dans l'image et collecte les coordonnées de clic `[{x, y}, ...]`
3. À la connexion, `captcha_key` et `clicks` sont soumis ensemble ; le serveur valide d'abord le captcha, puis les identifiants

```http
POST /api/v1/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "******",
  "captcha_key": "abc123...",
  "clicks": [{"x": 120, "y": 85}, {"x": 210, "y": 140}, {"x": 95, "y": 170}]
}
```

Les interfaces d'administration ultérieures nécessitent l'authentification JWT :

```http
Authorization: Bearer <token>
```

Après une connexion réussie, `access_token` est renvoyé avec une validité de 2 heures ; `refresh_token` est également renvoyé avec une validité de 14 jours.

À la déconnexion, le jeton est ajouté à la liste noire Redis et ne peut plus être réutilisé pendant sa période de validité. POST /admin/v1/profile/logout

### Double confirmation des opérations sensibles

Les opérations sensibles telles que la suppression d'un utilisateur, d'un rôle ou d'une permission exigent de transmettre le `password` de l'utilisateur connecté dans le corps de la requête pour une double confirmation d'identité :

```http
DELETE /admin/v1/user/{id}
Content-Type: application/json
Authorization: Bearer <token>

{ "password": "******" }
```

## Liste des API

La liste complète des interfaces (interfaces publiques / interfaces d'administration / interfaces métier / interfaces client) a été déplacée vers un document séparé :

→ [Document de référence API](API.md)

## Notes frontend

### Console d'administration Angular (`apps/angular/`)

```bash
cd apps/angular
npm install
npm run dev        # ng serve → http://localhost:4200 (port défini par ANGULAR_DEV_PORT dans .env)
npm run build      # tsc --noEmit + ng build, artefact dans dist/angular
npm run typecheck  # vérification de types uniquement
```

- **Version de Node requise** : le champ `engines` d'Angular CLI 22 exige **Node ≥ 22.22.3** (une version inférieure fait échouer `ng build` au démarrage).
  Si le Node local est trop ancien, passez temporairement par npx (la méthode de build la plus courante sur ce dépôt, hors CI) :

  ```bash
  npx --yes --package=node@22.22.3 -- node node_modules/@angular/cli/bin/ng.js build
  ```

  Sans `npx` (par exemple sur la machine de validation hors ligne de ce dépôt), utilisez le tsc fourni par le CLI pour la vérification de types :
  `./node_modules/.bin/tsc --noEmit -p tsconfig.app.json`

- **Proxy de développement** : `proxy.conf.js` redirige déjà `/admin` `/api` `/open` `/health` `/metrics` `/install`
  vers `APP_HTTP_PORT` de `.env` (8788 par défaut), il est donc **inutile** de configurer l'adresse du backend lors d'un `ng serve`
- **Architecture** : pilotée par configuration — les fichiers `src/app/config/domains/*.ts` déclarent les menus et les pages de ressources, **un seul `ResourcePage`
  rend toutes les pages métier** (ajouter une page de ressources revient à ajouter un objet de configuration, sans écrire de composant)
- **Multilingue** : 13 langues, dictionnaires chargés paresseusement par langue (chacune formant un chunk) ; bascule via l'icône globe de la barre supérieure
- **Auto-vérifications** (aucun navigateur requis, exécutables directement avec `node`) : `scripts/check-ng-tree-semantics.mjs`,
  `check-ng-i18n-dict.mjs`, `check-ng-spec-attrs.mjs`

### Console d'administration React (`apps/react/`)

```bash
cd apps/react
npm install
npm run dev        # Vite → http://localhost:5173 (port défini par REACT_DEV_PORT dans .env)
npm run build      # tsc --noEmit + vite build, artefact dans dist/
```

- Également **pilotée par configuration** comme Angular : les fichiers `src/config/domains/*.ts` déclarent les menus et les pages de ressources,
  le moteur de rendu est `src/components/ResourcePage.tsx` ; les jetons de style sont dans `src/styles/tokens.css`
  (mêmes valeurs que `styles/theme.less` côté Angular)
- Le point de bascule de langue se trouve dans la page **espace personnel** (côté Angular, il y a en plus l'icône globe de la barre supérieure)

### Console d'administration Flutter (style PC, `apps/flutter/`)

```bash
cd apps/flutter
flutter pub get
flutter run -d chrome    # Web (style console d'administration PC), prend aussi en charge iOS/Android/macOS/Windows/Linux
flutter analyze          # analyse statique (identique à la CI)
```

- **Disposition** : barre latérale (repliable 64px/240px) + barre supérieure + zone de contenu, trois points de rupture réactifs (mobile / tablette / bureau)
- **Couverture** : 22 groupes de menus, 102 pages routables, 119 fichiers de page (menus déclarés dans `lib/app/config/menu_config.dart`, pages dans `lib/app/pages/`) — tableau de bord, administration système, gestion des produits, tiers (clients/fournisseurs), gestion des achats, gestion des ventes, gestion des stocks, gestion financière, CRM, gestion des commandes, gestion d'entrepôt, gestion du transport, production, gestion de la qualité, ressources humaines, gestion de projets, workflow d'approbation, centre de notifications, rapports personnalisés, tableaux de bord BI, gestion des équipements, gestion documentaire
- **Gestion d'état** : GetX (`ApiService` singleton + persistance du jeton `AuthService`)
- **Tableau de bord** : cartes statistiques, courbe de tendance des ventes, top produits, répartition des statuts de commande, échéancier des comptes clients/fournisseurs, aperçu des stocks (fl_chart)
- **Export** : export Excel/PDF (`ExportService`), le PDF contient des informations de copyright non supprimables
- **Opérations en masse** : suppression en masse multi-sélection, activation/désactivation en masse
- **Thème** : double thème Material 3 clair / sombre
- **Internationalisation** : chinois/anglais (`lib/l10n/app_zh.arb` comme modèle, génération via `flutter gen-l10n`)

### Application mobile HarmonyOS (`apps/harmonyos/`)

- **Build** : ouvrir `apps/harmonyos/` avec DevEco Studio ; l'équivalent en ligne de commande est
  `cd apps/harmonyos && hvigorw --mode module -p product=default assembleHap --no-daemon`
  (nécessite le HarmonyOS SDK + command-line-tools, artefact `entry/build/default/outputs/default/*.hap`)
- **Pages** : `entry/src/main/resources/base/profile/main_pages.json` enregistre **41 pages, toutes accessibles depuis l'interface** (connexion, tableau de bord, liste/détail des utilisateurs, rôles et permissions, espace personnel, ainsi que les pages des sous-systèmes produits/stocks/achats/ventes/OMS/WMS/TMS/production/RH/approbation) ; la grille des modules métier du tableau de bord offre **32 accès directs**, les pages de détail des sous-systèmes s'ouvrant via les actions de ligne des listes
- **Authentification** : JWT Bearer + rafraîchissement transparent automatique du jeton sur 401, redirection automatique vers la page de connexion en cas d'échec du rafraîchissement
- **Stockage** : jeton géré via AppStorage
- **Internationalisation** : chinois/anglais (`resources/base/element/string.json` et `resources/en_US/element/string.json`)
- **Réseau** : le `BASE_URL` de `apps/harmonyos/entry/src/main/ets/utils/Config.ets` est une constante en lecture seule, par défaut `http://10.0.2.2:8788` (hôte de l'émulateur) ; la valeur par défaut du projet `http://erp.test` vaut pour le client Flutter et pour l'entrée Web du backend

## Règles de développement

- Les fonctions/classes globales ne sont pas précédées d'un `\`, import unifié via `use`
- Tous les fichiers PHP doivent contenir l'en-tête de copyright
- Tous les fichiers de configuration doivent contenir des commentaires en chinois
- Les clés primaires de base de données doivent être générées par snowflake au niveau applicatif, l'auto-incrémentation est interdite
- Tous les ID dans les paramètres et réponses de la couche API doivent passer par le chiffrement / déchiffrement hashids
- Le middleware AdminPermission met en cache les permissions des utilisateurs dans Redis (TTL=60s), éliminant le goulot d'étranglement des requêtes N+1

## Déploiement

### Docker Compose (recommandé)

`docker-compose.yml` à la racine du projet orchestre 5 services :

| Service | Image | Port |
|------|------|------|
| `nginx` | nginx:alpine | 80, 443 |
| `app` | construit à partir du `Dockerfile` local | 8788 |
| `mysql` | mysql:8.0 | 3306 |
| `redis` | redis:7-alpine | 6379 |
| `elasticsearch` | elasticsearch:8.x | 9200 |

L'image PHP est construite via le `Dockerfile`, image de base `php:8.3-cli`, OPcache activé.

```bash
cp .env.docker .env
# Remplacer les cles par des valeurs aleatoires (idempotent)
bash scripts/gen-env-keys.sh .env
docker compose up -d
```

### CI/CD

Pipeline d'intégration continue GitHub Actions : `.github/workflows/ci.yml`, avec cinq jobs :

| Job | Contenu |
|------|------|
| `php` (matrice PHP 8.3 / 8.4, avec MySQL 8 + Redis 7 en services) | validation composer et audit de sécurité → `php -l` → **PHPStan** (level 5 + baseline) → **PHP CS Fixer** (dry-run) → import du `install.sql` complet → **PHPUnit** (avec cas d'intégration) → collecte de couverture pcov → seuils de couverture (global ≥ 4 %, `app/service` ≥ 10 %, durcis progressivement) |
| `flutter` | `flutter analyze` + `flutter test` (`continue-on-error: true`, à durcir une fois l'environnement stabilisé) |
| `docs` | `bash scripts/doc-stats.sh --check` : vérifie que les annotations `<!-- stats:key=value -->` du README et de docs correspondent aux comptages réels du code (contrôleurs/services/modèles/tables/nombre de tests, etc.), toute dérive passe au rouge |
| `e2e` | démarre un vrai service webman → health check → test de fumée des chemins HTTP critiques + couverture des API d'administration |
| `release` | après un push sur `main` et le succès des jobs ci-dessus : tag en patch+1 et publication d'une Release (voir ci-dessous) |

> Périmètre des contrôles statiques frontend : la CI n'exécute pour l'instant que Flutter ; Angular/React (`tsc --noEmit`) et HarmonyOS (`hvigorw assembleHap`) doivent tourner en local ou dans des jobs ajoutés ultérieurement.

### Processus de publication (incrément de version)

Après un push sur `main` et le succès complet des vérifications php / docs / e2e, le job `release` de `ci.yml` crée et pousse automatiquement un nouveau tag de version en **patch+1** par rapport au dernier tag (`v1.1.4` → `v1.1.5`), puis crée une GitHub Release du même nom (`--generate-notes` génère automatiquement les notes de version).

- **Déclenchement** : uniquement lors d'un push sur `main` (les PR ne déclenchent rien ; un push de tag ne correspond pas au filtre de branche et ne réenclenche donc pas ce workflow)
- **Idempotence** : si un tag ou une release du même nom existe déjà sur le dépôt distant (CI concurrente / créé manuellement), le job passe automatiquement, sans erreur
- **Répétition locale** : `bash scripts/bump-version.sh --check` affiche le prochain numéro de version (lecture seule, n'écrit rien sur le dépôt distant)

### Sauvegarde de la base de données

Répertoire `database/backup/` :

- `backup.sh` — sauvegarde mysqldump + gzip, nettoyage automatique des sauvegardes de plus de 30 jours
- `restore.sh` — restauration interactive, liste les sauvegardes disponibles pour sélection

### Configuration de sécurité Nginx

Pour un déploiement en production, reportez-vous à `docs/nginx-security.conf` pour le durcissement du reverse proxy.

## L'open source n'est pas facile, soutenez-nous

| WeChat | Alipay |
|:---:|:---:|
| ![微信](./images/weixinpay.png "微信") | ![支付宝](./images/alipay.png "支付宝") |

### Virement international (Bank Transfer)

**Informations du bénéficiaire**

- Nom du bénéficiaire : WANG KEXUN
- Numéro de compte du bénéficiaire : 881015918251

**Banque du bénéficiaire**

- Code SWIFT de ZA Bank : AABLHKHHXXX
- Nom de la banque : ZA Bank Limited
- Numéro de banque : 387
- Adresse de la banque : Core F, Cyberport 3, 100 Cyberport Road, Hong Kong

**Banque correspondante pour les transferts transfrontaliers (si nécessaire)**

> Il s'agit des informations de la banque correspondante (intermédiaire), et non de la banque du bénéficiaire. Renseignez-vous auprès de votre banque pour savoir si elles sont requises.

- Dépôts en dollars de Hong Kong, en yuans et en dollars américains : Citibank N.A. Hong Kong — SWIFT `CITIHKHXXXX`, numéro de banque 006, succursale Hong Kong Branch, numéro de succursale 391, Citibank Tower, Citibank Plaza, 3 Garden Road, Central, Hong Kong
- Dépôts dans d'autres devises : THE BANK OF NEW YORK MELLON — SWIFT `IRVTUS3NXXX`, 240 GREENWICH STREET, NEW YORK, United States

### Don en cryptomonnaie (Crypto Donation)

Si ce projet vous est utile, scannez le code QR pour faire un don, merci !

| <img src="../../coin/1.jpg" width="200" alt="BNB Smart Chain (BEP20)"><br>**BNB Smart Chain (BEP20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/2.jpg" width="200" alt="Tron (TRC20)"><br>**Tron (TRC20)**<br>`TEdDHWLajt1XvqtPDWmQctdrJaC3pzZZzz` |
| <img src="../../coin/3.jpg" width="200" alt="Ethereum (ERC20)"><br>**Ethereum (ERC20)**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/4.jpg" width="200" alt="Aptos"><br>**Aptos**<br>`0x836e3780edfc3f7b2372b39e2a1a3a5d7adfaccd96c726f21cfde1b50dd68030` |
| <img src="../../coin/5.jpg" width="200" alt="Plasma"><br>**Plasma**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/6.jpg" width="200" alt="Polygon POS"><br>**Polygon POS**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |
| <img src="../../coin/7.jpg" width="200" alt="Solana"><br>**Solana**<br>`2hfhboHdmdrYsY25XfQSsEWxq5ip4EQsR7f4AzSRMUyr` | <img src="../../coin/8.jpg" width="200" alt="The Open Network (TON)"><br>**The Open Network (TON)**<br>`UQB9kFQohzmXUir9QSSZq01iwl9aQZIDdBpNmDklljRtCoGK` |
| <img src="../../coin/9.jpg" width="200" alt="Arbitrum One"><br>**Arbitrum One**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` | <img src="../../coin/10.jpg" width="200" alt="AVAX C-Chain"><br>**AVAX C-Chain**<br>`0x355d429f97511897ccb4e271ec888205f9ab6629` |

---

## License

MIT

Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
