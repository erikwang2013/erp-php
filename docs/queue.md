# 队列（Queue）使用与驱动切换指南

> 关联配置：`config/queue.php`（驱动与连接）、`config/process.php`（消费进程）、`app/queue/`（任务类）。
> 关联实现：`app/queue/RedisQueue.php`（生产者工具）、`app/process/QueueConsumer.php`（消费进程）、`app/queue/redis/SmokeTask.php`（冒烟任务）。

## 1. 现状：基于 Redis LIST 的最小实现

项目当前**未安装** `webman/redis-queue` 扩展包（`composer show | grep queue` 仅有传递依赖
`illuminate/queue` / `illuminate/redis`，无 webman 队列包），因此队列端到端采用
**Workerman 原生 Timer + Redis LIST** 的最小实现：

- **存储**：Redis LIST，键为 `erp:queue:{queue}`（默认 `queue` 取 `config/queue.php` 中
  `connections.redis.queue`，即 `default`）。
- **生产**：`RedisQueue::push(ClassName::class, 'consume', $data)` 执行 `LPUSH`。
- **消费**：`config/process.php` 中 `redis-queue` 进程（count=1），`onWorkerStart` 后每
  **0.5 秒**轮询 `LPOP` 排空队列，按消息体 `{class, method, data}` 白名单分发到任务类。
- **失败处理**：单条消息失败不中断消费循环，自动重试（attempts+1，最多 3 次），
  超限进入死信队列 `erp:queue:failed` 并写错误日志。
- **指数退避**：重试不立即执行，而是写入延迟集（zset，键 `erp:queue:{queue}:delay`）延迟入队，
  第 n 次重试延迟 `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` 秒
  （`app/process/QueueConsumer.php` 常量：base=5s、cap=120s，实际 3 次上限下为 5s/10s），
  到期后由消费进程提升回主队列，避免失败消息风暴式重试。
- **消息体约定与官方 `webman/redis-queue` 的作业格式一致**（`class` / `method` / `data`），
  便于将来无痛迁移。

### 冒烟验证（端到端）

1. 启动服务：`php start.php start -d`，`php start.php status` 应能看到 `redis-queue` 进程；
2. 投递（原调试路由 `/debug/queue-smoke` 已随安全修复移除，改用生产者投递）：
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. 观察消费结果：
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— 冒烟任务写入的操作日志；
   - `redis-cli GET erp:queue:smoke:count` —— 消费次数计数器；
   - `redis-cli LLEN erp:queue:default` —— 队列积压长度（应回落为 0）。

## 2. 切换为官方 webman/redis-queue（Redis 驱动，推荐）

官方文档：<https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

安装后自动生成配置 `config/plugin/webman/redis-queue/redis.php`，内容类似：

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

修改 `config/process.php`，将 `redis-queue` 进程的 handler 替换为官方消费类
（任务类目录 `app/queue/redis/` 与 `consume()` 方法约定保持不变）：

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

投递方式改为：

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> 消费任务类写法不变：`app/queue/redis/Xxx.php` 中定义 `public function consume(RedisQueue $queue, $data)`；
> 官方扩展额外提供失败重试、延迟队列能力。

## 3. 切换 RabbitMQ（官方推荐 STOMP 协议接入）

webman 官方对 RabbitMQ 的标准接入方式是通过 **STOMP 协议**插件
（`workerman/stomp` 客户端），需要在 RabbitMQ 服务端开启 stomp 插件
（默认端口 **61613**）。官方文档：<https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 开启 RabbitMQ STOMP 插件（服务端）

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 安装 webman/stomp 并配置

```bash
composer require webman/stomp
```

配置自动生成在 `config/plugin/webman/stomp/` 下，填写连接参数：

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

### 3.3 新增 STOMP 消费进程

在 `config/process.php` 增加（与 redis-queue 并存即可）：

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

消费任务类放 `app/queue/stomp/` 目录，实现 `Webman\Stomp\Consumer` 接口：

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

### 3.4 投递消息

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 驱动选择总结

| 驱动      | 安装包                 | 消费进程 handler                       | 生产者 API                |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`          |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`        | `Client::send()`          |
| 最小实现  | 无（当前默认）          | `app\process\QueueConsumer`            | `RedisQueue::push()`      |

`config/queue.php` 中 `default` 与 `rabbitmq` 两套连接配置保留作为参考；
切换后以对应插件生成的配置文件为准。
