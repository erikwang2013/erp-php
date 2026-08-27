# دليل استخدام قائمة الانتظار (Queue) وتبديل المحرّك

> التكوين المرتبط: `config/queue.php` (المحرّك والاتصال)، `config/process.php` (عملية الاستهلاك)، `app/queue/` (فئات المهام).
> التنفيذ المرتبط: `app/queue/RedisQueue.php` (أداة الإنتاج)، `app/process/QueueConsumer.php` (عملية الاستهلاك)، `app/queue/redis/SmokeTask.php` (مهمة اختبار الدخان).

## 1. الوضع الحالي: تنفيذ أدنى يعتمد على Redis LIST

المشروع **لا يثبّت** حاليًا حزمة `webman/redis-queue` (`composer show | grep queue` يُظهر فقط الاعتماديات العابرة
`illuminate/queue` / `illuminate/redis`، دون حزمة قائمة انتظار webman)، لذا يعتمد مسار قائمة الانتظار من البداية للنهاية على
**Timer الأصلي لـ Workerman + Redis LIST** كتنفيذ أدنى:

- **التخزين**: Redis LIST، المفتاح `erp:queue:{queue}` (الافتراضي `queue` يأخذ من `connections.redis.queue` في
  `config/queue.php`، أي `default`).
- **الإنتاج**: `RedisQueue::push(ClassName::class, 'consume', $data)` ينفّذ `LPUSH`.
- **الاستهلاك**: عملية `redis-queue` في `config/process.php` (count=1)، بعد `onWorkerStart` تُستقصى
  **كل 0.5 ثانية** عبر `LPOP` لتفريغ قائمة الانتظار، وتُوزَّع الرسائل حسب جسم الرسالة `{class, method, data}` إلى فئات المهام عبر قائمة بيضاء.
- **معالجة الفشل**: فشل رسالة واحدة لا يقطع حلقة الاستهلاك، مع إعادة محاولة تلقائية (attempts+1، بحد أقصى 3 مرات)،
  وعند تجاوز الحد تدخل إلى قائمة الانتظار الميتة `erp:queue:failed` مع كتابة سجل خطأ.
- **التراجع الأسي**: إعادة المحاولة لا تنفذ فورًا، بل تُكتب إلى مجموعة التأخير (zset، المفتاح `erp:queue:{queue}:delay`) لإعادة الدخول المؤجلة،
  وتأخير المحاولة رقم n هو `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` ثانية
  (ثوابت `app/process/QueueConsumer.php`: base=5s، cap=120s، وبحد أقصى 3 محاولات تكون 5s/10s)،
  وبعد انتهاء المدة ترفعها عملية الاستهلاك إلى قائمة الانتظار الرئيسية، لتجنب هجمات إعادة المحاولة المتتالية للرسائل الفاشلة.
- **اتفاقية جسم الرسالة مطابقة لتنسيق مهام `webman/redis-queue` الرسمي** (`class` / `method` / `data`)،
  لتسهيل الهجرة دون ألم في المستقبل.

### التحقق بالدخان (من النهاية للنهاية)

1. تشغيل الخدمة: `php start.php start -d`، ويجب أن تُظهر `php start.php status` عملية `redis-queue`؛
2. الإرسال (المسار الأصلي للتصحيح `/debug/queue-smoke` أُزيل مع إصلاحات الأمان، استخدم إنتاج المنتج بدلًا منه):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. ملاحظة نتيجة الاستهلاك:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — سجل العمليات الذي تكتبه مهمة الدخان؛
   - `redis-cli GET erp:queue:smoke:count` — عدّاد مرات الاستهلاك؛
   - `redis-cli LLEN erp:queue:default` — طول تراكم قائمة الانتظار (يجب أن يعود إلى 0).

## 2. التبديل إلى webman/redis-queue الرسمي (محرك Redis، موصى به)

الوثائق الرسمية: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

بعد التثبيت يُنشأ تلقائيًا التكوين `config/plugin/webman/redis-queue/redis.php`، بمحتوى مشابه:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // كلمة المرور، معامل اختياري
            'db' => 0,               // قاعدة البيانات
            'max_attempts' => 5,     // عدد محاولات إعادة المحاولة بعد فشل الاستهلاك
            'retry_seconds' => 5,    // الفاصل الزمني لإعادة المحاولة (ثانية)، فاصل المحاولة رقم N = N * retry_seconds
        ],
    ],
];
```

عدّل `config/process.php` واستبدل handler لعملية `redis-queue` بفئة الاستهلاك الرسمية
(دليل فئات المهام `app/queue/redis/` واتفاقية طريقة `consume()` تظلان دون تغيير):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

يصبح أسلوب الإرسال:

```php
use Webman\RedisQueue\Client;

// إرسال فوري
Client::send('default', ['key' => 'value']);

// إرسال مؤجل (بتأخير 10 ثوانٍ)
Client::sendLater('default', ['key' => 'value'], 10);
```

> أسلوب كتابة فئات مهام الاستهلاك لا يتغير: عرّف `public function consume(RedisQueue $queue, $data)` في `app/queue/redis/Xxx.php`؛
> الإضافة الرسمية توفّر إضافيًا إعادة المحاولة عند الفشل وقدرة قائمة الانتظار المؤجلة.

## 3. التبديل إلى RabbitMQ (الاتصال عبر بروتوكول STOMP الرسمي الموصى به)

الطريقة القياسية لربط webman الرسمي مع RabbitMQ هي عبر إضافة **بروتوكول STOMP**
(عميل `workerman/stomp`)، وتتطلب تفعيل إضافة stomp في خادم RabbitMQ
(المنفذ الافتراضي **61613**). الوثائق الرسمية: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 تفعيل إضافة RabbitMQ STOMP (جانب الخادم)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 تثبيت webman/stomp وتكوينه

```bash
composer require webman/stomp
```

يُولَّد التكوين تلقائيًا تحت `config/plugin/webman/stomp/`، املأ معاملات الاتصال:

```php
// config/plugin/webman/stomp/stomp.php (مثال توضيحي)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // منفذ STOMP (وليس منفذ AMQP 5672)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 إضافة عملية استهلاك STOMP جديدة

أضف في `config/process.php` (يمكن أن تتعايش مع redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

ضع فئات مهام الاستهلاك في دليل `app/queue/stomp/`، ونفّذ واجهة `Webman\Stomp\Consumer`:

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
        // مكوّن STOMP لا يقوم بإجراء التسلسل تلقائيًا، فإذا كان الإرسال مصفوفة يجب عليك json_encode / serialize بنفسك،
        // وعند الاستهلاك قم بإجراء إلغاء التسلسل المقابل
    }
}
```

### 3.4 إرسال الرسائل

```php
use Webman\Stomp\Client;

// البيانات (عند تمرير مصفوفة يلزم تسلسلها بنفسك)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 ملخص اختيار المحرّك

| المحرّك    | حزمة التثبيت           | handler عملية الاستهلاك                        | واجهة الإنتاج                |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`          |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`        | `Client::send()`          |
| التنفيذ الأدنى | بدون (الافتراضي الحالي)   | `app\process\QueueConsumer`            | `RedisQueue::push()`      |

تكوينا الاتصال `default` و `rabbitmq` في `config/queue.php` محفوظان كمرجع؛
بعد التبديل اعتمد على ملف التكوين المُولَّد من الإضافة المقابلة.
