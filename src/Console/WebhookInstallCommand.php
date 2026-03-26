<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class WebhookInstallCommand extends Command
{
    protected $signature = 'webhook:install';
    protected $description = 'Install webhooks for active messenger drivers';

    public function handle(): int
    {
        $config = app('config');
        $drivers = $config->get('messenger.drivers', []);
        $baseUrl = rtrim($config->get('app.url', ''), '/');

        if (empty($drivers)) {
            $this->warn('No active messenger drivers configured.');
            return self::SUCCESS;
        }

        foreach ($drivers as $driverName) {
            $url = "{$baseUrl}/webhook/{$driverName}";

            try {
                $driver = $this->resolveDriver($driverName);

                if ($driver->installWebhook($url)) {
                    $this->info("Webhook installed for {$driverName}: {$url}");
                } else {
                    $this->error("Failed to install webhook for {$driverName}");
                    return self::FAILURE;
                }
            } catch (\Throwable $e) {
                $this->error("Error installing webhook for {$driverName}: {$e->getMessage()}");
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

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
