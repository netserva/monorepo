<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\ProxmoxApiService;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Proxmox API Discovery Command
 *
 * Discovers infrastructure via Proxmox VE API
 */
class DiscoverProxmoxCommand extends Command
{
    protected $signature = 'fleet:discover-proxmox
                            {--vsite= : VSite name to discover}
                            {--test : Test API connectivity only}
                            {--nodes : Discover nodes only}
                            {--instances : Discover instances only}';

    protected $description = 'Discover Proxmox infrastructure via API';

    public function handle(): int
    {
        info('🔍 NetServa Fleet Proxmox API Discovery');

        // Get or select VSite
        $vsiteName = $this->option('vsite');
        if (! $vsiteName) {
            $vsites = FleetVsite::where('technology', 'proxmox')
                ->whereNotNull('api_endpoint')
                ->pluck('name', 'name')
                ->toArray();

            if (empty($vsites)) {
                error('No Proxmox VSites found with API endpoints configured.');

                return self::FAILURE;
            }

            $vsiteName = select(
                label: 'Select Proxmox VSite',
                options: $vsites
            );
        }

        $vsite = FleetVsite::where('name', $vsiteName)->first();
        if (! $vsite) {
            error("VSite '{$vsiteName}' not found!");

            return self::FAILURE;
        }

        if (! $vsite->api_endpoint || ! $vsite->api_credentials) {
            error("VSite '{$vsiteName}' does not have API configuration!");

            return self::FAILURE;
        }

        try {
            $apiService = new ProxmoxApiService($vsite);

            // Test connectivity
            info("Testing API connectivity to: {$vsite->api_endpoint}");
            $connectionTest = $apiService->testConnection();

            if (! $connectionTest['success']) {
                error("API connection failed: {$connectionTest['error']}");

                return self::FAILURE;
            }

            info("✅ Connected to Proxmox VE {$connectionTest['version']} (release {$connectionTest['release']})");

            if ($this->option('test')) {
                return self::SUCCESS;
            }

            // Discover nodes
            if (! $this->option('instances')) {
                info('🖥️  Discovering Proxmox nodes...');
                $nodeResults = $apiService->syncNodes();

                table(['Action', 'Count'], [
                    ['Nodes Created', $nodeResults['created']],
                    ['Nodes Updated', $nodeResults['updated']],
                    ['Errors', count($nodeResults['errors'])],
                ]);

                if (! empty($nodeResults['errors'])) {
                    warning('Node discovery errors:');
                    foreach ($nodeResults['errors'] as $error) {
                        $this->line("  • {$error}");
                    }
                }

                if ($this->option('nodes')) {
                    return self::SUCCESS;
                }
            }

            // Discover instances
            if (! $this->option('nodes')) {
                info('🌐 Discovering VMs and containers...');
                $instanceResults = $apiService->syncInstances();

                table(['Action', 'Count'], [
                    ['Instances Created', $instanceResults['created']],
                    ['Instances Updated', $instanceResults['updated']],
                    ['Templates Skipped', $instanceResults['skipped']],
                    ['Errors', count($instanceResults['errors'])],
                ]);

                if (! empty($instanceResults['errors'])) {
                    warning('Instance discovery errors:');
                    foreach ($instanceResults['errors'] as $error) {
                        $this->line("  • {$error}");
                    }
                }
            }

            info('✅ Proxmox discovery completed successfully!');
            info('🔧 Next steps:');
            info('   • Review discovered infrastructure in Filament interface');
            info('   • Configure SSH access for nodes');
            info('   • Set up monitoring and alerts');

            return self::SUCCESS;

        } catch (\Exception $e) {
            error("Discovery failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }
}
