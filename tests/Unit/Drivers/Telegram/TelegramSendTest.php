<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use Govorun\Drivers\Telegram\TelegramDriver;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\Media;
use Govorun\Messaging\Message;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

class TelegramSendTest extends TestCase
{
    private array $history = [];

    private function makeDriver(array $responses = []): TelegramDriver
    {
        if (empty($responses)) {
            $responses = [new Response(200, [], '{"ok":true,"result":{}}')];
        }

        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => 'test-secret'],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_send_text_message(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('Привет');
        $msg->chatId = '100';

        $driver->send($msg);

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/sendMessage', $request->getUri()->getPath());

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('100', $body['chat_id']);
        $this->assertSame('Привет', $body['text']);
    }

    public function test_send_text_with_parse_mode(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('**bold**')->parseMode('markdown');
        $msg->chatId = '100';

        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('markdown', $body['parse_mode']);
    }

    public function test_send_text_with_inline_keyboard(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('Choose')
            ->keyboard(
                Keyboard::make()
                    ->button('A', action: 'a', param: ['id' => '1'])
                    ->row()
                    ->button('B', url: 'https://example.com')
            );
        $msg->chatId = '100';

        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $markup = $body['reply_markup'];
        $this->assertArrayHasKey('inline_keyboard', $markup);
        $this->assertCount(2, $markup['inline_keyboard']);
        $this->assertSame('A', $markup['inline_keyboard'][0][0]['text']);
        $this->assertSame('act:a;id:1', $markup['inline_keyboard'][0][0]['callback_data']);
        $this->assertSame('https://example.com', $markup['inline_keyboard'][1][0]['url']);
    }

    public function test_send_reply_keyboard(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('Send contact')
            ->keyboard(
                Keyboard::reply()
                    ->button('Phone', requestContact: true)
            );
        $msg->chatId = '100';

        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $markup = $body['reply_markup'];
        $this->assertArrayHasKey('keyboard', $markup);
        $this->assertTrue($markup['keyboard'][0][0]['request_contact']);
    }

    public function test_send_remove_keyboard(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('Done')
            ->keyboard(Keyboard::remove());
        $msg->chatId = '100';

        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertTrue($body['reply_markup']['remove_keyboard']);
    }

    public function test_send_photo(): void
    {
        $driver = $this->makeDriver();
        $msg = Media::photo('https://example.com/img.jpg')->caption('Nice photo');
        $msg->chatId = '100';

        $driver->send($msg);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/sendPhoto', $request->getUri()->getPath());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('https://example.com/img.jpg', $body['photo']);
        $this->assertSame('Nice photo', $body['caption']);
    }

    public function test_send_document(): void
    {
        $driver = $this->makeDriver();
        $msg = Media::document('https://example.com/file.pdf')->caption('Doc');
        $msg->chatId = '100';

        $driver->send($msg);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/sendDocument', $request->getUri()->getPath());
    }

    public function test_send_voice(): void
    {
        $driver = $this->makeDriver();
        $msg = Media::voice('https://example.com/audio.ogg');
        $msg->chatId = '100';

        $driver->send($msg);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/sendVoice', $request->getUri()->getPath());
    }

    public function test_edit_message(): void
    {
        $driver = $this->makeDriver();
        $msg = Message::make('Updated text');
        $msg->chatId = '100';

        $driver->edit('456', $msg);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/editMessageText', $request->getUri()->getPath());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('100', $body['chat_id']);
        $this->assertSame(456, $body['message_id']);
        $this->assertSame('Updated text', $body['text']);
    }

    public function test_delete_message(): void
    {
        $driver = $this->makeDriver();
        $driver->delete('456', '100');

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/deleteMessage', $request->getUri()->getPath());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('100', $body['chat_id']);
        $this->assertSame(456, $body['message_id']);
    }

    public function test_send_returns_message_id(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":true,"result":{"message_id":42}}'),
        ]);
        $msg = Message::make('Привет');
        $msg->chatId = '100';

        $id = $driver->send($msg);

        $this->assertSame('42', $id);
    }

    public function test_send_returns_null_when_api_not_ok(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":false,"description":"fail"}'),
        ]);
        $msg = Message::make('Привет');
        $msg->chatId = '100';

        $id = $driver->send($msg);

        $this->assertNull($id);
    }

    public function test_send_media_returns_message_id(): void
    {
        $driver = $this->makeDriver([
            new Response(200, [], '{"ok":true,"result":{"message_id":77}}'),
        ]);
        $msg = Media::photo('https://example.com/img.jpg');
        $msg->chatId = '100';

        $id = $driver->send($msg);

        $this->assertSame('77', $id);
    }
}
