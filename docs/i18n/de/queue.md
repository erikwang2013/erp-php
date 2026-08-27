# Warteschlange (Queue) — Leitfaden zu Nutzung und Treiberwechsel

> Zugehörige Konfiguration: `config/queue.php` (Treiber und Verbindungen), `config/process.php` (Konsumprozesse), `app/queue/` (Task-Klassen).
> Zugehörige Implementierung: `app/queue/RedisQueue.php` (Producer-Werkzeug), `app/process/QueueConsumer.php` (Konsumprozess), `app/queue/redis/SmokeTask.php` (Smoke-Task).

## 1. Aktueller Stand: minimale Implementierung auf Basis von Redis LIST

Das Projekt hat das Erweiterungspaket `webman/redis-queue` **nicht** installiert (`composer show | grep queue` zeigt nur die transitiven Abhängigkeiten
`illuminate/queue` / `illuminate/redis`, kein webman-Warteschlangenpaket), daher ist die Warteschlange Ende-zu-Ende als
**minimale Implementierung aus Workerman-nativem Timer + Redis LIST** umgesetzt:

- **Speicherung**: Redis LIST, Schlüssel `erp:queue:{queue}` (Standard `queue` entstammt `connections.redis.queue` in
  `config/queue.php`, also `default`).
- **Produktion**: `RedisQueue::push(ClassName::class, 'consume', $data)` führt `LPUSH` aus.
- **Konsum**: Prozess `redis-queue` in `config/process.php` (count=1), pollt nach `onWorkerStart` alle
  **0,5 Sekunden** per `LPOP` die Warteschlange leer und verteilt nach Nachrichteninhalt `{class, method, data}` über eine Whitelist an die Task-Klassen.
- **Fehlerbehandlung**: Ein einzelner Nachrichtenfehler unterbricht die Konsumschleife nicht, automatische Wiederholung (attempts+1, maximal 3 Mal),
  bei Überschreitung erfolgt der Wechsel in die Dead-Letter-Warteschlange `erp:queue:failed` mit Fehlerprotokolleintrag.
- **Exponentielles Backoff**: Wiederholungen werden nicht sofort ausgeführt, sondern in eine Verzögerungsmenge geschrieben (zset, Schlüssel `erp:queue:{queue}:delay`) und verzögert eingereiht,
  die n-te Wiederholung verzögert um `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` Sekunden
  (Konstanten in `app/process/QueueConsumer.php`: base=5s, cap=120s, bei der tatsächlichen Obergrenze von 3 Versuchen also 5s/10s),
  nach Ablauf wird der Eintrag vom Konsumprozess zurück in die Hauptwarteschlange befördert, um Sturm-Wiederholungen fehlgeschlagener Nachrichten zu vermeiden.
- **Nachrichtenformat- Konvention entspricht dem Job-Format des offiziellen `webman/redis-queue`** (`class` / `method` / `data`),
  um einen späteren schmerzlosen Wechsel zu ermöglichen.

### Smoke-Verifikation (Ende-zu-Ende)

1. Dienst starten: `php start.php start -d`, in `php start.php status` sollte der Prozess `redis-queue` sichtbar sein;
2. Zustellung (die ursprüngliche Debug-Route `/debug/queue-smoke` wurde mit den Sicherheits-Fixes entfernt, stattdessen per Producer zustellen):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Konsumergebnis beobachten:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — das von der Smoke-Task geschriebene Operationsprotokoll;
   - `redis-cli GET erp:queue:smoke:count` — Zähler der Konsumvorgänge;
   - `redis-cli LLEN erp:queue:default` — Rückstau-Länge der Warteschlange (sollte wieder auf 0 fallen).

## 2. Wechsel zu offiziellem webman/redis-queue (Redis-Treiber, empfohlen)

Offizielle Dokumentation: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

Nach der Installation wird automatisch die Konfiguration `config/plugin/webman/redis-queue/redis.php` erzeugt, in etwa so:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // Passwort, optionaler Parameter
            'db' => 0,               // Datenbank
            'max_attempts' => 5,     // Wiederholungsanzahl nach Konsumfehler
            'retry_seconds' => 5,    // Wiederholungsintervall (Sekunden), Intervall der N-ten Wiederholung = N * retry_seconds
        ],
    ],
];
```

In `config/process.php` den handler des Prozesses `redis-queue` durch die offizielle Konsumklasse ersetzen
(Verzeichnis der Task-Klassen `app/queue/redis/` und die `consume()`-Methoden-Konvention bleiben unverändert):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

Die Zustellung ändert sich zu:

```php
use Webman\RedisQueue\Client;

// Sofort zustellen
Client::send('default', ['key' => 'value']);

// Verzögert zustellen (10 Sekunden Verzögerung)
Client::sendLater('default', ['key' => 'value'], 10);
```

> Die Schreibweise der Konsum-Task-Klassen bleibt unverändert: in `app/queue/redis/Xxx.php` `public function consume(RedisQueue $queue, $data)` definieren;
> das offizielle Erweiterungspaket bietet zusätzlich Fehlerwiederholung und verzögerte Warteschlangen.

## 3. Wechsel zu RabbitMQ (offiziell empfohlener STOMP-Protokoll-Anschluss)

Die standardmäßige Anbindung von webman an RabbitMQ erfolgt über das **STOMP-Protokoll**-Plugin
(`workerman/stomp`-Client); auf dem RabbitMQ-Server muss das stomp-Plugin aktiviert sein
(Standard-Port **61613**). Offizielle Dokumentation: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 RabbitMQ-STOMP-Plugin aktivieren (Server)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 webman/stomp installieren und konfigurieren

```bash
composer require webman/stomp
```

Die Konfiguration wird automatisch unter `config/plugin/webman/stomp/` erzeugt; Verbindungsparameter ausfüllen:

```php
// config/plugin/webman/stomp/stomp.php (Beispiel)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // STOMP-Port (nicht 5672 AMQP-Port)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 Neuen STOMP-Konsumprozess hinzufügen

In `config/process.php` ergänzen (kann parallel zu redis-queue bestehen):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Die Konsum-Task-Klassen kommen in das Verzeichnis `app/queue/stomp/` und implementieren das Interface `Webman\Stomp\Consumer`:

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
        // Die STOMP-Komponente serialisiert nicht automatisch; bei Zustellung von Arrays
        // muss selbst json_encode / serialize verwendet werden, beim Konsum entsprechend deserialisieren
    }
}
```

### 3.4 Nachrichten zustellen

```php
use Webman\Stomp\Client;

// Daten (Arrays müssen bei Übergabe selbst serialisiert werden)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Treiberwahl-Zusammenfassung

| Treiber   | Installationspaket        | Konsumprozess-handler                    | Producer-API              |
|-----------|---------------------------|------------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`      | `Webman\RedisQueue\Process\Consumer`     | `Client::send()`          |
| RabbitMQ  | `webman/stomp`            | `Webman\Stomp\Process\Consumer`          | `Client::send()`          |
| Minimal   | keines (aktueller Standard) | `app\process\QueueConsumer`              | `RedisQueue::push()`      |

In `config/queue.php` bleiben die beiden Verbindungskonfigurationen `default` und `rabbitmq` als Referenz erhalten;
nach dem Wechsel gilt die vom jeweiligen Plugin erzeugte Konfigurationsdatei.
