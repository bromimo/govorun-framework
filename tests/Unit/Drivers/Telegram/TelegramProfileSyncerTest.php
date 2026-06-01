<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use Govorun\Tests\TestCase;
use Govorun\Drivers\Telegram\TelegramDriver;
use Govorun\Drivers\Telegram\TelegramProfileSyncer;

/** Тесты синхронизатора профиля Telegram. */
class TelegramProfileSyncerTest extends TestCase
{
    private function syncer($driver, array $profile = ['name' => 'Бот', 'short_description' => 's', 'description' => 'd', 'commands' => []], string $token = 'tok'): TelegramProfileSyncer
    {
        return new TelegramProfileSyncer($driver, $profile, $token);
    }

    public function test_messenger_and_sections(): void
    {
        $syncer = $this->syncer($this->createMock(TelegramDriver::class));

        $this->assertSame('telegram', $syncer->messenger());
        $this->assertSame(['name', 'short_description', 'description', 'commands', 'photo'], $syncer->sections());
    }

    public function test_is_configured_false_without_token(): void
    {
        $syncer = $this->syncer($this->createMock(TelegramDriver::class), token: '');

        $this->assertFalse($syncer->isConfigured());
    }

    public function test_sync_name_pushes_when_changed(): void
    {
        $driver = $this->createMock(TelegramDriver::class);
        $driver->method('getMyName')->willReturn('Старое');
        $driver->expects($this->once())->method('setMyName')->with('Бот');

        $this->assertTrue($this->syncer($driver)->sync('name'));
    }

    public function test_sync_name_skips_when_unchanged(): void
    {
        $driver = $this->createMock(TelegramDriver::class);
        $driver->method('getMyName')->willReturn('Бот');
        $driver->expects($this->never())->method('setMyName');

        $this->assertFalse($this->syncer($driver)->sync('name'));
    }
}