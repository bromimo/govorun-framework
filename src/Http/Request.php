<?php

namespace Govorun\Http;

/** Обёртка над HTTP-запросом.
 * Предоставляет удобный доступ к данным входящего HTTP-запроса:
 * заголовкам, телу, параметрам строки запроса и метаданным сервера.
 */
class Request
{
    /** Создать экземпляр запроса.
     * @param array<string, mixed> $server Серверные переменные ($_SERVER)
     * @param array<string, mixed> $query Параметры строки запроса ($_GET)
     * @param array<string, mixed> $post Данные POST-запроса ($_POST)
     * @param string|null $content Необработанное тело запроса
     */
    public function __construct(
        protected array $server = [],
        protected array $query = [],
        protected array $post = [],
        protected ?string $content = null,
    ) {}

    /** Захватить текущий HTTP-запрос из глобальных переменных.
     * @return static Экземпляр запроса с данными текущего окружения
     */
    public static function capture(): static
    {
        return new static(
            server: $_SERVER,
            query: $_GET,
            post: $_POST,
            content: file_get_contents('php://input') ?: null,
        );
    }

    /** Получить необработанное тело запроса.
     * @return string|null Тело запроса или null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /** Декодировать тело запроса из JSON в массив.
     * @return array<string, mixed> Декодированные данные
     */
    public function json(): array
    {
        return json_decode($this->content ?? '{}', true) ?? [];
    }

    /** Получить значение HTTP-заголовка по имени.
     * @param string $name Имя заголовка (например, 'Content-Type')
     * @return string|null Значение заголовка или null
     */
    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    /** Получить HTTP-метод запроса.
     * @return string HTTP-метод (GET, POST и т.д.)
     */
    public function method(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    /** Получить полный URI запроса.
     * @return string URI запроса
     */
    public function uri(): string
    {
        return $this->server['REQUEST_URI'] ?? '/';
    }

    /** Получить путь запроса без строки запроса.
     * @return string Путь запроса
     */
    public function path(): string
    {
        return parse_url($this->uri(), PHP_URL_PATH) ?? '/';
    }

    /** Получить значение параметра строки запроса.
     * @param string $key Ключ параметра
     * @param mixed $default Значение по умолчанию
     * @return mixed Значение параметра или значение по умолчанию
     */
    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }
}
