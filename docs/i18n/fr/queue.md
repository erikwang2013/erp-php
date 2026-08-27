# Guide d'utilisation des files d'attente (Queue) et de bascule de pilote

> Configuration associée : `config/queue.php` (pilote et connexions), `config/process.php` (processus de consommation), `app/queue/` (classes de tâches).
> Implémentation associée : `app/queue/RedisQueue.php` (outil de production), `app/process/QueueConsumer.php` (processus de consommation), `app/queue/redis/SmokeTask.php` (tâche de fumée).

## 1. État actuel : implémentation minimale basée sur Redis LIST

Le projet n'a **pas installé** le paquet d'extension `webman/redis-queue` (`composer show | grep queue` ne montre que les dépendances transitives `illuminate/queue` / `illuminate/redis`, aucun paquet de file d'attente webman). Par conséquent, la file d'attente utilise de bout en bout une implémentation minimale **Timer natif Workerman + Redis LIST** :

- **Stockage** : Redis LIST, clé `erp:queue:{queue}` (par défaut, `queue` prend `connections.redis.queue` de `config/queue.php`, soit `default`).
- **Production** : `RedisQueue::push(ClassName::class, 'consume', $data)` exécute `LPUSH`.
- **Consommation** : le processus `redis-queue` dans `config/process.php` (count=1), après `onWorkerStart`, interroge `LPOP` toutes les **0,5 secondes** pour vider la file, et distribue vers la classe de tâches selon la liste blanche du corps de message `{class, method, data}`.
- **Gestion des échecs** : l'échec d'un message n'interrompt pas la boucle de consommation ; nouvelle tentative automatique (attempts+1, 3 fois maximum), puis entrée en file de lettres mortes `erp:queue:failed` et journal d'erreur écrit.
- **Backoff exponentiel** : la nouvelle tentative n'est pas exécutée immédiatement mais écrite dans un ensemble différé (zset, clé `erp:queue:{queue}:delay`) pour une mise en file différée ; la n-ième tentative est différée de `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` secondes (constantes de `app/process/QueueConsumer.php` : base=5s, cap=120s ; avec la limite réelle de 3 tentatives : 5s/10s), puis à échéance le processus de consommation la promet de nouveau dans la file principale, évitant une tempête de nouvelles tentatives de messages en échec.
- **Convention du corps de message identique au format de job officiel de `webman/redis-queue`** (`class` / `method` / `data`), facilitant une future migration sans douleur.

### Vérification par fumée (de bout en bout)

1. Démarrer le service : `php start.php start -d`, `php start.php status` doit montrer le processus `redis-queue` ;
2. Production (la route de débogage d'origine `/debug/queue-smoke` a été retirée avec les correctifs de sécurité ; utiliser la production par le producteur) :
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Observer le résultat de la consommation :
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — le journal d'opérations écrit par la tâche de fumée ;
   - `redis-cli GET erp:queue:smoke:count` — le compteur de consommations ;
   - `redis-cli LLEN erp:queue:default` — la longueur d'accumulation de la file (doit revenir à 0).

## 2. Bascule vers le webman/redis-queue officiel (pilote Redis, recommandé)

Documentation officielle : <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

Après l'installation, la configuration `config/plugin/webman/redis-queue/redis.php` est générée automatiquement, avec un contenu similaire :

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

Modifier `config/process.php` pour remplacer le handler du processus `redis-queue` par la classe de consommation officielle (le répertoire des classes de tâches `app/queue/redis/` et la convention de la méthode `consume()` restent inchangés) :

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

La production devient :

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> L'écriture des classes de tâches de consommation reste inchangée : définir `public function consume(RedisQueue $queue, $data)` dans `app/queue/redis/Xxx.php` ;
> l'extension officielle apporte en plus la nouvelle tentative en cas d'échec et la capacité de file différée.

## 3. Bascule vers RabbitMQ (accès officiellement recommandé via le protocole STOMP)

La méthode d'accès standard officielle de webman à RabbitMQ passe par le plugin de protocole **STOMP** (client `workerman/stomp`), qui nécessite d'activer le plugin stomp côté serveur RabbitMQ (port par défaut **61613**). Documentation officielle : <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Activer le plugin RabbitMQ STOMP (serveur)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Installer et configurer webman/stomp

```bash
composer require webman/stomp
```

La configuration est générée automatiquement dans `config/plugin/webman/stomp/`, à compléter avec les paramètres de connexion :

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

### 3.3 Ajouter un processus de consommation STOMP

Ajouter dans `config/process.php` (peut coexister avec redis-queue) :

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Les classes de tâches de consommation sont placées dans le répertoire `app/queue/stomp/` et implémentent l'interface `Webman\Stomp\Consumer` :

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

### 3.4 Production des messages

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Résumé du choix du pilote

| Pilote    | Paquet à installer         | Handler du processus de consommation | API du producteur          |
|-----------|----------------------------|--------------------------------------|----------------------------|
| Redis     | `webman/redis-queue`       | `Webman\RedisQueue\Process\Consumer` | `Client::send()`           |
| RabbitMQ  | `webman/stomp`             | `Webman\Stomp\Process\Consumer`      | `Client::send()`           |
| Implémentation minimale | Aucun (par défaut actuel) | `app\process\QueueConsumer`          | `RedisQueue::push()`       |

Dans `config/queue.php`, les deux ensembles de connexions `default` et `rabbitmq` sont conservés à titre de référence ;
après bascule, le fichier de configuration généré par le plugin correspondant fait foi.
