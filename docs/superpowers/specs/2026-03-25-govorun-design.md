# Говорун — Спецификация дизайна

## Статус реализации

> Последнее обновление: 2026-03-26

| # | Секция | Статус |
|---|--------|--------|
| 1 | Архитектура и структура пакетов | ✅ Готово |
| 2 | Жизненный цикл запроса | ✅ Готово |
| 3 | Система роутинга диалогов | ✅ Готово |
| 4 | Абстракция мессенджеров (Driver) | ✅ Готово (Telegram) |
| 5 | Машина состояний (State) | ✅ Готово |
| 6 | Работа с внешним API | ✅ Готово |
| 7 | Компоненты сообщений (Messaging) | ✅ Готово |
| 8 | Illuminate-компоненты и инфраструктура | ✅ Готово |
| 9 | Обработка ошибок | ✅ Готово |
| 10 | Тестирование | ✅ Готово |

Все компоненты реализованы.

---

## Обзор

Говорун — мультимессенджерный фреймворк для создания диалоговых ботов на PHP 8.3. Архитектура состоит из двух частей: ядро (`govorun/framework`) как Composer-пакет и шаблон бот-проекта (`govorun/skeleton`). Программирование бота не зависит от выбранного мессенджера.

## Ключевые решения

- **PHP 8.3** — баланс свежести и стабильности экосистемы
- **Illuminate-based** — отдельные illuminate-компоненты (database, container, events, cache, console, support, log) для Laravel-подобного опыта без веса всего фреймворка
- **Два репозитория** — `govorun/framework` (ядро) и `govorun/skeleton` (шаблон проекта, `composer create-project`)
- **HTTP API для внешних сервисов** — полная обособленность, подключение к сторонним системам только через REST API
- **Telegram первым** — архитектура мультимессенджерная, но первая реализация — Telegram. Далее: Viber, VK, Instagram, WhatsApp, Facebook Messenger

---

## 1. Архитектура и структура пакетов ✅

### govorun/framework (ядро)

```
/src
    /Console          # Artisan-like команды (migrate, webhook:install, make:controller)
    /Contracts        # Интерфейсы (MessengerDriver, StateStorage, ApiClient)
    /Database         # Миграции ядра (sessions, state), базовые модели
    /Drivers          # Драйверы мессенджеров
        /Telegram
        /Viber
        /...
    /Foundation       # Application, ServiceProvider, bootstrap
    /Http             # Входящие webhook-запросы, middleware
    /Messaging        # Абстракция сообщений (Message, Button, Keyboard, Media)
    /Routing          # Роутер диалогов (10 типов маршрутов)
    /State            # Машина состояний для многошаговых диалогов
    /Support          # Хелперы, трейты
```

### govorun/skeleton (шаблон бот-проекта)

```
/app
    /Controllers      # Контроллеры диалогов
    /Models           # Eloquent-модели
    /Providers        # ServiceProvider'ы
    /Services         # Бизнес-логика, API-клиенты
/bootstrap
    app.php           # Создание и конфигурация Application
/config
    app.php           # Общие настройки
    messenger.php     # Настройки драйверов мессенджеров
    database.php      # Подключение к БД
    api.php           # Настройки внешних API
    state.php         # Настройки машины состояний
    events.php        # Маппинг событий и слушателей
    logging.php       # Настройки логирования
/database
    /migrations       # Миграции бота
/routes
    dialog.php        # Дерево диалогов
/storage
    /logs
    /cache
/.env
/govorun              # CLI точка входа (как artisan)
/webhook.php          # Webhook точка входа
composer.json
```

---

## 2. Жизненный цикл запроса (Request Lifecycle) ✅

