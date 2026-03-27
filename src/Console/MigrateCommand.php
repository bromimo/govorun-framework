<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

/** Команда запуска миграций базы данных.
 * Сканирует директорию миграций фреймворка и выполняет метод up() каждой миграции.
 */
class MigrateCommand extends Command
{
    protected $signature = 'migrate';
    protected $description = 'Run framework database migrations';

    /** Выполнить миграции базы данных.
     * @return int
     */
    public function handle(): int
    {
        $connection = app('db');
        $schema = $connection->getSchemaBuilder();

        $migrationsPath = dirname(__DIR__) . '/Database/Migrations';

        if (! is_dir($migrationsPath)) {
            $this->warn('No migrations directory found.');
            return self::SUCCESS;
        }

        $files = glob($migrationsPath . '/*.php');

        if (empty($files)) {
            $this->info('No migrations to run.');
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $className = $this->resolveClassName($file);

            if ($className === null || ! class_exists($className)) {
                continue;
            }

            $migration = new $className();
            $migration->up($schema);
            $this->info('Migrated: ' . basename($file, '.php'));
        }

        return self::SUCCESS;
    }

    /** Определить полное имя класса миграции по пути к файлу.
     * @param string $file Путь к файлу миграции
     * @return string|null
     */
    private function resolveClassName(string $file): ?string
    {
        $basename = basename($file, '.php');
        return 'Govorun\\Database\\Migrations\\' . $basename;
    }
}
