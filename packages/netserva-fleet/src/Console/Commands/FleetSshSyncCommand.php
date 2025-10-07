<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * SSH Configuration Sync Command
 *
 * Import SSH host configurations and link to fleet infrastructure
 */
class FleetSshSyncCommand extends Command
{
    protected $signature = 'fleet:ssh-sync
                            {action : Action (link|discover|list|create-vhosts)}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Sync SSH configurations with fleet infrastructure for service discovery';

    protected array $sshHosts = [];

    protected array $mappings = [];

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'link' => $this->linkToFleet(),
            'discover' => $this->discoverServices(),
            'list' => $this->listMappings(),
            'create-vhosts' => $this->createVHostsFromSsh(),
            default => $this->showUsage(),
        };
    }

    protected function linkToFleet(): int
    {
        info('Linking SSH hosts to fleet infrastructure...');

        // Get all SSH hosts and VHosts
        $sshHosts = SshHost::all()->keyBy('host');
        $vhosts = FleetVHost::with('vnode')->get();
        $vnodes = FleetVNode::with('vsite')->get();

        $linkedVHosts = 0;
        $linkedVNodes = 0;
        $suggestions = [];

        // First pass: Direct hostname matches for VHosts
        foreach ($vhosts as $vhost) {
            $domain = $vhost->domain;
            $shortName = explode('.', $domain)[0]; // e.g., 'mail' from 'mail.goldcoast.org'

            // Try exact alias match first
            if ($sshHosts->has($shortName)) {
                $sshHost = $sshHosts[$shortName];

                if ($this->option('dry-run')) {
                    $this->line("Would link VHost '{$domain}' to SSH '{$shortName}' ({$sshHost->hostname})");
                } else {
                    // Update VHost to reference this SSH host via its VNode
                    $vnode = $vhost->vnode;
                    if (! $vnode->ssh_host_id) {
                        $vnode->update(['ssh_host_id' => $sshHost->id]);
                        $linkedVNodes++;
                    }
                }
                $linkedVHosts++;
            } else {
                // Look for potential matches
                foreach ($sshHosts as $hostAlias => $sshHost) {
                    if (str_contains($domain, $hostAlias) || str_contains($hostAlias, $shortName)) {
                        $suggestions[] = [
                            'vhost' => $domain,
                            'ssh_alias' => $hostAlias,
                            'ssh_hostname' => $sshHost->hostname,
                            'confidence' => 'medium',
                        ];
                    }
                }
            }
        }

        // Second pass: Look for VNode matches
        foreach ($vnodes as $vnode) {
            if ($vnode->ssh_host_id) {
                continue; // Already linked
            }

            $nodeName = $vnode->name;

            if ($sshHosts->has($nodeName)) {
                $sshHost = $sshHosts[$nodeName];

                if ($this->option('dry-run')) {
                    $this->line("Would link VNode '{$nodeName}' to SSH '{$nodeName}' ({$sshHost->hostname})");
                } else {
                    $vnode->update(['ssh_host_id' => $sshHost->id]);
                }
                $linkedVNodes++;
            }
        }

        if ($this->option('dry-run')) {
            info("Dry run complete. Would link {$linkedVHosts} VHosts and {$linkedVNodes} VNodes");
        } else {
            info("✅ Linked {$linkedVHosts} VHosts and {$linkedVNodes} VNodes to SSH hosts");
        }

        // Show suggestions for manual review
        if (! empty($suggestions)) {
            info("\nSuggested manual mappings:");
            table(
                ['VHost Domain', 'SSH Alias', 'SSH Hostname', 'Confidence'],
                array_slice($suggestions, 0, 10) // Show first 10 suggestions
            );

            if (count($suggestions) > 10) {
                info('... and '.(count($suggestions) - 10).' more suggestions');
            }
        }

        return self::SUCCESS;
    }

    protected function discoverServices(): int
    {
        info('Running service discovery on SSH-linked VHosts...');

        $vhosts = FleetVHost::with(['vnode.sshHost'])
            ->whereHas('vnode.sshHost')
            ->get();

        if ($vhosts->isEmpty()) {
            warning("No VHosts with SSH access found. Run 'fleet:ssh-sync link' first.");

            return self::SUCCESS;
        }

        $discovered = 0;
        $failed = 0;

        $progress = progress(label: 'Discovering services', steps: $vhosts->count());
        $progress->start();

        foreach ($vhosts as $vhost) {
            $progress->advance();

            try {
                $services = $this->discoverVHostServices($vhost);

                if (! $this->option('dry-run')) {
                    $vhost->update([
                        'services' => $services,
                        'last_discovered_at' => now(),
                    ]);
                }

                $discovered++;
            } catch (\Exception $e) {
                $failed++;
                if (! $this->option('dry-run')) {
                    $vhost->update(['last_error' => $e->getMessage()]);
                }
            }
        }

        $progress->finish();

        if ($this->option('dry-run')) {
            info("Dry run complete. Would discover services for {$discovered} VHosts, {$failed} failed");
        } else {
            info("✅ Service discovery complete: {$discovered} successful, {$failed} failed");
        }

        return self::SUCCESS;
    }

    protected function discoverVHostServices(FleetVHost $vhost): array
    {
        $sshHost = $vhost->vnode->sshHost;
        if (! $sshHost) {
            throw new \Exception("No SSH host configured for VHost {$vhost->domain}");
        }

        $services = [];

        // Try to discover services via SSH
        try {
            $command = 'systemctl list-units --type=service --state=active --no-pager --no-legend | cut -d. -f1';
            $sshCommand = "ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no {$sshHost->host} '{$command}' 2>/dev/null";

            $output = shell_exec($sshCommand);

            if ($output) {
                $services = array_filter(array_map('trim', explode("\n", trim($output))));
            }
        } catch (\Exception $e) {
            // Fall back to pattern-based detection
            $services = $this->detectServicesFromDomain($vhost->domain);
        }

        return $services;
    }

    protected function detectServicesFromDomain(string $domain): array
    {
        $services = [];
        $domain = strtolower($domain);

        if (str_contains($domain, 'mail') || str_contains($domain, 'mx')) {
            $services = array_merge($services, ['postfix', 'dovecot']);
        }

        if (str_contains($domain, 'web') || str_contains($domain, 'www')) {
            $services[] = 'nginx';
        }

        if (str_contains($domain, 'db') || str_contains($domain, 'mysql')) {
            $services[] = 'mysql';
        }

        if (str_contains($domain, 'proxy') || str_contains($domain, 'haproxy')) {
            $services[] = 'haproxy';
        }

        if (str_contains($domain, 'ns') || str_contains($domain, 'dns')) {
            $services[] = 'bind9';
        }

        return array_unique($services);
    }

    protected function listMappings(): int
    {
        info('Current SSH host to fleet mappings:');

        $vnodes = FleetVNode::with(['sshHost', 'vsite'])->whereNotNull('ssh_host_id')->get();
        $vhosts = FleetVHost::with(['vnode.sshHost', 'vnode.vsite'])->whereHas('vnode', fn ($q) => $q->whereNotNull('ssh_host_id'))->get();

        if ($vnodes->isNotEmpty()) {
            info("\nVNodes with SSH access:");
            table(
                ['VNode', 'VSite', 'SSH Alias', 'SSH Hostname', 'VHosts'],
                $vnodes->map(fn ($vn) => [
                    $vn->name,
                    $vn->vsite->name,
                    $vn->sshHost->host ?? '-',
                    $vn->sshHost->hostname ?? '-',
                    $vn->vhosts()->count(),
                ])->toArray()
            );
        }

        if ($vhosts->isNotEmpty()) {
            info("\nVHosts accessible via SSH:");
            table(
                ['Domain', 'VNode', 'SSH Alias', 'Services', 'Last Discovery'],
                $vhosts->map(fn ($vh) => [
                    $vh->domain,
                    $vh->vnode->name,
                    $vh->vnode->sshHost->host ?? '-',
                    $vh->services ? count($vh->services).' services' : 'None',
                    $vh->last_discovered_at?->format('Y-m-d H:i') ?? 'Never',
                ])->toArray()
            );
        }

        if ($vnodes->isEmpty() && $vhosts->isEmpty()) {
            warning("No SSH mappings found. Run 'fleet:ssh-sync import' and 'fleet:ssh-sync link' first.");
        }

        return self::SUCCESS;
    }

    protected function createVHostsFromSsh(): int
    {
        info('Creating VHosts from unmapped SSH hosts...');

        // Get all SSH hosts and existing VHosts
        $sshHosts = SshHost::all();
        $existingDomains = FleetVHost::pluck('domain')->toArray();
        $existingIps = FleetVHost::whereNotNull('ip_addresses')->get()
            ->flatMap(fn ($vh) => $vh->ip_addresses ?? [])
            ->unique()
            ->toArray();

        // Get default VNode for new VHosts (pve2 as it's the main node)
        $defaultVNode = FleetVNode::where('name', 'pve2')->first();
        if (! $defaultVNode) {
            error("Default VNode 'pve2' not found. Please ensure VNodes are discovered first.");

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;
        $candidates = [];

        foreach ($sshHosts as $sshHost) {
            // Skip hosts that are clearly not VHosts
            if ($this->shouldSkipSshHost($sshHost)) {
                $skipped++;

                continue;
            }

            // Try to determine domain name
            $domain = $this->inferDomainFromSshHost($sshHost);

            // Skip if domain already exists
            if (in_array($domain, $existingDomains)) {
                $skipped++;

                continue;
            }

            // Skip if IP already mapped
            if (in_array($sshHost->hostname, $existingIps)) {
                $skipped++;

                continue;
            }

            $candidates[] = [
                'ssh_host' => $sshHost,
                'domain' => $domain,
                'vnode' => $defaultVNode,
            ];
        }

        if (empty($candidates)) {
            info('No new VHosts to create from SSH hosts.');

            return self::SUCCESS;
        }

        info('Found '.count($candidates).' SSH hosts that could become VHosts:');

        if ($this->option('dry-run')) {
            foreach ($candidates as $candidate) {
                $this->line("Would create VHost: {$candidate['domain']} -> {$candidate['ssh_host']->hostname}");
            }
            info('Dry run complete. Would create '.count($candidates).' VHosts');

            return self::SUCCESS;
        }

        $progress = progress(label: 'Creating VHosts', steps: count($candidates));
        $progress->start();

        foreach ($candidates as $candidate) {
            $progress->advance();

            try {
                $vhost = FleetVHost::create([
                    'domain' => $candidate['domain'],
                    'slug' => str($candidate['domain'])->slug(),
                    'vnode_id' => $candidate['vnode']->id,
                    'instance_type' => 'vm', // Default assumption
                    'ip_addresses' => [$candidate['ssh_host']->hostname],
                    'services' => [],
                    'environment_vars' => [],
                    'description' => "Auto-created from SSH host: {$candidate['ssh_host']->host}",
                    'status' => 'active',
                    'is_active' => true,
                    'last_discovered_at' => now(),
                ]);

                // Link the VNode to the SSH host if not already linked
                if (! $candidate['vnode']->ssh_host_id) {
                    $candidate['vnode']->update(['ssh_host_id' => $candidate['ssh_host']->id]);
                }

                $created++;
            } catch (\Exception $e) {
                error("Failed to create VHost for {$candidate['domain']}: {$e->getMessage()}");
            }
        }

        $progress->finish();

        info("✅ Created {$created} VHosts from SSH hosts, skipped {$skipped}");

        return self::SUCCESS;
    }

    protected function shouldSkipSshHost(SshHost $sshHost): bool
    {
        $skipPatterns = [
            'github.com',
            'gitlab.com',
            'bitbucket.org',
        ];

        $host = strtolower($sshHost->host);
        $hostname = strtolower($sshHost->hostname);

        // Skip well-known service hosts
        foreach ($skipPatterns as $pattern) {
            if (str_contains($hostname, $pattern)) {
                return true;
            }
        }

        // Skip nodes that are already VNodes
        if (FleetVNode::where('name', $sshHost->host)->exists()) {
            return true;
        }

        // Skip infrastructure hosts (gateways, etc.)
        if (in_array($host, ['gw', 'gateway', 'router', 'switch'])) {
            return true;
        }

        return false;
    }

    protected function inferDomainFromSshHost(SshHost $sshHost): string
    {
        // If hostname is already a domain, use it
        if (str_contains($sshHost->hostname, '.') && ! filter_var($sshHost->hostname, FILTER_VALIDATE_IP)) {
            return $sshHost->hostname;
        }

        // Try to construct domain from SSH alias
        $alias = $sshHost->host;

        // Handle special cases
        $domainMappings = [
            'mgo' => 'mail.goldcoast.org',
            'motd' => 'motd.goldcoast.org',
            'mail' => 'mail.goldcoast.org',
            'ns1' => 'ns1.goldcoast.org',
            'ns2' => 'ns2.goldcoast.org',
            'ns3' => 'ns3.goldcoast.org',
            'haproxy' => 'haproxy.goldcoast.org',
            'pihole' => 'pihole.goldcoast.org',
        ];

        if (isset($domainMappings[$alias])) {
            return $domainMappings[$alias];
        }

        // Default pattern: alias.goldcoast.org
        return "{$alias}.goldcoast.org";
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:ssh-sync {action} [options]');
        info('');
        info('Actions:');
        info('  create-vhosts         Create VHosts from unmapped SSH hosts');
        info('  link                  Link SSH hosts to fleet VNodes/VHosts');
        info('  discover              Run service discovery on linked VHosts');
        info('  list                  Show current SSH mappings');
        info('');
        info('Options:');
        info('  --dry-run             Show what would be done without making changes');
        info('');
        info('Examples:');
        info('  php artisan fleet:ssh-sync create-vhosts --dry-run');
        info('  php artisan fleet:ssh-sync create-vhosts');
        info('  php artisan fleet:ssh-sync link');
        info('  php artisan fleet:ssh-sync discover');
        info('  php artisan fleet:ssh-sync list');
        info('');
        info('Note: SSH hosts are imported automatically by netserva-core.');

        return self::SUCCESS;
    }
}
