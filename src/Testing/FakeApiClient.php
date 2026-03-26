<?php

namespace Govorun\Testing;

use Govorun\Http\ApiClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;

class FakeApiClient
{
    private MockHandler $mockHandler;
    private array $history = [];

    /** @var array<string, mixed> method+uri => response body */
    private array $mocks = [];

    public function __construct(private ApiClient $realClient)
    {
        $this->installHandler();
    }

    public function mockGet(string $uri, mixed $response): static
    {
        return $this->mock('GET', $uri, $response);
    }

    public function mockPost(string $uri, mixed $response): static
    {
        return $this->mock('POST', $uri, $response);
    }

    public function mockPut(string $uri, mixed $response): static
    {
        return $this->mock('PUT', $uri, $response);
    }

    public function mockDelete(string $uri, mixed $response): static
    {
        return $this->mock('DELETE', $uri, $response);
    }

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

    public function assertRequestCount(int $expected): static
    {
        Assert::assertCount(
            $expected,
            $this->history,
            "Expected {$expected} requests but " . count($this->history) . ' were made',
        );

        return $this;
    }

    private function mock(string $method, string $uri, mixed $response): static
    {
        $key = $method . ' ' . '/' . ltrim($uri, '/');
        $this->mocks[$key] = $response;
        $this->installHandler();

        return $this;
    }

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
