# Очереди (Queue) — руководство по использованию и смене драйвера

> Связанная конфигурация: `config/queue.php` (драйвер и подключения), `config/process.php` (процессы-потребители), `app/queue/` (классы задач).
> Связанная реализация: `app/queue/RedisQueue.php` (инструмент производителя), `app/process/QueueConsumer.php` (процесс-потребитель), `app/queue/redis/SmokeTask.php` (дымовая задача).

## 1. Текущее состояние: минимальная реализация на Redis LIST

В проекте **не установлен** пакет расширения `webman/redis-queue` (`composer show | grep queue` показывает только транзитивные зависимости
`illuminate/queue` / `illuminate/redis`, пакета очередей webman нет), поэтому очередь end-to-end реализована минимально на
**нативном Timer Workerman + Redis LIST**:

- **Хранение**: Redis LIST, ключ `erp:queue:{queue}` (по умолчанию `queue` берётся из
  `connections.redis.queue` в `config/queue.php`, то есть `default`).
- **Производство**: `RedisQueue::push(ClassName::class, 'consume', $data)` выполняет `LPUSH`.
- **Потребление**: процесс `redis-queue` в `config/process.php` (count=1), после `onWorkerStart` каждые
  **0.5 секунды** опрашивает `LPOP` для разбора очереди, по белому списку сообщений `{class, method, data}` диспетчеризует в классы задач.
- **Обработка ошибок**: сбой одной записи не прерывает цикл потребления, автоматический повтор (attempts+1, максимум 3 раза),
  после исчерпания — сообщение уходит в очередь мёртвых `erp:queue:failed` и пишется журнал ошибок.
- **Экспоненциальная задержка**: повтор выполняется не сразу, а через отложенный набор (zset, ключ `erp:queue:{queue}:delay`),
  задержка n-го повтора `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` секунд
  (константы `app/process/QueueConsumer.php`: base=5s, cap=120s, при фактическом лимите в 3 раза — 5s/10s),
  по истечении потребительский процесс возвращает сообщение в основную очередь, избегая штормовых повторов при сбоях.
- **Формат сообщения совпадает с форматом задач официального `webman/redis-queue`** (`class` / `method` / `data`),
  что упрощает будущий безболезненный переход.

### Дымовая проверка (end-to-end)

1. Запустите сервис: `php start.php start -d`, в `php start.php status` должен быть виден процесс `redis-queue`;
2. Поставьте сообщение (отладочный маршрут `/debug/queue-smoke` удалён вместе с правками безопасности, используйте производителя):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Проверьте результат потребления:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — журнал операций, который пишет дымовая задача;
   - `redis-cli GET erp:queue:smoke:count` — счётчик количества потреблений;
   - `redis-cli LLEN erp:queue:default` — длина скопившейся очереди (должна вернуться к 0).

## 2. Переход на официальный webman/redis-queue (драйвер Redis, рекомендуется)

Официальная документация: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

После установки автоматически генерируется конфигурация `config/plugin/webman/redis-queue/redis.php`, примерно такого содержания:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // пароль, необязательный параметр
            'db' => 0,               // база данных
            'max_attempts' => 5,     // число повторов после сбоя потребления
            'retry_seconds' => 5,    // интервал повтора (секунды), интервал N-го повтора = N * retry_seconds
        ],
    ],
];
```

Измените `config/process.php`, заменив handler процесса `redis-queue` на официальный класс-потребитель
(каталог классов задач `app/queue/redis/` и соглашение о методе `consume()` остаются прежними):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

Способ постановки меняется на:

```php
use Webman\RedisQueue\Client;

// немедленная постановка
Client::send('default', ['key' => 'value']);

// отложенная постановка (задержка 10 секунд)
Client::sendLater('default', ['key' => 'value'], 10);
```

> Классы задач-потребителей пишутся без изменений: в `app/queue/redis/Xxx.php` определяется `public function consume(RedisQueue $queue, $data)`;
> официальное расширение дополнительно предоставляет повторы при сбоях и отложенные очереди.

## 3. Переход на RabbitMQ (официально рекомендуется подключение по протоколу STOMP)

Стандартный способ подключения RabbitMQ в webman — через плагин протокола **STOMP**
(клиент `workerman/stomp`), на сервере RabbitMQ необходимо включить плагин stomp
(порт по умолчанию **61613**). Официальная документация: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Включение плагина STOMP в RabbitMQ (на стороне сервера)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Установка webman/stomp и настройка

```bash
composer require webman/stomp
```

Конфигурация автоматически создаётся в `config/plugin/webman/stomp/`, заполните параметры подключения:

```php
// config/plugin/webman/stomp/stomp.php (пример)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // порт STOMP (не порт 5672 AMQP)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 Новый процесс-потребитель STOMP

Добавьте в `config/process.php` (может сосуществовать с redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Классы задач-потребителей размещаются в каталоге `app/queue/stomp/` и реализуют интерфейс `Webman\Stomp\Consumer`:

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
        // STOMP-компонент не делает автоматической сериализации, при постановке массива
        // сериализуйте его самостоятельно через json_encode / serialize,
        // при потреблении — выполните соответствующую десериализацию
    }
}
```

### 3.4 Постановка сообщений

```php
use Webman\Stomp\Client;

// данные (при передаче массива сериализуйте самостоятельно)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Сводка выбора драйвера

| Драйвер  | Пакет установки        | handler процесса-потребителя             | API производителя     |
|-----------|------------------------|------------------------------------------|-----------------------|
| Redis     | `webman/redis-queue`   | `Webman\RedisQueue\Process\Consumer`     | `Client::send()`      |
| RabbitMQ  | `webman/stomp`         | `Webman\Stomp\Process\Consumer`          | `Client::send()`      |
| Минимальный | нет (текущий по умолчанию) | `app\process\QueueConsumer`          | `RedisQueue::push()`  |

В `config/queue.php` оба набора конфигураций подключений `default` и `rabbitmq` сохранены для справки;
после перехода ориентируйтесь на файлы конфигурации, созданные соответствующим плагином.
