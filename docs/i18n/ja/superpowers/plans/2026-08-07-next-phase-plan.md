# 次フェーズ（P4 / 進化期 1.1）プロジェクト計画

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
>
> 作成：システムアーキテクト ｜ 日付：2026-08-07 ｜ 根拠：3 件の事前調査（計画とギャップ / バックエンドと品質 / フロントエンド）+ 現地抜き打ち確認
> 状態：ドラフト（レビュー待ち）｜ 対象バージョン：1.1（進化期）

---

## 1. フェーズの位置づけ

P0〜P3 ロードマップはすべて納品済み：22 業務モジュール、163 テーブル、121 コントローラー、24 サービス、161 モデル、12 ミドルウェア；
Flutter 96 ページ + HarmonyOS 34 ページ；総合スコア 89/100。**本フェーズでは業務ドメインを追加せず**、「実装済みだがクローズドループになっていない」能力の補完、品質負債の整理、ドキュメントのドリフト解消を行い、長期保守可能な **1.1 進化版**を生み出す。

3 つのコア判断（すべて抜き打ちで確認済み）：

1. **多くの能力が「存在するが機能していない」**：TenantScope ミドルウェアとモデル trait が `config/middleware.php` に未登録（マルチテナントは空殻）；
   キューは redis/rabbitmq の二重ドライバー設定があるが `config/process.php` に消費プロセスなし；WebSocket 接続は JWT を検証しない；
   Flutter ダッシュボードの OMS/WMS/TMS 統計はハードコードされたダミー値で、バックエンドの `/dashboard/oms|wms|tms` エンドポイントは既存なのに呼び出されていない；
   フロントエンドは存在しない通知エンドポイント `/admin/notification/my/read` を呼んでいる（バックエンドは実際には `/admin/notification/read-all`）。
2. **品質とセキュリティの負債**：11 の業務モジュールがテストゼロ；PHPStan level 5 だがベースラインで 974 件のエラーを抑制；137 テストはすべて純粋な単体テストで、統合/E2E/カバレッジなし；
   `.env.docker` に多数の弱いキー；CI は PHP ジョブのみで、フロントエンドの品質ゲートなし。
3. **ドキュメントの体系的なドリフト**：テスト数 132/779→135/799→137/805 と 3 バージョンで不一致；FUNCTIONS.md の付録と実測の差が大きい；
   EDITIONS.md の数字が自己矛盾；lite/standard/full の 3 ブランチが main より 20〜41 commits 遅れ。

**原則**：「実装済みだがクローズドループになっていない」もの（死んだエンドポイント、未配線の TenantScope/キュー、mock ダッシュボード）を先に補い、次にテストと品質ゲート、その後に構造とドキュメントを改善する。すべてのタスクは小さく明確で、単一の agent セッション内で完了できる；確信が持てないものは「要検証」と明記する。

---

## 2. ギャップ分析（まとめ）

3 件の調査のギャップを **6 つの作業グループ**に整理。各項目に証拠パスを示す。

### 作業グループ A：業務クローズドループ補完（優先度最高）

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| A1 | 通知「すべて既読にする」フロントエンドが存在しないエンドポイントを呼ぶ | `apps/flutter/lib/app/pages/notification/notification_page.dart:43` が `/admin/notification/my/read` を呼ぶ；バックエンドのルートは `config/route.php:250` の `POST /admin/notification/read-all` | 確認済み |
| A2 | ダッシュボードの OMS/WMS/TMS 統計が mock のダミー値で、リクエストに JWT がない | `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（独立 Dio `baseUrl: http://localhost:8787`、インターセプターなし；`omsStats/wmsStats/tmsStats` はハードコード；コメント "Mock values for now"）；バックエンドの実エンドポイント `config/route.php:231-233` | 確認済み |
| A3 | TenantScope ミドルウェアとモデル trait が未配線で、マルチテナントは空殻 | `app/middleware/TenantScope.php` + `app/model/concerns/TenantScope.php` は存在；`config/middleware.php` のグローバルチェーンは Locale/Cors/SecurityFilter/RateLimit/TracingId のみ登録、route.php の各グループにも参照なし | 確認済み |
| A4 | キューは二重ドライバーだが消費プロセスがなく、エンドツーエンドで機能しない | `config/queue.php`（デフォルト redis、オプション rabbitmq）；`config/process.php` は webman/socket/monitor の 3 プロセスのみ | 確認済み |
| A5 | WebSocket に認証なし | `app/process/WebSocket.php:23` にコメント "could validate JWT here"；`:47-50` の auth メッセージは直接 success:true を返し、token を検証しない | 確認済み |
| A6 | HarmonyOS 25 個の一覧ページでページングパラメータが機能しない（単引用符内 `${this.page}` が補間されない） | `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets:24`（抜き打ち確認済み）；他 24 箇所も同パターン | 確認済み（一覧は全量照合待ち） |
| A7 | 業務アクションエンドポイントが広範囲にフロントエンド未接続（精算/3 表/履行/承認/給与計算等） | カバレッジマトリックスの調査結論；例：購買/販売に精算ページなし、財務に 13 エンドポイント不足、CRM に follow/funnel/契約フロー不足 | 要検証（モジュールごとに一覧照合が必要） |
| A8 | 多くの業務ページのフォームが汎用の name/code フィールドのみ | 調査結論（販売注文/記帳伝票の作成が名前とコードのみ） | 要検証（ページごとに照合が必要） |

