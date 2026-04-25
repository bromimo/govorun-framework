<?php

namespace Govorun\Tests\Unit\Routing;

use Govorun\Tests\TestCase;
use Govorun\Testing\FakeDriver;
use Govorun\Messaging\Button;
use Govorun\Messaging\Message;
use Govorun\Routing\Controller;
use Govorun\Messaging\Keyboard;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\State\FileStateStorage;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;

class ControllerKeyboardFinalizeTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;
    private FakeDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir().'/govorun_ckf_test_'.uniqid();
        mkdir($this->storagePath, 0777, true);
        $this->storage = new FileStateStorage($this->storagePath);
        $this->driver = new FakeDriver();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storagePath.'/*.json') as $file) {
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
            type: $action !== null ? ContentType::Action : ContentType::Text,
            action: $action,
        );
    }

    private function makeController(): Controller
    {
        $controller = new class extends Controller
        {
            public function sendInlineKeyboard(): void
            {
                $this->send(
                    Message::make('Выбери')
                        ->keyboard(Keyboard::make()->buttons([
                            [
                                Button::make('Да')->action('yes'),
                                Button::make('Нет')->action('no'),
                            ],
                        ])),
                );
            }

            public function sendReplyKeyboard(): void
            {
                $this->send(
                    Message::make('Контакт')
                        ->keyboard(Keyboard::reply()->buttons([
                            [Button::make('Телефон')->requestContact()],
                        ])),
                );
            }

            public function sendUrlOnly(): void
            {
                $this->send(
                    Message::make('Открой')
                        ->keyboard(Keyboard::make()->buttons([
                            [Button::make('Сайт')->url('https://example.com')],
                        ])),
                );
            }

            public function sendNoKeyboard(): void
            {
                $this->send(Message::make('Просто'));
            }

            public function handle(): void {}
        };
        $controller->setStateStorage($this->storage);

        return $controller;
    }

    public function test_send_inline_keyboard_saves_controller_kb_ctx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);
        $ctrl->sendInlineKeyboard();

        $record = $this->storage->get('100', 'telegram');
        $ctx = $record['controller_kb_ctx'] ?? null;

        $this->assertNotNull($ctx);
        $this->assertSame('1', $ctx['message_id']);
        $this->assertSame('Выбери', $ctx['original_text']);
        $this->assertNull($ctx['parse_mode']);
        $this->assertSame(['yes' => 'Да', 'no' => 'Нет'], $ctx['label_map']);
    }

    public function test_send_reply_keyboard_does_not_save_ctx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);
        $ctrl->sendReplyKeyboard();

        $record = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('controller_kb_ctx', $record ?? []);
    }

    public function test_send_url_only_keyboard_does_not_save_ctx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);
        $ctrl->sendUrlOnly();

        $record = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('controller_kb_ctx', $record ?? []);
    }

    public function test_send_no_keyboard_does_not_save_ctx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);
        $ctrl->sendNoKeyboard();

        $record = $this->storage->get('100', 'telegram');
        $this->assertNull($record);
    }

    public function test_send_with_null_message_id_skips_ctx_save(): void
    {
        $driver = new class extends FakeDriver
        {
            public function send(OutgoingMessage $message): ?string
            {
                parent::send($message);

                return null;
            }
        };

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $driver);
        $ctrl->sendInlineKeyboard();

        $record = $this->storage->get('100', 'telegram');
        $this->assertArrayNotHasKey('controller_kb_ctx', $record ?? []);
    }

    public function test_action_message_finalizes_keyboard_with_label(): void
    {
        $this->storage->set('100', 'telegram', [
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => 'Выбери',
                'parse_mode' => null,
                'label_map' => ['yes' => 'Да', 'no' => 'Нет'],
            ],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'yes'), $this->driver);

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame('42', $edited[0]['messageId']);
        $this->assertSame("Выбери\n\n(выбрано: Да)", $edited[0]['message']->text);
        $this->assertNull($edited[0]['message']->keyboard);

        $record = $this->storage->get('100', 'telegram');
        $this->assertNull($record);
    }

    public function test_finalize_falls_back_to_action_when_label_missing(): void
    {
        $this->storage->set('100', 'telegram', [
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => 'Q',
                'parse_mode' => null,
                'label_map' => ['yes' => 'Да'],
            ],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'unknown'), $this->driver);

        $edited = $this->driver->getEditedMessages();
        $this->assertCount(1, $edited);
        $this->assertSame("Q\n\n(выбрано: unknown)", $edited[0]['message']->text);
    }

    public function test_finalize_preserves_parse_mode(): void
    {
        $this->storage->set('100', 'telegram', [
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => '<b>Q</b>',
                'parse_mode' => 'HTML',
                'label_map' => ['yes' => 'Да'],
            ],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'yes'), $this->driver);

        $edited = $this->driver->getEditedMessages();
        $this->assertSame('HTML', $edited[0]['message']->parseMode);
    }

    public function test_no_ctx_in_storage_is_noop(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'yes'), $this->driver);

        $this->assertCount(0, $this->driver->getEditedMessages());
    }

    public function test_text_message_does_not_finalize(): void
    {
        $this->storage->set('100', 'telegram', [
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => 'Q',
                'parse_mode' => null,
                'label_map' => ['yes' => 'Да'],
            ],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'просто текст'), $this->driver);

        $this->assertCount(0, $this->driver->getEditedMessages());

        $record = $this->storage->get('100', 'telegram');
        $this->assertArrayHasKey('controller_kb_ctx', $record);
    }

    public function test_edit_failure_clears_ctx_and_does_not_throw(): void
    {
        $this->storage->set('100', 'telegram', [
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => 'Q',
                'parse_mode' => null,
                'label_map' => ['yes' => 'Да'],
            ],
        ]);

        $throwingDriver = new class extends FakeDriver
        {
            public function edit(string $messageId, OutgoingMessage $message): void
            {
                throw new \RuntimeException('boom');
            }
        };

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'yes'), $throwingDriver);

        $record = $this->storage->get('100', 'telegram');
        $this->assertNull($record);
    }

    public function test_capture_overwrites_previous_ctx(): void
    {
        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);

        $ctrl->sendInlineKeyboard();
        $first = $this->storage->get('100', 'telegram')['controller_kb_ctx'];

        $ctrl->sendInlineKeyboard();
        $second = $this->storage->get('100', 'telegram')['controller_kb_ctx'];

        $this->assertNotSame($first['message_id'], $second['message_id']);
    }

    public function test_capture_preserves_existing_flow_record(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => 'Some\\Flow',
            'current_step' => 'first',
            'data' => ['name' => 'Иван'],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $this->driver);
        $ctrl->sendInlineKeyboard();

        $record = $this->storage->get('100', 'telegram');
        $this->assertSame('Some\\Flow', $record['flow_class']);
        $this->assertSame('first', $record['current_step']);
        $this->assertSame(['name' => 'Иван'], $record['data']);
        $this->assertArrayHasKey('controller_kb_ctx', $record);
    }

    public function test_finalize_preserves_flow_record(): void
    {
        $this->storage->set('100', 'telegram', [
            'flow_class' => 'Some\\Flow',
            'current_step' => 'first',
            'data' => ['name' => 'Иван'],
            'controller_kb_ctx' => [
                'message_id' => '42',
                'original_text' => 'Q',
                'parse_mode' => null,
                'label_map' => ['yes' => 'Да'],
            ],
        ]);

        $ctrl = $this->makeController();
        $ctrl->setContext($this->makeMessage(action: 'yes'), $this->driver);

        $record = $this->storage->get('100', 'telegram');
        $this->assertSame('Some\\Flow', $record['flow_class']);
        $this->assertSame('first', $record['current_step']);
        $this->assertSame(['name' => 'Иван'], $record['data']);
        $this->assertArrayNotHasKey('controller_kb_ctx', $record);
    }

    public function test_no_storage_set_does_not_break_send(): void
    {
        $driver = $this->createMock(MessengerDriver::class);
        $driver->method('send')->willReturn('1');

        $ctrl = new class extends Controller
        {
            public function sendKb(): void
            {
                $this->send(
                    Message::make('Q')
                        ->keyboard(Keyboard::make()->buttons([
                            [Button::make('Да')->action('yes')],
                        ])),
                );
            }
        };
        $ctrl->setContext($this->makeMessage(text: 'cmd'), $driver);

        $ctrl->sendKb();

        $this->assertTrue(true);
    }

    public function test_no_storage_set_does_not_break_action(): void
    {
        $driver = $this->createMock(MessengerDriver::class);

        $ctrl = new class extends Controller
        {
            public function handle(): void {}
        };
        $ctrl->setContext($this->makeMessage(action: 'yes'), $driver);

        $this->assertTrue(true);
    }
}
