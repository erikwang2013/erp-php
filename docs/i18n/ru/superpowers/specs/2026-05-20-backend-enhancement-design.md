# Подпроект A: Усиление бэкенда — спецификация дизайна

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

## Объём

В этот раз — усиление бэкенда, всего 15 функциональных точек, затрагивает 9 новых файлов + 4 изменяемых файла.

---

## Перечень новых/изменяемых файлов

```
app/middleware/
├── OperationLog.php          # Новый: автоматическая запись журнала операций
├── Cors.php                  # Новый: кросс-домен
└── RateLimit.php             # Новый: лимит запросов Redis
app/admin/controller/
├── ConfigController.php      # Новый: CRUD системной конфигурации
├── LogController.php         # Новый: запрос журнала операций
├── ProfileController.php     # Новый: личный кабинет (включая выход)
├── UploadController.php      # Новый: загрузка файлов
├── ImportController.php      # Новый: импорт пользователей из Excel
└── HealthController.php      # Новый: проверка работоспособности
app/model/
├── AdminUser.php             # Изменение: + SoftDeletes + Searchable trait
└── OperationLog.php          # Изменение: + public $timestamps = false
app/middleware/
└── AdminAuth.php             # Изменение: проверка JWT-чёрного списка
app/admin/controller/
├── DashboardController.php   # Изменение: переход на реальную статистику БД
└── UserController.php        # Изменение: новые пакетные действия
config/
└── route.php                 # Изменение: новые маршруты + middleware
```

---

## 1. Middleware

### 1.1 CORS-промежуточное ПО

**Файл**: `app/middleware/Cors.php`

- OPTIONS-preflight напрямую возвращает 204
- Для не-preflight запросов в заголовки ответа добавляется `Access-Control-Allow-Origin: *`
- Разрешённые заголовки: `Authorization, Content-Type, API-Version`
- Максимальный кэш: 86400 секунд

Монтаж: глобальный middleware (`config/middleware.php`)

### 1.2 Промежуточное ПО лимита запросов

**Файл**: `app/middleware/RateLimit.php`

- Хранилище: скользящее окно Redis Sorted Set
- По умолчанию: 60 раз/мин/IP/маршрут
- Чувствительные интерфейсы:
  - `/api/auth/login`: 10 раз/мин
  - `/api/auth/register`: 5 раз/мин
- При превышении возвращается `429 Too Many Requests`

Монтаж: глобальный middleware (`config/middleware.php`), после Cors, перед ApiVersion

### 1.3 Промежуточное ПО журнала операций

**Файл**: `app/middleware/OperationLog.php`

- Записываются только POST/PUT/DELETE
- Записываемые поля: user_id, action, method, path, ip, input(JSON)
- Асинхронная запись после возврата ответа (не блокирует)

Монтаж: группа маршрутов `/admin`, после AdminPermission

### 1.4 Цепочка выполнения глобальных middleware

```
Все запросы:
  Cors → RateLimit → ApiVersion → {middleware маршрута} → Controller

Запросы /admin/*:
  Cors → RateLimit → ApiVersion → AdminAuth → AdminPermission → OperationLog → Controller
```

### 1.5 Выход (JWT-чёрный список)

**Файл**: `app/middleware/AdminAuth.php` (изменение)

**Принцип**: JWT сам по себе без состояния; при выходе token добавляется в чёрный список Redis, AdminAuth при проверке сначала сверяется с чёрным списком.

**Доработка AdminAuth**:
- В начале `process()` добавить: проверку текущего token по набору `jwt_blacklist` в Redis
- При попадании в чёрный список возвращать 401

**Маршрут выхода** (в личном кабинете):

| Метод | Маршрут | Пояснение |
|------|------|------|
| `POST` | `/admin/profile/logout` | Текущий Bearer token добавляется в чёрный список Redis, TTL=оставшийся срок действия token |

