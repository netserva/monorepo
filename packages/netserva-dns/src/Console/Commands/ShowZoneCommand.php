<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsZoneManagementService;

/**
 * Show DNS Zone Command
 *
 * Display DNS zone information
 * Follows NetServa CRUD pattern: shzone (not "dns:zone:show")
 *
 * Usage: shzone [zone] [options]
 * Example: shzone                          # List all zones
 * Example: shzone example.com              # Show specific zone (name-based lookup)
 * Example: shzone 1 --with-dnssec          # Show with DNSSEC details (ID-based lookup)
 * Example: shzone --provider=homelab --active  # Filter zones
 */
class ShowZoneCommand extends Command
{
    protected $signature = 'shzone
        {zone? : Zone ID or name (shows all if omitted)}
        {--provider= : Filter by provider ID or name}
        {--type= : Filter by zone kind (Native, Master, Secondary)}
        {--active : Show only active zones}
        {--inactive : Show only inactive zones}
        {--dnssec : Show only DNSSEC-enabled zones}
        {--with-records : Include record count}
        {--with-dnssec : Include DNSSEC information}
        {--with-metadata : Include zone metadata}
        {--sync : Sync from remote before showing}
        {--test : Test provider connection}
        {--json : Output as JSON}
        {--all : Show detailed information (timestamps, metadata)}';

    protected $description = 'Show DNS zone information (NetServa CRUD pattern)';

    protected DnsZoneManagementService $zoneService;

    public function __construct(DnsZoneManagementService $zoneService)
    {
        parent::__construct();
        $this->zoneService = $zoneService;
    }

    public function handle(): int
    {
        $identifier = $this->argument('zone');

        // Show specific zone
        if ($identifier) {
            return $this->showSingleZone($identifier);
        }

        // List all zones with filters
        return $this->listZones();
    }

    /**
     * Show single zone details
     */
    protected function showSingleZone(string $identifier): int
    {
        $options = [
            'with_records' => $this->option('with-records'),
            'with_dnssec' => $this->option('with-dnssec'),
            'with_metadata' => $this->option('with-metadata'),
            'sync' => $this->option('sync'),
            'test' => $this->option('test'),
        ];

        $result = $this->zoneService->showZone($identifier, $options);

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            return self::FAILURE;
        }

        $zone = $result['zone'];
        $provider = $result['provider'];

        // JSON output
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Standard output
        $this->newLine();
        $zoneName = rtrim($zone->name, '.');
        $this->line("🌐 DNS Zone: <fg=yellow>{$zoneName}</>");
        $this->line(str_repeat('─', 60));

        // Basic Info
        $this->line("ID: <fg=cyan>{$zone->id}</>");
        $this->line("Provider: <fg=cyan>{$provider->name}</> (ID: {$provider->id})");
        $this->line("Kind: <fg=cyan>{$zone->kind}</>");
        $this->line("TTL: <fg=cyan>{$zone->ttl}s</>");
        $this->line("Serial: <fg=cyan>{$zone->serial}</>");

        $statusColor = $zone->active ? 'green' : 'red';
        $statusText = $zone->active ? 'Active' : 'Inactive';
        $this->line("Status: <fg={$statusColor}>{$statusText}</>");

        if ($zone->masters) {
            $this->line("Masters: <fg=cyan>" . implode(', ', $zone->masters) . "</>");
        }

        if ($zone->nameservers) {
            $this->line("Nameservers:");
            foreach ($zone->nameservers as $ns) {
                $this->line("  - <fg=cyan>{$ns}</>");
            }
        }

        if ($zone->description) {
            $this->line("Description: <fg=gray>{$zone->description}</>");
        }

        // Records count
        if ($options['with_records']) {
            $this->newLine();
            $this->line("📝 Records:");
            $this->line("   Total: <fg=cyan>{$result['records_count']}</>");
            $this->line("   Active: <fg=cyan>{$result['active_records_count']}</>");
            $disabledCount = $result['records_count'] - $result['active_records_count'];
            if ($disabledCount > 0) {
                $this->line("   Disabled: <fg=yellow>{$disabledCount}</>");
            }
        }

        // DNSSEC information
        if ($options['with_dnssec']) {
            $this->newLine();
            $dnssecEnabled = $zone->dnssec_enabled;
            $dnssecColor = $dnssecEnabled ? 'green' : 'red';
            $dnssecStatus = $dnssecEnabled ? 'Enabled' : 'Disabled';
            $this->line("🔐 DNSSEC: <fg={$dnssecColor}>{$dnssecStatus}</>");

            if ($dnssecEnabled && isset($result['dnssec_status'])) {
                $dnssec = $result['dnssec_status'];

                if ($dnssec['success'] && isset($dnssec['data']['cryptokeys'])) {
                    $this->line("   Keys:");
                    foreach ($dnssec['data']['cryptokeys'] as $key) {
                        $activeStatus = $key['active'] ? 'Active' : 'Inactive';
                        $this->line("     - ID {$key['id']}: {$key['keytype']} ({$activeStatus})");
                    }
                }

                if (isset($dnssec['data']['ds_records'])) {
                    $this->line("   DS Records:");
                    foreach ($dnssec['data']['ds_records'] as $ds) {
                        $this->line("     {$ds}");
                    }
                }
            }
        }

