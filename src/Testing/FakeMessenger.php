<?php

namespace Govorun\Testing;

use Govorun\Http\Request;
use PHPUnit\Framework\Assert;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Foundation\Application;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;

/** Fluent-объект для тестирования бота через цепочку receive/assert.
 * Позволяет эмулировать входящие сообщения и проверять ответы бота
 * без реальных HTTP-запросов к мессенджерам.
 */
class FakeMessenger
{
    /** @var int Позиция курсора в очереди отправленных сообщений */
    private int $cursor = 0;

    /** @var int Счётчик входящих сообщений */
    private int $messageCounter = 0;

    /** @var OutgoingMessage|null Последнее потреблённое сообщение */
    private ?OutgoingMessage $lastConsumed = null;

    /** Создать экземпляр FakeMessenger.
     * @param Application $app    Экземпляр приложения
     * @param FakeDriver  $driver Фейковый драйвер мессенджера
     * @param string      $chatId Идентификатор чата
     */
    public function __construct(
        private Application $app,
        private FakeDriver $driver,
        private string $chatId = 'fake-chat-1',
    ) {}

    /** Эмулирует получение текстового сообщения от пользователя.
     * Создаёт IncomingMessage, кладёт в очередь FakeDriver и прогоняет через handleWebhook.
     * @param string $text Текст сообщения
     * @return static
     */
    public function receive(string $text): static
    {
        $this->messageCounter++;

        $message = new IncomingMessage(
            id: (string) $this->messageCounter,
            chatId: $this->chatId,
            driverName: 'fake',
            text: $text,
            user: new UserDto(id: 'fake-user-1', firstName: 'Test'),
            type: ContentType::Text,
        );

        $this->driver->queueMessage($message);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/fake', 'REQUEST_METHOD' => 'POST'],
            content: '{}',
        );

        $this->app->handleWebhook($request);

        return $this;
    }

    /** Эмулирует нажатие кнопки из клавиатуры последнего сообщения.
     * Ищет кнопку по тексту, извлекает action/params и прогоняет через handleWebhook.
     * @param string $text Текст кнопки
     * @return static
     * @throws \RuntimeException Если кнопка не найдена
     */
    public function clickButton(string $text): static
    {
        $sent = $this->driver->getSentMessages();
        $lastMessage = end($sent);

        if ($lastMessage === false || $lastMessage->keyboard === null) {
            throw new \RuntimeException("Button '{$text}' not found in last message keyboard");
        }

        $button = $this->findButton($lastMessage->keyboard, $text);

        if ($button === null) {
            throw new \RuntimeException("Button '{$text}' not found in last message keyboard");
        }

        $this->messageCounter++;

        $message = new IncomingMessage(
            id: (string) $this->messageCounter,
            chatId: $this->chatId,
            driverName: 'fake',
            text: null,
            user: new UserDto(id: 'fake-user-1', firstName: 'Test'),
            type: ContentType::Action,
            action: $button['action'] ?? '',
            actionParams: $button['param'] ?? [],
        );

        $this->driver->queueMessage($message);

        $request = new Request(
            server: ['REQUEST_URI' => '/webhook/fake', 'REQUEST_METHOD' => 'POST'],
            content: '{}',
        );

        $this->app->handleWebhook($request);

        return $this;
    }

    /** Проверяет, что следующее сообщение точно совпадает с ожидаемым.
     * Потребляет сообщение из очереди.
     * @param string $expected Ожидаемый текст ответа
     * @return static
     */
    public function assertReply(string $expected): static
    {
        $sent = $this->driver->getSentMessages();

        Assert::assertArrayHasKey(
            $this->cursor,
            $sent,
            "Expected reply '{$expected}' but no messages were sent",
        );

        $actual = $sent[$this->cursor]->text;
        $this->lastConsumed = $sent[$this->cursor];
        $this->cursor++;

        Assert::assertSame($expected, $actual, "Expected reply '{$expected}' but got '{$actual}'");

        return $this;
    }

    /** Проверяет, что следующее сообщение содержит подстроку.
     * Потребляет сообщение из очереди.
     * @param string $needle Искомая подстрока
     * @return static
     */
    public function assertReplyContains(string $needle): static
    {
        $sent = $this->driver->getSentMessages();

        Assert::assertArrayHasKey(
            $this->cursor,
            $sent,
            "Expected reply containing '{$needle}' but no messages were sent",
        );

        $actual = $sent[$this->cursor]->text;
        $this->lastConsumed = $sent[$this->cursor];
        $this->cursor++;

        Assert::assertStringContainsString(
            $needle,
            $actual ?? '',
            "Expected reply containing '{$needle}' but got '{$actual}'",
        );

        return $this;
    }

    /** Проверяет, что больше нет непотреблённых сообщений.
     * @return static
     */
    public function assertNoReply(): static
    {
        $sent = $this->driver->getSentMessages();
        $remaining = array_slice($sent, $this->cursor);

        Assert::assertEmpty(
            $remaining,
            'Expected no more replies but found ' . count($remaining) . ' message(s)',
        );

        return $this;
    }

    /** Проверяет, что последнее потреблённое сообщение содержит клавиатуру с указанными кнопками.
     * Должен вызываться после assertReply() или assertReplyContains().
     * @param array<string> $expectedButtons Ожидаемые тексты кнопок
     * @return static
     */
    public function assertKeyboard(array $expectedButtons): static
    {
        Assert::assertNotNull(
            $this->lastConsumed,
            'assertKeyboard must be called after assertReply or assertReplyContains',
        );

        $keyboard = $this->lastConsumed->keyboard;

        Assert::assertNotNull($keyboard, 'Expected keyboard but message has none');

        $actualButtons = [];
        foreach ($keyboard['rows'] as $row) {
            foreach ($row as $btn) {
                $actualButtons[] = $btn['text'];
            }
        }

        Assert::assertSame(
            $expectedButtons,
            $actualButtons,
            'Expected buttons [' . implode(', ', $expectedButtons) . '] but got [' . implode(', ', $actualButtons) . ']',
        );

        return $this;
    }

    /** Ищет кнопку по тексту в структуре клавиатуры.
     * @param array<string, mixed> $keyboard Структура клавиатуры
     * @param string               $text     Текст кнопки
     * @return array<string, mixed>|null Данные кнопки или null
     */
    private function findButton(array $keyboard, string $text): ?array
    {
        foreach ($keyboard['rows'] as $row) {
            foreach ($row as $btn) {
                if ($btn['text'] === $text) {
                    return $btn;
                }
            }
        }

        return null;
    }
}
