<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\ConfigurationService;
use NetServa\Core\Services\LoggingService;

/**
 * NetServa Core Install Command
 *
 * Handles the installation and setup of the NetServa Core package.
 */
class InstallCommand extends Command
{
    protected $signature = 'netserva-core:install {--force : Force installation even if already installed}';

    protected $description = 'Install and setup NetServa Core';

    public function __construct(
        protected ConfigurationService $config,
        protected LoggingService $logger
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Installing NetServa Core...');

        // Check if already installed
        if (! $this->option('force') && $this->isInstalled()) {
            $this->warn('NetServa Core is already installed. Use --force to reinstall.');

            return self::FAILURE;
        }

        try {
            // Publish configuration
            $this->call('vendor:publish', [
                '--tag' => 'netserva-core-config',
                '--force' => $this->option('force'),
            ]);

            // Run migrations
            $this->call('migrate', ['--force' => true]);

            // Seed default core settings
            $this->seedDefaultSettings();

            // Mark as installed
            $this->markAsInstalled();

            $this->info('✅ NetServa Core installed successfully!');
            $this->logger->info('NetServa Core installed via command line');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Installation failed: {$e->getMessage()}");
            $this->logger->error('NetServa Core installation failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }
    }

    protected function isInstalled(): bool
    {
        return file_exists(config_path('netserva-core.php'));
    }

    protected function markAsInstalled(): void
    {
        // Create a marker file or update config to indicate installation
        $this->config->set('installed', true);
    }

    protected function seedDefaultSettings(): void
    {
        $this->info('Seeding default core settings from .env...');

        $defaults = [
            'app.name' => [
                'value' => config('app.name'),
                'type' => 'string',
                'description' => 'Application name',
            ],
            'app.url' => [
                'value' => config('app.url'),
                'type' => 'string',
                'description' => 'Application URL',
            ],
            'app.timezone' => [
                'value' => config('app.timezone'),
                'type' => 'string',
                'description' => 'Application timezone',
            ],
            'app.locale' => [
                'value' => config('app.locale'),
                'type' => 'string',
                'description' => 'Application locale',
            ],
        ];

        foreach ($defaults as $key => $config) {
            // Only create if doesn't exist (idempotent)
            if (! \NetServa\Core\Models\Setting::where('key', $key)->exists()) {
                \NetServa\Core\Models\Setting::create([
                    'key' => $key,
                    'value' => $config['value'],
                    'type' => $config['type'],
                    'description' => $config['description'],
                ]);

                $this->line("  ✓ Created setting: {$key} = {$config['value']}");
            } else {
                $this->line("  • Setting already exists: {$key}");
            }
        }

        $this->info('✓ Default settings seeded successfully');
    }
}
