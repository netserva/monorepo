<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\SettingsManager;

class SettingsGetCommand extends Command
{
    protected $signature = 'settings:get
                            {key : The setting key}
                            {--default= : Default value if setting not found}';

    protected $description = 'Get a configuration setting value';

    public function handle(SettingsManager $settings): int
    {
        $key = $this->argument('key');
        $default = $this->option('default');

        $value = $settings->get($key, $default);

        if ($value === null && $default === null) {
            $this->components->error("Setting [{$key}] not found");

            return self::FAILURE;
        }

        $this->components->info("{$key} = ".json_encode($value));

        return self::SUCCESS;
    }
}
