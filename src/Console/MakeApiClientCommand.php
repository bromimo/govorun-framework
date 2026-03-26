<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class MakeApiClientCommand extends Command
{
    protected $signature = 'make:api-client {name : The name of the API client}';
    protected $description = 'Create a new API client class';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