**Логика Logout**:
```php
// 解析 token 剩余有效期
$payload = JWT::decode($token);
$ttl = $payload['exp'] - time();
// 加入黑名单
Redis::setex("jwt_blacklist:" . md5($token), max($ttl, 0), '1');
```

---

## 2. Новые контроллеры и доработка существующих

### 2.1 CRUD системной конфигурации (`ConfigController`)

Наследует `BaseController`.

| Метод | Маршрут | Пояснение |
|------|------|------|
| `index()` | GET `/admin/config` | Постраничный список, фильтр по `group`, пагинация `page`/`limit` |
| `store()` | POST `/admin/config` | Создание элемента конфигурации, обязательны: group, key, value |
| `update()` | PUT `/admin/config/{id}` | Обновление value/type/description элемента конфигурации |
| `destroy()` | DELETE `/admin/config/{id}` | Удаление элемента конфигурации, требуется `confirmPassword()` |

### 2.2 Запрос журнала операций (`LogController`)

Наследует `BaseController`.

| Метод | Маршрут | Пояснение |
|------|------|------|
| `index()` | GET `/admin/log` | Постраничный список, фильтры: user_id, action, path, created_at(диапазон) |

Добавление/изменение/удаление не предоставляется, журнал записывается middleware автоматически.

### 2.3 Личный кабинет (`ProfileController`)

Наследует `BaseController`. Действует с текущим залогиненным пользователем (`$request->adminId`).

| Метод | Маршрут | Пояснение |
|------|------|------|
| `updateProfile()` | PUT `/admin/profile` | Обновление real_name, phone, email |
| `updatePassword()` | PUT `/admin/profile/password` | Смена пароля, требуются old_password, new_password, new_password_confirmation |

### 2.4 Загрузка файлов (`UploadController`)

Наследует `BaseController`.

| Метод | Маршрут | Пояснение |
|------|------|------|
| `upload()` | POST `/admin/upload` | Приём файла, поддерживаются image/jpeg/png/gif/pdf/xlsx/docx |

- Максимум 10MB
- Путь хранения: `public/upload/{date}/{hash}.{ext}`
- Возврат: `{ url: "/upload/2026-05-20/abc123.png" }`

### 2.5 Дашборд на реальных данных

**Файл**: `app/admin/controller/DashboardController.php` (изменение)

Перевод текущих захардкоженных фейковых данных на реальную статистику БД:

| Показатель | Источник | Пояснение |
|------|------|------|
| Всего пользователей | `AdminUser::count()` | Без мягко удалённых |
| Добавлено сегодня | `AdminUser::where('created_at', '>=', date('Y-m-d'))->count()` | |
| Всего ролей | `AdminRole::count()` | |
| Всего прав | `AdminPermission::count()` | |
| Данные тренда | `AdminUser::selectRaw('DATE(created_at) d, count(*) c')->groupBy('d')->orderBy('d','desc')->limit(7)->get()` | Помесячно, добавления за последние 7 дней |
| Данные распределения | `AdminUser::selectRaw('status, count(*) c')->groupBy('status')->get()` | Распределение по статусу |
| Последние операции | `OperationLog::with('user')->orderBy('created_at','desc')->limit(10)->get()` | Последние 10 записей журнала операций |

### 2.6 Пакетные операции с пользователями

**Файл**: `app/admin/controller/UserController.php` (изменение, новые методы)

| Метод | Маршрут | Пояснение |
|------|------|------|
| `batchDestroy()` | POST `/admin/user/batch/destroy` | Пакетное удаление, тело запроса `{ ids: [hashid, ...], password }` |
| `batchStatus()` | POST `/admin/user/batch/status` | Пакетное включение/отключение, тело запроса `{ ids: [hashid, ...], status: 1\|0 }` |

- Каждый id сначала проходит `decodeId()` в BIGINT
- `batchDestroy()` должен пройти проверку `confirmPassword()`

### 2.7 Импорт данных

**Файл**: `app/admin/controller/ImportController.php` (новый)

| Метод | Маршрут | Пояснение |
|------|------|------|
| `users()` | POST `/admin/import/users` | Загрузка файла Excel, пакетное создание пользователей |

