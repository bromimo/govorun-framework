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

    /** Накапливает отправленное сообщение.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return ?string Всегда null (фейковый драйвер)
     */
    public function send(OutgoingMessage $message): ?string
    {
        $this->sentMessages[] = $message;
        return null;
    }

    /** Редактирование сообщения — заглушка.
     * @param string          $messageId Идентификатор сообщения
     * @param OutgoingMessage $message   Исходящее сообщение
     * @return void
     */
    public function edit(string $messageId, OutgoingMessage $message): void {}

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
}
