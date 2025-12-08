<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * Sync BinaryLane Servers Command
 *
 * Synchronizes BinaryLane servers to VNodes and SSH hosts
 */
class BlSyncCommand extends Command
{
    protected $signature = 'bl:sync
                            {--vsite= : VSite name to sync to (creates default if not exists)}
                            {--dry-run : Show what would be synced without making changes}
                            {--prune : Mark VNodes as inactive if server no longer exists}';

    protected $description = 'Sync BinaryLane servers to VNodes and SSH hosts';

    public function handle(BinaryLaneService $service): int
    {
        $vsiteName = $this->option('vsite');
        $dryRun = $this->option('dry-run');
        $prune = $this->option('prune');

        // Get token
        $token = $this->resolveToken($vsiteName);
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);

        $this->info('Fetching BinaryLane servers...');

        try {
            $servers = $service->listServers();

            if ($servers->isEmpty()) {
                $this->warn('No servers found on BinaryLane.');

                return Command::SUCCESS;
            }

            $this->info("Found {$servers->count()} servers.");
            $this->newLine();

            // Find or create VSite
            $vsite = $this->resolveVsite($vsiteName, $dryRun);
            if (! $vsite && ! $dryRun) {
                return Command::FAILURE;
            }

            $stats = [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'ssh_created' => 0,
            ];

            foreach ($servers as $server) {
                $this->processServer($server, $vsite, $dryRun, $stats);
            }

            // Prune deleted servers
            if ($prune && $vsite) {
                $this->pruneDeletedServers($servers, $vsite, $dryRun, $stats);
            }

            // Summary
            $this->newLine();
            $total = $stats['created'] + $stats['updated'];
            $this->info("Synced {$total} servers from BinaryLane");
            $this->line("  Created: {$stats['created']} VNodes");
            $this->line("  Updated: {$stats['updated']} VNodes");
            $this->line("  Skipped: {$stats['skipped']} servers");
            $this->line("  SSH Hosts Created: {$stats['ssh_created']}");

            if ($dryRun) {
                $this->newLine();
                $this->warn('DRY RUN - No changes were made');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function processServer(array $server, ?FleetVsite $vsite, bool $dryRun, array &$stats): void
    {
        $this->line("Processing: {$server['name']} ({$server['id']})");

        // Skip servers without IP
        if (! $server['ipv4']) {
            $this->line('  <fg=yellow>Skipped: No IPv4 address yet</>');
            $stats['skipped']++;

            return;
        }

        // Check if VNode exists by BinaryLane server ID
        $vnode = FleetVnode::where('bl_server_id', $server['id'])->first();

        if ($vnode) {
            // Update existing VNode
            if ($dryRun) {
                $this->line("  <fg=blue>Would update VNode: {$vnode->name}</>");
            } else {
                $vnode->update([
                    'ip_address' => $server['ipv4'],
                    'fqdn' => $server['name'],
                    'bl_size_slug' => $server['size_slug'],
                    'bl_region' => $server['region_slug'],
                    'bl_image' => $server['image_slug'],
                    'bl_synced_at' => now(),
                    'memory_mb' => $server['memory_mb'],
                    'cpu_cores' => $server['vcpus'],
                    'disk_gb' => $server['disk_gb'],
                    'status' => $server['status'] === 'active' ? 'active' : 'inactive',
                    'last_discovered_at' => now(),
                ]);

                // Update SSH host IP if changed
                if ($vnode->sshHost && $vnode->sshHost->hostname !== $server['ipv4']) {
                    $vnode->sshHost->update(['hostname' => $server['ipv4']]);
                    $this->line("  <fg=cyan>Updated SSH host IP: {$server['ipv4']}</>");
                }

                $this->line("  <fg=green>Updated VNode: {$vnode->name}</>");
            }
            $stats['updated']++;

            return;
        }

        // Check if VNode exists by name (use full FQDN to avoid slug conflicts)
        $vnodeName = $server['name'];  // Full FQDN as name
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if ($vnode && ! $vnode->bl_server_id) {
            // Link existing VNode to BinaryLane server
            if ($dryRun) {
                $this->line("  <fg=blue>Would link existing VNode: {$vnode->name}</>");
            } else {
                $vnode->update([
                    'ip_address' => $server['ipv4'],
                    'fqdn' => $server['name'],
                    'bl_server_id' => $server['id'],
                    'bl_size_slug' => $server['size_slug'],
                    'bl_region' => $server['region_slug'],
                    'bl_image' => $server['image_slug'],
                    'bl_synced_at' => now(),
                    'memory_mb' => $server['memory_mb'],
                    'cpu_cores' => $server['vcpus'],
                    'disk_gb' => $server['disk_gb'],
                    'status' => $server['status'] === 'active' ? 'active' : 'inactive',
                    'last_discovered_at' => now(),
                ]);
                $this->line("  <fg=green>Linked VNode: {$vnode->name}</>");
            }
            $stats['updated']++;

            return;
        }

        // Create new VNode and SSH host
        if ($dryRun) {
            $this->line("  <fg=blue>Would create VNode: {$vnodeName}</>");
            $this->line("  <fg=blue>Would create SSH Host: {$server['ipv4']}</>");
            $stats['created']++;
            $stats['ssh_created']++;

            return;
        }

        // Create SSH host
        $sshHostName = str_replace('.', '-', $server['name']);
        $sshHost = SshHost::firstOrCreate(
            ['host' => $sshHostName],
            [
                'hostname' => $server['ipv4'],
                'user' => config('fleet.binarylane.default_ssh_user', 'root'),
                'port' => config('fleet.binarylane.default_ssh_port', 22),
                'is_active' => true,
            ]
        );

        if ($sshHost->wasRecentlyCreated) {
            $this->line("  <fg=cyan>Created SSH Host: {$sshHost->host}</>");
            $stats['ssh_created']++;
        }

        // Create VNode
        $vnode = FleetVnode::create([
            'name' => $vnodeName,
            'slug' => str($vnodeName)->slug(),
            'fqdn' => $server['name'],
            'vsite_id' => $vsite->id,
            'ssh_host_id' => $sshHost->id,
            'ip_address' => $server['ipv4'],
            'role' => 'compute',
            'environment' => 'production',
            'discovery_method' => 'api',
            'bl_server_id' => $server['id'],
            'bl_size_slug' => $server['size_slug'],
            'bl_region' => $server['region_slug'],
            'bl_image' => $server['image_slug'],
            'bl_synced_at' => now(),
            'memory_mb' => $server['memory_mb'],
            'cpu_cores' => $server['vcpus'],
            'disk_gb' => $server['disk_gb'],
            'status' => $server['status'] === 'active' ? 'active' : 'inactive',
            'is_active' => true,
            'last_discovered_at' => now(),
        ]);

        $this->line("  <fg=green>Created VNode: {$vnode->name}</>");
        $stats['created']++;
    }

    protected function pruneDeletedServers($servers, FleetVsite $vsite, bool $dryRun, array &$stats): void
    {
        $serverIds = $servers->pluck('id')->toArray();

        $orphanedVnodes = FleetVnode::where('vsite_id', $vsite->id)
            ->whereNotNull('bl_server_id')
            ->whereNotIn('bl_server_id', $serverIds)
            ->get();

        foreach ($orphanedVnodes as $vnode) {
            if ($dryRun) {
                $this->line("<fg=yellow>Would mark as inactive: {$vnode->name} (server {$vnode->bl_server_id} deleted)</>");
            } else {
                $vnode->update([
                    'status' => 'deleted',
                    'is_active' => false,
                ]);
                $this->line("<fg=yellow>Marked as inactive: {$vnode->name} (server deleted)</>");
            }
        }
    }

    protected function resolveVsite(?string $vsiteName, bool $dryRun): ?FleetVsite
    {
        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)->first();
            if (! $vsite) {
                $this->error("VSite not found: {$vsiteName}");

                return null;
            }

            return $vsite;
        }

        // Find default BinaryLane VSite
        $vsite = FleetVsite::where('provider', 'binarylane')->first();

        if ($vsite) {
            return $vsite;
        }

        // Create default VSite
        if ($dryRun) {
            $this->line('<fg=blue>Would create default BinaryLane VSite</>');

            return null;
        }

        $vsite = FleetVsite::create([
            'name' => 'BinaryLane Sydney',
            'slug' => 'binarylane-sydney',
            'provider' => 'binarylane',
            'technology' => 'vps',
            'location' => 'sydney',
            'api_endpoint' => 'https://api.binarylane.com.au/v2',
            'capabilities' => ['compute', 'networking', 'api_managed'],
        ]);

        $this->info("Created default VSite: {$vsite->name}");

        return $vsite;
    }

    protected function resolveToken(?string $vsiteName): ?string
    {
        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)
                ->where('provider', 'binarylane')
                ->first();

            if ($vsite) {
                $credentials = $vsite->api_credentials;

                return $credentials['token'] ?? null;
            }
        }

        $token = config('fleet.binarylane.api_token');
        if ($token) {
            return $token;
        }

        $this->error('No BinaryLane API token configured.');
        $this->info('Set BINARYLANE_API_TOKEN in .env or use --vsite option');

        return null;
    }
}
