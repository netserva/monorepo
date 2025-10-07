<?php

namespace NetServa\Wg\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use NetServa\Wg\Models\WireguardHub;
use NetServa\Wg\Models\WireguardSpoke;
use NetServa\Wg\Services\DeploymentService;

class RotateKeysCommand extends Command
{
    protected $signature = 'wireguard:rotate-keys
                          --hub= : Rotate keys for specific hub (ID or name)
                          --spoke= : Rotate keys for specific spoke (ID or name)
                          --all-hubs : Rotate keys for all hubs
                          --all-spokes : Rotate keys for all spokes
                          --older-than= : Rotate keys older than X days
                          --dry-run : Show what would be rotated without actually rotating
                          --force : Skip confirmation prompts
                          --backup : Create backup of old keys';

    protected $description = 'Rotate WireGuard cryptographic keys for enhanced security';

    public function handle(DeploymentService $deploymentService): int
    {
        $this->info('🔄 WireGuard Key Rotation Tool');

        if ($this->option('dry-run')) {
            $this->warn('🧪 DRY RUN MODE - No actual key rotation will be performed');
        }

        // Determine what to rotate
        $rotationPlan = $this->buildRotationPlan();

        if (empty($rotationPlan['hubs']) && empty($rotationPlan['spokes'])) {
            $this->info('ℹ️ No keys found for rotation');

            return 0;
        }

        // Display rotation plan
        $this->displayRotationPlan($rotationPlan);

        if (! $this->option('dry-run')) {
            if (! $this->option('force') && ! $this->confirm('🔄 Proceed with key rotation?')) {
                $this->info('⏹️ Key rotation cancelled');

                return 0;
            }

            // Perform rotation
            $this->performKeyRotation($rotationPlan, $deploymentService);
        }

        return 0;
    }

    private function buildRotationPlan(): array
    {
        $plan = ['hubs' => [], 'spokes' => []];
        $olderThanDays = $this->option('older-than');

        // Hub rotation
        if ($this->option('all-hubs')) {
            $hubs = WireguardHub::all();
        } elseif ($hubIdentifier = $this->option('hub')) {
            $hub = is_numeric($hubIdentifier)
                ? WireguardHub::find($hubIdentifier)
                : WireguardHub::where('name', $hubIdentifier)->first();
            $hubs = $hub ? collect([$hub]) : collect();
        } else {
            $hubs = collect();
        }

        foreach ($hubs as $hub) {
            if ($this->shouldRotateKeys($hub, $olderThanDays)) {
                $plan['hubs'][] = $hub;
            }
        }

        // Spoke rotation
        if ($this->option('all-spokes')) {
            $spokes = WireguardSpoke::all();
        } elseif ($spokeIdentifier = $this->option('spoke')) {
            $spoke = is_numeric($spokeIdentifier)
                ? WireguardSpoke::find($spokeIdentifier)
                : WireguardSpoke::where('name', $spokeIdentifier)->first();
            $spokes = $spoke ? collect([$spoke]) : collect();
        } else {
            $spokes = collect();
        }

        foreach ($spokes as $spoke) {
            if ($this->shouldRotateKeys($spoke, $olderThanDays)) {
                $plan['spokes'][] = $spoke;
            }
        }

        return $plan;
    }

    private function shouldRotateKeys($entity, ?string $olderThanDays): bool
    {
        if (! $olderThanDays) {
            return true; // Rotate all if no age filter
        }

        $cutoffDate = now()->subDays((int) $olderThanDays);
        $lastRotated = $entity->keys_rotated_at ?? $entity->created_at;

        return $lastRotated->lt($cutoffDate);
    }

    private function displayRotationPlan(array $plan): void
    {
        $this->info('📋 Key Rotation Plan:');

        if (! empty($plan['hubs'])) {
            $this->info('🏢 Hubs to rotate ('.count($plan['hubs']).'):');
            $this->table(
                ['ID', 'Name', 'Type', 'Status', 'Last Rotated', 'Spokes Count'],
                collect($plan['hubs'])->map(fn ($hub) => [
                    $hub->id,
                    $hub->name,
                    $hub->hub_type,
                    $hub->status,
                    $hub->keys_rotated_at?->format('Y-m-d H:i') ?? 'Never',
                    $hub->spokes()->count(),
                ])->toArray()
            );
        }

        if (! empty($plan['spokes'])) {
            $this->info('📱 Spokes to rotate ('.count($plan['spokes']).'):');
            $this->table(
                ['ID', 'Name', 'Hub', 'Status', 'Last Rotated', 'SSH Host'],
                collect($plan['spokes'])->map(fn ($spoke) => [
                    $spoke->id,
                    $spoke->name,
                    $spoke->hub->name,
                    $spoke->status,
                    $spoke->keys_rotated_at?->format('Y-m-d H:i') ?? 'Never',
                    $spoke->sshHost?->hostname ?? 'Not configured',
                ])->toArray()
            );
        }

        $totalCount = count($plan['hubs']) + count($plan['spokes']);
        $this->info("📊 Total entities for key rotation: $totalCount");
    }

