<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\SettingsManager;

class SettingsSetCommand extends Command
{
    protected $signature = 'settings:set
                            {key : The setting key}
                            {value : The setting value}
                            {--category= : Optional category (e.g., mail, dns, web)}
                            {--type=string : Value type (string, integer, boolean, json)}';

    protected $description = 'Set a configuration setting';

    public function handle(SettingsManager $settings): int
    {
        $key = $this->argument('key');
        $value = $this->argument('value');
        $category = $this->option('category');
        $type = $this->option('type');

        // Cast value based on type
        $value = match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($value, true),
            default => $value,
        };

        $settings->set($key, $value, $category);

        $this->components->info("Setting [{$key}] = [{$value}]");

        if ($category) {
            $this->components->info("Category: {$category}");
        }

        return self::SUCCESS;
    }
}
