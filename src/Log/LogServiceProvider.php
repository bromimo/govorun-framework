<?php

namespace Govorun\Log;

use Govorun\Foundation\ServiceProvider;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class LogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('log', function () {
            return $this->createLogger();
        });

        $this->app->alias('log', LoggerInterface::class);
    }

    private function createLogger(): LoggerInterface
    {
        $config = $this->app->make('config');
        $defaultChannel = $config->get('logging.default', 'single');
        $channels = $config->get('logging.channels', []);
        $channelConfig = $channels[$defaultChannel] ?? [];

        return $this->resolveChannel($defaultChannel, $channelConfig, $channels);
    }

    private function resolveChannel(string $name, array $config, array $allChannels): Logger
    {
        $driver = $config['driver'] ?? 'single';

        return match ($driver) {
            'single' => $this->createSingleChannel($name, $config),
            'daily' => $this->createDailyChannel($name, $config),
            'stack' => $this->createStackChannel($name, $config, $allChannels),
            default => $this->createSingleChannel($name, $config),
        };
    }

    private function createSingleChannel(string $name, array $config): Logger
    {
        $logger = new Logger($name);
        $logger->pushHandler(new StreamHandler(
            $config['path'] ?? $this->app->storagePath('logs/govorun.log'),
            $this->parseLevel($config['level'] ?? 'debug'),
        ));

        return $logger;
    }

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

    private function createStackChannel(string $name, array $config, array $allChannels): Logger
    {
        $logger = new Logger($name);
        $stackChannelNames = $config['channels'] ?? [];

        foreach ($stackChannelNames as $channelName) {
            $channelConfig = $allChannels[$channelName] ?? [];
            $sub = $this->resolveChannel($channelName, $channelConfig, $allChannels);

            foreach ($sub->getHandlers() as $handler) {
                $logger->pushHandler($handler);
            }
        }

        return $logger;
    }

    private function parseLevel(string $level): Level
    {
        return Level::fromName($level);
    }
}
