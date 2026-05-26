<?php

namespace Govorun\Tests\Unit\Drivers\Viber;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Govorun\Tests\TestCase;
use Govorun\Messaging\Media;
use Govorun\Messaging\Button;
use Govorun\Messaging\Message;
use Govorun\Messaging\Keyboard;
use GuzzleHttp\Handler\MockHandler;
use Govorun\Drivers\Viber\ViberDriver;

class ViberSendTest extends TestCase
{
    private array $history = [];

    private function makeDriver(array $responses, array $profile = []): ViberDriver
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        return new ViberDriver(
            config: [
                'auth_token' => 'tok',
                'profile' => array_merge([
                    'sender_name' => 'Bot',
                    'sender_avatar' => 'https://example.com/avatar.jpg',
                ], $profile),
            ],
            client: new Client(['handler' => $stack]),
        );
    }

    public function test_send_text_posts_to_send_message_with_sender_and_text(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"12345"}')]);

        $msg = Message::make('Hello');
        $msg->chatId = 'u1';

        $result = $driver->send($msg);

        $this->assertSame('12345', $result);

        $request = $this->history[0]['request'];
        $this->assertStringEndsWith('/send_message', $request->getUri()->getPath());
        $this->assertSame('tok', $request->getHeaderLine('X-Viber-Auth-Token'));

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('u1', $body['receiver']);
        $this->assertSame('text', $body['type']);
        $this->assertSame('Hello', $body['text']);
        $this->assertSame('Bot', $body['sender']['name']);
        $this->assertSame('https://example.com/avatar.jpg', $body['sender']['avatar']);
    }

    public function test_send_strips_html_tags_from_text(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Message::make('<b>Bold</b> and <i>italic</i>');
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('Bold and italic', $body['text']);
    }

    public function test_send_converts_anchor_tags_to_text_with_url(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Message::make('See <a href="https://example.com/docs">documentation</a>');
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('See documentation (https://example.com/docs)', $body['text']);
    }

    public function test_send_decodes_html_entities(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Message::make('Tom &amp; Jerry');
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('Tom & Jerry', $body['text']);
    }

    public function test_send_returns_null_on_non_zero_status(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":1,"status_message":"invalid receiver"}')]);

        $msg = Message::make('x');
        $msg->chatId = 'u1';

        $this->assertNull($driver->send($msg));
    }

    public function test_send_picture_posts_type_picture_with_media(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Media::photo('https://example.com/image.jpg')->caption('Caption');
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('picture', $body['type']);
        $this->assertSame('https://example.com/image.jpg', $body['media']);
        $this->assertSame('Caption', $body['text']);
    }

    public function test_send_video_posts_type_video_with_size_and_duration(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Media::video('https://example.com/video.mp4');
        $msg->media['size'] = 51200;
        $msg->media['duration'] = 10;
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('video', $body['type']);
        $this->assertSame('https://example.com/video.mp4', $body['media']);
        $this->assertSame(51200, $body['size']);
        $this->assertSame(10, $body['duration']);
    }

    public function test_send_file_posts_type_file_with_file_name(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Media::document('https://example.com/doc.pdf');
        $msg->media['file_name'] = 'doc.pdf';
        $msg->media['size'] = 1024;
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('file', $body['type']);
        $this->assertSame('https://example.com/doc.pdf', $body['media']);
        $this->assertSame('doc.pdf', $body['file_name']);
        $this->assertSame(1024, $body['size']);
    }

    public function test_send_with_inline_keyboard_posts_rich_media(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Message::make('Confirm?')->keyboard(
            Keyboard::make()->buttons([
                [Button::make('Yes')->action('confirm_yes'), Button::make('No')->action('confirm_no')],
            ]),
        );
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('rich_media', $body['type']);
        $this->assertSame('rich_media', $body['rich_media']['Type']);
        $this->assertSame(6, $body['rich_media']['ButtonsGroupColumns']);
        $this->assertCount(2, $body['rich_media']['Buttons']);
        $this->assertSame('reply', $body['rich_media']['Buttons'][0]['ActionType']);
        $this->assertSame('confirm_yes', $body['rich_media']['Buttons'][0]['ActionBody']);
        $this->assertSame('Yes', $body['rich_media']['Buttons'][0]['Text']);
        $this->assertSame('reply', $body['rich_media']['Buttons'][1]['ActionType']);
        $this->assertSame('confirm_no', $body['rich_media']['Buttons'][1]['ActionBody']);
    }

    public function test_send_keyboard_button_with_url_uses_open_url_action(): void
    {
        $driver = $this->makeDriver([new Response(200, [], '{"status":0,"message_token":"1"}')]);

        $msg = Message::make('Open link')->keyboard(
            Keyboard::make()->buttons([
                [Button::make('Visit')->url('https://example.com')],
            ]),
        );
        $msg->chatId = 'u1';
        $driver->send($msg);

        $body = json_decode($this->history[0]['request']->getBody()->getContents(), true);
        $this->assertSame('open-url', $body['rich_media']['Buttons'][0]['ActionType']);
        $this->assertSame('https://example.com', $body['rich_media']['Buttons'][0]['ActionBody']);
    }
}