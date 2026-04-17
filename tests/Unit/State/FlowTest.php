<?php

namespace Govorun\Tests\Unit\State;

use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\StateStorage;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\State\FileStateStorage;
use Govorun\State\Flow;
use Govorun\State\Step;
use Govorun\Tests\TestCase;

class FlowTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir() . '/govorun_flow_test_' . uniqid();
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
            public function send(OutgoingMessage $message): void { $this->sent[] = $message; }
            public function edit(string $messageId, OutgoingMessage $message): void {}
            public function delete(string $messageId, string $chatId): void {}
            public function installWebhook(string $url): bool { return true; }
            public function removeWebhook(): bool { return true; }
            public function getUser(string $id): UserDto { return new UserDto(id: $id); }
        };
    }

    private function makeMessage(string $text = 'test', ContentType $type = ContentType::Text): IncomingMessage
    {
        return new IncomingMessage(
            id: '1',
            chatId: '100',
            driverName: 'telegram',
            text: $text,
            user: new UserDto(id: '100', firstName: 'Ivan'),
            type: $type,
        );
    }

    public function test_flow_starts_at_first_step(): void
    {
        $message = $this->makeMessage();
        $driver = $this->makeDriver();

        $flow = new TestBookingFlow($this->storage, $driver, $message);
        $flow->start();

        $this->assertCount(1, $this->sent);
        $this->assertSame('Choose service:', $this->sent[0]->text);

        // State is persisted
        $state = $this->storage->get('100', 'telegram');
        $this->assertSame(TestBookingFlow::class, $state['flow_class']);
        $this->assertSame('service', $state['current_step']);
    }

    public function test_flow_receives_input_and_advances(): void
    {
        $driver = $this->makeDriver();

        // Start the flow
        $startMessage = $this->makeMessage();
        $flow = new TestBookingFlow($this->storage, $driver, $startMessage);
        $flow->start();

        // User responds
        $this->sent = [];
        $inputMessage = $this->makeMessage('manicure');
        $flow = new TestBookingFlow($this->storage, $driver, $inputMessage);
        $flow->resume();

        // Should advance to next step and ask for date
        $this->assertCount(1, $this->sent);
        $this->assertSame('Choose date:', $this->sent[0]->text);

        $state = $this->storage->get('100', 'telegram');
        $this->assertSame('date', $state['current_step']);
        $this->assertSame('manicure', $state['data']['service']);
    }

    public function test_flow_completes_after_last_step(): void
    {
        $driver = $this->makeDriver();
        $message = $this->makeMessage();

        // Start and go through steps
        $flow = new TestBookingFlow($this->storage, $driver, $message);
        $flow->start();

        $this->sent = [];
        $flow = new TestBookingFlow($this->storage, $driver, $this->makeMessage('manicure'));
        $flow->resume();

        $this->sent = [];
        $flow = new TestBookingFlow($this->storage, $driver, $this->makeMessage('2026-04-01'));
        $flow->resume();

        // onComplete should fire and state should be cleared
        $this->assertCount(1, $this->sent);
        $this->assertSame('Booked!', $this->sent[0]->text);
        $this->assertNull($this->storage->get('100', 'telegram'));
    }

    public function test_flow_state_data_get_and_set(): void
    {
        $driver = $this->makeDriver();
        $message = $this->makeMessage();

        $flow = new TestBookingFlow($this->storage, $driver, $message);
        $flow->start();

        // After service step ask, send response
        $flow = new TestBookingFlow($this->storage, $driver, $this->makeMessage('pedicure'));
        $flow->resume();

        $state = $this->storage->get('100', 'telegram');
        $this->assertSame('pedicure', $state['data']['service']);
    }

    public function test_flow_interrupt_commands(): void
    {
        $driver = $this->makeDriver();
        $message = $this->makeMessage();

        $flow = new TestBookingFlow($this->storage, $driver, $message);
        $flow->start();

        // /cancel should interrupt
        $cancelMessage = $this->makeMessage('/cancel');
        $flow = new TestBookingFlow($this->storage, $driver, $cancelMessage);
        $shouldInterrupt = $flow->shouldInterrupt($cancelMessage);

        $this->assertTrue($shouldInterrupt);
    }

    public function test_flow_interrupt_on_event(): void
    {
        $driver = $this->makeDriver();
        $message = $this->makeMessage();

        $flow = new TestBookingFlow($this->storage, $driver, $message);
        $flow->start();

        $eventMessage = new IncomingMessage(
            id: '2', chatId: '100', driverName: 'telegram',
            text: null, user: new UserDto(id: '100'),
            type: ContentType::Event, event: 'subscribe',
        );

        $flow = new TestBookingFlow($this->storage, $driver, $eventMessage);
        $shouldInterrupt = $flow->shouldInterrupt($eventMessage);

        $this->assertTrue($shouldInterrupt);
    }

    public function test_next_step_with_name_jumps_to_named_step(): void
    {
        $driver = $this->makeDriver();
        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage());
        $flow->start();

        // start показывает ask первого шага; теперь отвечаем 'man' → должен прыгнуть на askMan
        $this->sent = [];
        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage('man'));
        $flow->resume();

        $this->assertCount(1, $this->sent);
        $this->assertSame('Мужской вопрос', $this->sent[0]->text);

        $state = $this->storage->get('100', 'telegram');
        $this->assertSame('askMan', $state['current_step']);
    }

    public function test_next_step_with_unknown_name_throws(): void
    {
        $driver = $this->makeDriver();
        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage());
        $flow->start();

        $this->expectException(\InvalidArgumentException::class);

        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage('broken'));
        $flow->resume();
    }

    public function test_complete_flow_clears_state_and_calls_on_complete(): void
    {
        $driver = $this->makeDriver();
        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage());
        $flow->start();

        $this->sent = [];
        $flow = new TestBranchingFlow($this->storage, $driver, $this->makeMessage('woman'));
        $flow->resume();

        // woman → completeFlow напрямую
        $this->assertCount(1, $this->sent);
        $this->assertSame('Done', $this->sent[0]->text);
        $this->assertNull($this->storage->get('100', 'telegram'));
    }
}

