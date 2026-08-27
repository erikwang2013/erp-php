# 監査報告書 — 2026-08-07

**プロジェクト**: erp-php（webman 5.2.0 / PHP 8.3.7 / workerman event-loop: select）
**範囲**: 全体動作テスト、詳細検査、P0/P1 問題の修正
**指示**: 「一通りテストして、動かしてみて、詳しく調べてまだ問題や最適化すべき箇所がないか見てくれ？」
**テスト結果**: OK (135 tests, 799 assertions) — すべて通過

---

## 1. テストと実行検証の結果

| 項目 | 結果 |
|---|---|
| PHPUnit フルセット | 135 tests / 799 assertions すべて通過 |
| サービス起動 (port 8787→一時 8791) | 正常起動、プロセスクラッシュなし |
| /health ヘルスチェック | code=0、database/redis/elasticsearch フィールド完備 |
| レート制限チェーン | /api/auth/login の連続リクエストで 429 を返す |
| JWT ブラックリスト / ログインロック | 正常に機能（Redis 修正後） |
| CS-Fixer | 31 ファイルのフォーマット違反を修正 |
| PHPStan | キャッシュ破損修正後に復旧（851 個の ORM マジックメソッド誤報、75 条の期限切れベースライン） |

---

## 2. P0 修正（ランタイム障害 — すべて修正・検証済み）

### 2.1 support\Redis クラス欠如 — セキュリティメカニズムが静かに無効化

- **現象**: `support\Redis` が存在しない（composer.json に webman/redis を導入したことがない）、9 ファイルがこれを参照。
- **根本原因**: 多数の `catch (\Throwable)` fail-open 設計がクラス欠如エラーを握りつぶし、レート制限、JWT ブラックリスト、ログインロック、封禁がすべて静かに無効化され、API は「一見正常」だが防御は一切ない状態。
- **修正**: `composer require webman/redis`；`config/redis.php` を環境変数化（REDIS_PASSWORD/HOST/PORT/DATABASE）。
- **検証**: /health が `redis: ok` を返す；レート制限テストが 429 を返す。

### 2.2 ApiVersion ミドルウェアのコンパイル失敗 — 全 /api ルート 500

- **現象**: `Interface "app\middleware\MiddlewareInterface" not found` — `use Webman\MiddlewareInterface;` の欠如。
- **修正後の二次エラー**: `Declaration must be compatible with Webman\MiddlewareInterface::process(Webman\Http\Request...)` — `support\Request` は `Webman\Http\Request` のサブクラスであり、パラメータの反変性契約に違反。
- **修正**: `Webman\Http\Request` / `Webman\Http\Response` のインポートに変更。

### 2.3 AdminAuth ミドルウェアのパラメータ反変性 — /admin ルートで worker クラッシュ

- **現象**: /admin/dashboard が worker Empty reply（コンパイルクラッシュ）をトリガー。
- **根本原因**: 2.2 と同じパラメータ反変性の問題。
- **修正**: `Webman\Http\Request` / `Webman\Http\Response` に変更（`support\Redis` は維持）。
- **検証**: 401 JSON を返す。

### 2.4 validator() ヘルパー関数が存在しない — ログイン 500

- **現象**: `Call to undefined function validator()`、99 ファイル 105 箇所で呼び出し。
- **修正**: `composer require illuminate/validation`；`app/functions.php` にヘルパー関数を実装（静的 $factory キャッシュ）。
- **落とし穴**: `Factory::__construct()` の第 1 引数は `Translator` でなければならず、`ArrayLoader` ではない。
- **残課題（P2）**: エラーメッセージが未翻訳（中国語ではなく `validation.required` が表示される）、zh_CN 言語パックの補充が必要。

### 2.5 CORS ハードコード + プリフライトレスポンスで CORS ヘッダー欠落

- **修正**: `app/common/CorsPolicy.php` を新規作成し、`CORS_ALLOWED_ORIGIN` 環境変数からホワイトリスト（カンマ区切り）を読み取り、origin をエコーバック；非一致時は CORS ヘッダーを送信しない。
- **重要ポイント**: `Route::fallback` はグローバルミドルウェアチェーンを通らないため、OPTIONS プリフライトは自身で CORS ヘッダーを付加する必要がある — fallback クロージャ内で処理済み。
- **セキュリティヘッダー**: 廃止された X-XSS-Protection を除去；CSP に `connect-src 'self'` を追加。

