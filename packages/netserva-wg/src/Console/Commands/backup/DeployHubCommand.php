<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Services\DeploymentService;
use NetServa\Wg\Services\HubOrchestrationService;

class DeployHubCommand extends Command
{
    protected $signature = 'wireguard:deploy-hub
                          hub? : Hub ID or name to deploy
                          --all : Deploy all pending hubs
                          --type= : Deploy all hubs of specific type (workstation,logging,gateway,customer)
                          --force : Force redeployment even if already deployed
                          --dry-run : Show what would be deployed without actually deploying';

    protected $description = 'Deploy WireGuard hub configuration to remote server';

    public function handle(
        HubOrchestrationService $orchestrationService,
        DeploymentService $deploymentService
    ): int {
        $this->info('🚀 WireGuard Hub Deployment Tool');

        if ($this->option('dry-run')) {
            $this->warn('🧪 DRY RUN MODE - No actual deployments will be performed');
        }

        // Determine which hubs to deploy
        $hubs = $this->getHubsToProcess();

        if ($hubs->isEmpty()) {
            $this->error('❌ No hubs found matching criteria');

            return 1;
        }

        $this->info("📋 Found $hubs->count() hub(s) to process:");
        $this->table(
            ['ID', 'Name', 'Type', 'Status', 'Endpoint', 'SSH Host'],
            $hubs->map(fn ($hub) => [
                $hub->id,
                $hub->name,
                $hub->hub_type,
                $hub->deployment_status,
                $hub->endpoint ?? 'Not set',
                $hub->sshHost?->hostname ?? 'Not configured',
            ])->toArray()
        );

        if (! $this->option('dry-run')) {
            if (! $this->confirm('🔄 Proceed with deployment?')) {
                $this->info('⏹️ Deployment cancelled');

                return 0;
            }
        }

        // Deploy hubs
        $this->deployHubs($hubs, $orchestrationService, $deploymentService);

        return 0;
    }

    private function getHubsToProcess()
    {
        if ($this->option('all')) {
            $query = WireguardHub::query();

            if (! $this->option('force')) {
                $query->whereIn('deployment_status', ['pending', 'failed']);
            }

            return $query->get();
        }

        if ($type = $this->option('type')) {
            $query = WireguardHub::where('hub_type', $type);

            if (! $this->option('force')) {
                $query->whereIn('deployment_status', ['pending', 'failed']);
            }

            return $query->get();
        }

        if ($hubIdentifier = $this->argument('hub')) {
            $hub = is_numeric($hubIdentifier)
                ? WireguardHub::find($hubIdentifier)
                : WireguardHub::where('name', $hubIdentifier)->first();

            if (! $hub) {
                $this->error("❌ Hub '$hubIdentifier' not found");

                return collect();
            }

            return collect([$hub]);
        }

        // Interactive selection
        return $this->selectHubsInteractively();
    }

    private function selectHubsInteractively()
    {
        $availableHubs = WireguardHub::all();

        if ($availableHubs->isEmpty()) {
            $this->error('❌ No hubs configured');

            return collect();
        }

        $choices = $availableHubs->mapWithKeys(fn ($hub) => [
            $hub->id => "$hub->name ($hub->hub_type) - $hub->deployment_status",
        ])->toArray();

        $selectedIds = $this->choice(
            '🎯 Select hubs to deploy (comma-separated for multiple)',
            $choices,
            null,
            null,
            true
        );

        if (is_string($selectedIds)) {
            $selectedIds = [$selectedIds];
        }

        return $availableHubs->whereIn('id', $selectedIds);
    }

    private function deployHubs($hubs, HubOrchestrationService $orchestrationService, DeploymentService $deploymentService)
    {
        if ($this->option('dry-run')) {
            $this->info('🧪 DRY RUN: Would deploy the following hubs:');
            foreach ($hubs as $hub) {
                $this->line("  • $hub->name ($hub->hub_type)");
            }

            return;
        }

        $bar = $this->output->createProgressBar($hubs->count());
        $bar->setFormat('🔄 [%bar%] %current%/%max% %message%');
        $bar->start();

        $results = [
            'successful' => [],
            'failed' => [],
        ];

        foreach ($hubs as $hub) {
            $bar->setMessage("Deploying $hub->name...");

            try {
                $this->line(''); // New line for cleaner output
                $this->info("🚀 Deploying $hub->name ($hub->hub_type hub)...");

                // Use orchestration service for type-specific deployment
                $success = $orchestrationService->deployHub($hub);

                if ($success) {
                    $this->info("✅ Successfully deployed $hub->name");
                    $results['successful'][] = $hub;
                } else {
                    $this->error("❌ Failed to deploy $hub->name");
                    $results['failed'][] = $hub;
                }

            } catch (\Exception $e) {
                $this->error("❌ Error deploying $hub->name: $e->getMessage()");
                $results['failed'][] = $hub;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line(''); // New line after progress bar

        // Summary
        $this->info('📊 Deployment Summary:');
        $this->info('✅ Successful: '.count($results['successful']));
        $this->info('❌ Failed: '.count($results['failed']));

        if (! empty($results['successful'])) {
            $this->info('✅ Successfully deployed hubs:');
            foreach ($results['successful'] as $hub) {
                $this->line("  • $hub->name ($hub->hub_type)");
            }
        }

        if (! empty($results['failed'])) {
            $this->error('❌ Failed deployments:');
            foreach ($results['failed'] as $hub) {
                $this->line("  • $hub->name ($hub->hub_type)");
            }
        }

        // Post-deployment verification
        if (! empty($results['successful']) && $this->confirm('🔍 Run post-deployment verification?')) {
            $this->runPostDeploymentVerification($results['successful'], $deploymentService);
        }
    }

    private function runPostDeploymentVerification(array $hubs, DeploymentService $deploymentService)
    {
        $this->info('🔍 Running post-deployment verification...');

        foreach ($hubs as $hub) {
            $this->line("🔍 Verifying $hub->name...");

            try {
                $status = $deploymentService->getDeploymentStatus($hub);

                $this->table(
                    ['Property', 'Status'],
                    [
                        ['Interface', $status['interface_status']],
                        ['Service', $status['service_status']],
                        ['Peer Count', $status['peer_count']],
                        ['Last Handshake', $status['last_handshake'] ?? 'N/A'],
                    ]
                );

                if (! empty($status['errors'])) {
                    $this->warn('⚠️ Verification warnings:');
                    foreach ($status['errors'] as $error) {
                        $this->line("  • $error");
                    }
                }

                if ($status['interface_status'] === 'up' && $status['service_status'] === 'active') {
                    $this->info("✅ $hub->name is running correctly");
                } else {
                    $this->warn("⚠️ $hub->name may have issues");
                }

            } catch (\Exception $e) {
                $this->error("❌ Failed to verify $hub->name: $e->getMessage()");
            }

            $this->line(''); // Separator between hubs
        }
    }
}
