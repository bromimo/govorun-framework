<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    protected $signature = 'test {args?* : Arguments to pass to PHPUnit}';
    protected $description = 'Run the application tests';

    public function handle(): int
    {
        $phpunit = app()->basePath('vendor/bin/phpunit');

        if (! file_exists($phpunit)) {
            $this->error('PHPUnit not found. Run "composer require --dev phpunit/phpunit".');
            return self::FAILURE;
        }

        $args = $this->argument('args') ?: [];
        $command = escapeshellarg($phpunit) . ' ' . implode(' ', array_map('escapeshellarg', $args));

        passthru($command, $exitCode);

        return $exitCode;
    }
}
