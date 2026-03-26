<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class MigrateCommand extends Command
{
    protected $signature = 'migrate';
    protected $description = 'Run framework database migrations';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
