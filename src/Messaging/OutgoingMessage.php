<?php

namespace Govorun\Messaging;

class OutgoingMessage
{
    public string $chatId = '';
    public ?string $text = null;
    public ?string $parseMode = null;
    public ?array $keyboard = null;
    public ?array $media = null;
}
