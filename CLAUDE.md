# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Reference

- **Language:** PHP 8.3
- **Tests:** `vendor/bin/phpunit` (PHPUnit 11)
- **Single test:** `vendor/bin/phpunit tests/Unit/Path/TestFile.php --filter=test_name`
- **Autoload:** PSR-4 `Govorun\` -> `src/`, `Govorun\Tests\` -> `tests/`
- **Design spec:** `docs/superpowers/specs/2026-03-25-govorun-design.md`
- **Commits:** Conventional commits — `feat:`, `fix:`, `chore:`, `docs:`

## Architecture

Multi-messenger bot framework. Core package `govorun/framework` built on illuminate components ^11.0.

**Request lifecycle:** `Application::handleWebhook(Request)` → resolve driver → `verifyWebhook()` → `parseUpdate()` → `FlowHandler::handle()` (if active Flow exists) → `Router::dispatch()` → matched route action.

**Key abstractions:**
- `MessengerDriver` interface — platform adapters (Telegram implemented, Viber/WhatsApp planned)
- `StateStorage` interface — session persistence (File/Database/Cache implementations)
- `Route` static DSL — `Route::on()`, `Route::command()`, `Route::phrase()`, `Route::action()`, `Route::fallback()`
- `Flow` — multi-step dialogs with `Step` definitions, auto-resume, interrupt detection
- `Application` extends `Illuminate\Container\Container` — IoC, config, providers, webhook/console handling

```
src/
    Console/          # ConsoleServiceProvider, 8 commands, stubs/
    Contracts/        # MessengerDriver, StateStorage
    Database/         # Migrations (CreateGovorunStatesTable)
    Drivers/Telegram/ # TelegramDriver
    Events/           # EventServiceProvider
    Exceptions/       # SendFailedException, ApiException
    Foundation/       # Application, ServiceProvider
    Http/             # Request, ApiClient (abstract)
    Log/              # LogServiceProvider, Log facade
    Messaging/        # IncomingMessage, OutgoingMessage, Message, Button, Keyboard, Media, DTOs
    Routing/          # Route, Router, Controller, Middleware, MiddlewarePipeline, RouteEntry
    State/            # Flow, Step, FlowHandler, File/Database/CacheStateStorage, StateServiceProvider
    Support/          # helpers.php
    Testing/          # TestCase, FakeMessenger, FakeDriver, FakeApiClient, traits
```

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
