<?php

namespace Govorun\Tests\Unit\Messaging;

use Govorun\Messaging\Media;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Tests\TestCase;

class MediaTest extends TestCase
{
    public function test_photo_creates_outgoing_message(): void
    {
        $msg = Media::photo('https://example.com/img.jpg');
        $this->assertInstanceOf(OutgoingMessage::class, $msg);
        $this->assertSame('photo', $msg->media['type']);
        $this->assertSame('https://example.com/img.jpg', $msg->media['url']);
    }

    public function test_photo_with_caption(): void
    {
        $msg = Media::photo('https://example.com/img.jpg')->caption('Описание');
        $this->assertSame('Описание', $msg->text);
        $this->assertSame('photo', $msg->media['type']);
    }

    public function test_document_creates_outgoing_message(): void
    {
        $msg = Media::document('/path/to/file.pdf');
        $this->assertSame('document', $msg->media['type']);
        $this->assertSame('/path/to/file.pdf', $msg->media['url']);
    }

    public function test_document_with_caption(): void
    {
        $msg = Media::document('/path/to/file.pdf')->caption('Документ');
        $this->assertSame('Документ', $msg->text);
    }

    public function test_voice_creates_outgoing_message(): void
    {
        $msg = Media::voice('/path/to/audio.ogg');
        $this->assertSame('voice', $msg->media['type']);
        $this->assertSame('/path/to/audio.ogg', $msg->media['url']);
    }

    public function test_voice_has_no_text_by_default(): void
    {
        $msg = Media::voice('/path/to/audio.ogg');
        $this->assertNull($msg->text);
    }

    public function test_video_creates_outgoing_message(): void
    {
        $msg = Media::video('https://example.com/clip.mp4');
        $this->assertInstanceOf(OutgoingMessage::class, $msg);
        $this->assertSame('video', $msg->media['type']);
        $this->assertSame('https://example.com/clip.mp4', $msg->media['url']);
    }

    public function test_video_with_caption(): void
    {
        $msg = Media::video('https://example.com/clip.mp4')->caption('Описание');
        $this->assertSame('Описание', $msg->text);
    }
}
