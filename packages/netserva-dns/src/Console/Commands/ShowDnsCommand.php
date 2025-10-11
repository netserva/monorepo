<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsProviderManagementService;

/**
 * Show DNS Provider Command
 *
 * Display DNS provider information (single or list)
 * Follows NetServa CRUD pattern: shdns (not "dns:provider:show")
 *
 * Usage: shdns [provider] [options]
 * Example: shdns 1 --verbose
 * Example: shdns --type=powerdns --active
 */
class ShowDnsCommand extends Command
{
    protected $signature = 'shdns
        {provider? : Provider ID or name (shows all if omitted)}
        {zone? : Zone name to show records for (requires provider)}
        {--type=* : Filter by type (powerdns, cloudflare, etc.)}
        {--active : Show only active providers}
        {--inactive : Show only inactive providers}
        {--with-zones : Include zone count}
        {--with-usage : Show usage by venues/vsites/vnodes/vhosts}
        {--test : Test connection}
        {--sync-remote : Show zones from remote server (live count)}
        {--import-zones : Import remote zones to local database}
        {--zones : Show zones for provider(s) using shzone under the hood}
        {--records : Show records for provider(s) using shrec under the hood}
        {--json : Output as JSON}
        {--all : Show detailed information (connection, statistics, health)}';

    protected $description = 'Show DNS provider(s) (NetServa CRUD pattern)';

    protected DnsProviderManagementService $providerService;
    protected \NetServa\Dns\Services\PowerDnsTunnelService $tunnelService;

    public function __construct(
        DnsProviderManagementService $providerService,
        \NetServa\Dns\Services\PowerDnsTunnelService $tunnelService
    ) {
        parent::__construct();
        $this->providerService = $providerService;
        $this->tunnelService = $tunnelService;
    }

    public function handle(): int
    {
        $provider = $this->argument('provider');
        $zone = $this->argument('zone');

        // If zone is provided, show records for that zone (like shrec)
        if ($zone) {
            if (!$provider) {
                $this->error('❌ Provider is required when specifying a zone');
                $this->line('Usage: shdns <provider> <zone>');
                return self::FAILURE;
            }

            // Verify the zone belongs to the provider
            $result = $this->providerService->showProvider($provider, [
                'with_zones' => true,
            ]);

            if (!$result['success']) {
                $this->error("❌ {$result['message']}");
                return self::FAILURE;
            }

            // Call shrec to show records for this zone
            return $this->call('shrec', ['identifier' => $zone]);
        }

        // Macro overlay: --zones calls shzone under the hood
        if ($this->option('zones')) {
            return $this->showZonesOverlay($provider);
        }

        // Macro overlay: --records calls shrec under the hood
        if ($this->option('records')) {
            return $this->showRecordsOverlay($provider);
        }

        // Show single provider
        if ($provider) {
            return $this->showSingleProvider($provider);
        }

        // Show list of providers
        return $this->showProviderList();
    }

    /**
     * Show detailed information for a single provider
     */
    protected function showSingleProvider(int|string $identifier): int
    {
        $options = [
            'with_usage' => $this->option('with-usage'),
            'with_zones' => true,  // Always load zones for single provider view
            'test_connection' => $this->option('test'),
            'with_health' => $this->option('all'),
        ];

        $result = $this->providerService->showProvider($identifier, $options);

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            return self::FAILURE;
        }

        $provider = $result['provider'];

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Show header only with --all flag
        if ($this->option('all')) {
            $this->newLine();
            $title = $provider->description ?: $provider->name;
            $this->line("DNS Provider: <fg=yellow>{$title}</> <fg=gray>({$provider->name})</>");
            $this->line(str_repeat('=', 70));
            $this->newLine();
        }

