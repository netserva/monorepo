<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\Infrastructure\DnsmasqService;

class ShDnsmasqCommand extends Command
{
    protected $signature = 'shdnsmasq {vnode? : VNode name (default: gw)}
                            {hostname? : Filter by hostname (optional)}
                            {--sync : Sync FROM remote before displaying}
                            {--status : Show dnsmasq status only}
                            {--zone= : Filter by zone (e.g., goldcoast.org)}
                            {--source= : Filter by source (uci or config)}
                            {--json : Output as JSON}';

    protected $description = 'Show dnsmasq DNS records on router/gateway vnode';

    public function handle(DnsmasqService $dnsmasqService): int
    {
        $vnodeName = $this->argument('vnode') ?? 'gw';
        $hostname = $this->argument('hostname');

        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode '{$vnodeName}' not found");

            return 1;
        }

        if (! $vnode->sshHost) {
            $this->error("VNode '{$vnodeName}' has no SSH host configured");

            return 1;
        }

        // Show status only
        if ($this->option('status')) {
            return $this->showStatus($vnode, $dnsmasqService);
        }

        // Sync from remote with --sync flag
        if ($this->option('sync')) {
            return $this->syncAndShow($vnode, $dnsmasqService);
        }

        // Use cached data (check if stale)
        return $this->showCached($vnode, $dnsmasqService);
    }

    private function showStatus(FleetVnode $vnode, DnsmasqService $dnsmasqService): int
    {
        $this->info('Dnsmasq Status');
        $this->line("VNode: {$vnode->name}");
        $this->line('SSH Host: '.$vnode->sshHost->host);
        $this->newLine();

        try {
            $result = $dnsmasqService->getStatus($vnode);

            if (! $result['success']) {
                $this->error('✗ Failed to get status: '.($result['error'] ?? 'Unknown error'));

                return 1;
            }

            $status = $result['status'];

            if ($this->option('json')) {
                $this->line(json_encode($status, JSON_PRETTY_PRINT));

                return 0;
            }

            $this->table(
                ['Property', 'Value'],
                [
                    ['Status', $status['status'] ?? 'unknown'],
                    ['Processes', $status['processes'] ?? '0'],
                    ['Config Exists', $status['config_exists'] ? 'Yes' : 'No'],
                    ['Config Lines', $status['config_lines'] ?? '0'],
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $this->error('✗ Failed to get status: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    private function showCached(FleetVnode $vnode, DnsmasqService $dnsmasqService): int
    {
        $hostname = $this->argument('hostname');

        try {
            // Check cache staleness
            if ($dnsmasqService->isCacheStale($vnode, 60)) {
                $stats = $dnsmasqService->getCacheStats($vnode);
                $ageMinutes = $stats['age_minutes'] ?? 'unknown';

                $this->warn("Cache is stale (age: {$ageMinutes} minutes). Consider using --sync to refresh.");

                if ($stats['total_records'] === 0) {
                    $this->error('No cached data found. Please run with --sync first.');

                    return 1;
                }
            }

            // Get cached hosts
            $hosts = $dnsmasqService->getCachedHosts($vnode);

            // Convert to array format for filtering/display
            $hostsArray = $hosts->map(fn ($h) => [
                'name' => $h->hostname,
                'ip' => $h->ip,
                'type' => $h->type,
                'mac' => $h->mac,
                'source' => $h->source,
            ])->toArray();

            // Apply filters and display
            return $this->displayHosts($hostsArray, $hostname);

        } catch (\Exception $e) {
            $this->error('✗ Failed to read cache: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    private function syncAndShow(FleetVnode $vnode, DnsmasqService $dnsmasqService): int
    {
        $hostname = $this->argument('hostname');

        try {
            $this->info('Syncing from remote...');
            $result = $dnsmasqService->syncAndCache($vnode);
            $this->info('✓ Cache updated');

            // Display from fresh data
            return $this->displayHosts($result['all_hosts'], $hostname);

        } catch (\Exception $e) {
            $this->error('✗ Failed to sync records: '.$e->getMessage());
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }

            return 1;
        }
    }

    private function displayHosts(array $hosts, ?string $hostnameFilter): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($hosts, JSON_PRETTY_PRINT));

            return 0;
        }

        // Apply filters
        if ($hostnameFilter) {
            $hosts = array_filter($hosts, fn ($h) => str_contains($h['name'], $hostnameFilter));
            $this->line("Filtered by hostname: {$hostnameFilter}");
        }

        if ($zone = $this->option('zone')) {
            $hosts = array_filter($hosts, function ($h) use ($zone) {
                return str_ends_with($h['name'], $zone);
            });
            $this->line("Filtered by zone: {$zone}");
        }

        if ($source = $this->option('source')) {
            $hosts = array_filter($hosts, fn ($h) => $h['source'] === $source);
            $this->line("Filtered by source: {$source}");
        }

        if (empty($hosts)) {
            $this->warn('No hosts found matching filters');

            return 0;
        }

        // Display hosts table
        $this->table(
            ['Hostname', 'IP Address', 'Type', 'MAC'],
            array_map(fn ($host) => [
                $host['name'],
                $host['ip'],
                $host['type'],
                $host['mac'] ?? '',
            ], array_values($hosts))
        );

        return 0;
    }
}
