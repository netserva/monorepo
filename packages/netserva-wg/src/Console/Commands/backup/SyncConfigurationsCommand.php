<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;
use NetServa\Wg\Services\DeploymentService;
use NetServa\Wg\Services\HubOrchestrationService;

class SyncConfigurationsCommand extends Command
{
    protected $signature = 'wireguard:sync
                          --hub= : Sync specific hub (ID or name)
                          --spoke= : Sync specific spoke (ID or name)
                          --all : Sync all hubs and spokes
                          --check-only : Only check sync status without applying changes
                          --force : Force sync even if configurations appear current
                          --restart : Restart services after sync';

    protected $description = 'Synchronize WireGuard configurations across all nodes';

    public function handle(
        DeploymentService $deploymentService,
        HubOrchestrationService $orchestrationService
    ): int {
        $this->info('🔄 WireGuard Configuration Synchronization');

        if ($this->option('check-only')) {
            return $this->performSyncCheck($deploymentService);
        }

        // Determine what to sync
        $syncPlan = $this->buildSyncPlan();

        if (empty($syncPlan['hubs']) && empty($syncPlan['spokes'])) {
            $this->info('ℹ️ No configurations found for synchronization');

            return 0;
        }

        // Display sync plan
        $this->displaySyncPlan($syncPlan);

        if (! $this->confirm('🔄 Proceed with configuration synchronization?')) {
            $this->info('⏹️ Synchronization cancelled');

            return 0;
        }

        // Perform synchronization
        $this->performSync($syncPlan, $deploymentService, $orchestrationService);

        return 0;
    }

    private function buildSyncPlan(): array
    {
        $plan = ['hubs' => [], 'spokes' => []];

        if ($this->option('all')) {
            $plan['hubs'] = WireguardHub::where('status', 'active')->get()->toArray();
            $plan['spokes'] = WireguardSpoke::where('status', 'active')->get()->toArray();
        } else {
            if ($hubIdentifier = $this->option('hub')) {
                $hub = is_numeric($hubIdentifier)
                    ? WireguardHub::find($hubIdentifier)
                    : WireguardHub::where('name', $hubIdentifier)->first();

                if ($hub) {
                    $plan['hubs'][] = $hub;
                }
            }

            if ($spokeIdentifier = $this->option('spoke')) {
                $spoke = is_numeric($spokeIdentifier)
                    ? WireguardSpoke::find($spokeIdentifier)
                    : WireguardSpoke::where('name', $spokeIdentifier)->first();

                if ($spoke) {
                    $plan['spokes'][] = $spoke;
                }
            }
        }

        return $plan;
    }

    private function displaySyncPlan(array $plan): void
    {
        $this->info('📋 Configuration Sync Plan:');

        if (! empty($plan['hubs'])) {
            $this->info('🏢 Hubs to sync ('.count($plan['hubs']).'):');
            $this->table(
                ['ID', 'Name', 'Type', 'Status', 'Last Deployed', 'SSH Host'],
                collect($plan['hubs'])->map(fn ($hub) => [
                    $hub->id,
                    $hub->name,
                    $hub->hub_type,
                    $hub->deployment_status,
                    $hub->last_deployed_at?->format('Y-m-d H:i') ?? 'Never',
                    $hub->sshHost?->hostname ?? 'Not configured',
                ])->toArray()
            );
        }

        if (! empty($plan['spokes'])) {
            $this->info('📱 Spokes to sync ('.count($plan['spokes']).'):');
            $this->table(
                ['ID', 'Name', 'Hub', 'Status', 'Last Deployed', 'SSH Host'],
                collect($plan['spokes'])->map(fn ($spoke) => [
                    $spoke->id,
                    $spoke->name,
                    $spoke->hub->name,
                    $spoke->deployment_status,
                    $spoke->last_deployed_at?->format('Y-m-d H:i') ?? 'Never',
                    $spoke->sshHost?->hostname ?? 'Not configured',
                ])->toArray()
            );
        }
    }

    private function performSyncCheck(DeploymentService $deploymentService): int
    {
        $this->info('🔍 Checking synchronization status...');

        $hubs = WireguardHub::where('status', 'active')->get();
        $spokes = WireguardSpoke::where('status', 'active')->get();

        $outOfSync = ['hubs' => [], 'spokes' => []];

        // Check hubs
        foreach ($hubs as $hub) {
            $status = $this->checkHubSyncStatus($hub, $deploymentService);
            if (! $status['in_sync']) {
                $outOfSync['hubs'][] = [
                    'entity' => $hub,
                    'issues' => $status['issues'],
                ];
            }
        }

        // Check spokes
        foreach ($spokes as $spoke) {
            $status = $this->checkSpokeSyncStatus($spoke, $deploymentService);
            if (! $status['in_sync']) {
                $outOfSync['spokes'][] = [
                    'entity' => $spoke,
                    'issues' => $status['issues'],
                ];
            }
        }

        // Display results
        $this->displaySyncStatus($outOfSync);

        return empty($outOfSync['hubs']) && empty($outOfSync['spokes']) ? 0 : 1;
    }

    private function checkHubSyncStatus(WireguardHub $hub, DeploymentService $deploymentService): array
    {
        $issues = [];

        try {
            $status = $deploymentService->getDeploymentStatus($hub);

            if ($status['interface_status'] !== 'up') {
                $issues[] = 'Interface is down';
            }

            if ($status['service_status'] !== 'active') {
                $issues[] = 'WireGuard service is not active';
            }

            $expectedPeerCount = $hub->spokes()->where('status', 'active')->count();
            if ($status['peer_count'] !== $expectedPeerCount) {
                $issues[] = 'Peer count mismatch (expected: '.$expectedPeerCount.', actual: '.$status['peer_count'].')';
            }

            if (! empty($status['errors'])) {
                $issues = array_merge($issues, $status['errors']);
            }

        } catch (\Exception $e) {
            $issues[] = "Status check failed: $e->getMessage()";
        }

        return [
            'in_sync' => empty($issues),
            'issues' => $issues,
        ];
    }

