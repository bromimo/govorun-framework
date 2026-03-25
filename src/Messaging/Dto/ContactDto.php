<?php

namespace Govorun\Messaging\Dto;

class ContactDto
{
    public function __construct(
        public string $phone,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $userId = null,
        public array $raw = [],
    ) {}
}
