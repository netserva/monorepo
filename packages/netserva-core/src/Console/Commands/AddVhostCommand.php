<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Support\Facades\Artisan;
use NetServa\Core\Services\VhostManagementService;

/**
 * Add VHost Command
 *
 * Follows NetServa CRUD pattern: addvhost (not "ns vhost add")
 * Usage: addvhost <vnode> <vhost>
 * Example: addvhost markc markc.goldcoast.org
 */
class AddVhostCommand extends BaseNetServaCommand
{
    protected $signature = 'addvhost
        {vnode : SSH host/VNode identifier}
        {vhost : Domain name to add}
        {--skip-dns : Skip DNS record creation}
        {--dns-provider= : DNS provider to use (default: homelab)}
        {--dry-run : Show what would be done}';

    protected $description = 'Add a new virtual host (NetServa CRUD pattern)';

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
            $this->line("🚀 Adding VHost: <fg=yellow>{$VHOST}</> on node <fg=cyan>{$VNODE}</>");

            if ($this->option('dry-run')) {
                $this->dryRun("Add VHost {$VHOST} on {$VNODE}", [
                    'Check/create DNS records (A + PTR) for FCrDNS',
                    "Generate VHost configuration for {$VHOST}",
                    'Create fleet_vhosts database record',
                    'Store ~54 config variables in vconfs table (database-first)',
                    "Execute single heredoc SSH script to {$VNODE}",
                    'Create user u1001+, directories, permissions on remote',
                    'Configure PHP-FPM pool, nginx, database on remote',
                    'Set permissions and restart services',
                ]);

                return 0;
            }

            // STEP 1: Create DNS records FIRST (required for hostname -f to work)
            if (! $this->option('skip-dns')) {
                $dnsResult = $this->createDnsRecords($VNODE, $VHOST);

                if (! $dnsResult['success']) {
                    $this->error('❌ DNS record creation failed - cannot proceed with vhost creation');
                    $this->line('');
                    $this->warn('⚠️  FCrDNS (Forward-Confirmed Reverse DNS) is required for hostname -f to work');
                    $this->line('');
                    $this->line('💡 To fix this issue:');
                    $this->line("   1. Manually create DNS records: addrec A {$VHOST} <zone> <ip> --auto-ptr");
                    $this->line("   2. Or skip DNS and create manually: addvhost {$VNODE} {$VHOST} --skip-dns");
                    $this->line('');

                    return 1;
                }
            }

            // STEP 2: Create the VHost (DNS must exist first)
            $result = $this->vhostService->createVhost($VNODE, $VHOST);

            if ($result['success']) {
                $this->info("✅ VHost {$VHOST} created successfully on {$VNODE}");

                // Show key NetServa information
                if (isset($result['username']) && isset($result['uid'])) {
                    $this->line('');
                    $this->line('<fg=blue>📋 VHost Details:</>');
                    $this->line("   User: <fg=yellow>{$result['username']}</> (UID: {$result['uid']})");
                    if (isset($result['paths']['wpath'])) {
                        $this->line("   Web Path: <fg=yellow>{$result['paths']['wpath']}</>");
                    }
                    if (isset($result['fleet_vhost_id'])) {
                        $this->line("   Database ID: <fg=yellow>{$result['fleet_vhost_id']}</>");
                    }
                    $this->line('   Config: <fg=green>vconfs table</> (database-first)');
                }

                // Add to command history
                $this->context->addToHistory("addvhost {$VHOST}", [
                    'VNODE' => $VNODE,
                    'VHOST' => $VHOST,
                    'success' => true,
                ]);

                return 0;
            } else {
                $this->error("❌ Failed to create VHost {$VHOST} on {$VNODE}");
                if (isset($result['error'])) {
                    $this->line("   Error: {$result['error']}");
                }

                return 1;
            }
        });
    }

    /**
     * Create DNS records (A + PTR) for FCrDNS
     * Checks if records already exist before creating
     */
    protected function createDnsRecords(string $VNODE, string $VHOST): array
    {
        $this->line('');
        $this->line('<fg=blue>🌐 Checking DNS records for FCrDNS...</>');

        // Get vnode information (IP address)
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

        // Extract domain from VHOST (e.g., "wp.goldcoast.org" → "goldcoast.org")
        $parts = explode('.', $VHOST);
        $domain = implode('.', array_slice($parts, -2)); // Last 2 parts
        $subdomain = implode('.', array_slice($parts, 0, -2)) ?: '@';

        $this->line("   Domain: <fg=cyan>{$domain}</>");
        $this->line("   Subdomain: <fg=cyan>{$subdomain}</>");
        $this->line("   IP: <fg=cyan>{$ipAddress}</>");
        $this->line("   Provider: <fg=cyan>{$provider}</>");

        // Check if A record already exists
        $existingARecord = \NetServa\Dns\Models\DnsRecord::whereHas('zone', function ($q) use ($domain, $provider) {
            $q->where('name', $domain)->where('provider', $provider);
        })
            ->where('type', 'A')
            ->where('name', $subdomain === '@' ? $domain : $subdomain.'.'.$domain)
            ->where('content', $ipAddress)
            ->first();

        if ($existingARecord) {
            $this->line("   <fg=green>✓</> A record already exists (ID: {$existingARecord->id})");

            return [
                'success' => true,
                'message' => 'DNS records already exist',
                'existing' => true,
            ];
        }

        // Create A record with auto-PTR
        $this->line('   <fg=yellow>→</> Creating A record with auto-PTR...');

        $exitCode = Artisan::call('addrec', [
            'type' => 'A',
            'name' => $subdomain,
            'zone' => $domain,
            'content' => $ipAddress,
            '--auto-ptr' => true,
            '--auto-create-ptr-zone' => true,
        ], $this->output);

        if ($exitCode !== 0) {
            return [
                'success' => false,
                'message' => 'Failed to create DNS records via addrec command',
            ];
        }

        $this->line('   <fg=green>✓</> DNS records created successfully (FCrDNS enabled)');

        return [
            'success' => true,
            'message' => 'DNS records created',
            'created' => true,
        ];
    }
}
