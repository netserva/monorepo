<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Fleet Infrastructure Discovery Command
 *
 * Intelligently classify SSH hosts into VSites, VNodes, and VHosts
 */
class FleetInfraDiscoveryCommand extends Command
{
    protected $signature = 'fleet:infra-discovery
                            {action : Action (analyze|classify|rebuild|check-connectivity)}
                            {--dry-run : Show what would be done without making changes}
                            {--clean : Clean existing incorrect data first}
                            {--limit= : Limit analysis to N hosts for testing}
                            {--host= : Analyze specific host only}
                            {--timeout=5 : SSH connection timeout in seconds}';

    protected $description = 'Intelligently discover and classify infrastructure from SSH hosts';

    protected array $vsitePatterns = [
        'goldcoast-proxmox-datacenter' => [
            'patterns' => ['pve', 'pbs', 'proxmox'],
            'provider' => 'local',
            'technology' => 'proxmox',
            'location' => 'Gold Coast',
            'description' => 'Gold Coast Proxmox Datacenter',
        ],
        'unknown-vsite' => [
            'patterns' => [], // Will catch everything else
            'provider' => 'unknown',
            'technology' => 'unknown',
            'location' => null,
            'description' => 'Unclassified Infrastructure - to be organized manually',
        ],
    ];

