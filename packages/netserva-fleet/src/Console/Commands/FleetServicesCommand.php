<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Fleet Services Discovery Command
 *
 * Discover and manage services running on VHosts
 */
class FleetServicesCommand extends Command
{
    protected $signature = 'fleet:services
                            {action : Action (list|discover|update)}
                            {target? : VHost domain or VNode name}
                            {--all : Apply to all VHosts}';

    protected $description = 'Discover and manage services running on VHosts';

    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'list' => $this->listServices(),
            'discover' => $this->discoverServices(),
            'update' => $this->updateServices(),
            default => $this->showUsage(),
        };
    }

    protected function listServices(): int
    {
        $target = $this->argument('target');

        if ($target) {
            // Show services for specific VHost
            $vhost = FleetVHost::where('domain', $target)->first();
            if (! $vhost) {
                error("VHost '{$target}' not found.");

                return self::FAILURE;
            }

            return $this->showVHostServices($vhost);
        }

        // Show all VHosts with their services
        $vhosts = FleetVHost::with('vnode.vsite')->get();

        if ($vhosts->isEmpty()) {
            info('No VHosts found.');

            return self::SUCCESS;
        }

        $data = [];
        foreach ($vhosts as $vhost) {
            $services = $vhost->services ? implode(', ', $vhost->services) : 'None detected';
            $serviceCount = $vhost->services ? count($vhost->services) : 0;

            $data[] = [
                $vhost->domain,
                $vhost->vnode->name,
                $vhost->instance_type ?? '-',
                $serviceCount,
                strlen($services) > 50 ? substr($services, 0, 47).'...' : $services,
            ];
        }

        table(['Domain', 'VNode', 'Type', 'Count', 'Services'], $data);

        return self::SUCCESS;
    }

    protected function showVHostServices(FleetVHost $vhost): int
    {
        info("Services for: {$vhost->domain}");

        table(['Property', 'Value'], [
            ['Domain', $vhost->domain],
            ['VNode', $vhost->vnode->name],
            ['Instance Type', $vhost->instance_type ?? 'Unknown'],
            ['Instance ID', $vhost->instance_id ?? 'Unknown'],
            ['IP Addresses', $vhost->ip_addresses ? implode(', ', $vhost->ip_addresses) : 'None'],
            ['Last Discovered', $vhost->last_discovered_at?->format('Y-m-d H:i:s') ?? 'Never'],
        ]);

        if ($vhost->services && ! empty($vhost->services)) {
            info("\nDetected Services:");
            table(['Service', 'Status'], array_map(fn ($service) => [$service, '✓ Running'], $vhost->services));

            info("\nService Detection:");
            $detections = [
                'Web Server' => $vhost->isWebServer() ? '✓' : '✗',
                'Mail Server' => $vhost->isMailServer() ? '✓' : '✗',
                'Database Server' => $vhost->isDatabaseServer() ? '✓' : '✗',
            ];

            foreach ($detections as $type => $status) {
                $this->line("  {$status} {$type}");
            }
        } else {
            warning('No services detected. Run discovery to populate services.');
            info('Usage: php artisan fleet:services discover '.$vhost->domain);
        }

        return self::SUCCESS;
    }

    protected function discoverServices(): int
    {
        $target = $this->argument('target');

        if ($this->option('all')) {
            return $this->discoverAllServices();
        }

        if (! $target) {
            error('Target VHost domain or VNode name required.');

            return self::FAILURE;
        }

        // Try to find VHost first
        $vhost = FleetVHost::where('domain', $target)->first();
        if ($vhost) {
            return $this->discoverVHostServices($vhost);
        }

        // Try to find VNode
        $vnode = FleetVNode::where('name', $target)->first();
        if ($vnode) {
            return $this->discoverVNodeServices($vnode);
        }

        error("Neither VHost '{$target}' nor VNode '{$target}' found.");

        return self::FAILURE;
    }

    protected function discoverAllServices(): int
    {
        $vhosts = FleetVHost::with('vnode')->get();
        $processed = 0;
        $successful = 0;

        info("Discovering services for {$vhosts->count()} VHosts...");

        foreach ($vhosts as $vhost) {
            $processed++;
            $this->line("Processing: {$vhost->domain}");

            try {
                if ($this->discoverVHostServices($vhost, false)) {
                    $successful++;
                }
            } catch (\Exception $e) {
                warning("Failed for {$vhost->domain}: {$e->getMessage()}");
            }
        }

        info("✅ Discovery complete: {$successful}/{$processed} successful");

        return self::SUCCESS;
    }

    protected function discoverVNodeServices(FleetVNode $vnode): int
    {
        $vhosts = $vnode->vhosts;

        if ($vhosts->isEmpty()) {
            warning("No VHosts found on VNode '{$vnode->name}'.");

            return self::SUCCESS;
        }

        info("Discovering services for {$vhosts->count()} VHosts on {$vnode->name}...");

        $successful = 0;
        foreach ($vhosts as $vhost) {
            $this->line("Processing: {$vhost->domain}");
            if ($this->discoverVHostServices($vhost, false)) {
                $successful++;
            }
        }

        info("✅ Discovery complete: {$successful}/{$vhosts->count()} successful");

        return self::SUCCESS;
    }

    protected function discoverVHostServices(FleetVHost $vhost, bool $verbose = true): bool
    {
        if ($verbose) {
            info("Discovering services for: {$vhost->domain}");
        }

        // Method 1: Direct SSH to the VHost IP
        $services = $this->discoverViaDirectSsh($vhost);

        // Method 2: SSH via Proxmox node (for containers/VMs)
        if (empty($services) && $vhost->vnode) {
            $services = $this->discoverViaProxmoxNode($vhost);
        }

        // Method 3: Manual service patterns (fallback)
        if (empty($services)) {
            $services = $this->detectServicesFromContext($vhost);
        }

        // Update the VHost
        $vhost->update([
            'services' => $services,
            'last_discovered_at' => now(),
        ]);

        if ($verbose) {
            if (! empty($services)) {
                info('✅ Discovered '.count($services).' services: '.implode(', ', $services));
            } else {
                warning("No services detected for {$vhost->domain}");
            }
        }

        return ! empty($services);
    }

    protected function discoverViaDirectSsh(FleetVHost $vhost): array
    {
        $ip = $vhost->primary_ip;
        if (! $ip) {
            return [];
        }

        try {
            // Try to SSH directly to the VHost
            $command = "ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no root@{$ip} 'systemctl list-units --type=service --state=active --no-pager --no-legend | cut -d. -f1' 2>/dev/null";

            $result = Process::timeout(10)->run($command);

            if ($result->successful()) {
                $services = array_filter(explode("\n", trim($result->output())));

                return array_map('trim', $services);
            }
        } catch (\Exception $e) {
            // Silent fail - try other methods
        }

        return [];
    }

    protected function discoverViaProxmoxNode(FleetVHost $vhost): array
    {
        $vnode = $vhost->vnode;
        if (! $vnode->ip_address && ! $vnode->ssh_host_id) {
            return [];
        }

        try {
            $nodeHost = $vnode->ip_address ?? $vnode->sshHost?->hostname;
            if (! $nodeHost) {
                return [];
            }

            // For LXC containers
            if ($vhost->instance_type === 'lxc' && $vhost->instance_id) {
                $command = "ssh -o BatchMode=yes -o ConnectTimeout=5 root@{$nodeHost} 'pct exec {$vhost->instance_id} -- systemctl list-units --type=service --state=active --no-pager --no-legend | cut -d. -f1' 2>/dev/null";
            }
            // For QEMU VMs - would need different approach
            else {
                return [];
            }

            $result = Process::timeout(15)->run($command);

            if ($result->successful()) {
                $services = array_filter(explode("\n", trim($result->output())));

                return array_map('trim', $services);
            }
        } catch (\Exception $e) {
            // Silent fail
        }

        return [];
    }

    protected function detectServicesFromContext(FleetVHost $vhost): array
    {
        $services = [];

        // Detect from domain name patterns
        $domain = strtolower($vhost->domain);

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

        if (str_contains($domain, 'pbs')) {
            $services[] = 'proxmox-backup';
        }

        return array_unique($services);
    }

    protected function updateServices(): int
    {
        $target = $this->argument('target');
        if (! $target) {
            error('VHost domain required for update.');

            return self::FAILURE;
        }

        $vhost = FleetVHost::where('domain', $target)->first();
        if (! $vhost) {
            error("VHost '{$target}' not found.");

            return self::FAILURE;
        }

        info("Current services for {$vhost->domain}:");
        if ($vhost->services) {
            foreach ($vhost->services as $service) {
                $this->line("  • {$service}");
            }
        } else {
            $this->line('  No services configured');
        }

        $servicesInput = text(
            'Services (comma-separated)',
            default: $vhost->services ? implode(', ', $vhost->services) : ''
        );

        $services = $servicesInput ? array_map('trim', explode(',', $servicesInput)) : [];

        $vhost->update(['services' => $services]);

        info("✅ Services updated for {$vhost->domain}");

        return self::SUCCESS;
    }

    protected function showUsage(): int
    {
        info('Usage: php artisan fleet:services {action} {target?} [options]');
        info('');
        info('Actions:');
        info('  list                     List services for all VHosts');
        info('  list {domain}            Show services for specific VHost');
        info('  discover {domain}        Discover services for VHost');
        info('  discover {vnode}         Discover services for all VHosts on VNode');
        info('  discover --all           Discover services for all VHosts');
        info('  update {domain}          Manually update services for VHost');
        info('');
        info('Examples:');
        info('  php artisan fleet:services list');
        info('  php artisan fleet:services list motd.goldcoast.org');
        info('  php artisan fleet:services discover motd.goldcoast.org');
        info('  php artisan fleet:services discover pve2');
        info('  php artisan fleet:services discover --all');
        info('  php artisan fleet:services update motd.goldcoast.org');

        return self::SUCCESS;
    }
}
