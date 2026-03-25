<?php

namespace Govorun\Tests\Unit\Messaging;

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
}
