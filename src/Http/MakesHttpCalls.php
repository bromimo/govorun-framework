<?php

namespace Govorun\Http;

/** Трейт для выполнения HTTP-запросов к внешним API через BotConnection-подключения. */
trait MakesHttpCalls
{
    /** Получить фабрику HTTP-клиентов.
     * @return HttpManager
     */
    protected function http(): HttpManager
    {
        return new HttpManager(config('connections', []));
    }
}