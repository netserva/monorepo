<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use NetServa\Core\Services\VhostManagementService;

/**
 * Delete VHost Command
 *
 * Follows NetServa CRUD pattern: delvhost (not "ns vhost delete")
 * Usage: delvhost <vnode> <vhost>
 * Example: delvhost markc markc.goldcoast.org
 */
class DelvhostCommand extends BaseNetServaCommand
{
    protected $signature = 'delvhost
        {vnode : SSH host/VNode identifier}
        {vhost : Domain name to delete}
        {--skip-dns : Skip DNS record deletion}
        {--dns-provider= : DNS provider to use (default: homelab)}
        {--force : Skip confirmation}
        {--dry-run : Show what would be done}';

    protected $description = 'Delete a virtual host (NetServa CRUD pattern)';

    protected VhostManagementService $vhostService;

    public function __construct(VhostManagementService $vhostService)
    {
        parent::__construct();
        $this->vhostService = $vhostService;
    }

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            // Get required parameters from positional arguments
            $VNODE = $this->argument('vnode');
            $VHOST = $this->argument('vhost');

            // Show what we're about to do
            $this->line("🗑️  Deleting VHost: <fg=yellow>{$VHOST}</> from server <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Delete VHost {$VHOST} from {$VNODE}", [
                    'Load config from vconfs table (database-first)',
                    "SSH to {$VNODE} and execute cleanup via heredoc script",
                    'Remove user, directories, database on remote',
                    'Remove SSL certificate on remote',
                    'Remove nginx, PHP-FPM configuration on remote',
                    'Soft-delete fleet_vhosts record (cascades to vconfs)',
                    'Delete DNS records (A + PTR) after successful deletion',
                ]);

                return 0;
            }

            // Safety confirmation (unless --force)
            if (! $this->option('force')) {
                if (! $this->confirm("⚠️  Are you sure you want to delete VHost {$VHOST}? This cannot be undone.")) {
                    $this->info('🛑 Deletion cancelled');

                    return 0;
                }
            }

            // STEP 1: Delete the VHost first
            $result = $this->vhostService->deleteVhost($VNODE, $VHOST);

            if ($result['success']) {
                $this->info("✅ VHost {$VHOST} deleted successfully from {$VNODE}");

                // STEP 2: Delete DNS records AFTER successful vhost deletion
                if (! $this->option('skip-dns')) {
                    $dnsResult = $this->deleteDnsRecords($VNODE, $VHOST);

                    if (! $dnsResult['success']) {
                        $this->warn('⚠️  VHost deleted but DNS cleanup failed');
                        $this->line("   You may need to manually delete DNS records for {$VHOST}");
                    }
                }

                // Add to command history
                $this->context->addToHistory("delvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to delete VHost {$VHOST} from {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    /**
     * Delete DNS records (A + PTR) after successful vhost deletion
     * Checks if records exist before attempting deletion
     */
    protected function deleteDnsRecords(string $VNODE, string $VHOST): array
    {
        $this->line('');
        $this->line('<fg=blue>🌐 Cleaning up DNS records...</>');

        // Get vnode information
        $vnode = \NetServa\Fleet\Models\FleetVnode::where('name', $VNODE)->first();

        if (! $vnode || ! $vnode->ip_address) {
            return [
                'success' => false,
                'message' => "VNode {$VNODE} not found or missing IP address",
            ];
        }

        $ipAddress = $vnode->ip_address;

        // Hierarchical DNS provider resolution
        // 1. Command-line option (--dns-provider)
        // 2. VNode's dns_provider_id
        // 3. System default (homelab)
        $provider = $this->option('dns-provider')
            ?? $vnode->dnsProvider->name
            ?? 'homelab';

        // Extract domain from VHOST
        $parts = explode('.', $VHOST);
        $domain = implode('.', array_slice($parts, -2));
        $subdomain = implode('.', array_slice($parts, 0, -2)) ?: '@';

        // Find existing A record
        $aRecord = \NetServa\Dns\Models\DnsRecord::whereHas('zone', function ($q) use ($domain, $provider) {
            $q->where('name', $domain)
                ->whereHas('dnsProvider', function ($pq) use ($provider) {
                    $pq->where('name', $provider);
                });
        })
            ->where('type', 'A')
            ->where('name', $subdomain === '@' ? $domain : $subdomain.'.'.$domain)
            ->where('content', $ipAddress)
            ->first();

        if (! $aRecord) {
            $this->line("   <fg=yellow>⚠</> No A record found for {$VHOST}");

            return [
                'success' => true,
                'message' => 'No DNS records to delete',
                'skipped' => true,
            ];
        }

        $this->line("   Found A record (ID: {$aRecord->id})");

        // Delete A record (this should also clean up PTR if it was auto-created)
        $exitCode = Artisan::call('delrec', [
            'record' => $aRecord->id,
            '--force' => true,
        ], $this->output);

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'message' => 'Failed to delete DNS record via delrec command',
            ];
        }

        $this->line('   <fg=green>✓</> DNS records deleted successfully');

        return [
            'success' => true,
            'message' => 'DNS records deleted',
            'deleted' => true,
        ];
    }
}
