<?php

namespace Govorun\Messaging\Dto;

class UserDto
{
    public function __construct(
        public string $id,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $phone = null,
        public ?string $locale = null,
        public array $raw = [],
    ) {}
}
