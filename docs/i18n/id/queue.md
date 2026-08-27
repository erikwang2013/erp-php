# Panduan Penggunaan dan Penggantian Driver Queue

> Konfigurasi terkait: `config/queue.php` (driver dan koneksi), `config/process.php` (proses konsumsi), `app/queue/` (kelas tugas).
> Implementasi terkait: `app/queue/RedisQueue.php` (alat produksi), `app/process/QueueConsumer.php` (proses konsumsi), `app/queue/redis/SmokeTask.php` (tugas smoke test).

## 1. Kondisi Saat Ini: Implementasi Minimal Berbasis Redis LIST

Proyek saat ini **tidak menginstal** paket ekstensi `webman/redis-queue` (`composer show | grep queue` hanya memiliki dependensi transitif
`illuminate/queue` / `illuminate/redis`, tanpa paket queue webman), oleh karena itu queue end-to-end menggunakan
**Timer native Workerman + Redis LIST** sebagai implementasi minimal:

- **Penyimpanan**: Redis LIST, kunci `erp:queue:{queue}` (default `queue` mengambil `connections.redis.queue` dari `config/queue.php`, yaitu `default`).
- **Produksi**: `RedisQueue::push(ClassName::class, 'consume', $data)` menjalankan `LPUSH`.
- **Konsumsi**: proses `redis-queue` di `config/process.php` (count=1), setelah `onWorkerStart` setiap **0,5 detik** melakukan polling `LPOP` untuk mengosongkan antrean, mendistribusikan ke kelas tugas sesuai whitelist isi pesan `{class, method, data}`.
- **Penanganan kegagalan**: kegagalan satu pesan tidak menghentikan loop konsumsi, otomatis mencoba ulang (attempts+1, maksimal 3 kali), melebihi batas masuk ke dead letter queue `erp:queue:failed` dan menulis log kesalahan.
- **Exponential backoff**: percobaan ulang tidak langsung dieksekusi, melainkan ditulis ke set tertunda (zset, kunci `erp:queue:{queue}:delay`) untuk diantrekan dengan penundaan, penundaan percobaan ulang ke-n = `min(RETRY_BASE_DELAY * 2^(n-1), RETRY_MAX_DELAY)` detik
  (konstanta `app/process/QueueConsumer.php`: base=5s, cap=120s, dengan batas 3 kali aktual menjadi 5s/10s),
  setelah jatuh tempo dinaikkan kembali ke antrean utama oleh proses konsumsi, menghindari percobaan ulang badai pada pesan yang gagal.
- **Konvensi isi pesan konsisten dengan format job `webman/redis-queue` resmi** (`class` / `method` / `data`),
  memudahkan migrasi tanpa rasa sakit di masa mendatang.

### Verifikasi Smoke Test (end-to-end)

1. Mulai layanan: `php start.php start -d`, `php start.php status` seharusnya menampilkan proses `redis-queue`;
2. Kirim (route debug asli `/debug/queue-smoke` telah dihapus bersama perbaikan keamanan, gunakan produksi via produsen):
   ```php
   app\queue\RedisQueue::push(app\queue\redis\SmokeTask::class, 'consume', ['trigger' => 'smoke']);
   ```
3. Amati hasil konsumsi:
   - `tail -f runtime/logs/queue-smoke-$(date +%F).log` — log operasi yang ditulis tugas smoke test;
   - `redis-cli GET erp:queue:smoke:count` — penghitung jumlah konsumsi;
   - `redis-cli LLEN erp:queue:default` — panjang antrean yang menumpuk (harus kembali ke 0).

## 2. Beralih ke webman/redis-queue Resmi (driver Redis, direkomendasikan)

Dokumen resmi: <https://webman.workerman.net/doc/zh-cn/queue/redis.html>

```bash
composer require webman/redis-queue
```

Setelah instalasi otomatis menghasilkan konfigurasi `config/plugin/webman/redis-queue/redis.php`, isinya serupa:

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

Ubah `config/process.php`, ganti handler proses `redis-queue` dengan kelas konsumen resmi
(konvensi direktori kelas tugas `app/queue/redis/` dan metode `consume()` tetap tidak berubah):

```php
'redis-queue' => [
    'handler' => Webman\RedisQueue\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/redis',
    ],
],
```

Cara pengiriman diubah menjadi:

```php
use Webman\RedisQueue\Client;

// 立即投递
Client::send('default', ['key' => 'value']);

// 延迟投递（延迟 10 秒）
Client::sendLater('default', ['key' => 'value'], 10);
```

> Cara menulis kelas tugas konsumsi tidak berubah: definisikan `public function consume(RedisQueue $queue, $data)` di `app/queue/redis/Xxx.php`;
> ekstensi resmi tambahan menyediakan percobaan ulang kegagalan dan kemampuan antrean tertunda.

## 3. Beralih ke RabbitMQ (cara resmi yang direkomendasikan: protokol STOMP)

Cara standar webman resmi untuk mengintegrasikan RabbitMQ adalah melalui plugin **protokol STOMP**
(klien `workerman/stomp`), perlu mengaktifkan plugin stomp di sisi server RabbitMQ
(port default **61613**). Dokumen resmi: <https://webman.workerman.net/doc/zh-cn/queue/stomp.html>

### 3.1 Aktifkan Plugin STOMP RabbitMQ (sisi server)

```bash
rabbitmq-plugins enable rabbitmq_stomp
```

### 3.2 Instal webman/stomp dan Konfigurasi

```bash
composer require webman/stomp
```

Konfigurasi otomatis dibuat di `config/plugin/webman/stomp/`, isi parameter koneksi:

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

### 3.3 Tambah Proses Konsumsi STOMP

Tambahkan di `config/process.php` (dapat berdampingan dengan redis-queue):

```php
'stomp' => [
    'handler' => Webman\Stomp\Process\Consumer::class,
    'count' => 1,
    'constructor' => [
        'consumer_dir' => app_path() . '/queue/stomp',
    ],
],
```

Kelas tugas konsumsi diletakkan di direktori `app/queue/stomp/`, mengimplementasikan antarmuka `Webman\Stomp\Consumer`:

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

### 3.4 Kirim Pesan

```php
use Webman\Stomp\Client;

// 数据（传递数组时需要自行序列化）
$data = json_encode(['to' => 'tom@example.com', 'content' => 'hello']);
Client::send('default', $data);
```

### 3.5 Ringkasan Pemilihan Driver

| Driver    | Paket instalasi         | Handler proses konsumsi                | API produsen            |
|-----------|-------------------------|----------------------------------------|-------------------------|
| Redis     | `webman/redis-queue`    | `Webman\RedisQueue\Process\Consumer`   | `Client::send()`        |
| RabbitMQ  | `webman/stomp`          | `Webman\Stomp\Process\Consumer`        | `Client::send()`        |
| Implementasi minimal | tidak ada (default saat ini) | `app\process\QueueConsumer`      | `RedisQueue::push()`    |

Dua set konfigurasi koneksi `default` dan `rabbitmq` di `config/queue.php` dipertahankan sebagai referensi;
setelah beralih, gunakan file konfigurasi yang dibuat oleh plugin terkait sebagai acuan.
