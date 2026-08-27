# Planification de projet pour la phase suivante (P4 / version évolutive 1.1)

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> Rédaction : architecte système ｜ Date : 2026-08-07 ｜ Base : trois études préalables (planification et écarts / backend et qualité / frontend) + vérification sur le terrain par sondage
> Statut : brouillon (en attente de revue) ｜ Version cible : 1.1 (période d'évolution)

---

## 1. Positionnement de la phase

La feuille de route P0~P3 a été entièrement livrée : 22 modules métier, 163 tables, 121 contrôleurs, 24 services, 161 modèles, 12 middlewares ;
96 pages Flutter + 34 pages HarmonyOS ; score global 89/100. **Cette phase n'ajoute plus de domaine métier**, mais complète les capacités « implémentées mais non bouclées »,
traite la dette de qualité et élimine la dérive documentaire, pour produire une **version évolutive 1.1** maintenable à long terme.

Trois constats fondamentaux (tous confirmés par sondage) :

1. **Beaucoup de capacités « existent mais ne sont pas effectives »** : le middleware TenantScope et les traits de modèles ne sont pas enregistrés dans `config/middleware.php` (le multi-tenant est une coquille vide) ;
   la file d'attente est configurée avec le double pilote redis/rabbitmq mais `config/process.php` n'a aucun processus de consommation ; les connexions WebSocket ne vérifient pas le JWT ;
   les statistiques OMS/WMS/TMS du tableau de bord Flutter sont des fausses valeurs codées en dur, alors que les points d'accès backend `/dashboard/oms|wms|tms` existent déjà mais ne sont pas appelés ;
   le frontend appelle un point d'accès de notification inexistant `/admin/notification/my/read` (le backend est en réalité `/admin/notification/read-all`).
2. **Arriérés de qualité et de sécurité** : 11 modules métier avec zéro test ; PHPStan niveau 5 mais la baseline supprime 974 erreurs ; les 137 tests sont tous de purs tests unitaires, sans intégration/E2E/couverture ;
   `.env.docker` contient beaucoup de clés faibles ; la CI ne comporte que des jobs PHP, sans aucune porte de qualité frontend.
3. **Dérive documentaire systématique** : les nombres de tests 132/779→135/799→137/805 divergent entre trois versions ; l'annexe de FUNCTIONS.md est très éloignée des mesures réelles ;
   les chiffres d'EDITIONS.md se contredisent ; les trois branches lite/standard/full accusent un retard de 20~41 commits sur main.

**Principe** : d'abord compléter l'« implémenté mais non bouclé » (points d'accès morts, TenantScope/file d'attente non câblés, tableau de bord mock), puis les tests et les portes de qualité,
puis l'optimisation de la structure et de la documentation. Toutes les tâches sont petites et claires, réalisables dans une session d'agent unique ; les éléments incertains sont marqués « à vérifier ».

---

## 2. Analyse des écarts (synthèse)

Les écarts des trois études sont regroupés en **6 groupes de travail**. Chaque élément donne le chemin de preuve.

### Groupe de travail A : complétion du bouclage métier (priorité la plus haute)

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| A1 | Le « tout marquer comme lu » des notifications appelle un point d'accès inexistant côté frontend | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` appelle `/admin/notification/my/read` ; la route backend est `POST /admin/notification/read-all` dans `config/route.php:250` | Confirmé |
| A2 | Les statistiques OMS/WMS/TMS du tableau de bord sont des valeurs mock, les requêtes n'ont pas de JWT | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart` (Dio indépendant avec `baseUrl: http://localhost:8787`, sans intercepteur ; `omsStats/wmsStats/tmsStats` codés en dur ; commentaire « Mock values for now ») ; les vrais points d'accès backend sont dans `config/route.php:231-233` | Confirmé |
| A3 | Le middleware TenantScope et les traits de modèles ne sont pas câblés, le multi-tenant est une coquille vide | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` existent ; la chaîne globale de `config/middleware.php` n'enregistre que Locale/Cors/SecurityFilter/RateLimit/TracingId, et les groupes de route.php n'y font aucune référence | Confirmé |
| A4 | File d'attente à double pilote mais sans processus de consommation, inefficace de bout en bout | `config/queue.php` (redis par défaut, rabbitmq en option) ; `config/process.php` n'a que les trois processus webman/socket/monitor | Confirmé |
| A5 | WebSocket sans authentification | `app/process/WebSocket.php:23` commentaire « could validate JWT here » ; `:47-50` le message auth renvoie directement success:true, sans vérifier le jeton | Confirmé |
| A6 | Les paramètres de pagination de 25 pages de listes HarmonyOS sont inopérants (pas d'interpolation dans `${this.page}` entre guillemets simples) | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24` (vérifié par sondage) ; 24 autres occurrences du même modèle | Confirmé (liste à vérifier en totalité) |
| A7 | Une grande partie des points d'accès d'actions métier n'est pas connectée au frontend (règlements/trois états/exécution/approbations/calcul de paie, etc.) | Conclusion de l'étude de matrice de couverture ; ex. : achats/ventes sans page de règlement, finances sans 13 points d'accès, CRM sans follow/entonnoir/transition de contrat | À vérifier (inventaire module par module nécessaire) |
| A8 | Beaucoup de formulaires des pages métier n'ont que des champs génériques name/code | Conclusion de l'étude (création de commande de vente/pièce comptable ne remplissant que le nom et le code) | À vérifier (vérification page par page nécessaire) |

### Groupe de travail B : reconstruction du système de tests

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| B1 | 11 modules métier avec zéro test : crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | Les 19 fichiers de test de `tests/` ne couvrent que admin/finance/inventory/oms/wms/tms/notification/hr/mrp/les classes de base de sécurité ; ces 11 modules n'ont aucun fichier de test dédié — parmi eux, les six modules crm/eam/dms/quality/report/workflow n'ont **aucune mention** dans aucun fichier de test ; project/purchase/sales/product/bi ne sont référencés que par accident par les tests de classes de base génériques ou les tests de modules voisins (échantillonnage de modèles de ControllerPatternTest, inventaire des routes de bootstrap.php, contexte d'entrée en stock purchase/product mentionné dans InventoryServiceTest, « bi » sous-chaîne de debit_amount dans DoubleEntryServiceTest), aucune couverture dédiée | Confirmé |
| B2 | Pas d'intégration/E2E/couverture ; les 137 tests / 805 assertions sont tous de purs tests unitaires (mesuré : exécution en 1,2 s, entièrement en mémoire) | `vendor/bin/phpunit` mesuré « OK (137 tests, 805 assertions) » | Confirmé |
| B3 | PHPStan niveau 5 mais la baseline supprime 974 erreurs | `phpstan-baseline.neon` mesuré : 974 nœuds de message | Confirmé |
| B4 | La CI n'a ni collecte de couverture ni job de test d'intégration | `.github/workflows/ci.yml` (PHP 8.2/8.3/8.4 × mysql8/redis7, uniquement composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit) | Confirmé |
| B5 | Les contrôleurs purchase/sales dépendent de services codés en dur | `app/controller/sales/DeliveryController.php:142-143`、`app/controller/purchase/ReceiveController.php:142-143` (les deux fichiers ont les `use` déclarés en :15-16, `new InventoryService()/new FinanceService()` instanciés en :142-143) | Confirmé |

### Groupe de travail C : gouvernance de l'infrastructure et de la sécurité

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| C1 | Clés faibles dans `.env.docker` | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest` (.env.docker:15,32,37,51,67,81) | Confirmé |
| C2 | Validation stricte des variables d'environnement incomplète | Étude : seul ENCRYPTION_KEY passe par env_required | À vérifier (vérifier config/jwt.php, encryption.php) |
| C3 | Fail-open avalant silencieusement les erreurs | Conclusion de l'étude ; périmètre à auditer (try/catch vides, catch sans journalisation) | À vérifier (audit grep nécessaire) |
| C4 | backup-validator.sh et les `_rollback.sql` par migration manquants | `find` sur toute la base : aucune correspondance ; les 29 migrations SQL de `database/migrations/` n'ont aucun fichier de rollback correspondant | Confirmé |
| C5 | Canaux de notification stubs (email/wecom/dingtalk) | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | Confirmé |
| C6 | Lacunes de surveillance : aucun indicateur pour l'arriéré de la file et le nombre de connexions WebSocket | `app/admin/controller/MetricsController.php` a 5 gauges existants | Partiellement confirmé |

### Groupe de travail D : matrice de versions et gouvernance documentaire

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| D1 | Les branches lite/standard/full accusent un retard de 20~41 commits sur main | `git rev-list --left-right --count main...lite|standard|full` mesuré : 41/41/20 behind, et lite/standard ont chacune 6~7 commits uniques ahead | Confirmé |
| D2 | Chiffres contradictoires dans EDITIONS.md | Tableau de synthèse : contrôleurs 48/42/70, modules métier 6/6/12 ; le paragraphe de parcours de mise à niveau écrit pourtant 12/12/19 modules, 163 tables ; incompatible avec les 121 contrôleurs mesurés | Confirmé |
| D3 | Dérive de l'annexe de FUNCTIONS.md | L'annexe écrit 11 fichiers/90 méthodes/168 assertions/9 middlewares/22 migrations ; mesuré : 19~20 fichiers/137 tests/805 assertions/12 middlewares/29 migrations | Confirmé |
| D4 | Dérive des nombres de tests entre trois versions (132/779→135/799→137/805) | Historique documentaire et enregistrements de commits git | Confirmé |
| D5 | La matrice d'avancement marque QMS/EAM/DMS/BI en 🔴 mais le code existe déjà | Matrice autour de `docs/FUNCTIONS.md:555` vs `app/controller/{quality,eam,dms,bi}/` déjà implémenté | Confirmé |
| D6 | Définition de périmètre des contrôleurs incohérente : docs/CLAUDE.md écrit « 104 contrôleurs métier », mesuré 122 au total | `find app -path '*/controller/*.php' | wc -l` = 122 (y compris admin 14 + api 3 + métier 104 + Index/Install) ; l'étude dit 121 | Confirmé (différence de périmètre) |
| D7 | Nombre de migrations : étude 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29 (numérotées jusqu'à 000030, manquent 000007/000008) | Confirmé (29 est la mesure) |

### Groupe de travail E : qualité et alignement frontend

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| E1 | La CI n'a pas de flutter analyze/test/build, ni de build hvigor | `.github/workflows/ci.yml` uniquement des jobs PHP | Confirmé |
| E2 | README prétend que la CI contient l'analyse statique Flutter, contrairement aux faits | `README.md:635` « Flutter 静态分析 (flutter analyze) » vs ci.yml sans cette étape | Confirmé |
| E3 | Flutter n'a qu'un seul test de fumée | `apps/flutter/test/widget_test.dart` est l'unique fichier de test | Confirmé |
| E4 | Le jeton HarmonyOS n'est pas persisté (AppStorage en mémoire uniquement, retour à la page de connexion au démarrage à froid) | Conclusion de l'étude (à vérifier dans `apps/harmonyos/entry/src/main/ets/service/ApiService.ets`) | À vérifier |
| E5 | 25 pages HarmonyOS sont des gabarits, listes en lecture seule name/code sans CRUD | OrderListPage.ets vérifiée par sondage sur l'ensemble de ses 65 lignes : liste en lecture seule name/code uniquement | Confirmé |
| E6 | Profondeur de couverture frontend insuffisante (voir A7/A8) | Idem | À vérifier |

### Groupe de travail F : stratification de l'API et gouvernance de l'architecture (priorité basse, selon les moyens)

| # | Écart | Chemin de preuve | Statut |
|---|------|----------|------|
| F1 | La version `/api` n'a que 3 contrôleurs, tout le métier est dans le monolithe `/admin` | `app/api/v1/controller/` n'a que Captcha/Auth/Product | Confirmé |
| F2 | Les contrôleurs de 10 modules interrogent directement les modèles sans couche de service | Conclusion de l'étude (les contrôleurs crm/product etc. utilisent directement les requêtes de modèles) | Partiellement confirmé (audit complet nécessaire) |
| F3 | purchase/sales utilisent `new` codé en dur pour les services au lieu de l'injection de dépendances | Preuve de B5 | Confirmé |

---

## 3. Planification par phases

Répartis en trois lots par priorité (P0→P1→P2), **chaque période est publiable indépendamment, tous les critères d'acceptation sont quantifiables**. Durée totale d'environ **8~9 semaines** (hypothèse de parallélisme : estimée avec **2~3 développeurs en parallèle + collaboration d'équipe d'agents** ; total des tâches environ **77 jours-homme** — P0 ≈12,5 j, P1 ≈29,5 j, P2 ≈35 j — en exécution séquentielle par une seule personne, il faudrait environ 15 semaines. Base du parallélisme : les petites tâches backend A1/A4/A5 etc. sont indépendantes et parallélisables ; les tests B1 par module peuvent être divisés en sous-tâches parallèles ; les groupes B/C et E/D peuvent se chevaucher entre périodes ; les tâches frontend Flutter/HarmonyOS ne bloquent pas les tâches backend ; les dépendances explicites entre tâches sont au §5).

**Système de numérotation** : les numéros des tâches par période correspondent un à un aux numéros d'écart du §2 (A1~A8 → A1~A6/A7-1/A7-2/A8-1, B1~B5 → B1~B5, C1~C6 → C1~C6, D1~D7 → D1~D5, E1~E6 → E1/E3/E4/E5, F2/F3 → F2/F3) ; D6/D7 (périmètre des contrôleurs et des migrations) sont fusionnés dans la tâche D3, E2 (déclaration inexacte du README) est intégré à l'acceptation de E1, E6 (profondeur de couverture) est fusionné dans A7-2, F1 (version complète de /api) est explicitement hors périmètre de cette période (voir §6) ; il y a aussi une tâche i18n correspondant à l'« i18n Flutter non terminé » de l'étude, non numérotée dans le tableau des écarts.

### 3.1 Premier lot P0 : baseline de bouclage (semaines 1~2)

**Objectif** : éliminer les points d'accès morts et les fausses données, rendre les capacités existantes non câblées (TenantScope/file d'attente/WebSocket) utilisables ou explicitement rétrogradées.

| Tâche | Contenu | Périmètre concerné | Critère d'acceptation | Durée |
|------|------|----------|----------|------|
| A1 | Réparer le « tout marquer comme lu » des notifications : le frontend appelle `POST /admin/notification/read-all` (ou le backend ajoute une route alias, au choix, recommandation : modifier le frontend) | `notification_page.dart` + `config/route.php` | L'appel manuel/automatique aboutit ; ajout de 1 assertion PHPUnit vérifiant que cette route existe | 0,5 j |
| A2 | Connecter le tableau de bord aux vraies données : supprimer le Dio indépendant, passer par ApiService (intercepteur JWT) ; les trois onglets OMS/WMS/TMS appellent `/dashboard/oms\|wms\|tms` ; supprimer les fausses valeurs codées en dur ; conserver la sémantique du cache Redis 5 min | `dashboard_controller.dart` + pages concernées | Connecté, les trois onglets du tableau de bord affichent les vraies données backend, le panneau Réseau montre un 200 avec l'en-tête Authorization ; suppression des commentaires mock | 2 j |
| A3 | Câbler TenantScope : l'enregistrer dans le groupe de routes `/admin` ; l'ID du locataire provient de la revendication JWT ou de l'en-tête `X-Tenant-Id` (**point de décision**, voir §5) ; les traits de modèles sont prêts, pas de gros changement | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | Les données de deux locataires sont mutuellement invisibles (nouveau test d'intégration) ; sans en-tête de locataire, renvoyer 400 au lieu d'un passage silencieux ; **rétrogradation de secours** : si le moment est jugé inopportun, marquer explicitement « le multi-tenant est une capacité réservée » dans la documentation et donner les étapes d'activation, acceptation = cohérence document/code | 2 j |
| A4 | File d'attente de bout en bout : ajouter le processus de consommation `redis-queue` dans config/process.php (pilote redis par défaut) ; ajouter une tâche de fumée observable (par ex. écriture asynchrone du journal d'opérations) ; documenter les étapes de bascule vers rabbitmq | `config/process.php`、`app/queue/` | Après démarrage, le processus de consommation est en ligne (`php start.php status`) ; après envoi de la tâche de fumée, l'effet secondaire cible apparaît en moins de 5 s | 1 j |
| A5 | Authentification WebSocket : vérifier le JWT à l'établissement de la connexion/au message `auth` (réutiliser la logique d'AdminAuth), jeton invalide → auth_result:false et déconnexion ; mise à jour de la documentation | `app/process/WebSocket.php` + point de connexion frontend | Les connexions sans jeton/avec jeton falsifié sont rejetées ; les connexions avec jeton valide aboutissent ; ajout d'1 test de couverture | 1 j |
| A6 | Réparer la pagination HarmonyOS : 25 interpolations entre guillemets simples passent en template strings/concaténation ; incrément de page + chargement en bas de page + rafraîchissement par glissement ; composant de pagination unifié | `apps/harmonyos/entry/src/main/ets/pages/**` (25 fichiers) | grep sur tout le dépôt : plus aucune trace du motif `${this.page}` entre guillemets simples ; les paramètres de requête de pagination des listes sont corrects ; le build passe | 2 j |
| A7-1 | Remise à zéro complète des points d'accès morts : sur la base de la matrice de couverture de l'étude, lancer une comparaison automatique « URL frontend × route backend » (script extrayant les chaînes de requête Flutter/HarmonyOS vs `config/route.php`), sortir la liste des écarts restants | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | Le produit du script de comparaison est archivé (docs/) ; dans la liste des écarts, « appelé par le frontend mais inexistant au backend » tombe à zéro (les inexistants mais raisonnables sont marqués en liste blanche) | 2 j |
| A8-1 | Compléter les champs des formulaires à haute valeur : pages de commande d'achat/vente et de pièce comptable complétées avec les champs métier clés (montant/date/tiers/lignes de détail), uniquement compléter, sans moteur de formulaire | Pages Flutter concernées | Le formulaire peut créer un document complet avec champs métier, l'interface renvoie 200 | 2 j |

**Synthèse d'acceptation P0** : A1~A6 entièrement déployés ; la liste des points d'accès morts est à zéro ; CI toute verte ; aucune nouvelle dérive documentaire (les modifications sont synchronisées avec la liste des fonctionnalités de docs/CLAUDE.md).

### 3.2 Deuxième lot P1 : baseline de tests et de sécurité (semaines 3~5)

**Objectif** : le système de tests passe de « purs tests unitaires » à « unitaires + intégration + couverture », les faiblesses de sécurité sont remises à zéro.

| Tâche | Contenu | Périmètre concerné | Critère d'acceptation | Durée |
|------|------|----------|----------|------|
| B1 | Compléter les tests des 11 modules métier : tests des couches service/modèle par module, couvrant CRUD + actions principales (règlements, flux d'approbation, processus de contrôle qualité, bons d'équipement, etc.) | `tests/` (nouveaux fichiers de test crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow) | Ajout de ≥150 tests / ≥500 assertions ; ≥10 tests par module sur les 11 ; `vendor/bin/phpunit` tout vert | 2 s |
| B2 | Tests d'intégration : profiter des services mysql8/redis7 existants de la CI, nouveau groupe de tests d'intégration (CRUD sur vraie base + rollback de transactions + vérification de l'isolation TenantScope + fumée de file d'attente) | `tests/Integration/` + groupe dans `phpunit.xml` | Le groupe d'intégration est tout vert en CI ; exécutable en local avec `--group=integration` | 1 s |
| B3 | Fumée E2E : vrai HTTP parcourant health→login→CRUD principal→tableau de bord, scripté | `tests/E2E/` (scripts curl/php) | Le nouveau job CI exécute 10 chaînes principales, l'échec devient rouge | 2 j |
| B4 | Couverture : intégrer phpunit --coverage, fixer les seuils (couche métier ≥40 %, globale ≥30 %, à vérifier si la CI supporte la collecte xdebug) | `phpunit.xml`、`ci.yml` | La CI produit le rapport de couverture ; sous le seuil, échec | 1 j |
| B5 | Service-isation des contrôleurs (4 modules à haute fréquence) : les contrôleurs finance/inventory/sales/purchase retirent `new` et prennent les services du conteneur (`support\Container`), préparation du terrain pour les tests B1 | `app/controller/{finance,inventory,sales,purchase}/**` | Plus aucune trace de `new InventoryService/FinanceService` ; les tests existants restent tout verts | 3 j |
| C1 | Remise à zéro des clés faibles : `.env.docker`/`.env.example` passent à des placeholders aléatoires + validation stricte au démarrage (refus de démarrage si manquant/égal au placeholder) ; la CI ajoute une étape de « vérification env » | `.env*`、`config/*.php`、`ci.yml` | Démarrer avec `change-me` échoue directement avec un message d'orientation ; un nouveau conteneur Docker génère automatiquement des clés aléatoires | 1 j |
| C2 | Extension de la validation stricte des variables d'environnement : JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD intégrés dans env_required (d'abord vérifier l'état actuel de config/jwt.php, à vérifier) | `config/*.php` | Le démarrage échoue s'il manque une clé critique, message d'erreur clair en chinois | 1 j |
| C3 | Audit fail-open : grep des catch vides/catch sans journalisation, passage en fail-closed + journalisation (avec TraceId) | tout app/ | L'inventaire d'audit est archivé ; chaque élément corrigé est attesté par un test ou une journalisation | 2 j |
| C4 | Gouvernance des migrations : ajouter `database/backup/backup-validator.sh` (vérification automatique de restauration après sauvegarde) + 29 `_rollback.sql` par migration (déduits de la structure de install.sql) | `database/` | Le script validator passe sur les fichiers de sauvegarde (sauvegarde→restauration→comparaison nombre de tables/lignes) ; chaque fichier de migration a un `_rollback.sql` homonyme à côté | 2 j |
| C5 | Mise en place des canaux de notification (correspond à l'écart C5) : au moins un canal utilisable (recommandation : email — pilote SMTP ou pilote de journalisation fichier pour l'envoi) ; si le moment est jugé inopportun, documenter explicitement la rétrogradation en « uniquement messages internes + points d'adaptation réservés email/wecom/dingtalk » avec les étapes d'accès (deux options au choix, décision explicite requise) | `app/service/notification/ChannelRouter.php` + nouvelle classe de pilote + docs | Pilote email : après envoi réussi de la notification, ChannelRouter renvoie true (test avec pilote de journalisation pour l'assertion) ; en cas de rétrogradation : le commentaire de ChannelRouter.php:23 et les docs marquent explicitement l'état « réservé », éliminant l'ambiguïté de « stub for future implementation » | 1,5 j |
| C6 | Compléter les indicateurs de surveillance : arriéré de file (redis LLEN), nombre de connexions WebSocket en ligne | `MetricsController.php` | `/metrics` renvoie 2 nouveaux gauges | 1 j |

**Synthèse d'acceptation P1** : nombre total de tests ≥287 (137+150) ; rapport de couverture produit et au-dessus des seuils ; démarrage en échec avec clés faibles/manquantes ; validator et scripts de rollback en place ; au moins un canal de notification utilisable ou rétrogradation explicitement documentée ; nouveaux jobs CI intégration/E2E/couverture tout verts.

### 3.3 Troisième lot P2 : documentation, matrice de versions et profondeur frontend (semaines 6~8)

**Objectif** : les chiffres documentaires s'alignent totalement sur les faits du code (vérification automatique), la matrice de versions redevient fiable, le frontend comble la profondeur à haute valeur.

| Tâche | Contenu | Périmètre concerné | Critère d'acceptation | Durée |
|------|------|----------|----------|------|
| D1 | Synchronisation des trois branches : fusion de main dans lite/standard/full, résolution des conflits, CI des trois branches toute verte ; **point de décision** : ensuite adopter la stratégie « main comme unique source de développement, les branches de version ne reçoivent que des cherry-pick à la sortie » | git trois branches + ci.yml | behind=0 sur les trois branches ; CI verte sur chacune ; résolution des conflits consignée | 1 s |
| D2 | Réécriture d'EDITIONS.md : sur la base des mesures (tables/contrôleurs/modules issus d'un script de comptage de code), suppression des paragraphes contradictoires | `docs/EDITIONS.md` | Tous les chiffres du document coïncident avec la sortie du script | 1 j |
| D3 | Automatisation des statistiques documentaires : écrire `scripts/doc-stats.sh` (comptage des contrôleurs/services/modèles/migrations/tests/middlewares + sortie phpunit), l'annexe de FUNCTIONS.md référence sa sortie ; unifier simultanément D6 (périmètre contrôleurs 104/121/122) et D7 (périmètre migrations 22/29/30) sous le périmètre unique du script | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | La sortie du script coïncide avec la documentation ; tous les chiffres de README/docs sont reproductibles par le script (y compris un périmètre unique pour contrôleurs/migrations) | 2 j |
| D4 | Correction de la matrice d'avancement : les éléments réellement implémentés (QMS/EAM/DMS/BI etc.) passent à ✅, avec preuve de code | `docs/FUNCTIONS.md` | La matrice correspond un à un aux répertoires de `app/controller/`, aucun décalage 🔴/✅ | 1 j |
| D5 | Job CI de vérification documentaire : exécuter doc-stats et comparer avec les documents, toute dérive devient rouge | `ci.yml` + script | Modifier un chiffre rend la CI rouge (démonstration auto-testée) | 1 j |
| E1 | Jobs CI Flutter : flutter analyze + flutter test + build web, intégrés à ci.yml | `ci.yml`、`apps/flutter/` | Les trois étapes toutes vertes ; la déclaration de README.md:635 coïncide avec les faits | 1 j |
| E3 | Extension des tests Flutter : intercepteur ApiService/rafraîchissement 401, flux AuthService, validations de formulaires clés, ≥20 tests widget/unit | `apps/flutter/test/` | `flutter test` tout vert, ≥20 tests | 1 s |
| E4 | Persistance du jeton HarmonyOS : AppStorage avec persistance réelle + restauration au démarrage à froid + logique de rafraîchissement 401 (d'abord vérifier l'état actuel d'ApiService, à vérifier) | `apps/harmonyos/.../service/ApiService.ets` | Après avoir tué le processus et redémarré, la session reste connectée ; le jeton expiré se rafraîchit automatiquement | 2 j |
| E5 | Compléter les CRUD des pages HarmonyOS principales : triées par valeur (2~3 pages de listes chacune pour purchase/sales/inventory/finance/oms), chaque page reçoit les actions 新建/编辑/删除 et les formulaires | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | Les ≥10 pages de listes sélectionnées disposent du CRUD et communiquent avec le backend ; le build hvigor passe (sans environnement SDK HarmonyOS, marquer « en attente d'environnement CI prêt ») | 1 s |
| i18n | i18n minimal Flutter (correspond à l'« i18n Flutter non terminé » de l'étude) : les messages d'erreur d'ApiService et les textes clés de connexion/navigation/tableau de bord passent dans i18n (fichiers arb, en lien avec `app/common/I18n.php` du backend) ; **uniquement minimal viable, pas de refonte de tous les textes de pages** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | Les messages d'erreur clés et ≥10 textes de pages peuvent basculer de langue (en/zh) ; `flutter test` tout vert | 2 j |
| A7-2 | Couverture frontend en profondeur : selon la liste de comparaison de A7-1, compléter les pages de règlement achats/ventes, les trois états financiers/clôture de période/comptes bancaires, les transitions CRM follow/entonnoir/contrat et autres points d'accès clés | `apps/flutter/lib/app/pages/**` | Les éléments à haute priorité de la liste de comparaison « backend existant mais frontend non couvert » (règlements/trois états/exécution/approbations/paie) passent à zéro | 1 s |
| F2/F3 | Extraction légère de la couche de service (optionnel, selon les moyens) : couche de service fine + injection de dépendances pour les 3~5 modules qui interrogent le plus les modèles ; **explicitement sans refactorisation complète obligatoire** | `app/controller/{crm,product,project,hr,manufacturing}/**` | Les contrôleurs des modules extraits n'ont aucune requête directe de modèles ; les tests existants restent tout verts ; les modules non extraits sont marqués « contrôleurs interrogeant directement les modèles, dette technique connue » dans la documentation | 1 s |

**Synthèse d'acceptation P2** : trois branches synchronisées et CI verte ; chiffres docs reproductibles par script ; CI avec jobs Flutter et vérification documentaire ; ≥20 tests Flutter ; persistance HarmonyOS + CRUD sur ≥10 pages ; couverture des points d'accès haute priorité à zéro.

---

## 4. Critères d'acceptation (synthèse, tous vérifiables)

- **Points d'accès** : A1 point d'accès de notification, A2 `/dashboard/oms|wms|tms`, les points d'accès haute priorité A7 tous appelables en curl avec JWT renvoyant 200/données métier.
- **Tests** : `vendor/bin/phpunit` tout vert (≥287 tests) ; `flutter test` tout vert (≥20) ; jobs intégration/E2E verts en CI.
- **Sécurité** : démarrage en échec avec la clé `change-me` ; jeton WebSocket invalide rejeté ; aucun catch vide avalant silencieusement les erreurs (inventaire d'audit).
- **Canaux/i18n** : au moins un canal de notification utilisable ou rétrogradation explicitement documentée ; messages d'erreur clés Flutter et ≥10 textes basculables entre chinois et anglais (minimal viable).
- **CI** : tous les jobs de `.github/workflows/ci.yml` verts (matrice PHP + intégration + couverture + flutter + vérification documentaire).
- **Documentation** : la sortie de `scripts/doc-stats.sh` coïncide avec tous les chiffres des docs (toute dérive rend la CI rouge).
- **Branches** : `git rev-list --left-right --count main...lite|standard|full` vaut `0 0` partout.
- **Frontend** : aucune trace résiduelle de `${this.page}` entre guillemets simples dans HarmonyOS ; session conservée au démarrage à froid ; CRUD des pages principales communiquant avec le backend.

---

## 5. Dépendances et risques

**Relations de dépendance** :
- Groupe A (bouclage) → groupe B (tests) : les tests B1/B2 doivent cibler des points d'accès **réellement utilisables**, donc P0 répare d'abord les points d'accès morts et le câblage, P1 complète ensuite les tests.
- B5 (service-isation des contrôleurs) → B1 (tests) : **prépare uniquement le terrain pour les tests des quatre modules finance/inventory/sales/purchase qu'il couvre** (après suppression du `new` codé en dur, les services peuvent recevoir des mocks ; purchase/sales sont des modules à zéro test, finance/inventory ont déjà des tests qui peuvent en profiter) ; les tests des autres modules à zéro test (crm/eam/dms/quality/project/product/bi/report/workflow) **ne dépendent pas** de B5 et peuvent avancer en parallèle de B5.
- D1 (synchronisation des branches) → D3/D5 (vérification documentaire) : une fois synchronisé, main est l'unique source de vérité, le périmètre documentaire peut être unique.
- E1 (CI Flutter) → E3 (extension des tests) : d'abord la porte, puis l'extension des tests a un sens protecteur.

**Risques et atténuations** :
| Risque | Impact | Atténuation |
|------|------|------|
| Le câblage de TenantScope affecte toutes les requêtes /admin et peut introduire une régression de visibilité des données | Élevé | Test d'intégration d'abord ; locataire pris dans la revendication JWT (aucune modification frontend nécessaire) ; ou rétrogradation en « réservé » documenté dans P0 avec décision explicite |
| Conflits de fusion de la synchronisation des trois branches, régression possible | Moyen-élevé | D'abord main tout vert ; livrable uniquement si la CI des trois branches est toute verte après fusion ; résolution des conflits consignée |
| Processus de consommation de file indisponible dans certains environnements (rabbitmq) | Moyen | Pilote redis par défaut (CI a déjà redis7), rabbitmq uniquement avec étapes de bascule documentées |
| La modification de l'authentification WebSocket casse les clients existants | Moyen | Modification coordonnée frontend/backend dans le même jalon ; rejet des jetons invalides sans affecter les sessions valides |
| La matrice de couverture/la liste des champs de formulaires proviennent de l'étude, certaines « à vérifier » | Moyen | A7-1 fait d'abord le script de comparaison automatisé, se fier au résultat du script, ne pas compléter des pages à l'aveugle |
| Périmètre de la refactorisation de la couche de services hors de contrôle | Moyen | N'extraire explicitement que 3~5 modules, pas d'obligation de tout refactoriser ; pas de version complète de /api (F1 hors périmètre cette période) |
| Seuil de couverture indisponible dans l'environnement CI (xdebug non installé) | Faible | D'abord produire le rapport localement + seuil documentaire, intégrer après « à vérifier » de la capacité de collecte CI |
| La CI HarmonyOS (hvigor) nécessite le SDK HarmonyOS, l'environnement CI public peut ne pas l'avoir | Moyen | Marquer « en attente d'environnement CI prêt » ; la validation du build local fait foi, ne bloque pas les autres tâches |

---

## 6. Explicitement hors périmètre

Dans la continuité des exclusions du §12 de la feuille de route, sauf forte raison (nécessitant un projet de lancement séparé) :
- ❌ Découpage en microservices / déploiement K8s (l'expérience reste dans `.claude/worktrees/microservices-split/`, non fusionnée dans la ligne principale)
- ❌ Capacités IA/ML (prédiction, recommandation intelligente, NLP)
- ❌ Applications natives (iOS/Android natifs) — Flutter couvre déjà toutes les plateformes
- ❌ Interfaces GraphQL
- ❌ Intégration matérielle (IoT/lecteurs de codes-barres/connexion directe d'imprimantes)
- ❌ Solution commerciale complète multi-tenant (facturation SaaS, activation en libre-service des locataires) — cette période ne fait que le câblage minimal ou la réserve documentée
- ❌ Version complète de /api (F1) — le backend métier reste dans /admin, enregistré comme dette d'architecture
- ❌ Refactorisation complète de la couche de services et refonte complète des formulaires — extraction triée par valeur, pas de refactorisation « big bang »
- ❌ Complétion complète des pages HarmonyOS — seuls les CRUD des pages principales à haute valeur
- ❌ Refonte i18n complète des textes Flutter — cette période ne fait que le minimal viable (messages d'erreur + ≥10 textes clés), le multilinguisme complet des pages est laissé aux versions suivantes

---

## 7. Jalons suggérés

| Jalon | Temps | Contenu | Critère de sortie |
|--------|------|----------|------|
| **M1 Baseline de bouclage** | fin de semaine 2 | Groupe A en entier : points d'accès morts à zéro, vraies données du tableau de bord, TenantScope/file d'attente/WebSocket déployés, réparation de la pagination HarmonyOS | Tous les points de la synthèse d'acceptation P0 |
| **M2 Baseline de qualité** | fin de semaine 5 | Groupe B en entier + éléments de sécurité du groupe C : tests des 11 modules, intégration/E2E/couverture, clés faibles à zéro, audit fail-open, gouvernance des migrations, canaux de notification | Tous les points de la synthèse d'acceptation P1 |
| **M3 Qualité frontend** | fin de semaine 6 | Groupe E : jobs CI Flutter + extension des tests, persistance du jeton HarmonyOS et CRUD des pages principales | CI flutter verte, persistance effective, CRUD sur ≥10 pages |
| **M4 Gouvernance des versions et de la documentation** | fin de semaine 7 | Groupe D : synchronisation des trois branches, réécriture d'EDITIONS/FUNCTIONS, doc-stats automatisé + vérification CI | Branches synchronisées, toute dérive documentaire rend la CI rouge |
| **M5 Couverture en profondeur** | fin de semaine 8 | A7-2 profondeur frontend + extraction légère de la couche de services du groupe F | Couverture des points d'accès haute priorité à zéro, aucun accès direct aux modèles dans les modules extraits |
| **M6 Sortie 1.1** | fin de semaine 9 | Régression complète, notes de version (CHANGELOG), vérification finale de la documentation, archivage | Tous les critères de sortie des jalons passent (indicateurs durs) : nombre total de tests ≥287 et phpunit tout vert, rapport de couverture au-dessus du seuil, tous les jobs de ci.yml verts (matrice PHP+intégration+couverture+flutter+vérification documentaire), trois branches synchronisées à 0 0, liste des points d'accès morts à zéro, mécanisme « dérive doc-stats = rouge » effectif ; vérification finale CHANGELOG et documentation passée ; la revue par relecteur ne sert que de référence, sans seuil de score |

---

## Annexe : fichiers clés vérifiés par sondage dans ce plan

- `config/middleware.php`、`config/route.php`（:231-233 points d'accès du tableau de bord、:248-251 routes de notification、:387-415 groupes de middlewares）
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php`（:23、:47-50）
- `app/service/notification/ChannelRouter.php`（:23 stub）
- `app/controller/sales/DeliveryController.php`（:142-143）、`app/controller/purchase/ReceiveController.php`（:142-143，l'instanciation `new` des deux fichiers est ici；les `use` sont déclarés en :15-16）
- `app/api/v1/controller/`（uniquement 3 contrôleurs）
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（statistiques mock + Dio indépendant）
- `apps/flutter/lib/app/pages/notification/notification_page.dart`（:43 point d'accès mort）
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets`（:24 bug d'interpolation）
- `tests/`（inventaire des 19 fichiers de test）、`vendor/bin/phpunit` mesuré 137/805
- `phpstan-baseline.neon`（974 messages）
- `.github/workflows/ci.yml`（uniquement des jobs PHP）、`README.md`（:635 déclaration inexacte）
- `.env.docker`（clés faibles）、`database/migrations/`（29，sans _rollback）
- `docs/EDITIONS.md`（contradictions）、`docs/FUNCTIONS.md`（dérive d'annexe）、`docs/CLAUDE.md`（104 vs 122 contrôleurs mesurés）
- branches git `lite/standard/full`（behind 41/41/20）

> Note de périmètre : contrôleurs mesurés `find app -path '*/controller/*.php'` = 122（y compris admin 14 + api 3 + contrôleurs métier + Index/Install）；l'étude dit 121，docs/CLAUDE.md périmètre métier 104，les trois différences proviennent de périmètres de comptage différents，déjà listé en D6 comme élément de gouvernance pour unifier le périmètre.
