<?php

namespace Govorun\Routing;

use Govorun\State\Flow;
use Govorun\Messaging\Message;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\ContentType;
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
    protected IncomingMessage $message;
    protected IncomingMessage $incomingMessage;
    protected MessengerDriver $driver;
    protected ?StateStorage $stateStorage = null;

    /** Установить контекст выполнения контроллера.
     * Выставляет оба свойства — $message (рекомендуемое, симметрично с Flow)
     * и $incomingMessage (deprecated-алиас для обратной совместимости).
     * При входящем Action-сообщении автоматически финализирует ранее отправленную
     * inline-клавиатуру (редактирует исходное сообщение и убирает клавиатуру).
     * @param IncomingMessage $message Входящее сообщение
     * @param MessengerDriver $driver Драйвер мессенджера
     * @return void
     */
    public function setContext(IncomingMessage $message, MessengerDriver $driver): void
    {
        $this->message = $message;
        $this->incomingMessage = $message;
        $this->driver = $driver;

        if ($message->type === ContentType::Action) {
            $this->finalizeKeyboardContext($message->action);
        }
    }

    /** Получить входящее сообщение.
     * @deprecated используйте свойство $this->message напрямую
     * @return IncomingMessage
     */
    protected function message(): IncomingMessage
    {
        return $this->message;
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
     * При наличии inline-клавиатуры с action-кнопками сохраняет контекст
     * для последующего автоматического удаления при нажатии кнопки.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return void
     */
    protected function send(OutgoingMessage $message): void
    {
        $message->chatId = $this->incomingMessage->chatId;
        $sentId = $this->driver->send($message);
        $this->captureKeyboardContext($sentId, $message);
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

    /** Получить хранилище состояний без выбрасывания исключений.
     * Возвращает null, если хранилище недоступно (например, в тестах
     * без bootstrap'а Application и без ручной установки через setStateStorage).
     * @return StateStorage|null
     */
    private function tryStateStorage(): ?StateStorage
    {
        if ($this->stateStorage !== null) {
            return $this->stateStorage;
        }

        try {
            return app(\Govorun\Contracts\StateStorage::class);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Сохранить контекст inline-клавиатуры для последующего finalize.
     * Пишет только при не-null message_id, inline-режиме и наличии хотя бы
     * одной action-кнопки. Для reply/url/remove-клавиатур — no-op.
     * @param ?string $messageId Идентификатор отправленного сообщения
     * @param OutgoingMessage $message Отправленное сообщение
     * @return void
     */
    private function captureKeyboardContext(?string $messageId, OutgoingMessage $message): void
    {
        if ($messageId === null || $message->keyboard === null) {
            return;
        }

        $kb = $message->keyboard;

        if ($kb['type'] !== 'inline' || $kb['remove']) {
            return;
        }

        $labelMap = [];

        foreach ($kb['rows'] as $row) {
            foreach ($row as $btn) {
                if (isset($btn['action'])) {
                    $labelMap[$btn['action']] = $btn['text'];
                }
            }
        }

        if (empty($labelMap)) {
            return;
        }

        $storage = $this->tryStateStorage();

        if ($storage === null) {
            return;
        }

        try {
            $existing = $storage->get($this->incomingMessage->chatId, $this->incomingMessage->driverName) ?? [];
            $existing['controller_kb_ctx'] = [
                'message_id' => $messageId,
                'original_text' => $message->text ?? '',
                'parse_mode' => $message->parseMode,
                'label_map' => $labelMap,
            ];
            $storage->set($this->incomingMessage->chatId, $this->incomingMessage->driverName, $existing);
        } catch (\Throwable) {
            // Ошибка хранилища не должна ронять отправку сообщения.
        }
    }

    /** Финализировать активную inline-клавиатуру: отредактировать исходное
     * сообщение (убрать клавиатуру, дописать «(выбрано: X)») и очистить контекст.
     * @param ?string $action Action нажатой кнопки
     * @return void
     */
    private function finalizeKeyboardContext(?string $action): void
    {
        $storage = $this->tryStateStorage();

        if ($storage === null) {
            return;
        }

        try {
            $record = $storage->get($this->incomingMessage->chatId, $this->incomingMessage->driverName);
        } catch (\Throwable) {
            return;
        }

        if ($record === null || ! isset($record['controller_kb_ctx'])) {
            return;
        }

        $ctx = $record['controller_kb_ctx'];

        if ($action === null || $action === '') {
            $this->clearKeyboardContext($storage);

            return;
        }

        $label = $ctx['label_map'][$action] ?? $action;
        $msg = Message::make($ctx['original_text']."\n\n(выбрано: {$label})");
        $msg->chatId = $this->incomingMessage->chatId;

        if ($ctx['parse_mode'] !== null) {
            $msg->parseMode($ctx['parse_mode']);
        }

        try {
            $this->driver->edit($ctx['message_id'], $msg);
        } catch (\Throwable) {
            // Мёртвый edit не должен ронять обработку action-сообщения.
        }

        $this->clearKeyboardContext($storage);
    }

    /** Удалить controller_kb_ctx из стораджа, сохранив остальные поля записи.
     * Если после удаления запись становится пустой — удалить запись целиком.
     * @param StateStorage $storage Хранилище состояний
     * @return void
     */
    private function clearKeyboardContext(StateStorage $storage): void
    {
        try {
            $record = $storage->get($this->incomingMessage->chatId, $this->incomingMessage->driverName);

            if ($record === null) {
                return;
            }

            unset($record['controller_kb_ctx']);

            if (empty($record)) {
                $storage->delete($this->incomingMessage->chatId, $this->incomingMessage->driverName);

                return;
            }

            $storage->set($this->incomingMessage->chatId, $this->incomingMessage->driverName, $record);
        } catch (\Throwable) {
            // Ошибка хранилища не должна ломать пользовательский флоу.
        }
    }
}
