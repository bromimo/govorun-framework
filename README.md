# Govorun Framework

Мульти-мессенджер бот-фреймворк на PHP 8.3+. Один код — разные мессенджеры (на сегодня поддерживается Telegram; интерфейсы готовы под Viber/WhatsApp). Используется визуальным билдером [`govorun-factory`](https://github.com/bromimo/govorun-factory) как целевой рантайм для сгенерированных проектов.

> **v3.x (breaking, краткая шпаргалка по миграции с v1.x):**
> - `Keyboard::button()` / `->row()` удалены. Только `Keyboard::make()->buttons([[Button::make('…')->action('…'), …], …])`.
> - `Button::make(...)` + fluent: `->action()`, `->url()`, `->requestContact()`, `->requestLocation()`.
> - `Keyboard::reply()->resize()->oneTime()` — fluent-флаги для reply-клавиатуры.
> - `Step::ask(string|OutgoingMessage $msg, Closure|Keyboard|null $keyboard)` — клавиатуру можно передавать напрямую.
> - В `Controller` и `Flow` подмешан трейт `MakesHttpCalls` — `$this->http()->connection('slug')->...`.

## Быстрый старт

```bash
composer create-project govorun/skeleton my-bot
cd my-bot
```

Положите токен в `.env`:

```env
TELEGRAM_BOT_TOKEN=your-token
```

Установите вебхук:

```bash
php govorun webhook:install
```

Подробный пользовательский гид — [`govorun-skeleton`](https://github.com/bromimo/govorun-skeleton). Если бот сгенерирован фабрикой — просто разверните ZIP, `composer install`, заполните `.env` и `php govorun webhook:install`.

---

## Архитектура

### Жизненный цикл запроса

```
HTTP POST → public/index.php → Application::handleWebhook()
  1. loadEnvironment()             — загрузка .env
  2. loadConfiguration()           — загрузка config/*.php
  3. registerCoreProviders()       — EventServiceProvider, LogServiceProvider, StateServiceProvider
  4. registerConfiguredProviders() — провайдеры из config('app.providers')
  5. boot()                        — boot всех провайдеров
  6. loadRoutes()                  — routes/messenger.php
  7. resolveDriverName()           — драйвер из URL
  8. resolveDriver()               — экземпляр драйвера
  9. verifyWebhook()               — проверка подписи
 10. parseUpdate()                 — парсинг в IncomingMessage
 11. FlowHandler::handle()         — возобновление активного Flow (если есть)
 12. Router::dispatch()            — диспатч в Controller или Flow-старт
```

### Структура пакета

```
src/
├── Console/            CLI: make:controller, make:flow, make:api-client,
│                       migrate, state:clear, test, webhook:install/remove,
│                       bot:profile-sync
├── Contracts/          MessengerDriver, StateStorage, StateAccessor
├── Database/Migrations CreateGovorunStatesTable
├── Drivers/Telegram/   TelegramDriver (URL и локальный файл через multipart)
├── Events/             EventServiceProvider
├── Exceptions/         SendFailedException, ApiException
├── Foundation/         Application (Illuminate Container), ServiceProvider
├── Http/               Request, ApiClient (abstract), HttpManager,
│                       ConnectionClient, HttpResponse, MakesHttpCalls trait
├── Log/                LogServiceProvider, Log facade
├── Messaging/          IncomingMessage, OutgoingMessage, Message, Button,
│                       Keyboard, Media + Dto/{User,Media,Location,Contact}
├── Routing/            Route, Router, Controller, Middleware, MiddlewarePipeline
├── State/              Flow, Step, FlowHandler, StateData, PersistentState,
│                       File/Database/CacheStateStorage, StateServiceProvider
├── Support/            helpers.php, Validator
└── Testing/            TestCase, FakeMessenger, FakeDriver, FakeApiClient, traits
```

---

## Application

Ядро. Наследует `Illuminate\Container\Container`.

```php
$app = new Application(dirname(__DIR__));
```

| Метод | Описание |
|-------|----------|
| `basePath($path)` | Базовый путь проекта |
| `configPath($path)` | Путь к `config/` |
| `storagePath($path)` | Путь к `storage/` |
| `databasePath($path)` | Путь к `database/` |
| `register(ServiceProvider)` | Зарегистрировать провайдер |
| `boot()` | Загрузить все провайдеры |
| `handleConsole()` | Обработать CLI-запрос |
| `handleWebhook(Request)` | Обработать вебхук |
| `loadRoutes()` | Загрузить `routes/messenger.php` |

---

## Маршрутизация

### DSL

```php
use Govorun\Routing\Route;

Route::command('start', StartController::class);
Route::phrase('привет', HelloController::class);
Route::pattern('/^\d+$/', NumberController::class);
Route::action('confirm', ConfirmController::class);
Route::event('member_joined', WelcomeController::class);
Route::media('photo', PhotoController::class);
Route::location(LocationController::class);
Route::contact(ContactController::class);
Route::referral('promo', PromoController::class);
Route::fallback(FallbackController::class);
```

`Route::command('start', ...)` нормализуется к `/start` — слеш можно опускать.

### Приоритет

`event` > `command` > `action` > `referral` > `media` > `location` > `contact` > `pattern` > `phrase` > `fallback`

### Middleware

```php
Route::middleware(AuthMiddleware::class, function () {
    Route::command('admin', AdminController::class);
});
```

### Вложенные phrase

```php
Route::phrase('меню', function () {
    Route::phrase('цены', PriceController::class);
    Route::phrase('контакты', ContactInfoController::class);
});
```

### Алиасы

```php
Route::phrase('привет', HelloController::class)
    ->alias(['здравствуйте', 'добрый день']);
```

---

## Controller

Базовый класс контроллера бота.

```php
use Govorun\Routing\Controller;

class StartController extends Controller
{
    public function handle(): void
    {
        $name = $this->message->user->firstName;
        $this->reply("Привет, {$name}!");
    }
}
```

`handle()` вызывается без аргументов; альтернатива — `__invoke()`.

| Свойство / метод | Тип | Описание |
|---|---|---|
| `$this->message` | `IncomingMessage` | Входящее сообщение (предпочтительный доступ; `$this->incomingMessage` — deprecated алиас) |
| `$this->driver` | `MessengerDriver` | Драйвер мессенджера |
| `$this->state` | `StateAccessor` | `PersistentState` (write-through в storage), при отсутствии storage — пустая `StateData` |
| `reply(string $text)` | `void` | Отправить текстовый ответ |
| `send(OutgoingMessage $msg)` | `void` | Отправить сообщение (с клавиатурой/медиа) |
| `user()` | `UserDto` | Данные отправителя |
| `param(string $key)` | `?string` | Параметр callback-действия (для `Route::action()`) |
| `startFlow(string $class)` | `void` | Запустить Flow-диалог |
| `http()` (через `MakesHttpCalls`) | `HttpManager` | Доступ к подключениям (`$this->http()->connection('slug')->...`) |

### Auto-finalize inline-клавиатуры

При отправке через `send()` сообщения с inline-клавиатурой, содержащей `action`-кнопки, контроллер сохраняет в storage контекст (`message_id`, `original_text`, `parse_mode`, текстовые лейблы кнопок). На следующем Action-сообщении исходное сообщение редактируется — клавиатура убирается, к тексту дописывается `(выбрано: <label>)`. Ошибки `driver->edit()` не пробрасываются — это «вежливая» финализация.

Reply-клавиатуры, `Keyboard::remove()` и кнопки без `action` (только URL/requestContact/requestLocation) контекст не пишут.

---

## Messaging

### IncomingMessage

| Свойство | Тип | Описание |
|---|---|---|
| `id` | `string` | ID сообщения |
| `chatId` | `string` | ID чата |
| `driverName` | `string` | Имя драйвера (`telegram`, …) |
| `text` | `?string` | Текст |
| `user` | `UserDto` | Отправитель |
| `type` | `ContentType` | Тип контента |
| `action` | `?string` | Callback-action |
| `actionParams` | `?array` | Параметры action |
| `event` | `?string` | Имя события |
| `media` | `?MediaDto` | Медиа |
| `location` | `?LocationDto` | Геолокация |
| `contact` | `?ContactDto` | Контакт |
| `referral` | `?string` | Реферальный код |
| `raw` | `array` | Сырое тело апдейта |

### ContentType (enum)

`Text` | `Action` | `Media` | `Location` | `Contact` | `Event`.

### OutgoingMessage

```php
use Govorun\Messaging\Message;

$msg = Message::make('Текст')
    ->keyboard($keyboard)
    ->parseMode('HTML');
```

### Keyboard

```php
use Govorun\Messaging\Button;
use Govorun\Messaging\Keyboard;

// Inline
Keyboard::make()->buttons([
    [
        Button::make('Текст')->action('confirm', ['id' => 42]),
        Button::make('Ссылка')->url('https://example.com'),
    ],
    [Button::make('Новый ряд')->action('continue')],
]);

// Reply (с fluent-флагами)
Keyboard::reply()
    ->resize()
    ->oneTime()
    ->buttons([
        [Button::make('Контакт')->requestContact()],
        [Button::make('Локация')->requestLocation()],
    ]);

// Снять клавиатуру
Keyboard::remove();
```

### Media

```php
use Govorun\Messaging\Media;

Media::photo('https://example.com/img.jpg')->caption('Описание');
Media::document('https://example.com/file.pdf');
Media::video('https://example.com/clip.mp4');
Media::audio('https://example.com/track.mp3');
Media::voice('https://example.com/voice.ogg');
Media::animation('https://example.com/anim.gif');
```

Telegram-драйвер дополнительно умеет отправлять **локальные файлы** — если в `OutgoingMessage::$media['url']` лежит существующий локальный путь (а не URL), используется multipart-upload через Bot API.

### Button

```php
Button::make('Текст')
    ->action('callback_action', ['key' => 'value'])
    ->url('https://...')
    ->requestContact()
    ->requestLocation();
```

---

## DTO

### UserDto

| Свойство | Тип |
|---|---|
| `id` | `string` |
| `firstName` | `?string` |
| `lastName` | `?string` |
| `username` | `?string` |
| `phone` | `?string` |
| `locale` | `?string` |
| `raw` | `array` |

### MediaDto / LocationDto / ContactDto

`MediaDto`: `type`, `url`, `fileId`, `mimeType`, `fileSize`, `raw`.
`LocationDto`: `latitude`, `longitude`, `raw`.
`ContactDto`: `phone`, `firstName`, `lastName`, `userId`, `raw`.

---

## Flow (пошаговые диалоги)

Многошаговый диалог. Состояние сохраняется между шагами в `StateStorage`.

```php
use Govorun\State\Flow;
use Govorun\State\Step;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\Button;
use Govorun\Messaging\IncomingMessage;

class OrderFlow extends Flow
{
    protected array $steps = ['product', 'quantity', 'confirm'];
    protected array $interruptCommands = ['/start', '/cancel'];
    protected bool $interruptOnEvent = true;

    public function productStep(Step $step): void
    {
        $step->ask('Какой товар вас интересует?');

        $step->receive(function (IncomingMessage $msg) {
            if ($this->validator($msg->text)->required()->fails()) {
                return; // ошибка уже отправлена пользователю
            }

            $this->state->set('product', $msg->text);
            $this->nextStep();
        });
    }

    public function quantityStep(Step $step): void
    {
        $step->ask('Сколько штук?');

        $step->receive(function (IncomingMessage $msg) {
            $this->state->set('quantity', (int) $msg->text);
            $this->nextStep();
        });
    }

    public function confirmStep(Step $step): void
    {
        $product = $this->state->get('product');
        $qty = $this->state->get('quantity');

        $step->ask(
            "Заказ: {$product} x {$qty}. Подтвердить?",
            Keyboard::make()->buttons([[
                Button::make('Да')->action('yes'),
                Button::make('Нет')->action('no'),
            ]]),
        );

        $step->receive(function (IncomingMessage $msg) {
            if ($msg->action === 'yes') {
                $this->reply('Заказ принят!');
            }
            $this->nextStep(); // обязателен — завершает flow и чистит state
        });
    }

    public function onComplete(): void {}
    public function onCancel(): void { $this->reply('Заказ отменён.'); }
}
```

### Свойства

| Свойство | Тип | Описание |
|---|---|---|
| `$steps` | `array` | Имена шагов в порядке выполнения |
| `$interruptCommands` | `array` | Команды, прерывающие flow |
| `$interruptOnEvent` | `bool` | Прерывать при событии |
| `$state` | `StateData` | In-memory данные шагов (сохраняются после ask/receive) |

### Методы

| Метод | Описание |
|---|---|
| `start()` | Запуск с первого шага |
| `resume()` | Возобновление текущего шага (вызывает `FlowHandler`) |
| `nextStep(?string $name)` | Переход. Без аргумента — следующий по `$steps`; с именем — прыжок (`goTo`). Если текущий последний — `completeFlow()` |
| `reply(string $text)` | Текстовый ответ |
| `send(OutgoingMessage $msg)` | Отправка сложного сообщения |
| `validator(?string $value)` | Создать `Validator` с автоматической отправкой ошибки пользователю |
| `http()` | `HttpManager` (через трейт `MakesHttpCalls`) |
| `onComplete()` / `onCancel()` | Хуки |

### Step

```php
$step->ask(
    string|OutgoingMessage $message,
    Closure|Keyboard|null  $keyboard = null,  // прямой Keyboard или Closure-билдер
);

$step->receive(Closure $callback);  // function (IncomingMessage $msg): void
```

Клавиатура-`Closure` исполняется в bind'е Flow, поэтому имеет доступ к `$this->state`.

### Прерывание

`shouldInterrupt(IncomingMessage)` возвращает true когда:

1. Активна `ask_keyboard` (есть `__ask_keyboard_ctx` в state) и пришёл текст — это значит пользователь не нажал кнопку, а написал что-то ещё. События в этом режиме не прерывают.
2. `$interruptOnEvent === true` и пришло событие.
3. Текст совпадает с одной из `$interruptCommands` (или начинается на `<cmd> `).

### Auto-finalize ask_keyboard

Если в шаге задана inline-клавиатура с action-кнопками, при отправке `ask` контекст сохраняется в `state.__ask_keyboard_ctx`. На `nextStep()` / `onCancel()` исходное сообщение редактируется: `(выбрано: <label>)` или `(отменено)`. При прерывании по команде до выбора — финализация с `(отменено)`.

---

## State Storage

```php
interface StateStorage
{
    public function get(string $chatId, string $driver): ?array;
    public function set(string $chatId, string $driver, array $data): void;
    public function delete(string $chatId, string $driver): void;
}
```

| Класс | Описание |
|---|---|
| `FileStateStorage` | JSON-файлы в `storage/state/` |
| `DatabaseStateStorage` | Таблица `govorun_states` (создаётся миграцией) |
| `CacheStateStorage` | Кеш Illuminate с TTL |

Драйвер выбирается в `config/state.php`: `driver` (`file`/`database`/`cache`) + `ttl` (секунды).

### StateAccessor

Общий контракт чтения/записи произвольных ключей состояния. Две реализации:

| Реализация | Где | Семантика записи |
|---|---|---|
| `StateData` | `$this->state` во `Flow` | In-memory, флашится в storage в конце `ask`/`receive` |
| `PersistentState` | `$this->state` в `Controller` | Write-through — каждый `set()` сразу пишет в storage |

| Метод | Описание |
|---|---|
| `get(string $key, mixed $default)` | Получить значение |
| `set(string $key, mixed $value)` | Сохранить значение |
| `has(string $key)` | Проверить наличие |
| `all()` | Получить весь массив |

---

## HTTP

### ConnectionClient (рекомендуемый путь)

Подключения к внешним API описаны в `config/connections.php`:

```php
return [
    'payment' => [
        'base_url' => 'https://api.payment.com/v1',
        'default_headers' => ['Accept' => 'application/json'],
        'auth' => ['type' => 'bearer', 'token' => env('PAYMENT_TOKEN')],
    ],
];
```

Трейт `MakesHttpCalls` подмешан в `Controller` и `Flow`:

```php
$response = $this->http()->connection('payment')->post('/charge', [
    'json' => ['amount' => 100, 'currency' => 'RUB'],
]);

if ($response->successful()) {
    $payment = $response->json();
}
```

| Тип auth | Конфиг |
|---|---|
| `none` | (без auth) |
| `bearer` | `['type' => 'bearer', 'token' => '…']` → заголовок `Authorization: Bearer …` |
| `api_key` (header) | `['type' => 'api_key', 'in' => 'header', 'key' => 'X-Api-Key', 'value' => '…']` |
| `api_key` (query) | `['type' => 'api_key', 'in' => 'query', 'key' => 'api_key', 'value' => '…']` |
| `basic` | `['type' => 'basic', 'login' => '…', 'password' => '…']` |

Опции Guzzle (`json`, `form_params`, `query`, `headers`, …) пробрасываются через второй аргумент. Таймаут по умолчанию — 10 секунд, `http_errors=false` (не бросает исключения на 4xx/5xx).

`HttpResponse`:

| Метод | Описание |
|---|---|
| `status(): int` | HTTP-статус |
| `successful(): bool` | 2xx |
| `failed(): bool` | не 2xx |
| `body(): string` | сырое тело |
| `json(): ?array` | JSON-декод (null при ошибке) |

### ApiClient (абстрактный, для собственных клиентов)

Альтернатива для случаев, когда удобнее иметь типизированный клиент-наследник, а не вызывать `connection('slug')`:

```php
use Govorun\Http\ApiClient;

class PaymentClient extends ApiClient
{
    protected int $timeout = 10;
    protected int $retries = 2;

    public function baseUrl(): string
    {
        return 'https://api.payment.com/v1';
    }

    public function headers(): array
    {
        return ['Authorization' => 'Bearer ' . env('PAYMENT_KEY')];
    }
}
```

| Метод | Описание |
|---|---|
| `get(string $uri, array $params)` | GET |
| `post(string $uri, array $data)` | POST |
| `put(string $uri, array $data)` | PUT |
| `delete(string $uri)` | DELETE |

---

## Validator

`Govorun\Support\Validator` — fluent-валидатор пользовательского ввода с lazy-load дефолтов из `resources/validation-messages.json` (синхронизирован с `govorun-factory/resources/validation-messages.json` — править одновременно).

```php
use Govorun\Support\Validator;

$error = Validator::make($msg->text)
    ->required()
    ->numeric()
    ->min(1)
    ->max(999)
    ->validate();   // ?string — null если ок

if ($error !== null) {
    $this->reply($error);
    return;
}
```

В `Flow` есть шорткат `$this->validator($value)` — он сам отправит ошибку пользователю через `errorHandler`. Терминал — `->fails(): bool` (true = ошибка уже отправлена пользователю):

```php
if ($this->validator($msg->text)->required()->email()->fails()) {
    return;
}
```

Доступные правила: `required`, `email`, `string`, `numeric`, `integer`, `url`, `phone`, `regex`, `min`, `max`, `between`, `in`, `date`. Полный список — `src/Support/Validator.php`. Шаблоны сообщений — `resources/validation-messages.json`.

---

## Middleware

```php
use Govorun\Routing\Middleware;
use Govorun\Messaging\IncomingMessage;

class LogMiddleware implements Middleware
{
    public function handle(IncomingMessage $message, \Closure $next): void
    {
        logger()->info("Message from {$message->user->id}: {$message->text}");
        $next($message);
    }
}
```

Middleware складываются в pipeline. Если `$next` не вызван — цепочка прерывается.

---

## Request

| Метод | Описание |
|---|---|
| `Request::capture()` | Создать из глобальных переменных |
| `getContent()` | Сырое тело |
| `json()` | Декодированный JSON |
| `header(string $name)` | Значение заголовка |
| `method()` | HTTP-метод |
| `uri()` | Полный URI |
| `path()` | Путь без query |
| `query(string $key, $default)` | Параметр строки запроса |

---

## ServiceProvider

```php
use Govorun\Foundation\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void { /* привязки контейнера */ }
    public function boot(): void { /* после регистрации всех провайдеров */ }
}
```

Подключение в `config/app.php`:

```php
'providers' => [
    App\Providers\AppServiceProvider::class,
],
```

---

## MessengerDriver (интерфейс)

| Метод | Описание |
|---|---|
| `verifyWebhook(Request)` | Проверить подпись запроса |
| `parseUpdate(Request)` | Распарсить в `IncomingMessage` |
| `send(OutgoingMessage)` | Отправить сообщение (вернуть `?string` ID отправленного) |
| `edit(string $id, OutgoingMessage)` | Редактировать сообщение |
| `delete(string $id, string $chatId)` | Удалить сообщение |
| `installWebhook(string $url)` | Установить вебхук |
| `removeWebhook()` | Удалить вебхук |
| `getUser(string $id)` | Получить данные пользователя |

Telegram-драйвер дополнительно реализует методы для `bot:profile-sync`: `setMyName`, `setMyShortDescription`, `setMyDescription`, `setMyCommands`, `setMyProfilePhoto`, `removeMyProfilePhoto`.

---

## CLI-команды

| Команда | Описание |
|---|---|
| `php govorun webhook:install` | Установить вебхуки для активных драйверов |
| `php govorun webhook:remove` | Удалить вебхуки |
| `php govorun migrate` | Запустить миграции БД |
| `php govorun make:controller {name}` | Создать контроллер из stub'а |
| `php govorun make:flow {name}` | Создать Flow-диалог |
| `php govorun make:api-client {name}` | Создать API-клиент |
| `php govorun state:clear` | Очистить состояния Flow |
| `php govorun bot:profile-sync` | Идемпотентная синхронизация Telegram-профиля (name / short_description / description / commands / photo) из `config/bot_profile.php` и `storage/app/bot-profile.{jpg,mp4}`. Флаги `--only=<sec>...` / `--skip=<sec>...` |
| `php govorun test` | Запустить тесты проекта |

---

## Тестирование

```bash
vendor/bin/phpunit                                    # все тесты
vendor/bin/phpunit --testsuite=Unit                   # только Unit
vendor/bin/phpunit --testsuite=Integration            # только Integration
vendor/bin/phpunit tests/Unit/State/FlowTest.php      # один файл
vendor/bin/phpunit --filter test_specific_thing       # по имени
```

В `src/Testing/` лежат `TestCase`, `FakeDriver`, `FakeMessenger`, `FakeApiClient` и трейты — используются как в тестах фреймворка, так и из проектов-потребителей.

## Лицензия

MIT