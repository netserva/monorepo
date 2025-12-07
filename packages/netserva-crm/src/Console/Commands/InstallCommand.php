<?php

declare(strict_types=1);

namespace NetServa\Crm\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use NetServa\Crm\CrmServiceProvider;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'crm:install
        {--force : Overwrite existing configuration}
        {--no-interaction : Run without prompts}';

    protected $description = 'Install and configure NetServa CRM';

    public function handle(): int
    {
        info('Installing NetServa CRM...');

        // Run migrations
        note('Running migrations...');
        Artisan::call('migrate', ['--force' => true]);
        $this->line(Artisan::output());

        // Publish config if requested
        if ($this->option('force') || (! $this->option('no-interaction') && confirm('Publish CRM configuration file?', default: false))) {
            Artisan::call('vendor:publish', [
                '--tag' => 'netserva-crm-config',
                '--force' => $this->option('force'),
            ]);
            note('Configuration published to config/netserva-crm.php');
        }

        // Show integration status
        info('Integration Status:');
        $this->table(
            ['Integration', 'Available'],
            [
                ['Fleet (VSites/VNodes/VHosts)', CrmServiceProvider::hasFleetIntegration() ? 'Yes' : 'No'],
                ['Domains (SwDomain)', CrmServiceProvider::hasDomainIntegration() ? 'Yes' : 'No'],
                ['Core (Plugin Discovery)', CrmServiceProvider::hasCoreIntegration() ? 'Yes' : 'No'],
            ]
        );

        if (! CrmServiceProvider::hasFleetIntegration()) {
            warning('Fleet integration not available. Install netserva/fleet to link customers to VSites.');
        }

        if (! CrmServiceProvider::hasDomainIntegration()) {
            warning('Domain integration not available. SwDomain model not found.');
        }

        info('NetServa CRM installed successfully!');
        note('Run `php artisan addcrm` to create your first customer.');

        return self::SUCCESS;
    }
}
