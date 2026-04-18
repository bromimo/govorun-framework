<?php

namespace Govorun\Contracts;

use Govorun\Http\Request;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;

/** Контракт драйвера мессенджера.
 * Определяет единый интерфейс для взаимодействия с различными
 * мессенджер-платформами (Telegram, Viber, WhatsApp и др.).
 */
interface MessengerDriver
{
    /** Проверить подлинность входящего вебхук-запроса.
     * @param Request $request Входящий HTTP-запрос
     * @return bool Результат верификации
     */
    public function verifyWebhook(Request $request): bool;

    /** Разобрать входящий вебхук-запрос в объект сообщения.
     * @param Request $request Входящий HTTP-запрос
     * @return IncomingMessage Разобранное входящее сообщение
     * @throws \Throwable
     */
    public function parseUpdate(Request $request): IncomingMessage;

    /** Отправить исходящее сообщение пользователю.
     * @param OutgoingMessage $message Исходящее сообщение для отправки
     * @return ?string Идентификатор отправленного сообщения или null
     * @throws \Throwable
     */
    public function send(OutgoingMessage $message): ?string;

    /** Редактировать ранее отправленное сообщение.
     * @param string $messageId Идентификатор сообщения для редактирования
     * @param OutgoingMessage $message Новое содержимое сообщения
     * @return void
     * @throws \Throwable
     */
    public function edit(string $messageId, OutgoingMessage $message): void;

    /** Удалить сообщение из чата.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     * @throws \Throwable
     */
    public function delete(string $messageId, string $chatId): void;

    /** Установить вебхук для получения обновлений.
     * @param string $url URL-адрес для установки вебхука
     * @return bool Успешность установки
     * @throws \Throwable
     */
    public function installWebhook(string $url): bool;

    /** Удалить установленный вебхук.
     * @return bool Успешность удаления
     * @throws \Throwable
     */
    public function removeWebhook(): bool;

    /** Получить информацию о пользователе по идентификатору.
     * @param string $id Идентификатор пользователя
     * @return UserDto Данные пользователя
     * @throws \Throwable
     */
    public function getUser(string $id): UserDto;
}
