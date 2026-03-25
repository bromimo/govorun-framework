<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;

class FileStateStorage implements StateStorage
{
    public function __construct(
        private string $storagePath,
    ) {}

    public function get(string $chatId, string $driver): ?array
    {
        $file = $this->filePath($chatId, $driver);

        if (! file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    public function set(string $chatId, string $driver, array $data): void
    {
        if (! is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0777, true);
        }

        file_put_contents(
            $this->filePath($chatId, $driver),
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        );
    }

    public function delete(string $chatId, string $driver): void
    {
        $file = $this->filePath($chatId, $driver);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function filePath(string $chatId, string $driver): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . "{$chatId}_{$driver}.json";
    }
}
