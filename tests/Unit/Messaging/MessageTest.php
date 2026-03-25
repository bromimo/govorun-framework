<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Keyboard;
use Govorun\Messaging\Message;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Tests\TestCase;

class MessageTest extends TestCase
{
    public function test_make_creates_outgoing_message_with_text(): void
    {
        $msg = Message::make('Привет');
        $this->assertInstanceOf(OutgoingMessage::class, $msg);
        $this->assertSame('Привет', $msg->text);
    }

    public function test_make_with_keyboard_and_parse_mode(): void
    {
        $msg = Message::make('Выберите действие')
            ->keyboard(
                Keyboard::make()
                    ->button('Кнопка', action: 'btn')
                    ->row()
                    ->button('Ссылка', url: 'https://example.com')
            )
            ->parseMode('markdown');

        $this->assertSame('Выберите действие', $msg->text);
        $this->assertSame('markdown', $msg->parseMode);
        $this->assertNotNull($msg->keyboard);
        $this->assertCount(2, $msg->keyboard['rows']);
    }

    public function test_make_without_extras(): void
    {
        $msg = Message::make('Simple text');
        $this->assertNull($msg->keyboard);
        $this->assertNull($msg->parseMode);
        $this->assertNull($msg->media);
    }
}
