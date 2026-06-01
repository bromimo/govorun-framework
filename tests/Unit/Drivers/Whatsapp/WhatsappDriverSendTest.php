<?php

namespace Govorun\Tests\Unit\Drivers\Whatsapp;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use Govorun\Tests\TestCase;
use GuzzleHttp\Psr7\Response;
use Govorun\Messaging\Button;
use Govorun\Messaging\Keyboard;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Drivers\Whatsapp\WhatsappDriver;

/** Тесты отправки сообщений WhatsApp (text/media/interactive). */
class WhatsappDriverSendTest extends TestCase
{
    private array $history = [];

    private function driverWith(array $responses): WhatsappDriver
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        return new WhatsappDriver(
            config: ['access_token' => 'TOK', 'phone_number_id' => '555'],
            client: new Client(['handler' => $stack]),
        );
    }

    private function lastBody(): array
    {
        $request = end($this->history)['request'];

        return json_decode((string) $request->getBody(), true);
    }

    private function okResponse(): Response
    {
        return new Response(200, [], json_encode(['messages' => [['id' => 'wamid.OUT']]]));
    }

    private function outgoing(string $text): OutgoingMessage
    {
        $msg = new OutgoingMessage();
        $msg->chatId = '79990000000';
        $msg->text = $text;

        return $msg;
    }

    public function test_send_text(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $id = $driver->send($this->outgoing('<b>Привет</b>'));

        $this->assertSame('wamid.OUT', $id);

        $request = end($this->history)['request'];
        $this->assertStringEndsWith('/v25.0/555/messages', $request->getUri()->getPath());
        $this->assertSame('Bearer TOK', $request->getHeaderLine('Authorization'));

        $body = $this->lastBody();
        $this->assertSame('whatsapp', $body['messaging_product']);
        $this->assertSame('79990000000', $body['to']);
        $this->assertSame('text', $body['type']);
        $this->assertSame('Привет', $body['text']['body']);
    }

    public function test_send_media_image_with_caption(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $msg = $this->outgoing('подпись');
        $msg->media = ['type' => 'photo', 'url' => 'https://e.com/i.jpg'];
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('image', $body['type']);
        $this->assertSame('https://e.com/i.jpg', $body['image']['link']);
        $this->assertSame('подпись', $body['image']['caption']);
    }

    public function test_send_keyboard_up_to_three_buttons_is_interactive_button(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $msg = $this->outgoing('Выберите');
        $msg->keyboard = Keyboard::make()->buttons([
            [Button::make('Да')->action('yes'), Button::make('Нет')->action('no')],
        ])->toArray();
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('interactive', $body['type']);
        $this->assertSame('button', $body['interactive']['type']);
        $this->assertSame('Выберите', $body['interactive']['body']['text']);
        $buttons = $body['interactive']['action']['buttons'];
        $this->assertCount(2, $buttons);
        $this->assertSame('yes', $buttons[0]['reply']['id']);
        $this->assertSame('Да', $buttons[0]['reply']['title']);
    }

    public function test_send_keyboard_four_to_ten_buttons_is_interactive_list(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $row = [];
        foreach (['a', 'b', 'c', 'd', 'e'] as $key) {
            $row[] = Button::make(strtoupper($key))->action($key);
        }
        $msg = $this->outgoing('Меню');
        $msg->keyboard = Keyboard::make()->buttons([$row])->toArray();
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('list', $body['interactive']['type']);
        $rows = $body['interactive']['action']['sections'][0]['rows'];
        $this->assertCount(5, $rows);
        $this->assertSame('a', $rows[0]['id']);
        $this->assertSame('A', $rows[0]['title']);
    }

    public function test_send_keyboard_with_remove_sends_text(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $msg = $this->outgoing('Текст');
        $msg->keyboard = array_merge(
            Keyboard::make()->buttons([[Button::make('Да')->action('yes')]])->toArray(),
            ['remove' => true]
        );
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('text', $body['type']);
        $this->assertSame('Текст', $body['text']['body']);
    }

    public function test_send_keyboard_exactly_three_buttons_is_button_type(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $msg = $this->outgoing('Выбор');
        $msg->keyboard = Keyboard::make()->buttons([
            [Button::make('A')->action('a'), Button::make('B')->action('b'), Button::make('C')->action('c')],
        ])->toArray();
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('button', $body['interactive']['type']);
    }

    public function test_send_keyboard_exactly_four_buttons_is_list_type(): void
    {
        $driver = $this->driverWith([$this->okResponse()]);

        $msg = $this->outgoing('Выбор');
        $msg->keyboard = Keyboard::make()->buttons([
            [Button::make('A')->action('a'), Button::make('B')->action('b'), Button::make('C')->action('c'), Button::make('D')->action('d')],
        ])->toArray();
        $driver->send($msg);

        $body = $this->lastBody();
        $this->assertSame('list', $body['interactive']['type']);
    }

    public function test_send_throws_on_api_error(): void
    {
        $driver = $this->driverWith([new Response(200, [], json_encode([
            'error' => ['code' => 131009, 'message' => 'Parameter value is not valid'],
        ]))]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('failed [131009]');

        $driver->send($this->outgoing('x'));
    }
}