        // Show detailed information only with --all flag
        if ($this->option('all')) {
            // Basic information
            $this->line('<fg=blue>Type:</>' .str_repeat(' ', 10).ucfirst($provider->type));
            $this->line('<fg=blue>Active:</>' .str_repeat(' ', 8).($provider->active ? '✅ Yes' : '❌ No'));

            if ($provider->version) {
                $this->line('<fg=blue>Version:</>' .str_repeat(' ', 7).$provider->version);
            }

            $this->line('<fg=blue>Priority:</>' .str_repeat(' ', 6).$provider->sort_order);

            // Connection configuration
            $this->newLine();
            $this->info('Connection Configuration:');

            $config = $provider->connection_config ?? [];

            if ($provider->type === 'powerdns') {
                $endpoint = $config['api_endpoint'] ?? 'Not configured';
                $this->line("  Endpoint:   <fg=cyan>{$endpoint}</>");

                if (isset($config['ssh_host'])) {
                    $this->line("  SSH Host:   <fg=cyan>{$config['ssh_host']}</>");
                }

                $port = $config['api_port'] ?? 8081;
                $this->line("  Port:       <fg=cyan>{$port}</>");
            } elseif ($provider->type === 'cloudflare') {
                if (isset($config['email'])) {
                    $this->line("  Email:      <fg=cyan>{$config['email']}</>");
                }
                $this->line('  API Key:    <fg=gray>••••••••</>');
            } elseif ($provider->type === 'route53') {
                $region = $config['region'] ?? 'us-east-1';
                $this->line("  Region:     <fg=cyan>{$region}</>");
                $this->line('  Access Key: <fg=gray>••••••••</>');
            }

            $timeout = $provider->timeout ?? 30;
            $rateLimit = $provider->rate_limit ?? 100;
            $this->line("  Timeout:    <fg=cyan>{$timeout}s</>");
            $this->line("  Rate Limit: <fg=cyan>{$rateLimit} req/min</>");

            // Statistics
            $this->newLine();
            $this->info('Statistics:');

            // Show remote zone count if --sync-remote is used
            if ($this->option('sync-remote')) {
                $this->line('  <fg=yellow>Fetching zones from remote server...</>');

                try {
                    $remoteZones = $this->tunnelService->getZones($provider);

                    if (is_array($remoteZones) && !empty($remoteZones)) {
                        $remoteCount = count($remoteZones);
                        $localCount = $result['zones_count'];

                        $this->line("  Zones (Remote): <fg=cyan>{$remoteCount}</>");
                        $this->line("  Zones (Local):  <fg=cyan>{$localCount}</>");

                        if ($remoteCount !== $localCount) {
                            $diff = $remoteCount - $localCount;
                            $this->warn("  ⚠️  {$diff} zone(s) not synced to local database");
                            $this->line("  💡 Use --import-zones to sync");
                        }
                    } else {
                        $this->error("  Failed to fetch remote zones or no zones found");
                        $zonesCount = $result['zones_count'];
                        $this->line("  Zones (Local):  <fg=cyan>{$zonesCount}</>");
                    }
                } catch (\Exception $e) {
                    $this->error("  Error: " . $e->getMessage());
                    $zonesCount = $result['zones_count'];
                    $this->line("  Zones (Local):  <fg=cyan>{$zonesCount}</>");
                }
            } else {
                $zonesCount = $result['zones_count'];
                $this->line("  Zones:      <fg=cyan>{$zonesCount}</> <fg=gray>(local database)</>");
                $this->line("  <fg=gray>💡 Use --sync-remote to see remote count</>");
            }

            if ($provider->last_sync) {
                $this->line('  Last Sync:  <fg=cyan>'.$provider->last_sync->format('Y-m-d H:i:s').'</>');
            }

            // Usage information
            if (isset($result['usage'])) {
                $this->newLine();
                $this->info('Usage:');
                $usage = $result['usage'];

                if ($usage['venues'] > 0) {
                    $this->line("  Venues:     <fg=cyan>{$usage['venues']}</>");
                }
                if ($usage['vsites'] > 0) {
                    $this->line("  VSites:     <fg=cyan>{$usage['vsites']}</>");
                }
                if ($usage['vnodes'] > 0) {
                    $this->line("  VNodes:     <fg=cyan>{$usage['vnodes']}</>");
                }
                if ($usage['vhosts'] > 0) {
                    $this->line("  VHosts:     <fg=cyan>{$usage['vhosts']}</>");
                }

                if (array_sum($usage) === 0) {
                    $this->line('  <fg=gray>Not currently in use</>');
                }
            }

            $this->newLine();
        }

        // Zone details (always shown)
        if (isset($result['zones']) && count($result['zones']) > 0) {
            // Only show "Zones:" label with --all flag
            if ($this->option('all')) {
                $this->info('Zones:');
            }

            $this->table(
                ['ID', 'Name', 'Active', 'Records'],
                collect($result['zones'])->map(fn ($zone) => [
                    $zone['id'],
                    rtrim($zone['name'], '.'),  // Remove trailing dot for display
                    $zone['active'] ? '✅' : '❌',
                    $zone['records_count'],
                ])->toArray()
            );
        }