class TestBookingFlow extends Flow
{
    protected array $steps = ['service', 'date'];

    public function serviceStep(Step $step): void
    {
        $step->ask('Choose service:');

        $step->receive(function (IncomingMessage $message) {
            $this->state->set('service', $message->text);
            $this->nextStep();
        });
    }

    public function dateStep(Step $step): void
    {
        $step->ask('Choose date:');

        $step->receive(function (IncomingMessage $message) {
            $this->state->set('date', $message->text);
            $this->nextStep();
        });
    }

    public function onComplete(): void
    {
        $this->reply('Booked!');
    }
}

class TestBranchingFlow extends Flow
{
    // askWoman стоит между askGender и askMan умышленно: это делает
    // test_next_step_with_name_jumps_to_named_step настоящим true-positive — при
    // сломанной реализации nextStep(?string) поток без аргумента ушёл бы в askWoman.
    protected array $steps = ['askGender', 'askWoman', 'askMan'];

    public function askGenderStep(Step $step): void
    {
        $step->ask('Пол?');
        $step->receive(function (IncomingMessage $message) {
            match ($message->text) {
                'man'   => (function () { $this->nextStep('askMan'); return; })(),
                'woman' => (function () { $this->completeFlow(); return; })(),
                default => (function () { $this->nextStep('nonexistent'); return; })(),
            };
        });
    }

    public function askWomanStep(Step $step): void
    {
        $step->ask('Женский вопрос');
        $step->receive(function (IncomingMessage $message) {
            $this->completeFlow();
        });
    }

    public function askManStep(Step $step): void
    {
        $step->ask('Мужской вопрос');
        $step->receive(function (IncomingMessage $message) {
            $this->completeFlow();
        });
    }

    public function onComplete(): void
    {
        $this->reply('Done');
    }
}