Процесс:
1. Приём файла `.xlsx`
2. Разбор PhpSpreadsheet, ожидаемые колонки: `username, password, real_name, phone, email, status`
3. Построчная валидация + создание (ID через snowflake, пароль bcrypt, phone/email через encryption)
4. Возврат результата: `{ total: 100, success: 95, failed: 5, errors: [{row: 3, reason: "用户名已存在"}, ...] }`

### 2.8 Проверка работоспособности

**Файл**: `app/admin/controller/HealthController.php` (новый)

`GET /health` (без аутентификации, не попадает в журнал операций):

Возвращает статусы подключения компонентов:
```json
{
  "code": 0,
  "data": {
    "app": "open-admin",
    "version": "1.0",
    "php": "8.3.0",
    "database": "ok",
    "redis": "ok",
    "elasticsearch": "ok",
    "timestamp": 1747737600
  }
}
```

- При сбое проверки компонента значение соответствующего поля — строка описания ошибки
- Маршрут не вешается на префикс `/admin`, регистрируется отдельно глобально

---

## 3. Исправления моделей

### 3.1 Таймстампы OperationLog

**Файл**: `app/model/OperationLog.php` (изменение)

В таблице `erik_operation_log` только колонка `created_at` (нет `updated_at`). Eloquent при `save()` по умолчанию пытается записать `updated_at`, что вызывает SQL-ошибку.

Исправление: `public $timestamps = false;` + ручное указание `created_at` при записи.

### 3.2 Доработка модели AdminUser

- Добавить trait `Searchable`
- Реализовать `toSearchableArray()`: возвращает username, real_name
- `UserController::index()` при обнаружении ключевого слова использует `AdminUser::search($kw)->get()` вместо MySQL LIKE

Для ES нужно сначала создать индекс, можно через команды Scout:

```bash
php vendor/bin/scout index:create AdminUser
php vendor/bin/scout import:all AdminUser
```

---

## 4. Изменения маршрутов

В `config/route.php` добавляются маршруты:

```php
// /admin 路由组内新增:
Route::get('/config', [app\admin\controller\ConfigController::class, 'index']);
Route::post('/config', [app\admin\controller\ConfigController::class, 'store']);
Route::put('/config/{id}', [app\admin\controller\ConfigController::class, 'update']);
Route::delete('/config/{id}', [app\admin\controller\ConfigController::class, 'destroy']);

Route::get('/log', [app\admin\controller\LogController::class, 'index']);

Route::put('/profile', [app\admin\controller\ProfileController::class, 'updateProfile']);
Route::put('/profile/password', [app\admin\controller\ProfileController::class, 'updatePassword']);
Route::post('/profile/logout', [app\admin\controller\ProfileController::class, 'logout']);

Route::post('/upload', [app\admin\controller\UploadController::class, 'upload']);

Route::post('/user/batch/destroy', [app\admin\controller\UserController::class, 'batchDestroy']);
Route::post('/user/batch/status', [app\admin\controller\UserController::class, 'batchStatus']);

Route::post('/import/users', [app\admin\controller\ImportController::class, 'users']);

// 健康检查（全局路由，非 /admin 组内）
Route::get('/health', [app\admin\controller\HealthController::class, 'index']);

// 中间件:
/admin 组中间件追加 app\middleware\OperationLog::class
```

Регистрация глобальных middleware в `config/middleware.php`:

```php
return [
    app\middleware\Cors::class,
    app\middleware\RateLimit::class,
];
```

---

## 5. Дополнение кодов ошибок

| code | Значение | Сценарий возникновения |
|------|------|---------|
| 429 | Слишком частые запросы | Сработал RateLimit |

---

## 6. Не входит в объём этого раза

- Система уведомлений (нужны инфраструктура очереди сообщений + пуш-инфраструктура фронтенда)
- Страницы фронтенда Flutter (подпроект B)
- Обновление token HarmonyOS (подпроект C)