    private function performKeyRotation(array $plan, DeploymentService $deploymentService): void
    {
        $totalItems = count($plan['hubs']) + count($plan['spokes']);
        $bar = $this->output->createProgressBar($totalItems);
        $bar->setFormat('🔄 [%bar%] %current%/%max% %message%');
        $bar->start();

        $results = [
            'successful' => [],
            'failed' => [],
        ];

        // Rotate hub keys
        foreach ($plan['hubs'] as $hub) {
            $bar->setMessage("Rotating hub keys: $hub->name");

            try {
                $this->rotateHubKeys($hub, $deploymentService);
                $results['successful'][] = "Hub: $hub->name";
            } catch (\Exception $e) {
                $this->error("❌ Failed to rotate keys for hub $hub->name: $e->getMessage()");
                $results['failed'][] = "Hub: $hub->name - $e->getMessage()";
            }

            $bar->advance();
        }

        // Rotate spoke keys
        foreach ($plan['spokes'] as $spoke) {
            $bar->setMessage("Rotating spoke keys: $spoke->name");

            try {
                $this->rotateSpokeKeys($spoke, $deploymentService);
                $results['successful'][] = "Spoke: $spoke->name";
            } catch (\Exception $e) {
                $this->error("❌ Failed to rotate keys for spoke $spoke->name: $e->getMessage()");
                $results['failed'][] = "Spoke: $spoke->name - $e->getMessage()";
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line(''); // New line after progress bar

        // Display results
        $this->displayRotationResults($results);
    }

    private function rotateHubKeys(WireguardHub $hub, DeploymentService $deploymentService): void
    {
        DB::transaction(function () use ($hub, $deploymentService) {
            // Backup old keys if requested
            if ($this->option('backup')) {
                $this->backupKeys($hub);
            }

            // Generate new key pair
            $keyPair = WireguardHub::generateKeyPair();

            // Update hub with new keys
            $hub->update([
                'public_key' => $keyPair['public'],
                'private_key' => encrypt($keyPair['private']),
                'keys_rotated_at' => now(),
            ]);

            // Update all spokes to use new hub public key
            $spokes = $hub->spokes()->where('status', 'active')->get();

            foreach ($spokes as $spoke) {
                // Redeploy spoke configuration with new hub public key
                $deploymentService->deploySpoke($spoke);
            }

            // Redeploy hub configuration
            $deploymentService->updateHubConfiguration($hub);

            Log::info("Rotated keys for hub: $hub->name");
        });
    }

    private function rotateSpokeKeys(WireguardSpoke $spoke, DeploymentService $deploymentService): void
    {
        DB::transaction(function () use ($spoke, $deploymentService) {
            // Backup old keys if requested
            if ($this->option('backup')) {
                $this->backupKeys($spoke);
            }

            // Generate new key pair
            $keyPair = WireguardSpoke::generateKeyPair();

            // Update spoke with new keys
            $spoke->update([
                'public_key' => $keyPair['public'],
                'private_key' => encrypt($keyPair['private']),
                'keys_rotated_at' => now(),
            ]);

            // Redeploy spoke configuration
            $deploymentService->deploySpoke($spoke);

            // Update hub configuration to include new spoke public key
            $deploymentService->updateHubConfiguration($spoke->hub);

            Log::info("Rotated keys for spoke: $spoke->name");
        });
    }

    private function backupKeys($entity): void
    {
        $backupData = [
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id,
            'entity_name' => $entity->name,
            'old_public_key' => $entity->public_key,
            'old_private_key' => $entity->private_key, // Already encrypted
            'backup_created_at' => now()->toISOString(),
        ];

        $backupFilename = sprintf(
            'wireguard-key-backup-%s-%d-%s.json',
            strtolower(class_basename($entity)),
            $entity->id,
            now()->format('Y-m-d-H-i-s')
        );

        $backupPath = storage_path("app/wireguard-backups/$backupFilename");

        // Ensure backup directory exists
        if (! is_dir(dirname($backupPath))) {
            mkdir(dirname($backupPath), 0700, true);
        }

        file_put_contents($backupPath, json_encode($backupData, JSON_PRETTY_PRINT));

        $this->line("💾 Backup created: $backupPath");
    }

    private function displayRotationResults(array $results): void
    {
        $this->info('📊 Key Rotation Results:');
        $this->info('✅ Successful: '.count($results['successful']));
        $this->info('❌ Failed: '.count($results['failed']));

        if (! empty($results['successful'])) {
            $this->info('✅ Successfully rotated keys for:');
            foreach ($results['successful'] as $success) {
                $this->line("  • $success");
            }
        }

        if (! empty($results['failed'])) {
            $this->error('❌ Failed to rotate keys for:');
            foreach ($results['failed'] as $failure) {
                $this->line("  • $failure");
            }
        }

        if (! empty($results['successful'])) {
            $this->warn('⚠️ Important Security Notes:');
            $this->warn('• All clients with old configurations will need to update their configs');
            $this->warn('• Download new client configurations from the admin panel');
            $this->warn('• Test connectivity after key rotation');

            if ($this->option('backup')) {
                $this->info('💾 Key backups are stored in: '.storage_path('app/wireguard-backups/'));
            }
        }
    }
}
