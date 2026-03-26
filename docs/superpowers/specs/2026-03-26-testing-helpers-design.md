# Phase 11: Testing Helpers — Спецификация

## Обзор

Testing helpers для Govorun Framework: `fakeMessenger()` и `fakeApi()`. Позволяют тестировать ботов через fluent API без реальных HTTP-запросов к мессенджерам и внешним сервисам. Тесты проходят полный цикл `handleWebhook` — роутинг, middleware, Flow/State.

## Структура файлов

```
src/Testing/
    TestCase.php                 # Базовый TestCase (extends PHPUnit\TestCase)
    InteractsWithMessenger.php   # Трейт: fakeMessenger()
    InteractsWithApi.php         # Трейт: fakeApi()
    FakeMessenger.php            # Fluent-объект для цепочки receive/assert
    FakeDriver.php               # Реализация MessengerDriver, копит sent messages
    FakeApiClient.php            # Обёртка для mock/assert API-вызовов

tests/Unit/Testing/
    FakeDriverTest.php
    FakeMessengerTest.php
    FakeApiClientTest.php
    InteractsWithMessengerTest.php
    InteractsWithApiTest.php
```

Namespace: `Govorun\Testing\` для source, `Govorun\Tests\Unit\Testing\` для тестов.

## Компоненты

### TestCase

Базовый класс, подключает оба трейта. Создаёт `Application` в `setUp()`, чистит в `tearDown()`.

```php
namespace Govorun\Testing;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Govorun\Foundation\Application;

abstract class TestCase extends PHPUnitTestCase
{
    use InteractsWithMessenger, InteractsWithApi;

    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApplication();
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    protected function createApplication(): Application
    {
        $app = new Application(base_path());
        $app->loadConfiguration();
        $app->registerCoreProviders();
        $app->boot();
        return $app;
    }
}
```

Пользователь может не наследовать TestCase — достаточно `use InteractsWithMessenger` в своём тесте, но тогда нужно самому настроить Application и свойство `$app`.

### FakeDriver

Реализует `MessengerDriver`. Ключевое поведение:

- `verifyWebhook()` → всегда `true`
- `parseUpdate()` → возвращает следующий `IncomingMessage` из внутренней очереди `$pendingMessages`
- `send()` → копит `OutgoingMessage` в `$sentMessages[]`
- `edit()`, `delete()`, `installWebhook()`, `removeWebhook()`, `getUser()` — no-op / заглушки

Публичные методы для тестового окружения:

- `queueMessage(IncomingMessage $message): void` — кладёт сообщение в очередь
- `getSentMessages(): array` — возвращает отправленные сообщения
- `resetSentMessages(): void` — очищает очередь отправленных

### FakeMessenger

Fluent-объект, ядро тестирования. Хранит ссылку на `Application` и `FakeDriver`.

**Fluent API:**

```php
$this->fakeMessenger()
    ->receive('/start')
    ->assertReply('Добро пожаловать!')
    ->assertKeyboard(['Маникюр', 'Педикюр'])
    ->assertNoReply()
    ->receive('записаться')
    ->assertReplyContains('услугу')
    ->clickButton('Маникюр')
    ->assertReply('Выберите дату:');
