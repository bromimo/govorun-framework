<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;

/** Файловое хранилище состояния потоков.
 * Сохраняет состояние каждого чата в отдельный JSON-файл
 * в указанной директории.
 */
class FileStateStorage implements StateStorage
{
    /** Создать экземпляр файлового хранилища.
     * @param string $storagePath Путь к директории хранения файлов состояния
     */
    public function __construct(
        private string $storagePath,
    ) {}

    /** Получить состояние по идентификатору чата и драйверу.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return array|null
     */
    public function get(string $chatId, string $driver): ?array
    {
        $file = $this->filePath($chatId, $driver);

        if (! file_exists($file)) {
            return null;
        }

        $data = json_decode(file_get_contents($file), true);

        return is_array($data) ? $data : null;
    }

    /** Сохранить состояние для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @param array $data Данные состояния
     * @return void
     */
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

    /** Удалить состояние для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return void
     */
    public function delete(string $chatId, string $driver): void
    {
        $file = $this->filePath($chatId, $driver);

        if (file_exists($file)) {
            unlink($file);
        }
    }

    /** Сформировать путь к файлу состояния.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return string
     */
    private function filePath(string $chatId, string $driver): string
    {
        return $this->storagePath . DIRECTORY_SEPARATOR . "{$chatId}_{$driver}.json";
    }
}
