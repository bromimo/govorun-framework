<?php

namespace Govorun\Http;

/** Фабрика HTTP-клиентов для зарегистрированных подключений. */
class HttpManager
{
    /** @param array<string, array<string, mixed>> $connections Конфиги подключений из connections.php */
    public function __construct(private array $connections) {}

    /** Получить клиент для подключения по slug.
     * @param string $slug Идентификатор подключения
     * @return ConnectionClient
     */
    public function connection(string $slug): ConnectionClient
    {
        return new ConnectionClient($this->connections[$slug] ?? []);
    }
}