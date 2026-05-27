<?php

namespace Govorun\Tests\Unit\Drivers\Vk;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Govorun\Tests\TestCase;
use Govorun\Messaging\Message;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Vk\VkDriver;

class VkDriverSendTest extends TestCase
{
    private array $history = [];

    private function driverWith(array $responses): VkDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new VkDriver(
            config: ['token' => 'tok', 'confirmation' => 'c'],
            client: new Client(['handler' => $stack]),
        );
    }

    private function lastRequestParams(): array
    {
        $request = end($this->history)['request'];
        parse_str((string) $request->getBody(), $params);

        return $params;
    }

    public function test_send_text_posts_to_messages_send(): void
    {
        $driver = $this->driverWith([new Response(200, [], json_encode(['response' => 98765]))]);

        $msg = Message::make('<b>Привет</b>');
        $msg->chatId = '42';

        $id = $driver->send($msg);

        $this->assertSame('98765', $id);

        $request = end($this->history)['request'];
        $this->assertStringContainsString('messages.send', (string) $request->getUri());

        $params = $this->lastRequestParams();
        $this->assertSame('42', $params['peer_id']);
        $this->assertSame('Привет', $params['message']);
        $this->assertSame('tok', $params['access_token']);
        $this->assertArrayHasKey('random_id', $params);
        $this->assertArrayHasKey('v', $params);
    }

    public function test_send_throws_on_api_error(): void
    {
        $driver = $this->driverWith([new Response(200, [], json_encode([
            'error' => ['error_code' => 100, 'error_msg' => 'bad param'],
        ]))]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VK messages.send failed [100]: bad param');

        $msg = Message::make('x');
        $msg->chatId = '1';
        $driver->send($msg);
    }
}