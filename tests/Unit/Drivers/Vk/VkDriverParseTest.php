<?php

namespace Govorun\Tests\Unit\Drivers\Vk;

use GuzzleHttp\Client;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use Govorun\Drivers\Vk\VkDriver;
use Govorun\Messaging\ContentType;

class VkDriverParseTest extends TestCase
{
    private function driver(): VkDriver
    {
        return new VkDriver(config: ['token' => 't', 'confirmation' => 'c'], client: new Client());
    }

    private function event(array $object, string $type = 'message_new'): Request
    {
        return new Request(
            server: ['REQUEST_URI' => '/webhook/vk', 'REQUEST_METHOD' => 'POST'],
            content: json_encode(['type' => $type, 'object' => $object, 'group_id' => 1]),
        );
    }

    public function test_parses_text_message(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'message' => ['id' => 55, 'from_id' => 42, 'peer_id' => 42, 'text' => 'привет'],
        ]));

        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('привет', $msg->text);
        $this->assertSame('42', $msg->chatId);
        $this->assertSame('42', $msg->user->id);
        $this->assertSame('vk', $msg->driverName);
        $this->assertSame('55', $msg->id);
    }

    public function test_parses_payload_as_action(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'message' => [
                'id' => 1, 'from_id' => 7, 'peer_id' => 7, 'text' => '',
                'payload' => json_encode(['action' => 'buy', 'param' => ['id' => '10']]),
            ],
        ]));

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('buy', $msg->action);
        $this->assertSame(['id' => '10'], $msg->actionParams);
    }

    public function test_parses_photo_attachment_as_media(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'message' => [
                'id' => 2, 'from_id' => 7, 'peer_id' => 7, 'text' => 'cap',
                'attachments' => [[
                    'type' => 'photo',
                    'photo' => ['sizes' => [
                        ['type' => 's', 'url' => 'http://s', 'width' => 75],
                        ['type' => 'x', 'url' => 'http://x', 'width' => 604],
                    ]],
                ]],
            ],
        ]));

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('photo', $msg->media->type);
        $this->assertSame('http://x', $msg->media->url);
        $this->assertSame('cap', $msg->text);
    }

    public function test_parses_geo_as_location(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'message' => [
                'id' => 3, 'from_id' => 7, 'peer_id' => 7, 'text' => '',
                'geo' => ['coordinates' => ['latitude' => 55.7, 'longitude' => 37.6]],
            ],
        ]));

        $this->assertSame(ContentType::Location, $msg->type);
        $this->assertSame(55.7, $msg->location->latitude);
        $this->assertSame(37.6, $msg->location->longitude);
    }

    public function test_parses_message_event_as_action(): void
    {
        $req = new Request(
            server: ['REQUEST_URI' => '/webhook/vk', 'REQUEST_METHOD' => 'POST'],
            content: json_encode(['type' => 'message_event', 'object' => [
                'user_id' => 9, 'peer_id' => 9, 'event_id' => 'ev1',
                'payload' => ['action' => 'next', 'param' => ['p' => '1']],
            ]]),
        );

        $msg = $this->driver()->parseUpdate($req);

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('next', $msg->action);
        $this->assertSame(['p' => '1'], $msg->actionParams);
        $this->assertSame('9', $msg->chatId);
        $this->assertSame('9', $msg->user->id);
    }

    public function test_parses_group_join_as_event(): void
    {
        $req = new Request(
            server: ['REQUEST_URI' => '/webhook/vk', 'REQUEST_METHOD' => 'POST'],
            content: json_encode(['type' => 'group_join', 'object' => ['user_id' => 5]]),
        );

        $msg = $this->driver()->parseUpdate($req);

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('group_join', $msg->event);
        $this->assertSame('5', $msg->chatId);
    }

    public function test_unsupported_event_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unsupported VK event: like_add');

        $req = new Request(
            server: ['REQUEST_URI' => '/webhook/vk', 'REQUEST_METHOD' => 'POST'],
            content: json_encode(['type' => 'like_add', 'object' => []]),
        );

        $this->driver()->parseUpdate($req);
    }
}