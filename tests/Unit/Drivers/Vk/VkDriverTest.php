<?php

namespace Govorun\Tests\Unit\Drivers\Vk;

use GuzzleHttp\Client;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use Govorun\Drivers\Vk\VkDriver;
use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\WebhookResponder;

class VkDriverTest extends TestCase
{
    private function makeDriver(array $config = []): VkDriver
    {
        return new VkDriver(
            config: array_merge(['token' => 't', 'confirmation' => 'conf-code'], $config),
            client: new Client(),
        );
    }

    private function request(array $body): Request
    {
        return new Request(
            server: ['REQUEST_URI' => '/webhook/vk', 'REQUEST_METHOD' => 'POST'],
            content: json_encode($body),
        );
    }

    public function test_implements_contracts(): void
    {
        $driver = $this->makeDriver();
        $this->assertInstanceOf(MessengerDriver::class, $driver);
        $this->assertInstanceOf(WebhookResponder::class, $driver);
    }

    public function test_verify_passes_when_no_secret_configured(): void
    {
        $this->assertTrue($this->makeDriver()->verifyWebhook($this->request(['type' => 'message_new'])));
    }

    public function test_verify_checks_secret_in_body(): void
    {
        $driver = $this->makeDriver(['secret' => 's3cret']);

        $this->assertTrue($driver->verifyWebhook($this->request(['secret' => 's3cret'])));
        $this->assertFalse($driver->verifyWebhook($this->request(['secret' => 'wrong'])));
        $this->assertFalse($driver->verifyWebhook($this->request([])));
    }

    public function test_preflight_returns_confirmation_code(): void
    {
        $response = $this->makeDriver()->preflight($this->request(['type' => 'confirmation']));

        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
        $this->assertSame('conf-code', $response->body);
    }

    public function test_preflight_returns_null_for_other_events(): void
    {
        $this->assertNull($this->makeDriver()->preflight($this->request(['type' => 'message_new'])));
    }

    public function test_ack_body_is_ok(): void
    {
        $this->assertSame('ok', $this->makeDriver()->ackBody());
    }
}