<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

/** Команда очистки состояний диалогов (Flow).
 * Поддерживает очистку для драйверов file, database и cache.
 */
class StateClearCommand extends Command
{
    protected $signature = 'state:clear';
    protected $description = 'Clear all Flow dialog states';

    /** Очистить все сохранённые состояния диалогов.
     * @return int
     */
    public function handle(): int
    {
        $driver = app('config')->get('state.driver', 'file');

        return match ($driver) {
            'file' => $this->clearFileStorage(),
            'database' => $this->clearDatabaseStorage(),
            'cache' => $this->clearCacheStorage(),
            default => $this->clearFileStorage(),
        };
    }

    /** Очистить файловое хранилище состояний.
     * @return int
     */
    private function clearFileStorage(): int
    {
        $path = app()->storagePath('state');

        if (! is_dir($path)) {
            $this->info('No state files found.');
            return self::SUCCESS;
        }

        $files = glob($path . '/*.json');
        $count = count($files);

        foreach ($files as $file) {
            unlink($file);
        }

        $this->info("Cleared {$count} state file(s).");
        return self::SUCCESS;
    }

    /** Очистить состояния из базы данных.
     * @return int
     */
    private function clearDatabaseStorage(): int
    {
        $connection = app('db');
        $count = $connection->table('govorun_states')->count();
        $connection->table('govorun_states')->truncate();

        $this->info("Cleared {$count} state record(s) from database.");
        return self::SUCCESS;
    }

    /** Вывести предупреждение о невозможности селективной очистки кеша.
     * @return int
     */
    private function clearCacheStorage(): int
    {
        $this->warn('Cache-based state storage cannot be selectively cleared.');
        $this->warn('Use your cache driver\'s flush mechanism if needed.');
        return self::SUCCESS;
    }
}
