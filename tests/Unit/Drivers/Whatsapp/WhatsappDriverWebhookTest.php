<?php

namespace Govorun\Tests\Unit\Drivers\Whatsapp;

use GuzzleHttp\Client;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use Govorun\Drivers\Whatsapp\WhatsappDriver;

/** Тесты верификации вебхука WhatsApp (GET-challenge, HMAC, игнор статусов). */
class WhatsappDriverWebhookTest extends TestCase
{
    private function driver(array $config = []): WhatsappDriver
    {
        return new WhatsappDriver(
            config: array_merge([
                'access_token' => 'tok',
                'phone_number_id' => '111',
                'app_secret' => 'shh',
                'verify_token' => 'vt',
            ], $config),
            client: new Client(),
        );
    }

    public function test_get_challenge_returns_challenge_when_verify_token_matches(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/webhook/whatsapp'],
            query: ['hub_mode' => 'subscribe', 'hub_verify_token' => 'vt', 'hub_challenge' => '99887'],
        );

        $this->assertTrue($this->driver()->verifyWebhook($request));

        $response = $this->driver()->preflight($request);
        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
        $this->assertSame('99887', $response->body);
    }

    public function test_get_challenge_forbidden_when_verify_token_mismatch(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/webhook/whatsapp'],
            query: ['hub_mode' => 'subscribe', 'hub_verify_token' => 'WRONG', 'hub_challenge' => '1'],
        );

        $response = $this->driver()->preflight($request);
        $this->assertNotNull($response);
        $this->assertSame(403, $response->status);
    }

    public function test_verify_webhook_post_checks_hmac_signature(): void
    {
        $body = '{"object":"whatsapp_business_account"}';
        $signature = 'sha256=' . hash_hmac('sha256', $body, 'shh');

        $valid = new Request(server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_HUB_SIGNATURE_256' => $signature], content: $body);
        $invalid = new Request(server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_HUB_SIGNATURE_256' => 'sha256=deadbeef'], content: $body);

        $this->assertTrue($this->driver()->verifyWebhook($valid));
        $this->assertFalse($this->driver()->verifyWebhook($invalid));
    }

    public function test_verify_webhook_post_returns_false_when_signature_missing(): void
    {
        $body = '{"object":"whatsapp_business_account"}';
        $request = new Request(server: ['REQUEST_METHOD' => 'POST'], content: $body);

        $this->assertFalse($this->driver()->verifyWebhook($request));
    }

    public function test_preflight_ignores_delivery_statuses(): void
    {
        $body = json_encode(['entry' => [['changes' => [['value' => ['statuses' => [['status' => 'read']]]]]]]]);
        $request = new Request(server: ['REQUEST_METHOD' => 'POST'], content: $body);

        $response = $this->driver()->preflight($request);
        $this->assertNotNull($response);
        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->body);
    }

    public function test_preflight_returns_null_for_incoming_message(): void
    {
        $body = json_encode(['entry' => [['changes' => [['value' => ['messages' => [['id' => 'w1', 'type' => 'text']]]]]]]]);
        $request = new Request(server: ['REQUEST_METHOD' => 'POST'], content: $body);

        $this->assertNull($this->driver()->preflight($request));
    }
}