```
HTTP POST (от мессенджера)
    │
    ▼
webhook.php
    │  $app = require 'bootstrap/app.php';
    │  $app->handleWebhook();
    │
    ▼
Application::handleWebhook()
    │  1. Загрузка конфигов (glob config/*.php → Repository)
    │  2. Загрузка .env (vlucas/phpdotenv)
    │  3. Boot ServiceProviders
    │  4. Определение драйвера по URL: /webhook/telegram → TelegramDriver
    │
    ▼
Driver::verifyWebhook($request)
    │  Проверка подписи/секрета (автоматически, до любой логики)
    │  Если невалидно → 403, обработка прекращается
    │
    ▼
Driver::parseUpdate($request) → IncomingMessage
    │  Парсинг сырых данных мессенджера в унифицированный объект
    │
    ▼
Global Middleware Pipeline
    │  Логирование, rate limiting и т.д.
    │
    ▼
Flow Check (если есть активный Flow для данного chat+driver)
    │  ├─ Входящее — event или command → Flow приостанавливается, идём в роутер
    │  └─ Иначе → Flow потребляет сообщение, контроллер не вызывается
    │
    ▼
Router::dispatch(IncomingMessage)
    │  Приоритет: event → command → action → referral → media
    │  → location → contact → pattern → phrase → fallback
    │
    ▼
Route Middleware Pipeline
    │  Middleware конкретного маршрута/группы
    │
    ▼
Controller::method()
    │  Бизнес-логика, ответ пользователю
    │
    ▼
Driver::send(OutgoingMessage)
    │  Преобразование в формат API мессенджера, HTTP-запрос
    │
    ▼
HTTP 200 (ответ мессенджеру)
```

### Webhook URL

Каждый драйвер получает свой URL: `/webhook/telegram`, `/webhook/viber`. Определение драйвера — по последнему сегменту URL. Это позволяет обслуживать несколько мессенджеров одновременно в одном бот-проекте.

### Bootstrap

`Foundation/Application` при запуске:
1. Загружает `.env` через `vlucas/phpdotenv`
2. Загружает конфиги: `glob(config_path('*.php'))` → `Illuminate\Config\Repository`
3. Регистрирует и загружает ServiceProvider'ы
4. Регистрирует глобальные хелперы: `config()`, `env()`, `app()`, `event()`, `Log`

---

## 3. Система роутинга диалогов ✅

### 10 типов маршрутов

| Тип | Метод | Описание |
|-----|-------|----------|
| command | `Route::command()` | Команды `/start`, `/help` с параметрами |
| phrase | `Route::phrase()` | Совпадение по подстроке с алиасами |
| pattern | `Route::pattern()` | Regex-паттерны |
| action | `Route::action()` | Callback от кнопок |
| event | `Route::event()` | Lifecycle-события (subscribe, member_join и т.д.) |
| media | `Route::media()` | Входящие медиа по типу (photo, video, voice, sticker...) |
| location | `Route::location()` | Геолокация |
| contact | `Route::contact()` | Контакт |
| referral | `Route::referral()` | Deep-ссылки |
| fallback | `Route::fallback()` | Ничего не совпало |

### Приоритет обработки

```
event → command → action → referral → media → location → contact → pattern → phrase → fallback
```

### Пример маршрутов

```php
use Govorun\Routing\Route;

Route::command('start', StartController::class);

Route::phrase('запись', function () {
    Route::phrase('моя', [RecordController::class, 'my'])
        ->alias(['мои', 'последняя']);
    Route::phrase('отменить', [RecordController::class, 'delete'])
        ->alias(['удалить']);
})->alias(['записаться']);

Route::pattern('/^привет/ui', GreetingController::class);
Route::action('confirm_order', [OrderController::class, 'confirm']);
Route::event('subscribe', WelcomeController::class);
Route::media('photo', PhotoController::class);
Route::location(LocationController::class);
Route::contact(ContactController::class);
Route::referral('promo', PromoController::class);
Route::fallback(DefaultController::class);
```

### Вложенность через closures

Closure-based нестинг со стек-механизмом, неограниченная глубина.

**Алгоритм сопоставления вложенных phrase-маршрутов:**
1. Роутер проверяет текст сообщения на `str_contains()` по имени фразы и её алиасам
2. Если совпало и action — массив подроутов (closure), рекурсивно ищет совпадение во вложенных фразах
3. Если вложенное совпадение найдено — вызывает вложенный контроллер
4. Если вложенного совпадения нет, но у родительской фразы есть fallback-контроллер — вызывает его
5. Алиасы на родительской группе работают как альтернативы имени группы (не как префиксы)

