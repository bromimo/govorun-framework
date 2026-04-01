# Govorun Framework

Мульти-мессенджер бот-фреймворк на PHP 8.3+. Позволяет создавать ботов с единым кодом для разных мессенджеров.

## Быстрый старт

```bash
composer create-project govorun/skeleton my-bot
cd my-bot
```

Укажите токен бота в `.env`:

```env
TELEGRAM_BOT_TOKEN=your-token
```

Установите вебхук:

```bash
php govorun webhook:install
```

Подробная документация по использованию: [govorun/skeleton](https://github.com/bromimo/govorun-skeleton)

---

## Архитектура

### Жизненный цикл запроса

```
HTTP POST → public/index.php → Application::handleWebhook()
  1. loadEnvironment()         — загрузка .env
  2. loadConfiguration()       — загрузка config/*.php
  3. registerCoreProviders()   — EventServiceProvider, LogServiceProvider, StateServiceProvider
  4. registerConfiguredProviders() — провайдеры из config('app.providers')
  5. boot()                    — загрузка всех провайдеров
  6. loadRoutes()              — загрузка routes/messenger.php
  7. resolveDriverName()       — определение драйвера по URL
  8. resolveDriver()           — создание экземпляра драйвера
  9. verifyWebhook()           — проверка подписи запроса
  10. parseUpdate()            — парсинг в IncomingMessage
  11. FlowHandler::handle()    — проверка активного Flow-диалога
  12. Router::dispatch()       — маршрутизация к контроллеру
```

### Компоненты

```
src/
├── Console/            — CLI-команды (make:controller, webhook:install и т.д.)
├── Contracts/          — интерфейсы (MessengerDriver, StateStorage)
├── Database/           — миграции
├── Drivers/            — драйверы мессенджеров
│   └── Telegram/       — Telegram-драйвер
├── Events/             — EventServiceProvider
├── Exceptions/         — обработка ошибок
├── Foundation/         — Application, ServiceProvider
├── Http/               — Request, ApiClient
├── Log/                — LogServiceProvider
├── Messaging/          — IncomingMessage, OutgoingMessage, Keyboard, Media, Button
│   └── Dto/            — UserDto, MediaDto, LocationDto, ContactDto
├── Routing/            — Router, Route, Controller, Middleware
├── State/              — Flow, FlowHandler, Step, StateStorage
├── Support/            — хелперы (env, config, app, storage_path и т.д.)
└── Testing/            — тестовые утилиты
```

---

## Application

Ядро фреймворка. Наследует `Illuminate\Container\Container`.

```php
$app = new Application(dirname(__DIR__));
```

### Методы

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

### Route (статический DSL)

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

### Приоритет маршрутов

`event` > `command` > `action` > `referral` > `media` > `location` > `contact` > `pattern` > `phrase` > `fallback`

### Middleware

```php
Route::middleware(AuthMiddleware::class, function () {
    Route::command('admin', AdminController::class);
});
```

### Вложенные маршруты (phrase)

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

### RouteEntry

| Свойство | Тип | Описание |
|----------|-----|----------|
| `type` | `string` | Тип маршрута |
| `value` | `?string` | Значение для сопоставления |
| `action` | `mixed` | Обработчик |
| `aliases` | `array` | Алиасы для phrase |
| `middleware` | `array` | Классы middleware |
| `children` | `array` | Вложенные маршруты |

---

## Controller

Базовый класс контроллера бота.

```php
use Govorun\Routing\Controller;

class StartController extends Controller
{
    public function handle(): void
    {
        $name = $this->user()->firstName;
        $this->reply("Привет, {$name}!");
    }
}
```

Метод `handle()` вызывается без аргументов. Альтернативно можно использовать `__invoke()`.

### Методы

| Метод | Возвращает | Описание |
|-------|------------|----------|
| `reply(string $text)` | `void` | Отправить текстовый ответ |
| `send(OutgoingMessage $msg)` | `void` | Отправить сообщение с клавиатурой/медиа |
| `message()` | `IncomingMessage` | Входящее сообщение |
| `user()` | `UserDto` | Данные пользователя |
| `param(string $key)` | `?string` | Параметр callback-действия |
| `startFlow(string $class)` | `void` | Запустить Flow-диалог |

---

## Messaging

### IncomingMessage

| Свойство | Тип | Описание |
|----------|-----|----------|
| `id` | `string` | ID сообщения |
| `chatId` | `string` | ID чата |
| `driverName` | `string` | Имя драйвера |
| `text` | `?string` | Текст сообщения |
| `user` | `UserDto` | Данные отправителя |
| `type` | `ContentType` | Тип контента |
| `action` | `?string` | Callback action |
| `actionParams` | `?array` | Параметры action |
| `event` | `?string` | Имя события |
| `media` | `?MediaDto` | Медиаконтент |
| `location` | `?LocationDto` | Геолокация |
| `contact` | `?ContactDto` | Контакт |
| `referral` | `?string` | Реферальный код |
| `raw` | `array` | Сырые данные от мессенджера |

### ContentType (enum)

| Значение | Описание |
|----------|----------|
| `Text` | Текстовое сообщение |
| `Action` | Callback-действие |
| `Media` | Медиафайл |
| `Location` | Геолокация |
| `Contact` | Контакт |
| `Event` | Событие |

### OutgoingMessage

```php
use Govorun\Messaging\Message;

$msg = Message::make('Текст')
    ->keyboard($keyboard)
    ->parseMode('HTML');
```

| Свойство | Тип | Описание |
|----------|-----|----------|
| `chatId` | `string` | ID чата (заполняется автоматически) |
| `text` | `?string` | Текст |
| `parseMode` | `?string` | Режим разбора (HTML, Markdown) |
| `keyboard` | `?array` | Клавиатура |
| `media` | `?array` | Медиа |

### Keyboard

```php
use Govorun\Messaging\Keyboard;

// Inline-клавиатура
Keyboard::make()
    ->button('Текст', action: 'name', param: ['key' => 'value'])
    ->button('Ссылка', url: 'https://example.com')
    ->row()
    ->button('Новый ряд');

// Reply-клавиатура
Keyboard::reply()
    ->button('Контакт', requestContact: true)
    ->button('Локация', requestLocation: true);

// Удалить клавиатуру
Keyboard::remove();
```

### Media

```php
use Govorun\Messaging\Media;

Media::photo('https://example.com/img.jpg')->caption('Описание');
Media::document('https://example.com/file.pdf');
Media::voice('https://example.com/audio.ogg');
```

### Button

```php
new Button(
    text: 'Текст кнопки',
    action: 'callback_action',
    param: ['key' => 'value'],
    url: 'https://...',
    requestContact: false,
    requestLocation: false,
);
```

---

## DTO (Data Transfer Objects)

### UserDto

| Свойство | Тип | Описание |
|----------|-----|----------|
| `id` | `string` | ID пользователя |
| `firstName` | `?string` | Имя |
| `lastName` | `?string` | Фамилия |
| `username` | `?string` | Username |
| `phone` | `?string` | Телефон |
| `locale` | `?string` | Локаль |
| `raw` | `array` | Сырые данные |

### MediaDto

| Свойство | Тип | Описание |
|----------|-----|----------|
| `type` | `string` | Тип (photo, video, document, voice) |
| `url` | `?string` | URL файла |
| `fileId` | `?string` | ID файла в мессенджере |
| `mimeType` | `?string` | MIME-тип |
| `fileSize` | `?int` | Размер в байтах |
| `raw` | `array` | Сырые данные |

### LocationDto

| Свойство | Тип |
|----------|-----|
| `latitude` | `float` |
| `longitude` | `float` |
| `raw` | `array` |

### ContactDto

| Свойство | Тип |
|----------|-----|
| `phone` | `string` |
| `firstName` | `?string` |
| `lastName` | `?string` |
| `userId` | `?string` |
| `raw` | `array` |

---

## Flow (пошаговые диалоги)

Flow управляет многошаговыми диалогами. Состояние сохраняется между шагами.

```php
use Govorun\State\Flow;
use Govorun\State\Step;
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
        $step->ask("Заказ: {$product} x {$qty}. Подтвердить?");

        $step->receive(function (IncomingMessage $msg) {
            $this->reply('Заказ принят!');
        });
    }

    public function onComplete(): void { }
    public function onCancel(): void { $this->reply('Заказ отменён.'); }
}
```

### Свойства Flow

| Свойство | Тип | Описание |
|----------|-----|----------|
| `$steps` | `array` | Имена шагов (порядок выполнения) |
| `$interruptCommands` | `array` | Команды, прерывающие flow |
| `$interruptOnEvent` | `bool` | Прерывать при событии |
| `$state` | `StateData` | Данные состояния |

### StateData

| Метод | Описание |
|-------|----------|
| `get(string $key, mixed $default)` | Получить значение |
| `set(string $key, mixed $value)` | Сохранить значение |
| `has(string $key)` | Проверить наличие ключа |
| `all()` | Получить все данные |

### Step

| Метод | Описание |
|-------|----------|
| `ask(string $text, ?Closure $keyboard)` | Задать вопрос (с опциональной клавиатурой) |
| `receive(Closure $callback)` | Обработать ответ пользователя |

### Логика работы

1. Контроллер вызывает `$this->startFlow(OrderFlow::class)`
2. Flow выполняет первый шаг — отправляет вопрос (`ask`)
3. Следующее сообщение от пользователя перехватывается `FlowHandler`
4. Flow вызывает `receive` callback текущего шага
5. `$this->nextStep()` переходит к следующему шагу
6. После последнего шага вызывается `onComplete()`

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

        $next($message); // передать дальше
    }
}
```

Middleware оборачиваются в pipeline. Каждый получает сообщение и `$next`. Если `$next` не вызван — цепочка прерывается.

---

## HTTP / ApiClient

Базовый класс для работы с внешними API.

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

### Методы ApiClient

| Метод | Описание |
|-------|----------|
| `get(string $uri, array $params)` | GET-запрос |
| `post(string $uri, array $data)` | POST-запрос |
| `put(string $uri, array $data)` | PUT-запрос |
| `delete(string $uri)` | DELETE-запрос |

---

## Request

Обёртка HTTP-запроса.

| Метод | Описание |
|-------|----------|
| `Request::capture()` | Создать из глобальных переменных |
| `getContent()` | Тело запроса (raw) |
| `json()` | Декодировать JSON тело |
| `header(string $name)` | Значение заголовка |
| `method()` | HTTP-метод |
| `uri()` | Полный URI |
| `path()` | Путь без query string |
| `query(string $key, $default)` | Параметр строки запроса |

---

## State Storage

Интерфейс хранения состояния Flow-диалогов.

```php
interface StateStorage
{
    public function get(string $chatId, string $driver): ?array;
    public function set(string $chatId, string $driver, array $data): void;
    public function delete(string $chatId, string $driver): void;
}
```

### Реализации

| Класс | Описание |
|-------|----------|
| `FileStateStorage` | JSON-файлы в `storage/state/` |
| `DatabaseStateStorage` | Таблица `govorun_states` |
| `CacheStateStorage` | Кеш с TTL |

---

## ServiceProvider

```php
use Govorun\Foundation\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Регистрация привязок в контейнере
    }

    public function boot(): void
    {
        // После регистрации всех провайдеров
    }
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

Каждый мессенджер реализует этот интерфейс:

| Метод | Описание |
|-------|----------|
| `verifyWebhook(Request)` | Проверить подпись запроса |
| `parseUpdate(Request)` | Распарсить в IncomingMessage |
| `send(OutgoingMessage)` | Отправить сообщение |
| `edit(string $id, OutgoingMessage)` | Редактировать сообщение |
| `delete(string $id, string $chatId)` | Удалить сообщение |
| `installWebhook(string $url)` | Установить вебхук |
| `removeWebhook()` | Удалить вебхук |
| `getUser(string $id)` | Получить данные пользователя |

---

## CLI-команды

| Команда | Описание |
|---------|----------|
| `php govorun webhook:install` | Установить вебхуки для активных драйверов |
| `php govorun webhook:remove` | Удалить вебхуки |
| `php govorun migrate` | Запустить миграции БД |
| `php govorun make:controller {name}` | Создать контроллер |
| `php govorun make:flow {name}` | Создать Flow-диалог |
| `php govorun make:api-client {name}` | Создать API-клиент |
| `php govorun state:clear` | Очистить состояния Flow |
| `php govorun test` | Запустить тесты |

---

## Лицензия

MIT
