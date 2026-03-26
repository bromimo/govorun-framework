<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class StateClearCommand extends Command
{
    protected $signature = 'state:clear';
    protected $description = 'Clear all Flow dialog states';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
