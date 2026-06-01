<?php

namespace Govorun\Tests\Unit\Drivers\Whatsapp;

use Govorun\Tests\TestCase;
use Govorun\Drivers\Whatsapp\WhatsappDriver;
use Govorun\Drivers\Whatsapp\WhatsappProfileSyncer;

/** Тесты синхронизатора бизнес-профиля WhatsApp. */
class WhatsappProfileSyncerTest extends TestCase
{
    private function syncer($driver, array $profile = ['about' => 'Привет', 'description' => 'Опис', 'vertical' => 'RETAIL']): WhatsappProfileSyncer
    {
        return new WhatsappProfileSyncer($driver, $profile, 'TOK');
    }

    public function test_messenger_and_sections(): void
    {
        $syncer = $this->syncer($this->createMock(WhatsappDriver::class));

        $this->assertSame('whatsapp', $syncer->messenger());
        $this->assertSame(['about', 'description', 'address', 'email', 'websites', 'vertical', 'photo'], $syncer->sections());
    }

    public function test_sync_about_pushes_when_changed(): void
    {
        $driver = $this->createMock(WhatsappDriver::class);
        $driver->method('getBusinessProfile')->willReturn(['about' => 'Старое']);
        $driver->expects($this->once())->method('setBusinessProfile')->with(['about' => 'Привет']);

        $this->assertTrue($this->syncer($driver)->sync('about'));
    }

    public function test_sync_about_skips_when_unchanged(): void
    {
        $driver = $this->createMock(WhatsappDriver::class);
        $driver->method('getBusinessProfile')->willReturn(['about' => 'Привет']);
        $driver->expects($this->never())->method('setBusinessProfile');

        $this->assertFalse($this->syncer($driver)->sync('about'));
    }

    public function test_sync_websites_pushes_array(): void
    {
        $driver = $this->createMock(WhatsappDriver::class);
        $driver->method('getBusinessProfile')->willReturn(['websites' => []]);
        $driver->expects($this->once())->method('setBusinessProfile')->with(['websites' => ['https://a.com']]);

        $syncer = $this->syncer($driver, ['websites' => ['https://a.com']]);
        $this->assertTrue($syncer->sync('websites'));
    }
}