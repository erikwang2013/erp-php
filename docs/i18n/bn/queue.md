# কিউ (Queue) ব্যবহার ও ড্রাইভার সুইচিং গাইড

> সম্পর্কিত কনফিগারেশন: `config/queue.php` (ড্রাইভার ও কানেকশন), `config/process.php` (কনজিউম প্রসেস), `app/queue/` (টাস্ক ক্লাস)।
> সম্পর্কিত ইমপ্লিমেন্টেশন: `app/queue/RedisQueue.php` (প্রোডিউসার টুল), `app/process/QueueConsumer.php` (কনজিউম প্রসেস), `app/queue/redis/SmokeTask.php` (স্মোক টাস্ক)।

## 1. বর্তমান অবস্থা: Redis LIST ভিত্তিক ন্যূনতম ইমপ্লিমেন্টেশন

প্রজেক্টে বর্তমানে **ইনস্টল করা নেই** `webman/redis-queue` এক্সটেনশন প্যাকেজ (`composer show | grep queue` এ শুধুমাত্র ট্রানজিটিভ ডিপেন্ডেন্সি
`illuminate/queue` / `illuminate/redis` আছে, webman কিউ প্যাকেজ নেই), তাই কিউ এন্ড-টু-এন্ড
**Workerman নেটিভ Timer + Redis LIST** ভিত্তিক ন্যূনতম ইমপ্লিমেন্টেশন ব্যবহার করে:

- **স্টোরেজ**: Redis LIST, কী `erp:queue:{queue}` (ডিফল্ট `queue` হয় `config/queue.php` এর
  `connections.redis.queue` থেকে, অর্থাৎ `default`)।
- **প্রোডিউস**: `RedisQueue::push(ClassName::class, 'consume', $data)` `LPUSH` এক্সিকিউট করে।
- **কনজিউম**: `config/process.php` এর `redis-queue` প্রসেস (count=1), `onWorkerStart` এর পর প্রতি
  **০.৫ সেকেন্ড** `LPOP` দিয়ে কিউ খালি করা হয়, মেসেজ বডি `{class, method, data}` হোয়াইটলিস্ট অনুযায়ী টাস্ক ক্লাসে ডিস্ট্রিবিউট হয়।
- **ফেইল্যুর হ্যান্ডলিং**: একটি মেসেজ ফেইল হলে কনজিউম লুপ বাধাগ্রস্ত হয় না, স্বয়ংক্রিয় রিট্রাই (attempts+1, সর্বোচ্চ ৩ বার),
  সীমা অতিক্রম করলে ডেড-লেটার কিউ `erp:queue:failed`-এ যায় এবং এরর লগ লেখা হয়।
- **এক্সপোনেনশিয়াল ব্যাকঅফ**: রিট্রাই অবিলম্বে হয় না, বরং ডেলেড সেট (zset, কী `erp:queue:{queue}:delay`) এ লিখে বিলম্বিত এনকিউ হয়,
  n-তম রিট্রাই বিলম্ব `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` সেকেন্ড
  (`app/process/QueueConsumer.php` কনস্ট্যান্ট: base=5s, cap=120s, বাস্তবে ৩ বার সীমার অধীনে 5s/10s),
  মেয়াদ শেষ হলে কনজিউম প্রসেস মেইন কিউতে ফেরত দেয়, ফেইলড মেসেজের স্টর্ম-স্টাইল রিট্রাই এড়ানো যায়।
- **মেসেজ বডি কনভেনশন অফিসিয়াল `webman/redis-queue` এর জব ফরম্যাটের সাথে সামঞ্জস্যপূর্ণ** (`class` / `method` / `data`),
  ভবিষ্যতে ব্যথাহীন মাইগ্রেশনের জন্য সুবিধাজনক।

### স্মোক ভেরিফিকেশন (এন্ড-টু-এন্ড)

