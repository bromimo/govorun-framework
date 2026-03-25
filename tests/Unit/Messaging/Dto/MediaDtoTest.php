<?php

namespace Govorun\Tests\Unit\Messaging\Dto;

use Govorun\Messaging\Dto\MediaDto;
use Govorun\Tests\TestCase;

class MediaDtoTest extends TestCase
{
    public function test_creates_with_all_fields(): void
    {
        $dto = new MediaDto(
            type: 'photo',
            url: 'https://example.com/img.jpg',
            fileId: 'abc123',
            mimeType: 'image/jpeg',
            fileSize: 102400,
            raw: ['width' => 800],
        );
        $this->assertSame('photo', $dto->type);
        $this->assertSame('https://example.com/img.jpg', $dto->url);
        $this->assertSame('abc123', $dto->fileId);
        $this->assertSame('image/jpeg', $dto->mimeType);
        $this->assertSame(102400, $dto->fileSize);
        $this->assertSame(['width' => 800], $dto->raw);
    }

    public function test_creates_with_minimal_fields(): void
    {
        $dto = new MediaDto(type: 'voice');
        $this->assertSame('voice', $dto->type);
        $this->assertNull($dto->url);
        $this->assertNull($dto->fileId);
        $this->assertNull($dto->mimeType);
        $this->assertNull($dto->fileSize);
        $this->assertSame([], $dto->raw);
    }
}
