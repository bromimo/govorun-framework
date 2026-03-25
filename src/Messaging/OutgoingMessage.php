<?php

namespace Govorun\Messaging;

class OutgoingMessage
{
    public string $chatId = '';
    public ?string $text = null;
    public ?string $parseMode = null;
    public ?array $keyboard = null;
    public ?array $media = null;

    public function keyboard(Keyboard $keyboard): static
    {
        $this->keyboard = $keyboard->toArray();

        return $this;
    }

    public function parseMode(string $mode): static
    {
        $this->parseMode = $mode;

        return $this;
    }

    public function caption(string $text): static
    {
        $this->text = $text;

        return $this;
    }
}