1. সার্ভিস চালু করুন: `php start.php start -d`, `php start.php status` এ `redis-queue` প্রসেস দেখা যাবে;
2. ডেলিভারি (পুরনো ডিবাগ রাউট `/debug/queue-smoke` নিরাপত্তা ফিক্সের সাথে সরিয়ে ফেলা হয়েছে, প্রোডিউসার দিয়ে ডেলিভারি করুন):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. কনজিউম ফলাফল পর্যবেক্ষণ করুন:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— স্মোক টাস্কের লেখা অপারেশন লগ;
   - `redis-cli GET erp:queue:smoke:count` —— কনজিউম কাউন্ট কাউন্টার;
   - `redis-cli LLEN erp:queue:default` —— কিউ ব্যাকলগ দৈর্ঘ্য (০-এ ফিরে আসা উচিত)।

## 2. অফিসিয়াল webman/redis-queue-তে সুইচ (Redis ড্রাইভার, প্রস্তাবিত)

অফিসিয়াল ডকুমেন্টেশন: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

ইনস্টলের পর স্বয়ংক্রিয় কনফিগারেশন তৈরি হয় `config/plugin/webman/redis-queue/redis.php`, বিষয়বস্তু প্রায় এরকম:

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

`config/process.php` পরিবর্তন করে `redis-queue` প্রসেসের handler অফিসিয়াল কনজিউম ক্লাস দিয়ে প্রতিস্থাপন করুন
(টাস্ক ক্লাস ডিরেক্টরি `app/queue/redis/` ও `consume()` মেথড কনভেনশন অপরিবর্তিত থাকে):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

ডেলিভারি পদ্ধতি এভাবে পরিবর্তন হয়:

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> কনজিউম টাস্ক ক্লাসের লেখার ধরন অপরিবর্তিত: `app/queue/redis/Xxx.php` এ `public function consume(RedisQueue $queue, $data)` ডিফাইন করুন;
> অফিসিয়াল এক্সটেনশন অতিরিক্ত ফেইল্যুর রিট্রাই, ডেলেড কিউ ক্ষমতা প্রদান করে।

## 3. RabbitMQ-তে সুইচ (অফিসিয়াল প্রস্তাবিত STOMP প্রোটোকল সংযোগ)

webman-এর অফিসিয়াল RabbitMQ স্ট্যান্ডার্ড সংযোগ পদ্ধতি হল **STOMP প্রোটোকল** প্লাগইন
(`workerman/stomp` ক্লায়েন্ট), RabbitMQ সার্ভারে stomp প্লাগইন চালু করতে হবে
(ডিফল্ট পোর্ট **61613**)। অফিসিয়াল ডকুমেন্টেশন: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 RabbitMQ STOMP প্লাগইন চালু করুন (সার্ভার সাইড)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 webman/stomp ইনস্টল ও কনফিগার করুন

```bash
composer require webman/stomp
```

কনফিগারেশন স্বয়ংক্রিয়ভাবে `config/plugin/webman/stomp/` এর অধীনে তৈরি হয়, কানেকশন প্যারামিটার পূরণ করুন:

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

### 3.3 নতুন STOMP কনজিউম প্রসেস যোগ করুন

`config/process.php` এ যোগ করুন (redis-queue এর সাথে সহাবস্থান করা যায়):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

কনজিউম টাস্ক ক্লাস `app/queue/stomp/` ডিরেক্টরিতে রাখুন, `Webman\Stomp\Consumer` ইন্টারফেস ইমপ্লিমেন্ট করুন:

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

### 3.4 মেসেজ ডেলিভারি

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 ড্রাইভার নির্বাচন সারসংক্ষেপ

| ড্রাইভার | ইনস্টল প্যাকেজ | কনজিউম প্রসেস handler | প্রোডিউসার API |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis | `webman/redis-queue` | `Webman\RedisQueue\Process\Consumer` | `Client::send()` |
| RabbitMQ | `webman/stomp` | `Webman\Stomp\Process\Consumer` | `Client::send()` |
| ন্যূনতম ইমপ্লিমেন্টেশন | নেই (বর্তমান ডিফল্ট) | `app\process\QueueConsumer` | `RedisQueue::push()` |

`config/queue.php` এর `default` ও `rabbitmq` দুটি কানেকশন কনফিগারেশন রেফারেন্স হিসেবে সংরক্ষিত;
সুইচ করার পর সংশ্লিষ্ট প্লাগইনের তৈরি কনফিগারেশন ফাইল অনুযায়ী চলুন।
