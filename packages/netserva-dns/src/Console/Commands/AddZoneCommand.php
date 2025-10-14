<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsZoneManagementService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Add DNS Zone Command
 *
 * Creates a new DNS zone on a provider
 * Follows NetServa CRUD pattern: addzone (not "dns:zone:add")
 *
 * Usage: addzone <zone> <provider> [options]
 * Example: addzone example.com 1 --nameservers=ns1.example.com,ns2.example.com
 * Example: addzone test.local "Homelab PowerDNS" --kind=Native --auto-dnssec
 */
class AddZoneCommand extends Command
{
    protected $signature = 'addzone
        {zone? : Zone name (e.g., "example.com")}
        {provider? : Provider ID or name}
        {--kind=Native : Zone kind (Native, Primary, Secondary)}
        {--masters= : Master nameservers for Secondary zones (comma-separated)}
        {--nameservers=* : Authoritative nameservers for the zone}
        {--ttl=300 : Default TTL for zone records}
        {--admin-email=admin@example.com : Admin email for SOA record}
        {--description= : Zone description}
        {--auto-dnssec : Enable DNSSEC automatically}
        {--no-default-records : Skip creating default SOA/NS records}
        {--inactive : Create zone as inactive}
        {--skip-test : Skip connection test before creation}
        {--dry-run : Show what would be created without creating}';

    protected $description = 'Add a new DNS zone (NetServa CRUD pattern)';

    protected DnsZoneManagementService $zoneService;

    public function __construct(DnsZoneManagementService $zoneService)
    {
        parent::__construct();
        $this->zoneService = $zoneService;
    }

    public function handle(): int
    {
        // Get zone name
        $zoneName = $this->argument('zone');
        if (! $zoneName) {
            $zoneName = text(
                label: 'Zone name',
                placeholder: 'example.com',
                required: true,
                hint: 'Domain name for the zone'
            );
        }

        // Normalize zone name (ensure trailing dot for PowerDNS)
        $zoneName = rtrim($zoneName, '.');

        // Get provider
        $provider = $this->argument('provider');
        if (! $provider) {
            $provider = text(
                label: 'DNS Provider',
                placeholder: 'Provider ID or name',
                required: true,
                hint: 'Use "shdnsprovider" to list available providers'
            );
        }

        // Get zone kind if not specified
        $kind = $this->option('kind');
        if (! $kind || ! in_array($kind, ['Native', 'Primary', 'Secondary'])) {
            $kind = select(
                label: 'Zone kind',
                options: [
                    'Native' => 'Native (PowerDNS native replication)',
                    'Primary' => 'Primary (AXFR primary)',
                    'Secondary' => 'Secondary (AXFR secondary)',
                ],
                default: 'Native'
            );
        }

        // Get masters for Secondary zones
        $masters = $this->option('masters');
        if ($kind === 'Secondary' && ! $masters) {
            $mastersInput = text(
                label: 'Master nameservers',
                placeholder: '192.168.1.1,192.168.1.2',
                required: true,
                hint: 'Comma-separated list of master DNS servers'
            );
            $masters = explode(',', $mastersInput);
        } elseif ($masters) {
            $masters = is_array($masters) ? $masters : explode(',', $masters);
        }

        // Get nameservers
        $nameservers = $this->option('nameservers');
        if (empty($nameservers)) {
            if (confirm('Use default nameservers (ns1/ns2.example.com)?', true)) {
                $nameservers = ['ns1.example.com.', 'ns2.example.com.'];
            } else {
                $nsInput = text(
                    label: 'Nameservers',
                    placeholder: 'ns1.example.com,ns2.example.com',
                    required: true,
                    hint: 'Comma-separated authoritative nameservers'
                );
                $nameservers = array_map(
                    fn ($ns) => rtrim($ns, '.').'.',
                    explode(',', $nsInput)
                );
            }
        } else {
            // Ensure trailing dots
            $nameservers = array_map(
                fn ($ns) => rtrim($ns, '.').'.',
                is_array($nameservers) ? $nameservers : explode(',', $nameservers)
            );
        }

        // Build options
        $options = [
            'kind' => $kind,
            'masters' => $masters,
            'nameservers' => $nameservers,
            'ttl' => (int) $this->option('ttl'),
            'admin_email' => $this->option('admin-email'),
            'description' => $this->option('description'),
            'auto_dnssec' => $this->option('auto-dnssec'),
            'create_default_records' => ! $this->option('no-default-records'),
            'active' => ! $this->option('inactive'),
            'skip_test' => $this->option('skip-test'),
        ];

        // Show what we're about to create
        $this->newLine();
        $this->line("🚀 Creating DNS Zone: <fg=yellow>{$zoneName}</>");
        $this->line("   Provider: <fg=cyan>{$provider}</>");
        $this->line("   Kind: <fg=cyan>{$kind}</>");

        if ($masters) {
            $this->line('   Masters: <fg=cyan>'.implode(', ', $masters).'</>');
        }

        $this->line('   Nameservers: <fg=cyan>'.implode(', ', $nameservers).'</>');
        $this->line("   TTL: <fg=cyan>{$options['ttl']}</>");
        $this->line('   DNSSEC: <fg='.($options['auto_dnssec'] ? 'green>Enabled' : 'red>Disabled').'</>');
        $this->line('   Default Records: <fg='.($options['create_default_records'] ? 'green>Yes' : 'red>No').'</>');
        $this->line('   Status: <fg='.($options['active'] ? 'green>Active' : 'red>Inactive').'</>');

        // Dry run check
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('✅ Dry run complete - no changes made');
            $this->line('');
            $this->line('Would create zone with:');
            $this->line('  Zone: '.$zoneName);
            $this->line('  Provider: '.$provider);
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();

        // Create the zone
        $result = $this->zoneService->createZone(
            zoneName: $zoneName,
            providerId: $provider,
            options: $options
        );

        if (! $result['success']) {
            $this->error('❌ Failed to create DNS zone');
            $this->line("   Error: {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Details: {$result['error']}");
            }

            return self::FAILURE;
        }

