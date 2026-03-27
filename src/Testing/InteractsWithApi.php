<?php

namespace Govorun\Testing;

use Govorun\Http\ApiClient;

/** Трейт для подмены ApiClient на FakeApiClient в тестах.
 * Требует свойство $this->app типа Application на объекте теста.
 */
trait InteractsWithApi
{
    /** Создаёт экземпляр ApiClient, оборачивает в FakeApiClient и регистрирует в контейнере.
     * @param string $clientClass FQCN класса ApiClient
     * @return FakeApiClient
     */
    protected function fakeApi(string $clientClass): FakeApiClient
    {
        $instance = new $clientClass();
        $fake = new FakeApiClient($instance);
        $this->app->instance($clientClass, $instance);

        return $fake;
    }
}
