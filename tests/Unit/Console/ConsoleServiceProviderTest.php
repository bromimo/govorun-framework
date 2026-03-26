<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Illuminate\Console\Application as Artisan;

class ConsoleServiceProviderTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();
        $this->app->registerCoreProviders();
    }

    protected function tearDown(): void
    {
        $this->app->flush();
        gc_collect_cycles();
        Application::setInstance(null);
        $logFile = sys_get_temp_dir() . '/govorun-test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        parent::tearDown();
    }

    public function test_registers_artisan_instance(): void
    {
        $provider = new ConsoleServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(Artisan::class, $this->app->make('artisan'));
    }

    public function test_registers_all_framework_commands(): void
    {
        $provider = new ConsoleServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $artisan = $this->app->make('artisan');

        $this->assertTrue($artisan->has('webhook:install'));
        $this->assertTrue($artisan->has('webhook:remove'));
        $this->assertTrue($artisan->has('migrate'));
        $this->assertTrue($artisan->has('make:controller'));
        $this->assertTrue($artisan->has('make:flow'));
        $this->assertTrue($artisan->has('make:api-client'));
        $this->assertTrue($artisan->has('state:clear'));
        $this->assertTrue($artisan->has('test'));
    }
}
