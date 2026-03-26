<?php

namespace Govorun\Tests\Unit\Events;

use Govorun\Events\EventServiceProvider;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Illuminate\Events\Dispatcher;

class EventServiceProviderTest extends TestCase
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

    public function test_registers_event_dispatcher(): void
    {
        $provider = new EventServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(Dispatcher::class, $this->app->make('events'));
    }

    public function test_dispatcher_is_singleton(): void
    {
        $provider = new EventServiceProvider($this->app);
        $provider->register();

        $this->assertSame($this->app->make('events'), $this->app->make('events'));
    }

    public function test_boot_registers_listeners_from_config(): void
    {
        $provider = new EventServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $dispatcher = $this->app->make('events');

        $this->assertTrue($dispatcher->hasListeners('test.user.created'));
    }

    public function test_event_helper_dispatches_through_registered_dispatcher(): void
    {
        $provider = new EventServiceProvider($this->app);
        $provider->register();

        $dispatcher = $this->app->make('events');

        $received = null;
        $dispatcher->listen('order.placed', function ($data) use (&$received) {
            $received = $data;
        });

        event('order.placed', 'pizza');

        $this->assertSame('pizza', $received);
    }

    public function test_boot_handles_empty_events_config(): void
    {
        // Override config to have no events key
        $this->app->make('config')->set('events', null);

        $provider = new EventServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Should not throw
        $this->assertInstanceOf(Dispatcher::class, $this->app->make('events'));
    }
}
