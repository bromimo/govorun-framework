<?php

namespace Govorun\Tests\Unit\Drivers\Vk;

use GuzzleHttp\Client;
use Govorun\Tests\TestCase;
use Govorun\Drivers\Vk\VkDriver;
use Govorun\Exceptions\WebhookManualSetupException;

class VkDriverWebhookTest extends TestCase
{
    private function driver(): VkDriver
    {
        return new VkDriver(config: ['token' => 't', 'confirmation' => 'c'], client: new Client());
    }

    public function test_install_webhook_throws_manual_setup(): void
    {
        $this->expectException(WebhookManualSetupException::class);
        $this->driver()->installWebhook('https://bot.example.com/webhook/vk');
    }

    public function test_remove_webhook_throws_manual_setup(): void
    {
        $this->expectException(WebhookManualSetupException::class);
        $this->driver()->removeWebhook();
    }
}