### 作業グループ B：テスト体制の再構築

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| B1 | 11 の業務モジュールがテストゼロ：crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow | `tests/` の 19 テストファイルは admin/finance/inventory/oms/wms/tms/notification/hr/mrp/セキュリティ基底クラスのみカバー；上記 11 モジュールに専用テストファイルなし——うち crm/eam/dms/quality/report/workflow の 6 モジュールはどのテストファイルにも**一切言及なし**；project/purchase/sales/product/bi は汎用基底クラステストや隣接モジュールのテストに偶然参照されるのみ（ControllerPatternTest のパターンサンプリング、bootstrap.php のルート一覧、InventoryServiceTest の purchase/product 入庫コンテキスト、DoubleEntryServiceTest の "bi" は debit_amount の部分文字列）で、いずれも専用カバレッジではない | 確認済み |
| B2 | 統合/E2E/カバレッジなし；137 tests / 805 assertions はすべて純単体テスト（実測 1.2 秒以内で完了、純メモリ） | `vendor/bin/phpunit` 実測 "OK (137 tests, 805 assertions)" | 確認済み |
| B3 | PHPStan level 5 だがベースラインで 974 件のエラーを抑制 | `phpstan-baseline.neon` 実測 974 個の message ノード | 確認済み |
| B4 | CI にカバレッジ収集なし、統合テストジョブなし | `.github/workflows/ci.yml`（PHP 8.2/8.3/8.4 × mysql8/redis7、composer validate/audit + php -l + PHPStan + CS-Fixer + PHPUnit のみ） | 確認済み |
| B5 | purchase/sales コントローラーがサービスにハードコード依存 | `app/controller/sales/DeliveryController.php:142-143`、`app/controller/purchase/ReceiveController.php:142-143`（両ファイルとも `use` 宣言は :15-16、`new InventoryService()/new FinanceService()` のインスタンス化は :142-143） | 確認済み |

### 作業グループ C：インフラとセキュリティガバナンス

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| C1 | `.env.docker` の弱いキー | `JWT_SECRET_KEY=change-me-...`、`ENCRYPTION_KEY/ENCRYPTABLE_KEY=change-me-...`、`DB_PASSWORD=root`、`ES_PASSWORD=changeme`、`RABBITMQ_PASSWORD=guest`（.env.docker:15,32,37,51,67,81） | 確認済み |
| C2 | 環境変数の強検証が不完全 | 調査：ENCRYPTION_KEY のみ env_required 経由 | 要検証（config/jwt.php、encryption.php を照合） |
| C3 | fail-open のサイレントエラー吞み込み | 調査結論；範囲は監査待ち（空 try/catch、ログなし catch） | 要検証（grep 監査が必要） |
| C4 | backup-validator.sh と移行ごとの `_rollback.sql` が欠落 | `find` で全リポジトリに一致なし；`database/migrations/` の 29 個の SQL 移行にいずれも対応するロールバックファイルなし | 確認済み |
| C5 | 通知チャネルの stub（email/wecom/dingtalk） | `app/service/notification/ChannelRouter.php:23` `default => false, // stub for future implementation` | 確認済み |
| C6 | 監視の欠落：キューの滞留/WebSocket 接続数の指標なし | `app/admin/controller/MetricsController.php` の既存 5 gauge | 部分的に確認 |

### 作業グループ D：バージョンマトリックスとドキュメントガバナンス

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| D1 | lite/standard/full ブランチが main より 20〜41 commits 遅れ | `git rev-list --left-right --count main...lite|standard|full` 実測：41/41/20 behind、かつ lite/standard はそれぞれ 6〜7 ahead の独自コミットあり | 確認済み |
| D2 | EDITIONS.md の数字が自己矛盾 | 概要表：コントローラー 48/42/70、業務モジュール 6/6/12；アップグレードパスの段落は 12/12/19 モジュール、163 テーブルと記載；実測 121 コントローラーと不一致 | 確認済み |
| D3 | FUNCTIONS.md 付録のドリフト | 付録は 11 ファイル/90 メソッド/168 アサーション/9 ミドルウェア/22 移行と記載；実測は 19〜20 ファイル/137 テスト/805 アサーション/12 ミドルウェア/29 移行 | 確認済み |
| D4 | テスト数が 3 バージョンでドリフト（132/779→135/799→137/805） | ドキュメント履歴と git コミット記録 | 確認済み |
| D5 | 完成度マトリックスが QMS/EAM/DMS/BI を 🔴 と表記するがコードは既に存在 | `docs/FUNCTIONS.md:555` 付近のマトリックス vs `app/controller/{quality,eam,dms,bi}/` は実装済み | 確認済み |
| D6 | コントローラー口径の混乱：docs/CLAUDE.md は「業務コントローラー 104 個」と記載、実測は全量 122 | `find app -path '*/controller/*.php' | wc -l` = 122（admin 14 + api 3 + 業務 104 + Index/Install を含む）；調査口径 121 | 確認済み（口径差異） |
| D7 | 移行数の口径：調査 30 / docs/CLAUDE.md 29 / FUNCTIONS.md 22 | `ls database/migrations/*.sql | wc -l` = 29（000030 まで採番、000007/000008 欠落） | 確認済み（29 が実測） |

