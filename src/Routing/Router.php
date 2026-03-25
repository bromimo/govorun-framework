<?php

namespace Govorun\Routing;

use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\IncomingMessage;

class Router
{
    private MiddlewarePipeline $pipeline;

    public function __construct(
        private MessengerDriver $driver,
    ) {
        $this->pipeline = new MiddlewarePipeline();
    }

    public function dispatch(IncomingMessage $message): void
    {
        $routes = Route::getRoutes();
        $matched = $this->matchRoute($message, $routes);

        if ($matched === null) {
            return;
        }

        [$entry, $middleware] = $matched;

        $this->pipeline->run($message, $middleware, function (IncomingMessage $msg) use ($entry) {
            $this->callAction($entry->action, $msg);
        });
    }

    /**
     * @return array{RouteEntry, string[]}|null
     */
    private function matchRoute(IncomingMessage $message, array $routes): ?array
    {
        $priorityOrder = ['event', 'command', 'action', 'referral', 'media', 'location', 'contact', 'pattern', 'phrase', 'fallback'];

        foreach ($priorityOrder as $type) {
            foreach ($routes as $entry) {
                if ($entry->type !== $type) {
                    continue;
                }
                $result = $this->matchEntry($message, $entry);
                if ($result !== null) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * @return array{RouteEntry, string[]}|null
     */
    private function matchEntry(IncomingMessage $message, RouteEntry $entry): ?array
    {
        // Handle phrase with children first (nested routes)
        if ($entry->type === 'phrase' && !empty($entry->children) && $this->phraseTextMatches($message, $entry)) {
            $childResult = $this->matchRoute($message, $entry->children);
            if ($childResult !== null) {
                [$childEntry, $childMiddleware] = $childResult;
                return [$childEntry, array_merge($entry->middleware, $childMiddleware)];
            }
            if ($entry->action !== null) {
                return [$entry, $entry->middleware];
            }
            return null;
        }

        $matches = match ($entry->type) {
            'event'    => $message->type === ContentType::Event && $message->event === $entry->value,
            'command'  => $this->matchCommand($message, $entry),
            'action'   => $message->type === ContentType::Action && $message->action === $entry->value,
            'referral' => $message->referral !== null && $message->referral === $entry->value,
            'media'    => $message->type === ContentType::Media && $message->media?->type === $entry->value,
            'location' => $message->type === ContentType::Location && $message->location !== null,
            'contact'  => $message->type === ContentType::Contact && $message->contact !== null,
            'pattern'  => $message->text !== null && preg_match($entry->value, $message->text) === 1,
            'phrase'   => $this->phraseTextMatches($message, $entry),
            'fallback' => true,
            default    => false,
        };

        if ($matches && $entry->action !== null) {
            return [$entry, $entry->middleware];
        }

        return null;
    }

    private function matchCommand(IncomingMessage $message, RouteEntry $entry): bool
    {
        if ($message->text === null) {
            return false;
        }
        $text = ltrim($message->text, '/');
        $command = $entry->value;
        return $text === $command || str_starts_with($text, $command . ' ');
    }

    private function phraseTextMatches(IncomingMessage $message, RouteEntry $entry): bool
    {
        if ($message->text === null) {
            return false;
        }
        $text = mb_strtolower($message->text);
        if (str_contains($text, mb_strtolower($entry->value))) {
            return true;
        }
        foreach ($entry->aliases as $alias) {
            if (str_contains($text, mb_strtolower($alias))) {
                return true;
            }
        }
        return false;
    }

    private function callAction(mixed $action, IncomingMessage $message): void
    {
        if (is_string($action)) {
            $controller = new $action();
            if ($controller instanceof Controller) {
                $controller->setContext($message, $this->driver);
            }
            $method = method_exists($controller, 'handle') ? 'handle' : '__invoke';
            $controller->$method();
            return;
        }

        if (is_array($action) && count($action) === 2) {
            [$class, $method] = $action;
            $controller = new $class();
            if ($controller instanceof Controller) {
                $controller->setContext($message, $this->driver);
            }
            $controller->$method();
        }
    }
}
