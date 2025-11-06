<?php

declare(strict_types=1);

namespace NetServa\Admin\Console\Commands;

use Illuminate\Console\Command;

/**
 * NetServa Admin Install Command
 *
 * Handles the installation and setup of the NetServa Admin package.
 */
class InstallCommand extends Command
{
    protected $signature = 'netserva-admin:install {--force : Force installation even if already installed}';

    protected $description = 'Install and setup NetServa Admin panel';

    public function handle(): int
    {
        $this->components->info('Installing NetServa Admin...');

        try {
            // Ensure netserva-core is installed
            if (! class_exists(\NetServa\Core\Models\Setting::class)) {
                $this->components->error('NetServa Core is required. Please run: php artisan netserva-core:install');

                return self::FAILURE;
            }

            // Publish configuration
            $this->components->task('Publishing configuration', function () {
                $this->call('vendor:publish', [
                    '--tag' => 'netserva-admin-config',
                    '--force' => $this->option('force'),
                ]);

                return true;
            });

            // Run migrations
            $this->components->task('Running migrations', function () {
                $this->call('migrate', ['--force' => true]);

                return true;
            });

            // Clear caches to ensure Filament discovers resources
            $this->components->task('Clearing caches', function () {
                $this->call('optimize:clear');
                $this->call('filament:upgrade');

                return true;
            });

            $this->newLine();
            $this->components->success('NetServa Admin installed successfully!');
            $this->newLine();

            $this->components->info('Next steps:');
            $this->components->bulletList([
                'Add resource discovery to your AdminPanelProvider.php',
                'Run: php artisan filament:upgrade',
                'Visit /admin to access the admin panel',
            ]);

            $this->newLine();
            $this->components->warn('Add this to app/Providers/Filament/AdminPanelProvider.php:');
            $this->line('');
            $this->line("->discoverResources(in: base_path('vendor/netserva/admin/src/Filament/Resources'), for: 'NetServa\\Admin\\Filament\\Resources')");
            $this->line('');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->components->error("Installation failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
