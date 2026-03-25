<?php

namespace Govorun\Foundation;

use Dotenv\Dotenv;
use Govorun\Contracts\MessengerDriver;
use Govorun\Http\Request;
use Govorun\Routing\Router;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;

class Application extends Container
{
    protected string $basePath;

    protected bool $booted = false;

    /** @var ServiceProvider[] */
    protected array $serviceProviders = [];

    /** @var array<class-string, true> */
    protected array $loadedProviders = [];

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');

        static::setInstance($this);

        $this->registerBaseBindings();
    }

    protected function registerBaseBindings(): void
    {
        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance(Container::class, $this);
    }

    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function configPath(string $path = ''): string
    {
        return $this->basePath('config') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

    public function databasePath(string $path = ''): string
    {
        return $this->basePath('database') . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
    }

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

    public function isBooted(): bool
    {
        return $this->booted;
    }

    public function loadEnvironment(): void
    {
        if (file_exists($this->basePath('.env'))) {
            Dotenv::createImmutable($this->basePath())->load();
        }
    }

    public function loadConfiguration(): void
    {
        $config = new ConfigRepository();

        foreach (glob($this->configPath('*.php')) ?: [] as $file) {
            $key = basename($file, '.php');
            $config->set($key, require $file);
        }

        $this->instance('config', $config);
    }

    public function registerConfiguredProviders(): void
    {
        $providers = $this->make('config')->get('app.providers', []);

        foreach ($providers as $providerClass) {
            $this->register(new $providerClass($this));
        }
    }

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

    protected function handleException(\Throwable $e, \Govorun\Messaging\IncomingMessage $message, MessengerDriver $driver): void
    {
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

    protected function resolveStateStorage(): \Govorun\Contracts\StateStorage
    {
        if ($this->bound(\Govorun\Contracts\StateStorage::class)) {
            return $this->make(\Govorun\Contracts\StateStorage::class);
        }

        return new \Govorun\State\FileStateStorage($this->storagePath('state'));
    }

    protected function resolveDriverName(Request $request): string
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);

        return end($segments);
    }

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