### 作業グループ E：フロントエンド品質と整合

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| E1 | CI に flutter analyze/test/build、hvigor ビルドなし | `.github/workflows/ci.yml` は PHP ジョブのみ | 確認済み |
| E2 | README が CI に Flutter 静的解析を含むと主張、事実と不一致 | `README.md:635` "Flutter 静态分析 (flutter analyze)" vs ci.yml にこのステップなし | 確認済み |
| E3 | Flutter はスモークテスト 1 件のみ | `apps/flutter/test/widget_test.dart` が唯一のテストファイル | 確認済み |
| E4 | HarmonyOS の token が永続化されない（AppStorage はメモリのみ、コールドスタートでログインページに戻る） | 調査結論（`apps/harmonyos/entry/src/main/ets/service/ApiService.ets` を要照合） | 要検証 |
| E5 | HarmonyOS 25 ページがテンプレート化され、読み取り専用の name/code 一覧で追加・削除・変更なし | OrderListPage.ets 全 65 行を抜き打ち：name/code の読み取り専用一覧のみ | 確認済み |
| E6 | フロントエンドのカバレッジ深度不足（A7/A8 参照） | 同上 | 要検証 |

### 作業グループ F：API 階層とアーキテクチャガバナンス（低優先、できる範囲で）

| # | ギャップ | 証拠パス | 状態 |
|---|------|----------|------|
| F1 | /api のバージョン化は 3 コントローラーのみ、業務はすべて /admin の単一ブロック | `app/api/v1/controller/` は Captcha/Auth/Product の 3 つのみ | 確認済み |
| F2 | 10 モジュールのコントローラーがサービス層なしでモデルを直接参照 | 調査結論（crm/product 等のコントローラーがモデルクエリを直接使用） | 部分的に確認（全量監査待ち） |
| F3 | purchase/sales が依存注入ではなくハードコード `new` でサービスを使用 | B5 の証拠 | 確認済み |

---

## 3. 段階別計画

優先度順に 3 バッチ（P0→P1→P2）、**各期は独立してリリース可能、受入基準はすべて定量化**。総工期約 **8〜9 週間**（並行度の仮定：**開発者 2〜3 名の並行 + agent チーム協働**で見積もり；単一タスク合計は約 **77 人日**——P0 ≈12.5d、P1 ≈29.5d、P2 ≈35d——単独で直列実行する場合は約 15 週間。並行の根拠：A1/A4/A5 等のバックエンド小タスクは互いに独立で並行可；B1 の各モジュールテストはサブタスクに分割して並行可；B/C グループと E/D グループは期をまたいで重複可；Flutter/HarmonyOS フロントエンドタスクとバックエンドタスクは互いにブロックしない；タスク間の明示的依存は §5 参照）。

**番号体系**：期別タスク番号は §2 のギャップ番号と 1 対 1 対応（A1〜A8 → A1〜A6/A7-1/A7-2/A8-1、B1〜B5 → B1〜B5、C1〜C6 → C1〜C6、D1〜D7 → D1〜D5、E1〜E6 → E1/E3/E4/E5、F2/F3 → F2/F3）；うち D6/D7（コントローラーと移行の口径）は D3 タスクに統合、E2（README の不実な主張）は E1 の受入に統合、E6（カバレッジ深度）は A7-2 に統合、F1（/api バージョン化）は本期内に実施しない（§6 参照）；他に i18n タスクが調査の「Flutter i18n 未完了」に対応し、ギャップ表の番号ではない。

### 3.1 第 1 バッチ P0：クローズドループ・ベースライン（第 1〜2 週）

**目標**：死んだエンドポイントとダミーデータを排除し、既存の未配線能力（TenantScope/キュー/WebSocket）を利用可能にするか、明確にダウングレードする。

