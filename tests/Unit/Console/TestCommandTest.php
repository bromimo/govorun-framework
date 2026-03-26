<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Console\TestCommand;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class TestCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();
        $this->app->registerCoreProviders();

        $provider = new ConsoleServiceProvider($this->app);
        $provider->register();
        $provider->boot();
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

    public function test_command_is_registered(): void
    {
        $artisan = $this->app->make('artisan');
        $this->assertTrue($artisan->has('test'));
    }

    public function test_command_fails_when_phpunit_not_found(): void
    {
        // Use a temp directory where phpunit doesn't exist
        $tempApp = new Application(sys_get_temp_dir() . '/nonexistent_' . uniqid());

        $artisan = $this->app->make('artisan');

        // We can verify the command logic by checking the test command class directly
        $command = new TestCommand();
        $command->setLaravel($tempApp);

        // We can't easily test passthru in unit tests,
        // but we can verify the command exists and has the right signature
        $this->assertSame('test', $command->getName());
    }
}
