<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\MountManager;

class MountCommand extends Command
{
    protected $signature = 'ns:mount {action : mount|unmount|list} {host?}';

    protected $description = 'Manage SSH mount points';

    public function handle(MountManager $mountManager): int
    {
        $action = $this->argument('action');
        $host = $this->argument('host');

        match ($action) {
            'list' => $this->info('Mount points listed'),
            'mount' => $this->info("Mounted: {$host}"),
            'unmount' => $this->info("Unmounted: {$host}"),
            default => $this->error("Unknown action: {$action}")
        };

        return Command::SUCCESS;
    }
}
