<?php

namespace Govorun\Tests\Unit\Messaging\Dto;

use Govorun\Messaging\Dto\UserDto;
use Govorun\Tests\TestCase;

class UserDtoTest extends TestCase
{
    public function test_creates_with_all_fields(): void
    {
        $dto = new UserDto(
            id: '123',
            firstName: 'Ivan',
            lastName: 'Petrov',
            username: 'ipetrov',
            phone: '+79001234567',
            locale: 'ru',
            raw: ['extra' => 'data'],
        );

        $this->assertSame('123', $dto->id);
        $this->assertSame('Ivan', $dto->firstName);
        $this->assertSame('Petrov', $dto->lastName);
        $this->assertSame('ipetrov', $dto->username);
        $this->assertSame('+79001234567', $dto->phone);
        $this->assertSame('ru', $dto->locale);
        $this->assertSame(['extra' => 'data'], $dto->raw);
    }

    public function test_creates_with_minimal_fields(): void
    {
        $dto = new UserDto(id: '456');
        $this->assertSame('456', $dto->id);
        $this->assertNull($dto->firstName);
        $this->assertNull($dto->lastName);
        $this->assertNull($dto->username);
        $this->assertNull($dto->phone);
        $this->assertNull($dto->locale);
        $this->assertSame([], $dto->raw);
    }
}
