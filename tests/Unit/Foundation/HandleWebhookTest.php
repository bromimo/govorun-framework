<?php

namespace Govorun\Tests\Unit\Foundation;

use Govorun\Contracts\MessengerDriver;
use Govorun\Foundation\Application;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Routing\Controller;
use Govorun\Routing\Route;
use Govorun\Tests\TestCase;

class HandleWebhookTest extends TestCase
{
    private Application $app;
    private array $sent = [];

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
        parent::tearDown();
    }

    private function makeFakeDriver(IncomingMessage $message): MessengerDriver
    {
        $sent = &$this->sent;

        return new class($message, $sent) implements MessengerDriver {
            public function __construct(
                private IncomingMessage $message,
                private array &$sent,
            ) {}

            public function verifyWebhook(Request $request): bool
            {
                return true;
            }

            public function parseUpdate(Request $request): IncomingMessage
            {
                return $this->message;
            }

            public function send(OutgoingMessage $message): void
            {
                $this->sent[] = $message;
            }

            public function edit(string $messageId, OutgoingMessage $message): void {}
            public function delete(string $messageId, string $chatId): void {}
            public function installWebhook(string $url): bool { return true; }
            public function removeWebhook(): bool { return true; }
            public function getUser(string $id): UserDto { return new UserDto(id: $id); }
        };
    }

    private function makeIncomingMessage(string $text = '/start'): IncomingMessage
    {
        return new IncomingMessage(
            id: '1',
            chatId: '100',
            driverName: 'telegram',
            text: $text,
            user: new UserDto(id: '100', firstName: 'Ivan'),
            type: ContentType::Text,
        );
    }

    public function test_handle_webhook_dispatches_to_controller(): void
    {
        $message = $this->makeIncomingMessage('/start');
        $driver = $this->makeFakeDriver($message);
        $this->app->instance(MessengerDriver::class, $driver);

        Route::command('start', TestStartController::class);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/telegram'],
            content: '{}',
        );

        $this->app->handleWebhook($request);

        $this->assertCount(1, $this->sent);
        $this->assertSame('Welcome!', $this->sent[0]->text);
    }

    public function test_handle_webhook_returns_403_on_invalid_webhook(): void
    {
        $message = $this->makeIncomingMessage();
        $sent = &$this->sent;

        $driver = new class($message, $sent) implements MessengerDriver {
            public function __construct(
                private IncomingMessage $message,
                private array &$sent,
            ) {}
            public function verifyWebhook(Request $request): bool { return false; }
            public function parseUpdate(Request $request): IncomingMessage { return $this->message; }
            public function send(OutgoingMessage $message): void { $this->sent[] = $message; }
            public function edit(string $messageId, OutgoingMessage $message): void {}
            public function delete(string $messageId, string $chatId): void {}
            public function installWebhook(string $url): bool { return true; }
            public function removeWebhook(): bool { return true; }
            public function getUser(string $id): UserDto { return new UserDto(id: $id); }
        };

        $this->app->instance(MessengerDriver::class, $driver);

        Route::command('start', TestStartController::class);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/telegram'],
            content: '{}',
        );

        $result = $this->app->handleWebhook($request);

        $this->assertSame(403, $result);
        $this->assertEmpty($this->sent);
    }

    public function test_handle_webhook_resolves_driver_name_from_url(): void
    {
        $message = $this->makeIncomingMessage('/start');
        $driver = $this->makeFakeDriver($message);

        // Bind driver under the resolved name convention (not globally)
        $this->app->instance('driver.telegram', $driver);

        Route::command('start', TestStartController::class);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/telegram'],
            content: '{}',
        );

        $result = $this->app->handleWebhook($request);

        $this->assertSame(200, $result);
        $this->assertCount(1, $this->sent);
    }
}

class TestStartController extends Controller
{
    public function handle(): void
    {
        $this->reply('Welcome!');
    }
}
