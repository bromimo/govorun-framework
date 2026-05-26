<?php

namespace Govorun\Tests\Unit\Drivers\Viber;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Govorun\Tests\TestCase;
use Govorun\Messaging\Message;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Viber\ViberDriver;
use Govorun\Contracts\MessengerDriver;

class ViberDriverTest extends TestCase
{
    private function makeDriver(): ViberDriver
    {
        return new ViberDriver(
            config: ['auth_token' => 'test-token'],
            client: new Client(),
        );
    }

    public function test_implements_messenger_driver_contract(): void
    {
        $this->assertInstanceOf(MessengerDriver::class, $this->makeDriver());
    }

    public function test_edit_throws_unsupported_operation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Viber API does not support message editing');

        $msg = Message::make('x');
        $msg->chatId = '1';
        $this->makeDriver()->edit('msg-1', $msg);
    }

    public function test_delete_throws_unsupported_operation(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Viber API does not support message deletion');

        $this->makeDriver()->delete('msg-1', 'chat-1');
    }

    public function test_get_user_fetches_user_details(): void
    {
        $history = [];
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'status' => 0,
                'user' => ['id' => 'u1', 'name' => 'John Smith', 'country' => 'US', 'language' => 'en'],
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($history));

        $driver = new ViberDriver(
            config: ['auth_token' => 't'],
            client: new Client(['handler' => $stack]),
        );

        $user = $driver->getUser('u1');

        $this->assertSame('u1', $user->id);
        $this->assertSame('John Smith', $user->firstName);
        $this->assertSame('en', $user->locale);
        $this->assertSame('US', $user->raw['country'] ?? null);

        $body = json_decode($history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('u1', $body['id']);
    }
}