### Контроллер

```php
class RecordController extends Controller
{
    public function my()
    {
        $this->reply('Ваши записи:');
        $this->send(
            Message::make('Выберите действие')
                ->keyboard(
                    Keyboard::make()
                        ->button('Отменить', action: 'cancel_record')
                        ->button('Перенести', action: 'reschedule')
                )
        );
    }
}
```

Базовый `Controller` предоставляет:
- `reply(string $text)` — быстрый текстовый ответ
- `send(OutgoingMessage $message)` — отправка сообщения с клавиатурой/медиа
- `edit(OutgoingMessage $message)` — редактирование предыдущего сообщения (например, обновление клавиатуры после нажатия кнопки)
- `deleteLastMessage()` — удаление предыдущего сообщения
- `user(): UserDto` — данные текущего пользователя
- `message(): IncomingMessage` — входящее сообщение
- `state(): StateManager` — доступ к машине состояний
- `param(string $key): ?string` — получение параметров action-кнопки

### Доступ к параметрам action-кнопок

Кнопка определена как:
```php
->button('Маникюр', action: 'service', param: ['type' => 'manicure'])
```

В контроллере action-маршрута:
```php
Route::action('service', [ServiceController::class, 'select']);

// В контроллере:
public function select()
{
    $type = $this->param('type'); // 'manicure'
}
```

Формат хранения callback_data: `act:service;type:manicure`. Драйвер парсит это в структурированный объект.

---

## 4. Абстракция мессенджеров (Driver-система) ✅

### Интерфейс драйвера

```php
interface MessengerDriver
{
    public function verifyWebhook(Request $request): bool;
    public function parseUpdate(Request $request): IncomingMessage;
    public function send(OutgoingMessage $message): void;
    public function edit(string $messageId, OutgoingMessage $message): void;
    public function delete(string $messageId, string $chatId): void;
    public function installWebhook(string $url): bool;
    public function removeWebhook(): bool;
    public function getUser(string $id): UserDto;
}
```

Единый метод `send()` — принимает `OutgoingMessage`, который может содержать текст, медиа, клавиатуру или любую комбинацию. Билдеры `Message::make()` и `Media::photo()` создают один и тот же тип `OutgoingMessage`.

### Унифицированный IncomingMessage

```php
class IncomingMessage
{
    public string $id;
    public string $chatId;
    public string $driverName;      // 'telegram', 'viber' и т.д.
    public ?string $text;
    public UserDto $user;
    public ContentType $type;        // text, action, media, location, contact, event
    public ?string $action;          // callback от кнопки (парсится роутером)
    public ?array $actionParams;     // параметры action-кнопки
    public ?string $event;           // subscribe, member_join и т.д.
    public ?MediaDto $media;
    public ?LocationDto $location;
    public ?ContactDto $contact;
    public ?string $referral;
    public array $raw;               // оригинальные данные мессенджера
}
```

`ContentType` — тип контента от мессенджера (text, action, media, location, contact, event). `action` — нажатие inline-кнопки (callback query). Определение команд, фраз, паттернов — задача роутера, не драйвера.

### UserDto

```php
class UserDto
{
    public string $id;               // ID в мессенджере
    public ?string $firstName;
    public ?string $lastName;
    public ?string $username;
    public ?string $phone;
    public ?string $locale;
    public array $raw;               // оригинальные данные
}
```

### Конфигурация (мульти-драйвер)

