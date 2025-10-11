<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsProviderManagementService;

use function Laravel\Prompts\confirm;

/**
 * Delete DNS Provider Command
 *
 * Delete a DNS provider with safety checks
 * Follows NetServa CRUD pattern: deldns (not "dns:provider:delete")
 *
 * Usage: deldns <provider> [options]
 * Example: deldns 4
 * Example: deldns "Old PowerDNS" --cascade
 */
class DeleteDnsCommand extends Command
{
    protected $signature = 'deldns
        {provider : Provider ID or name}
        {--force : Delete even if zones exist or in use}
        {--cascade : Also delete all zones and records (DANGEROUS)}
        {--reassign= : Reassign zones to another provider ID}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete DNS provider (NetServa CRUD pattern)';

    protected DnsProviderManagementService $providerService;

    public function __construct(DnsProviderManagementService $providerService)
    {
        parent::__construct();
        $this->providerService = $providerService;
    }

    public function handle(): int
    {
        $identifier = $this->argument('provider');

        // Get provider details for impact analysis
        $providerResult = $this->providerService->showProvider($identifier, [
            'with_usage' => true,
            'with_zones' => false,
        ]);

        if (! $providerResult['success']) {
            $this->error("❌ {$providerResult['message']}");

            return self::FAILURE;
        }

        $provider = $providerResult['provider'];
        $zonesCount = $providerResult['zones_count'];
        $usage = $providerResult['usage'] ?? [];

        // Display impact analysis
        $this->newLine();
        $this->warn("⚠️  Deleting DNS Provider: {$provider->name} (ID: {$provider->id})");
        $this->newLine();

        $this->info('Impact Analysis:');
        $this->line("  Zones:      <fg=".($zonesCount > 0 ? 'red' : 'green').">{$zonesCount}</>");

        if (! empty($usage)) {
            $this->line("  Venues:     <fg=".($usage['venues'] > 0 ? 'red' : 'gray').">{$usage['venues']}</>");
            $this->line("  VSites:     <fg=".($usage['vsites'] > 0 ? 'red' : 'gray').">{$usage['vsites']}</>");
            $this->line("  VNodes:     <fg=".($usage['vnodes'] > 0 ? 'red' : 'gray').">{$usage['vnodes']}</>");
            $this->line("  VHosts:     <fg=".($usage['vhosts'] > 0 ? 'red' : 'gray').">{$usage['vhosts']}</>");
        }

        $this->newLine();

        // Check if safe to delete
        $hasZones = $zonesCount > 0;
        $inUse = ! empty($usage) && array_sum($usage) > 0;
        $safeToDelete = ! $hasZones && ! $inUse;

        if ($safeToDelete) {
            $this->info('✅ Safe to delete');
        } else {
            if ($hasZones) {
                $this->error("❌ Cannot delete - provider has {$zonesCount} zone(s)");
            }

            if ($inUse) {
                $totalUsage = array_sum($usage);
                $this->error("❌ Cannot delete - provider is used by {$totalUsage} resource(s)");
            }

            if (! $this->option('force') && ! $this->option('cascade') && ! $this->option('reassign')) {
                $this->newLine();
                $this->line('💡 Options:');

                if ($hasZones) {
                    $this->line("   1. Reassign zones: deldns {$provider->id} --reassign=<provider_id>");
                    $this->line("   2. Cascade delete: deldns {$provider->id} --cascade (DANGEROUS)");
                }

                $this->line("   3. Force delete:   deldns {$provider->id} --force (VERY DANGEROUS)");

                return self::FAILURE;
            }
        }

        // Prepare delete options
        $options = [];

        if ($this->option('force')) {
            $options['force'] = true;
        }

        if ($this->option('cascade')) {
            $options['cascade'] = true;
        }

        if ($this->option('reassign')) {
            $options['reassign'] = $this->option('reassign');
        }

        // Dry run preview
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would delete provider:');
            $this->line("  ID: {$provider->id}");
            $this->line("  Name: {$provider->name}");
            $this->line("  Type: {$provider->type}");
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));
            $this->line('');

            if (isset($options['cascade'])) {
                $this->warn("  Would also delete {$zonesCount} zone(s) and all their records!");
            }

            if (isset($options['reassign'])) {
                $this->line("  Would reassign {$zonesCount} zone(s) to provider {$options['reassign']}");
            }

            return self::SUCCESS;
        }

        // Confirmation for dangerous operations
        if ($options['cascade'] ?? false) {
            $this->newLine();
            $this->error('⚠️ WARNING: CASCADE DELETE');
            $this->line("This will delete {$zonesCount} zone(s) and ALL their DNS records!");
            $this->line('This operation CANNOT be undone!');
            $this->newLine();

            if (! confirm('Are you ABSOLUTELY SURE you want to proceed?', false)) {
                $this->info('Deletion cancelled');

                return self::SUCCESS;
            }

            // Double confirmation for cascade
            if (! confirm("Type YES to confirm deletion of '{$provider->name}' and all zones", false)) {
                $this->info('Deletion cancelled');

                return self::SUCCESS;
            }
        } elseif (! $safeToDelete) {
            // Confirm force delete or reassignment
            if (! confirm("Confirm deletion of '{$provider->name}'?", false)) {
                $this->info('Deletion cancelled');

                return self::SUCCESS;
            }
        } else {
            // Safe delete - simple confirmation
            if (! confirm("Delete DNS provider '{$provider->name}'?", true)) {
                $this->info('Deletion cancelled');

                return self::SUCCESS;
            }
        }

        // Perform deletion
        $this->newLine();
        $this->line('🗑️  Deleting...');

        $result = $this->providerService->deleteProvider(
            identifier: $identifier,
            options: $options
        );

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Error: {$result['error']}");
            }

            // Show impact if deletion blocked
            if (isset($result['zones_count']) && $result['zones_count'] > 0) {
                $this->newLine();
                $this->line('Provider has:');
                $this->line("  Zones: {$result['zones_count']}");

                if (isset($result['usage'])) {
                    foreach ($result['usage'] as $type => $count) {
                        if ($count > 0) {
                            $this->line("  ".ucfirst($type).": {$count}");
                        }
                    }
                }
            }

            return self::FAILURE;
        }

        // Success
        $this->info("✅ {$result['message']}");

        if (isset($result['zones_deleted']) && $result['zones_deleted'] > 0) {
            $this->line("   Zones deleted: <fg=red>{$result['zones_deleted']}</>");
        }

        if (isset($result['zones_reassigned']) && $result['zones_reassigned'] > 0) {
            $this->line("   Zones reassigned: <fg=cyan>{$result['zones_reassigned']}</>");
        }

        // Show remaining providers
        $this->newLine();
        $this->line('💡 View remaining providers: shdns');

        return self::SUCCESS;
    }
}