| タスク | 内容 | 対象範囲 | 受入基準 | 工期 |
|------|------|----------|----------|------|
| A1 | 通知「すべて既読」を修正：フロントエンドは `POST /admin/notification/read-all` を呼ぶように変更（またはバックエンドに別名ルートを追加、二択、推奨はフロントエンド変更） | `notification_page.dart` + `config/route.php` | 手動/自動呼び出しが成功；このルートが存在する PHPUnit アサーションを 1 件追加 | 0.5d |
| A2 | ダッシュボードを実データに接続：独立 Dio を削除し ApiService（JWT インターセプター）経由に変更；OMS/WMS/TMS の 3 タブで `/dashboard/oms\|wms\|tms` を呼ぶ；ハードコードのダミー値を削除；Redis 5m キャッシュの意味論は維持 | `dashboard_controller.dart` + 関連ページ | ログイン状態でダッシュボードの 3 タブがバックエンドの実データを表示、Network パネルで 200 かつ Authorization ヘッダー付きを確認；mock コメントを削除 | 2d |
| A3 | TenantScope の配線：`/admin` ルートグループに登録；テナント ID は JWT クレームまたは `X-Tenant-Id` ヘッダーから取得（**決定ポイント**、§5 参照）；モデル trait は準備済みで大きな変更不要 | `config/route.php`、`app/middleware/TenantScope.php`、`config/middleware.php` | 2 つのテナントのデータが互いに見えない（統合テストを追加）；テナントヘッダーなしは 400 を返し、サイレントに通過しない；**代替ダウングレード**：時期尚早と判断した場合は、ドキュメントに「マルチテナントは予約機能」と明記し開始手順を示す。受入＝ドキュメントとコードの一致 | 2d |
| A4 | キューのエンドツーエンド：config/process.php に `redis-queue` 消費プロセスを追加（デフォルト redis ドライバー）；観測可能なスモークタスクを 1 件追加（例：操作ログの非同期書き込み）；rabbitmq への切替手順をドキュメントに記載 | `config/process.php`、`app/queue/` | 起動後に消費プロセスがオンライン（`php start.php status`）；スモークタスク投入後、対象の副作用が 5 秒以内に出現 | 1d |
| A5 | WebSocket 認証：接続確立/`auth` メッセージで JWT を検証（AdminAuth ロジックを再利用）、不正 token は auth_result:false を返して切断；ドキュメントも同期 | `app/process/WebSocket.php` + フロントエンド接続箇所 | token なし/偽造の接続は拒否；正しい token の接続は成功；テスト 1 件追加 | 1d |
| A6 | HarmonyOS ページング修正：25 箇所の単引用符補間をテンプレート文字列/連結に変更；page 自増 + 下端ロード + プルリフレッシュ；ページングコンポーネントを共通化 | `apps/harmonyos/entry/src/main/ets/pages/**`（25 ファイル） | grep で全リポジトリに `${this.page}` 単引用符パターンが残存しない；一覧のページ送りリクエストパラメータが正しい；ビルド成功 | 2d |
| A7-1 | 死んだエンドポイントを全量ゼロに：「フロントエンド URL × バックエンドルート」の自動比較を実行（スクリプトで Flutter/HarmonyOS のリクエスト文字列と `config/route.php` を抽出）、残り差異の一覧を出力 | `apps/flutter/lib`、`apps/harmonyos/.../pages`、`config/route.php` | 比較スクリプトの成果物をリポジトリに格納（docs/）；差異一覧のうち「フロントエンドが呼ぶがバックエンドに存在しない」をゼロに（存在しないが妥当なものはホワイトリストに明記） | 2d |
| A8-1 | 高価値フォームのフィールド補完：購買/販売注文、記帳伝票ページに業務の重要フィールド（金額/日付/取引先/明細行）を追加。補完のみでフォームエンジンは作らない | 対応する Flutter ページ | フォームで業務フィールド付きの完全な伝票を作成可能、インターフェース 200 | 2d |

**P0 受入まとめ**：A1〜A6 がすべて実装；死んだエンドポイント一覧がゼロ；CI 全緑；新たなドキュメントドリフトなし（変更は docs/CLAUDE.md の機能一覧に同期）。

### 3.2 第 2 バッチ P1：テストとセキュリティのベースライン（第 3〜5 週）

**目標**：テスト体制を「純単体テスト」から「単体+統合+カバレッジ」にアップグレードし、セキュリティの弱点をゼロにする。

