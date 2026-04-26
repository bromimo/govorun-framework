<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use Govorun\Tests\TestCase;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Telegram\TelegramDriver;

class TelegramProfileTest extends TestCase
{
    private array $history = [];

    private function makeDriver(array $responses = []): TelegramDriver
    {
        if (empty($responses)) {
            $responses = [new Response(200, [], '{"ok":true,"result":true}')];
        }

        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => null],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_set_my_name_calls_api(): void
    {
        $driver = $this->makeDriver();

        $driver->setMyName('Govorun Bot');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/setMyName', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('Govorun Bot', $body['name']);
    }
}
