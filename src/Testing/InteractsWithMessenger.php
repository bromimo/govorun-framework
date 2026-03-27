<?php

namespace Govorun\Testing;

use Govorun\Contracts\MessengerDriver;

/** Трейт для подмены мессенджер-драйвера на FakeDriver в тестах.
 * Требует свойство $this->app типа Application на объекте теста.
 */
trait InteractsWithMessenger
{
    /** Создаёт FakeDriver, регистрирует его в контейнере и возвращает FakeMessenger.
     * @param string $chatId Идентификатор чата (по умолчанию 'fake-chat-1')
     * @return FakeMessenger
     */
    protected function fakeMessenger(string $chatId = 'fake-chat-1'): FakeMessenger
    {
        $driver = new FakeDriver();

        $this->app->instance(MessengerDriver::class, $driver);
        $this->app->instance('driver.fake', $driver);

        return new FakeMessenger($this->app, $driver, $chatId);
    }
}
