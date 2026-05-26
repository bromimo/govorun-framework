<?php

namespace Govorun\Tests\Unit\Drivers\Viber;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Viber\ViberDriver;

class ViberWebhookTest extends TestCase
{
    private array $history = [];

    private function driverWithToken(string $token): ViberDriver
    {
        return new ViberDriver(
            config: ['auth_token' => $token],
            client: new Client(),
        );
    }

    private function makeDriverWithMock(array $responses, array $profile = []): ViberDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new ViberDriver(
            config: [
                'auth_token' => 'tok',
                'profile' => array_merge([
                    'event_types' => ['message', 'conversation_started'],
                    'sender_name' => 'Bot',
                    'sender_avatar' => 'https://example.com/avatar.jpg',
                ], $profile),
            ],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_verify_webhook_returns_true_for_valid_signature(): void
    {
        $token = 'my-secret';
        $body = '{"event":"message","sender":{"id":"u1"}}';
        $signature = hash_hmac('sha256', $body, $token);

        $request = new Request(
            server: ['HTTP_X_VIBER_CONTENT_SIGNATURE' => $signature],
            content: $body,
        );

        $this->assertTrue($this->driverWithToken($token)->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_when_signature_missing(): void
    {
        $request = new Request(content: '{}');

        $this->assertFalse($this->driverWithToken('any')->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_for_wrong_signature(): void
    {
        $request = new Request(
            server: ['HTTP_X_VIBER_CONTENT_SIGNATURE' => 'wrong-signature'],
            content: '{}',
        );

        $this->assertFalse($this->driverWithToken('correct')->verifyWebhook($request));
    }

    public function test_verify_webhook_returns_false_when_token_empty(): void
    {
        $request = new Request(
            server: ['HTTP_X_VIBER_CONTENT_SIGNATURE' => 'anything'],
            content: '{}',
        );

        $this->assertFalse($this->driverWithToken('')->verifyWebhook($request));
    }

    public function test_install_webhook_posts_to_set_webhook_with_event_types(): void
    {
        $driver = $this->makeDriverWithMock([new Response(200, [], '{"status":0}')]);

        $result = $driver->installWebhook('https://my-bot.example.com/webhook/viber');

        $this->assertTrue($result);
        $this->assertCount(1, $this->history);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/set_webhook', $request->getUri()->getPath());
        $this->assertSame('tok', $request->getHeaderLine('X-Viber-Auth-Token'));

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('https://my-bot.example.com/webhook/viber', $body['url']);
        $this->assertSame(['message', 'conversation_started'], $body['event_types']);
        $this->assertTrue($body['send_name']);
        $this->assertTrue($body['send_photo']);
    }

    public function test_install_webhook_returns_false_on_non_zero_status(): void
    {
        $driver = $this->makeDriverWithMock([
            new Response(200, [], '{"status":1,"status_message":"invalidUrl"}'),
        ]);

        $this->assertFalse($driver->installWebhook('https://x.example.com/webhook'));
    }

    public function test_remove_webhook_posts_empty_url(): void
    {
        $driver = $this->makeDriverWithMock([new Response(200, [], '{"status":0}')]);

        $this->assertTrue($driver->removeWebhook());

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('', $body['url']);
    }
}