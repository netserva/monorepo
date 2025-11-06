<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\Setting;

class AddcfgCommand extends Command
{
    protected $signature = 'addcfg
                            {key : The setting key}
                            {value : The setting value}
                            {--type=string : Value type (string, integer, boolean, json)}
                            {--description= : Optional description}';

    protected $description = 'Add a new configuration setting';

    public function handle(): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        $type = $this->option('type');
        $description = $this->option('description');

        // Check if setting already exists
        if (Setting::where('key', $key)->exists()) {
            $this->components->error("Setting [{$key}] already exists. Use 'chcfg' to modify it.");

            return self::FAILURE;
        }

        // Cast value based on type
        $castValue = match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };

        // Create the setting
        Setting::create([
            'key' => $key,
            'value' => $castValue,
            'type' => $type,
            'description' => $description,
        ]);

        $this->components->success("Created setting: {$key} = {$value}");

        if ($description) {
            $this->components->info("Description: {$description}");
        }

        return self::SUCCESS;
    }
}
