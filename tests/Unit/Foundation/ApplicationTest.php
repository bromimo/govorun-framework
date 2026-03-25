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
}
