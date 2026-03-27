<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Illuminate\Database\ConnectionInterface;

/** Хранилище состояния потоков на основе базы данных.
 * Сохраняет и извлекает состояние Flow через Illuminate Database,
 * используя таблицу govorun_states.
 */
class DatabaseStateStorage implements StateStorage
{
    /** Создать экземпляр хранилища на основе БД.
     * @param ConnectionInterface $connection Соединение с базой данных
     * @param string $table Имя таблицы для хранения состояний
     */
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'govorun_states',
    ) {}

    /** Получить состояние по идентификатору чата и драйверу.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return array|null
     * @throws \Illuminate\Database\QueryException
     */
    public function get(string $chatId, string $driver): ?array
    {
        $row = $this->connection->table($this->table)
            ->where('chat_id', $chatId)
            ->where('driver', $driver)
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'flow_class' => $row->flow_class,
            'current_step' => $row->current_step,
            'data' => json_decode($row->data, true) ?: [],
        ];
    }

    /** Сохранить состояние для чата и драйвера.
     * Выполняет вставку новой записи или обновление существующей.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @param array $data Данные состояния
     * @return void
     * @throws \Illuminate\Database\QueryException
     */
    public function set(string $chatId, string $driver, array $data): void
    {
        $row = [
            'flow_class' => $data['flow_class'] ?? '',
            'current_step' => $data['current_step'] ?? '',
            'data' => json_encode($data['data'] ?? [], JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $existing = $this->connection->table($this->table)
            ->where('chat_id', $chatId)
            ->where('driver', $driver)
            ->first();

        if ($existing) {
            $this->connection->table($this->table)
                ->where('chat_id', $chatId)
                ->where('driver', $driver)
                ->update($row);
        } else {
            $row['chat_id'] = $chatId;
            $row['driver'] = $driver;
            $row['created_at'] = date('Y-m-d H:i:s');
            $this->connection->table($this->table)->insert($row);
        }
    }

    /** Удалить состояние для чата и драйвера.
     * @param string $chatId Идентификатор чата
     * @param string $driver Имя драйвера мессенджера
     * @return void
     * @throws \Illuminate\Database\QueryException
     */
    public function delete(string $chatId, string $driver): void
    {
        $this->connection->table($this->table)
            ->where('chat_id', $chatId)
            ->where('driver', $driver)
            ->delete();
    }
}
