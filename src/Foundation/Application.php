<?php

namespace Govorun\Foundation;

use Dotenv\Dotenv;
use Govorun\Http\Request;
use Govorun\Routing\Router;
use Govorun\Log\LogServiceProvider;
use Govorun\Contracts\MessengerDriver;
use Govorun\Events\EventServiceProvider;
use Govorun\State\StateServiceProvider;
use Illuminate\Container\Container;
use Illuminate\Config\Repository as ConfigRepository;

/** Ядро приложения Govorun.
 * Расширяет IoC-контейнер Illuminate, управляет жизненным циклом
 * приложения: загрузка конфигурации, регистрация провайдеров,
 * обработка вебхуков и консольных команд.
 */
class Application extends Container
{
    /** Базовый путь к корню проекта. */
    protected string $basePath;

    /** Признак завершённой загрузки (boot) провайдеров. */
    protected bool $booted = false;

    /** @var ServiceProvider[] Зарегистрированные сервис-провайдеры */
    protected array $serviceProviders = [];

    /** @var array<class-string, true> Карта уже загруженных провайдеров */
    protected array $loadedProviders = [];

    /** Создать экземпляр приложения.
     * @param string $basePath Корневой путь проекта
     */
    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');

        static::setInstance($this);

        $this->registerBaseBindings();
    }

    /** Зарегистрировать базовые привязки контейнера.
     * @return void
     */
    protected function registerBaseBindings(): void
    {
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);
    }

    /** Получить базовый путь проекта или путь относительно него.
     * @param string $path Относительный путь (необязательно)
     * @return string
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /** Получить путь к директории конфигурации.
     * @param string $path Относительный путь (необязательно)
     * @return string
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /** Получить путь к директории хранилища.
     * @param string $path Относительный путь (необязательно)
     * @return string
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /** Получить путь к директории базы данных.
     * @param string $path Относительный путь (необязательно)
     * @return string
     */
    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    /** Зарегистрировать сервис-провайдер в приложении.
     * Если провайдер уже зарегистрирован, повторная регистрация не выполняется.
     * Если приложение уже загружено, провайдер будет немедленно запущен (boot).
     * @param ServiceProvider $provider Экземпляр провайдера
     * @return ServiceProvider
     */
    public function register(ServiceProvider $provider): ServiceProvider
    {
        $name = get_class($provider);

        if (isset($this->loadedProviders[$name])) {
            return $provider;
        }

        $this->serviceProviders[] = $provider;
        $provider->register();
        $this->loadedProviders[$name] = true;

        if ($this->booted) {
            $provider->boot();
        }

        return $provider;
    }

    /** Загрузить (boot) все зарегистрированные сервис-провайдеры.
     * @return void
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->serviceProviders as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /** Проверить, завершена ли загрузка провайдеров.
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /** Определить, запущено ли приложение в контексте unit-тестов.
     * @return bool
     */
    public function runningUnitTests(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL') || defined('__PHPUNIT_PHAR__');
    }

    /** Загрузить переменные окружения из файла .env.
     * @return void
     */
    public function loadEnvironment(): void
    {
        if (file_exists($this->basePath('.env'))) {
            Dotenv::createImmutable($this->basePath())->load();
        }
    }

    /** Загрузить конфигурационные файлы из директории config/.
     * @return void
     */
    public function loadConfiguration(): void
    {
        $config = new ConfigRepository();

        foreach (glob($this->configPath('*.php')) ?: [] as $file) {
            $key = basename($file, '.php');
            $config->set($key, require $file);
        }

        $this->instance('config', $config);
    }

    /** Зарегистрировать основные (core) провайдеры фреймворка.
     * @return void
     */
    public function registerCoreProviders(): void
    {
        $this->register(new EventServiceProvider($this));
        $this->register(new LogServiceProvider($this));
        $this->register(new StateServiceProvider($this));
    }

    /** Зарегистрировать провайдеры, указанные в конфигурации app.providers.
     * @return void
     */
    public function registerConfiguredProviders(): void
    {
        $providers = $this->make('config')->get('app.providers', []);

        foreach ($providers as $providerClass) {
            $this->register(new $providerClass($this));
        }
    }

    /** Обработать консольный запрос (CLI).
     * Загружает окружение, конфигурацию, провайдеры и запускает Artisan.
     * @return int Код завершения процесса
     * @throws \Throwable При ошибке загрузки или выполнения команды
     */
    public function handleConsole(): int
    {
        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->registerCoreProviders();
        $this->register(new \Govorun\Console\ConsoleServiceProvider($this));
        $this->registerConfiguredProviders();
        $this->boot();

        return $this->make('artisan')->run();
    }

    /** Обработать входящий вебхук от мессенджера.
     * Определяет драйвер, проверяет подпись, парсит сообщение,
     * пропускает через FlowHandler и маршрутизатор.
     * @param Request $request HTTP-запрос вебхука
     * @return int HTTP-код ответа
     * @throws \RuntimeException Если тип обновления не поддерживается драйвером
     */
    public function handleWebhook(Request $request): int
    {
        $driverName = $this->resolveDriverName($request);
        $driver = $this->resolveDriver($driverName);

        if (! $driver->verifyWebhook($request)) {
            return 403;
        }

        $message = $driver->parseUpdate($request);

        try {
            $flowHandler = new \Govorun\State\FlowHandler(
                $this->resolveStateStorage(),
                $driver,
            );

            if ($flowHandler->handle($message)) {
                return 200;
            }

            $router = new Router($driver);
            $router->dispatch($message);
        } catch (\Throwable $e) {
            $this->handleException($e, $message, $driver);
        }

        return 200;
    }

    /** Обработать исключение, возникшее при обработке вебхука.
     * Логирует ошибку и отправляет пользователю сообщение об ошибке.
     * @param \Throwable $e Исключение
     * @param \Govorun\Messaging\IncomingMessage $message Входящее сообщение
     * @param MessengerDriver $driver Драйвер мессенджера
     * @return void
     */
    protected function handleException(\Throwable $e, \Govorun\Messaging\IncomingMessage $message, MessengerDriver $driver): void
    {
        if ($this->bound('log')) {
            $this->make('log')->error($e->getMessage(), [
                'exception' => get_class($e),
                'user' => $message->user->id ?? null,
                'chat_id' => $message->chatId,
                'driver' => $message->driverName,
            ]);
        }

        $errorMessage = $this->make('config')->get('app.error_message', 'An error occurred.');

        try {
            $reply = \Govorun\Messaging\Message::make($errorMessage);
            $reply->chatId = $message->chatId;
            $driver->send($reply);
        } catch (\Throwable) {
            // If even the error response fails, silently swallow —
            // we must return 200 to prevent messenger retries
        }
    }

    /** Получить экземпляр хранилища состояний.
     * Если хранилище привязано в контейнере — возвращает его,
     * иначе использует файловое хранилище по умолчанию.
     * @return \Govorun\Contracts\StateStorage
     */
    protected function resolveStateStorage(): \Govorun\Contracts\StateStorage
    {
        if ($this->bound(\Govorun\Contracts\StateStorage::class)) {
            return $this->make(\Govorun\Contracts\StateStorage::class);
        }

        return new \Govorun\State\FileStateStorage($this->storagePath('state'));
    }

    /** Определить имя драйвера мессенджера из пути запроса.
     * @param Request $request HTTP-запрос
     * @return string Имя драйвера (например, "telegram")
     */
    protected function resolveDriverName(Request $request): string
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        return end($segments);
    }

    /** Создать экземпляр драйвера мессенджера по имени.
     * Поддерживает предварительно привязанные драйверы (для тестов)
     * и автоматическое разрешение по соглашению об именовании.
     * @param string $name Имя драйвера
     * @return MessengerDriver
     */
    protected function resolveDriver(string $name): MessengerDriver
    {
        // Allow pre-bound driver by name (for testing)
        if ($this->bound("driver.{$name}")) {
            return $this->make("driver.{$name}");
        }

        // Allow globally-bound driver (for testing)
        if ($this->bound(MessengerDriver::class)) {
            return $this->make(MessengerDriver::class);
        }

        // Convention: Govorun\Drivers\Telegram\TelegramDriver
        // All drivers take (array $config, ClientInterface $client) in constructor
        $className = 'Govorun\\Drivers\\' . ucfirst($name) . '\\' . ucfirst($name) . 'Driver';
        $config = $this->make('config')->get("messenger.{$name}", []);

        return new $className(config: $config, client: new \GuzzleHttp\Client());
    }
}