| タスク | 内容 | 対象範囲 | 受入基準 | 工期 |
|------|------|----------|----------|------|
| B1 | 11 の業務モジュールにテスト追加：モジュールごとにサービス/モデル層テストを作成、CRUD + コアアクション（精算、承認フロー、品質検査フロー、設備作業指示書等）をカバー | `tests/`（crm/eam/dms/quality/project/purchase/sales/product/bi/report/workflow のテストファイルを新規追加） | 新規 ≥150 tests / ≥500 assertions；11 モジュールそれぞれ ≥10 tests；`vendor/bin/phpunit` 全緑 | 2w |
| B2 | 統合テスト：CI の既存 mysql8/redis7 services を利用し、統合テストグループを新規追加（実 DB の CRUD + トランザクションロールバック + TenantScope 分離検証 + キュースモーク） | `tests/Integration/` + `phpunit.xml` のグループ | 統合グループが CI で全緑；ローカルで `--group=integration` 実行可 | 1w |
| B3 | E2E スモーク：実 HTTP で health→login→コア CRUD→ダッシュボードを通す、スクリプト化 | `tests/E2E/`（curl/php スクリプト） | CI の新ジョブで 10 本のコアチェーンが成功、失敗は即レッド | 2d |
| B4 | カバレッジ：phpunit --coverage を導入、しきい値設定（業務層 ≥40%、全体 ≥30%、CI が xdebug 収集をサポートするかは要検証） | `phpunit.xml`、`ci.yml` | CI がカバレッジレポートを出力；しきい値未達で失敗 | 1d |
| B5 | コントローラーのサービス化（頻出 4 モジュール）：finance/inventory/sales/purchase コントローラーの `new` を削除し、コンテナ取得に変更（`support\Container`）、B1 テストの布石 | `app/controller/{finance,inventory,sales,purchase}/**` | `new InventoryService/FinanceService` の残存なし；既存テスト全緑 | 3d |
| C1 | 弱いキーをゼロに：`.env.docker`/`.env.example` をランダムプレースホルダー + 起動時の強検証（欠落/プレースホルダーと等しい場合は起動拒否）に変更；CI に `env 検証` ステップを追加 | `.env*`、`config/*.php`、`ci.yml` | `change-me` で起動すると即失敗し案内を表示；Docker 新インスタンスはランダムキーを自動生成 | 1d |
| C2 | 環境変数の強検証拡張：JWT_SECRET_KEY/ENCRYPTABLE_KEY/DB_PASSWORD を env_required に含める（先に config/jwt.php の現状を照合、要検証） | `config/*.php` | 主要キーのいずれかが欠落すると起動失敗、エラーメッセージは中国語で明確 | 1d |
| C3 | fail-open 監査：空 catch/ログなし catch を grep し、fail-closed + ログ（TraceId 含む）に変更 | 全 app/ | 監査一覧をリポジトリに格納；修正項目はすべてテストまたはログで裏付け | 2d |
| C4 | 移行ガバナンス：`database/backup/backup-validator.sh`（バックアップ後の自動復元検証）と 29 個の移行ごとの `_rollback.sql`（install.sql からテーブル構造を逆算）を追加 | `database/` | validator スクリプトがバックアップファイルで成功（バックアップ→復元→テーブル数/行数比較）；各移行ファイルの隣に同名の `_rollback.sql` あり | 2d |
| C5 | 通知チャネルの実装（ギャップ C5 対応）：少なくとも 1 つの利用可能なチャネルを通す（推奨 email：SMTP ドライバーまたはファイルログドライバーで送信を実装）；時期尚早と判断した場合は「サイト内通知のみ + email/wecom/dingtalk アダプタポイント予約」へのダウングレードをドキュメント化し接続手順を示す（二択、明示的な決定が必要） | `app/service/notification/ChannelRouter.php` + 新規ドライバークラス + docs | メールドライバー：通知送信成功後に ChannelRouter が true を返す（テストはログドライバーでアサート）；ダウングレードの場合：ChannelRouter.php:23 のコメントと docs に「予約」状態を明記し、"stub for future implementation" の曖昧さを解消 | 1.5d |
| C6 | 監視の指標追加：キューの滞留（redis LLEN）、WebSocket オンライン接続数 | `MetricsController.php` | `/metrics` 出力に gauge を 2 つ追加 | 1d |

**P1 受入まとめ**：テスト総数 ≥287（137+150）；カバレッジレポートを出力してしきい値を通過；弱いキー/キー欠落で起動失敗；validator とロールバックスクリプトが整備；通知チャネルが少なくとも 1 つ利用可能または明確にダウングレードをドキュメント化；CI に統合/E2E/カバレッジジョブを追加して全緑。

### 3.3 第 3 バッチ P2：ドキュメント、バージョンマトリックスとフロントエンド深度（第 6〜8 週）

**目標**：ドキュメントの数字とコードの事実を完全に整合（自動検証）、バージョンマトリックスの信頼性を回復、フロントエンドの高価値深度を補完。

