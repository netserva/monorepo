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
    protected $signature = 'netserva:install {--force : Force installation even if already installed}';

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
}
