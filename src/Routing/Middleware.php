<?php

namespace Govorun\Routing;

use Govorun\Messaging\IncomingMessage;

interface Middleware
{
    public function handle(IncomingMessage $message, \Closure $next): void;
}
