<?php

namespace Govorun\Routing;

use Govorun\State\Flow;
use Govorun\Messaging\Message;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Contracts\StateStorage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;

/** Базовый контроллер бота.
 * Предоставляет доступ к входящему сообщению, драйверу мессенджера,
 * а также вспомогательные методы для отправки ответов и запуска flow.
 */
abstract class Controller
{
    protected IncomingMessage $incomingMessage;
    protected MessengerDriver $driver;
    protected ?StateStorage $stateStorage = null;

    /** Установить контекст выполнения контроллера.
     * @param IncomingMessage $message Входящее сообщение
     * @param MessengerDriver $driver Драйвер мессенджера
     * @return void
     */
    public function setContext(IncomingMessage $message, MessengerDriver $driver): void
    {
        $this->incomingMessage = $message;
        $this->driver = $driver;
    }

    /** Получить входящее сообщение.
     * @return IncomingMessage
     */
    protected function message(): IncomingMessage
    {
        return $this->incomingMessage;
    }

    /** Получить данные пользователя из входящего сообщения.
     * @return UserDto
     */
    protected function user(): UserDto
    {
        return $this->incomingMessage->user;
    }

    /** Отправить текстовый ответ в чат.
     * @param string $text Текст ответа
     * @return void
     */
    protected function reply(string $text): void
    {
        $msg = Message::make($text);
        $msg->chatId = $this->incomingMessage->chatId;
        $this->driver->send($msg);
    }

    /** Отправить исходящее сообщение в чат.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return void
     */
    protected function send(OutgoingMessage $message): void
    {
        $message->chatId = $this->incomingMessage->chatId;
        $this->driver->send($message);
    }

    /** Редактировать ранее отправленное сообщение (заглушка).
     * @param OutgoingMessage $message Исходящее сообщение с изменениями
     * @return void
     */
    protected function edit(OutgoingMessage $message): void
    {
        $message->chatId = $this->incomingMessage->chatId;
        // Requires lastMessageId tracking — stub for Phase 5
    }

    /** Удалить последнее отправленное сообщение (заглушка).
     * @return void
     */
    protected function deleteLastMessage(): void
    {
        // Requires lastMessageId tracking — stub for Phase 5
    }

    /** Получить параметр callback-действия по ключу.
     * @param string $key Ключ параметра
     * @return string|null
     */
    protected function param(string $key): ?string
    {
        return $this->incomingMessage->actionParams[$key] ?? null;
    }

    /** Установить хранилище состояний.
     * @param StateStorage $storage Реализация хранилища состояний
     * @return void
     */
    public function setStateStorage(StateStorage $storage): void
    {
        $this->stateStorage = $storage;
    }

    /** Запустить flow (цепочку шагов) по имени класса.
     * @param string $flowClass Полное имя класса Flow
     * @return void
     */
    protected function startFlow(string $flowClass): void
    {
        $storage = $this->resolveStateStorage();

        /** @var Flow $flow */
        $flow = new $flowClass($storage, $this->driver, $this->incomingMessage);
        $flow->start();
    }

    /** Получить экземпляр хранилища состояний (из свойства или контейнера).
     * @return StateStorage
     */
    protected function resolveStateStorage(): StateStorage
    {
        if ($this->stateStorage !== null) {
            return $this->stateStorage;
        }

        return app(\Govorun\Contracts\StateStorage::class);
    }
}
