<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Tests\TestCase;

class IncomingMessageTest extends TestCase
{
    public function test_creates_text_message(): void
    {
        $user = new UserDto(id: '100', firstName: 'Ivan');
        $msg = new IncomingMessage(
            id: 'msg1',
            chatId: 'chat1',
            driverName: 'telegram',
            text: 'Привет',
            user: $user,
            type: ContentType::Text,
        );

        $this->assertSame('msg1', $msg->id);
        $this->assertSame('chat1', $msg->chatId);
        $this->assertSame('telegram', $msg->driverName);
        $this->assertSame('Привет', $msg->text);
        $this->assertSame($user, $msg->user);
        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertNull($msg->action);
        $this->assertNull($msg->actionParams);
        $this->assertNull($msg->event);
        $this->assertNull($msg->media);
        $this->assertNull($msg->location);
        $this->assertNull($msg->contact);
        $this->assertNull($msg->referral);
        $this->assertSame([], $msg->raw);
    }

    public function test_creates_action_message(): void
    {
        $user = new UserDto(id: '100');
        $msg = new IncomingMessage(
            id: 'msg2',
            chatId: 'chat1',
            driverName: 'telegram',
            text: null,
            user: $user,
            type: ContentType::Action,
            action: 'confirm_order',
            actionParams: ['id' => '42'],
        );

        $this->assertSame('confirm_order', $msg->action);
        $this->assertSame(['id' => '42'], $msg->actionParams);
    }

    public function test_creates_media_message(): void
    {
        $user = new UserDto(id: '100');
        $media = new MediaDto(type: 'photo', url: 'https://example.com/img.jpg');
        $msg = new IncomingMessage(
            id: 'msg3',
            chatId: 'chat1',
            driverName: 'telegram',
            text: null,
            user: $user,
            type: ContentType::Media,
            media: $media,
        );

        $this->assertSame($media, $msg->media);
    }

    public function test_creates_location_message(): void
    {
        $user = new UserDto(id: '100');
        $location = new LocationDto(latitude: 55.75, longitude: 37.62);
        $msg = new IncomingMessage(
            id: 'msg4',
            chatId: 'chat1',
            driverName: 'telegram',
            text: null,
            user: $user,
            type: ContentType::Location,
            location: $location,
        );

        $this->assertSame($location, $msg->location);
    }

    public function test_creates_contact_message(): void
    {
        $user = new UserDto(id: '100');
        $contact = new ContactDto(phone: '+79001234567', firstName: 'Ivan');
        $msg = new IncomingMessage(
            id: 'msg5',
            chatId: 'chat1',
            driverName: 'telegram',
            text: null,
            user: $user,
            type: ContentType::Contact,
            contact: $contact,
        );

        $this->assertSame($contact, $msg->contact);
    }

    public function test_stores_raw_data(): void
    {
        $user = new UserDto(id: '100');
        $raw = ['update_id' => 999, 'original' => 'payload'];
        $msg = new IncomingMessage(
            id: 'msg6',
            chatId: 'chat1',
            driverName: 'telegram',
            text: 'test',
            user: $user,
            type: ContentType::Text,
            raw: $raw,
        );

        $this->assertSame($raw, $msg->raw);
    }
}
