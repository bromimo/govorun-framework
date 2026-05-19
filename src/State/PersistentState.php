<?php

namespace Govorun\State;

use Govorun\Contracts\StateAccessor;
use Govorun\Contracts\StateStorage;

/** Контейнер состояния с немедленной записью в хранилище при каждом set(). */
class PersistentState implements StateAccessor
{
    private array $data;

    /** @param StateStorage $storage Хранилище состояния
     * @param string $chatId ID чата
     * @param string $driverName Имя драйвера
     */
    public function __construct(
        private StateStorage $storage,
        private string $chatId,
        private string $driverName,
    ) {
        $this->data = $this->storage->get($chatId, $driverName) ?? [];
    }

    /** Получить значение по ключу.
     * @param string $key Ключ
     * @param mixed $default Значение по умолчанию
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /** Установить значение и немедленно записать в хранилище.
     * @param string $key Ключ
     * @param mixed $value Значение
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->storage->set($this->chatId, $this->driverName, $this->data);
    }

    /** Проверить наличие ключа.
     * @param string $key Ключ
     * @return bool
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /** Получить все данные.
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}