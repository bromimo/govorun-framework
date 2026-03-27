<?php

namespace Govorun\Console;

use Govorun\Foundation\ServiceProvider;
use Illuminate\Console\Application as Artisan;

/** Провайдер консольных команд фреймворка.
 * Регистрирует Artisan-приложение и загружает все встроенные команды.
 */
class ConsoleServiceProvider extends ServiceProvider
{
    /** Регистрация Artisan-приложения в контейнере.
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('artisan', function () {
            $artisan = new Artisan(
                $this->app,
                $this->app->make('events'),
                'Govorun',
            );
            $artisan->setAutoExit(false);

            return $artisan;
        });
    }

    /** Загрузка и регистрация всех встроенных консольных команд.
     * @return void
     */
    public function boot(): void
    {
        $artisan = $this->app->make('artisan');

        $artisan->addCommands([
            new WebhookInstallCommand(),
            new WebhookRemoveCommand(),
            new MigrateCommand(),
            new MakeControllerCommand(),
            new MakeFlowCommand(),
            new MakeApiClientCommand(),
            new StateClearCommand(),
            new TestCommand(),
        ]);
    }
}
