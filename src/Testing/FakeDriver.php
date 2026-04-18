<?php

namespace Govorun\Testing;

use Govorun\Http\Request;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;

/** Фейковый драйвер мессенджера для тестирования.
 * Реализует MessengerDriver, копит отправленные сообщения и выдаёт
 * подготовленные входящие сообщения из внутренней очереди.
 */
class FakeDriver implements MessengerDriver
{
    /** @var IncomingMessage[] Очередь входящих сообщений */
    private array $pendingMessages = [];

    /** @var OutgoingMessage[] Накопленные отправленные сообщения */
    private array $sentMessages = [];

    /** @var int Счётчик синтетических message_id */
    private int $messageIdCounter = 0;

    /** @var array<int, array{messageId: string, message: OutgoingMessage}> Записанные edit-вызовы */
    private array $editedMessages = [];

    /** Добавляет сообщение в очередь входящих.
     * @param IncomingMessage $message Входящее сообщение
     * @return void
     */
    public function queueMessage(IncomingMessage $message): void
    {
        $this->pendingMessages[] = $message;
    }

    /** Верификация вебхука — всегда возвращает true.
     * @param Request $request HTTP-запрос
     * @return bool
     */
    public function verifyWebhook(Request $request): bool
    {
        return true;
    }

    /** Извлекает следующее сообщение из очереди.
     * @param Request $request HTTP-запрос
     * @return IncomingMessage
     * @throws \RuntimeException Если очередь пуста
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        if (empty($this->pendingMessages)) {
            throw new \RuntimeException('No messages in FakeDriver queue');
        }

        return array_shift($this->pendingMessages);
    }

    /** Накапливает отправленное сообщение и выдаёт синтетический message_id.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return ?string Синтетический message_id (последовательные строки "1", "2", ...)
     */
    public function send(OutgoingMessage $message): ?string
    {
        $this->sentMessages[] = $message;
        $this->messageIdCounter++;

        return (string) $this->messageIdCounter;
    }

    /** Записывает edit-вызов в журнал.
     * @param string          $messageId Идентификатор сообщения
     * @param OutgoingMessage $message   Исходящее сообщение
     * @return void
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        $this->editedMessages[] = ['messageId' => $messageId, 'message' => $message];
    }

    /** Удаление сообщения — заглушка.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId    Идентификатор чата
     * @return void
     */
    public function delete(string $messageId, string $chatId): void {}

    /** Установка вебхука — заглушка, всегда true.
     * @param string $url URL вебхука
     * @return bool
     */
    public function installWebhook(string $url): bool
    {
        return true;
    }

    /** Удаление вебхука — заглушка, всегда true.
     * @return bool
     */
    public function removeWebhook(): bool
    {
        return true;
    }

    /** Получение пользователя — возвращает заглушку UserDto.
     * @param string $id Идентификатор пользователя
     * @return UserDto
     */
    public function getUser(string $id): UserDto
    {
        return new UserDto(id: $id);
    }

    /** Возвращает массив всех отправленных сообщений.
     * @return OutgoingMessage[]
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /** Очищает очередь отправленных сообщений.
     * @return void
     */
    public function resetSentMessages(): void
    {
        $this->sentMessages = [];
    }

    /** Возвращает массив зафиксированных edit-вызовов.
     * @return array<int, array{messageId: string, message: OutgoingMessage}>
     */
    public function getEditedMessages(): array
    {
        return $this->editedMessages;
    }

    /** Сбрасывает журнал edit-вызовов.
     * @return void
     */
    public function resetEditedMessages(): void
    {
        $this->editedMessages = [];
    }
}
