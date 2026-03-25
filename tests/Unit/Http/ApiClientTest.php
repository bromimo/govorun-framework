<?php

namespace Govorun\Tests\Unit\Http;

use Govorun\Exceptions\ApiException;
use Govorun\Http\ApiClient;
use Govorun\Tests\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;

class ApiClientTest extends TestCase
{
    private array $history = [];

    private function makeClient(array $responses): TestApiClient
    {
        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->history));

        $httpClient = new Client(['handler' => $stack]);

        return new TestApiClient($httpClient);
    }

    public function test_get_request(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"data": [1, 2, 3]}'),
        ]);

        $result = $client->fetchItems(['page' => '1']);

        $this->assertSame([1, 2, 3], $result['data']);
        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/items', $request->getUri()->getPath());
        $this->assertSame('page=1', $request->getUri()->getQuery());
    }

    public function test_post_request(): void
    {
        $client = $this->makeClient([
            new Response(201, [], '{"id": 1}'),
        ]);

        $result = $client->createItem(['name' => 'Test']);

        $this->assertSame(1, $result['id']);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('Test', $body['name']);
    }

    public function test_put_request(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{"updated": true}'),
        ]);

        $result = $client->updateItem(['name' => 'Updated']);

        $this->assertTrue($result['updated']);
        $this->assertSame('PUT', $this->history[0]['request']->getMethod());
    }

    public function test_delete_request(): void
    {
        $client = $this->makeClient([
            new Response(204, [], ''),
        ]);

        $result = $client->removeItem();

        $this->assertSame([], $result);
        $this->assertSame('DELETE', $this->history[0]['request']->getMethod());
    }

    public function test_custom_headers_are_sent(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{}'),
        ]);

        $client->fetchItems();

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer test-token', $request->getHeaderLine('Authorization'));
    }

    public function test_timeout_is_configured(): void
    {
        $client = $this->makeClient([
            new Response(200, [], '{}'),
        ]);

        $client->fetchItems();

        $options = $this->history[0]['options'];
        $this->assertSame(5, $options['timeout']);
    }

    public function test_api_exception_on_http_error(): void
    {
        $client = $this->makeClient([
            new Response(500, [], '{"error": "Internal Server Error"}'),
        ]);

        $this->expectException(ApiException::class);
        $client->fetchItems();
    }

    public function test_api_exception_on_network_error(): void
    {
        $client = $this->makeClient([
            new ConnectException('Connection refused', new GuzzleRequest('GET', '/items')),
        ]);

        $this->expectException(ApiException::class);
        $client->fetchItems();
    }

    public function test_retries_on_failure(): void
    {
        $client = $this->makeClient([
            new Response(500, [], '{}'),
            new Response(500, [], '{}'),
            new Response(200, [], '{"ok": true}'),
        ]);
        $client->setRetries(2);

        $result = $client->fetchItems();

        $this->assertTrue($result['ok']);
        $this->assertCount(3, $this->history);
    }
}

class TestApiClient extends ApiClient
{
    public function __construct(\GuzzleHttp\ClientInterface $client)
    {
        $this->client = $client;
    }

    protected function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    protected function headers(): array
    {
        return ['Authorization' => 'Bearer test-token'];
    }

    public function fetchItems(array $params = []): mixed
    {
        return $this->get('/items', $params);
    }

    public function createItem(array $data): mixed
    {
        return $this->post('/items', $data);
    }

    public function updateItem(array $data): mixed
    {
        return $this->put('/items/1', $data);
    }

    public function removeItem(): mixed
    {
        return $this->delete('/items/1');
    }

    public function setRetries(int $retries): void
    {
        $this->retries = $retries;
    }
}
