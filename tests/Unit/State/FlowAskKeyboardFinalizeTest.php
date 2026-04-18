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
