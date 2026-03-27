<?php

namespace Govorun\State;

use Govorun\Contracts\StateStorage;
use Govorun\Foundation\ServiceProvider;

/** Сервис-провайдер управления состоянием.
 * Регистрирует реализацию StateStorage в контейнере
 * на основе конфигурации (file, database или cache).
 */
class StateServiceProvider extends ServiceProvider
{
    /** Зарегистрировать привязки в контейнере.
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton(StateStorage::class, function () {
            $driver = $this->app->make('config')->get('state.driver', 'file');

            return match ($driver) {
                'database' => new DatabaseStateStorage($this->app->make('db')),
                'cache' => new CacheStateStorage(
                    $this->app->make('cache'),
                    $this->app->make('config')->get('state.ttl', 3600),
                ),
                default => new FileStateStorage($this->app->storagePath('state')),
            };
        });
    }
}
