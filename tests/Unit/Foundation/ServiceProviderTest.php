<?php

namespace Govorun\Tests\Unit\Foundation;

use Govorun\Foundation\Application;
use Govorun\Foundation\ServiceProvider;
use Govorun\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_register_and_boot_are_callable(): void
    {
        $app = new Application('/var/www/bot');
        $provider = new class($app) extends ServiceProvider {
            public bool $registered = false;
            public bool $booted = false;

            public function register(): void
            {
                $this->registered = true;
            }

            public function boot(): void
            {
                $this->booted = true;
            }
        };

        $provider->register();
        $this->assertTrue($provider->registered);

        $provider->boot();
        $this->assertTrue($provider->booted);
    }

    public function test_application_registers_provider(): void
    {
        $app = new Application('/var/www/bot');
        $provider = new class($app) extends ServiceProvider {
            public bool $registered = false;

            public function register(): void
            {
                $this->registered = true;
            }
        };

        $app->register($provider);
        $this->assertTrue($provider->registered);
    }

    public function test_application_boots_providers(): void
    {
        $app = new Application('/var/www/bot');
        $provider = new class($app) extends ServiceProvider {
            public bool $booted = false;

            public function boot(): void
            {
                $this->booted = true;
            }
        };

        $app->register($provider);
        $app->boot();
        $this->assertTrue($provider->booted);
    }

    public function test_provider_registered_only_once(): void
    {
        $app = new Application('/var/www/bot');
        $provider = new class($app) extends ServiceProvider {
            public int $callCount = 0;

            public function register(): void
            {
                $this->callCount++;
            }
        };

        $app->register($provider);
        $app->register($provider);
        $this->assertSame(1, $provider->callCount);
    }

    public function test_late_registered_provider_boots_immediately(): void
    {
        $app = new Application('/var/www/bot');
        $app->boot();

        $provider = new class($app) extends ServiceProvider {
            public bool $booted = false;

            public function boot(): void
            {
                $this->booted = true;
            }
        };

        $app->register($provider);
        $this->assertTrue($provider->booted);
    }
}
