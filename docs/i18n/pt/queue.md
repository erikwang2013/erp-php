# Guia de uso e troca de driver da Fila (Queue)

> Configuração relacionada: `config/queue.php` (drivers e conexões), `config/process.php` (processo consumidor), `app/queue/` (classes de tarefas).
> Implementação relacionada: `app/queue/RedisQueue.php` (ferramenta produtora), `app/process/QueueConsumer.php` (processo consumidor), `app/queue/redis/SmokeTask.php` (tarefa de fumaça/smoke).

## 1. Situação atual: implementação mínima baseada em Redis LIST

O projeto **não instalou** atualmente o pacote `webman/redis-queue` (`composer show | grep queue` mostra apenas as dependências transitivas
`illuminate/queue` / `illuminate/redis`, sem o pacote de fila do webman), portanto a fila ponta a ponta usa a
**implementação mínima com Timer nativo do Workerman + Redis LIST**:

- **Armazenamento**: Redis LIST, chave `erp:queue:{queue}` (o `queue` padrão vem de
  `connections.redis.queue` em `config/queue.php`, ou seja, `default`).
- **Produção**: `RedisQueue::push(ClassName::class, 'consume', $data)` executa `LPUSH`.
- **Consumo**: processo `redis-queue` em `config/process.php` (count=1), que após `onWorkerStart` faz polling a cada
  **0,5 segundos** com `LPOP` para drenar a fila, distribuindo para as classes de tarefas por lista branca segundo o corpo da mensagem `{class, method, data}`.
- **Tratamento de falhas**: uma mensagem com falha não interrompe o loop de consumo; há retry automático (attempts+1, máximo 3 vezes),
  e ao exceder o limite entra na fila de mensagens mortas `erp:queue:failed` com registro do erro em log.
- **Backoff exponencial**: o retry não é executado imediatamente; a mensagem é gravada em um conjunto atrasado (zset, chave `erp:queue:{queue}:delay`) para reenfileiramento atrasado,
  com atraso do n-ésimo retry de `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` segundos
  (constantes em `app/process/QueueConsumer.php`: base=5s, cap=120s; com o limite real de 3 tentativas: 5s/10s),
  e ao expirar o processo consumidor a promove de volta à fila principal, evitando tempestade de retries de mensagens com falha.
- **Convenção do corpo da mensagem é compatível com o formato de job do `webman/redis-queue` oficial** (`class` / `method` / `data`),
  facilitando uma migração indolor no futuro.

### Verificação de fumaça (ponta a ponta)

1. Iniciar o serviço: `php start.php start -d`; `php start.php status` deve mostrar o processo `redis-queue`;
2. Produzir (a rota de debug original `/debug/queue-smoke` foi removida junto com as correções de segurança; usar o produtor):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Observar o resultado do consumo:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` —— log de operação gravado pela tarefa de fumaça;
   - `redis-cli GET erp:queue:smoke:count` —— contador de execuções consumidas;
   - `redis-cli LLEN erp:queue:default` —— tamanho do backlog da fila (deve voltar a 0).

## 2. Trocar para o webman/redis-queue oficial (driver Redis, recomendado)

Documentação oficial: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

Após a instalação, é gerada automaticamente a configuração `config/plugin/webman/redis-queue/redis.php`, com conteúdo similar:

```php
return [
    'default' => [
        'host' => 'redis://127.0.0.1:6379',
        'options' => [
            'auth' => '',            // senha, parâmetro opcional
            'db' => 0,               // banco de dados
            'max_attempts' => 5,     // número de retries após falha de consumo
            'retry_seconds' => 5,    // intervalo do retry (segundos); intervalo do N-ésimo retry = N * retry_seconds
        ],
    ],
];
```

Alterar `config/process.php`, substituindo o handler do processo `redis-queue` pela classe de consumo oficial
(a convenção do diretório de classes de tarefas `app/queue/redis/` e do método `consume()` permanece a mesma):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

A forma de produzir passa a ser:

```php
use Webman\RedisQueue\Client;

// Envio imediato
Client::send('default', ['key' => 'value']);

// Envio atrasado (atraso de 10 segundos)
Client::sendLater('default', ['key' => 'value'], 10);
```

> A escrita das classes de tarefas de consumo não muda: definir `public function consume(RedisQueue $queue, $data)` em `app/queue/redis/Xxx.php`;
> a extensão oficial oferece adicionalmente retry em caso de falha e capacidade de fila atrasada.

## 3. Trocar para RabbitMQ (integração oficial recomendada via protocolo STOMP)

A forma padrão do webman de integrar RabbitMQ é pelo plugin de protocolo **STOMP**
(cliente `workerman/stomp`), sendo necessário habilitar o plugin stomp no servidor RabbitMQ
(porta padrão **61613**). Documentação oficial: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Habilitar o plugin STOMP do RabbitMQ (servidor)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Instalar webman/stomp e configurar

```bash
composer require webman/stomp
```

A configuração é gerada automaticamente em `config/plugin/webman/stomp/`; preencher os parâmetros de conexão:

```php
// config/plugin/webman/stomp/stomp.php (exemplo)
return [
    'default' => [
        'host' => '127.0.0.1',
        'port' => 61613,      // porta STOMP (não é a porta AMQP 5672)
        'username' => 'guest',
        'password' => 'guest',
        'vhost' => '/',
        'queue' => 'default',
    ],
];
```

### 3.3 Adicionar o processo consumidor STOMP

Em `config/process.php`, adicionar (pode coexistir com o redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

As classes de tarefas de consumo ficam no diretório `app/queue/stomp/`, implementando a interface `Webman\Stomp\Consumer`:

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
        // O componente STOMP não faz serialização automática; ao produzir, se for array,
        // faça json_encode / serialize por conta própria e a desserialização correspondente ao consumir
    }
}
```

### 3.4 Produzir mensagens

```php
use Webman\Stomp\Client;

// Dados (ao passar array, é necessário serializar por conta própria)
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Resumo da escolha de driver

| Driver    | Pacote instalado        | Handler do processo consumidor           | API do produtor           |
|-----------|-------------------------|------------------------------------------|---------------------------|
| Redis     | `webman/redis-queue`    | `Webman\RedisQueue\Process\Consumer`     | `Client::send()`          |
| RabbitMQ  | `webman/stomp`          | `Webman\Stomp\Process\Consumer`          | `Client::send()`          |
| Mínimo    | Nenhum (padrão atual)   | `app\process\QueueConsumer`              | `RedisQueue::push()`      |

Em `config/queue.php`, os dois conjuntos de configuração de conexão `default` e `rabbitmq` são mantidos como referência;
após a troca, vale a configuração gerada pelo plugin correspondente.
