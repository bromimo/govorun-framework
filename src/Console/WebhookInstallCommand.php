<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class WebhookInstallCommand extends Command
{
    protected $signature = 'webhook:install';
    protected $description = 'Install webhooks for active messenger drivers';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
