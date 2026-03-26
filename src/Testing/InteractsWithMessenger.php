<?php

namespace Govorun\Testing;

use Govorun\Contracts\MessengerDriver;

trait InteractsWithMessenger
{
    protected function fakeMessenger(string $chatId = 'fake-chat-1'): FakeMessenger
    {
        $driver = new FakeDriver();

        $this->app->instance(MessengerDriver::class, $driver);
        $this->app->instance('driver.fake', $driver);

        return new FakeMessenger($this->app, $driver, $chatId);
    }
}
