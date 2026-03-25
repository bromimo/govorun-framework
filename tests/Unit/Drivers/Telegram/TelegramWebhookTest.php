<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use Govorun\Drivers\Telegram\TelegramDriver;
use Govorun\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class TelegramWebhookTest extends TestCase
{
    private array $history = [];

    private function makeDriver(array $responses): TelegramDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => 'test-secret'],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_install_webhook(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":true,"result":true}'),
        ]);

        $result = $driver->installWebhook('https://example.com/webhook/telegram');

        $this->assertTrue($result);
        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setWebhook', $request->getUri()->getPath());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('https://example.com/webhook/telegram', $body['url']);
        $this->assertSame('test-secret', $body['secret_token']);
    }

    public function test_install_webhook_failure(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":false}'),
        ]);

        $result = $driver->installWebhook('https://example.com/webhook/telegram');

        $this->assertFalse($result);
    }

    public function test_remove_webhook(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":true,"result":true}'),
        ]);

        $result = $driver->removeWebhook();

        $this->assertTrue($result);
        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/deleteWebhook', $request->getUri()->getPath());
    }

    public function test_get_user(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], json_encode([
                'ok' => true,
                'result' => [
                    'id' => 100,
                    'first_name' => 'Ivan',
                    'last_name' => 'Petrov',
                    'username' => 'ipetrov',
                    'language_code' => 'ru',
                ],
            ])),
        ]);

        $user = $driver->getUser('100');

        $this->assertSame('100', $user->id);
        $this->assertSame('Ivan', $user->firstName);
        $this->assertSame('Petrov', $user->lastName);
        $this->assertSame('ipetrov', $user->username);
        $this->assertSame('ru', $user->locale);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/getChat', $request->getUri()->getPath());
    }
}
