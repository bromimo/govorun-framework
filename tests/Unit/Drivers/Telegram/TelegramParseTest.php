<?php

namespace Govorun\Tests\Unit\Drivers\Telegram;

use Govorun\Drivers\Telegram\TelegramDriver;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Tests\TestCase;
use GuzzleHttp\Client;

class TelegramParseTest extends TestCase
{
    private TelegramDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => 'test-secret'],
            client: new Client(),
        );
    }

    public function test_verify_webhook_with_valid_secret(): void
    {
        $request = new Request(
            server: ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'test-secret'],
            content: '{}',
        );

        $this->assertTrue($this->driver->verifyWebhook($request));
    }

    public function test_verify_webhook_with_invalid_secret(): void
    {
        $request = new Request(
            server: ['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN' => 'wrong-secret'],
            content: '{}',
        );

        $this->assertFalse($this->driver->verifyWebhook($request));
    }

    public function test_verify_webhook_without_secret_header(): void
    {
        $request = new Request(content: '{}');

        $this->assertFalse($this->driver->verifyWebhook($request));
    }

    public function test_verify_webhook_passes_when_no_secret_configured(): void
    {
        $driver = new TelegramDriver(
            config: ['token' => 'test-token', 'secret' => null],
            client: new Client(),
        );
        $request = new Request(content: '{}');

        $this->assertTrue($driver->verifyWebhook($request));
    }

    public function test_parse_text_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 123,
            'message' => [
                'message_id' => 456,
                'from' => ['id' => 100, 'first_name' => 'Ivan', 'last_name' => 'Petrov', 'username' => 'ipetrov', 'language_code' => 'ru'],
                'chat' => ['id' => 100],
                'text' => 'Привет',
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame('456', $msg->id);
        $this->assertSame('100', $msg->chatId);
        $this->assertSame('telegram', $msg->driverName);
        $this->assertSame('Привет', $msg->text);
        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('100', $msg->user->id);
        $this->assertSame('Ivan', $msg->user->firstName);
        $this->assertSame('Petrov', $msg->user->lastName);
        $this->assertSame('ipetrov', $msg->user->username);
        $this->assertSame('ru', $msg->user->locale);
    }

    public function test_parse_callback_query(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 124,
            'callback_query' => [
                'id' => 'cb1',
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'message' => ['message_id' => 456, 'chat' => ['id' => 100]],
                'data' => 'act:service;type:manicure',
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Action, $msg->type);
        $this->assertSame('service', $msg->action);
        $this->assertSame(['type' => 'manicure'], $msg->actionParams);
        $this->assertSame('100', $msg->chatId);
    }

    public function test_parse_callback_without_params(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 124,
            'callback_query' => [
                'id' => 'cb2',
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'message' => ['message_id' => 456, 'chat' => ['id' => 100]],
                'data' => 'act:confirm',
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame('confirm', $msg->action);
        $this->assertSame([], $msg->actionParams);
    }

    public function test_parse_photo_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 125,
            'message' => [
                'message_id' => 457,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'photo' => [
                    ['file_id' => 'small', 'width' => 90, 'height' => 90],
                    ['file_id' => 'large', 'width' => 800, 'height' => 600],
                ],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('photo', $msg->media->type);
        $this->assertSame('large', $msg->media->fileId); // last (largest) photo
    }

    public function test_parse_document_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 126,
            'message' => [
                'message_id' => 458,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'document' => ['file_id' => 'doc1', 'mime_type' => 'application/pdf', 'file_size' => 1024],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('document', $msg->media->type);
        $this->assertSame('doc1', $msg->media->fileId);
        $this->assertSame('application/pdf', $msg->media->mimeType);
    }

    public function test_parse_voice_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 127,
            'message' => [
                'message_id' => 459,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'voice' => ['file_id' => 'voice1', 'mime_type' => 'audio/ogg', 'duration' => 5],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('voice', $msg->media->type);
    }

    public function test_parse_sticker_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 128,
            'message' => [
                'message_id' => 460,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'sticker' => ['file_id' => 'sticker1', 'emoji' => '😀'],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('sticker', $msg->media->type);
    }

    public function test_parse_location_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 129,
            'message' => [
                'message_id' => 461,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'location' => ['latitude' => 55.7558, 'longitude' => 37.6173],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Location, $msg->type);
        $this->assertSame(55.7558, $msg->location->latitude);
        $this->assertSame(37.6173, $msg->location->longitude);
    }

    public function test_parse_contact_message(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 130,
            'message' => [
                'message_id' => 462,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'contact' => ['phone_number' => '+79001234567', 'first_name' => 'Ivan', 'user_id' => 100],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Contact, $msg->type);
        $this->assertSame('+79001234567', $msg->contact->phone);
        $this->assertSame('Ivan', $msg->contact->firstName);
    }

    public function test_parse_new_chat_members_event(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 131,
            'message' => [
                'message_id' => 463,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => -200],
                'new_chat_members' => [['id' => 100, 'first_name' => 'Ivan']],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('new_chat_members', $msg->event);
    }

    public function test_parse_left_chat_member_event(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 132,
            'message' => [
                'message_id' => 464,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => -200],
                'left_chat_member' => ['id' => 100, 'first_name' => 'Ivan'],
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('left_chat_member', $msg->event);
    }

    public function test_parse_start_with_referral(): void
    {
        $request = new Request(content: json_encode([
            'update_id' => 133,
            'message' => [
                'message_id' => 465,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'text' => '/start promo123',
            ],
        ]));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('/start promo123', $msg->text);
        $this->assertSame('promo123', $msg->referral);
    }

    public function test_raw_data_is_preserved(): void
    {
        $data = [
            'update_id' => 123,
            'message' => [
                'message_id' => 456,
                'from' => ['id' => 100, 'first_name' => 'Ivan'],
                'chat' => ['id' => 100],
                'text' => 'test',
            ],
        ];
        $request = new Request(content: json_encode($data));

        $msg = $this->driver->parseUpdate($request);

        $this->assertSame($data, $msg->raw);
    }
}
