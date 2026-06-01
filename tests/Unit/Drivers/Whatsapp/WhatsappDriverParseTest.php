<?php

namespace Govorun\Tests\Unit\Drivers\Whatsapp;

use GuzzleHttp\Client;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use Govorun\Messaging\ContentType;
use Govorun\Drivers\Whatsapp\WhatsappDriver;

/** Тесты разбора входящих сообщений WhatsApp. */
class WhatsappDriverParseTest extends TestCase
{
    private function driver(): WhatsappDriver
    {
        return new WhatsappDriver(config: ['access_token' => 't', 'phone_number_id' => '1'], client: new Client());
    }

    private function event(array $message, array $contacts = [['profile' => ['name' => 'Иван'], 'wa_id' => '79990000000']]): Request
    {
        $payload = ['entry' => [['changes' => [['field' => 'messages', 'value' => [
            'contacts' => $contacts,
            'messages' => [$message],
        ]]]]]];

        return new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/webhook/whatsapp'], content: json_encode($payload));
    }

    public function test_parses_text(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '79990000000', 'id' => 'wamid.1', 'type' => 'text', 'text' => ['body' => 'привет'],
        ]));

        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('привет', $msg->text);
        $this->assertSame('79990000000', $msg->chatId);
        $this->assertSame('wamid.1', $msg->id);
        $this->assertSame('whatsapp', $msg->driverName);
        $this->assertSame('Иван', $msg->user->firstName);
    }

    public function test_parses_button_reply_as_action(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.2', 'type' => 'interactive',
            'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'buy', 'title' => 'Купить']],
        ]));

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('buy', $msg->action);
    }

    public function test_parses_list_reply_as_action(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.3', 'type' => 'interactive',
            'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'opt_5', 'title' => 'Опция 5']],
        ]));

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('opt_5', $msg->action);
    }

    public function test_parses_image_as_media_with_file_id(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.4', 'type' => 'image',
            'image' => ['id' => 'MEDIA_77', 'mime_type' => 'image/jpeg', 'caption' => 'фото'],
        ]));

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('photo', $msg->media->type);
        $this->assertSame('MEDIA_77', $msg->media->fileId);
        $this->assertSame('image/jpeg', $msg->media->mimeType);
        $this->assertSame('фото', $msg->text);
    }

    public function test_parses_location(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.5', 'type' => 'location',
            'location' => ['latitude' => 55.75, 'longitude' => 37.61],
        ]));

        $this->assertSame(ContentType::Location, $msg->type);
        $this->assertSame(55.75, $msg->location->latitude);
        $this->assertSame(37.61, $msg->location->longitude);
    }

    public function test_parses_contact(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.6', 'type' => 'contacts',
            'contacts' => [['name' => ['first_name' => 'Пётр'], 'phones' => [['phone' => '79991112233']]]],
        ]));

        $this->assertSame(ContentType::Contact, $msg->type);
        $this->assertSame('79991112233', $msg->contact->phone);
        $this->assertSame('Пётр', $msg->contact->firstName);
    }

    public function test_parses_button_quick_reply_as_action(): void
    {
        $msg = $this->driver()->parseUpdate($this->event([
            'from' => '7999', 'id' => 'wamid.8', 'type' => 'button',
            'button' => ['payload' => 'confirm', 'text' => 'Подтвердить'],
        ]));

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('confirm', $msg->action);
    }

    public function test_throws_on_unsupported_type(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->driver()->parseUpdate($this->event(['from' => '7999', 'id' => 'wamid.7', 'type' => 'reaction']));
    }
}