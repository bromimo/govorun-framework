<?php

namespace Govorun\Contracts;

/** Контракт хранилища состояний.
 * Определяет интерфейс для сохранения, получения и удаления
 * состояний пользовательских сессий (потоков) в различных хранилищах.
 */
interface StateStorage
{
    /** Получить данные состояния для указанного чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return array<string, mixed>|null Данные состояния или null, если не найдены
     * @throws \Throwable
     */
    public function get(string $chatId, string $driver): ?array;

    /** Сохранить данные состояния для указанного чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @param array<string, mixed> $data Данные состояния для сохранения
     * @return void
     * @throws \Throwable
     */
    public function set(string $chatId, string $driver, array $data): void;

    /** Удалить данные состояния для указанного чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return void
     * @throws \Throwable
     */
    public function delete(string $chatId, string $driver): void;
}
