<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Services\FleetDiscoveryService;

/**
 * Add Fleet Command (NetServa 3.0 CRUD: CREATE)
 *
 * Discovers and registers fleet infrastructure via SSH
 */
class AddfleetCommand extends Command
{
    protected $signature = 'addfleet
                          {vnode? : Discover specific vnode only (optional - discovers all if omitted)}
                          {--all : Discover all vnodes (same as omitting vnode argument)}
                          {--force : Force discovery even if not scheduled}
                          {--test-ssh : Test SSH connections only}
                          {--import-legacy : Import existing NetServa 1.0 vhosts from /srv/}
                          {--fqdn= : Manually set FQDN for the vnode (e.g., server.example.com)}
                          {--auto-dns : Automatically create DNS records if missing (uses PowerDNS API)}
                          {--force-no-dns : Emergency override - skip DNS validation (disables email)}
                          {--verify-dns-only : Only verify DNS without making changes}';

    protected $description = 'Add/discover fleet infrastructure via SSH (NetServa 3.0 CRUD: Create)';

    protected FleetDiscoveryService $discoveryService;

    public function __construct(FleetDiscoveryService $discoveryService)
    {
        parent::__construct();
        $this->discoveryService = $discoveryService;
    }

    public function handle(): int
    {
        $this->info('🔍 Starting NetServa Fleet Discovery');

        if ($this->option('test-ssh')) {
            return $this->testSshConnections();
        }

        $specificVnode = $this->argument('vnode');
        $discoverAll = $this->option('all');
        $force = $this->option('force');

        try {
            // --all flag overrides positional vnode argument
            if ($discoverAll || ! $specificVnode) {
                return $this->discoverAll($force);
            } else {
                return $this->discoverSpecificVNode($specificVnode, $force);
            }
        } catch (\Exception $e) {
            $this->error("Discovery failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Test SSH connections to all VNodes
     */
    protected function testSshConnections(): int
    {
        $this->info('🔌 Testing SSH connections to all VNodes');

        $vnodes = FleetVNode::withSsh()->with(['sshHost', 'vsite'])->get();

        if ($vnodes->isEmpty()) {
            $this->warn('No VNodes with SSH access found');

            return 0;
        }

        $results = [];

        foreach ($vnodes as $vnode) {
            $this->line("Testing {$vnode->name}...");

            $result = $this->discoveryService->testSshConnection($vnode);
            $results[] = [
                'VNode' => $vnode->name,
                'SSH Host' => $vnode->sshHost->hostname ?? 'N/A',
                'Status' => $result['success'] ? '✅ Connected' : '❌ Failed',
                'Error' => $result['error'] ?? '',
            ];

            if ($result['success']) {
                $this->info("  ✅ {$vnode->name}: Connected");
            } else {
                $this->error("  ❌ {$vnode->name}: {$result['error']}");
            }
        }

        $this->newLine();
        $this->table(['VNode', 'SSH Host', 'Status', 'Error'], $results);

        $successful = collect($results)->where('Status', '✅ Connected')->count();
        $total = count($results);

        $this->info("SSH Test Summary: {$successful}/{$total} connections successful");

        return $successful === $total ? 0 : 1;
    }

    /**
     * Discover specific VNode
     */
    protected function discoverSpecificVNode(string $vnodeName, bool $force): int
    {
        $vnode = FleetVNode::where('name', $vnodeName)
            ->with(['sshHost', 'vsite'])
            ->first();

        if (! $vnode) {
            $this->error("VNode not found: {$vnodeName}");

            return 1;
        }

        if (! $force && ! $vnode->isDueForScan()) {
            $nextScan = $vnode->next_scan_at->format('Y-m-d H:i:s');
            $this->warn("VNode {$vnodeName} not due for scan until {$nextScan}. Use --force to override.");

            return 0;
        }

        $this->info("🔍 Discovering VNode: {$vnode->name}");

        // If FQDN is manually specified, set it before discovery
        if ($manualFqdn = $this->option('fqdn')) {
            $vnode->update(['fqdn' => $manualFqdn]);
            $this->info("   FQDN manually set to: {$manualFqdn}");
        }

        // If legacy import is requested, skip normal vhost discovery
        $skipVhostDiscovery = $this->option('import-legacy');

        // Get DNS-related flags
        $forceNoDns = $this->option('force-no-dns');
        $autoDns = $this->option('auto-dns');

        $success = $this->discoveryService->discoverVNode($vnode, $skipVhostDiscovery, $forceNoDns, $autoDns);

        if ($success) {
            $this->info("✅ Discovery successful for {$vnode->name}");

            // Import legacy vhosts if requested
            if ($this->option('import-legacy')) {
                $this->newLine();
                $this->info('📦 Importing legacy NetServa 1.0 vhosts...');

                $importService = app(\NetServa\Cli\Services\LegacyImportService::class);
                $result = $importService->discoverLegacyVhosts($vnode);

                if ($result['success']) {
                    $this->info('✅ Legacy import complete:');
                    $this->line("   • Discovered: {$result['discovered']} vhosts");
                    $this->line("   • Imported: {$result['imported']} vhosts");

                    if ($result['skipped'] > 0) {
                        $this->warn("   • Skipped: {$result['skipped']} vhosts");

                        if (! empty($result['errors'])) {
                            $this->newLine();
                            $this->error('Errors encountered:');
                            foreach ($result['errors'] as $error) {
                                $this->line("   • {$error['domain']}: {$error['error']}");
                            }
                        }
                    }
                } else {
                    $this->error("❌ Legacy import failed: {$result['error']}");
                }
            }

            // Display discovered information
            $vnode->refresh();
            $this->displayVNodeInfo($vnode);

            return 0;
        } else {
            $this->error("❌ Discovery failed for {$vnode->name}: {$vnode->last_error}");

            return 1;
        }
    }

    /**
     * Discover all VNodes
     */
    protected function discoverAll(bool $force): int
    {
        $query = FleetVNode::withSsh()->with(['sshHost', 'vsite']);

        if (! $force) {
            $query->needsScanning();
        }

        $vnodes = $query->get();

        if ($vnodes->isEmpty()) {
            $this->info('No VNodes need discovery at this time');

            return 0;
        }

        $this->info("🔍 Discovering {$vnodes->count()} VNodes");

        $results = $this->discoveryService->discoverInfrastructure();

        $this->newLine();
        $this->info('📊 Discovery Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Processed', $results['processed']],
                ['Successful', $results['successful']],
                ['Failed', $results['failed']],
                ['Errors', count($results['errors'])],
            ]
        );

        if (! empty($results['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($results['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        $this->newLine();
        if ($results['failed'] === 0) {
            $this->info('✅ All discoveries completed successfully!');
        } else {
            $this->warn("⚠️  {$results['failed']} discoveries failed");
        }

        return $results['failed'] === 0 ? 0 : 1;
    }

    /**
     * Display VNode information after discovery
     */
    protected function displayVNodeInfo(FleetVNode $vnode): void
    {
        $this->newLine();
        $this->line('<info>VNode Information:</info>');

        $info = [
            ['Property', 'Value'],
            ['Name', $vnode->name],
            ['FQDN', $vnode->fqdn ?? 'Not set'],
            ['VSite', $vnode->vsite->name],
            ['Role', ucfirst($vnode->role)],
            ['Environment', ucfirst($vnode->environment)],
            ['IP Address', $vnode->ip_address ?? 'Unknown'],
            ['OS', $vnode->operating_system ?? 'Unknown'],
            ['CPU Cores', $vnode->cpu_cores ?? 'Unknown'],
            ['Memory', $vnode->memory_mb ? $vnode->memory_mb.' MB' : 'Unknown'],
            ['Disk', $vnode->disk_gb ? $vnode->disk_gb.' GB' : 'Unknown'],
            ['Status', ucfirst($vnode->status)],
            ['Last Discovery', $vnode->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never'],
            ['Next Scan', $vnode->next_scan_at?->format('Y-m-d H:i:s') ?? 'Not scheduled'],
        ];

        $this->table($info[0], array_slice($info, 1));

        // Show VHosts if any
        $vhosts = $vnode->vhosts()->get();
        if ($vhosts->isNotEmpty()) {
            $this->newLine();
            $this->line("<info>VHosts ({$vhosts->count()}):</info>");

            $vhostInfo = [];
            foreach ($vhosts as $vhost) {
                $vhostInfo[] = [
                    'Domain' => $vhost->domain,
                    'Type' => $vhost->instance_type ?? 'Unknown',
                    'Status' => ucfirst($vhost->status),
                    'Last Discovered' => $vhost->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never',
                ];
            }

            $this->table(['Domain', 'Type', 'Status', 'Last Discovered'], $vhostInfo);
        }
    }
}
