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

class VkDriverApiTest extends TestCase
{
    private array $history = [];

    private function driver(array $responses): VkDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new VkDriver(config: ['token' => 't', 'confirmation' => 'c'], client: new Client(['handler' => $stack]));
    }

    private function paramsOf(int $i): array
    {
        parse_str((string) $this->history[$i]['request']->getBody(), $p);

        return $p;
    }

    public function test_edit_calls_messages_edit(): void
    {
        $driver = $this->driver([new Response(200, [], json_encode(['response' => 1]))]);

        $msg = Message::make('новый текст');
        $msg->chatId = '42';
        $driver->edit('555', $msg);

        $this->assertStringContainsString('messages.edit', (string) $this->history[0]['request']->getUri());
        $p = $this->paramsOf(0);
        $this->assertSame('42', $p['peer_id']);
        $this->assertSame('555', $p['message_id']);
        $this->assertSame('новый текст', $p['message']);
    }

    public function test_delete_calls_messages_delete(): void
    {
        $driver = $this->driver([new Response(200, [], json_encode(['response' => 1]))]);

        $driver->delete('555', '42');

        $this->assertStringContainsString('messages.delete', (string) $this->history[0]['request']->getUri());
        $p = $this->paramsOf(0);
        $this->assertSame('555', $p['message_ids']);
        $this->assertSame('1', $p['delete_for_all']);
    }

    public function test_get_user_calls_users_get(): void
    {
        $driver = $this->driver([new Response(200, [], json_encode([
            'response' => [[
                'id' => 7, 'first_name' => 'Иван', 'last_name' => 'Петров', 'screen_name' => 'ivan',
            ]],
        ]))]);

        $user = $driver->getUser('7');

        $this->assertSame('7', $user->id);
        $this->assertSame('Иван', $user->firstName);
        $this->assertSame('Петров', $user->lastName);
        $this->assertSame('ivan', $user->username);

        $p = $this->paramsOf(0);
        $this->assertSame('7', $p['user_ids']);
    }
}