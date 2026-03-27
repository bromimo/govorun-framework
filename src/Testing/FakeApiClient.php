<?php

namespace Govorun\Testing;

use GuzzleHttp\Client;
use GuzzleHttp\Middleware;
use Govorun\Http\ApiClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;
use GuzzleHttp\Handler\MockHandler;

/** Обёртка над ApiClient для мокирования HTTP-запросов в тестах.
 * Через рефлексию подменяет Guzzle-клиент внутри реального ApiClient
 * на MockHandler, позволяя регистрировать заготовленные ответы и проверять вызовы.
 */
class FakeApiClient
{
    /** @var MockHandler */
    private MockHandler $mockHandler;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface}> История запросов */
    private array $history = [];

    /** @var array<string, mixed> Зарегистрированные моки (method+uri => response body) */
    private array $mocks = [];

    /** Создать экземпляр FakeApiClient.
     * @param ApiClient $realClient Оригинальный экземпляр ApiClient
     */
    public function __construct(private ApiClient $realClient)
    {
        $this->installHandler();
    }

    /** Регистрирует мок-ответ для GET-запроса.
     * @param string $uri      URI запроса
     * @param mixed  $response Тело ответа
     * @return static
     */
    public function mockGet(string $uri, mixed $response): static
    {
        return $this->mock('GET', $uri, $response);
    }

    /** Регистрирует мок-ответ для POST-запроса.
     * @param string $uri      URI запроса
     * @param mixed  $response Тело ответа
     * @return static
     */
    public function mockPost(string $uri, mixed $response): static
    {
        return $this->mock('POST', $uri, $response);
    }

    /** Регистрирует мок-ответ для PUT-запроса.
     * @param string $uri      URI запроса
     * @param mixed  $response Тело ответа
     * @return static
     */
    public function mockPut(string $uri, mixed $response): static
    {
        return $this->mock('PUT', $uri, $response);
    }

    /** Регистрирует мок-ответ для DELETE-запроса.
     * @param string $uri      URI запроса
     * @param mixed  $response Тело ответа
     * @return static
     */
    public function mockDelete(string $uri, mixed $response): static
    {
        return $this->mock('DELETE', $uri, $response);
    }

    /** Проверяет, что запрос с указанным методом и URI был выполнен.
     * @param string $method HTTP-метод (GET, POST, PUT, DELETE)
     * @param string $uri    URI запроса
     * @return static
     */
    public function assertRequestMade(string $method, string $uri): static
    {
        $normalizedUri = '/' . ltrim($uri, '/');

        foreach ($this->history as $entry) {
            $request = $entry['request'];
            $requestUri = $request->getUri()->getPath();
            if (str_contains($requestUri, $normalizedUri) && $request->getMethod() === $method) {
                Assert::assertTrue(true);
                return $this;
            }
        }

        Assert::fail("Expected request {$method} {$uri} was not made");

        return $this;
    }

    /** Проверяет общее количество выполненных запросов.
     * @param int $expected Ожидаемое количество
     * @return static
     */
    public function assertRequestCount(int $expected): static
    {
        Assert::assertCount(
            $expected,
            $this->history,
            "Expected {$expected} requests but " . count($this->history) . ' were made',
        );

        return $this;
    }

    /** Регистрирует мок-ответ для указанного метода и URI.
     * @param string $method   HTTP-метод
     * @param string $uri      URI запроса
     * @param mixed  $response Тело ответа
     * @return static
     */
    private function mock(string $method, string $uri, mixed $response): static
    {
        $key = $method . ' ' . '/' . ltrim($uri, '/');
        $this->mocks[$key] = $response;
        $this->installHandler();

        return $this;
    }

    /** Устанавливает мок-обработчик в реальный ApiClient через рефлексию.
     * @return void
     */
    private function installHandler(): void
    {
        $mocks = &$this->mocks;
        $history = &$this->history;

        $handler = function (\Psr\Http\Message\RequestInterface $request, array $options) use (&$mocks) {
            $method = $request->getMethod();
            $path = $request->getUri()->getPath();

            foreach ($mocks as $key => $response) {
                [$mockMethod, $mockUri] = explode(' ', $key, 2);
                if ($method === $mockMethod && str_contains($path, $mockUri)) {
                    $body = json_encode($response);
                    return new \GuzzleHttp\Promise\FulfilledPromise(
                        new Response(200, ['Content-Type' => 'application/json'], $body)
                    );
                }
            }

            throw new \RuntimeException("No mock registered for {$method} {$path}");
        };

        $stack = new HandlerStack($handler);
        $stack->push(Middleware::history($history));

        $httpClient = new Client(['handler' => $stack]);

        $ref = new \ReflectionProperty(ApiClient::class, 'client');
        $ref->setValue($this->realClient, $httpClient);
    }
}