    private function checkSpokeSyncStatus(WireguardSpoke $spoke, DeploymentService $deploymentService): array
    {
        $issues = [];

        if (! $spoke->ssh_host_id) {
            return ['in_sync' => true, 'issues' => []]; // Can't check remote-only spokes
        }

        try {
            // This would require implementing spoke status checking
            // For now, we'll check basic deployment status
            if ($spoke->deployment_status !== 'deployed') {
                $issues[] = 'Deployment status is not deployed';
            }

            if (! $spoke->last_deployed_at) {
                $issues[] = 'Never deployed';
            }

        } catch (\Exception $e) {
            $issues[] = "Status check failed: $e->getMessage()";
        }

        return [
            'in_sync' => empty($issues),
            'issues' => $issues,
        ];
    }

    private function displaySyncStatus(array $outOfSync): void
    {
        if (empty($outOfSync['hubs']) && empty($outOfSync['spokes'])) {
            $this->info('✅ All configurations are in sync');

            return;
        }

        $this->warn('⚠️ Configurations out of sync:');

        if (! empty($outOfSync['hubs'])) {
            $this->warn('🏢 Hubs with sync issues:');
            foreach ($outOfSync['hubs'] as $item) {
                $hub = $item['entity'];
                $this->line("  • $hub->name ($hub->hub_type):");
                foreach ($item['issues'] as $issue) {
                    $this->line("    - $issue");
                }
            }
        }

        if (! empty($outOfSync['spokes'])) {
            $this->warn('📱 Spokes with sync issues:');
            foreach ($outOfSync['spokes'] as $item) {
                $spoke = $item['entity'];
                $this->line("  • $spoke->name (Hub: $spoke->hub->name):");
                foreach ($item['issues'] as $issue) {
                    $this->line("    - $issue");
                }
            }
        }
    }

    private function performSync(array $plan, DeploymentService $deploymentService, HubOrchestrationService $orchestrationService): void
    {
        $totalItems = count($plan['hubs']) + count($plan['spokes']);
        $bar = $this->output->createProgressBar($totalItems);
        $bar->setFormat('🔄 [%bar%] %current%/%max% %message%');
        $bar->start();

        $results = [
            'successful' => [],
            'failed' => [],
        ];

        // Sync hubs first (they're dependencies for spokes)
        foreach ($plan['hubs'] as $hub) {
            $bar->setMessage("Syncing hub: $hub->name");

            try {
                $success = $orchestrationService->deployHub($hub);

                if ($success) {
                    $results['successful'][] = "Hub: $hub->name";

                    if ($this->option('restart')) {
                        $this->restartWireGuardService($hub, $deploymentService);
                    }
                } else {
                    $results['failed'][] = "Hub: $hub->name - Deployment failed";
                }

            } catch (\Exception $e) {
                $this->error("❌ Failed to sync hub $hub->name: $e->getMessage()");
                $results['failed'][] = "Hub: $hub->name - $e->getMessage()";
            }

            $bar->advance();
        }

        // Sync spokes
        foreach ($plan['spokes'] as $spoke) {
            $bar->setMessage("Syncing spoke: $spoke->name");

            try {
                $success = $deploymentService->deploySpoke($spoke);

                if ($success) {
                    $results['successful'][] = "Spoke: $spoke->name";

                    if ($this->option('restart')) {
                        $this->restartWireGuardService($spoke, $deploymentService);
                    }
                } else {
                    $results['failed'][] = "Spoke: $spoke->name - Deployment failed";
                }

            } catch (\Exception $e) {
                $this->error("❌ Failed to sync spoke $spoke->name: $e->getMessage()");
                $results['failed'][] = "Spoke: $spoke->name - $e->getMessage()";
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line(''); // New line after progress bar

        // Display results
        $this->displaySyncResults($results);
    }

    private function restartWireGuardService($entity, DeploymentService $deploymentService): void
    {
        try {
            if (! $entity->ssh_host_id) {
                return; // Can't restart remote-only configurations
            }

            $sshService = app(\NetServa\Core\Services\SshConnectionService::class);
            $connection = $sshService->getConnection($entity->sshHost);

            $sshService->executeCommand($connection, "wg-quick down $entity->interface_name 2>/dev/null || true");
            sleep(1);
            $sshService->executeCommand($connection, "wg-quick up $entity->interface_name");

            $this->line("  🔄 Restarted WireGuard service for $entity->name");

        } catch (\Exception $e) {
            $this->warn("  ⚠️ Failed to restart service for $entity->name: $e->getMessage()");
        }
    }

    private function displaySyncResults(array $results): void
    {
        $this->info('📊 Synchronization Results:');
        $this->info('✅ Successful: '.count($results['successful']));
        $this->info('❌ Failed: '.count($results['failed']));

        if (! empty($results['successful'])) {
            $this->info('✅ Successfully synchronized:');
            foreach ($results['successful'] as $success) {
                $this->line("  • $success");
            }
        }

        if (! empty($results['failed'])) {
            $this->error('❌ Failed to synchronize:');
            foreach ($results['failed'] as $failure) {
                $this->line("  • $failure");
            }
        }

        if (! empty($results['successful'])) {
            $this->info('💡 Next steps:');
            $this->info('• Run `php artisan wireguard:monitor` to verify connections');
            $this->info('• Check logs for any configuration warnings');
            $this->info('• Test connectivity between hubs and spokes');
        }
    }
}
