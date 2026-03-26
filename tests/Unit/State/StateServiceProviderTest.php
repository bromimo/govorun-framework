<?php

namespace Govorun\Tests\Unit\State;

use Govorun\Contracts\StateStorage;
use Govorun\Foundation\Application;
use Govorun\State\CacheStateStorage;
use Govorun\State\DatabaseStateStorage;
use Govorun\State\FileStateStorage;
use Govorun\State\StateServiceProvider;
use Govorun\Tests\TestCase;

class StateServiceProviderTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_resolves_file_storage_by_default(): void
    {
        $provider = new StateServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(FileStateStorage::class, $this->app->make(StateStorage::class));
    }

    public function test_resolves_cache_storage_when_configured(): void
    {
        $this->app->make('config')->set('state.driver', 'cache');

        $cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $this->app->instance('cache', $cache);

        $provider = new StateServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(CacheStateStorage::class, $this->app->make(StateStorage::class));
    }

    public function test_resolves_database_storage_when_configured(): void
    {
        $this->app->make('config')->set('state.driver', 'database');

        $capsule = new \Illuminate\Database\Capsule\Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->app->instance('db', $capsule->getConnection());

        $provider = new StateServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(DatabaseStateStorage::class, $this->app->make(StateStorage::class));
    }

    public function test_storage_is_singleton(): void
    {
        $provider = new StateServiceProvider($this->app);
        $provider->register();

        $this->assertSame(
            $this->app->make(StateStorage::class),
            $this->app->make(StateStorage::class),
        );
    }

    public function test_cache_storage_uses_ttl_from_config(): void
    {
        $this->app->make('config')->set('state.driver', 'cache');
        $this->app->make('config')->set('state.ttl', 7200);

        $cache = new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore());
        $this->app->instance('cache', $cache);

        $provider = new StateServiceProvider($this->app);
        $provider->register();

        $storage = $this->app->make(StateStorage::class);
        $this->assertInstanceOf(CacheStateStorage::class, $storage);
    }
}
