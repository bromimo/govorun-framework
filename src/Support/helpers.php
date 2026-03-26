<?php

use Govorun\Foundation\Application;

if (! function_exists('app')) {
    function app(?string $abstract = null, array $parameters = []): mixed
    {
        $instance = Application::getInstance();

        if ($abstract === null) {
            return $instance;
        }

        return $instance->make($abstract, $parameters);
    }
}

if (! function_exists('config')) {
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $config = app('config');

        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $config->set($k, $v);
            }
            return null;
        }

        if ($key === null) {
            return $config;
        }

        return $config->get($key, $default);
    }
}

if (! function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;

        if ($value === null) {
            $value = getenv($key);
            if ($value === false) {
                return $default;
            }
        }

        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (! function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return app()->basePath($path);
    }
}

if (! function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return app()->configPath($path);
    }
}

if (! function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return app()->storagePath($path);
    }
}

if (! function_exists('database_path')) {
    function database_path(string $path = ''): string
    {
        return app()->databasePath($path);
    }
}

if (! function_exists('event')) {
    function event(string|object $event, mixed $payload = [], bool $halt = false): mixed
    {
        return app('events')->dispatch($event, $payload, $halt);
    }
}
