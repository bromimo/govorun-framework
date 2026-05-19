<?php

namespace Govorun\Http;

use Psr\Http\Message\ResponseInterface;

/** Обёртка ответа HTTP-запроса через внешнее подключение. */
class HttpResponse
{
    private string $body;
    private int $statusCode;

    /** @param ResponseInterface $response PSR-7 ответ */
    public function __construct(ResponseInterface $response)
    {
        $this->statusCode = $response->getStatusCode();
        $this->body = (string) $response->getBody();
    }

    /** HTTP-статус ответа.
     * @return int
     */
    public function status(): int
    {
        return $this->statusCode;
    }

    /** Ответ успешен (2xx).
     * @return bool
     */
    public function successful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    /** Ответ неуспешен (не 2xx).
     * @return bool
     */
    public function failed(): bool
    {
        return ! $this->successful();
    }

    /** Тело ответа как строка.
     * @return string
     */
    public function body(): string
    {
        return $this->body;
    }

    /** Тело ответа как PHP-массив (JSON).
     * @return array<mixed>|null
     */
    public function json(): ?array
    {
        return json_decode($this->body, true) ?: null;
    }
}