```php
// config/messenger.php
return [

    /*
    |--------------------------------------------------------------------------
    | Активные драйверы мессенджеров
    |--------------------------------------------------------------------------
    |
    | Список драйверов, для которых фреймворк будет регистрировать
    | webhook-эндпоинты и обрабатывать входящие сообщения.
    | Каждый драйвер получает свой URL: /webhook/{driver}
    |
    | Для одновременной работы нескольких мессенджеров перечислите
    | их через запятую в .env: MESSENGER_DRIVERS=telegram,viber
    |
    */

    'drivers' => explode(',', env('MESSENGER_DRIVERS', 'telegram')),

    /*
    |--------------------------------------------------------------------------
    | Telegram
    |--------------------------------------------------------------------------
    |
    | Настройки для работы с Telegram Bot API.
    | Токен получается у @BotFather, secret используется для верификации
    | входящих webhook-запросов (X-Telegram-Bot-Api-Secret-Token).
    |
    */

    'telegram' => [
        'token'  => env('TELEGRAM_TOKEN'),
        'secret' => env('TELEGRAM_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Viber
    |--------------------------------------------------------------------------
    |
    | Настройки для работы с Viber REST API.
    | Токен выдаётся при создании бота в Viber Admin Panel.
    |
    */

    'viber' => [
        'token' => env('VIBER_TOKEN'),
    ],

];
```

Каждый активный драйвер получает свой webhook-endpoint.

### Graceful degradation

Если драйвер не поддерживает фичу (например, Viber не поддерживает markdown), он деградирует тихо — отправляет plain text вместо ошибки.

---

## 5. Машина состояний (State Management) ⚠️

### Идентификация сессии ✅

Ключ сессии: `chatId + driverName`. Один физический пользователь в Telegram и Viber — это две разные сессии. Это предотвращает конфликты и соответствует реальности (разные мессенджеры — разные возможности).

### Схема БД ✅

```sql
-- Таблица состояний Flow
CREATE TABLE govorun_states (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id VARCHAR(255) NOT NULL,
    driver VARCHAR(50) NOT NULL,
    flow_class VARCHAR(255) NOT NULL,
    current_step VARCHAR(100) NOT NULL,
    data JSON NOT NULL DEFAULT '{}',
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_session (chat_id, driver),
    INDEX idx_expires (expires_at)
);
```

### Flow-класс для многошаговых сценариев ✅

```php
class AppointmentFlow extends Flow
{
    protected array $steps = ['service', 'date', 'time', 'confirm'];

    // Какие команды прерывают Flow (по умолчанию: /start, /cancel)
    protected array $interruptCommands = ['/start', '/cancel'];

    // Прерывать ли Flow при совпадении event-маршрутов (по умолчанию: true)
    protected bool $interruptOnEvent = true;

    public function serviceStep(Step $step): void
    {
        $step->ask('Выберите услугу:', function () {
            return Keyboard::make()
                ->button('Маникюр', action: 'service', param: ['type' => 'manicure'])
                ->button('Педикюр', action: 'service', param: ['type' => 'pedicure']);
        });

        // $message — полный IncomingMessage, доступны action, params, text
        $step->receive(function (IncomingMessage $message) {
            // Если нажата кнопка — берём param
            $type = $message->actionParams['type'] ?? $message->text;
            $this->state->set('service', $type);
            $this->nextStep();
        });
    }

    // ... остальные шаги

    public function onComplete(): void
    {
        $this->reply('Записано!');
    }

    public function onCancel(): void
    {
        $this->reply('Запись отменена.');
    }
}
```

### Интеграция Flow с роутером ✅

Место Flow в цепочке обработки:

```
IncomingMessage → Global Middleware → Flow Check → Router
```

Логика Flow Check:
1. Есть ли активный Flow для `chatId + driverName`?
2. Если нет — передаём в роутер
3. Если да — проверяем, должен ли Flow быть прерван:
   - Входящее — event → прерываем (если `interruptOnEvent = true`), передаём в роутер
   - Входящее — command из `interruptCommands` → прерываем, передаём в роутер
   - Иначе → Flow потребляет сообщение, роутер не вызывается

### Запуск из контроллера

```php
$this->startFlow(AppointmentFlow::class);
```

### Хранение состояния ✅

```php
// config/state.php
return [

    /*
    |--------------------------------------------------------------------------
    | Драйвер хранения состояний
    |--------------------------------------------------------------------------
    |
    | Определяет, где фреймворк хранит состояния Flow-диалогов.
    | Поддерживаемые драйверы: "database", "cache", "file"
    |
    | "database" — надёжно, переживает перезапуски (рекомендуется)
    | "cache"    — быстро, но состояния могут быть утеряны при очистке кэша
    | "file"     — для локальной разработки, хранит в storage/state
    |
    */

    'driver' => env('STATE_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Время жизни состояния (TTL)
    |--------------------------------------------------------------------------
    |
    | Максимальное время в секундах, в течение которого состояние Flow
    | считается активным. По истечении — Flow автоматически завершается
    | и пользователь начинает диалог заново.
    | Установите null для бессрочного хранения.
    |
    */

    'ttl' => env('STATE_TTL', 3600),

];
```

