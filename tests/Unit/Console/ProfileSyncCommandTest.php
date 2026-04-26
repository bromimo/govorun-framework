<?php

namespace Govorun\Tests\Unit\Console;

use Govorun\Tests\TestCase;
use Govorun\Foundation\Application;
use Govorun\Console\ConsoleServiceProvider;
use Govorun\Drivers\Telegram\TelegramDriver;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** Тесты artisan-команды `bot:profile-sync` синхронизации Telegram-профиля бота. */
class ProfileSyncCommandTest extends TestCase
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
        parent::tearDown();
    }

    private function bindDriver(): TelegramDriver
    {
        $driver = $this->createMock(TelegramDriver::class);
        $this->app->instance('driver.telegram', $driver);

        return $driver;
    }

    public function test_sync_calls_all_text_methods(): void
    {
        $driver = $this->bindDriver();
        $driver->expects($this->once())->method('setMyName')->with('Test Bot');
        $driver->expects($this->once())->method('setMyShortDescription')->with('About test');
        $driver->expects($this->once())->method('setMyDescription')->with('Long description for test');
        $driver->expects($this->once())->method('setMyCommands')->with([
            ['command' => 'start', 'description' => 'Start the bot'],
            ['command' => 'help',  'description' => 'Show help'],
        ]);
        $driver->expects($this->once())->method('removeMyProfilePhoto');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'bot:profile-sync']), $output);

        $text = $output->fetch();
        $this->assertSame(0, $exitCode, $text);
        $this->assertStringContainsString('✓ name', $text);
    }
}
