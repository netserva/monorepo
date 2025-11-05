<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\SettingsManager;

class SettingsListCommand extends Command
{
    protected $signature = 'settings:list
                            {--category= : Filter by category}';

    protected $description = 'List all configuration settings';

    public function handle(SettingsManager $settings): int
    {
        $category = $this->option('category');

        $allSettings = $category
            ? $settings->category($category)
            : $settings->all();

        if (empty($allSettings)) {
            $message = $category
                ? "No settings found in category [{$category}]"
                : 'No settings found';

            $this->components->warn($message);

            return self::SUCCESS;
        }

        $header = $category ? "Settings in category [{$category}]" : 'All Settings';
        $this->components->info($header);
        $this->newLine();

        $rows = [];
        foreach ($allSettings as $key => $value) {
            $rows[] = [
                $key,
                is_array($value) ? json_encode($value) : (string) $value,
            ];
        }

        $this->table(['Key', 'Value'], $rows);

        return self::SUCCESS;
    }
}
