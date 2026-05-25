# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Reference

- **Language:** PHP 8.3
- **Tests:** `vendor/bin/phpunit` (PHPUnit 11). Suite-режимы: `--testsuite=Unit` или `--testsuite=Integration`.
- **Single test:** `vendor/bin/phpunit tests/Unit/Path/TestFile.php --filter=test_name`
- **Autoload:** PSR-4 `Govorun\` -> `src/`, `Govorun\Tests\` -> `tests/`
- **Design spec:** `docs/superpowers/specs/2026-03-25-govorun-design.md`
- **Commits:** Conventional commits — `feat:`, `fix:`, `chore:`, `docs:`. Сломанное API — `feat!:` / `BREAKING CHANGE:`.
- **Release:** после мержа feature-PR в `develop`/`main` сразу тегировать semver (см. `git tag | head` для последнего).

## Architecture

Multi-messenger bot framework. Core package `govorun/framework` built on illuminate components ^11.0. Скелетон-приложение бота — отдельный репозиторий `govorun-skeleton`, локально `C:\domains\govorun-skeleton`. Целевой потребитель — `govorun-factory` (визуальный билдер, генерирует код против этого фреймворка).

**Request lifecycle:** `Application::handleWebhook(Request)` → resolve driver → `verifyWebhook()` → `parseUpdate()` → `FlowHandler::handle()` (если есть запись в `StateStorage` с `flow_class`) → `Router::dispatch()` → matched route action. `controller_kb_ctx`-записи в storage НЕ считаются активным Flow и не блокируют диспатч.

**Key abstractions:**
- `MessengerDriver` interface — platform adapters (Telegram implemented, Viber/WhatsApp planned)
- `StateStorage` interface — session persistence (File/Database/Cache implementations)
- `StateAccessor` interface — общий контракт state для Flow (`StateData`, in-memory) и Controller (`PersistentState`, write-through в storage)
- `Route` static DSL — `Route::command()`, `Route::phrase()`, `Route::pattern()`, `Route::action()`, `Route::event()`, `Route::media()`, `Route::location()`, `Route::contact()`, `Route::referral()`, `Route::fallback()`, `Route::middleware()`
- `Flow` — multi-step dialogs with `Step` definitions, auto-resume, interrupt detection. Использует трейт `MakesHttpCalls`.
- `Controller` — базовый класс. Использует `MakesHttpCalls`. Публичное свойство `$this->message` (рекомендуемое), deprecated алиас `$this->incomingMessage`. `$this->state` — `PersistentState` (или `StateData`-заглушка, если storage недоступен).
- `Application` extends `Illuminate\Container\Container` — IoC, config, providers, webhook/console handling

```
src/
    Console/          # ConsoleServiceProvider, 9 commands (вкл. bot:profile-sync), stubs/
    Contracts/        # MessengerDriver, StateStorage, StateAccessor
    Database/         # Migrations (CreateGovorunStatesTable)
    Drivers/Telegram/ # TelegramDriver (sendMedia поддерживает URL и локальный файл через multipart)
    Events/           # EventServiceProvider
    Exceptions/       # SendFailedException, ApiException
    Foundation/       # Application, ServiceProvider
    Http/             # Request, ApiClient (abstract), HttpManager + ConnectionClient + HttpResponse, MakesHttpCalls trait
    Log/              # LogServiceProvider, Log facade
    Messaging/        # IncomingMessage, OutgoingMessage, Message, Button, Keyboard, Media, DTOs
    Routing/          # Route, Router, Controller, Middleware, MiddlewarePipeline, RouteEntry
    State/            # Flow, Step, FlowHandler, StateData, PersistentState, File/Database/CacheStateStorage, StateServiceProvider
    Support/          # helpers.php, Validator (fluent-валидатор с lazy-load дефолтов из resources/validation-messages.json)
    Testing/          # TestCase, FakeMessenger, FakeDriver, FakeApiClient, traits
