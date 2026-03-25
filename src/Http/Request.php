<?php

namespace Govorun\Http;

class Request
{
    public function __construct(
        protected array $server = [],
        protected array $query = [],
        protected array $post = [],
        protected ?string $content = null,
    ) {}

    public static function capture(): static
    {
        return new static(
            server: $_SERVER,
            query: $_GET,
            post: $_POST,
            content: file_get_contents('php://input') ?: null,
        );
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function json(): array
    {
        return json_decode($this->content ?? '{}', true) ?? [];
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    public function path(): string
    {
        return parse_url($this->uri(), PHP_URL_PATH) ?? '/';
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
}
