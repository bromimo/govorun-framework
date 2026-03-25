<?php

namespace Govorun\Foundation;

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
}
