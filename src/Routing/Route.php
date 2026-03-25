<?php

namespace Govorun\Routing;

class Route
{
    /** @var RouteEntry[] */
    protected static array $routes = [];
    /** @var string[] */
    protected static array $middlewareStack = [];

    public static function command(string $name, mixed $action): RouteEntry
    {
        return static::addRoute('command', $name, $action);
    }

    public static function phrase(string $phrase, mixed $action): RouteEntry
    {
        if ($action instanceof \Closure) {
            $entry = new RouteEntry('phrase', $phrase, null);
            $entry->middleware = static::$middlewareStack;
            $previousRoutes = static::$routes;
            static::$routes = [];
            $action();
            $entry->children = static::$routes;
            static::$routes = $previousRoutes;
            static::$routes[] = $entry;
            return $entry;
        }
        return static::addRoute('phrase', $phrase, $action);
    }

    public static function pattern(string $regex, mixed $action): RouteEntry
    {
        return static::addRoute('pattern', $regex, $action);
    }

    public static function action(string $name, mixed $action): RouteEntry
    {
        return static::addRoute('action', $name, $action);
    }

    public static function event(string $name, mixed $action): RouteEntry
    {
        return static::addRoute('event', $name, $action);
    }

    public static function media(string $type, mixed $action): RouteEntry
    {
        return static::addRoute('media', $type, $action);
    }

    public static function location(mixed $action): RouteEntry
    {
        return static::addRoute('location', null, $action);
    }

    public static function contact(mixed $action): RouteEntry
    {
        return static::addRoute('contact', null, $action);
    }

    public static function referral(string $code, mixed $action): RouteEntry
    {
        return static::addRoute('referral', $code, $action);
    }

    public static function fallback(mixed $action): RouteEntry
    {
        return static::addRoute('fallback', null, $action);
    }

    public static function middleware(string $middleware, \Closure $callback): void
    {
        static::$middlewareStack[] = $middleware;
        $callback();
        array_pop(static::$middlewareStack);
    }

    /** @return RouteEntry[] */
    public static function getRoutes(): array
    {
        return static::$routes;
    }

    public static function clear(): void
    {
        static::$routes = [];
        static::$middlewareStack = [];
    }

    protected static function addRoute(string $type, ?string $value, mixed $action): RouteEntry
    {
        $entry = new RouteEntry($type, $value, $action);
        $entry->middleware = static::$middlewareStack;
        static::$routes[] = $entry;
        return $entry;
    }
}
