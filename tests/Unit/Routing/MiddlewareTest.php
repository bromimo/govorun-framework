<?php

namespace Govorun\Tests\Unit\Routing;

use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Routing\Middleware;
use Govorun\Routing\MiddlewarePipeline;
use Govorun\Tests\TestCase;

class MiddlewareTest extends TestCase
{
    private function makeMessage(string $text = 'test'): IncomingMessage
    {
        return new IncomingMessage(
            id: '1', chatId: 'chat1', driverName: 'telegram',
            text: $text, user: new UserDto(id: '100'), type: ContentType::Text,
        );
    }

    public function test_pipeline_runs_handler_without_middleware(): void
    {
        $pipeline = new MiddlewarePipeline();
        $called = false;
        $pipeline->run($this->makeMessage(), [], function () use (&$called) {
            $called = true;
        });
        $this->assertTrue($called);
    }

    public function test_pipeline_runs_middleware_then_handler(): void
    {
        $pipeline = new MiddlewarePipeline();
        $order = [];

        $middleware = new class implements Middleware {
            public function handle(IncomingMessage $message, \Closure $next): void
            {
                $GLOBALS['test_order'][] = 'middleware';
                $next($message);
            }
        };

        $GLOBALS['test_order'] = &$order;
        $pipeline->run($this->makeMessage(), [get_class($middleware)], function () use (&$order) {
            $order[] = 'handler';
        }, [get_class($middleware) => $middleware]);

        $this->assertSame(['middleware', 'handler'], $order);
        unset($GLOBALS['test_order']);
    }

    public function test_middleware_can_stop_pipeline(): void
    {
        $pipeline = new MiddlewarePipeline();
        $handlerCalled = false;

        $middleware = new class implements Middleware {
            public function handle(IncomingMessage $message, \Closure $next): void
            {
                // Don't call $next — blocks pipeline
            }
        };

        $pipeline->run($this->makeMessage(), [get_class($middleware)], function () use (&$handlerCalled) {
            $handlerCalled = true;
        }, [get_class($middleware) => $middleware]);

        $this->assertFalse($handlerCalled);
    }

    public function test_multiple_middleware_run_in_order(): void
    {
        $pipeline = new MiddlewarePipeline();
        $order = [];

        $mw1 = new class implements Middleware {
            public function handle(IncomingMessage $message, \Closure $next): void
            {
                $GLOBALS['test_order'][] = 'first';
                $next($message);
            }
        };

        $mw2 = new class implements Middleware {
            public function handle(IncomingMessage $message, \Closure $next): void
            {
                $GLOBALS['test_order'][] = 'second';
                $next($message);
            }
        };

        $GLOBALS['test_order'] = &$order;
        $pipeline->run(
            $this->makeMessage(),
            [get_class($mw1), get_class($mw2)],
            function () use (&$order) { $order[] = 'handler'; },
            [get_class($mw1) => $mw1, get_class($mw2) => $mw2]
        );

        $this->assertSame(['first', 'second', 'handler'], $order);
        unset($GLOBALS['test_order']);
    }
}