| タスク | 内容 | 対象範囲 | 受入基準 | 工期 |
|------|------|----------|----------|------|
| D1 | 3 ブランチ同期：main を lite/standard/full にマージしコンフリクトを解決、3 ブランチとも CI 全緑；**決定ポイント**：以降は「main を唯一の開発源とし、バージョンブランチはリリース時にのみ cherry-pick」戦略を採用 | git 3 ブランチ + ci.yml | 3 ブランチとも behind=0；各ブランチ CI 緑；コンフリクト解決を記録 | 1w |
| D2 | EDITIONS.md の書き直し：実測を基準に（テーブル/コントローラー/モジュール数はコードカウントスクリプトから取得）、自己矛盾の段落を削除 | `docs/EDITIONS.md` | ドキュメントのすべての数字がスクリプト出力と一致 | 1d |
| D3 | ドキュメント統計の自動化：`scripts/doc-stats.sh`（コントローラー/サービス/モデル/移行/テスト/ミドルウェアのカウント + phpunit 出力）を作成し、FUNCTIONS.md の付録をその出力参照に変更；同時に D6（コントローラー口径 104/121/122）と D7（移行口径 22/29/30）をスクリプトの単一口径に統一 | `scripts/doc-stats.sh`、`docs/FUNCTIONS.md`、`docs/CLAUDE.md` | スクリプト出力とドキュメントが一致；README/docs のすべての数字がスクリプトで再現可能（コントローラー/移行口径の単一化含む） | 2d |
| D4 | 完成度マトリックスの修正：QMS/EAM/DMS/BI 等の実際に実装済みの項目を ✅ に変更、コード証拠を添付 | `docs/FUNCTIONS.md` | マトリックスが `app/controller/` ディレクトリと 1 対 1 対応、🔴/✅ のずれなし | 1d |
| D5 | CI ドキュメント検証ジョブ：doc-stats とドキュメントの比較を実行、ドリフトは即レッド | `ci.yml` + スクリプト | 数字を 1 箇所改ざんすると CI がレッド（自己テストで実演） | 1d |
| E1 | Flutter CI ジョブ：flutter analyze + flutter test + build web を ci.yml に組み込む | `ci.yml`、`apps/flutter/` | 3 ステップとも全緑；README.md:635 の主張と実際が一致 | 1d |
| E3 | Flutter テスト拡充：ApiService インターセプター/401 リフレッシュ、AuthService フロー、主要フォーム検証、≥20 の widget/unit テスト | `apps/flutter/test/` | `flutter test` 全緑、≥20 tests | 1w |
| E4 | HarmonyOS token 永続化：AppStorage の永続化実装 + コールドスタート復元 + 401 リフレッシュロジック（先に ApiService の現状を照合、要検証） | `apps/harmonyos/.../service/ApiService.ets` | プロセスを殺して再起動してもログイン状態を維持；token 期限切れで自動リフレッシュ | 2d |
| E5 | HarmonyOS コアページに追加・削除・変更を補完：価値順に（購買/販売/在庫/財務/OMS から各 2〜3 個の一覧ページ）、各ページに 新規/編集/削除 アクションとフォームを補完 | `apps/harmonyos/.../pages/{purchase,sales,inventory,finance,oms}/**` | 選択した ≥10 個の一覧ページが追加・削除・変更を持ちバックエンドと疎通；hvigor ビルド成功（鴻蒙 SDK がない環境は「CI 環境の準備待ち」と明記） | 1w |
| i18n | Flutter 最小 i18n（調査の「Flutter i18n 未完了」対応）：ApiService のエラーメッセージとログイン/ナビゲーション/ダッシュボードの主要文言を i18n に組み込み（arb ファイル、バックエンド `app/common/I18n.php` と連携）；**最小限のみで、全ページの文言改造はしない** | `apps/flutter/lib/app/services/`、`apps/flutter/lib/l10n/` | 主要エラーメッセージと ≥10 箇所のページ文言が言語切替可能（en/zh）；`flutter test` 全緑 | 2d |
| A7-2 | フロントエンド深度カバレッジ：A7-1 の比較一覧に基づき、購買/販売精算ページ、財務の 3 表/期末振替/銀行口座、CRM follow/funnel/契約フロー等の主要エンドポイントページを補完 | `apps/flutter/lib/app/pages/**` | 比較一覧の「バックエンドに存在するがフロントエンドが未カバー」の高優先項目（精算/3 表/履行/承認/給与）をゼロに | 1w |
| F2/F3 | サービス層の軽量抽出（任意、できる範囲で）：モデル直接参照が最も重い 3〜5 モジュールに薄いサービス層 + 依存注入を抽出；**全量リファクタリングは強制しないことを明示** | `app/controller/{crm,product,project,hr,manufacturing}/**` | 抽出モジュールのコントローラーにモデル直接参照なし；既存テスト全緑；非抽出モジュールはドキュメントに「コントローラーがモデルを直接参照、既知の技術負債」と明記 | 1w |

**P2 受入まとめ**：3 ブランチ同期かつ CI 緑；docs の数字がスクリプトで再現可能；CI に Flutter ジョブとドキュメント検証を含む；Flutter ≥20 テスト；HarmonyOS 永続化 + ≥10 ページの追加・削除・変更；高優先エンドポイントのカバレッジゼロ。

---

## 4. 受入基準（まとめ、すべて検証可能）

