<?php

namespace Govorun\Routing;

use Govorun\Messaging\IncomingMessage;

class MiddlewarePipeline
{
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
