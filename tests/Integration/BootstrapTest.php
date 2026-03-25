<?php

namespace Govorun\Tests\Integration;

use Govorun\Foundation\Application;
use Govorun\Foundation\ServiceProvider;
use Govorun\Tests\TestCase;
use Illuminate\Config\Repository as ConfigRepository;

class BootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_full_bootstrap_cycle(): void
    {
        // 1. Create application
        $app = new Application(dirname(__DIR__) . '/fixtures');

        // 2. Load environment
        $app->loadEnvironment();

        // 3. Load configuration
        $app->loadConfiguration();

        // 4. Verify config is accessible
        $config = $app->make('config');
        $this->assertInstanceOf(ConfigRepository::class, $config);
        $this->assertSame('Test Bot', $config->get('app.name'));
        $this->assertSame(['telegram'], $config->get('messenger.drivers'));

        // 5. Register a test provider
        $provider = new class($app) extends ServiceProvider {
            public bool $registered = false;
            public bool $booted = false;

            public function register(): void
            {
                $this->registered = true;
                $this->app->bind('test.service', fn () => 'it works');
            }

            public function boot(): void
            {
                $this->booted = true;
            }
        };

        $app->register($provider);
        $this->assertTrue($provider->registered);

        // 6. Boot the application
        $app->boot();
        $this->assertTrue($provider->booted);

        // 7. Resolve bound service
        $this->assertSame('it works', $app->make('test.service'));

        // 8. Helpers work
        $this->assertSame($app, app());
        $this->assertSame('Test Bot', config('app.name'));
    }

    public function test_bootstrap_with_configured_providers(): void
    {
        // Create a temporary config directory with providers
        $tmpDir = sys_get_temp_dir() . '/govorun_test_' . uniqid();
        mkdir($tmpDir . '/config', 0777, true);

        file_put_contents($tmpDir . '/config/app.php', '<?php return [
            "name" => "Provider Test",
            "providers" => [],
        ];');

        $app = new Application($tmpDir);
        $app->loadConfiguration();
        $app->registerConfiguredProviders();
        $app->boot();

        $this->assertSame('Provider Test', config('app.name'));
        $this->assertTrue($app->isBooted());

        // Cleanup
        unlink($tmpDir . '/config/app.php');
        rmdir($tmpDir . '/config');
        rmdir($tmpDir);
    }
}
