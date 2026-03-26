<?php

namespace Govorun\Testing;

use Govorun\Http\ApiClient;

trait InteractsWithApi
{
    protected function fakeApi(string $clientClass): FakeApiClient
    {
        $instance = new $clientClass();
        $fake = new FakeApiClient($instance);
        $this->app->instance($clientClass, $instance);

        return $fake;
    }
}
