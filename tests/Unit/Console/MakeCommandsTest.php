<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MakeCommandsTest extends TestCase
{
    private Application $app;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/govorun_make_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create minimal config directory so loadConfiguration() works
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);
        file_put_contents($configDir . '/app.php', '<?php return ["providers" => []];');
        file_put_contents($configDir . '/state.php', '<?php return ["driver" => "file"];');
        file_put_contents($configDir . '/logging.php', '<?php return ["default" => "single", "channels" => ["single" => ["driver" => "single", "path" => sys_get_temp_dir() . "/govorun-make-test.log", "level" => "debug"]]];');
        file_put_contents($configDir . '/events.php', '<?php return [];');

        $this->app = new Application($this->tempDir);
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
        $this->deleteDirectory($this->tempDir);
        Application::setInstance(null);
        $logFile = sys_get_temp_dir() . '/govorun-make-test.log';
        if (file_exists($logFile)) {
            unlink($logFile);
        }
        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function test_make_controller_creates_file(): void
    {
        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:controller', 'name' => 'StartController']),
            $output
        );

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Controllers/StartController.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('class StartController extends Controller', file_get_contents($file));
    }

    public function test_make_flow_creates_file(): void
    {
        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:flow', 'name' => 'AppointmentFlow']),
            $output
        );

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Flows/AppointmentFlow.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('class AppointmentFlow extends Flow', file_get_contents($file));
    }

    public function test_make_api_client_creates_file(): void
    {
        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:api-client', 'name' => 'RecordApiClient']),
            $output
        );

        $this->assertSame(0, $exitCode);
        $file = $this->tempDir . '/app/Services/RecordApiClient.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('class RecordApiClient extends ApiClient', file_get_contents($file));
    }

    public function test_make_controller_fails_if_exists(): void
    {
        $dir = $this->tempDir . '/app/Controllers';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/StartController.php', '<?php // existing');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:controller', 'name' => 'StartController']),
            $output
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', $output->fetch());
    }

    public function test_make_flow_fails_if_exists(): void
    {
        $dir = $this->tempDir . '/app/Flows';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/AppointmentFlow.php', '<?php // existing');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:flow', 'name' => 'AppointmentFlow']),
            $output
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', $output->fetch());
    }

    public function test_make_api_client_fails_if_exists(): void
    {
        $dir = $this->tempDir . '/app/Services';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/RecordApiClient.php', '<?php // existing');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(
            new ArrayInput(['command' => 'make:api-client', 'name' => 'RecordApiClient']),
            $output
        );

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('already exists', $output->fetch());
    }
}
