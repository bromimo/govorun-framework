<?php

namespace Govorun\Log;

use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Govorun\Foundation\ServiceProvider;
use Monolog\Handler\RotatingFileHandler;

/** Сервис-провайдер логирования.
 * Регистрирует PSR-3 логгер в контейнере с поддержкой каналов:
 * single (один файл), daily (ротация по дням), stack (объединение каналов).
 */
class LogServiceProvider extends ServiceProvider
{
    /** Зарегистрировать логгер в контейнере.
     * @return void
     */
    public function register(): void
    {
        $this->app->singleton('log', function () {
            return $this->createLogger();
        });

        $this->app->alias('log', LoggerInterface::class);
    }

    /** Создать экземпляр логгера на основе конфигурации.
     * @return LoggerInterface Настроенный экземпляр логгера
     * @throws \RuntimeException При обнаружении циклической зависимости каналов
     */
    private function createLogger(): LoggerInterface
    {
        $config = $this->app->make('config');
        $defaultChannel = $config->get('logging.default', 'single');
        $channels = $config->get('logging.channels', []);
        $channelConfig = $channels[$defaultChannel] ?? [];

        return $this->resolveChannel($defaultChannel, $channelConfig, $channels);
    }

    /** Разрешить канал логирования по имени и конфигурации.
     * @param string $name Имя канала
     * @param array<string, mixed> $config Конфигурация канала
     * @param array<string, array<string, mixed>> $allChannels Все доступные каналы
     * @param array<string, bool> &$resolved Уже разрешённые каналы (защита от циклов)
     * @return Logger Экземпляр Monolog-логгера
     * @throws \RuntimeException При обнаружении циклической зависимости каналов
     */
    private function resolveChannel(string $name, array $config, array $allChannels, array &$resolved = []): Logger
    {
        if (isset($resolved[$name])) {
            throw new \RuntimeException("Circular channel reference detected: {$name}");
        }

        $resolved[$name] = true;
        $driver = $config['driver'] ?? 'single';

        return match ($driver) {
            'single' => $this->createSingleChannel($name, $config),
            'daily' => $this->createDailyChannel($name, $config),
            'stack' => $this->createStackChannel($name, $config, $allChannels, $resolved),
            default => $this->createSingleChannel($name, $config),
        };
    }

    /** Создать канал с записью в один файл.
     * @param string $name Имя канала
     * @param array<string, mixed> $config Конфигурация канала
     * @return Logger Экземпляр логгера
     */
    private function createSingleChannel(string $name, array $config): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler(
            $config['path'] ?? $this->app->storagePath('logs/govorun.log'),
            $this->parseLevel($config['level'] ?? 'debug'),
        ));

        return $logger;
    }

    /** Создать канал с ежедневной ротацией файлов.
     * @param string $name Имя канала
     * @param array<string, mixed> $config Конфигурация канала
     * @return Logger Экземпляр логгера
     */
    private function createDailyChannel(string $name, array $config): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler(new RotatingFileHandler(
            $config['path'] ?? $this->app->storagePath('logs/govorun.log'),
            $config['days'] ?? 14,
            $this->parseLevel($config['level'] ?? 'debug'),
        ));

        return $logger;
    }

    /** Создать составной канал (стек), объединяющий несколько каналов.
     * @param string $name Имя канала
     * @param array<string, mixed> $config Конфигурация канала
     * @param array<string, array<string, mixed>> $allChannels Все доступные каналы
     * @param array<string, bool> &$resolved Уже разрешённые каналы (защита от циклов)
     * @return Logger Экземпляр логгера с объединёнными обработчиками
     * @throws \RuntimeException При обнаружении циклической зависимости каналов
     */
    private function createStackChannel(string $name, array $config, array $allChannels, array &$resolved = []): Logger
    {
        $logger = new Logger($name);
        $stackChannelNames = $config['channels'] ?? [];

        foreach ($stackChannelNames as $channelName) {
            $channelConfig = $allChannels[$channelName] ?? [];
            $sub = $this->resolveChannel($channelName, $channelConfig, $allChannels, $resolved);

            foreach ($sub->getHandlers() as $handler) {
                $logger->pushHandler($handler);
            }
        }

        return $logger;
    }

    /** Разобрать строковое имя уровня логирования в enum.
     * @param string $level Строковое имя уровня (debug, info и т.д.)
     * @return Level Уровень логирования Monolog
     */
    private function parseLevel(string $level): Level
    {
        return Level::fromName($level);
    }
}
