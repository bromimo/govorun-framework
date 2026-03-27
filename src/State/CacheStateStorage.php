<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Illuminate\Contracts\Cache\Repository as CacheContract;

/** Хранилище состояния потоков на основе кэша.
 * Использует Illuminate Cache для хранения состояния Flow
 * с поддержкой TTL (времени жизни записи).
 */
class CacheStateStorage implements StateStorage
{
    /** Создать экземпляр кэш-хранилища.
     * @param CacheContract $cache Экземпляр кэш-репозитория
     * @param int $ttl Время жизни записи в секундах
     */
    public function __construct(
        private CacheContract $cache,
        private int $ttl = 3600,
    ) {}

    /** Получить состояние по идентификатору чата и драйверу.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return array|null
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function get(string $chatId, string $driver): ?array
    {
        return $this->cache->get($this->key($chatId, $driver));
    }

    /** Сохранить состояние для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @param array $data Данные состояния
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function set(string $chatId, string $driver, array $data): void
    {
        $this->cache->put($this->key($chatId, $driver), $data, $this->ttl);
    }

    /** Удалить состояние для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return void
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    public function delete(string $chatId, string $driver): void
    {
        $this->cache->forget($this->key($chatId, $driver));
    }

    /** Сформировать ключ кэша для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return string
     */
    private function key(string $chatId, string $driver): string
    {
        return "govorun_state:{$chatId}:{$driver}";
    }
}