---

## 6. Работа с внешним API ✅

### ApiClient — базовый класс

```php
abstract class ApiClient
{
    protected function get(string $uri, array $params = []): mixed;
    protected function post(string $uri, array $data = []): mixed;
    protected function put(string $uri, array $data = []): mixed;
    protected function delete(string $uri): mixed;

    protected int $timeout = 5;
    protected int $retries = 0;
    protected function handleError(RequestException $e): void;
}
```

### Конкретный API-клиент

```php
class RecordApiClient extends ApiClient
{
    protected function baseUrl(): string
    {
        return config('api.records.url');
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer ' . config('api.records.token')];
    }

    public function getUserRecords(string $phone): array
    {
        return $this->get('/records', ['phone' => $phone]);
    }
}
```

### Использование через DI

```php
class RecordController extends Controller
{
    public function __construct(private RecordApiClient $api) {}

    public function my()
    {
        $records = $this->api->getUserRecords($this->user()->phone);
        // ...
    }
}
```

HTTP-клиент: Guzzle под капотом с таймаутами, ретраями и fallback-ответами.

### Конфигурация внешних API

```php
// config/api.php
return [

    /*
    |--------------------------------------------------------------------------
    | Внешние API-сервисы
    |--------------------------------------------------------------------------
    |
    | Настройки подключения к внешним REST API. Каждый ключ — логическое
    | имя сервиса, используемое в ApiClient-наследниках через
    | config('api.service_name').
    |
    | Пример использования в клиенте:
    |   protected function baseUrl(): string {
    |       return config('api.records.url');
    |   }
    |
    */

    'records' => [
        'url'     => env('API_RECORDS_URL', 'https://api.example.com'),
        'token'   => env('API_RECORDS_TOKEN'),
        'timeout' => 5,
    ],

    // 'crm' => [
    //     'url'     => env('API_CRM_URL'),
    //     'token'   => env('API_CRM_TOKEN'),
    //     'timeout' => 10,
    // ],

];
```

---

## 7. Компоненты сообщений (Messaging) ✅

Все билдеры создают `OutgoingMessage` — единый тип, который принимает драйвер.

### Message

```php
Message::make('Текст')
    ->keyboard(
        Keyboard::make()
            ->button('Кнопка', action: 'btn')
            ->row()
            ->button('Ссылка', url: 'https://...')
    )
    ->parseMode('markdown');
```

### Media

```php
Media::photo('https://example.com/img.jpg')->caption('Описание');
Media::document('/path/to/file.pdf')->caption('Документ');
Media::voice('/path/to/audio.ogg');
```

### Keyboard — два типа

```php
// Inline-кнопки (под сообщением)
Keyboard::make()->button('Нажми', action: 'click');

// Reply-кнопки (вместо клавиатуры)
Keyboard::reply()
    ->button('Отправить номер', requestContact: true)
    ->button('Отправить локацию', requestLocation: true);

// Убрать клавиатуру
Keyboard::remove();
```

### Button — параметры

```php
->button('Текст', action: 'name', param: ['key' => 'value'])  // callback
->button('На сайт', url: 'https://...')                         // URL
->button('Телефон', requestContact: true)                       // запрос контакта
```

---

## 8. Illuminate-компоненты и инфраструктура ⚠️

### Конфигурация приложения ✅

