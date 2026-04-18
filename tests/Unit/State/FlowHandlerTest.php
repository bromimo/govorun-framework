<?php

namespace Govorun\Tests\Unit\State;

use Govorun\Contracts\MessengerDriver;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\State\FileStateStorage;
use Govorun\State\Flow;
use Govorun\State\FlowHandler;
use Govorun\State\Step;
use Govorun\Tests\TestCase;

class FlowHandlerTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir() . '/govorun_fh_test_' . uniqid();
        mkdir($this->storagePath, 0777, true);
        $this->storage = new FileStateStorage($this->storagePath);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storagePath . '/*.json') as $file) {
            unlink($file);
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
        parent::tearDown();
    }

    private function makeDriver(): MessengerDriver
    {
        $sent = &$this->sent;

        return new class($sent) implements MessengerDriver {
            public function __construct(private array &$sent) {}
            public function verifyWebhook(Request $request): bool { return true; }
            public function parseUpdate(Request $request): IncomingMessage {
                return new IncomingMessage(id: '1', chatId: '1', driverName: 'telegram', text: '', user: new UserDto(id: '1'), type: ContentType::Text);
            }
            public function send(OutgoingMessage $message): ?string { $this->sent[] = $message; return null; }
            public function edit(string $messageId, OutgoingMessage $message): void {}
            public function delete(string $messageId, string $chatId): void {}
            public function installWebhook(string $url): bool { return true; }
            public function removeWebhook(): bool { return true; }
            public function getUser(string $id): UserDto { return new UserDto(id: $id); }
        };
    }

    public function test_returns_false_when_no_active_flow(): void
    {
        $driver = $this->makeDriver();
        $handler = new FlowHandler($this->storage, $driver);

        $message = new IncomingMessage(
            id: '1', chatId: '100', driverName: 'telegram',
            text: '/start', user: new UserDto(id: '100'),
            type: ContentType::Text,
        );

        $this->assertFalse($handler->handle($message));
    }

    public function test_returns_true_and_resumes_flow_when_active(): void
    {
        $driver = $this->makeDriver();

        $this->storage->set('100', 'telegram', [
            'flow_class' => SimpleTestFlow::class,
            'current_step' => 'name',
            'data' => [],
        ]);

        $handler = new FlowHandler($this->storage, $driver);

        $message = new IncomingMessage(
            id: '1', chatId: '100', driverName: 'telegram',
            text: 'Ivan', user: new UserDto(id: '100'),
            type: ContentType::Text,
        );

        $result = $handler->handle($message);

        $this->assertTrue($result);
        $this->assertNotEmpty($this->sent);
    }

    public function test_returns_false_when_flow_should_be_interrupted_by_command(): void
    {
        $driver = $this->makeDriver();

        $this->storage->set('100', 'telegram', [
            'flow_class' => SimpleTestFlow::class,
            'current_step' => 'name',
            'data' => [],
        ]);

        $handler = new FlowHandler($this->storage, $driver);

        $message = new IncomingMessage(
            id: '1', chatId: '100', driverName: 'telegram',
            text: '/start', user: new UserDto(id: '100'),
            type: ContentType::Text,
        );

        $result = $handler->handle($message);

        $this->assertFalse($result);
        $this->assertNull($this->storage->get('100', 'telegram'));
    }

    public function test_returns_false_when_flow_interrupted_by_event(): void
    {
        $driver = $this->makeDriver();

        $this->storage->set('100', 'telegram', [
            'flow_class' => SimpleTestFlow::class,
            'current_step' => 'name',
            'data' => [],
        ]);

        $handler = new FlowHandler($this->storage, $driver);

        $message = new IncomingMessage(
            id: '1', chatId: '100', driverName: 'telegram',
            text: null, user: new UserDto(id: '100'),
            type: ContentType::Event, event: 'subscribe',
        );

        $result = $handler->handle($message);

        $this->assertFalse($result);
    }
}

class SimpleTestFlow extends Flow
{
    protected array $steps = ['name'];

    public function nameStep(Step $step): void
    {
        $step->ask('Enter your name:');

        $step->receive(function (IncomingMessage $message) {
            $this->state->set('name', $message->text);
            $this->nextStep();
        });
    }

    public function onComplete(): void
    {
        $this->reply('Done! Name: ' . $this->state->get('name'));
    }
}
