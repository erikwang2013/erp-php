# Queue Usage and Driver Switching Guide

> Related config: `config/queue.php` (drivers and connections), `config/process.php` (consumer processes), `app/queue/` (task classes).
> Related implementation: `app/queue/RedisQueue.php` (producer utility), `app/process/QueueConsumer.php` (consumer process), `app/queue/redis/SmokeTask.php` (smoke test task).

## 1. Current State: Minimal Implementation Based on Redis LIST

The project currently does **not** have the `webman/redis-queue` extension package installed (`composer show | grep queue` shows only the transitive dependencies
`illuminate/queue` / `illuminate/redis`, no webman queue package), so the queue end-to-end uses the
**Workerman native Timer + Redis LIST** minimal implementation:

- **Storage**: Redis LIST, key `erp:queue:{queue}` (by default `queue` takes
  `connections.redis.queue` from `config/queue.php`, i.e. `default`).
- **Produce**: `RedisQueue::push(ClassName::class, 'consume', $data)` executes `LPUSH`.
- **Consume**: the `redis-queue` process in `config/process.php` (count=1) polls `LPOP` every **0.5 seconds**
  after `onWorkerStart` to drain the queue, dispatching to task classes by the message body `{class, method, data}` whitelist.
- **Failure handling**: a single message failure does not interrupt the consume loop; automatic retry (attempts+1, max 3 times),
  beyond the limit it goes to the dead-letter queue `erp:queue:failed` and writes an error log.
- **Exponential backoff**: retries are not executed immediately but written to a delayed set (zset, key `erp:queue:{queue}:delay`) for delayed enqueue;
  the n-th retry is delayed by `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` seconds
  (`app/process/QueueConsumer.php` constants: base=5s, cap=120s; with the actual 3-retry cap this is 5s/10s),
  after expiry the consumer process promotes it back to the main queue, avoiding a storm of retries for failed messages.
- **Message body convention is identical to the official `webman/redis-queue` job format** (`class` / `method` / `data`),
  facilitating painless future migration.

### Smoke Verification (end-to-end)

1. Start the service: `php start.php start -d`, `php start.php status` should show the `redis-queue` process;
2. Dispatch (the original debug route `/debug/queue-smoke` was removed with the security fixes; use producer dispatch instead):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Observe the consumption results:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— operation log written by the smoke task;
   - `redis-cli GET erp:queue:smoke:count` —— consumption counter;
   - `redis-cli LLEN erp:queue:default` —— queue backlog length (should fall back to 0).

## 2. Switching to the Official webman/redis-queue (Redis driver, recommended)

Official docs: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

After installation, the config `config/plugin/webman/redis-queue/redis.php` is auto-generated, similar to:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // password, optional
            'db' => 0,               // database
            'max_attempts' => 5,     // retry count after consumption failure
            'retry_seconds' => 5,    // retry interval (seconds); the N-th retry interval = N * retry_seconds
        ],
    ],
];
```

Modify `config/process.php` to replace the `redis-queue` process handler with the official consumer class
(task class directory `app/queue/redis/` and the `consume()` method convention remain unchanged):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

Change the dispatch method to:

```php
use Webman\RedisQueue\Client;

// immediate dispatch
Client::send('default', ['key' => 'value']);

// delayed dispatch (delayed 10 seconds)
Client::sendLater('default', ['key' => 'value'], 10);
```

> The consumer task class style is unchanged: define `public function consume(RedisQueue $queue, $data)` in `app/queue/redis/Xxx.php`;
> the official extension additionally provides failure retry and delayed queue capabilities.

## 3. Switching to RabbitMQ (officially recommended STOMP protocol integration)

webman's standard way of integrating RabbitMQ is through the **STOMP protocol** plugin
(`workerman/stomp` client), which requires enabling the stomp plugin on the RabbitMQ server
(default port **61613**). Official docs: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Enable the RabbitMQ STOMP Plugin (server side)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Install webman/stomp and Configure

```bash
composer require webman/stomp
```

Config is auto-generated under `config/plugin/webman/stomp/`; fill in the connection parameters:

```php
// config/plugin/webman/stomp/stomp.php (illustrative)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // STOMP port (not the 5672 AMQP port)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 Add a STOMP Consumer Process

Add to `config/process.php` (it can coexist with redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Put consumer task classes in the `app/queue/stomp/` directory, implementing the `Webman\Stomp\Consumer` interface:

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
        // The STOMP component does not auto-serialize; if the dispatched data is an array,
        // json_encode / serialize it yourself, and deserialize accordingly when consuming
    }
}
```

### 3.4 Dispatching Messages

```php
use Webman\Stomp\Client;

// data (serialize yourself when passing arrays)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Driver Selection Summary

| Driver     | Package                | Consumer Process Handler                | Producer API               |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`          |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`        | `Client::send()`          |
| Minimal implementation | none (current default) | `app\process\QueueConsumer`            | `RedisQueue::push()`      |

The `default` and `rabbitmq` connection configs in `config/queue.php` are kept for reference;
after switching, the config files generated by the corresponding plugin take precedence.
