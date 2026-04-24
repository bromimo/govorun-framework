<?php

namespace Govorun\Tests\Unit\Routing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\Message;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Routing\Controller;
use Govorun\State\FileStateStorage;
use Govorun\State\Flow;
use Govorun\State\Step;
use Govorun\Tests\TestCase;

class ControllerTest extends TestCase
{
    private function makeMessage(
        string $text = 'test',
        ?string $action = null,
        ?array $actionParams = null,
    ): IncomingMessage {
        return new IncomingMessage(
            id: 'msg1', chatId: 'chat1', driverName: 'telegram',
            text: $text,
            user: new UserDto(id: '100', firstName: 'Ivan'),
            type: $action ? ContentType::Action : ContentType::Text,
            action: $action, actionParams: $actionParams,
        );
    }

    public function test_message_returns_incoming_message(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage('Привет');
        $controller = new class extends Controller {
            public function test(): IncomingMessage { return $this->message(); }
        };
        $controller->setContext($msg, $driver);
        $this->assertSame($msg, $controller->test());
    }

    public function test_message_property_returns_incoming_message(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage('Привет');
        $controller = new class extends Controller {
            public function test(): IncomingMessage { return $this->message; }
        };
        $controller->setContext($msg, $driver);
        $this->assertSame($msg, $controller->test());
    }

    public function test_message_property_exposes_user_first_name(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage();
        $controller = new class extends Controller {
            public function test(): ?string { return $this->message->user->firstName; }
        };
        $controller->setContext($msg, $driver);
        $this->assertSame('Ivan', $controller->test());
    }

    public function test_user_returns_user_dto(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage();
        $controller = new class extends Controller {
            public function test(): UserDto { return $this->user(); }
        };
        $controller->setContext($msg, $driver);
        $this->assertSame('100', $controller->test()->id);
        $this->assertSame('Ivan', $controller->test()->firstName);
    }

    public function test_reply_sends_text_message(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('send')
            ->with($this->callback(function (OutgoingMessage $msg) {
                return $msg->text === 'Привет!' && $msg->chatId === 'chat1';
            }));
        $msg = $this->makeMessage();
        $controller = new class extends Controller {
            public function test(): void { $this->reply('Привет!'); }
        };
        $controller->setContext($msg, $driver);
        $controller->test();
    }

    public function test_send_sends_outgoing_message_with_chat_id(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('send')
            ->with($this->callback(function (OutgoingMessage $msg) {
                return $msg->text === 'Выберите' && $msg->chatId === 'chat1';
            }));
        $msg = $this->makeMessage();
        $controller = new class extends Controller {
            public function test(): void { $this->send(Message::make('Выберите')); }
        };
        $controller->setContext($msg, $driver);
        $controller->test();
    }

    public function test_param_returns_action_parameter(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage(action: 'service', actionParams: ['type' => 'manicure']);
        $controller = new class extends Controller {
            public function test(): ?string { return $this->param('type'); }
        };
        $controller->setContext($msg, $driver);
        $this->assertSame('manicure', $controller->test());
    }

    public function test_param_returns_null_for_missing_key(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage(action: 'service', actionParams: ['type' => 'manicure']);
        $controller = new class extends Controller {
            public function test(): ?string { return $this->param('missing'); }
        };
        $controller->setContext($msg, $driver);
        $this->assertNull($controller->test());
    }

    public function test_param_returns_null_when_no_action_params(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $msg = $this->makeMessage();
        $controller = new class extends Controller {
            public function test(): ?string { return $this->param('anything'); }
        };
        $controller->setContext($msg, $driver);
        $this->assertNull($controller->test());
    }

    public function test_start_flow_persists_state(): void
    {
        $tmpDir = sys_get_temp_dir() . '/govorun_test_' . uniqid();
        $storage = new FileStateStorage($tmpDir);

        $driver = $this->createMock(MessengerDriver::class);
        $driver->expects($this->once())
            ->method('send')
            ->with($this->callback(function (OutgoingMessage $msg) {
                return $msg->text === 'Enter your name:' && $msg->chatId === 'chat1';
            }));

        $msg = $this->makeMessage('hello');

        $controller = new TestFlowController();
        $controller->setContext($msg, $driver);
        $controller->setStateStorage($storage);
        $controller->handle();

        $state = $storage->get('chat1', 'telegram');
        $this->assertNotNull($state);
        $this->assertSame('name', $state['current_step']);
        $this->assertSame(ControllerTestFlow::class, $state['flow_class']);

        // Cleanup
        array_map('unlink', glob($tmpDir . '/*'));
        @rmdir($tmpDir);
    }
}

class ControllerTestFlow extends Flow
{
    protected array $steps = ['name'];

    public function nameStep(Step $step): void
    {
        $step->ask('Enter your name:');
        $step->receive(function (IncomingMessage $message) {
            $this->nextStep();
        });
    }

    public function onComplete(): void
    {
        $this->reply('Done!');
    }
}

class TestFlowController extends Controller
{
    public function handle(): void
    {
        $this->startFlow(ControllerTestFlow::class);
    }
}