```

**Метод `receive(string $text): self`**

1. Создаёт `IncomingMessage` с `ContentType::Text`, chatId = переданный при создании (default `fake-chat-1`), driverName = `fake`
2. Кладёт в `FakeDriver::$pendingMessages`
3. Формирует `Request` с path `/webhook/fake` и минимальным JSON-телом
4. Вызывает `$app->handleWebhook($request)`
5. Возвращает `$this`

**Метод `clickButton(string $text): self`**

1. Ищет кнопку с текстом `$text` в keyboard последнего отправленного сообщения
2. Извлекает `action` и `params` из данных кнопки
3. Создаёт `IncomingMessage` с `ContentType::Action`, соответствующими action/actionParams
4. Прогоняет через `handleWebhook` аналогично `receive()`
5. Если кнопка не найдена — бросает `\RuntimeException("Button '{$text}' not found in last message keyboard")`

**Assertions (используют PHPUnit assertions внутри):**

- `assertReply(string $expected): self` — следующее сообщение в очереди точно равно `$expected`. Потребляет сообщение из очереди. Fail: `"Expected reply '{$expected}' but no messages were sent"` или `"Expected reply '{$expected}' but got '{$actual}'"`
- `assertReplyContains(string $needle): self` — следующее сообщение содержит `$needle`. Потребляет сообщение. Fail: аналогично.
- `assertNoReply(): self` — очередь непотреблённых сообщений пуста. Не потребляет.
- `assertKeyboard(array $buttonTexts): self` — последнее потреблённое сообщение содержит keyboard с указанными текстами кнопок. Не потребляет новое сообщение. Fail: `"Expected keyboard but message has none"` или `"Expected buttons [...] but got [...]"`

Порядок потребления — FIFO. Несколько `receive()` подряд без assertions допустимы, сообщения копятся.

### InteractsWithMessenger (трейт)

```php
trait InteractsWithMessenger
{
    protected function fakeMessenger(string $chatId = 'fake-chat-1'): FakeMessenger
    {
        $driver = new FakeDriver();
        $this->app->instance(MessengerDriver::class, $driver);
        $this->app->instance('driver.fake', $driver);

        return new FakeMessenger($this->app, $driver, $chatId);
    }
}
```

Требует свойство `$this->app` типа `Application` на объекте теста.

### FakeApiClient

Обёртка над реальным ApiClient. Через рефлексию подменяет `$client` (GuzzleClientInterface) внутри экземпляра оригинального класса на Guzzle MockHandler + History middleware.

**Mock-методы:**

- `mockGet(string $uri, mixed $response): self`
- `mockPost(string $uri, mixed $response): self`
- `mockPut(string $uri, mixed $response): self`
- `mockDelete(string $uri, mixed $response): self`

Регистрируют заготовленные ответы по method+uri. При вызове соответствующего метода оригинального ApiClient — Guzzle MockHandler возвращает `Response(200, json_encode($response))`.

Если запрос не имеет заготовленного ответа — бросает `\RuntimeException("No mock registered for {METHOD} {uri}")`.

**Assertions:**

- `assertRequestMade(string $method, string $uri): self` — был вызов с этим method+uri
- `assertRequestCount(int $expected): self` — общее количество перехваченных вызовов

### InteractsWithApi (трейт)

```php
trait InteractsWithApi
{
    protected function fakeApi(string $clientClass): FakeApiClient
    {
        $instance = $this->app->make($clientClass);
        $fake = new FakeApiClient($instance);
        $this->app->instance($clientClass, $instance);

        return $fake;
    }
}
```

FakeApiClient принимает экземпляр оригинального ApiClient, через рефлексию подменяет его `$client` property на mock-обработчик. Затем этот экземпляр регистрируется в контейнере — код бота получает его через DI.

## Интеграция с handleWebhook

Изменений в `Application::handleWebhook()` не требуется. Существующий код уже поддерживает подмену драйвера через контейнер:

```php
// Application::resolveDriver() — уже есть:
if ($this->bound(MessengerDriver::class)) {
    return $this->make(MessengerDriver::class);
}
```

Поток при `FakeMessenger::receive('text')`:

1. FakeMessenger создаёт IncomingMessage → кладёт в `FakeDriver::$pendingMessages`
2. Формирует `Request` с path `/webhook/fake`
3. `handleWebhook($request)` → `resolveDriverName()` = `fake` → `resolveDriver('fake')` → находит FakeDriver
4. `FakeDriver::verifyWebhook()` → true
5. `FakeDriver::parseUpdate()` → отдаёт подготовленный IncomingMessage
6. FlowHandler + Router работают штатно
7. `FakeDriver::send()` копит ответы
8. FakeMessenger проверяет ответы через assertions

## Edge cases

- **`clickButton()` — кнопка не найдена:** `RuntimeException`
- **`assertReply()` — пустая очередь:** PHPUnit assertion failure
- **`assertKeyboard()` — нет клавиатуры:** PHPUnit assertion failure
- **`fakeApi()` — незарегистрированный запрос:** `RuntimeException`
- **Несколько `receive()` подряд:** сообщения копятся, assertions потребляют в FIFO
- **State/Flow:** работает через полный цикл с настроенным StateStorage

## Тесты

Все тесты в `tests/Unit/Testing/`:

| Файл | Что проверяет |
|------|---------------|
| `FakeDriverTest` | `send()` копит, `verifyWebhook()` = true, `parseUpdate()` из очереди |
| `FakeMessengerTest` | receive/assertReply цепочка, clickButton, fail-сценарии assertions |
| `FakeApiClientTest` | mockGet возвращает данные, assertRequestMade, exception на незарегистрированный запрос |
| `InteractsWithMessengerTest` | трейт создаёт FakeDriver и биндит в контейнер |
| `InteractsWithApiTest` | трейт подменяет ApiClient через рефлексию |