        // Connection test (only with --all or --test)
        if (isset($result['connection_test']) && ($this->option('all') || $this->option('test'))) {
            $this->newLine();
            $test = $result['connection_test'];

            if ($test['success']) {
                $this->info('✅ Connection Test: Successful');

                if (isset($test['server_info'])) {
                    $this->line("   Server:   <fg=cyan>{$test['server_info']}</>");
                }
                if (isset($test['zones_count'])) {
                    $this->line("   Zones:    <fg=cyan>{$test['zones_count']}</>");
                }
                if (isset($test['latency_ms'])) {
                    $this->line("   Latency:  <fg=cyan>{$test['latency_ms']}ms</>");
                }
            } else {
                $this->warn('⚠️ Connection Test: Failed');
                $this->line("   Error: {$test['message']}");
            }
        }

        // Health status (only with --all)
        if (isset($result['health']) && $this->option('all')) {
            $this->newLine();
            $health = $result['health'];

            if ($health['success']) {
                $status = $health['health']['overall_status'] ?? 'unknown';
                $statusIcon = match ($status) {
                    'healthy' => '✅',
                    'warning' => '⚠️',
                    'unhealthy' => '❌',
                    default => '❓'
                };

                $this->info("{$statusIcon} Health: ".strtoupper($status));

                if (isset($health['health']['checks'])) {
                    foreach ($health['health']['checks'] as $check => $passed) {
                        $icon = $passed ? '✅' : '❌';
                        $this->line("   {$icon} ".str_replace('_', ' ', ucfirst($check)));
                    }
                }
            }
        }

