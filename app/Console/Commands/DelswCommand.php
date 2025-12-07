<?php

namespace App\Console\Commands;

use App\Models\SwDomain;
use App\Services\SynergyWholesaleService;
use Illuminate\Console\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class DelswCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'delsw {domain : Domain name to delete/cancel}
                            {--local-only : Only remove from local database}
                            {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete/cancel a Synergy Wholesale domain';

    /**
     * Execute the console command.
     */
    public function handle(SynergyWholesaleService $sw): int
    {
        $domainName = $this->argument('domain');

        // Get domain from local DB
        $domain = SwDomain::where('domain_name', $domainName)->first();

        if (! $domain) {
            $this->error("Domain not found in local database: {$domainName}");

            return 1;
        }

        $this->warn('⚠️  DANGER: Domain deletion');
        $this->newLine();
        $this->line("Domain: {$domain->domain_name}");
        $this->line("Status: {$domain->lifecycle_status}");
        $this->line("Expiry: {$domain->domain_expiry?->format('Y-m-d')}");
        $this->newLine();

        // Determine action
        $localOnly = $this->option('local-only');

        if (! $localOnly && ! $this->option('no-interaction')) {
            $action = select(
                label: 'What would you like to do?',
                options: [
                    'local' => 'Remove from local database only (recommended)',
                    'cancel' => 'Cancel domain at registrar (CANNOT BE UNDONE)',
                    'abort' => 'Cancel operation',
                ],
                default: 'local'
            );

            if ($action === 'abort') {
                $this->info('Operation cancelled');

                return 0;
            }

            $localOnly = ($action === 'local');
        }

        if ($localOnly) {
            return $this->deleteLocal($domain);
        } else {
            return $this->cancelAtRegistrar($domain, $sw);
        }
    }

    /**
     * Delete domain from local database only
     */
    private function deleteLocal(SwDomain $domain): int
    {
        $this->info('Removing from local database only...');
        $this->warn('The domain will remain active at Synergy Wholesale');
        $this->newLine();

        if (! $this->option('force') && ! $this->option('no-interaction')) {
            if (! confirm("Remove {$domain->domain_name} from local database?", false)) {
                $this->info('Operation cancelled');

                return 0;
            }
        }

        try {
            // Delete related records first (cascade should handle this, but be explicit)
            $glueCount = $domain->glueRecords()->count();
            $metaCount = $domain->metadata()->count();
            $fieldsCount = $domain->additionalFields()->count();

            $domain->delete();

            $this->info("✓ Domain removed from local database: {$domain->domain_name}");

            if ($glueCount > 0) {
                $this->line("  • Deleted {$glueCount} glue record(s)");
            }
            if ($metaCount > 0) {
                $this->line("  • Deleted {$metaCount} metadata record(s)");
            }
            if ($fieldsCount > 0) {
                $this->line("  • Deleted {$fieldsCount} additional field(s)");
            }

            $this->newLine();
            $this->line('The domain remains active at Synergy Wholesale');
            $this->line('To re-import, run: php artisan shsw --sync');

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to delete from local database: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Cancel domain at registrar (DANGEROUS)
     */
    private function cancelAtRegistrar(SwDomain $domain, SynergyWholesaleService $sw): int
    {
        $this->error('⚠️  DANGER: This will cancel the domain at Synergy Wholesale');
        $this->error('⚠️  The domain will be DELETED and may become available for others to register');
        $this->error('⚠️  This action CANNOT be undone');
        $this->newLine();

        if ($domain->lifecycle_status === 'active') {
            $this->warn("This is an ACTIVE domain expiring on {$domain->domain_expiry?->format('Y-m-d')}");
            $this->warn('You will LOSE this domain permanently');
        }

        $this->newLine();

        if (! $this->option('force')) {
            // Triple confirmation for safety
            $this->warn('To confirm, you must type the domain name exactly:');
            $confirmation = \Laravel\Prompts\text("Type '{$domain->domain_name}' to confirm:");

            if ($confirmation !== $domain->domain_name) {
                $this->error('Domain name does not match. Operation cancelled.');

                return 1;
            }

            if (! confirm('Are you ABSOLUTELY SURE you want to cancel this domain at the registrar?', false)) {
                $this->info('Operation cancelled');

                return 0;
            }

            if (! confirm('Final confirmation: Cancel domain at registrar?', false)) {
                $this->info('Operation cancelled');

                return 0;
            }
        }

        try {
            $this->line('Cancelling domain at Synergy Wholesale...');

            $sw->cancelDomain($domain->domain_name);

            $this->info('✓ Domain cancelled at registrar');

            // Update local record
            $domain->lifecycle_status = 'cancelled';
            $domain->is_active = false;
            $domain->save();

            $this->newLine();
            $this->warn('The domain has been cancelled and will be deleted');
            $this->line('Local record updated to "cancelled" status');

            return 0;
        } catch (\Exception $e) {
            $this->error("Failed to cancel domain at registrar: {$e->getMessage()}");
            $this->line('Local database has NOT been modified');

            return 1;
        }
    }
}
