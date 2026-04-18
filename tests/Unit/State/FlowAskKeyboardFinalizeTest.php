<?php

namespace Govorun\Tests\Unit\State;

use Govorun\State\Step;
use Govorun\State\Flow;
use Govorun\Tests\TestCase;
use Govorun\Testing\FakeDriver;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\ContentType;
use Govorun\State\FileStateStorage;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;

class FlowAskKeyboardFinalizeTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;
    private FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir() . '/govorun_akf_test_' . uniqid();
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

    public function test_ask_keyboard_saves_context_to_state(): void
    {
        $message = $this->makeMessage();
        $flow = new InlineAskFlow($this->storage, $this->driver, $message);

        $flow->start();

        $state = $this->storage->get('100', 'telegram');
        $ctx = $state['data']['__ask_keyboard_ctx'] ?? null;

        $this->assertNotNull($ctx);
        $this->assertSame('1', $ctx['message_id']);
        $this->assertSame('Выбери', $ctx['original_text']);
        $this->assertNull($ctx['parse_mode']);
        $this->assertSame(['yes' => 'Да', 'no' => 'Нет'], $ctx['label_map']);
    }

    public function test_reply_keyboard_does_not_save_context(): void
    {
        $message = $this->makeMessage();
        $flow = new ReplyAskFlow($this->storage, $this->driver, $message);

        $flow->start();

        $state = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('__ask_keyboard_ctx', $state['data'] ?? []);
    }

    public function test_url_only_keyboard_does_not_save_context(): void
    {
        $message = $this->makeMessage();
        $flow = new UrlOnlyAskFlow($this->storage, $this->driver, $message);

        $flow->start();

        $state = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('__ask_keyboard_ctx', $state['data'] ?? []);
    }

    public function test_null_message_id_skips_context_save(): void
    {
        $driver = new class extends FakeDriver {
            public function send(\Govorun\Messaging\OutgoingMessage $message): ?string
            {
                parent::send($message);
                return null;
            }
        };

        $flow = new InlineAskFlow($this->storage, $driver, $this->makeMessage());
        $flow->start();

        $state = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('__ask_keyboard_ctx', $state['data'] ?? []);
    }

    public function test_finalize_edits_message_with_selected_label(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => InlineAskFlow::class,
            'current_step' => 'pick',
            'data' => [
                '__ask_keyboard_ctx' => [
                    'message_id' => '42',
                    'original_text' => 'Выбери',
                    'parse_mode' => null,
                    'label_map' => ['yes' => 'Да', 'no' => 'Нет'],
                ],
            ],
        ]);

        $message = $this->makeMessage(action: 'yes');
        $flow = new InlineAskFlow($this->storage, $this->driver, $message);

        $flow->resume();

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame('42', $edited[0]['messageId']);
        $this->assertSame("Выбери\n\n(выбрано: Да)", $edited[0]['message']->text);
        $this->assertNull($edited[0]['message']->keyboard);
    }

    public function test_finalize_fallback_uses_action_when_label_missing(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => InlineAskFlow::class,
            'current_step' => 'pick',
            'data' => [
                '__ask_keyboard_ctx' => [
                    'message_id' => '42',
                    'original_text' => 'Q',
                    'parse_mode' => null,
                    'label_map' => ['yes' => 'Да'],
                ],
            ],
        ]);

        $flow = new InlineAskFlow($this->storage, $this->driver, $this->makeMessage(action: 'maybe'));
        $flow->resume();

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame("Q\n\n(выбрано: maybe)", $edited[0]['message']->text);
    }

    public function test_finalize_skips_edit_on_empty_action(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => InlineAskFlow::class,
            'current_step' => 'pick',
            'data' => [
                '__ask_keyboard_ctx' => [
                    'message_id' => '42',
                    'original_text' => 'Q',
                    'parse_mode' => null,
                    'label_map' => ['yes' => 'Да'],
                ],
            ],
        ]);

        $flow = new InlineAskFlow($this->storage, $this->driver, $this->makeMessage(text: 'blah'));
        $flow->resume();

        $this->assertCount(0, $this->driver->getEditedMessages());

        $state = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('__ask_keyboard_ctx', $state['data'] ?? []);
    }

    public function test_on_cancel_edits_message_with_cancelled_suffix(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => InlineAskFlow::class,
            'current_step' => 'pick',
            'data' => [
                '__ask_keyboard_ctx' => [
                    'message_id' => '42',
                    'original_text' => 'Выбери',
                    'parse_mode' => null,
                    'label_map' => ['yes' => 'Да'],
                ],
            ],
        ]);

        $flow = new InlineAskFlow($this->storage, $this->driver, $this->makeMessage(text: '/cancel'));
        $flow->onCancel();

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame("Выбери\n\n(отменено)", $edited[0]['message']->text);
    }

    public function test_next_step_triggers_finalize(): void
    {
        $message = $this->makeMessage();
        $flow = new TwoAskFlow($this->storage, $this->driver, $message);
        $flow->start();

        $this->driver->resetSentMessages();
        $reply = $this->makeMessage(action: 'yes');
        $flow = new TwoAskFlow($this->storage, $this->driver, $reply);
        $flow->resume();

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame("Первый\n\n(выбрано: Да)", $edited[0]['message']->text);

        $sent = $this->driver->getSentMessages();
        $this->assertCount(1, $sent);
        $this->assertSame('Второй', $sent[0]->text);

        $state = $this->storage->get('100', 'telegram');
        $this->assertSame('secondStep', $state['current_step']);
        $this->assertArrayHasKey('__ask_keyboard_ctx', $state['data']);
        $this->assertSame('Второй', $state['data']['__ask_keyboard_ctx']['original_text']);
    }

    public function test_edit_throws_does_not_break_flow(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => InlineAskFlow::class,
            'current_step' => 'pick',
            'data' => [
                '__ask_keyboard_ctx' => [
                    'message_id' => '42',
                    'original_text' => 'Q',
                    'parse_mode' => null,
                    'label_map' => ['yes' => 'Да'],
                ],
            ],
        ]);

        $throwingDriver = new class extends FakeDriver {
            public function edit(string $messageId, \Govorun\Messaging\OutgoingMessage $message): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $flow = new InlineAskFlow($this->storage, $throwingDriver, $this->makeMessage(action: 'yes'));

        $flow->resume();

        $state = $this->storage->get('100', 'telegram');
        $this->assertNull($state);
    }
}

