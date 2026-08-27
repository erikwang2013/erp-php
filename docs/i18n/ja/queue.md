# キュー（Queue）の使用とドライバー切替ガイド

> 関連設定: `config/queue.php`（ドライバーと接続）、`config/process.php`（消費プロセス）、`app/queue/`（タスククラス）。
> 関連実装: `app/queue/RedisQueue.php`（プロデューサーツール）、`app/process/QueueConsumer.php`（消費プロセス）、`app/queue/redis/SmokeTask.php`（スモークタスク）。

## 1. 現状：Redis LIST ベースの最小実装

プロジェクトは現在 `webman/redis-queue` 拡張パッケージを**インストールしていません**（`composer show | grep queue` は推移依存の
`illuminate/queue` / `illuminate/redis` のみで、webman キュー用パッケージはありません）。そのためキューはエンドツーエンドで
**Workerman ネイティブ Timer + Redis LIST** の最小実装を採用しています：

- **保存**：Redis LIST、キーは `erp:queue:{queue}`（デフォルト `queue` は `config/queue.php` の
  `connections.redis.queue`、すなわち `default`）。
- **生産**：`RedisQueue::push(ClassName::class, 'consume', $data)` が `LPUSH` を実行。
- **消費**：`config/process.php` の `redis-queue` プロセス（count=1）が、`onWorkerStart` 後に
  0.5 秒ごとに `LPOP` でキューを空にし、メッセージ本文 `{class, method, data}` のホワイトリストに基づいてタスククラスへ振り分けます。
- **失敗処理**：1 件のメッセージ失敗でも消費ループは中断せず、自動リトライ（attempts+1、最大 3 回）、
  上限超過はデッドレターキュー `erp:queue:failed` に入りエラーログを書き込みます。
- **指数バックオフ**：リトライは即時実行ではなく、遅延セット（zset、キー `erp:queue:{queue}:delay`）に書き込んで遅延エンキューし、
  n 回目のリトライ遅延は `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` 秒
  （`app/process/QueueConsumer.php` の定数: base=5s、cap=120s、実際の 3 回上限の下では 5s/10s）、
  期限到達後に消費プロセスがメインキューへ戻し、失敗メッセージのストーム的リトライを回避します。
- **メッセージ本文の規約は公式 `webman/redis-queue` のジョブ形式と一致**（`class` / `method` / `data`）、
  将来の痛みのないマイグレーションに備えます。

### スモーク検証（エンドツーエンド）

1. サービス起動: `php start.php start -d`、`php start.php status` で `redis-queue` プロセスが見えるはずです。
2. 投递（元のデバッグルート `/debug/queue-smoke` はセキュリティ修正で削除済み。プロデューサー経由で投递）:
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. 消費結果を確認:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— スモークタスクが書き込む操作ログ；
   - `redis-cli GET erp:queue:smoke:count` —— 消費回数カウンター；
   - `redis-cli LLEN erp:queue:default` —— キューの滞留長（0 に戻るはず）。

## 2. 公式 webman/redis-queue への切替（Redis ドライバー、推奨）

公式ドキュメント: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

インストール後に設定 `config/plugin/webman/redis-queue/redis.php` が自動生成され、内容は概ね次の通りです：

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // 密码，可选参数
            'db' => 0,               // 数据库
            'max_attempts' => 5,     // 消费失败后重试次数
            'retry_seconds' => 5,    // 重试间隔（秒），第 N 次重试间隔 = N * retry_seconds
        ],
    ],
];
```

`config/process.php` を変更し、`redis-queue` プロセスの handler を公式消費クラスに置き換えます
（タスククラスディレクトリ `app/queue/redis/` と `consume()` メソッドの規約は変更なし）：

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

投递方法の変更：

```php
use Webman\RedisQueue\Client;

// 即時投递
Client::send('default', ['key' => 'value']);

// 遅延投递（10 秒遅延）
Client::sendLater('default', ['key' => 'value'], 10);
```

> 消費タスククラスの書き方は変更なし: `app/queue/redis/Xxx.php` で `public function consume(RedisQueue $queue, $data)` を定義；
> 公式拡張は追加で失敗リトライ、遅延キューの機能を提供します。

## 3. RabbitMQ への切替（公式推奨は STOMP プロトコルでの接続）

webman 公式の RabbitMQ 標準接続方式は **STOMP プロトコル**プラグイン
（`workerman/stomp` クライアント）です。RabbitMQ サーバー側で stomp プラグインの有効化が必要
（デフォルトポート **61613**）。公式ドキュメント: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 RabbitMQ STOMP プラグインの有効化（サーバー側）

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 webman/stomp のインストールと設定

```bash
composer require webman/stomp
```

設定は `config/plugin/webman/stomp/` に自動生成され、接続パラメータを記入します：

```php
// config/plugin/webman/stomp/stomp.php（示意）
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // STOMP 端口（非 5672 AMQP 端口）
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 STOMP 消費プロセスの追加

`config/process.php` に追加（redis-queue と併存可能）：

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

消費タスククラスは `app/queue/stomp/` ディレクトリに置き、`Webman\Stomp\Consumer` インターフェースを実装します：

```php
namespace app\queue\stomp;

use Webman\Stomp\Consumer;

class MyMailSend implements Consumer
{
    public function queueName(): string
    {
        return 'default';
    }

    public function consume($data): void
    {
        // STOMP 组件不做自动序列化，投递时若为数组需自行 json_encode / serialize，
        // 消费时对应反序列化
    }
}
```

### 3.4 メッセージ投递

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 ドライバー選択のまとめ

| ドライバー | インストールパッケージ | 消費プロセス handler | プロデューサー API |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`          |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`        | `Client::send()`          |
| 最小実装  | なし（現在のデフォルト） | `app\process\QueueConsumer`            | `RedisQueue::push()`      |

`config/queue.php` の `default` と `rabbitmq` の 2 セットの接続設定は参考として保持されています。
切替後は対応プラグインが生成した設定ファイルが基準になります。
