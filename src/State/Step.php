<?php

namespace Govorun\State;

use Closure;

class Step
{
    private ?string $askText = null;
    private ?Closure $askCallback = null;
    private ?Closure $receiveCallback = null;

    public function ask(string $text, ?Closure $keyboardBuilder = null): void
    {
        $this->askText = $text;
        $this->askCallback = $keyboardBuilder;
    }

    public function receive(Closure $callback): void
    {
        $this->receiveCallback = $callback;
    }

    public function getAskText(): ?string
    {
        return $this->askText;
    }

    public function getAskCallback(): ?Closure
    {
        return $this->askCallback;
    }

    public function getReceiveCallback(): ?Closure
    {
        return $this->receiveCallback;
    }
}
