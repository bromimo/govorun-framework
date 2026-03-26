<?php

namespace Govorun\Tests\Unit\Testing;

use Govorun\Foundation\Application;
use Govorun\Http\ApiClient;
use Govorun\Testing\FakeApiClient;
use GuzzleHttp\ClientInterface;
use PHPUnit\Framework\TestCase;

class FakeApiClientTest extends TestCase
{
    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    private function makeRealClient(): TestableApiClient
    {
        return new TestableApiClient();
    }

    public function test_mock_get_returns_prepared_response(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockGet('/items', [['id' => 1, 'name' => 'Test']]);

        $result = $real->fetchItems();
        $this->assertSame([['id' => 1, 'name' => 'Test']], $result);
    }

    public function test_mock_post_returns_prepared_response(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockPost('/items', ['id' => 2, 'status' => 'created']);

        $result = $real->createItem(['name' => 'New']);
        $this->assertSame(['id' => 2, 'status' => 'created'], $result);
    }

    public function test_mock_put_returns_prepared_response(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockPut('/items/1', ['updated' => true]);

        $result = $real->updateItem(['name' => 'Updated']);
        $this->assertSame(['updated' => true], $result);
    }

    public function test_mock_delete_returns_prepared_response(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockDelete('/items/1', []);

        $result = $real->removeItem();
        $this->assertSame([], $result);
    }

    public function test_assert_request_made(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockGet('/items', []);
        $real->fetchItems();

        $fake->assertRequestMade('GET', '/items');
    }

    public function test_assert_request_made_fails_when_not_called(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $fake->assertRequestMade('GET', '/items');
    }

    public function test_assert_request_count(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $fake->mockGet('/items', []);
        $fake->mockPost('/items', ['id' => 1]);

        $real->fetchItems();
        $real->createItem(['name' => 'Test']);

        $fake->assertRequestCount(2);
    }

    public function test_unmocked_request_throws(): void
    {
        $real = $this->makeRealClient();
        $fake = new FakeApiClient($real);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No mock registered for');

        $real->fetchItems();
    }
}

class TestableApiClient extends ApiClient
{
    public function __construct()
    {
        // No Guzzle client needed — FakeApiClient will inject one
    }

    protected function baseUrl(): string
    {
        return 'https://api.example.com';
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
}
