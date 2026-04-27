<?php

namespace Govorun\Tests\Unit\Log;

use Govorun\Foundation\Application;
use Govorun\Log\Log;
use Govorun\Log\LogServiceProvider;
use Govorun\Tests\TestCase;

class LogFacadeTest extends TestCase
{
    private Application $app;
    private string $logFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logFile = sys_get_temp_dir() . '/govorun-test-' . uniqid() . '.log';
        putenv('GOVORUN_TEST_LOG_PATH=' . $this->logFile);

        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();

        $provider = new LogServiceProvider($this->app);
        $provider->register();
    }

    protected function tearDown(): void
    {
        $this->app->flush();
        Application::setInstance(null);
        putenv('GOVORUN_TEST_LOG_PATH');
        gc_collect_cycles();

        if (file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
        parent::tearDown();
    }

    public function test_info_writes_to_log(): void
    {
        Log::info('Info message');

        $this->assertStringContainsString('Info message', file_get_contents($this->logFile));
    }

    public function test_error_writes_to_log(): void
    {
        Log::error('Error occurred', ['code' => 500]);

        $contents = file_get_contents($this->logFile);
        $this->assertStringContainsString('Error occurred', $contents);
        $this->assertStringContainsString('500', $contents);
    }

    public function test_debug_writes_to_log(): void
    {
        Log::debug('Debug data');

        $this->assertStringContainsString('Debug data', file_get_contents($this->logFile));
    }

    public function test_warning_writes_to_log(): void
    {
        Log::warning('Warning message');

        $this->assertStringContainsString('Warning message', file_get_contents($this->logFile));
    }
}