```php
// config/app.php
return [

    /*
    |--------------------------------------------------------------------------
    | Имя приложения
    |--------------------------------------------------------------------------
    |
    | Используется в логах, консольных командах и уведомлениях
    | администраторам. Не отображается пользователям мессенджера.
    |
    */

    'name' => env('APP_NAME', 'Govorun Bot'),

    /*
    |--------------------------------------------------------------------------
    | Окружение приложения
    |--------------------------------------------------------------------------
    |
    | Текущее окружение. Влияет на уровень логирования,
    | детализацию ошибок и поведение кэширования.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Режим отладки
    |--------------------------------------------------------------------------
    |
    | В режиме отладки ошибки логируются с полным стектрейсом.
    | В production-окружении ОБЯЗАТЕЛЬНО должен быть false.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | URL приложения
    |--------------------------------------------------------------------------
    |
    | Базовый URL, на котором работает бот. Используется при установке
    | webhook'ов и формировании абсолютных ссылок.
    |
    */

    'url' => env('APP_URL', 'https://example.com'),

    /*
    |--------------------------------------------------------------------------
    | Часовой пояс
    |--------------------------------------------------------------------------
    |
    | Часовой пояс по умолчанию. Влияет на хранение дат в БД,
    | логи и работу с временными метками.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'UTC'),

    /*
    |--------------------------------------------------------------------------
    | Сообщение об ошибке для пользователя
    |--------------------------------------------------------------------------
    |
    | Текст, который отправляется пользователю в мессенджер при
    | необработанном исключении в контроллере. Должен быть
    | дружелюбным и не раскрывать технических деталей.
    |
    */

    'error_message' => 'Произошла ошибка, попробуйте позже.',

    /*
    |--------------------------------------------------------------------------
    | Провайдеры сервисов
    |--------------------------------------------------------------------------
    |
    | Список ServiceProvider'ов, загружаемых при старте приложения.
    | Фреймворк загружает свои провайдеры автоматически,
    | здесь перечислены провайдеры бот-проекта.
    |
    */

    'providers' => [
        App\Providers\AppServiceProvider::class,
    ],

];
```

### Конфигурация базы данных ✅

```php
// config/database.php
return [

    /*
    |--------------------------------------------------------------------------
    | Соединение по умолчанию
    |--------------------------------------------------------------------------
    |
    | Имя соединения, используемого Eloquent и Query Builder
    | при вызовах без явного указания соединения.
    |
    */

    'default' => env('DB_CONNECTION', 'mysql'),

    /*
    |--------------------------------------------------------------------------
    | Соединения с базами данных
    |--------------------------------------------------------------------------
    |
    | Настройки каждого соединения. Поддерживаются: mysql, pgsql, sqlite.
    | Все параметры рекомендуется выносить в .env файл.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'govorun'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'govorun'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Таблица миграций
    |--------------------------------------------------------------------------
    |
    | Имя таблицы, в которой Eloquent хранит историю
    | выполненных миграций.
    |
    */

    'migrations' => 'migrations',

];
```

### Используемые пакеты ✅

- `illuminate/database` — Eloquent ORM, Query Builder, миграции
- `illuminate/container` — Service Container (DI)
- `illuminate/events` — система событий
- `illuminate/cache` — кэширование
- `illuminate/console` — Artisan-like команды
- `illuminate/log` — логирование (обёртка над Monolog)
- `illuminate/support` — коллекции, хелперы
- `vlucas/phpdotenv` — загрузка .env файлов

