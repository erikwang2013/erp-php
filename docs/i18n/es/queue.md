# Guía de uso y cambio de driver de la cola (Queue)

> Configuración relacionada: `config/queue.php` (driver y conexión), `config/process.php` (proceso consumidor), `app/queue/` (clases de tareas).
> Implementación relacionada: `app/queue/RedisQueue.php` (herramienta de producción), `app/process/QueueConsumer.php` (proceso consumidor), `app/queue/redis/SmokeTask.php` (tarea de prueba de humo).

## 1. Estado actual: implementación mínima basada en Redis LIST

El proyecto **no tiene instalado** actualmente el paquete de extensión `webman/redis-queue` (`composer show | grep queue` solo muestra las dependencias transitivas
`illuminate/queue` / `illuminate/redis`, sin el paquete de colas de webman), por lo que la cola de extremo a extremo utiliza una
implementación mínima con **Timer nativo de Workerman + Redis LIST**:

- **Almacenamiento**: Redis LIST, con clave `erp:queue:{queue}` (por defecto `queue` toma `connections.redis.queue` de `config/queue.php`, es decir, `default`).
- **Producción**: `RedisQueue::push(ClassName::class, 'consume', $data)` ejecuta `LPUSH`.
- **Consumo**: el proceso `redis-queue` de `config/process.php` (count=1), tras `onWorkerStart`, consulta `LPOP` cada **0,5 segundos** para drenar la cola y distribuye a la clase de tarea según la lista blanca del cuerpo del mensaje `{class, method, data}`.
- **Gestión de fallos**: el fallo de un mensaje no interrumpe el bucle de consumo; se reintenta automáticamente (attempts+1, máximo 3 veces); al superar el límite entra en la cola de mensajes fallidos `erp:queue:failed` y se escribe un log de error.
- **Retroceso exponencial**: el reintento no se ejecuta inmediatamente, sino que se escribe en un conjunto diferido (zset, clave `erp:queue:{queue}:delay`) para reencolarlo con retraso; el enésimo reintento se retrasa `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` segundos
  (constantes de `app/process/QueueConsumer.php`: base=5s, cap=120s; con el límite real de 3 intentos, 5s/10s);
  al vencer, el proceso consumidor lo promueve de nuevo a la cola principal, evitando tormentas de reintentos de mensajes fallidos.
- **Convención del cuerpo del mensaje**: idéntica al formato de trabajo del `webman/redis-queue` oficial (`class` / `method` / `data`),
  lo que facilita una futura migración sin dolor.

### Verificación de humo (extremo a extremo)

1. Inicie el servicio: `php start.php start -d`; `php start.php status` debería mostrar el proceso `redis-queue`;
2. Envío (la ruta de depuración original `/debug/queue-smoke` se eliminó con la corrección de seguridad; use la producción directa):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Observe el resultado del consumo:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — el log de operaciones que escribe la tarea de humo;
   - `redis-cli GET erp:queue:smoke:count` — el contador de consumos;
   - `redis-cli LLEN erp:queue:default` — la longitud de la cola acumulada (debería volver a 0).

## 2. Cambio al webman/redis-queue oficial (driver Redis, recomendado)

Documentación oficial: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

Tras la instalación se genera automáticamente la configuración `config/plugin/webman/redis-queue/redis.php`, similar a:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // contraseña, parámetro opcional
            'db' => 0,               // base de datos
            'max_attempts' => 5,     // reintentos tras fallo de consumo
            'retry_seconds' => 5,    // intervalo de reintento (segundos); el enésimo reintento = N * retry_seconds
        ],
    ],
];
```

Modifique `config/process.php`, sustituyendo el handler del proceso `redis-queue` por la clase de consumo oficial
(se mantienen el directorio de clases de tareas `app/queue/redis/` y la convención del método `consume()`):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

El envío cambia a:

```php
use Webman\RedisQueue\Client;

// envío inmediato
Client::send('default', ['key' => 'value']);

// envío diferido (retraso de 10 segundos)
Client::sendLater('default', ['key' => 'value'], 10);
```

> La forma de escribir las clases de tareas de consumo no cambia: defina `public function consume(RedisQueue $queue, $data)` en `app/queue/redis/Xxx.php`;
> la extensión oficial aporta además reintentos ante fallos y capacidad de cola diferida.

## 3. Cambio a RabbitMQ (integración recomendada por el oficial mediante protocolo STOMP)

La forma estándar del oficial de webman para integrar RabbitMQ es mediante el plugin de **protocolo STOMP**
(cliente `workerman/stomp`), y es necesario activar el plugin stomp en el servidor RabbitMQ
(puerto por defecto **61613**). Documentación oficial: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Activar el plugin STOMP de RabbitMQ (servidor)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Instalar webman/stomp y configurarlo

```bash
composer require webman/stomp
```

La configuración se genera automáticamente en `config/plugin/webman/stomp/`; complete los parámetros de conexión:

```php
// config/plugin/webman/stomp/stomp.php (esquemático)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // puerto STOMP (no el puerto AMQP 5672)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 Añadir el proceso consumidor STOMP

En `config/process.php` añada (puede coexistir con redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Las clases de tareas de consumo van en el directorio `app/queue/stomp/`, implementando la interfaz `Webman\Stomp\Consumer`:

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
        // El componente STOMP no realiza serialización automática; si al enviar es un array
        // debe hacer usted mismo json_encode / serialize y, al consumir, la deserialización correspondiente
    }
}
```

### 3.4 Envío de mensajes

```php
use Webman\Stomp\Client;

// datos (si pasa un array, debe serializarlo usted mismo)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Resumen de selección de driver

| Driver | Paquete de instalación | Handler del proceso consumidor | API de producción |
|-----------|------------------------|----------------------------------------|---------------------------|
| Redis | `webman/redis-queue` | `Webman\RedisQueue\Process\Consumer` | `Client::send()` |
| RabbitMQ | `webman/stomp` | `Webman\Stomp\Process\Consumer` | `Client::send()` |
| Implementación mínima | ninguno (actual por defecto) | `app\process\QueueConsumer` | `RedisQueue::push()` |

En `config/queue.php` se conservan como referencia las dos configuraciones de conexión `default` y `rabbitmq`;
tras el cambio, prevalece el archivo de configuración generado por el plugin correspondiente.
