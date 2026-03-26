<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class MakeFlowCommand extends Command
{
    protected $signature = 'make:flow {name : The name of the flow}';
    protected $description = 'Create a new Flow class';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
