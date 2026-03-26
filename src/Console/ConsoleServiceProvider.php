<?php

namespace Govorun\Console;

use Govorun\Foundation\ServiceProvider;
use Illuminate\Console\Application as Artisan;

class ConsoleServiceProvider extends ServiceProvider
{
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