- **エンドポイント**：A1 通知エンドポイント、A2 `/dashboard/oms|wms|tms`、A7 高優先エンドポイントがすべて curl で JWT 付き呼び出し可能、200/業務データを返す。
- **テスト**：`vendor/bin/phpunit` 全緑（≥287 tests）；`flutter test` 全緑（≥20）；統合/E2E ジョブが CI で緑。
- **セキュリティ**：`change-me` キーで起動失敗；WebSocket の不正 token が拒否される；空 catch のサイレントエラー吞み込みなし（監査一覧）。
- **チャネル/i18n**：通知が少なくとも 1 チャネル利用可能または明確にダウングレードをドキュメント化；Flutter の主要エラーメッセージと ≥10 箇所の文言が中英切替可能（最小限）。
- **CI**：`.github/workflows/ci.yml` の全ジョブが緑（PHP マトリックス + 統合 + カバレッジ + flutter + ドキュメント検証）。
- **ドキュメント**：`scripts/doc-stats.sh` の出力と docs の全数字が一致（ドリフトは CI レッド）。
- **ブランチ**：`git rev-list --left-right --count main...lite|standard|full` がすべて `0 0`。
- **フロントエンド**：HarmonyOS に `${this.page}` 単引用符の残存なし；コールドスタートでログイン維持；コアページの追加・削除・変更がバックエンドと疎通。

---

## 5. 依存関係とリスク

**依存関係**：
- A グループ（クローズドループ）→ B グループ（テスト）：B1/B2 のテストは**実際に利用可能な**エンドポイントを対象とする必要があるため、P0 で死んだエンドポイントと配線を先に修正し、P1 でテストを補う。
- B5（コントローラーのサービス化）→ B1（テスト）：**カバーする finance/inventory/sales/purchase の 4 モジュールのテストの布石にすぎない**（`new` ハードコードを排除すればサービスを mock 注入可能；うち purchase/sales はテストゼロのモジュール、finance/inventory は既存テストがありついでに改善可能）；他のテストゼロモジュール（crm/eam/dms/quality/project/product/bi/report/workflow）のテストは B5 に**依存せず**、B5 と並行推進可能。
- D1（ブランチ同期）→ D3/D5（ドキュメント検証）：同期後、main が唯一の事実源になり、ドキュメント口径も唯一になる。
- E1（Flutter CI）→ E3（テスト拡充）：先にゲートを設けてから、テスト拡充が保護の意味を持つ。

**リスクと対策**：
| リスク | 影響 | 対策 |
|------|------|------|
| TenantScope の配線が全 /admin クエリに影響し、データ可視性のリグレッションを招く可能性 | 高 | 統合テストを先行；JWT クレームでテナント取得（フロントエンド改造不要）；または P0 内で「ドキュメントに予約と明記」にダウングレードし明確に決定 |
| 3 ブランチ同期のマージコンフリクトでリグレッションを招く可能性 | 中高 | 先に main を全緑に；マージ後、3 ブランチとも各自の CI が全緑になってから納品；コンフリクト解決を記録 |
| キューの消費プロセスが一部環境（rabbitmq）で利用不可 | 中 | デフォルト redis ドライバー（CI に redis7 あり）、rabbitmq はドキュメントで切替手順のみ |
| WebSocket 認証変更で既存クライアントを壊す | 中 | 前後端を同一マイルストーン内で協働修正；不正 token は拒否するが正規セッションには影響しない |
| カバレッジマトリックス/フォームフィールド一覧が調査結論で、一部「要検証」 | 中 | A7-1 で先に自動比較スクリプトを作成し、スクリプト結果を基準に、印象でページを補わない |
| サービス層リファクタリングの範囲が暴走 | 中 | 3〜5 モジュールのみ抽出と明示、全量は強制しない；/api の全量バージョン化はしない（F1 は本期内に実施しない） |
| カバレッジしきい値が CI 環境で利用不可（xdebug 未インストール） | 低 | 先にローカルでレポートとドキュメントしきい値、CI 収集能力は「要検証」の後に接続 |
| HarmonyOS CI（hvigor）に鴻蒙 SDK が必要、公共 CI 環境にない可能性 | 中 | 「CI 環境の準備待ち」と明記；ローカルビルド検証を基準に、他のタスクをブロックしない |

---

## 6. 明確にやらないこと

ロードマップ §12 の除外項目を継続。強い理由がない限り（個別のレビューと起案が必要）：
- ❌ マイクロサービス分割 / K8s デプロイ（実験は `.claude/worktrees/microservices-split/` に留め、メインラインに統合しない）
- ❌ AI/ML 能力（予測、スマートレコメンド、NLP）
- ❌ ネイティブ App（iOS/Android ネイティブ）——Flutter が全プラットフォームをカバー済み
- ❌ GraphQL インターフェース
- ❌ ハードウェア連携（IoT/スキャナ/プリンタ直結）
- ❌ マルチテナントの完全な商用化ソリューション（SaaS 課金、テナント自助開通）——本期内は最小配線またはドキュメント化予約のみ
- ❌ /api の全量バージョン化（F1）——業務は引き続き /admin、アーキテクチャ負債として記録のみ
- ❌ 全量サービス層リファクタリングと全量フォーム作り直し——価値順に抽出、「ビッグバン」式リファクタリングはしない
- ❌ HarmonyOS の全ページ補完——高価値コアページの追加・削除・変更のみ
- ❌ Flutter の全量 i18n 文言改造——本期内は最小限のみ（エラーメッセージ + ≥10 箇所の主要文言）、全ページの多言語は後のバージョンに

