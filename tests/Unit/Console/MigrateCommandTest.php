<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Console\ConsoleServiceProvider;
use Govorun\Foundation\Application;
use Govorun\Tests\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MigrateCommandTest extends TestCase
{
    private Application $app;
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = new Application(dirname(__DIR__, 2) . '/fixtures');
        $this->app->loadConfiguration();

        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();

        $this->app->instance('db', $this->capsule->getConnection());
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

    public function test_migrate_creates_govorun_states_table(): void
    {
        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'migrate']), $output);

        $this->assertSame(0, $exitCode);
        $this->assertTrue(
            $this->capsule->getConnection()->getSchemaBuilder()->hasTable('govorun_states')
        );
        $this->assertStringContainsString('Migrated: CreateGovorunStatesTable', $output->fetch());
    }
}
