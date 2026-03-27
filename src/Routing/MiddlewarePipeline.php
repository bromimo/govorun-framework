<?php

namespace Govorun\Routing;

use Govorun\Messaging\IncomingMessage;

/** Конвейер middleware для последовательной обработки сообщений.
 * Строит цепочку из middleware-классов и пропускает через неё
 * входящее сообщение перед вызовом финального обработчика.
 */
class MiddlewarePipeline
{
    /** Выполнить цепочку middleware и вызвать финальный обработчик.
     * @param IncomingMessage $message Входящее сообщение
     * @param array $middlewareClasses Массив имён классов middleware
     * @param \Closure $handler Финальный обработчик
     * @param array $resolved Массив предварительно созданных экземпляров middleware
     * @return void
     */
    public function run(IncomingMessage $message, array $middlewareClasses, \Closure $handler, array $resolved = []): void
    {
        $pipeline = array_reduce(
            array_reverse($middlewareClasses),
            function (\Closure $next, string $class) use ($resolved) {
                return function (IncomingMessage $message) use ($class, $next, $resolved) {
                    $middleware = $resolved[$class] ?? new $class();
                    $middleware->handle($message, $next);
                };
            },
            $handler
        );

        $pipeline($message);
    }
}