        $zone = $result['zone'];

        $this->info('✅ DNS Zone created successfully');
        $this->line("   ID: <fg=yellow>{$zone->id}</>");
        $this->line("   Name: <fg=yellow>{$zone->name}</>");
        $this->line("   Provider: <fg=cyan>{$zone->dnsProvider->name}</>");
        $this->line("   Serial: <fg=cyan>{$zone->serial}</>");

        // Show DNSSEC info if enabled
        if ($options['auto_dnssec'] && isset($result['dnssec'])) {
            $this->newLine();
            $this->info('🔐 DNSSEC enabled');

            $dnssec = $result['dnssec'];
            if ($dnssec['success'] ?? false) {
                $this->line('   Status: <fg=green>Active</>');

                if (isset($dnssec['data']['ds_records'])) {
                    $this->line('   DS Records:');
                    foreach ($dnssec['data']['ds_records'] as $ds) {
                        $this->line("     {$ds}");
                    }
                }
            } else {
                $this->warn('   ⚠️ DNSSEC setup failed: '.($dnssec['message'] ?? 'Unknown error'));
            }
        }

        // Show default records created
        if ($options['create_default_records']) {
            $this->newLine();
            $this->info('📝 Default records created:');
            $this->line('   - SOA record');
            $this->line('   - NS records ('.count($nameservers).')');
        }

        // Show next steps
        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line("   - Add A record: adddns A www {$zone->id} 192.168.1.100");
        $this->line("   - Add MX record: adddns MX @ {$zone->id} mail.example.com --priority=10");
        $this->line("   - View zone: shzone {$zone->id}");
        $this->line("   - View records: shdns --zone={$zone->id}");

        if ($options['auto_dnssec']) {
            $this->line('   - Get DS records: shzone '.$zone->id.' --with-dnssec');
        }

        return self::SUCCESS;
    }
}
