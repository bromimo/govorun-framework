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

    public function test_sync_skip_photo_does_not_touch_photo(): void
    {
        $driver = $this->bindDriver();
        $driver->expects($this->once())->method('setMyName');
        $driver->expects($this->once())->method('setMyShortDescription');
        $driver->expects($this->once())->method('setMyDescription');
        $driver->expects($this->once())->method('setMyCommands');
        $driver->expects($this->never())->method('removeMyProfilePhoto');
        $driver->expects($this->never())->method('setMyProfilePhoto');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput([
            'command' => 'bot:profile-sync',
            '--skip' => ['photo'],
        ]), $output);

        $this->assertSame(0, $exitCode, $output->fetch());
    }

    public function test_sync_only_commands_runs_only_commands(): void
    {
        $driver = $this->bindDriver();
        $driver->expects($this->never())->method('setMyName');
        $driver->expects($this->never())->method('setMyShortDescription');
        $driver->expects($this->never())->method('setMyDescription');
        $driver->expects($this->once())->method('setMyCommands');
        $driver->expects($this->never())->method('removeMyProfilePhoto');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput([
            'command' => 'bot:profile-sync',
            '--only' => ['commands'],
        ]), $output);

        $this->assertSame(0, $exitCode);
    }

    public function test_sync_invalid_only_returns_failure(): void
    {
        $this->bindDriver();

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput([
            'command' => 'bot:profile-sync',
            '--only' => ['bogus'],
        ]), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Неизвестные секции: bogus', $output->fetch());
    }

    public function test_sync_continues_on_error_and_reports(): void
    {
        $driver = $this->bindDriver();
        $driver->expects($this->once())->method('setMyName');
        $driver->expects($this->once())->method('setMyShortDescription')
            ->willThrowException(new \RuntimeException('Bad gateway'));
        $driver->expects($this->once())->method('setMyDescription');
        $driver->expects($this->once())->method('setMyCommands');
        $driver->expects($this->once())->method('removeMyProfilePhoto');

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'bot:profile-sync']), $output);

        $this->assertSame(1, $exitCode);
        $text = $output->fetch();
        $this->assertStringContainsString('✓ name', $text);
        $this->assertStringContainsString('✗ short_description: Bad gateway', $text);
        $this->assertStringContainsString('✓ description', $text);
        $this->assertStringContainsString('Ошибки в 1 секциях', $text);
    }

    public function test_sync_without_config_returns_failure(): void
    {
        app('config')->set('bot_profile', null);
        $this->bindDriver();

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'bot:profile-sync']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Профиль не найден', $output->fetch());
    }

    public function test_sync_without_token_returns_failure(): void
    {
        app('config')->set('messenger.telegram.token', '');
        $this->bindDriver();

        $artisan = $this->app->make('artisan');
        $output = new BufferedOutput();
        $exitCode = $artisan->run(new ArrayInput(['command' => 'bot:profile-sync']), $output);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Telegram-токен не задан', $output->fetch());
    }

    public function test_sync_photo_uses_static_when_jpg_exists(): void
    {
        $jpg = storage_path('app/bot-profile.jpg');
        @mkdir(dirname($jpg), 0755, true);
        file_put_contents($jpg, 'fake-jpg');

        try {
            $driver = $this->bindDriver();
            $driver->expects($this->once())->method('setMyProfilePhoto')->with($jpg, 'static');
            $driver->expects($this->never())->method('removeMyProfilePhoto');

            $artisan = $this->app->make('artisan');
            $artisan->run(new ArrayInput(['command' => 'bot:profile-sync', '--only' => ['photo']]), new BufferedOutput());
        } finally {
            @unlink($jpg);
        }
    }

    public function test_sync_photo_uses_animated_when_only_mp4_exists(): void
    {
        $mp4 = storage_path('app/bot-profile.mp4');
        @mkdir(dirname($mp4), 0755, true);
        file_put_contents($mp4, 'fake-mp4');

        try {
            $driver = $this->bindDriver();
            $driver->expects($this->once())->method('setMyProfilePhoto')->with($mp4, 'animated');
            $driver->expects($this->never())->method('removeMyProfilePhoto');

            $artisan = $this->app->make('artisan');
            $artisan->run(new ArrayInput(['command' => 'bot:profile-sync', '--only' => ['photo']]), new BufferedOutput());
        } finally {
            @unlink($mp4);
        }
    }
}
