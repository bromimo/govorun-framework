<?php

namespace Govorun\Tests\Unit\Drivers\Vk;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Govorun\Tests\TestCase;
use Govorun\Messaging\Button;
use Govorun\Messaging\Message;
use Govorun\Messaging\Keyboard;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Vk\VkDriver;

class VkDriverKeyboardTest extends TestCase
{
    private array $history = [];

    private function driver(): VkDriver
    {
        $mock = new MockHandler([new Response(200, [], json_encode(['response' => 1]))]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new VkDriver(config: ['token' => 't', 'confirmation' => 'c'], client: new Client(['handler' => $stack]));
    }

    private function sentKeyboard(): array
    {
        $request = end($this->history)['request'];
        parse_str((string) $request->getBody(), $params);

        return json_decode($params['keyboard'], true);
    }

    public function test_inline_callback_and_url_buttons(): void
    {
        $kb = Keyboard::make()->buttons([[
            Button::make('Купить')->action('buy', ['id' => '5']),
            Button::make('Сайт')->url('https://example.com'),
        ]]);
        $msg = Message::make('t');
        $msg->chatId = '1';
        $msg->keyboard($kb);

        $this->driver()->send($msg);
        $kbData = $this->sentKeyboard();

        $this->assertTrue($kbData['inline']);
        $callback = $kbData['buttons'][0][0]['action'];
        $this->assertSame('callback', $callback['type']);
        $this->assertSame('Купить', $callback['label']);
        $this->assertSame(['action' => 'buy', 'param' => ['id' => '5']], json_decode($callback['payload'], true));

        $link = $kbData['buttons'][0][1]['action'];
        $this->assertSame('openlink', $link['type']);
        $this->assertSame('https://example.com', $link['link']);
    }

    public function test_reply_keyboard(): void
    {
        $kb = Keyboard::reply()->oneTime()->buttons([[Button::make('Да'), Button::make('Нет')]]);
        $msg = Message::make('t');
        $msg->chatId = '1';
        $msg->keyboard($kb);

        $this->driver()->send($msg);
        $kbData = $this->sentKeyboard();

        $this->assertFalse($kbData['inline']);
        $this->assertTrue($kbData['one_time']);
        $this->assertSame('text', $kbData['buttons'][0][0]['action']['type']);
        $this->assertSame('Да', $kbData['buttons'][0][0]['action']['label']);
    }

    public function test_remove_keyboard(): void
    {
        $msg = Message::make('t');
        $msg->chatId = '1';
        $msg->keyboard(Keyboard::remove());

        $this->driver()->send($msg);
        $kbData = $this->sentKeyboard();

        $this->assertSame([], $kbData['buttons']);
        $this->assertTrue($kbData['one_time']);
    }
}