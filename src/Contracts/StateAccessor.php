<?php

namespace Govorun\Contracts;

/** Контракт для доступа к состоянию бота. */
interface StateAccessor
{
    /** Получить значение по ключу.
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed;

    /** Установить значение по ключу.
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /** Проверить наличие ключа.
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool;

    /** Получить все данные.
     * @return array<string, mixed>
     */
    public function all(): array;
}
