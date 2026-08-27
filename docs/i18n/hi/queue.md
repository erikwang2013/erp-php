# कतार (Queue) उपयोग और ड्राइवर स्विचिंग गाइड

> संबंधित कॉन्फ़िगरेशन: `config/queue.php` (ड्राइवर और कनेक्शन), `config/process.php` (उपभोग प्रक्रिया), `app/queue/` (कार्य वर्ग)।
> संबंधित कार्यान्वयन: `app/queue/RedisQueue.php` (उत्पादक उपकरण), `app/process/QueueConsumer.php` (उपभोग प्रक्रिया), `app/queue/redis/SmokeTask.php` (स्मोक कार्य)।

## 1. वर्तमान स्थिति: Redis LIST पर आधारित न्यूनतम कार्यान्वयन

प्रोजेक्ट में वर्तमान में **नहीं लगा** है `webman/redis-queue` एक्सटेंशन पैकेज (`composer show | grep queue` में केवल पारित निर्भरताएँ
`illuminate/queue` / `illuminate/redis` हैं, कोई webman कतार पैकेज नहीं), इसलिए कतार अंत-से-अंत
**Workerman नेटिव Timer + Redis LIST** के न्यूनतम कार्यान्वयन से चलती है:

- **भंडारण**: Redis LIST, कुंजी `erp:queue:{queue}` (डिफ़ॉल्ट `queue` `config/queue.php` में
  `connections.redis.queue` से लेता है, अर्थात `default`)।
- **उत्पादन**: `RedisQueue::push(ClassName::class, 'consume', $data)` से `LPUSH` निष्पादित होता है।
- **उपभोग**: `config/process.php` में `redis-queue` प्रक्रिया (count=1), `onWorkerStart` के बाद हर
  **0.5 सेकंड** में `LPOP` से कतार खाली करने का पोल करती है, संदेश निकाय `{class, method, data}` के अनुसार सफेद सूची से कार्य वर्ग में वितरित करती है।
- **विफलता हैंडलिंग**: एक संदेश की विफलता उपभोग लूप को बाधित नहीं करती, स्वतः रीट्राई होती है (attempts+1, अधिकतम 3 बार),
  सीमा पार होने पर डेड-लेटर कतार `erp:queue:failed` में जाता है और त्रुटि लॉग लिखा जाता है।
- **एक्सपोनेंशियल बैकऑफ़**: रीट्राई तुरंत नहीं होती, बल्कि विलंब सेट (zset, कुंजी `erp:queue:{queue}:delay`) में लिखकर विलंबित प्रवेश होता है,
  nवीं रीट्राई का विलंब `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` सेकंड होता है
  (`app/process/QueueConsumer.php` स्थिरांक: base=5s, cap=120s, वास्तविक 3 बार की सीमा में 5s/10s),
  अवधि समाप्त होने पर उपभोग प्रक्रिया इसे वापस मुख्य कतार में उठा लेती है, विफल संदेशों की तूफानी रीट्राई से बचती है।
- **संदेश निकाय परंपरा आधिकारिक `webman/redis-queue` की जॉब प्रारूप के अनुरूप है** (`class` / `method` / `data`),
  जिससे भविष्य में दर्द रहित माइग्रेशन संभव है।

### स्मोक सत्यापन (अंत-से-अंत)

1. सेवा शुरू करें: `php start.php start -d`, `php start.php status` में `redis-queue` प्रक्रिया दिखनी चाहिए;
2. वितरण (मूल डीबग रूट `/debug/queue-smoke` सुरक्षा फिक्स के साथ हटा दिया गया, अब उत्पादक से वितरण करें):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. उपभोग परिणाम देखें:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— स्मोक कार्य द्वारा लिखा गया ऑपरेशन लॉग;
   - `redis-cli GET erp:queue:smoke:count` —— उपभोग गणना काउंटर;
   - `redis-cli LLEN erp:queue:default` —— कतार बैकलॉग लंबाई (0 पर लौटनी चाहिए)।

## 2. आधिकारिक webman/redis-queue पर स्विच करें (Redis ड्राइवर, अनुशंसित)

आधिकारिक दस्तावेज़: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

इंस्टॉलेशन के बाद स्वतः कॉन्फ़िगरेशन `config/plugin/webman/redis-queue/redis.php` उत्पन्न होता है, सामग्री कुछ इस प्रकार:

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

`config/process.php` संशोधित करें, `redis-queue` प्रक्रिया के handler को आधिकारिक उपभोग वर्ग से बदलें
(कार्य वर्ग निर्देशिका `app/queue/redis/` और `consume()` विधि परंपरा अपरिवर्तित रहती है):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

वितरण विधि बदल जाती है:

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> उपभोग कार्य वर्ग का लेखन अपरिवर्तित: `app/queue/redis/Xxx.php` में `public function consume(RedisQueue $queue, $data)` परिभाषित करें;
> आधिकारिक एक्सटेंशन अतिरिक्त रूप से विफलता रीट्राई, विलंब कतार क्षमताएँ प्रदान करता है।

## 3. RabbitMQ पर स्विच करें (आधिकारिक रूप से अनुशंसित STOMP प्रोटोकॉल एकीकरण)

webman का RabbitMQ के लिए मानक एकीकरण **STOMP प्रोटोकॉल** प्लगइन से होता है
(`workerman/stomp` क्लाइंट), RabbitMQ सर्वर पर stomp प्लगइन सक्षम करना आवश्यक है
(डिफ़ॉल्ट पोर्ट **61613**)। आधिकारिक दस्तावेज़: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 RabbitMQ STOMP प्लगइन सक्षम करें (सर्वर)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 webman/stomp स्थापित और कॉन्फ़िगर करें

```bash
composer require webman/stomp
```

कॉन्फ़िगरेशन स्वतः `config/plugin/webman/stomp/` में उत्पन्न होता है, कनेक्शन पैरामीटर भरें:

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

### 3.3 नई STOMP उपभोग प्रक्रिया जोड़ें

`config/process.php` में जोड़ें (redis-queue के साथ सह-अस्तित्व संभव):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

उपभोग कार्य वर्ग `app/queue/stomp/` निर्देशिका में रखें, `Webman\Stomp\Consumer` इंटरफ़ेस लागू करें:

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

### 3.4 संदेश वितरण

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 ड्राइवर चयन सारांश

| ड्राइवर      | इंस्टॉलेशन पैकेज                 | उपभोग प्रक्रिया handler                       | उत्पादक API                |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`          |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`        | `Client::send()`          |
| न्यूनतम कार्यान्वयन  | कोई नहीं (वर्तमान डिफ़ॉल्ट)          | `app\process\QueueConsumer`            | `RedisQueue::push()`      |

`config/queue.php` में `default` और `rabbitmq` दो कनेक्शन कॉन्फ़िगरेशन संदर्भ के रूप में बनाए रखे गए हैं;
स्विच के बाद संबंधित प्लगइन द्वारा उत्पन्न कॉन्फ़िगरेशन फ़ाइल मान्य होती है।