/** Flow с одной ask_keyboard для тестов финализации. */
class InlineAskFlow extends Flow
{
    protected array $steps = ['pick'];

    public function pickStep(Step $step): void
    {
        $step->ask('Выбери', fn () => Keyboard::make()
            ->button('Да', 'yes')
            ->button('Нет', 'no')
        );
        $step->receive(function (IncomingMessage $message) {
            $this->completeFlow();
        });
    }
}

/** Flow с reply-клавиатурой для проверки отсутствия контекста. */
class ReplyAskFlow extends Flow
{
    protected array $steps = ['pick'];

    public function pickStep(Step $step): void
    {
        $step->ask('Контакт', fn () => Keyboard::reply()
            ->button('Телефон', requestContact: true)
        );
        $step->receive(fn (IncomingMessage $m) => $this->completeFlow());
    }
}

/** Flow с URL-only клавиатурой для проверки отсутствия контекста. */
class UrlOnlyAskFlow extends Flow
{
    protected array $steps = ['pick'];

    public function pickStep(Step $step): void
    {
        $step->ask('Открой', fn () => Keyboard::make()
            ->button('Сайт', url: 'https://example.com')
        );
        $step->receive(fn (IncomingMessage $m) => $this->completeFlow());
    }
}

/** Flow с двумя ask_keyboard-шагами для проверки финализации при nextStep. */
class TwoAskFlow extends Flow
{
    protected array $steps = ['firstStep', 'secondStep'];

    public function firstStepStep(Step $step): void
    {
        $step->ask('Первый', fn () => Keyboard::make()->button('Да', 'yes'));
        $step->receive(fn (IncomingMessage $m) => $this->nextStep('secondStep'));
    }

    public function secondStepStep(Step $step): void
    {
        $step->ask('Второй', fn () => Keyboard::make()->button('ОК', 'ok'));
        $step->receive(fn (IncomingMessage $m) => $this->completeFlow());
    }
}
