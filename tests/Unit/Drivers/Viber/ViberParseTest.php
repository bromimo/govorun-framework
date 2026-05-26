<?php

namespace Govorun\Tests\Unit\Drivers\Viber;

use GuzzleHttp\Client;
use Govorun\Http\Request;
use Govorun\Tests\TestCase;
use Govorun\Messaging\ContentType;
use Govorun\Drivers\Viber\ViberDriver;

class ViberParseTest extends TestCase
{
    private ViberDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new ViberDriver(config: ['auth_token' => 't'], client: new Client());
    }

    private function requestFromFixture(string $name): Request
    {
        $body = file_get_contents(__DIR__.'/../../../fixtures/viber/'.$name.'.json');
        return new Request(content: $body);
    }

    public function test_parse_text_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_text'));

        $this->assertSame('viber', $msg->driverName);
        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('Привет, бот!', $msg->text);
        $this->assertSame('01234567890A=', $msg->chatId);
        $this->assertSame('01234567890A=', $msg->user->id);
        $this->assertSame('John Smith', $msg->user->firstName);
        $this->assertSame('en', $msg->user->locale);
        $this->assertSame('US', $msg->user->raw['country'] ?? null);
    }

    public function test_parse_picture_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_picture'));

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('photo', $msg->media->type);
        $this->assertSame('caption', $msg->text);
        $this->assertSame('https://example.com/image.jpg', $msg->media->url);
    }

    public function test_parse_video_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_video'));

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('video', $msg->media->type);
        $this->assertSame('https://example.com/video.mp4', $msg->media->url);
        $this->assertSame(102400, $msg->media->fileSize);
        $this->assertSame(10, $msg->media->raw['duration'] ?? null);
    }

    public function test_parse_file_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_file'));

        $this->assertSame(ContentType::Media, $msg->type);
        $this->assertSame('document', $msg->media->type);
        $this->assertSame('https://example.com/doc.pdf', $msg->media->url);
        $this->assertSame('doc.pdf', $msg->media->raw['file_name'] ?? null);
        $this->assertSame(51200, $msg->media->fileSize);
    }

    public function test_parse_contact_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_contact'));

        $this->assertSame(ContentType::Contact, $msg->type);
        $this->assertSame('+1234567890', $msg->contact->phone);
        $this->assertSame('John', $msg->contact->firstName);
    }

    public function test_parse_location_message(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_location'));

        $this->assertSame(ContentType::Location, $msg->type);
        $this->assertSame(37.7749, $msg->location->latitude);
        $this->assertSame(-122.4194, $msg->location->longitude);
    }

    public function test_parse_sticker_message_as_text_fallback(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('message_sticker'));

        $this->assertSame(ContentType::Text, $msg->type);
        $this->assertSame('', $msg->text);
        $this->assertSame(40100, $msg->raw['message']['sticker_id'] ?? null);
    }

    public function test_parse_conversation_started_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('conversation_started'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('conversation_started', $msg->event);
        $this->assertSame('01234567890A=', $msg->user->id);
    }

    public function test_parse_subscribed_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('subscribed'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('subscribed', $msg->event);
        $this->assertSame('01234567890A=', $msg->user->id);
    }

    public function test_parse_unsubscribed_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('unsubscribed'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('unsubscribed', $msg->event);
        $this->assertSame('01234567890A=', $msg->user->id);
    }

    public function test_parse_delivered_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('delivered'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('delivered', $msg->event);
        $this->assertSame('01234567890A=', $msg->user->id);
    }

    public function test_parse_seen_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('seen'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('seen', $msg->event);
    }

    public function test_parse_failed_event(): void
    {
        $msg = $this->driver->parseUpdate($this->requestFromFixture('failed'));

        $this->assertSame(ContentType::Event, $msg->type);
        $this->assertSame('failed', $msg->event);
        $this->assertSame('failure reason', $msg->raw['desc'] ?? null);
    }
}