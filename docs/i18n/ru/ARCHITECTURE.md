# Диаграммы архитектуры и бизнес-логики

> Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

> Приведённые ниже диаграммы Mermaid автоматически рендерятся на GitHub / GitLab / в VS Code. В других средах используйте [Mermaid Live Editor](https://mermaid.live/).

---

## 1. Топология системной архитектуры

```mermaid
flowchart TB
    subgraph "Клиентский слой"
        A1["Flutter Web<br/>Панель администрирования ПК<br/>(Port 3000)"]
        A2["HarmonyOS ArkTS<br/>Мобильный/планшетный клиент"]
    end

    subgraph "Шлюз / пограничный слой (Nginx Edge)"
        B1["Nginx Edge Node<br/>Docker nginx:alpine<br/>Обратный прокси + HTTPS + Gzip<br/>Обслуживание статических файлов"]
    end

    subgraph "Прикладной слой (webman v2)"
        C_LOC["Middleware Locale<br/>Автоопределение Accept-Language"]
        C0["Middleware ApiVersion<br/>Проверка заголовка API-Version"]
        C1["Middleware AdminAuth<br/>Проверка JWT"]
        C2["Middleware AdminPermission<br/>Проверка прав RBAC"]
        C3["Контроллеры админ-панели<br/>Dashboard / User / Role / Permission"]
        C4["Публичные контроллеры v1<br/>Captcha / Auth"]
        C5["Общие сервисы<br/>Hashids / Snowflake / Encryption"]
    end

    subgraph "Слой хранения"
        D1[("MySQL 8.0<br/>Основное хранилище<br/>Префикс таблиц erp_")]
        D2[("Elasticsearch<br/>Полнотекстовый поиск<br/>Префикс индексов erp_")]
        D3[("Redis<br/>Сессии / кэш<br/>Хранение Captcha")]
    end

    subgraph "Внешние системы"
        E1["DevEco Studio<br/>Сборка HarmonyOS"]
        E2["Flutter SDK<br/>Сборка Web"]
    end

    A1 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    A2 -->|"HTTPS / JSON<br/>JWT Bearer"| B1
    B1 --> C0
    C0 --> C1
    C1 --> C2
    C2 --> C3
    C0 --> C4
    C3 --> C5
    C4 --> C5
    C3 --> D1
    C4 --> D1
    C3 --> D2
    C4 --> D2
    C1 --> D3

    style A1 fill:#1677FF,color:#fff
    style A2 fill:#1677FF,color:#fff
    style B1 fill:#722ED1,color:#fff
    style C0 fill:#EB2F96,color:#fff
    style C1 fill:#FA8C16,color:#fff
    style C2 fill:#FA8C16,color:#fff
    style C3 fill:#52C41A,color:#fff
    style C4 fill:#52C41A,color:#fff
    style C5 fill:#52C41A,color:#fff
    style D1 fill:#1890FF,color:#fff
    style D2 fill:#1890FF,color:#fff
    style D3 fill:#1890FF,color:#fff
```

---

## 2. Многоуровневая архитектура бэкенда

```mermaid
flowchart TD
    subgraph "Слой маршрутизации Route Layer"
        R1["config/route.php<br/>Отображение URL → Controller"]
    end

    subgraph "Слой промежуточного ПО Middleware Layer"
        M_LOC["Locale<br/>Автоопределение Accept-Language<br/>zh_CN/en"]
        M_RL["RateLimit<br/>Скользящее окно ограничения на Redis<br/>Заголовки ответа X-RateLimit"]
        M_SF["SecurityFilter<br/>Блокировка по обнаружению атак<br/>XSS/инъекции SQL/обход путей/CSRF"]
        M0["ApiVersion<br/>Проверка версии API<br/>Внедрение apiVersion"]
        M1["AdminAuth<br/>Проверка JWT-токена<br/>Внедрение adminId"]
        M2["AdminPermission<br/>Авторизация RBAC<br/>Сопоставление method.path<br/>Кэш прав в Redis на 60 с"]
    end

    subgraph "Слой контроллеров Controller Layer"
        CT1["BaseController<br/>success/fail<br/>encodeId/decodeId<br/>generateId<br/>confirmPassword"]
        CT2["UserController<br/>CRUD + поиск + пагинация"]
        CT3["RoleController<br/>CRUD + синхронизация прав"]
        CT4["PermissionController<br/>CRUD + построение дерева"]
        CT5["DashboardController<br/>Статистика/тренды/распределение"]
        CT6["ExportController<br/>Экспорт Excel/PDF"]
        CT7["CaptchaController<br/>Генерация/проверка капчи"]
        CT8["AuthController<br/>Вход/регистрация/обновление"]
    end

    subgraph "Слой сервисов Service Layer"
        S1["HashidsService<br/>Кодирование/декодирование ID"]
        S2["SnowflakeService<br/>Генерация глобально уникальных ID"]
        S3["EncryptionService<br/>Шифрование/дешифрование + маскирование"]
    end

    subgraph "Слой моделей Model Layer"
        MD1["AdminUser<br/>encryptable casts"]
        MD2["AdminRole"]
        MD3["AdminPermission"]
        MD4["OperationLog"]
        MD5["SystemConfig"]
    end

    subgraph "Слой драйверов Driver Layer"
        D1["MySQL PDO"]
        D2["Elasticsearch HTTP"]
        D3["Redis"]
    end

    R1 --> M_LOC --> M_SF --> M_RL --> M0
    M0 --> M1
    M1 --> M2
    M2 --> CT2 & CT3 & CT4 & CT5 & CT6
    M0 --> CT7 & CT8
    CT1 -.->|extends| CT2 & CT3 & CT4 & CT5 & CT6
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> S1 & S2 & S3
    CT2 & CT3 & CT4 & CT5 & CT6 & CT7 & CT8 --> MD1 & MD2 & MD3 & MD4 & MD5
    MD1 & MD2 & MD3 & MD4 & MD5 --> D1
    MD1 --> D2
    CT7 --> D3

    style R1 fill:#722ED1,color:#fff
    style M_LOC fill:#13C2C2,color:#fff
    style M_SF fill:#FF4D4F,color:#fff
    style M_RL fill:#EB2F96,color:#fff
    style M0 fill:#EB2F96,color:#fff
    style M1 fill:#FA8C16,color:#fff
    style M2 fill:#FA8C16,color:#fff
    style CT1 fill:#1677FF,color:#fff
```

### Расширение бизнес-слоя ERP

По мере эволюции системы от чистой админ-панели до полноценной ERP-системы в слое контроллеров и сервисов добавлены следующие бизнес-модули:

| Слой | Каталог | Описание |
|------|---------|----------|
| Бизнес-контроллеры | `app/controller/{product,purchase,sales,inventory,finance,crm,workflow,notification,project,hr,manufacturing,report}/` | 70 шт., разделены по модулям, обрабатывают бизнес-запросы |
| Бизнес-сервисы | `app/service/{inventory,finance,notification}/` | Приход/расход склада + расчёт себестоимости, дебиторская/кредиторская задолженность + взаимозачёт, отправка уведомлений |

---

## 3. Жизненный цикл запроса

```mermaid
sequenceDiagram
    participant C as Клиент
    participant N as Nginx
    participant MW_LOC as Locale
    participant MW_SF as SecurityFilter
    participant MW_RL as RateLimit
    participant MW0 as ApiVersion
    participant MW1 as AdminAuth
    participant MW2 as AdminPermission
    participant CTL as Контроллер
    participant SVC as Сервис
    participant MDL as Модель
    participant DB as MySQL
    participant OPLOG as OperationLog

    C->>N: HTTPS-запрос<br/>Header: API-Version: v1
    N->>MW_LOC: Передача
    MW_LOC->>MW_LOC: Разбор Accept-Language<br/>Установка locale
    MW_LOC->>MW_SF: Пройдено

    alt Нестандартный HTTP-метод (TRACE/CONNECT/PATCH...)
        MW_SF-->>C: 405 Method Not Allowed
    else Метод допустим (GET/POST/PUT/DELETE/OPTIONS/HEAD)
        Note over MW_SF: Проверка по белому списку методов пройдена
    end

    alt Сработало обнаружение атаки
        MW_SF-->>C: 403 Forbidden
    end

    MW_SF->>MW_RL: Пройдено

    alt Сработало ограничение частоты
        MW_RL-->>C: 429 + Retry-After
    end

    MW_RL->>MW0: Пройдено

    alt Неподдерживаемая версия
        MW0-->>C: 400 Неподдерживаемая версия API
    else Версия допустима
        MW0->>MW0: $request->apiVersion = v1
    end

    alt Токен отсутствует или недействителен
        MW1-->>C: 401 Unauthorized
    else Токен действителен
        MW1->>MW1: jwt()->verify(token)
        MW1->>MW2: $request->adminId = sub
    end

    alt Нет прав
        MW2-->>C: 403 Forbidden
    else Права есть
        MW2->>CTL: Переход в контроллер
    end

    CTL->>CTL: Проверка параметров (validator)
    CTL->>CTL: decodeId(hashid) → BIGINT

    alt Чувствительная операция (DELETE)
        CTL->>CTL: confirmPassword(adminId, password)
        alt Неверный пароль
            CTL-->>C: 422 Ошибка проверки пароля
        end
    end

    CTL->>MDL: AdminUser::find(id)
    MDL->>MDL: Автоматическое расшифровывание encryptable cast
    MDL->>DB: SELECT
    DB-->>MDL: Строка
    MDL-->>CTL: Модель

    CTL->>SVC: encodeId(id) → hashid
    SVC-->>CTL: hash-строка

    CTL->>CTL: Формирование JSON-ответа
    CTL-->>C: 200 { code: 0, data: {...} }
    CTL-->>OPLOG: Запись журнала операций (POST/PUT/DELETE)
```

---

## 4. Поток аутентификации и капчи

```mermaid
sequenceDiagram
    participant U as Пользователь
    participant CL as Клиент
    participant SV as Сервер
    participant JWT as JWT Service
    participant CAP as Captcha Service

    Note over U,CAP: === Шаг 1: получение капчи ===
    CL->>SV: POST /api/captcha/generate
    SV->>CAP: captcha_create('click')
    CAP->>CAP: Генерация фонового изображения 300×200
    CAP->>CAP: Случайное размещение N целей с китайскими символами
    CAP->>CAP: Генерация key, сохранение targets
    CAP-->>SV: { key, image(PNG base64), targets }
    SV-->>CL: 200 { key, image, extra.targets }

    Note over U,CAP: === Шаг 2: клик пользователя ===
    CL->>CL: Отрисовка изображения капчи
    CL->>CL: Подсказка «Нажмите по порядку: дерево → птица → цветок»
    U->>CL: Последовательные клики по позициям символов на изображении
    CL->>CL: Сбор clicks: [{x,y}, {x,y}, {x,y}]

    Note over U,CAP: === Шаг 3: вход ===
    CL->>SV: POST /api/auth/login { username, password, captcha_key, clicks }
    SV->>CAP: captcha_verify(key, 'click', clicks)
    alt Ошибка капчи
        CAP-->>SV: false
        SV-->>CL: 422 Ошибка капчи
    else Капча верна
        CAP-->>SV: true
        SV->>SV: password_verify()
        alt Неверные учётные данные
            SV-->>CL: 401 Неверное имя пользователя или пароль
        else Учётные данные верны
            SV->>JWT: jwt()->create({sub, username})
            JWT-->>SV: access_token (2ч)
            SV->>JWT: jwt()->refresh()
            JWT-->>SV: refresh_token (14д)
            SV-->>CL: 200 { access_token, refresh_token, user }
        end
    end

    Note over U,CAP: === Последующие запросы ===
    CL->>SV: GET /admin/dashboard<br/>Authorization: Bearer access_token
    SV->>JWT: jwt()->verify(token)
    JWT-->>SV: { sub, username }
    SV-->>CL: 200 { данные дашборда }
```

---

## 5. Модель прав RBAC

```mermaid
flowchart LR
    subgraph "Пользователь User"
        U1["admin<br/>(супер-администратор)"]
        U2["editor<br/>(редактор)"]
        U3["viewer<br/>(только чтение)"]
    end

    subgraph "Роль Role"
        R1["super_admin<br/>Идентификатор прав: *"]
        R2["editor<br/>Идентификаторы прав: get.*, post.*"]
        R3["viewer<br/>Идентификатор прав: get.*"]
    end

    subgraph "Право Permission (дерево)"
        P1["dashboard<br/>type=1 меню"]
        P2["user<br/>type=1 меню"]
        P3["get.admin/user<br/>type=3 API"]
        P4["post.admin/user<br/>type=3 API"]
        P5["delete.admin/user<br/>type=3 API"]
        P6["export.excel<br/>type=2 кнопка"]
    end

    U1 --> R1
    U2 --> R2
    U3 --> R3

    R1 -->|"* (все права)"| P1 & P2 & P3 & P4 & P5 & P6
    R2 --> P1 & P2 & P3 & P4
    R3 --> P1 & P3

    P2 --> P3 & P4 & P5
    P1 --> P6

    style U1 fill:#1677FF,color:#fff
    style R1 fill:#FA8C16,color:#fff
    style P1 fill:#52C41A,color:#fff
```

```mermaid
flowchart TD
    subgraph "Типы прав"
        T1["type=1 меню<br/>Управление показом/скрытием боковой панели"]
        T2["type=2 кнопка<br/>Управление кнопками действий на странице"]
        T3["type=3 API<br/>Управление доступом к интерфейсам"]
    end

    subgraph "Формат идентификатора прав"
        F1["{method}.{path}<br/>Напр.: get.admin/user<br/>Напр.: post.admin/user<br/>Напр.: delete.admin/role"]
    end

    subgraph "Процесс проверки"
        J1["Извлечение токена → adminId"]
        J2["Поиск ролей пользователя"]
        J3["Сбор всех slug прав"]
        J4["Формирование method.path"]
        J5{"Совпадение?"}
        J6["Разрешить"]
        J7["403 Forbidden"]

        J1 --> J2
        J2 --> J3
        J3 --> J4
        J4 --> J5
        J5 -->|"да / slug=*"| J6
        J5 -->|нет| J7
    end

    style J6 fill:#52C41A,color:#fff
    style J7 fill:#FF4D4F,color:#fff
```

---

## 6. Полный жизненный цикл ID

```mermaid
flowchart LR
    subgraph "1. Генерация"
        G1["SnowflakeService<br/>::generate()"]
        G2["datacenter_id(5bit)<br/>+ worker_id(5bit)<br/>+ timestamp(41bit)<br/>+ sequence(12bit)"]
        G3["BIGINT(18)<br/>Напр.: 1750123456789"]
        G1 --> G2 --> G3
    end

    subgraph "2. Хранение"
        S1["Таблицы MySQL erp_*<br/>id BIGINT UNSIGNED<br/>NOT NULL"]
        S2["Чувствительные поля<br/>encryptable cast<br/>Шифрование AES-128-ECB"]
        G3 --> S1
        S1 --> S2
    end

    subgraph "3. Передача"
        T1["HashidsService<br/>::encode(bigint)"]
        T2["hashid-строка<br/>Напр.: aB3xK9mW2pQ7rT5v"]
        S1 --> T1
        T1 --> T2
    end

    subgraph "4. Обратное декодирование"
        R1["HashidsService<br/>::decode(hashid)"]
        R2["BIGINT"]
        T2 --> R1 --> R2
    end

    style G1 fill:#1677FF,color:#fff
    style T1 fill:#52C41A,color:#fff
    style S2 fill:#FA8C16,color:#fff
```

---

## 7. Многоуровневое шифрование данных

```mermaid
flowchart TB
    subgraph "Шифрование на транспортном уровне (encryption)"
        E1["Клиент отправляет чувствительные данные"]
        E2["Шифрование AES-256-CBC"]
        E3["Передача шифротекста по API"]
        E4["Расшифровка на сервере и обработка"]
        E1 --> E2 --> E3 --> E4
    end

    subgraph "Шифрование на уровне хранения (encryptable)"
        D1["Model $casts<br/>email => Encryptable::class<br/>phone => Encryptable::class<br/>id_card => Encryptable::class"]
        D2["Запись: автоматическое шифрование"]
        D3["MySQL VARCHAR(500)<br/>Хранение шифротекста"]
        D4["Чтение: автоматическое расшифровывание"]
        D1 --> D2 --> D3 --> D4
    end

    subgraph "Маскирование на уровне отображения (mask)"
        M1["phone: 138****1234"]
        M2["email: a***@example.com"]
        M3["id_card: ********"]
        D4 --> M1 & M2 & M3
    end

    E4 --> D1

    style E2 fill:#1677FF,color:#fff
    style D2 fill:#FA8C16,color:#fff
    style M1 fill:#52C41A,color:#fff
```

---

## 8. ER-отношения базы данных

```mermaid
erDiagram
    erp_admin_user {
        BIGINT id PK "Snowflake"
        VARCHAR username UK
        VARCHAR password "bcrypt"
        VARCHAR real_name
        VARCHAR avatar
        VARCHAR email "зашифровано"
        VARCHAR phone "зашифровано"
        VARCHAR id_card "зашифровано"
        TINYINT status
        DATETIME last_login_at
        VARCHAR last_login_ip
        DATETIME created_at
        DATETIME updated_at
        DATETIME deleted_at "мягкое удаление"
    }

    erp_admin_role {
        BIGINT id PK "Snowflake"
        VARCHAR name
        VARCHAR slug UK
        VARCHAR description
        TINYINT status
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_permission {
        BIGINT id PK "Snowflake"
        BIGINT parent_id FK "самоссылка"
        VARCHAR name
        VARCHAR slug
        TINYINT type "1меню 2кнопка 3API"
        VARCHAR icon
        VARCHAR path
        INT sort
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user_role {
        BIGINT user_id PK_FK
        BIGINT role_id PK_FK
    }

    erp_admin_role_permission {
        BIGINT role_id PK_FK
        BIGINT permission_id PK_FK
    }

    erp_operation_log {
        BIGINT id PK "Snowflake"
        BIGINT user_id FK
        VARCHAR action
        VARCHAR method
        VARCHAR path
        VARCHAR ip
        VARCHAR source "клиент"
        TEXT input "маскирование"
        DATETIME created_at
    }

    erp_system_config {
        BIGINT id PK "Snowflake"
        VARCHAR group
        VARCHAR key
        TEXT value
        VARCHAR type
        VARCHAR description
        DATETIME created_at
        DATETIME updated_at
    }

    erp_admin_user ||--o{ erp_admin_user_role : "user_id"
    erp_admin_role ||--o{ erp_admin_user_role : "role_id"
    erp_admin_role ||--o{ erp_admin_role_permission : "role_id"
    erp_admin_permission ||--o{ erp_admin_role_permission : "permission_id"
    erp_admin_user ||--o{ erp_operation_log : "user_id"
    erp_admin_permission ||--o{ erp_admin_permission : "parent_id"
```

---

## 9. Бизнес-процесс экспорта

```mermaid
sequenceDiagram
    participant C as Клиент
    participant CTL as ExportController
    participant DB as MySQL
    participant FS as Файловая система

    Note over C,FS: === Экспорт Excel ===
    C->>CTL: POST /admin/export/excel<br/>{ table, columns, conditions }
    CTL->>DB: SELECT ... LIMIT 10000
    DB-->>CTL: Данные
    CTL->>CTL: Расшифровка чувствительных полей
    CTL->>CTL: Маскирование (maskPhone/maskEmail)
    CTL->>CTL: Построение PhpSpreadsheet<br/>Заголовок: синий фон, белый текст<br/>Строки данных: тонкие границы<br/>Закрепление первой строки<br/>Автофильтр
    CTL->>FS: Запись runtime/tmp/export_*.xlsx
    CTL-->>C: Скачивание файла

    Note over C,FS: === Экспорт PDF ===
    C->>CTL: POST /admin/export/pdf<br/>{ type, title, data }
    CTL->>CTL: buildPdfHtml()<br/>Колонтитул: заголовок + копирайт + время<br/>Содержимое: таблица или карточки<br/>Нижний колонтитул: неудаляемый копирайт
    CTL->>CTL: Отрисовка Dompdf A4 альбомная
    CTL->>FS: Запись runtime/tmp/export_*.pdf
    CTL-->>C: Скачивание файла
```

---

## 10. Дерево компонентов Flutter Web

```mermaid
flowchart TD
    APP["AdminApp (GetMaterialApp)"]
    APP --> LP["/login<br/>LoginPage"]
    APP --> DB["/dashboard<br/>AdminLayout"]

    LP --> LF["Форма входа<br/>Имя пользователя/пароль/капча"]
    LF --> CAPTCHA["Компонент кликовой капчи<br/>GestureDetector + Stack<br/>Image.memory(base64)<br/>Отметка клика Circle"]

    DB --> SIDEBAR["Боковая панель NavigationDrawer<br/>Сворачиваемая 64px / 240px<br/>Дашборд/пользователи/роли/конфиг/журналы"]
    DB --> HEADER["Верхняя панель 56px<br/>Кнопка сворачивания + меню пользователя<br/>AlertDialog выхода"]
    DB --> CONTENT["Область содержимого"]
    CONTENT --> DASH["DashboardPage<br/>Карточки статистики GridView<br/>Линейный график трендов LineChart<br/>Круговая диаграмма PieChart<br/>Последние операции ListTile"]

    style APP fill:#1677FF,color:#fff
    style CAPTCHA fill:#FA8C16,color:#fff
    style SIDEBAR fill:#722ED1,color:#fff
    style DASH fill:#52C41A,color:#fff
```

---

## 11. Маршрутизация страниц HarmonyOS

```mermaid
flowchart LR
    EA["EntryAbility<br/>Запуск"]
    EA -->|"Нет токена"| LP["LoginPage<br/>Страница входа"]
    EA -->|"Есть токен"| DP["DashboardPage<br/>Дашборд"]

    LP -->|"Успешный вход<br/>replaceUrl"| DP

    DP -->|"pushUrl"| ULP["UserListPage<br/>Список пользователей"]
    DP -->|"pushUrl"| PP["ProfilePage<br/>Личный кабинет"]

    ULP -->|"pushUrl"| UDP["UserDetailPage<br/>Детали/создание/редактирование пользователя"]
    ULP -->|"router.back"| DP
    UDP -->|"router.back"| ULP

    PP -->|"Выход<br/>replaceUrl"| LP
    PP -->|"router.back"| DP

    style LP fill:#1677FF,color:#fff
    style DP fill:#52C41A,color:#fff
    style ULP fill:#FA8C16,color:#fff
    style UDP fill:#FA8C16,color:#fff
    style PP fill:#722ED1,color:#fff
```

---

## 12. Обзор многоуровневой защиты

```mermaid
flowchart TB
    subgraph "Уровень 1: проверка человек-машина"
        L1["Кликовая капча<br/>Click Captcha<br/>Обязательна при входе/регистрации"]
    end

    subgraph "Уровень 2: подтверждение операции"
        L2["Повторное подтверждение паролем<br/>confirmPassword()<br/>Обязательно для DELETE"]
    end

    subgraph "Уровень 3: безопасность передачи"
        L3["HTTPS<br/>JWT Bearer Token<br/>AES-256-CBC"]
    end

    subgraph "Уровень 4: аутентификация"
        L4["JWT HS256<br/>access_token 2ч<br/>refresh_token 14д"]
    end

    subgraph "Уровень 5: авторизация прав"
        L5["RBAC<br/>Гранулярность method.path<br/>Супер-администратор *"]
    end

    subgraph "Уровень 6: защита данных"
        L6["ID интерфейсов: шифрование Hashids<br/>Тело запроса: шифрование Encryption<br/>Слой хранения: шифрование Encryptable<br/>Экспорт: маскирование + копирайт"]
    end

    subgraph "Уровень 7: аудит и трассировка"
        L7["OperationLog<br/>Запись всех операций<br/>Пользователь/IP/время/клиент/параметры"]
    end

    L1 --> L2 --> L3 --> L4 --> L5 --> L6 --> L7

    style L1 fill:#1677FF,color:#fff
    style L2 fill:#1677FF,color:#fff
    style L3 fill:#FA8C16,color:#fff
    style L4 fill:#FA8C16,color:#fff
    style L5 fill:#52C41A,color:#fff
    style L6 fill:#722ED1,color:#fff
    style L7 fill:#FF4D4F,color:#fff
```

---

## 13. Топология развёртывания

```mermaid
flowchart TB
    subgraph "DNS / CDN"
        DNS["erik.xyz"]
    end

    subgraph "Веб-сервер"
        NGX["Nginx<br/>:443 HTTPS<br/>:80 → 443 redirect<br/>gzip on"]
        STA["Статические файлы<br/>Flutter Web build/"]
    end

    subgraph "Серверы приложений (горизонтальное масштабирование)"
        WM1["webman worker 1<br/>:8787"]
        WM2["webman worker 2<br/>:8787"]
        WM3["webman worker N<br/>:8787"]
    end

    subgraph "Слой данных"
        MYSQL["MySQL 8.0<br/>Мастер-реплика<br/>Префикс erp_"]
        ES["Elasticsearch 8.x<br/>Кластер из 3 узлов<br/>Префикс erp_"]
        REDIS["Redis 7.x<br/>Режим сентинел<br/>poster:captcha:*"]
    end

    subgraph "Мониторинг"
        MON["Grafana<br/>+ Prometheus"]
    end

    DNS --> NGX
    NGX --> STA
    NGX --> WM1 & WM2 & WM3
    WM1 & WM2 & WM3 --> MYSQL
    WM1 & WM2 & WM3 --> ES
    WM1 & WM2 & WM3 --> REDIS
    WM1 & WM2 & WM3 --> MON

    style NGX fill:#722ED1,color:#fff
    style WM1 fill:#1677FF,color:#fff
    style WM2 fill:#1677FF,color:#fff
    style WM3 fill:#1677FF,color:#fff
    style MYSQL fill:#1890FF,color:#fff
    style ES fill:#1890FF,color:#fff
    style REDIS fill:#1890FF,color:#fff
```

---

## 14. Общая архитектура ERP-системы

```mermaid
graph TB
    subgraph Client["Клиентский слой"]
        FW["Flutter Web<br/>Панель администрирования ПК"]
        FA["Flutter App<br/>iOS/Android/macOS/Windows/Linux"]
        HW["HarmonyOS<br/>Нативное приложение HarmonyOS"]
    end

    subgraph Gateway["Слой API-шлюза"]
        MW["Цепочка промежуточного ПО<br/>Locale→Cors→SecurityFilter→RateLimit→Auth→Permission→OpLog"]
    end

    subgraph Business["Слой бизнес-модулей"]
        direction LR
        Admin["Системное администрирование<br/>Пользователи/роли/права/конфиг/журналы"]
        Product["Управление товарами<br/>Товары/категории/бренды/склады/поставщики/клиенты"]
        Purchase["Управление закупками<br/>Заявка→заказ→приёмка→возврат→расчёт"]
        Sales["Управление продажами<br/>Предложение→заказ→отгрузка→возврат→расчёт"]
        Inventory["Управление складом<br/>Приход/расход/партии/инвентаризация/перемещение/предупреждения"]
        Finance["Управление финансами<br/>Счета/проводки/дебиторка-кредиторка/главная книга/детали/отчёты/возмещения"]
        CRM["CRM<br/>Клиенты/контакты/сделки/воронка/общий пул/предложения/договоры"]
        Workflow["Согласование рабочих процессов<br/>Определение процесса/подача/одобрение/отклонение/отзыв"]
        Notification["Уведомления<br/>Список уведомлений/прочитано/счётчик непрочитанных"]
        Project["Управление проектами<br/>Проекты/задачи/учёт времени"]
        HR["HR<br/>Отделы/сотрудники/должности/посещаемость/отпуска/зарплаты"]
        Manufacturing["Производство<br/>BOM/производственные заказы/маршруты/рабочие центры/MRP"]
        Report["Пользовательские отчёты<br/>Шаблоны отчётов/наборы данных/поля/фильтры/планировщик"]
    end

    subgraph Service["Слой бизнес-сервисов"]
        IS["InventoryService<br/>Приход/расход + скользящая средневзвешенная себестоимость"]
        FS["FinanceService<br/>Автогенерация дебиторки/кредиторки + взаимозачёт"]
        NS["NotificationService<br/>Единая отправка уведомлений"]
    end

    subgraph Data["Слой данных"]
        MySQL["MySQL 8.0<br/>163 бизнес-таблицы"]
        Redis["Redis 7<br/>Кэш/лимиты/сессии"]
        ES["Elasticsearch 8<br/>Полнотекстовый поиск"]
    end

    Client --> Gateway
    Gateway --> Business
    Business --> Service
    Service --> Data
    Business --> Data
```

---

## 15. Поток данных между модулями

```mermaid
sequenceDiagram
    participant PO as Приёмка закупки
    participant IS as InventoryService
    participant FS as FinanceService
    participant INV as Таблица остатков
    participant COST as Записи себестоимости
    participant ARAP as Дебиторка/кредиторка

    PO->>IS: stockIn(товар,количество,цена)
    IS->>INV: Обновление остатка в реальном времени (с блокировкой)
    IS->>COST: Пересчёт скользящей средневзвешенной себестоимости
    IS-->>PO: Возврат ID записи
    
    PO->>FS: createAp(поставщик,сумма)
    FS->>ARAP: Создание записи кредиторской задолженности
    
    Note over PO,ARAP: Отгрузка продажи аналогично: stockOut + createAr
```

---

## 16. Поток расчёта складской себестоимости

```mermaid
graph LR
    A[Приёмка закупки 100₽×10 шт.] --> B[Записи прихода]
    C[Приёмка закупки 130₽×20 шт.] --> D[Записи прихода]
    B --> E[Остаток: 10 шт., себестоимость 100]
    D --> F[Остаток: 30 шт., себестоимость 120]
    E --> G[Скользящая средняя: 100]
    F --> H[Скользящая средняя: 120]
    H --> I[Расход по себестоимости 120]
```

---

## 17. Поток данных согласования рабочего процесса

```mermaid
sequenceDiagram
    participant Biz as Бизнес-модуль
    participant WF as WorkflowController
    participant APR as ApprovalController
    participant WFE as Движок рабочих процессов
    participant NTF as NotificationService

    Biz->>WF: Подача на согласование (номер документа, тип модуля)
    WF->>WFE: Сопоставление определения процесса → создание экземпляра
    WFE->>APR: Уведомление согласующего первого узла
    APR->>NTF: Отправка уведомления о согласовании
    NTF-->>APR: Уведомление отправлено
    APR->>APR: Согласующий одобряет/отклоняет
    alt Одобрение
        APR->>WFE: Переход к следующему узлу
        alt Все узлы пройдены
            WFE->>Biz: Обратный вызов: согласовано, обновление статуса бизнес-документа
        end
    else Отклонение
        WFE->>Biz: Обратный вызов: согласование отклонено
    end
```

---

## 18. Поток данных уведомлений

```mermaid
sequenceDiagram
    participant Event as Источник события
    participant NS as NotificationService
    participant DB as Таблица уведомлений
    participant User as Пользователь

    Event->>NS: Триггер уведомления (тип, заголовок, содержимое, получатель)
    NS->>DB: Запись уведомления
    NS-->>User: Пуш (внутреннее сообщение/WebSocket)
    User->>NS: Отметить прочитанным
    NS->>DB: Обновление статуса прочтения
    User->>NS: Запрос счётчика непрочитанных
    NS-->>User: Количество непрочитанных
```

---

## 19. Поток данных MRP (планирование потребностей в материалах)

```mermaid
sequenceDiagram
    participant SO as Заказ продажи
    participant MRP as MrpController
    participant BOM as MfgBom
    participant INV as InventoryService
    participant PO as Предложение закупки
    participant MO as Предложение производства

    SO->>MRP: Потребность заказа продажи
    MRP->>BOM: Развёртывание BOM для получения списка материалов
    BOM-->>MRP: Материалы + нормативный расход
    MRP->>INV: Запрос доступного остатка
    INV-->>MRP: Количество на складе
    MRP->>MRP: Расчёт чистой потребности = валовая потребность − остаток
    alt Не хватает сырья
        MRP->>PO: Генерация предложения закупки
    else Не хватает полуфабрикатов
        MRP->>MO: Генерация предложения производства
    end
```

---

## 20. Таблица соответствия «контроллер-сервис-модель» модулей ERP

> Примечание о слое сервисов: в колонке «Ключевой Service» отмечены бизнес-сервисы, уже вынесенные для модуля; модули,
> отмеченные **⚠ контроллер напрямую обращается к модели — известный технический долг**,
> по-прежнему вызывают методы запроса/записи моделей напрямую из контроллеров (`XxxModel::find/where/save` и т. д.),
> слой сервисов для них ещё не выделен; это известный технический долг, который будет постепенно устраняться
> по схеме лёгкого выделения сервисного слоя P2-F2 (`app/service/AbstractCrudService` — универсальный CRUD-базовый класс + модульный Service).

| Модуль | Контроллеры (каталог) | Ключевой Service | Основные модели | Число таблиц |
|--------|-----------------------|------------------|-----------------|--------------|
| Системное администрирование | admin/controller/ (14) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | AdminUser, AdminRole, AdminPermission | 7 |
| Управление товарами | controller/product/ (7) | ProductService | Product, Category, Brand, Warehouse, Supplier, Customer | 11 |
| Управление закупками | controller/purchase/ (5) | InventoryService, FinanceService ⚠ CRUD пока напрямую, известный технический долг | PurchaseOrder, PurchaseReceive | 9 |
| Управление продажами | controller/sales/ (5) | InventoryService, FinanceService ⚠ CRUD пока напрямую, известный технический долг | SalesOrder, SalesDelivery | 9 |
| Управление складом | controller/inventory/ (5) | InventoryService ⚠ CRUD пока напрямую, известный технический долг | Inventory, InventoryFlow, CostRecord | 11 |
| Управление финансами | controller/finance/ (20) | FinanceService ⚠ CRUD пока напрямую, известный технический долг | FinanceArAp, FinanceVoucher, FinanceReceipt, FinancePayment, FinanceGeneralLedger, FinanceBalanceSheet, FinanceAsset, FinanceBudget, FinanceCostCenter | 26 |
| CRM | controller/crm/ (10) | CrmService | CrmOpportunity, CrmFollowRecord, CrmContract, CrmPoolRule, CrmQuotation, CrmCampaign, CrmTicket, CrmAnalyticsReport | 16 |
| Согласование рабочих процессов | controller/workflow/ (2) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | ApprovalWorkflow, ApprovalInstance, ApprovalNode, ApprovalRecord | 4 |
| Уведомления | controller/notification/ (1) | NotificationService ⚠ CRUD пока напрямую, известный технический долг | Notification, NotificationSetting, NotificationTemplate | 3 |
| Управление проектами | controller/project/ (3) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | Project, ProjectTask, ProjectTimesheet, ProjectMember, ProjectGantt | 5 |
| HR | controller/hr/ (5) | HrService | HrDepartment, HrEmployee, HrPosition, HrAttendance, HrLeave, HrSalary | 8 |
| Производство | controller/manufacturing/ (5) | ManufacturingService | MfgBom, MfgProductionOrder, MfgRouting, MfgWorkstation, MfgMrpPlan | 8 |
| Пользовательские отчёты | controller/report/ (2) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | ReportTemplate, ReportDataset, ReportField, ReportFilter, ReportSchedule | 5 |
| EAM (управление оборудованием) | controller/eam/ (4) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | EamEquipment, EamMaintenancePlan, EamRepairOrder, EamSparePart | 4 |
| DMS (управление документами) | controller/dms/ (2) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | DmsCategory, DmsDocument, DmsDocumentVersion | 3 |
| BI-дашборды | controller/bi/ (3) | - ⚠ контроллер напрямую обращается к модели, известный технический долг | BiDashboard, BiWidget | 2 |

### 20.1 Журнал лёгкого выделения сервисного слоя P2-F2 (crm/hr/manufacturing/product уже выделены)

| Модуль | Вызовов контроллером до выделения | После выделения | Новый Service | Что выделено |
|--------|----------------------------------|-----------------|---------------|--------------|
| CRM | 57 | 0 | `app/service/crm/CrmService.php` | Универсальный CRUD + смена статуса договора, предложение→договор, выдача/возврат в общий пул, назначение/решение/ответ по тикетам, каскадная очистка деталей, построение данных аналитических отчётов |
| HR | 38 | 0 | `app/service/hr/HrService.php` | Универсальный CRUD + определение опозданий/ранних уходов, согласование отпусков (автогенерация отпускной посещаемости), уникальность зарплат/расчёт к выдаче/выплата/массовое создание |
| Производство | 33 | 0 | `app/service/manufacturing/ManufacturingService.php` | Универсальный CRUD + переходы «начало/завершение» производственных заказов, копирование версий BOM/взаимоисключение при вводе в действие, генерация деталей MRP |
| Управление товарами | 29 | 0 | `app/service/product/ProductService.php` | Универсальный CRUD + транзакционное создание товара (SKU/цены), обновление с сохранением исходных значений по полям, загрузка связанных данных деталей |

Схема выделения: `app/service/AbstractCrudService.php` предоставляет универсальные CRUD-методы `list/all/find/create/update/delete/deleteWhere`
и вспомогательные функции чистой логики `normalizePageParams/canTransition`; модульные Service наследуют его и аккумулируют специфическую бизнес-логику модуля.
Контроллеры внедряют сервис через `Container::get(XxxService::class)` (с откатом через class_exists), маршруты/параметры/структура ответов полностью неизменны;
кодирование hashid, повторное подтверждение пароля, обёртка ответов и прочие HTTP-задачи остаются в контроллерах.
Новые Service зарегистрированы в `config/dependence.php` (этот файл — dead config, не загружается через addDefinitions; во время выполнения
контейнер полагается на откат через class_exists, поэтому все Service сохраняют конструктор без аргументов).

Модули без выделения (управление проектами — 18 вызовов, пользовательские отчёты — 18, закупки — 24, продажи — 24, системное администрирование — 42 и т. д.)
отмечены в таблице как «контроллер напрямую обращается к модели, известный технический долг»; в следующих итерациях будут выделены по той же схеме.

---

## Расширения OMS/WMS/TMS (2026-08)

### OMS (Order Management System) — 8 таблиц
- **Расширение заказов** (`erp_oms_order`): агрегация по каналам/статус исполнения/статус оплаты/приоритет
- **Адреса заказов** (`erp_oms_order_address`): адреса доставки/оплаты (форматы разных стран)
- **Записи исполнения** (`erp_oms_fulfillment`+`_item`): отслеживание количеств «распределено/собрано/упаковано/отгружено»
- **RMA** (`erp_oms_rma`+`_item`): полный жизненный цикл возвратов/обменов
- **Резервирование остатков** (`erp_oms_inventory_reservation`): ATP = physical − reserved
- **Каналы** (`erp_channel`): direct/marketplace/edi/pos

### WMS (Warehouse Management System) — 12 таблиц
- **Зоны и ячейки** (`erp_wms_zone`, `erp_wms_location`): zone→aisle→rack→level→bin
- **Приёмка** (`erp_wms_asn`+`_item`, `erp_wms_receiving`, `erp_wms_putaway_task`+`_item`)
- **Отгрузка** (`erp_wms_wave`+`wave_order`, `erp_wms_pick_task`+`_item`, `erp_wms_pack_task`)

### TMS (Transport Management System) — 7 таблиц
- **Перевозчики** (`erp_tms_carrier`+`carrier_service`, `erp_tms_freight_rate`)
- **Накладные** (`erp_tms_shipment`+`_package`, `erp_tms_tracking_event`)
- **Счета** (`erp_tms_freight_invoice`)

### Поток данных
```
OMS: Заказ канала → Резервирование остатков (ATP) → Создание исполнения → WMS
WMS: Волна → Сборка → Упаковка → Накладная TMS
TMS: Сравнение тарифов → Отгрузка → Подтверждение (stockOut + AR) → Трекинг → Доставка
WMS Inbound: ASN → Приёмка → Размещение (stockIn + AP)
RMA: Заявка → Одобрение → Возврат → Приёмка (stockIn) → Возмещение
```

---

## 21. Дорожная карта экосистемы (2026-08)

> Детальная спецификация: `superpowers/specs/2026-08-04-erp-ecosystem-roadmap-design.md`

### 21.1 Базовая оценка (на момент запуска дорожной карты)

> P0~P3 полностью сданы, текущая общая оценка 89/100 (см. CLAUDE.md); таблица ниже — базовый снимок до запуска дорожной карты.

| Измерение | Оценка | Ключевые пробелы |
|-----------|--------|------------------|
| Backend API | 85/100 | Многие модули — CRUD-скелеты, не хватает бизнес-движков расчётов |
| Безопасность | 95/100 | 18 уровней защиты, готово к продакшену |
| Frontend UI | 20/100 | **Самое слабое место**: Flutter 12 страниц покрывают ~20% модулей, нет веб-панели администрирования |
| Операционная экосистема | 70/100 | Нет отката миграций, автоматических резервных копий, наблюдаемости |
| Глубина бизнеса | 55/100 | Ключевые алгоритмы финансов/HR/производства не реализованы |
| **Общая** | **65/100** | |

### 21.2 Четырёхэтапная последовательная дорожная карта

```
P0(3-4 недели) → P1(4-6 недель) → P2(1-2 недели) → P3(2-3 недели) = всего около 13 недель
```

| Этап | Название | Ключевые результаты |
|------|----------|---------------------|
| **P0** | Фронтенд-экосистема | Веб-панель администрирования Flutter Web на все модули (14 модулей, 40+ страниц), библиотека общих компонентов, выравнивание с HarmonyOS |
| **P1** | Глубина бизнеса | Движок двойной бухгалтерии, движок расчёта зарплат, движок MRP, модуль управления качеством, уведомления в реальном времени (WebSocket) |
| **P2** | Операционная надёжность | Откат миграций БД, расширенное автобекап, трассировка OpenTelemetry, драйвер очередей RabbitMQ |
| **P3** | Улучшение опыта | BI-дашборды с перетаскиванием, управление оборудованием (EAM), изоляция арендаторов, управление документами (DMS) |

### 21.3 Эволюция цепочки промежуточного ПО

```
Сейчас:   Locale → Cors → SecurityFilter → RateLimit → TracingId → {группа маршрутов}
После P1: Locale → Cors → SecurityFilter → RateLimit → WebSocketUpgrade → {группа маршрутов}
После P2: Locale → Cors → SecurityFilter → RateLimit → TracingId → WebSocketUpgrade → {группа маршрутов}
После P3: Locale → Cors → SecurityFilter → RateLimit → TracingId → TenantScope → WebSocketUpgrade → {группа маршрутов}
```

### 21.4 Целевая архитектура P0 — веб-панель Flutter Web

| Слой | Новое содержимое |
|------|------------------|
| Слой макета | `AdminLayout` — трёхколоночный макет ПК (сворачиваемая боковая панель + верхняя панель + область содержимого) |
| Слой компонентов | `DataTableWrapper`, `FormDialog`, `ConfirmDialog`, `StatCard`, `BreadcrumbNav`, `FileUploader` |
| Слой страниц | Расширение с текущих 12 страниц до полного покрытия 14 модулей, 40+ страниц |
| Слой сервисов | Переиспользование существующих `ApiService`, `AuthService`, `CaptchaService`, `ExportService` |

### 21.5 Целевая архитектура P1 — бизнес-движки расчётов

| Движок | Сервисные классы | Ключевые правила |
|--------|------------------|------------------|
| Двойная бухгалтерия | `DoubleEntryService`, `PeriodCloseService`, `AccountBalanceService` | Принудительная проверка баланса дебета/кредита, перенос результатов периода, пересчёт валют по курсам |
| Расчёт зарплат | `SalaryEngineService`, `SocialInsuranceService`, `HousingFundService`, `TaxCalculatorService` | Верхние/нижние границы базы соцвзносов, доля жилищного фонда, прогрессивная шкала НДФЛ, банковские выплаты |
| MRP | `MrpEngineService`, `BomExplosionService`, `NetRequirementService` | Многоуровневое развёртывание BOM + потери, низкий код уровня (LLC), страховой запас, правила партий |
| Качество | `QmsInspectionService` | Поток трёх форм: IQC входной / IPQC процессный / OQC выходной контроль |
| Уведомления | `WebSocketService`, `ChannelRouter` | Многоканально: внутренние/email/WeCom/钉钉 (DingTalk) |

### 21.6 Сводка изменений модели данных

| Этап | Новых таблиц | Затронутые модули |
|------|--------------|-------------------|
| P0 | 0 | Только фронтенд, изменений таблиц нет |
| P1 | 14 | Финансы (2) + HR (3) + Производство (2) + Качество (5) + Уведомления (2) |
| P3 | 7 | BI (2) + EAM (3) + DMS (2) |

---

## 22. Мультиарендность (зарезервированная возможность, не включена)

> Заявление об авторских правах такое же, как в шапке файла: Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz

### 22.1 Позиционирование и решение

Мультиарендность в этом проекте позиционируется как **зарезервированная возможность**; в текущем цикле она **не подключается и не включается** (документированная деградация). В соответствии с планированием:
SaaS-биллинг, самостоятельное подключение арендаторов и прочие «полные коммерческие решения мультиарендности» не входят в объём данного этапа; на данном этапе сохраняется только минимальный
каркас кода (промежуточное ПО + Trait модели) с описанием шагов включения для последующего включения по мере необходимости.
Примечание: «изоляция арендаторов» из этапа P3 дорожной карты §21.2 в соответствии с этим скорректирована на «зарезервированная возможность (документированная деградация)», каркас сохранён, подключение не выполняется.

Основания решения (рецензия 2026-08):
- Почти все существующие развёртывания — однократные арендаторы; подключение внесёт ненужную сложность изоляции и риски регрессий;
- Текущий каркас имеет технические недостатки (см. 22.4), «подключение = изоляция» не выполняется, требуется сначала завершить исправление проекта;
- Изоляция требует добавления колонки в каждую из 163 бизнес-таблиц и включения для каждой модели — затраты намного превышают «минимальное подключение».

### 22.2 Текущие факты (сверка кода и конфигурации)

| Пункт | Текущее состояние |
|-------|-------------------|
| `app/middleware/TenantScope.php` | Существует, не зарегистрирован; читает арендатора из заголовка `X-Tenant-Id`, при отсутствии заголовка пропускает |
| `app/model/concerns/TenantScope.php` | Существует, ни одна модель не использует; `bootTenantScope()` — глобальная область видимости, фильтрует только после установки арендатора |
| `config/middleware.php` | Глобальная цепочка: Locale → Cors → SecurityFilter → RateLimit → TracingId, без TenantScope |
| `config/route.php` /admin-группа | AdminAuth → AdminPermission → OperationLog, без TenantScope |
| Нагрузка JWT | Только `sub` / `username` / `token_type`, **без объявления tenant_id** (`app/api/v1/controller/AuthController.php`) |
| База данных | **Во всей БД нет колонки tenant_id** (в install.sql тоже нет) |
| Модели | **Ни одна модель не использует Trait TenantScope** |

### 22.3 Шаги включения (справочник, в текущем цикле не выполняются)

1. Зарегистрировать промежуточное ПО: в группе /admin файла `config/route.php` добавить в `middleware()`
   `app\middleware\TenantScope::class` (после AdminAuth, чтобы гарантировать аутентификацию).
2. Запрашивающая сторона передаёт `X-Tenant-Id` в заголовке запроса (int — ID арендатора).
3. Добавить колонку `tenant_id` (BIGINT + индекс) в бизнес-таблицы, требующие изоляции, и заполнить существующие данные;
   словарные/системные таблицы (например, `erp_admin_user`, `erp_role`, `erp_permission`) не изолируются.
4. В моделях, требующих изоляции, подключить `use app\model\concerns\TenantScope;` — автоматическая фильтрация по текущему арендатору.
5. (Опционально) если арендатор должен браться из JWT, а не из заголовка: расширить полезную нагрузку логина, добавив объявление `tenant_id`,
   и в промежуточном ПО читать из `$payload['tenant_id']`.

### 22.4 Известные технические ограничения (обязательно решить до включения)

- **Разрыв статической цепочки передачи (проверено на PHP 8.3)**: вызов `setCurrentTenantId()` из промежуточного ПО через имя trait
   записывает в собственную статическую копию trait, которую модель, использующая этот trait, не видит — запросы не фильтруются.
   При включении нужно перейти на внедрение на основе контекста запроса (например, `request()->tenantId`).
- **Перекрёстные помехи статического глобального состояния**: Workerman — резидентный процесс, статические свойства разделяются между запросами; при включении кооперативного режима
   (Swoole/Swow) возможны перекрёстные помехи данных между арендаторами; нужно перейти на привязку на уровне запроса (`context()` / объект запроса).
- **Пробел на стороне данных**: во всей БД нет колонки tenant_id, требуется миграция по каждой таблице; для словарных таблиц, общих для арендаторов, нужен механизм исключений.

### 22.5 Критерии приёмки

Приёмка текущего цикла = согласованность документации и кода: `config/middleware.php` и `config/route.php` не содержат
регистрации TenantScope; в комментариях промежуточного ПО и Trait явно указано «зарезервированная возможность, не включена» и приведены шаги включения;
описание этого раздела построчно соответствует текущему состоянию кода.
