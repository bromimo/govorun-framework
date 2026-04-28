<?php

namespace Govorun\Tests\Unit\State;

use Govorun\State\Step;
use Govorun\State\Flow;
use Govorun\Tests\TestCase;
use Govorun\Testing\FakeDriver;
use Govorun\Messaging\Button;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\ContentType;
use Govorun\State\FileStateStorage;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;

class FlowInterruptOnTextTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;
    private FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir() . '/govorun_iot_test_' . uniqid();
        mkdir($this->storagePath, 0777, true);
        $this->storage = new FileStateStorage($this->storagePath);
        $this->driver = new FakeDriver();
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

    private function makeMessage(?string $text = null, ?string $action = null): IncomingMessage
    {
        return new IncomingMessage(
            id: '1',
            chatId: '100',
            driverName: 'telegram',
            text: $text,
            user: new UserDto(id: '100'),
            type: $action !== null ? ContentType::Event : ContentType::Text,
            action: $action,
        );
    }

    public function test_text_interrupts_flow_when_ask_keyboard_active(): void
    {
        $startMsg = $this->makeMessage();
        $flow = new InterruptOnTextFlow($this->storage, $this->driver, $startMsg);
        $flow->start();

        $textMsg = $this->makeMessage('какой-то текст');
        $resumeFlow = new InterruptOnTextFlow($this->storage, $this->driver, $textMsg);

        $this->assertTrue($resumeFlow->shouldInterrupt($textMsg));
    }

    public function test_callback_does_not_interrupt_flow(): void
    {
        $startMsg = $this->makeMessage();
        $flow = new InterruptOnTextFlow($this->storage, $this->driver, $startMsg);
        $flow->start();

        $callbackMsg = $this->makeMessage(action: 'opt1');
        $resumeFlow = new InterruptOnTextFlow($this->storage, $this->driver, $callbackMsg);

        $this->assertFalse($resumeFlow->shouldInterrupt($callbackMsg));
    }

    public function test_text_does_not_interrupt_when_no_ask_keyboard_active(): void
    {
        $textMsg = $this->makeMessage('обычный текст');
        $flow = new InterruptOnTextFlow($this->storage, $this->driver, $textMsg);

        $this->assertFalse($flow->shouldInterrupt($textMsg));
    }
}

class InterruptOnTextFlow extends Flow
{
    protected array $steps = ['askColor'];

    protected function askColorStep(Step $step): void
    {
        $kb = Keyboard::make()->buttons([
            [Button::make('Red')->action('red')],
            [Button::make('Blue')->action('blue')],
        ]);
        $step->ask('Pick a color', $kb);
        $step->receive(fn () => $this->nextStep());
    }
}