    protected array $vnodePatterns = [
        // Proxmox nodes
        'pve', 'pbs', 'proxmox',
        // DNS servers
        'ns1', 'ns2', 'ns3',
        // Infrastructure nodes
        'haproxy', 'monitor', 'pihole',
        // Physical servers
        'kenyon', 'mbs', 'mcc',
    ];

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'analyze' => $this->analyzeSshHosts(),
            'classify' => $this->classifyInfrastructure(),
            'rebuild' => $this->rebuildInfrastructure(),
            'check-connectivity' => $this->checkSshConnectivity(),
            default => $this->showUsage(),
        };
    }

    protected function analyzeSshHosts(): int
    {
        info('Analyzing SSH hosts for infrastructure classification...');

        $query = SshHost::query();

        // Handle specific host analysis
        if ($specificHost = $this->option('host')) {
            $query->where('host', $specificHost);
        }

        // Handle limit for testing
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $sshHosts = $query->get();
        info('Analyzing '.$sshHosts->count().' SSH hosts...');

        $analysis = [
            'potential_vsites' => [],
            'potential_vnodes' => [],
            'potential_vhosts' => [],
            'unclassified' => [],
        ];

        $progress = progress(label: 'Analyzing hosts', steps: $sshHosts->count());
        $progress->start();

        foreach ($sshHosts as $sshHost) {
            $progress->advance();

            $classification = $this->classifySshHost($sshHost);
            $analysis[$classification['type']][] = [
                'ssh_host' => $sshHost,
                'classification' => $classification,
            ];
        }

        $progress->finish();

        // Show VSites analysis
        info("\n=== VSite Patterns Analysis ===");
        foreach ($this->vsitePatterns as $vsiteName => $config) {
            $matches = collect($sshHosts)->filter(function ($host) use ($config) {
                return collect($config['patterns'])->some(fn ($pattern) => str_contains(strtolower($host->host), $pattern)
                );
            });

            if ($matches->isNotEmpty()) {
                info("VSite: {$vsiteName} ({$config['description']})");
                info('  Matches: '.$matches->pluck('host')->join(', '));
                info('  Count: '.$matches->count());
            }
        }

        // Show classification summary
        info("\n=== Classification Summary ===");
        foreach ($analysis as $type => $items) {
            if (! empty($items)) {
                info("{$type}: ".count($items).' hosts');
                if ($type !== 'unclassified') {
                    $examples = collect($items)->take(5)->pluck('ssh_host.host')->join(', ');
                    info("  Examples: {$examples}");
                }
            }
        }

        // Show VHost detection details
        $vhostItems = $analysis['potential_vhosts'] ?? [];
        if (! empty($vhostItems)) {
            info("\n=== VHost Directory Detection ===");
            $vhostTypes = collect($vhostItems)->groupBy(fn ($item) => $item['classification']['vhost_type'] ?? 'unknown');

            foreach ($vhostTypes as $type => $hosts) {
                if ($type && $type !== 'unknown') {
                    info("NetServa {$type}: ".$hosts->count().' hosts');
                    $examples = $hosts->take(3)->map(fn ($item) => $item['ssh_host']->host.' ('.count($item['classification']['vhost_paths'] ?? []).' VHosts)'
                    )->join(', ');
                    info("  Examples: {$examples}");
                }
            }
        }

        // Show unclassified hosts
        if (! empty($analysis['unclassified'])) {
            warning("\nUnclassified hosts need manual review:");
            foreach ($analysis['unclassified'] as $item) {
                $host = $item['ssh_host'];
                $this->line("  {$host->host} -> {$host->hostname}");
            }
        }

        return self::SUCCESS;
    }

    protected function classifySshHost(SshHost $sshHost): array
    {
        $host = strtolower($sshHost->host);
        $hostname = strtolower($sshHost->hostname);

        // Skip non-infrastructure hosts
        if ($this->shouldSkipHost($sshHost)) {
            return ['type' => 'unclassified', 'reason' => 'skip'];
        }

        // Determine VSite first
        $vsite = $this->determineVSite($sshHost);
        if (! $vsite) {
            return ['type' => 'unclassified', 'reason' => 'no_vsite_match'];
        }

        // Check if this host has VHost directories via SSH
        $vhostCheck = $this->checkForVHostDirectories($sshHost);

        if ($vhostCheck['is_vhost']) {
            return [
                'type' => 'potential_vhosts',
                'vsite' => $vsite,
                'domain' => $this->getCanonicalFqdn($sshHost),
                'vhost_type' => $vhostCheck['type'],
                'vhost_paths' => $vhostCheck['paths'],
            ];
        } else {
            return [
                'type' => 'potential_vnodes',
                'vsite' => $vsite,
                'role' => $this->determineVNodeRole($sshHost),
                'environment' => $this->determineEnvironment($sshHost),
                'ssh_accessible' => $vhostCheck['ssh_accessible'],
            ];
        }
    }

    protected function checkForVHostDirectories(SshHost $sshHost): array
    {
        $result = [
            'is_vhost' => false,
            'ssh_accessible' => false,
            'type' => null,
            'paths' => [],
        ];

        try {
            // Test SSH connectivity first
            $testCommand = "ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no {$sshHost->host} 'echo ok' 2>/dev/null";
            $testResult = Process::timeout(10)->run($testCommand);

            if (! $testResult->successful()) {
                return $result; // SSH not accessible
            }

            $result['ssh_accessible'] = true;

            // Check for VHost directory structures in order of preference
            $vhostChecks = [
                'v3.0' => '/srv/*',
                'v2.0' => '/var/ns/*',
                'v1.0' => '/home/u/*',
            ];

            foreach ($vhostChecks as $version => $pattern) {
                $checkCommand = "ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no {$sshHost->host} 'ls -d {$pattern} 2>/dev/null | head -5' 2>/dev/null";
                $checkResult = Process::timeout(10)->run($checkCommand);

                if ($checkResult->successful() && ! empty(trim($checkResult->output()))) {
                    $paths = array_filter(explode("\n", trim($checkResult->output())));
                    if (! empty($paths)) {
                        $result['is_vhost'] = true;
                        $result['type'] = $version;
                        $result['paths'] = $paths;

                        // If we find v3.0, prefer it and stop checking
                        if ($version === 'v3.0') {
                            break;
                        }
                    }
                }
            }

        } catch (\Exception $e) {
            // SSH failed, treat as VNode
            $result['ssh_accessible'] = false;
        }

        return $result;
    }

    protected function getCanonicalFqdn(SshHost $sshHost): ?string
    {
        // First try reverse PTR lookup
        if (filter_var($sshHost->hostname, FILTER_VALIDATE_IP)) {
            try {
                $ptrRecord = gethostbyaddr($sshHost->hostname);
                if ($ptrRecord && $ptrRecord !== $sshHost->hostname && str_contains($ptrRecord, '.')) {
                    return $ptrRecord;
                }
            } catch (\Exception $e) {
                // PTR lookup failed, continue with other methods
            }
        }

        // If hostname is already a domain, use it
        if (str_contains($sshHost->hostname, '.') && ! filter_var($sshHost->hostname, FILTER_VALIDATE_IP)) {
            return $sshHost->hostname;
        }

        // Fallback to inferred domain
        return $this->inferDomain($sshHost);
    }

    protected function shouldSkipHost(SshHost $sshHost): bool
    {
        $skipPatterns = [
            'github.com', 'gitlab.com', 'bitbucket.org',
            'gw', 'gateway', 'router', 'switch',
        ];

        $host = strtolower($sshHost->host);
        $hostname = strtolower($sshHost->hostname);

        foreach ($skipPatterns as $pattern) {
            if (str_contains($hostname, $pattern) || str_contains($host, $pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function determineVSite(SshHost $sshHost): ?string
    {
        $host = strtolower($sshHost->host);

        foreach ($this->vsitePatterns as $vsiteName => $config) {
            foreach ($config['patterns'] as $pattern) {
                if (str_contains($host, $pattern)) {
                    return $vsiteName;
                }
            }
        }

        // Default to unknown vsite for manual classification later
        return 'unknown-vsite';
    }

    protected function determineVNodeRole(SshHost $sshHost): string
    {
        $host = strtolower($sshHost->host);

        if (str_contains($host, 'pve') || str_contains($host, 'proxmox')) {
            return 'compute';
        }
        if (str_contains($host, 'pbs')) {
            return 'storage';
        }
        if (str_contains($host, 'ns') || str_contains($host, 'dns')) {
            return 'network';
        }
        if (str_contains($host, 'haproxy') || str_contains($host, 'proxy')) {
            return 'network';
        }

        return 'mixed';
    }

    protected function determineEnvironment(SshHost $sshHost): string
    {
        $host = strtolower($sshHost->host);

        if (str_contains($host, 'test') || str_contains($host, 'dev') || str_contains($host, 'tpl')) {
            return 'development';
        }
        if (str_contains($host, 'staging') || str_contains($host, 'stage')) {
            return 'staging';
        }

        return 'production';
    }

    protected function inferDomain(SshHost $sshHost): string
    {
        // If hostname is already a domain, use it
        if (str_contains($sshHost->hostname, '.') && ! filter_var($sshHost->hostname, FILTER_VALIDATE_IP)) {
            return $sshHost->hostname;
        }

        $alias = $sshHost->host;

        // Special domain mappings
        $domainMappings = [
            'mgo' => 'mail.goldcoast.org',
            'motd' => 'motd.goldcoast.org',
            'mail' => 'mail.goldcoast.org',
            'mx1' => 'mx1.goldcoast.org',
        ];

        if (isset($domainMappings[$alias])) {
            return $domainMappings[$alias];
        }

        // Determine domain suffix based on VSite
        $vsite = $this->determineVSite($sshHost);
        $suffix = match ($vsite) {
            'goldcoast-proxmox-datacenter', 'local-network' => 'goldcoast.org',
            'netserva-cloud' => 'netserva.com',
            'development-lab' => 'lab.local',
            default => 'goldcoast.org',
        };

        return "{$alias}.{$suffix}";
    }

    protected function classifyInfrastructure(): int
    {
        if ($this->option('clean')) {
            if (! confirm('This will delete incorrect VHosts and rebuild the infrastructure. Continue?', false)) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
            $this->cleanIncorrectData();
        }

        info('Classifying SSH hosts into Venues, VSites, VNodes, and VHosts...');

        $sshHosts = SshHost::all();
        $created = ['venues' => 0, 'vsites' => 0, 'vnodes' => 0, 'vhosts' => 0];
        $skipped = 0;

        // First pass: Create Venues
        $venuesCreated = $this->createVenues();
        $created['venues'] = $venuesCreated;

        // Second pass: Create VSites
        $vsitesCreated = $this->createVSites();
        $created['vsites'] = $vsitesCreated;

        // Third pass: Create VNodes
        foreach ($sshHosts as $sshHost) {
            $classification = $this->classifySshHost($sshHost);

            if ($classification['type'] === 'potential_vnodes') {
                if ($this->createVNode($sshHost, $classification)) {
                    $created['vnodes']++;
                } else {
                    $skipped++;
                }
            }
        }

        // Fourth pass: Create VHosts
        foreach ($sshHosts as $sshHost) {
            $classification = $this->classifySshHost($sshHost);

            if ($classification['type'] === 'potential_vhosts') {
                if ($this->createVHost($sshHost, $classification)) {
                    $created['vhosts']++;
                } else {
                    $skipped++;
                }
            }
        }

        info('✅ Infrastructure classification complete:');
        info("  Venues created: {$created['venues']}");
        info("  VSites created: {$created['vsites']}");
        info("  VNodes created: {$created['vnodes']}");
        info("  VHosts created: {$created['vhosts']}");
        info("  Skipped: {$skipped}");

        return self::SUCCESS;
    }

    protected function createVenues(): int
    {
        $created = 0;

        // Create a default "Local" venue for all local infrastructure
        if (! FleetVenue::where('name', 'local-infrastructure')->exists()) {
            FleetVenue::create([
                'name' => 'local-infrastructure',
                'slug' => 'local-infrastructure',
                'provider' => 'local',
                'location' => 'Local Infrastructure',
                'description' => 'Local development and homelab infrastructure',
                'status' => 'active',
                'is_active' => true,
            ]);
            $created++;
        }

        return $created;
    }

    protected function cleanIncorrectData(): void
    {
        info('Cleaning incorrect data...');

        // Keep only the original Proxmox cluster VSite and its VNodes
        $originalVSite = FleetVSite::where('name', 'goldcoast-proxmox-datacenter')->first();
        $originalVNodes = $originalVSite ? $originalVSite->vnodes->pluck('id') : collect();

        // Delete incorrectly created VHosts (most of them)
        $incorrectVHosts = FleetVHost::whereNotIn('vnode_id', $originalVNodes)
            ->orWhereHas('vnode', fn ($q) => $q->where('vsite_id', '!=', $originalVSite?->id))
            ->get();

        foreach ($incorrectVHosts as $vhost) {
            $vhost->delete();
        }

        info('Deleted '.$incorrectVHosts->count().' incorrectly created VHosts');
    }

    protected function createVSites(): int
    {
        $created = 0;

        // Get the default local venue
        $venue = FleetVenue::where('name', 'local-infrastructure')->first();
        if (! $venue) {
            return 0;
        }

        foreach ($this->vsitePatterns as $vsiteName => $config) {
            if (! FleetVSite::where('name', $vsiteName)->exists()) {
                FleetVSite::create([
                    'name' => $vsiteName,
                    'slug' => str($vsiteName)->slug(),
                    'venue_id' => $venue->id,
                    'provider' => $config['provider'],
                    'technology' => $config['technology'],
                    'location' => $config['location'] ?? null,
                    'description' => $config['description'],
                    'status' => 'active',
                    'is_active' => true,
                ]);
                $created++;
            }
        }

        return $created;
    }

    protected function createVNode(SshHost $sshHost, array $classification): bool
    {
        // Check if VNode already exists
        if (FleetVNode::where('name', $sshHost->host)->exists()) {
            return false;
        }

        $vsite = FleetVSite::where('name', $classification['vsite'])->first();
        if (! $vsite) {
            return false;
        }

        try {
            FleetVNode::create([
                'name' => $sshHost->host,
                'slug' => str($sshHost->host)->slug(),
                'vsite_id' => $vsite->id,
                'ssh_host_id' => $sshHost->id,
                'role' => $classification['role'],
                'environment' => $classification['environment'],
                'ip_address' => $sshHost->hostname,
                'description' => "Auto-discovered from SSH host: {$sshHost->host}",
                'discovery_method' => 'ssh_classification',
                'scan_frequency_hours' => 24,
                'status' => 'active',
                'is_active' => true,
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function createVHost(SshHost $sshHost, array $classification): bool
    {
        $domain = $classification['domain'];

        // Check if VHost already exists
        if (FleetVHost::where('domain', $domain)->exists()) {
            return false;
        }

        // Find appropriate VNode for this VHost
        $vsite = FleetVSite::where('name', $classification['vsite'])->first();
        if (! $vsite) {
            return false;
        }

        // Try to find a VNode in the same VSite, prefer compute nodes
        $vnode = $vsite->vnodes()
            ->where('role', 'compute')
            ->where('is_active', true)
            ->first();

        if (! $vnode) {
            // Fallback to any VNode in the VSite
            $vnode = $vsite->vnodes()->where('is_active', true)->first();
        }

        if (! $vnode) {
            return false;
        }

        try {
            FleetVHost::create([
                'domain' => $domain,
                'slug' => str($domain)->slug(),
                'vnode_id' => $vnode->id,
                'instance_type' => 'vm',
                'ip_addresses' => [$sshHost->hostname],
                'services' => [],
                'environment_vars' => [],
                'description' => "Auto-discovered from SSH host: {$sshHost->host}",
                'status' => 'active',
                'is_active' => true,
                'last_discovered_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function rebuildInfrastructure(): int
    {
        info('Rebuilding infrastructure with clean slate...');

        if (! confirm('This will delete ALL existing fleet data and rebuild from SSH hosts. Continue?', false)) {
            info('Operation cancelled.');

            return self::SUCCESS;
        }

        // Delete all existing data
        FleetVHost::truncate();
        FleetVNode::truncate();
        FleetVSite::truncate();

        info('Deleted all existing fleet data.');

        // Rebuild from scratch
        return $this->classifyInfrastructure();
    }

    protected function checkSshConnectivity(): int
    {
        info('Checking SSH connectivity for all hosts...');

        $timeout = $this->option('timeout') ?? 5;
        $sshHosts = SshHost::all();

        $reachable = [];
        $unreachable = [];
        $suspect = [];

        $progress = progress(label: 'Testing SSH connectivity', steps: $sshHosts->count());
        $progress->start();

        foreach ($sshHosts as $sshHost) {
            $progress->advance();

            $startTime = microtime(true);

            // Test basic SSH connectivity with short timeout
            $testCommand = "ssh -o BatchMode=yes -o ConnectTimeout={$timeout} -o StrictHostKeyChecking=no {$sshHost->host} 'echo ok' 2>/dev/null";

            $result = Process::timeout($timeout + 2)->run($testCommand);
            $elapsed = round((microtime(true) - $startTime) * 1000); // ms

            if ($result->successful() && trim($result->output()) === 'ok') {
                $reachable[] = [
                    'host' => $sshHost->host,
                    'hostname' => $sshHost->hostname,
                    'time_ms' => $elapsed,
                    'status' => '✓ OK',
                ];
            } elseif ($elapsed >= ($timeout * 1000)) {
                $suspect[] = [
                    'host' => $sshHost->host,
                    'hostname' => $sshHost->hostname,
                    'time_ms' => $elapsed,
                    'status' => '⚠ TIMEOUT',
                    'config_file' => "~/.ssh/hosts/{$sshHost->host}",
                ];
            } else {
                $unreachable[] = [
                    'host' => $sshHost->host,
                    'hostname' => $sshHost->hostname,
                    'time_ms' => $elapsed,
                    'status' => '✗ UNREACHABLE',
                    'config_file' => "~/.ssh/hosts/{$sshHost->host}",
                ];
            }
        }

        $progress->finish();

        // Show results
        info("\n=== SSH Connectivity Results ===");
        info('Total hosts tested: '.$sshHosts->count());
        info('Reachable: '.count($reachable));
        info('Unreachable: '.count($unreachable));
        info('Suspect (timeout): '.count($suspect));

        if (! empty($reachable)) {
            info("\n✅ Reachable Hosts:");
            table(['Host', 'IP/Hostname', 'Response (ms)', 'Status'], $reachable);
        }

        if (! empty($unreachable)) {
            info("\n❌ Unreachable Hosts:");
            table(['Host', 'IP/Hostname', 'Response (ms)', 'Status', 'Config File'], $unreachable);
        }

        if (! empty($suspect)) {
            info("\n⚠️  Suspect Hosts (Timeout - Likely Stale):");
            table(['Host', 'IP/Hostname', 'Response (ms)', 'Status', 'Config File'], $suspect);

            info("\nThese hosts timed out and are likely stale/inactive.");
            info('You can manually remove them using:');
            foreach ($suspect as $host) {
                info("  rm ~/.ssh/hosts/{$host['host']}");
            }
        }

        return self::SUCCESS;
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:infra-discovery {action} [options]');
        info('');
        info('Actions:');
        info('  check-connectivity   Test SSH connectivity and identify stale hosts');
        info('  analyze              Analyze SSH hosts and show classification patterns');
        info('  classify             Create VSites, VNodes, and VHosts from SSH hosts');
        info('  rebuild              Clean slate rebuild of entire infrastructure');
        info('');
        info('Options:');
        info('  --dry-run            Show what would be done without making changes');
        info('  --clean              Clean existing incorrect data first');
        info('  --timeout=5          SSH connection timeout in seconds (default: 5)');
        info('');
        info('Examples:');
        info('  php artisan fleet:infra-discovery check-connectivity');
        info('  php artisan fleet:infra-discovery check-connectivity --timeout=3');
        info('  php artisan fleet:infra-discovery analyze');
        info('  php artisan fleet:infra-discovery classify --clean');
        info('  php artisan fleet:infra-discovery rebuild');

        return self::SUCCESS;
    }
}
