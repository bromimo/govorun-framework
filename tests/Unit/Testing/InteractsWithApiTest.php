<?php

namespace Govorun\Tests\Unit\Testing;

use Govorun\Foundation\Application;
use Govorun\Http\ApiClient;
use Govorun\Testing\FakeApiClient;
use Govorun\Testing\InteractsWithApi;
use PHPUnit\Framework\TestCase;

class InteractsWithApiTest extends TestCase
{
    use InteractsWithApi;

    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = new Application(__DIR__ . '/../../fixtures');
        $this->app->loadConfiguration();
    }

    protected function tearDown(): void
    {
        Application::setInstance(null);
        parent::tearDown();
    }

    public function test_fake_api_returns_fake_api_client(): void
    {
        $fake = $this->fakeApi(InteractsWithApiTestClient::class);
        $this->assertInstanceOf(FakeApiClient::class, $fake);
    }

    public function test_fake_api_registers_instance_in_container(): void
    {
        $this->fakeApi(InteractsWithApiTestClient::class);

        $resolved = $this->app->make(InteractsWithApiTestClient::class);
        $this->assertInstanceOf(InteractsWithApiTestClient::class, $resolved);
    }

    public function test_fake_api_mocked_calls_work(): void
    {
        $fake = $this->fakeApi(InteractsWithApiTestClient::class);
        $fake->mockGet('/data', ['key' => 'value']);

        $client = $this->app->make(InteractsWithApiTestClient::class);
        $result = $client->fetchData();

        $this->assertSame(['key' => 'value'], $result);
        $fake->assertRequestMade('GET', '/data');
        $fake->assertRequestCount(1);
    }
}

class InteractsWithApiTestClient extends ApiClient
{
    public function __construct()
    {
        // No Guzzle client — will be injected by FakeApiClient
    }

    protected function baseUrl(): string
    {
        return 'https://api.example.com';
    }

    public function fetchData(): mixed
    {
        return $this->get('/data');
    }
}
