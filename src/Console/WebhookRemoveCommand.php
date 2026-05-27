<?php

namespace Govorun\Console;

use Illuminate\Console\Command;
use Govorun\Exceptions\WebhookManualSetupException;

/** Команда удаления вебхуков для активных мессенджер-драйверов.
 * Перебирает настроенные драйверы и удаляет зарегистрированные вебхуки.
 */
class WebhookRemoveCommand extends Command
{
    protected $signature = 'webhook:remove';
    protected $description = 'Remove webhooks for active messenger drivers';

    /** Удалить вебхуки для всех настроенных мессенджер-драйверов.
     * @return int
     */
    public function handle(): int
    {
        $config = app('config');
        $drivers = $config->get('messenger.drivers', []);

        if (empty($drivers)) {
            $this->warn('No active messenger drivers configured.');
            return self::SUCCESS;
        }

        foreach ($drivers as $driverName) {
            try {
                $driver = $this->resolveDriver($driverName);

                if ($driver->removeWebhook()) {
                    $this->info("Webhook removed for {$driverName}");
                } else {
                    $this->error("Failed to remove webhook for {$driverName}");
                    return self::FAILURE;
                }
            } catch (WebhookManualSetupException $e) {
                $this->warn("{$driverName}: {$e->getMessage()}");
            } catch (\Throwable $e) {
                $this->error("Error removing webhook for {$driverName}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /** Разрешить экземпляр драйвера мессенджера по имени.
     * @param string $name Имя драйвера
     * @return \Govorun\Contracts\MessengerDriver
     */
    private function resolveDriver(string $name): \Govorun\Contracts\MessengerDriver
    {
        $app = app();

        if ($app->bound("driver.{$name}")) {
            return $app->make("driver.{$name}");
        }

        $className = 'Govorun\\Drivers\\' . ucfirst($name) . '\\' . ucfirst($name) . 'Driver';
        $config = app('config')->get("messenger.{$name}", []);

        return new $className(config: $config, client: new \GuzzleHttp\Client());
    }
}
