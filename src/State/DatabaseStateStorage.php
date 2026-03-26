<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Illuminate\Database\ConnectionInterface;

class DatabaseStateStorage implements StateStorage
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'govorun_states',
    ) {}

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

    public function delete(string $chatId, string $driver): void
    {
        $this->connection->table($this->table)
            ->where('chat_id', $chatId)
            ->where('driver', $driver)
            ->delete();
    }
}
