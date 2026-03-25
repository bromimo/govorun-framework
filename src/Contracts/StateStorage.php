<?php

namespace Govorun\Contracts;

interface StateStorage
{
    public function get(string $chatId, string $driver): ?array;
    public function set(string $chatId, string $driver, array $data): void;
    public function delete(string $chatId, string $driver): void;
}
