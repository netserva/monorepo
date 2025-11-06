<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\Setting;

class DelcfgCommand extends Command
{
    protected $signature = 'delcfg
                            {key : The setting key to delete}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete a configuration setting';

    public function handle(): int
    {
        $key = $this->argument('key');
        $force = $this->option('force');

        // Find the setting
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            $this->components->error("Setting [{$key}] not found.");

            return self::FAILURE;
        }

        // Show what will be deleted
        $this->components->warn('You are about to delete:');
        $this->components->info("Key: {$setting->key}");
        $this->components->info("Value: {$setting->getTypedValue()}");

        // Confirm deletion unless forced
        if (! $force && ! $this->confirm('Are you sure you want to delete this setting?', false)) {
            $this->components->info('Deletion cancelled.');

            return self::SUCCESS;
        }

        // Delete the setting
        $setting->delete();

        $this->components->success("Deleted setting: {$key}");

        return self::SUCCESS;
    }
}
