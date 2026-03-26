<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Illuminate\Contracts\Cache\Repository as CacheContract;

class CacheStateStorage implements StateStorage
{
    public function __construct(
        private CacheContract $cache,
        private int $ttl = 3600,
    ) {}

    public function get(string $chatId, string $driver): ?array
    {
        return $this->cache->get($this->key($chatId, $driver));
    }

    public function set(string $chatId, string $driver, array $data): void
    {
        $this->cache->put($this->key($chatId, $driver), $data, $this->ttl);
    }

    public function delete(string $chatId, string $driver): void
    {
        $this->cache->forget($this->key($chatId, $driver));
    }

    private function key(string $chatId, string $driver): string
    {
        return "govorun_state:{$chatId}:{$driver}";
    }
}
