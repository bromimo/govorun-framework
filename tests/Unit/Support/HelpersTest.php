<?php

namespace Govorun\Tests\Unit\Support;

use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;

class HelpersTest extends TestCase
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

    public function test_app_returns_application_instance(): void
    {
        $this->assertSame($this->app, app());
    }

    public function test_app_resolves_abstract(): void
    {
        $this->assertSame($this->app->make('config'), app('config'));
    }

    public function test_config_gets_value(): void
    {
        $this->assertSame('Test Bot', config('app.name'));
    }

    public function test_config_returns_default(): void
    {
        $this->assertSame('fallback', config('app.missing', 'fallback'));
    }

    public function test_config_sets_values_with_array(): void
    {
        config(['app.name' => 'New Name']);
        $this->assertSame('New Name', config('app.name'));
    }

    public function test_base_path_returns_base_path(): void
    {
        $this->assertSame($this->app->basePath(), base_path());
        $this->assertSame($this->app->basePath('src'), base_path('src'));
    }

    public function test_config_path_returns_config_path(): void
    {
        $this->assertSame($this->app->configPath(), config_path());
    }

    public function test_storage_path_returns_storage_path(): void
    {
        $this->assertSame($this->app->storagePath(), storage_path());
        $this->assertSame($this->app->storagePath('logs'), storage_path('logs'));
    }

    public function test_database_path_returns_database_path(): void
    {
        $this->assertSame($this->app->databasePath(), database_path());
    }

    public function test_env_returns_environment_variable(): void
    {
        $_ENV['GOVORUN_TEST_VAR'] = 'test_value';
        $this->assertSame('test_value', env('GOVORUN_TEST_VAR'));
        unset($_ENV['GOVORUN_TEST_VAR']);
    }

    public function test_env_returns_default_when_not_set(): void
    {
        $this->assertSame('default', env('GOVORUN_NONEXISTENT', 'default'));
    }

    public function test_env_casts_boolean_strings(): void
    {
        $_ENV['GOVORUN_BOOL_TRUE'] = 'true';
        $_ENV['GOVORUN_BOOL_FALSE'] = 'false';
        $this->assertTrue(env('GOVORUN_BOOL_TRUE'));
        $this->assertFalse(env('GOVORUN_BOOL_FALSE'));
        unset($_ENV['GOVORUN_BOOL_TRUE'], $_ENV['GOVORUN_BOOL_FALSE']);
    }

    public function test_env_casts_null_string(): void
    {
        $_ENV['GOVORUN_NULL'] = 'null';
        $this->assertNull(env('GOVORUN_NULL'));
        unset($_ENV['GOVORUN_NULL']);
    }

    public function test_env_casts_empty_string(): void
    {
        $_ENV['GOVORUN_EMPTY'] = 'empty';
        $this->assertSame('', env('GOVORUN_EMPTY'));
        unset($_ENV['GOVORUN_EMPTY']);
    }

    public function test_event_dispatches_through_events(): void
    {
        $dispatcher = new \Illuminate\Events\Dispatcher($this->app);
        $this->app->instance('events', $dispatcher);

        $fired = false;
        $dispatcher->listen('test.event', function () use (&$fired) {
            $fired = true;
        });

        $dispatcher->dispatch('test.event');

        $this->assertTrue($fired);
    }
}
