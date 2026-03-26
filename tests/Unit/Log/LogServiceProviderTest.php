<?php

namespace Govorun\Tests\Unit\Log;

use Govorun\Foundation\Application;
use Govorun\Log\LogServiceProvider;
use Govorun\Tests\TestCase;
use Psr\Log\LoggerInterface;

class LogServiceProviderTest extends TestCase
{
    private Application $app;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();
        $this->logFile = sys_get_temp_dir() . '/govorun-test.log';

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    protected function tearDown(): void
    {
        $this->app->flush();
        Application::setInstance(null);
        gc_collect_cycles();

        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function test_registers_logger(): void
    {
        $provider = new LogServiceProvider($this->app);
        $provider->register();

        $this->assertInstanceOf(LoggerInterface::class, $this->app->make('log'));
    }

    public function test_logger_is_singleton(): void
    {
        $provider = new LogServiceProvider($this->app);
        $provider->register();

        $this->assertSame($this->app->make('log'), $this->app->make('log'));
    }

    public function test_logger_writes_to_configured_path(): void
    {
        $provider = new LogServiceProvider($this->app);
        $provider->register();

        $logger = $this->app->make('log');
        $logger->info('Test log message');

        $this->assertFileExists($this->logFile);
        $this->assertStringContainsString('Test log message', file_get_contents($this->logFile));
    }

    public function test_logger_respects_channel_level(): void
    {
        // The 'single' channel is set to 'debug' level — all messages should be logged
        $provider = new LogServiceProvider($this->app);
        $provider->register();

        $logger = $this->app->make('log');
        $logger->debug('Debug message');

        $this->assertStringContainsString('Debug message', file_get_contents($this->logFile));
    }
}