```

## Connections / api_call

Подключения к внешним HTTP-API лежат в `config/connections.php` (массив с ключами-slug'ами). Трейт `MakesHttpCalls` подмешан в `Controller` и `Flow`:

```php
$response = $this->http()->connection('payment')->post('/charge', [
    'json' => ['amount' => 100],
]);

if ($response->successful()) {
    $data = $response->json();
}
```

`ConnectionClient` собирает Guzzle-клиент из конфига подключения: `base_url`, `default_headers`, `auth` (`none`/`bearer`/`api_key` header или query/`basic`). Таймаут 10с, `http_errors=false`. Ответ — `HttpResponse` со status/successful/failed/body/json. Этот же интерфейс генерирует фабрика в `api_call`-нодах.

## Inline-keyboard auto-finalize

При отправке inline-клавиатуры с action-кнопками контекст (`message_id`, `original_text`, `parse_mode`, `label_map`) сохраняется в storage под ключом `controller_kb_ctx` (для Controller) или `__ask_keyboard_ctx` (для Flow). При следующем Action-сообщении исходное сообщение редактируется — клавиатура убирается, к тексту дописывается `(выбрано: <label>)`. В `Flow::onCancel()` / `nextStep()` — `(отменено)`. Ошибки `driver->edit()` глотаются, чтобы не ронять flow.

## State accessor

- `Flow`: `protected StateData $state` — in-memory во время прохождения шага; сохраняется в storage целиком в `saveState()` после `ask`/`receive` (см. `Flow.php`).
- `Controller`: `protected StateAccessor $state` — экземпляр `PersistentState`, каждый `set()` пишет в storage немедленно (write-through). Это позволяет вне Flow накапливать состояние через `api_call`-блоки.

## Validation messages

Дефолтные тексты сообщений валидации — в `resources/validation-messages.json` (плоский `{ruleName: template}` объект с именованными плейсхолдерами `{value}`, `{min}`, `{max}`).
`Support/Validator.php` lazy-читает файл при первом вызове.

**Этот файл дублируется в `govorun-factory/resources/validation-messages.json`.** При любом изменении обновлять обе копии — одним коммитом, в обоих репо.

## Conventions

### Code Style

- **PHPDocs** — required on all classes, methods, properties. Format:
  - Description on the **same line** as `/**`
  - No blank lines inside PHPDoc
  - Single-line PHPDocs close on same line: `/** Description. */`
  - All tags required: `@param`, `@return`, `@throws`
  - `@throws` required for **propagated exceptions** too (from called methods)
  ```php
  /** Description. */

  /** Method description.
   * @param string $name Name
   * @return void
   * @throws \RuntimeException If something goes wrong
   */
  ```
- **use imports** — sorted by ascending line length (shortest first)

### Testing

- Mirror src structure under `tests/Unit/`. Naming: `{Class}Test.php`
- Test fixtures in `tests/fixtures/config/`
- **tearDown:** Always call `Application::setInstance(null)` to prevent state leakage
- Database tests use SQLite in-memory via `Illuminate\Database\Capsule\Manager`
- Windows: call `$this->app->flush()` and `gc_collect_cycles()` in tearDown before unlinking log files

### Framework Patterns

- **Service Providers:** Extend `Govorun\Foundation\ServiceProvider`, implement `register()` and `boot()`
- **Core Providers:** `Application::registerCoreProviders()` registers Event + Log + State providers
- **State config:** `config/state.php` — `driver` (file/database/cache), `ttl` (seconds)
- **Helpers:** `app()`, `config()`, `env()`, `event()`, `base_path()`, `config_path()`, `storage_path()`, `database_path()`
- **Log facade:** `Govorun\Log\Log::info()`, `Log::error()`, etc.

## Implementation Status

All 11 phases complete: Foundation, Messaging, Routing, Telegram Driver, HTTP Lifecycle, API Client, State Management, Events & Logging, Database & State Storage, Console Commands, Testing Helpers.
