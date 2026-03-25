<?php

namespace Govorun\Foundation;

use Dotenv\Dotenv;
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
}
