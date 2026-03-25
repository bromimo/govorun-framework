<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Keyboard;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Tests\TestCase;

class OutgoingMessageTest extends TestCase
{
    public function test_creates_with_defaults(): void
    {
        $msg = new OutgoingMessage();

        $this->assertSame('', $msg->chatId);
        $this->assertNull($msg->text);
        $this->assertNull($msg->parseMode);
        $this->assertNull($msg->keyboard);
        $this->assertNull($msg->media);
    }

    public function test_properties_are_writable(): void
    {
        $msg = new OutgoingMessage();
        $msg->chatId = 'chat1';
        $msg->text = 'Hello';
        $msg->parseMode = 'markdown';

        $this->assertSame('chat1', $msg->chatId);
        $this->assertSame('Hello', $msg->text);
        $this->assertSame('markdown', $msg->parseMode);
    }

    public function test_keyboard_sets_keyboard_data(): void
    {
        $kb = Keyboard::make()->button('Click', action: 'btn');
        $msg = new OutgoingMessage();
        $result = $msg->keyboard($kb);

        $this->assertSame($msg, $result);
        $this->assertSame('inline', $msg->keyboard['type']);
        $this->assertCount(1, $msg->keyboard['rows']);
    }

    public function test_parse_mode_is_fluent(): void
    {
        $msg = new OutgoingMessage();
        $result = $msg->parseMode('markdown');

        $this->assertSame($msg, $result);
        $this->assertSame('markdown', $msg->parseMode);
    }

    public function test_caption_sets_text(): void
    {
        $msg = new OutgoingMessage();
        $result = $msg->caption('Photo caption');

        $this->assertSame($msg, $result);
        $this->assertSame('Photo caption', $msg->text);
    }
}
