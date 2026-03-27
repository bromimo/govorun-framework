<?php

namespace Govorun\Events;

use Illuminate\Events\Dispatcher;
use Govorun\Foundation\ServiceProvider;

/** Сервис-провайдер событий.
 * Регистрирует диспетчер событий в контейнере и привязывает
 * слушатели из конфигурации при загрузке приложения.
 */
class EventServiceProvider extends ServiceProvider
{
    /** Зарегистрировать диспетчер событий в контейнере.
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('events', function () {
            return new Dispatcher($this->app);
        });
    }

    /** Привязать слушатели событий из конфигурации.
     * @return void
     */
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
