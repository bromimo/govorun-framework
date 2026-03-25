<?php

namespace Govorun\Tests\Unit\Foundation;

use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;

class ApplicationTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_sets_base_path(): void
    {
        $app = new Application('/var/www/bot');
        $this->assertSame('/var/www/bot', $app->basePath());
    }

    public function test_generates_sub_paths(): void
    {
        $ds = DIRECTORY_SEPARATOR;
        $app = new Application('/var/www/bot');

        $this->assertSame("/var/www/bot{$ds}config", $app->configPath());
        $this->assertSame("/var/www/bot{$ds}config{$ds}app.php", $app->configPath('app.php'));
        $this->assertSame("/var/www/bot{$ds}storage", $app->storagePath());
        $this->assertSame("/var/www/bot{$ds}storage{$ds}logs", $app->storagePath('logs'));
        $this->assertSame("/var/www/bot{$ds}database", $app->databasePath());
        $this->assertSame("/var/www/bot{$ds}database{$ds}migrations", $app->databasePath('migrations'));
    }

    public function test_sets_singleton_instance(): void
    {
        $app = new Application('/var/www/bot');
        $this->assertSame($app, Application::getInstance());
    }

    public function test_resolves_itself_from_container(): void
    {
        $app = new Application('/var/www/bot');
        $this->assertSame($app, $app->make('app'));
        $this->assertSame($app, $app->make(Application::class));
    }

    public function test_trims_trailing_slashes_from_base_path(): void
    {
        $app = new Application('/var/www/bot/');
        $this->assertSame('/var/www/bot', $app->basePath());
    }

    public function test_loads_configuration_from_files(): void
    {
        $app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $app->loadConfiguration();

        $config = $app->make('config');

        $this->assertSame('Test Bot', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertSame('Europe/Moscow', $config->get('app.timezone'));
        $this->assertSame(['telegram'], $config->get('messenger.drivers'));
        $this->assertSame('test-token', $config->get('messenger.telegram.token'));
    }

    public function test_config_returns_default_for_missing_key(): void
    {
        $app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $app->loadConfiguration();

        $config = $app->make('config');

        $this->assertNull($config->get('app.nonexistent'));
        $this->assertSame('fallback', $config->get('app.nonexistent', 'fallback'));
    }

    public function test_loads_environment_variables(): void
    {
        $app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $app->loadEnvironment();

        $this->assertSame('Env Bot', $_ENV['APP_NAME'] ?? getenv('APP_NAME'));
        $this->assertSame('env-token-123', $_ENV['TELEGRAM_TOKEN'] ?? getenv('TELEGRAM_TOKEN'));

        // Cleanup to prevent state leakage
        unset($_ENV['APP_NAME'], $_ENV['APP_DEBUG'], $_ENV['TELEGRAM_TOKEN']);
        putenv('APP_NAME');
        putenv('APP_DEBUG');
        putenv('TELEGRAM_TOKEN');
    }

    public function test_skips_env_loading_when_no_env_file(): void
    {
        $app = new Application('/nonexistent/path');

        // Should not throw
        $app->loadEnvironment();
        $this->assertTrue(true);
    }
}