---

## 7. マイルストーン提案

| マイルストーン | 時期 | 内容 | 出口基準 |
|--------|------|------|----------|
| **M1 クローズドループベースライン** | 第 2 週末 | A グループ全部：死んだエンドポイントゼロ、ダッシュボード実データ、TenantScope/キュー/WebSocket 実装、HarmonyOS ページング修正 | P0 受入まとめ全通過 |
| **M2 品質ベースライン** | 第 5 週末 | B グループ全部 + C グループのセキュリティ項目：11 モジュールテスト、統合/E2E/カバレッジ、弱いキーゼロ、fail-open 監査、移行ガバナンス、通知チャネル | P1 受入まとめ全通過 |
| **M3 フロントエンド品質** | 第 6 週末 | E グループ：Flutter CI ジョブ + テスト拡充、HarmonyOS token 永続化とコアページの追加・削除・変更 | flutter CI 緑、永続化が機能、≥10 ページの追加・削除・変更 |
| **M4 バージョンとドキュメントガバナンス** | 第 7 週末 | D グループ：3 ブランチ同期、EDITIONS/FUNCTIONS 書き直し、doc-stats 自動化 + CI 検証 | ブランチ同期、ドキュメントドリフトで即レッド |
| **M5 深度カバレッジ** | 第 8 週末 | A7-2 フロントエンド深度 + F グループのサービス層軽量抽出 | 高優先エンドポイントのカバレッジゼロ、抽出モジュールにモデル直接参照なし |
| **M6 1.1 リリース** | 第 9 週末 | 全量リグレッション、リリースノート（CHANGELOG）、ドキュメント最終検証、アーカイブ | 全マイルストーンの出口基準合格（ハード指標）：テスト総数 ≥287 かつ phpunit 全緑、カバレッジレポートがしきい値通過、ci.yml 全ジョブ緑（PHP マトリックス+統合+カバレッジ+flutter+ドキュメント検証）、3 ブランチ同期 0 0、死んだエンドポイント一覧ゼロ、doc-stats ドリフト即レッドの仕組みが機能；CHANGELOG とドキュメント最終検証合格；レビュー再審査は参考のみで、スコアしきい値は設けない |

---

## 付録：本計画で抜き打ち検証した主要ファイル

- `config/middleware.php`、`config/route.php`（:231-233 dashboard エンドポイント、:248-251 通知ルート、:387-415 ミドルウェアグループ）
- `config/process.php`、`config/queue.php`
- `app/middleware/TenantScope.php`、`app/model/concerns/TenantScope.php`
- `app/process/WebSocket.php`（:23、:47-50）
- `app/service/notification/ChannelRouter.php`（:23 stub）
- `app/controller/sales/DeliveryController.php`（:142-143）、`app/controller/purchase/ReceiveController.php`（:142-143、両ファイルとも `new` インスタンス化はこの位置；`use` 宣言は :15-16）
- `app/api/v1/controller/`（コントローラー 3 つのみ）
- `apps/flutter/lib/app/pages/dashboard/dashboard_controller.dart`（mock 統計 + 独立 Dio）
- `apps/flutter/lib/app/pages/notification/notification_page.dart`（:43 死んだエンドポイント）
- `apps/harmonyos/entry/src/main/ets/pages/oms/OrderListPage.ets`（:24 補間バグ）
- `tests/`（19 テストファイル一覧）、`vendor/bin/phpunit` 実測 137/805
- `phpstan-baseline.neon`（974 message）
- `.github/workflows/ci.yml`（PHP ジョブのみ）、`README.md`（:635 不実な主張）
- `.env.docker`（弱いキー）、`database/migrations/`（29 個、_rollback なし）
- `docs/EDITIONS.md`（自己矛盾）、`docs/FUNCTIONS.md`（付録ドリフト）、`docs/CLAUDE.md`（104 vs 実測 122 のコントローラー口径）
- git ブランチ `lite/standard/full`（behind 41/41/20）

> 口径の説明：コントローラー実測 `find app -path '*/controller/*.php'` = 122（admin 14 + api 3 + 業務コントローラー + Index/Install を含む）；調査口径 121、docs/CLAUDE.md の業務口径 104、3 者の差は統計範囲の違いによるもので、D6 でガバナンス項目として口径を統一する。
