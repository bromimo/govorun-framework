<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class StateClearCommandTest extends TestCase
{
    private Application $app;
    private string $statePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();
        $this->app->registerCoreProviders();

        $provider = new ConsoleServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        $this->statePath = $this->app->storagePath('state');
    }

    protected function tearDown(): void
    {
        // Clean up state directory
        if (is_dir($this->statePath)) {
            foreach (glob($this->statePath . '/*.json') as $file) {
                unlink($file);
            }
            rmdir($this->statePath);
        }
        $this->app->flush();
        gc_collect_cycles();
        Application::setInstance(null);
        $logFile = sys_get_temp_dir() . '/govorun-test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        parent::tearDown();
    }

    public function test_clears_file_state_storage(): void
    {
        // Create some state files
        if (! is_dir($this->statePath)) {
            mkdir($this->statePath, 0777, true);
        }
        file_put_contents($this->statePath . '/100_telegram.json', '{"flow_class":"Test"}');
        file_put_contents($this->statePath . '/200_viber.json', '{"flow_class":"Test"}');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'state:clear']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Cleared 2 state file(s)', $output->fetch());
        $this->assertEmpty(glob($this->statePath . '/*.json'));
    }

    public function test_clears_empty_file_storage(): void
    {
        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'state:clear']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No state files found', $output->fetch());
    }
}
