# Govorun Framework

Multi-messenger bot framework for PHP 8.3. Architecture: core (`govorun/framework`) as Composer package.

## Quick Reference

- **Language:** PHP 8.3
- **Tests:** `vendor/bin/phpunit` (PHPUnit 11)
- **Single test:** `vendor/bin/phpunit tests/Unit/Path/TestFile.php --filter=test_name`
- **Autoload:** PSR-4 `Govorun\` -> `src/`, `Govorun\Tests\` -> `tests/`
- **Design spec:** `docs/superpowers/specs/2026-03-25-govorun-design.md`

## Architecture

```
src/
    Console/          # ConsoleServiceProvider, 8 commands (webhook:*, migrate, make:*, state:clear, test), stubs/
    Contracts/        # Interfaces: MessengerDriver, StateStorage
    Database/         # Migrations (CreateGovorunStatesTable)
    Drivers/Telegram/ # TelegramDriver — first messenger implementation
    Events/           # EventServiceProvider — Dispatcher registration, listener mapping from config
    Exceptions/       # SendFailedException, ApiException
    Foundation/       # Application (extends Container), ServiceProvider
    Http/             # Request wrapper, ApiClient base class
    Log/              # LogServiceProvider (single/daily/stack channels), Log facade
    Messaging/        # IncomingMessage, OutgoingMessage, Message, Button, Keyboard, Media, DTOs
    Routing/          # Route (static DSL), Router (priority dispatch), Controller, Middleware
    State/            # Flow, Step, FlowHandler, File/Database/CacheStateStorage, StateServiceProvider
    Support/          # helpers.php (app, config, env, event, base_path, etc.)
```

## Conventions

- **Namespace:** `Govorun\` for source, `Govorun\Tests\` for tests
- **Tests:** Mirror src structure under `tests/Unit/`. Test class naming: `{Class}Test.php`
- **Test fixtures:** `tests/fixtures/config/` for config files
- **Test tearDown:** Always call `Application::setInstance(null)` to prevent state leakage
- **Dependencies:** illuminate components ^11.0 (container, config, support, events, database, cache, console, log)
- **Helpers:** Global functions in `src/Support/helpers.php` — `app()`, `config()`, `env()`, `event()`, path helpers
- **Log facade:** `Govorun\Log\Log::info()`, `Log::error()`, etc. — static access to PSR-3 logger
- **Service Providers:** Extend `Govorun\Foundation\ServiceProvider`, implement `register()` and `boot()`
- **Core Providers:** `Application::registerCoreProviders()` registers EventServiceProvider + LogServiceProvider + StateServiceProvider
- **State config:** `config/state.php` — `driver` (file/database/cache), `ttl` (seconds). StateServiceProvider resolves the right storage
- **Database tests:** Use SQLite in-memory via `Illuminate\Database\Capsule\Manager`
- **Windows tests:** Use `$this->app->flush()` and `gc_collect_cycles()` in tearDown before unlinking log files
- **Commits:** Conventional commits — `feat:`, `fix:`, `chore:`, `docs:`

## Implementation Status

| Phase | Component | Status |
|-------|-----------|--------|
| 1 | Foundation (Container, Config, Helpers) | Done |
| 2 | Messaging (Message, Button, Keyboard, Media) | Done |
| 3 | Routing (Route, Router, Controller, Middleware) | Done |
| 4 | Telegram Driver | Done |
| 5 | HTTP Lifecycle (handleWebhook) | Done |
| 6 | API Client | Done |
| 7 | State Management (Flow, Step, FlowHandler, FileStateStorage) | Done |
| 8 | Events & Logging | Done |
| 9 | Database & State Storage (migration, DatabaseStateStorage, CacheStateStorage, StateServiceProvider) | Done |
| 10 | Console Commands (webhook:install/remove, migrate, make:*, state:clear, test) | Done |
| 11 | Testing Helpers (fakeMessenger, fakeApi) | Not Started |
