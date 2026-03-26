<?php

namespace Govorun\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

class MigrateCommand extends Command
{
    protected $signature = 'migrate';
    protected $description = 'Run framework database migrations';

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

    private function resolveClassName(string $file): ?string
    {
        $basename = basename($file, '.php');
        return 'Govorun\\Database\\Migrations\\' . $basename;
    }
}
