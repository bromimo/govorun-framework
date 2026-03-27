<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

/** Команда создания нового класса Flow (диалогового потока).
 * Генерирует файл Flow из шаблона (stub) в директории app/Flows.
 */
class MakeFlowCommand extends Command
{
    protected $signature = 'make:flow {name : The name of the flow}';
    protected $description = 'Create a new Flow class';

    /** Создать новый файл Flow из шаблона.
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $stub = file_get_contents(__DIR__ . '/stubs/flow.stub');
        $content = str_replace('DummyClass', $name, $stub);

        $dir = app()->basePath('app/Flows');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . "/{$name}.php";

        if (file_exists($path)) {
            $this->error("Flow {$name} already exists!");
            return self::FAILURE;
        }

        file_put_contents($path, $content);
        $this->info("Flow created: app/Flows/{$name}.php");

        return self::SUCCESS;
    }
}
