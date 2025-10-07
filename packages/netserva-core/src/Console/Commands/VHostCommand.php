<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;

class VHostCommand extends Command
{
    protected $signature = 'ns:vhost {action : create|delete|list}';

    protected $description = 'Manage virtual hosts';

    public function handle(): int
    {
        $action = $this->argument('action');
        $this->info("VHost action: {$action}");

        return Command::SUCCESS;
    }
}