        // Metadata
        if ($options['with_metadata'] && isset($result['metadata'])) {
            $this->newLine();
            $this->line("ℹ️ Metadata:");
            $metadata = $result['metadata'];
            if ($metadata['success'] && isset($metadata['data'])) {
                foreach ($metadata['data'] as $key => $values) {
                    $this->line("   {$key}: " . implode(', ', $values));
                }
            }
        }

        // Sync result
        if ($options['sync'] && isset($result['sync_result'])) {
            $this->newLine();
            $sync = $result['sync_result'];
            if ($sync['success']) {
                $this->info("✅ Synced from remote");
                $this->line("   Records synced: <fg=cyan>{$sync['records_synced']}</>");
            } else {
                $this->warn("⚠️ Sync failed: {$sync['message']}");
            }
        }

        // Connection test
        if ($options['test'] && isset($result['connection_test'])) {
            $this->newLine();
            $test = $result['connection_test'];
            if ($test['success']) {
                $this->info('✅ Provider connection successful');
                if (isset($test['server_info'])) {
                    $this->line("   Server: <fg=cyan>{$test['server_info']}</>");
                }
            } else {
                $this->warn("⚠️ Connection test failed: {$test['message']}");
            }
        }

        // Timestamps
        if ($this->option('all')) {
            $this->newLine();
            $this->line("🕐 Timestamps:");
            $this->line("   Created: <fg=gray>{$zone->created_at}</>");
            $this->line("   Updated: <fg=gray>{$zone->updated_at}</>");

            if ($zone->last_check) {
                $this->line("   Last Check: <fg=gray>{$zone->last_check}</>");
            }

            if ($zone->last_synced) {
                $this->line("   Last Synced: <fg=gray>{$zone->last_synced}</>");
            }
        }

        // Next steps
        $this->newLine();
        $this->line("💡 Available actions:");
        $this->line("   - View records: shrec {$zoneName}");
        $this->line("   - Add record: addrec A www {$zoneName} 192.168.1.100");
        $this->line("   - Update zone: chzone {$zoneName} --ttl=7200");
        $this->line("   - Delete zone: delzone {$zoneName}");

        return self::SUCCESS;
    }

    /**
     * List all zones with filters
     */
    protected function listZones(): int
    {
        $filters = [];

        if ($this->option('provider')) {
            $filters['provider'] = $this->option('provider');
        }

        if ($this->option('type')) {
            $filters['type'] = $this->option('type');
        }

        if ($this->option('active')) {
            $filters['active'] = true;
        } elseif ($this->option('inactive')) {
            $filters['active'] = false;
        }

        if ($this->option('dnssec')) {
            $filters['dnssec'] = true;
        }

        $zones = $this->zoneService->listZones($filters);

        if ($zones->isEmpty()) {
            $this->newLine();
            $this->warn('⚠️ No DNS zones found');

            if (! empty($filters)) {
                $this->line('');
                $this->line('Active filters:');
                foreach ($filters as $key => $value) {
                    $this->line("  {$key}: {$value}");
                }
            }

            $this->newLine();
            $this->line('💡 Create your first zone:');
            $this->line('   addzone example.com <dns-provider-id>');

            return self::SUCCESS;
        }

        // JSON output
        if ($this->option('json')) {
            $this->line($zones->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Table output - only show header with --all
        if ($this->option('all')) {
            $this->newLine();
            $this->line('🌐 DNS Zones');
            $this->line(str_repeat('─', 100));
        }

        $rows = [];
        foreach ($zones as $zone) {
            $status = $zone->active ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $dnssec = $zone->dnssec_enabled ? '<fg=green>Yes</>' : '<fg=gray>No</>';
            $recordsCount = $this->option('with-records')
                ? $zone->getRecordCount()
                : ($zone->records_count ?? '-');

            $rows[] = [
                $zone->id,
                rtrim($zone->name, '.'),  // Remove trailing dot for display
                $zone->dnsProvider->name,
                $zone->kind,
                $recordsCount,
                $dnssec,
                $status,
            ];
        }

        $headers = ['ID', 'Zone', 'Provider', 'Kind', 'Records', 'DNSSEC', 'Active'];
        $this->table($headers, $rows);

        // Summary - only show with --all
        if ($this->option('all')) {
            $this->newLine();
            $this->line("Total zones: <fg=cyan>{$zones->count()}</>");

            $activeCount = $zones->where('active', true)->count();
            $this->line("Active: <fg=green>{$activeCount}</>");

            $dnssecCount = $zones->where('dnssec_enabled', true)->count();
            if ($dnssecCount > 0) {
                $this->line("DNSSEC enabled: <fg=green>{$dnssecCount}</>");
            }

            // Next steps removed - not needed with --all
        }

        return self::SUCCESS;
    }
}
