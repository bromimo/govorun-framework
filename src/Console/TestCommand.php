<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test {-- : Additional arguments passed to PHPUnit}';
    protected $description = 'Run the application tests';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
