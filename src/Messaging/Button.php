<?php

namespace Govorun\Messaging;

class Button
{
    public function __construct(
        public string $text,
        public ?string $action = null,
        public ?array $param = null,
        public ?string $url = null,
        public bool $requestContact = false,
        public bool $requestLocation = false,
    ) {}

    public function toArray(): array
    {
        $data = ['text' => $this->text];

        if ($this->action !== null) {
            $data['action'] = $this->action;
        }
        if ($this->param !== null) {
            $data['param'] = $this->param;
        }
        if ($this->url !== null) {
            $data['url'] = $this->url;
        }
        if ($this->requestContact) {
            $data['requestContact'] = true;
        }
        if ($this->requestLocation) {
            $data['requestLocation'] = true;
        }

        return $data;
    }
}
