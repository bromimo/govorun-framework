<?php

namespace Govorun\Tests\Unit\Messaging\Dto;

use Govorun\Messaging\Dto\LocationDto;
use Govorun\Tests\TestCase;

class LocationDtoTest extends TestCase
{
    public function test_creates_with_coordinates(): void
    {
        $dto = new LocationDto(latitude: 55.7558, longitude: 37.6173, raw: ['accuracy' => 10]);
        $this->assertSame(55.7558, $dto->latitude);
        $this->assertSame(37.6173, $dto->longitude);
        $this->assertSame(['accuracy' => 10], $dto->raw);
    }

    public function test_creates_with_minimal_fields(): void
    {
        $dto = new LocationDto(latitude: 0.0, longitude: 0.0);
        $this->assertSame([], $dto->raw);
    }
}
