<?php

namespace Govorun\Routing;

class RouteEntry
{
    public array $aliases = [];
    public array $middleware = [];
    public array $children = [];

    public function __construct(
        public string $type,
        public ?string $value,
        public mixed $action,
    ) {}

    public function alias(array $aliases): static
    {
        $this->aliases = $aliases;
        return $this;
    }
}
