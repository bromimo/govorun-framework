<?php

namespace Govorun\Tests\Unit\Messaging\Dto;

use Govorun\Messaging\Dto\ContactDto;
use Govorun\Tests\TestCase;

class ContactDtoTest extends TestCase
{
    public function test_creates_with_all_fields(): void
    {
        $dto = new ContactDto(
            phone: '+79001234567',
            firstName: 'Ivan',
            lastName: 'Petrov',
            userId: '789',
            raw: ['vcard' => '...'],
        );
        $this->assertSame('+79001234567', $dto->phone);
        $this->assertSame('Ivan', $dto->firstName);
        $this->assertSame('Petrov', $dto->lastName);
        $this->assertSame('789', $dto->userId);
        $this->assertSame(['vcard' => '...'], $dto->raw);
    }

    public function test_creates_with_minimal_fields(): void
    {
        $dto = new ContactDto(phone: '+79001234567');
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->lastName);
        $this->assertNull($dto->userId);
        $this->assertSame([], $dto->raw);
    }
}
