<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Services\PowerDnsTunnelService;
use Symfony\Component\Console\Helper\Table;

class PowerDnsCommand extends Command
{
    protected $signature = 'ns:powerdns 
                            {action : Action to perform (test|zones|stats|flush|tunnel)}
                            {provider? : Provider name or ID}
                            {--zone= : Zone name for zone-specific actions}
                            {--domain= : Domain for cache flush}
                            {--create-zone= : Create zone with this name}
                            {--nameservers=* : Nameservers for zone creation}
                            {--ssh-host= : SSH host for tunnel management}
                            {--close : Close tunnel}
                            {--status : Show tunnel status}';

    protected $description = 'Manage PowerDNS operations with SSH tunneling support';

    protected PowerDnsTunnelService $powerDnsService;

    public function __construct(PowerDnsTunnelService $powerDnsService)
    {
        parent::__construct();
        $this->powerDnsService = $powerDnsService;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $providerInput = $this->argument('provider');

        try {
            switch ($action) {
                case 'test':
                    return $this->handleTest($providerInput);

                case 'zones':
                    return $this->handleZones($providerInput);

                case 'stats':
                    return $this->handleStats($providerInput);

                case 'flush':
                    return $this->handleFlush($providerInput);

                case 'tunnel':
                    return $this->handleTunnel($providerInput);

                default:
                    $this->error("Unknown action: $action");
                    $this->line('Available actions: test, zones, stats, flush, tunnel');

                    return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Command failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function handleTest(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        $this->info("Testing PowerDNS connection for provider: {$provider->name}");
        $this->line('SSH Host: '.($provider->config['ssh_host'] ?? 'Not configured'));

        $this->newLine();
        $this->info('Establishing SSH tunnel...');

        $result = $this->powerDnsService->testConnection($provider);

        if ($result['success']) {
            $this->info('✅ '.$result['message']);

            if (isset($result['servers']) && count($result['servers']) > 0) {
                $this->newLine();
                $this->info('PowerDNS Servers:');

                $table = new Table($this->output);
                $table->setHeaders(['Server ID', 'Type', 'URL', 'Version']);

                foreach ($result['servers'] as $server) {
                    $table->addRow([
                        $server['id'] ?? 'N/A',
                        $server['type'] ?? 'N/A',
                        $server['url'] ?? 'N/A',
                        $server['version'] ?? 'N/A',
                    ]);
                }

                $table->render();
            }

            if ($result['tunnel_used'] ?? false) {
                $this->info('🔒 Connection established through SSH tunnel');
            }

            return self::SUCCESS;
        } else {
            $this->error('❌ '.$result['message']);

            return self::FAILURE;
        }
    }

    protected function handleZones(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        // Handle zone creation
        if ($this->option('create-zone')) {
            return $this->handleZoneCreation($provider);
        }

        // Handle specific zone lookup
        if ($zoneName = $this->option('zone')) {
            return $this->handleZoneLookup($provider, $zoneName);
        }

        // List all zones
        $this->info("Fetching zones from PowerDNS provider: {$provider->name}");

        $zones = $this->powerDnsService->getZones($provider);

        if (empty($zones)) {
            $this->warn('No zones found or failed to fetch zones');

            return self::FAILURE;
        }

        $this->info('Found '.count($zones).' zones:');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Name', 'Kind', 'Serial', 'Last Check', 'Records']);

        foreach ($zones as $zone) {
            $table->addRow([
                $zone['name'] ?? 'N/A',
                $zone['kind'] ?? 'N/A',
                $zone['serial'] ?? 'N/A',
                isset($zone['last_check']) ? date('Y-m-d H:i:s', $zone['last_check']) : 'N/A',
                count($zone['rrsets'] ?? []),
            ]);
        }

        $table->render();

        return self::SUCCESS;
    }

    protected function handleZoneCreation(DnsProvider $provider): int
    {
        $zoneName = $this->option('create-zone');
        $nameservers = $this->option('nameservers') ?: [];

        if (empty($nameservers)) {
            $nameservers = [
                'ns1.'.$zoneName,
                'ns2.'.$zoneName,
            ];
        }

        $this->info("Creating zone: $zoneName");
        $this->line('Nameservers: '.implode(', ', $nameservers));

        $zoneData = [
            'name' => $zoneName,
            'kind' => 'Master',
            'nameservers' => $nameservers,
            'masters' => [],
        ];

        $result = $this->powerDnsService->createZone($provider, $zoneData);

        if ($result['success']) {
            $this->info('✅ Zone created successfully');

            return self::SUCCESS;
        } else {
            $this->error('❌ '.$result['message']);

            return self::FAILURE;
        }
    }

    protected function handleZoneLookup(DnsProvider $provider, string $zoneName): int
    {
        $this->info("Fetching zone details: $zoneName");

        $zone = $this->powerDnsService->getZone($provider, $zoneName);

        if (empty($zone)) {
            $this->error("Zone not found or failed to fetch: $zoneName");

            return self::FAILURE;
        }

        $this->info('Zone Information:');
        $this->line('Name: '.($zone['name'] ?? 'N/A'));
        $this->line('Kind: '.($zone['kind'] ?? 'N/A'));
        $this->line('Serial: '.($zone['serial'] ?? 'N/A'));
        $this->line('Masters: '.implode(', ', $zone['masters'] ?? []));
        $this->line('Nameservers: '.implode(', ', $zone['nameservers'] ?? []));

        if (! empty($zone['rrsets'])) {
            $this->newLine();
            $this->info('Resource Records:');

            $table = new Table($this->output);
            $table->setHeaders(['Name', 'Type', 'TTL', 'Records']);

            foreach ($zone['rrsets'] as $rrset) {
                $records = [];
                foreach ($rrset['records'] ?? [] as $record) {
                    $records[] = $record['content'];
                }

                $table->addRow([
                    $rrset['name'],
                    $rrset['type'],
                    $rrset['ttl'] ?? 'N/A',
                    implode("\n", $records),
                ]);
            }

            $table->render();
        }

        return self::SUCCESS;
    }

    protected function handleStats(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        $this->info("Fetching PowerDNS statistics for: {$provider->name}");

        $result = $this->powerDnsService->getServerStats($provider);

        if (! $result['success']) {
            $this->error('❌ Failed to get statistics: '.$result['message']);

            return self::FAILURE;
        }

        $stats = $result['stats'];

        if (empty($stats)) {
            $this->warn('No statistics available');

            return self::SUCCESS;
        }

        $this->info('PowerDNS Server Statistics:');
        $this->newLine();

        $table = new Table($this->output);
        $table->setHeaders(['Metric', 'Value']);

        // Key statistics to display
        $keyStats = [
            'uptime' => 'Uptime (seconds)',
            'queries' => 'Total Queries',
            'cache-hits' => 'Cache Hits',
            'cache-misses' => 'Cache Misses',
            'packetcache-hits' => 'Packet Cache Hits',
            'packetcache-misses' => 'Packet Cache Misses',
            'servfail-answers' => 'ServFail Answers',
            'tcp-queries' => 'TCP Queries',
            'udp-queries' => 'UDP Queries',
        ];

        foreach ($keyStats as $key => $label) {
            $value = 'N/A';

            // Find the statistic
            foreach ($stats as $stat) {
                if (($stat['name'] ?? '') === $key) {
                    $value = $stat['value'] ?? 'N/A';
                    break;
                }
            }

            $table->addRow([$label, $value]);
        }

        $table->render();

        if ($result['tunnel_used'] ?? false) {
            $this->newLine();
            $this->info('🔒 Statistics retrieved through SSH tunnel');
        }

        return self::SUCCESS;
    }

    protected function handleFlush(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        $domain = $this->option('domain');

        $message = $domain ? "Flushing cache for domain: $domain" : 'Flushing entire PowerDNS cache';
        $this->info($message);

        if (! $this->confirm('Are you sure you want to flush the cache?')) {
            $this->info('Cache flush cancelled');

            return self::SUCCESS;
        }

        $result = $this->powerDnsService->flushCache($provider, $domain);

        if ($result['success']) {
            $this->info('✅ '.$result['message']);

            return self::SUCCESS;
        } else {
            $this->error('❌ '.$result['message']);

            return self::FAILURE;
        }
    }

    protected function handleTunnel(?string $providerInput): int
    {
        if ($this->option('status')) {
            return $this->handleTunnelStatus($providerInput);
        }

        if ($this->option('close')) {
            return $this->handleTunnelClose($providerInput);
        }

        // Default: show tunnel information
        return $this->handleTunnelInfo($providerInput);
    }

    protected function handleTunnelStatus(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        $status = $this->powerDnsService->getTunnelStatus($provider);

        $this->info("Tunnel Status for {$provider->name}:");
        $this->line('SSH Host: '.($status['ssh_host'] ?? 'Not configured'));
        $this->line('Local Port: '.($status['local_port'] ?? 'N/A'));
        $this->line('Remote Port: '.($status['remote_port'] ?? 'N/A'));
        $this->line('Status: '.($status['active'] ? '🟢 Active' : '🔴 Inactive'));

        if ($status['active'] && $status['endpoint']) {
            $this->line('Endpoint: '.$status['endpoint']);
        }

        $this->newLine();
        $this->line($status['message']);

        return self::SUCCESS;
    }

    protected function handleTunnelClose(?string $providerInput): int
    {
        $provider = $this->getProvider($providerInput);
        if (! $provider) {
            return self::FAILURE;
        }

        $this->info("Closing SSH tunnel for {$provider->name}...");

        $result = $this->powerDnsService->closeTunnel($provider);

        if ($result['success']) {
            $this->info('✅ '.$result['message']);

            return self::SUCCESS;
        } else {
            $this->error('❌ '.$result['message']);

            return self::FAILURE;
        }
    }

    protected function handleTunnelInfo(?string $providerInput): int
    {
        if (! $providerInput) {
            $this->error('Provider required for tunnel management');
            $this->line('Usage: ns:powerdns tunnel <provider> [--status] [--close]');

            return self::FAILURE;
        }

        return $this->handleTunnelStatus($providerInput);
    }

    protected function getProvider(?string $providerInput): ?DnsProvider
    {
        if (! $providerInput) {
            // Show available PowerDNS providers
            $providers = DnsProvider::where('provider', 'powerdns')->where('is_active', true)->get();

            if ($providers->isEmpty()) {
                $this->error('No active PowerDNS providers found');

                return null;
            }

            if ($providers->count() === 1) {
                return $providers->first();
            }

            $this->error('Multiple PowerDNS providers found. Please specify one:');
            foreach ($providers as $provider) {
                $this->line("  {$provider->id}: {$provider->name}");
            }

            return null;
        }

        // Try to find by ID first, then by name
        $provider = DnsProvider::where('provider', 'powerdns')
            ->where('is_active', true)
            ->where(function ($query) use ($providerInput) {
                $query->where('id', $providerInput)
                    ->orWhere('name', $providerInput);
            })
            ->first();

        if (! $provider) {
            $this->error("PowerDNS provider not found: $providerInput");

            return null;
        }

        return $provider;
    }
}
