<?php

namespace Govorun\Log;

use Psr\Log\LoggerInterface;

/** Фасад логирования.
 * Предоставляет статический доступ к PSR-3 логгеру,
 * зарегистрированному в контейнере приложения.
 */
class Log
{
    /** Записать сообщение уровня emergency.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function emergency(string $message, array $context = []): void
    {
        static::logger()->emergency($message, $context);
    }

    /** Записать сообщение уровня alert.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function alert(string $message, array $context = []): void
    {
        static::logger()->alert($message, $context);
    }

    /** Записать сообщение уровня critical.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function critical(string $message, array $context = []): void
    {
        static::logger()->critical($message, $context);
    }

    /** Записать сообщение уровня error.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        static::logger()->error($message, $context);
    }

    /** Записать сообщение уровня warning.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        static::logger()->warning($message, $context);
    }

    /** Записать сообщение уровня notice.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function notice(string $message, array $context = []): void
    {
        static::logger()->notice($message, $context);
    }

    /** Записать сообщение уровня info.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        static::logger()->info($message, $context);
    }

    /** Записать сообщение уровня debug.
     * @param string $message Текст сообщения
     * @param array<string, mixed> $context Контекстные данные
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        static::logger()->debug($message, $context);
    }

    /** Получить экземпляр логгера из контейнера.
     * @return LoggerInterface Экземпляр PSR-3 логгера
     */
    private static function logger(): LoggerInterface
    {
        return app('log');
    }
}
