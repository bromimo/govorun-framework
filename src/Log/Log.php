<?php

namespace Govorun\Log;

use Psr\Log\LoggerInterface;

class Log
{
    public static function emergency(string $message, array $context = []): void
    {
        static::logger()->emergency($message, $context);
    }

    public static function alert(string $message, array $context = []): void
    {
        static::logger()->alert($message, $context);
    }

    public static function critical(string $message, array $context = []): void
    {
        static::logger()->critical($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        static::logger()->error($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        static::logger()->warning($message, $context);
    }

    public static function notice(string $message, array $context = []): void
    {
        static::logger()->notice($message, $context);
    }

    public static function info(string $message, array $context = []): void
    {
        static::logger()->info($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        static::logger()->debug($message, $context);
    }

    private static function logger(): LoggerInterface
    {
        return app('log');
    }
}
