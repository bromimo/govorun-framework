<?php

namespace Govorun\Routing;

use Govorun\Messaging\IncomingMessage;

/** Интерфейс middleware для обработки входящих сообщений.
 * Каждый middleware получает сообщение и замыкание для передачи
 * управления следующему обработчику в цепочке.
 */
interface Middleware
{
    /** Обработать входящее сообщение и передать управление дальше.
     * @param IncomingMessage $message Входящее сообщение
     * @param \Closure $next Следующий обработчик в цепочке
     * @return void
     */
    public function handle(IncomingMessage $message, \Closure $next): void;
}
