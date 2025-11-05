<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\SettingsManager;

class SettingsForgetCommand extends Command
{
    protected $signature = 'settings:forget
                            {key : The setting key to delete}
                            {--force : Skip confirmation}';

    protected $description = 'Delete a configuration setting';

    public function handle(SettingsManager $settings): int
    {
        $key = $this->argument('key');

        if (! $settings->has($key)) {
            $this->components->error("Setting [{$key}] not found");

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Delete setting [{$key}]?")) {
                $this->components->info('Cancelled');

                return self::SUCCESS;
            }
        }

        $settings->forget($key);

        $this->components->info("Deleted setting [{$key}]");

        return self::SUCCESS;
    }
}