### Service Container (DI) ✅

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RecordApiClient::class, function ($app) {
            return new RecordApiClient(config('api.records'));
        });
    }
}
```

### Console-команды ✅

```bash
php govorun webhook:install       # Установить webhook
php govorun webhook:remove        # Удалить webhook
php govorun migrate               # Миграции
php govorun make:controller Name  # Генерация контроллера
php govorun make:flow Name        # Генерация Flow
php govorun make:api-client Name  # Генерация API-клиента
php govorun state:clear           # Очистить состояния
php govorun test                  # Запуск тестов
```

### Events ✅

```php
event(new RecordCreated($user, $record));
```

```php
// config/events.php
return [

    /*
    |--------------------------------------------------------------------------
    | Маппинг событий и слушателей
    |--------------------------------------------------------------------------
    |
    | Здесь регистрируются все события приложения и их слушатели.
    | Ключ — полное имя класса события, значение — массив классов-слушателей,
    | которые будут вызваны при срабатывании события.
    |
    | Слушатели вызываются в порядке перечисления.
    |
    */

    App\Events\RecordCreated::class => [
        App\Listeners\SendAdminNotification::class,
    ],

];
```

### Middleware ✅

```php
Route::middleware(AuthorizedUser::class, function () {
    Route::command('profile', ProfileController::class);
    Route::phrase('запись', RecordController::class);
});
```

### Логирование ✅

```php
// config/logging.php
return [

    /*
    |--------------------------------------------------------------------------
    | Канал логирования по умолчанию
    |--------------------------------------------------------------------------
    |
    | Канал, используемый при вызове Log::info() и подобных методов
    | без явного указания канала.
    |
    */

    'default' => env('LOG_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Каналы логирования
    |--------------------------------------------------------------------------
    |
    | Настройки каждого канала. Поддерживаемые драйверы:
    | "single" — один файл, "daily" — файл по дням с ротацией,
    | "stack" — комбинация нескольких каналов.
    |
    */

    'channels' => [

        'stack' => [
            'driver'   => 'stack',
            'channels' => ['single'],
        ],

        'single' => [
            'driver' => 'single',
            'path'   => storage_path('logs/govorun.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
        ],

        'daily' => [
            'driver' => 'daily',
            'path'   => storage_path('logs/govorun.log'),
            'level'  => env('LOG_LEVEL', 'debug'),
            'days'   => 14,
        ],

    ],

];
```

Использование:
```php
Log::info('Новая запись', ['user' => $user->id]);
Log::error('API недоступен', ['url' => $url]);
```

---

## 9. Обработка ошибок ✅

### Ошибки драйвера (отправка сообщений)

- Если `send()` падает с ошибкой сети/API → логируем, бросаем `SendFailedException`
- Контроллер может обработать через try/catch, или фреймворк поймает глобально

### Ошибки контроллера (исключения)

Глобальный обработчик в `Application`:
1. Логирует исключение с полным контекстом (user, message, driver)
2. Отправляет пользователю fallback-сообщение: `"Произошла ошибка, попробуйте позже"`
3. Текст fallback-сообщения настраивается в `config/app.php`

### Ошибки внешнего API

- `ApiClient::handleError()` — переопределяется в конкретном клиенте
- По умолчанию: логирует и бросает `ApiException`
- Контроллер решает, как ответить пользователю

### Таймауты webhook

Обработка webhook должна завершаться быстро (Telegram даёт ~60с, другие мессенджеры — меньше). В v1 вся обработка синхронная. Рекомендация для тяжёлых задач — вынести вызов внешнего API в контроллере с таймаутом 5с и fallback-ответом при превышении.

---

## 10. Тестирование ⬜

### Фейковый мессенджер

```php
public function test_full_appointment_flow(): void
{
    $this->fakeMessenger()
        ->receive('/start')
        ->assertReply('Добро пожаловать!')
        ->receive('записаться')
        ->assertReply('Выберите услугу:')
        ->clickButton('Маникюр')
        ->assertReply('Выберите дату:')
        ->receive('30.04.2026')
        ->assertReplyContains('Подтверждаете')
        ->clickButton('Да')
        ->assertReply('Записано!');
}
```

### Фейк внешнего API

```php
public function test_shows_user_records(): void
{
    $this->fakeApi(RecordApiClient::class)
        ->mockGet('/records', [
            ['id' => 1, 'service' => 'Маникюр', 'date' => '2026-04-01'],
        ]);

    $this->fakeMessenger()
        ->receive('мои записи')
        ->assertReplyContains('Маникюр');
}
```

### Инструмент

PHPUnit, запуск через `php govorun test`.

---

## Технологический стек

| Компонент | Технология |
|-----------|-----------|
| Язык | PHP 8.3 |
| ORM | Eloquent (illuminate/database) |
| DI | illuminate/container |
| События | illuminate/events |
| Кэш | illuminate/cache |
| CLI | illuminate/console |
| Логирование | illuminate/log (Monolog) |
| HTTP-клиент | Guzzle 7 |
| Env | vlucas/phpdotenv |
| Тесты | PHPUnit |
| Пакетный менеджер | Composer |
