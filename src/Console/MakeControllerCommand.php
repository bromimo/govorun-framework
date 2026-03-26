<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class MakeControllerCommand extends Command
{
    protected $signature = 'make:controller {name : The name of the controller}';
    protected $description = 'Create a new controller class';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
