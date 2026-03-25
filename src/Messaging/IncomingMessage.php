<?php

namespace Govorun\Messaging;

use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\UserDto;

class IncomingMessage
{
    public function __construct(
        public string $id,
        public string $chatId,
        public string $driverName,
        public ?string $text,
        public UserDto $user,
        public ContentType $type,
        public ?string $action = null,
        public ?array $actionParams = null,
        public ?string $event = null,
        public ?MediaDto $media = null,
        public ?LocationDto $location = null,
        public ?ContactDto $contact = null,
        public ?string $referral = null,
        public array $raw = [],
    ) {}
}