        // Import zones from remote
        if ($this->option('import-zones')) {
            $this->newLine();
            $this->info('📥 Importing zones from remote server...');

            try {
                $remoteZones = $this->tunnelService->getZones($provider);

                if (!is_array($remoteZones) || empty($remoteZones)) {
                    $this->error("Failed to fetch zones or no zones found");
                    return self::FAILURE;
                }

                $imported = 0;
                $skipped = 0;

                foreach ($remoteZones as $remoteZone) {
                    $zoneName = $remoteZone['name'] ?? null;

                    if (!$zoneName) {
                        continue;
                    }

                    // Check if zone already exists
                    $existingZone = \NetServa\Dns\Models\DnsZone::where('dns_provider_id', $provider->id)
                        ->where('name', $zoneName)
                        ->first();

                    if ($existingZone) {
                        $skipped++;
                        continue;
                    }

                    // Map PowerDNS kind to database kind (Master/Slave → Primary/Secondary)
                    $kind = $remoteZone['kind'] ?? 'Native';
                    $kindMap = [
                        'Master' => 'Primary',
                        'Slave' => 'Secondary',
                        'Primary' => 'Primary',
                        'Secondary' => 'Secondary',
                        'Native' => 'Native',
                        'Forwarded' => 'Forwarded',
                    ];
                    $kind = $kindMap[$kind] ?? 'Native';

                    // Import zone
                    \NetServa\Dns\Models\DnsZone::create([
                        'dns_provider_id' => $provider->id,
                        'external_id' => $remoteZone['id'] ?? $zoneName,
                        'name' => $zoneName,
                        'kind' => $kind,
                        'serial' => $remoteZone['serial'] ?? null,
                        'masters' => $remoteZone['masters'] ?? null,
                        'active' => true,
                        'provider_data' => $remoteZone,
                        'last_synced' => now(),
                    ]);

                    $imported++;
                }

                $this->newLine();
                $this->info("✅ Zone import complete:");
                $this->line("   Imported: <fg=cyan>{$imported}</>");
                $this->line("   Skipped (already exist): <fg=yellow>{$skipped}</>");
                $this->line("   Total zones now: <fg=cyan>" . ($imported + $skipped) . "</>");

            } catch (\Exception $e) {
                $this->error("Import failed: " . $e->getMessage());
                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }

    /**
     * Show list of providers
     */
    protected function showProviderList(): int
    {
        // Build filters
        $filters = [];

        if ($this->option('type')) {
            $filters['type'] = $this->option('type');
        }

        if ($this->option('active')) {
            $filters['active'] = true;
        } elseif ($this->option('inactive')) {
            $filters['active'] = false;
        }

        $filters['with_zones'] = $this->option('with-zones');
        $filters['with_usage'] = $this->option('with-usage');

        // Get providers
        $providers = $this->providerService->listProviders($filters);

        if ($providers->isEmpty()) {
            $this->warn('No DNS providers found');

            if (! empty($filters)) {
                $this->line('');
                $this->line('💡 Try removing filters or create a provider:');
                $this->line('   adddnsprovider "Homelab PowerDNS" powerdns --endpoint=http://192.168.1.1:8081 --api-key=secret');
            }

            return self::SUCCESS;
        }

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($providers, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Display table (no header unless --all)
        $headers = ['ID', 'Name', 'Type', 'Active'];

        // Add Description and Zones columns when --all
        if ($this->option('all')) {
            $headers[] = 'Description';
            $headers[] = 'Zones';
        } elseif ($this->option('with-zones')) {
            // Only add Zones if explicitly requested and not using --all
            $headers[] = 'Zones';
        }

        $headers[] = 'Connection';

        // Add Used By column when --all or --with-usage
        if ($this->option('all') || $this->option('with-usage')) {
            $headers[] = 'Used By';
        }

        $rows = $providers->map(function ($provider) {
            $row = [
                $provider->id,
                $provider->name,
                ucfirst($provider->type),
                $provider->active ? '✅' : '❌',
            ];

            // Add description and zones when --all
            if ($this->option('all')) {
                $row[] = $provider->description ?: '-';
                $row[] = $provider->zones->count();
            } elseif ($this->option('with-zones')) {
                // Only add zones if explicitly requested and not using --all
                $row[] = $provider->zones->count();
            }

            // Connection summary
            $config = $provider->connection_config ?? [];
            $connection = match ($provider->type) {
                'powerdns' => isset($config['ssh_host'])
                    ? "SSH: {$config['ssh_host']} → :{$config['api_port']}"
                    : ($config['api_endpoint'] ?? 'Not configured'),
                'cloudflare' => ($config['email'] ?? 'Email') . ($config['email'] ? ": {$config['email']}" : ''),
                'route53' => 'Region: '.($config['region'] ?? 'us-east-1'),
                default => $config['api_endpoint'] ?? 'Not configured',
            };
            $row[] = $connection;

            // Add Used By column when --all or --with-usage
            if ($this->option('all') || $this->option('with-usage')) {
                $counts = [
                    'Venues' => $provider->venues->count(),
                    'VSites' => $provider->vsites->count(),
                    'VNodes' => $provider->vnodes->count(),
                    'VHosts' => $provider->vhosts->count(),
                ];

                $usage = collect($counts)
                    ->filter(fn ($count) => $count > 0)
                    ->map(fn ($count, $type) => "{$count} {$type}")
                    ->join(', ') ?: 'Unused';

                $row[] = $usage;
            }

            return $row;
        })->toArray();

        $this->table($headers, $rows);

        // No footer/hints in default view - only show with --all
        if ($this->option('all')) {
            $this->newLine();
            $this->line('Total: <fg=cyan>'.$providers->count().'</> provider(s)');
        }

        return self::SUCCESS;
    }

    /**
     * Show zones overlay - calls shzone command under the hood
     */
    protected function showZonesOverlay(?string $providerIdentifier): int
    {
        // If specific provider specified, use it as filter
        if ($providerIdentifier) {
            // Call shzone with provider filter
            return $this->call('shzone', [
                '--provider' => $providerIdentifier,
            ]);
        }

        // No provider specified - show zones for all providers
        return $this->call('shzone');
    }

    /**
     * Show records overlay - calls shrec command under the hood
     */
    protected function showRecordsOverlay(?string $providerIdentifier): int
    {
        // If specific provider specified, filter by provider
        if ($providerIdentifier) {
            // Use showProvider to get provider with zones loaded
            $result = $this->providerService->showProvider($providerIdentifier, [
                'with_zones' => true,
            ]);

            if (!$result['success']) {
                $this->error("❌ {$result['message']}");
                return self::FAILURE;
            }

            $provider = $result['provider'];
            $zones = $provider->zones;

            if ($zones->isEmpty()) {
                $this->warn("⚠️ Provider '{$provider->name}' has no zones");
                return self::SUCCESS;
            }

            // Show records for all zones in this provider
            foreach ($zones as $zone) {
                $this->newLine();
                $this->line("<fg=yellow>Zone: {$zone->name}</> (Provider: {$provider->name})");
                $this->call('shrec', ['identifier' => $zone->name]);
            }

            return self::SUCCESS;
        }

        // No provider specified - show all records
        return $this->call('shrec');
    }
}
