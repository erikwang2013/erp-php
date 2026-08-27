# 큐(Queue) 사용 및 드라이버 전환 가이드

> 관련 설정: `config/queue.php`(드라이버와 연결), `config/process.php`(소비 프로세스), `app/queue/`(태스크 클래스).
> 관련 구현: `app/queue/RedisQueue.php`(생산자 도구), `app/process/QueueConsumer.php`(소비 프로세스), `app/queue/redis/SmokeTask.php`(스모크 태스크).

## 1. 현황: Redis LIST 기반 최소 구현

현재 프로젝트에는 `webman/redis-queue` 확장 패키지가 **설치되어 있지 않습니다**(`composer show | grep queue`는 전이 의존성
`illuminate/queue` / `illuminate/redis`만 표시, webman 큐 패키지 없음). 따라서 큐는 엔드투엔드로
**Workerman 네이티브 Timer + Redis LIST** 최소 구현을 사용합니다:

- **저장**: Redis LIST, 키 `erp:queue:{queue}`(기본 `queue`는 `config/queue.php`의
  `connections.redis.queue`에서 가져오며, 즉 `default`).
- **생산**: `RedisQueue::push(ClassName::class, 'consume', $data)`가 `LPUSH`를 실행.
- **소비**: `config/process.php`의 `redis-queue` 프로세스(count=1), `onWorkerStart` 후
  **0.5초마다** `LPOP`으로 큐를 비우며, 메시지 본문 `{class, method, data}` 화이트리스트에 따라 태스크 클래스로 분배.
- **실패 처리**: 단일 메시지 실패는 소비 루프를 중단시키지 않고 자동 재시도(attempts+1, 최대 3회),
  한도 초과 시 데드 레터 큐 `erp:queue:failed`로 이동하고 오류 로그를 기록.
- **지수 백오프**: 재시도는 즉시 실행하지 않고 지연 집합(zset, 키 `erp:queue:{queue}:delay`)에 기록해 지연 후 재입큐하며,
  n번째 재시도 지연은 `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)`초
  (`app/process/QueueConsumer.php` 상수: base=5s, cap=120s, 실제 3회 한도에서는 5s/10s),
  만료 후 소비 프로세스가 메인 큐로 다시 승격시켜 실패 메시지 폭풍 재시도를 방지.
- **메시지 본문 규약은 공식 `webman/redis-queue`의 작업 형식과 동일**(`class` / `method` / `data`),
  추후 무통증 마이그레이션이 가능.

### 스모크 검증(엔드투엔드)

1. 서비스 시작: `php start.php start -d`, `php start.php status`에 `redis-queue` 프로세스가 보여야 합니다;
2. 투입(기존 디버그 라우트 `/debug/queue-smoke`는 보안 수정과 함께 제거되어, 생산자 투입으로 변경):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. 소비 결과 확인:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — 스모크 태스크가 기록한 작업 로그;
   - `redis-cli GET erp:queue:smoke:count` — 소비 횟수 카운터;
   - `redis-cli LLEN erp:queue:default` — 큐 적체 길이(0으로 돌아가야 함).

## 2. 공식 webman/redis-queue로 전환(Redis 드라이버, 권장)

공식 문서: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

설치 후 `config/plugin/webman/redis-queue/redis.php` 설정이 자동 생성되며, 내용은 대략:

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

`config/process.php`를 수정해 `redis-queue` 프로세스의 handler를 공식 소비 클래스로 교체
(태스크 클래스 디렉토리 `app/queue/redis/`와 `consume()` 메서드 규약은 그대로 유지):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

투입 방식 변경:

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> 소비 태스크 클래스 작성법은 동일: `app/queue/redis/Xxx.php`에 `public function consume(RedisQueue $queue, $data)` 정의;
> 공식 확장이 실패 재시도, 지연 큐 기능을 추가로 제공.

## 3. RabbitMQ로 전환(공식 권장 STOMP 프로토콜 연동)

webman 공식의 RabbitMQ 표준 연동 방식은 **STOMP 프로토콜** 플러그인
(`workerman/stomp` 클라이언트)을 통한 것이며, RabbitMQ 서버에서 stomp 플러그인을 켜야 합니다
(기본 포트 **61613**). 공식 문서: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 RabbitMQ STOMP 플러그인 켜기(서버 측)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 webman/stomp 설치 및 설정

```bash
composer require webman/stomp
```

설정이 `config/plugin/webman/stomp/` 아래에 자동 생성되며, 연결 파라미터를 입력합니다:

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

### 3.3 STOMP 소비 프로세스 추가

`config/process.php`에 추가(redis-queue와 병존 가능):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

소비 태스크 클래스는 `app/queue/stomp/` 디렉토리에 두고 `Webman\Stomp\Consumer` 인터페이스를 구현합니다:

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

### 3.4 메시지 투입

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 드라이버 선택 요약

| 드라이버  | 설치 패키지              | 소비 프로세스 handler                    | 생산자 API             |
|-----------|--------------------------|------------------------------------------|------------------------|
| Redis     | `webman/redis-queue`     | `Webman\RedisQueue\Process\Consumer`     | `Client::send()`       |
| RabbitMQ  | `webman/stomp`           | `Webman\Stomp\Process\Consumer`          | `Client::send()`       |
| 최소 구현 | 없음(현재 기본)          | `app\process\QueueConsumer`              | `RedisQueue::push()`   |

`config/queue.php`의 `default`와 `rabbitmq` 두 세트 연결 설정은 참고용으로 보존합니다;
전환 후에는 해당 플러그인이 생성한 설정 파일을 기준으로 합니다.
