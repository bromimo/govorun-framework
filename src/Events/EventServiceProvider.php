<?php

namespace Govorun\Events;

use Govorun\Foundation\ServiceProvider;
use Illuminate\Events\Dispatcher;

class EventServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('events', function () {
            return new Dispatcher($this->app);
        });
    }

    public function boot(): void
    {
        $events = $this->app->make('events');
        $listeners = $this->app->make('config')->get('events') ?? [];

        foreach ($listeners as $event => $handlers) {
            foreach ($handlers as $handler) {
                $events->listen($event, $handler);
            }
        }
    }
}
