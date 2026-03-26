<?php

namespace Govorun\Tests\Unit\Foundation;

use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Illuminate\Events\Dispatcher;
use Psr\Log\LoggerInterface;

class CoreProvidersTest extends TestCase
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
        $this->app->flush();
        gc_collect_cycles();
        $logFile = sys_get_temp_dir() . '/govorun-test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        parent::tearDown();
    }

    public function test_registers_core_providers_on_boot(): void
    {
        $this->app->registerCoreProviders();
        $this->app->boot();

        $this->assertInstanceOf(Dispatcher::class, $this->app->make('events'));
        $this->assertInstanceOf(LoggerInterface::class, $this->app->make('log'));
    }

    public function test_core_providers_registered_before_user_providers(): void
    {
        $this->app->registerCoreProviders();
        $this->app->registerConfiguredProviders();
        $this->app->boot();

        // Core providers should already be registered, so 'events' is available
        $this->assertInstanceOf(Dispatcher::class, $this->app->make('events'));
    }

    public function test_event_listeners_from_config_are_registered_after_boot(): void
    {
        $this->app->registerCoreProviders();
        $this->app->boot();

        $dispatcher = $this->app->make('events');

        $this->assertTrue($dispatcher->hasListeners('test.user.created'));
    }
}
