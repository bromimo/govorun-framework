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