### 2.6 FastRoute BadRouteException — ルートの遮蔽

- **現象**: `Static route "/install" is shadowed by previously defined variable route`。
- **根本原因**: OPTIONS ワイルドカードルート `/{path:.+}` が後続の静的ルートを遮蔽；プラグルート（apidoc）は config/route.php の後にロードされる。
- **修正**: ワイルドカードルートを除去し、`Route::fallback` に変更（ルートファイルの末尾に置く必要がある）；`/crm/pool/rules` を resource から明示的な GET ルートに変更、`PoolController::rules()` を public に変更。

---

## 3. P1 修正（エンジニアリング品質）

- **3.1 PHPStan キャッシュ破損**: /tmp/phpstan/cache が削除済みの service/ ディレクトリ（マイクロサービス分割の残骸）由来で、古い絶対パスを含み phar エラーと CPU 0% ハングを引き起こす。キャッシュをクリアして再インストール後に復旧。851 個のエラーは webman ORM マジックメソッドの誤報；75 条のベースラインパスが存在しない service/ ディレクトリを指す（P2）。
- **3.2 CS-Fixer**: 31 ファイルの空白/use ソート違反を修正。
- **3.3 テスト同期**: `test_cors_response_is_assigned_correctly` を新実装（withHeaders + CorsPolicy）を検証するよう更新。

---

## 4. 前回監査（08-04）で見逃した根本原因

- テストが**ミドルウェアクラスのロード可能性**と**ルートの呼び出し可能性**をカバーしていない（class_exists / is_subclass_of では use 欠如とパラメータ反変性を捕捉できない）。
- コミット b1fe2de が主張する CORS/X-XSS 修正は実際のコードと一致しない — 監査結論が実行検証ではなくコミット情報に過度に依存。

---

## 5. 今回の変更リスト（git status: 41 修正 + 2 新規）

| ファイル | 変更 |
|---|---|
| app/middleware/ApiVersion.php | use Webman\MiddlewareInterface を追加；パラメータ型を Webman\Http に変更 |
| app/middleware/AdminAuth.php | パラメータ型を Webman\Http に変更 |
| app/middleware/Cors.php | CorsPolicy を使用するようリファクタリング；CSP/セキュリティヘッダー更新 |
| app/common/CorsPolicy.php | **新規**：CORS ホワイトリストポリシー |
| config/route.php | fallback ルート + /crm/pool/rules 修正 |
| app/controller/crm/PoolController.php | rules() を public に変更 |
| app/functions.php | validator() ヘルパー関数を新規追加 |
| config/redis.php | **新規**（composer 生成後に環境変数化） |
| composer.json / composer.lock | + webman/redis ^2.0, illuminate/validation ^11.0 |
| .env / .env.example | + CORS_ALLOWED_ORIGIN |
| tests/BackendEnhancementTest.php | CORS アサーション同期 |
| 残り ~30 ファイル | CS-Fixer フォーマット修正 |

---

## 6. P2 提案（環境/保留事項、未修正）

1. **.env の DB_PASSWORD が空** — MySQL root 認証失敗、`database: unavailable`；実際のパスワード設定が必要。
2. **ポート 8787 衝突** — cloud-php/service が使用中（別プロジェクト）；本番デプロイでは分離が必要。
3. **validator 中国語エラーメッセージ** — 言語パックのインストールまたはカスタム messages が必要。
4. **PHPStan ベースライン再構築** — 75 条のパスが削除済みの service/ ディレクトリを指すため、クリーンアップと再構築を推奨。
5. **fail-open 監査** — `catch (\Throwable)` の静かなエラー握りつぶし箇所を全体洗い出し（今回 1 箇所の重大な影響を発見）、fail-closed または明示的ログへの変更を推奨。

---

*報告生成: 2026-08-07、サービスは停止済み、ポートは 8787 に復旧。*
