<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use NetServa\Fleet\Services\BinaryLaneService;

/**
 * Delete BinaryLane Server Command (NetServa 3.0 CRUD: DELETE)
 *
 * Deletes a BinaryLane VPS server and optionally cleans up VNode/SSH host
 */
class DelblCommand extends Command
{
    protected $signature = 'delbl
                            {server : Server ID or name}
                            {--vsite= : VSite name (must be binarylane provider)}
                            {--force : Skip confirmation}
                            {--keep-vnode : Do not delete associated VNode}
                            {--keep-ssh : Do not delete associated SSH host}';

    protected $description = 'Delete a BinaryLane server (NetServa 3.0 CRUD: Delete)';

    public function handle(BinaryLaneService $service): int
    {
        $serverArg = $this->argument('server');
        $force = $this->option('force');
        $keepVnode = $this->option('keep-vnode');
        $keepSsh = $this->option('keep-ssh');

        // Get token
        $token = $this->resolveToken();
        if (! $token) {
            return Command::FAILURE;
        }

        $service->setToken($token);

        // Resolve server
        try {
            $server = $this->resolveServer($service, $serverArg);
            if (! $server) {
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("Failed to find server: {$e->getMessage()}");

            return Command::FAILURE;
        }

        // Show server info
        $this->warn('Server to be deleted:');
        $this->line("  ID: {$server['id']}");
        $this->line("  Name: {$server['name']}");
        $this->line('  IPv4: '.($server['ipv4'] ?? 'N/A'));
        $this->line("  Size: {$server['size_slug']}");
        $this->line("  Region: {$server['region_slug']}");

        // Find associated VNode
        $vnode = FleetVnode::where('bl_server_id', $server['id'])->first();
        if ($vnode) {
            $this->newLine();
            $this->line("  Associated VNode: {$vnode->name}");
            if ($vnode->sshHost) {
                $this->line("  Associated SSH Host: {$vnode->sshHost->host}");
            }
        }

        // Confirm deletion
        if (! $force) {
            $this->newLine();
            $this->error('THIS ACTION IS IRREVERSIBLE!');
            if (! $this->confirm("Are you sure you want to DELETE server '{$server['name']}' ({$server['id']})?")) {
                $this->info('Operation cancelled.');

                return Command::SUCCESS;
            }
        }

        $this->newLine();
        $this->info("Deleting server: {$server['name']} ({$server['id']})...");

        try {
            $service->deleteServer($server['id']);
            $this->info('Server deleted successfully from BinaryLane.');

            // Clean up VNode and SSH host
            if ($vnode && ! $keepVnode) {
                $sshHost = $vnode->sshHost;

                // Delete VNode
                $vnodeName = $vnode->name;
                $vnode->delete();
                $this->info("  Deleted VNode: {$vnodeName}");

                // Delete SSH host
                if ($sshHost && ! $keepSsh) {
                    $sshHostName = $sshHost->host;
                    $sshHost->delete();
                    $this->info("  Deleted SSH Host: {$sshHostName}");
                }
            } elseif ($vnode && $keepVnode) {
                // Mark VNode as inactive
                $vnode->update([
                    'status' => 'deleted',
                    'is_active' => false,
                    'bl_server_id' => null,
                ]);
                $this->info("  Marked VNode '{$vnode->name}' as inactive (kept)");
            }

            $this->newLine();
            $this->info('Deletion complete.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to delete server: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }

    protected function resolveServer(BinaryLaneService $service, string $serverArg): ?array
    {
        // Try by ID first
        if (is_numeric($serverArg)) {
            return $service->getServer((int) $serverArg);
        }

        // Search by name
        $servers = $service->listServers();
        $server = $servers->firstWhere('name', $serverArg);

        if (! $server) {
            $this->error("Server not found: {$serverArg}");
            $this->info('Use shbl to list available servers.');

            return null;
        }

        return $server;
    }

    protected function resolveToken(): ?string
    {
        $vsiteName = $this->option('vsite');

        if ($vsiteName) {
            $vsite = FleetVsite::where('name', $vsiteName)
                ->where('provider', 'binarylane')
                ->first();

            if (! $vsite) {
                $this->error("VSite not found or not a BinaryLane provider: {$vsiteName}");

                return null;
            }

            $credentials = $vsite->api_credentials;

            return $credentials['token'] ?? null;
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
