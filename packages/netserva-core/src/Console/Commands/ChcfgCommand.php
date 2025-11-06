<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\Setting;

class ChcfgCommand extends Command
{
    protected $signature = 'chcfg
                            {key : The setting key}
                            {value : The new setting value}';

    protected $description = 'Change an existing configuration setting value';

    public function handle(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');

        // Find the setting
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            $this->components->error("Setting [{$key}] not found. Use 'addcfg' to create it.");

            return self::FAILURE;
        }

        // Store old value for display
        $oldValue = $setting->getTypedValue();

        // Cast value based on setting's existing type
        $castValue = match ($setting->type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };

        // Update the setting
        $setting->update(['value' => $castValue]);

        $this->components->success("Updated setting: {$key}");
        $this->components->info("Old value: {$oldValue}");
        $this->components->info("New value: {$value}");

        return self::SUCCESS;
    }
}
