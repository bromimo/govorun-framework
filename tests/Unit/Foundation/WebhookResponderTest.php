<?php

namespace Govorun\Tests\Unit\Foundation;

use Govorun\Http\Request;
use Govorun\Routing\Route;
use Govorun\Tests\TestCase;
use Govorun\Http\WebhookResponse;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Foundation\Application;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\WebhookResponder;

class WebhookResponderTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();
        Route::clear();
        $this->app = new Application(__DIR__ . '/../../fixtures');
        $this->app->loadConfiguration();
    }

    protected function tearDown(): void
    {
        Route::clear();
        $this->app->flush();
        Application::setInstance(null);
        gc_collect_cycles();
        parent::tearDown();
    }

    private function makeResponder(?WebhookResponse $preflight, string $ack): MessengerDriver
    {
        return new class($preflight, $ack) implements MessengerDriver, WebhookResponder {
            public function __construct(
                private ?WebhookResponse $preflight,
                private string $ack,
            ) {}
            public function verifyWebhook(Request $request): bool { return true; }
            public function preflight(Request $request): ?WebhookResponse { return $this->preflight; }
            public function ackBody(): string { return $this->ack; }
            public function parseUpdate(Request $request): IncomingMessage
            {
                return new IncomingMessage(
                    id: '1', chatId: '100', driverName: 'vk',
                    text: 'hi', user: new UserDto(id: '100'), type: ContentType::Text,
                );
            }
            public function send(OutgoingMessage $message): ?string { return null; }
            public function edit(string $messageId, OutgoingMessage $message): void {}
            public function delete(string $messageId, string $chatId): void {}
            public function installWebhook(string $url): bool { return true; }
            public function removeWebhook(): bool { return true; }
            public function getUser(string $id): UserDto { return new UserDto(id: $id); }
        };
    }

    public function test_preflight_short_circuits_with_its_body(): void
    {
        $driver = $this->makeResponder(WebhookResponse::text('confirm-code'), 'ok');
        $this->app->instance('driver.vk', $driver);

        $request = new Request(server: ['REQUEST_URI' => '/webhook/vk'], content: '{}');
        $result = $this->app->handleWebhook($request);

        $this->assertSame(200, $result->status);
        $this->assertSame('confirm-code', $result->body);
    }

    public function test_ack_body_returned_after_dispatch(): void
    {
        $driver = $this->makeResponder(null, 'ok');
        $this->app->instance('driver.vk', $driver);

        $request = new Request(server: ['REQUEST_URI' => '/webhook/vk'], content: '{}');
        $result = $this->app->handleWebhook($request);

        $this->assertSame(200, $result->status);
        $this->assertSame('ok', $result->body);
    }
}