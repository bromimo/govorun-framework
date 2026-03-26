<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Contracts\MessengerDriver;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class WebhookCommandsTest extends TestCase
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

    public function test_webhook_install_calls_install_on_drivers(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('installWebhook')
            ->with('https://example.com/webhook/telegram')
            ->willReturn(true);

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:install']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Webhook installed for telegram', $output->fetch());
    }

    public function test_webhook_install_returns_failure_when_driver_fails(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('installWebhook')
            ->willReturn(false);

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:install']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to install webhook for telegram', $output->fetch());
    }

    public function test_webhook_install_returns_failure_on_exception(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('installWebhook')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:install']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Error installing webhook for telegram: Connection refused', $output->fetch());
    }

    public function test_webhook_remove_calls_remove_on_drivers(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('removeWebhook')
            ->willReturn(true);

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:remove']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Webhook removed for telegram', $output->fetch());
    }

    public function test_webhook_remove_returns_failure_when_driver_fails(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('removeWebhook')
            ->willReturn(false);

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:remove']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Failed to remove webhook for telegram', $output->fetch());
    }

    public function test_webhook_remove_returns_failure_on_exception(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('removeWebhook')
            ->willThrowException(new \RuntimeException('Connection refused'));

        $this->app->instance('driver.telegram', $driver);

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'webhook:remove']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Error removing webhook for telegram: Connection refused', $output->fetch());
    }
}
