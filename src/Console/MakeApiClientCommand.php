<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class MakeApiClientCommand extends Command
{
    protected $signature = 'make:api-client {name : The name of the API client}';
    protected $description = 'Create a new API client class';

    public function handle(): int
    {
        $name = $this->argument('name');
        $stub = file_get_contents(__DIR__ . '/stubs/api-client.stub');
        $content = str_replace('DummyClass', $name, $stub);

        $dir = app()->basePath('app/Services');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . "/{$name}.php";

        if (file_exists($path)) {
            $this->error("API client {$name} already exists!");
            return self::FAILURE;
        }

        file_put_contents($path, $content);
        $this->info("API client created: app/Services/{$name}.php");

        return self::SUCCESS;
    }
}
