<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

/** Команда создания нового класса контроллера.
 * Генерирует файл контроллера из шаблона (stub) в директории app/Controllers.
 */
class MakeControllerCommand extends Command
{
    protected $signature = 'make:controller {name : The name of the controller}';
    protected $description = 'Create a new controller class';

    /** Создать новый файл контроллера из шаблона.
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $stub = file_get_contents(__DIR__ . '/stubs/controller.stub');
        $content = str_replace('DummyClass', $name, $stub);

        $dir = app()->basePath('app/Controllers');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = $dir . "/{$name}.php";

        if (file_exists($path)) {
            $this->error("Controller {$name} already exists!");
            return self::FAILURE;
        }

        file_put_contents($path, $content);
        $this->info("Controller created: app/Controllers/{$name}.php");

        return self::SUCCESS;
    }
}
