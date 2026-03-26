<?php

namespace Govorun\Console;

use Illuminate\Console\Command;

class WebhookRemoveCommand extends Command
{
    protected $signature = 'webhook:remove';
    protected $description = 'Remove webhooks for active messenger drivers';

    public function handle(): int
    {
        $this->info('Not yet implemented.');
        return self::SUCCESS;
    }
}
