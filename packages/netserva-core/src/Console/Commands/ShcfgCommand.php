<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\Setting;

class ShcfgCommand extends Command
{
    protected $signature = 'shcfg
                            {key? : The setting key to show (optional)}
                            {--all : Show all settings}';

    protected $description = 'Show configuration setting(s)';

    public function handle(): int
    {
        $key = $this->argument('key');
        $showAll = $this->option('all');

        // Show all settings
        if ($showAll || ! $key) {
            return $this->showAllSettings();
        }

        // Show specific setting
        return $this->showSetting($key);
    }

    protected function showSetting(string $key): int
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            $this->components->error("Setting [{$key}] not found.");

            return self::FAILURE;
        }

        $this->components->info("Key: {$setting->key}");
        $this->components->info("Value: {$setting->getTypedValue()}");
        $this->components->info("Type: {$setting->type}");

        if ($setting->description) {
            $this->components->info("Description: {$setting->description}");
        }

        return self::SUCCESS;
    }

    protected function showAllSettings(): int
    {
        $settings = Setting::orderBy('key')->get();

        if ($settings->isEmpty()) {
            $this->components->warn('No settings found.');

            return self::SUCCESS;
        }

        $tableData = $settings->map(function ($setting) {
            $value = $setting->getTypedValue();

            // Convert arrays/objects to JSON for display
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            }

            return [
                'key' => $setting->key,
                'value' => $value ?? '',
            ];
        })->toArray();

        $this->table(
            ['Key', 'Value'],
            $tableData
        );

        return self::SUCCESS;
    }
}
