<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsZoneManagementService;

/**
 * Change DNS Zone Command
 *
 * Update DNS zone configuration
 * Follows NetServa CRUD pattern: chzone <vnode> <zone> [options]
 *
 * Usage: chzone <vnode> <zone> [options]
 * Example: chzone ns1gc example.com --ttl=7200
 * Example: chzone ns1gc goldcoast.org --enable-dnssec
 */
class ChangeZoneCommand extends Command
{
    protected $signature = 'chzone
        {vnode : VNode identifier (DNS provider)}
        {zone : Zone name}
        {--kind= : Change zone kind (Native, Primary, Secondary)}
        {--masters= : Update master nameservers (comma-separated)}
        {--ttl= : Update default TTL}
        {--description= : Update description}
        {--activate : Set zone to active}
        {--deactivate : Set zone to inactive}
        {--enable-dnssec : Enable DNSSEC}
        {--disable-dnssec : Disable DNSSEC}
        {--test : Test provider connection after update}
        {--dry-run : Show what would change without changing}';

    protected $description = 'Change DNS zone configuration (NetServa CRUD pattern)';

    protected DnsZoneManagementService $zoneService;

    public function __construct(DnsZoneManagementService $zoneService)
    {
        parent::__construct();
        $this->zoneService = $zoneService;
    }

    public function handle(): int
    {
        $vnode = $this->argument('vnode');
        $zoneName = $this->argument('zone');

        // Build updates array from options
        $updates = [];

        // Zone kind
        if ($this->option('kind')) {
            $kind = $this->option('kind');
            if (! in_array($kind, ['Native', 'Primary', 'Secondary'])) {
                $this->error("❌ Invalid zone kind: {$kind}");
                $this->line('   Valid kinds: Native, Primary, Secondary');

                return self::FAILURE;
            }
            $updates['kind'] = $kind;
        }

        // Masters
        if ($this->option('masters')) {
            $masters = $this->option('masters');
            $updates['masters'] = is_array($masters)
                ? $masters
                : explode(',', $masters);
        }

        // TTL
        if ($this->option('ttl')) {
            $updates['ttl'] = (int) $this->option('ttl');
        }

        // Description
        if ($this->option('description')) {
            $updates['description'] = $this->option('description');
        }

        // Active/inactive status
        if ($this->option('activate')) {
            $updates['active'] = true;
        } elseif ($this->option('deactivate')) {
            $updates['active'] = false;
        }

        // DNSSEC
        if ($this->option('enable-dnssec')) {
            $updates['dnssec_enabled'] = true;
        } elseif ($this->option('disable-dnssec')) {
            $updates['dnssec_enabled'] = false;
        }

        // Check if any updates provided
        if (empty($updates)) {
            $this->error('❌ No updates specified');
            $this->line('');
            $this->line('Available options:');
            $this->line('  --kind, --masters, --ttl, --description');
            $this->line('  --activate, --deactivate');
            $this->line('  --enable-dnssec, --disable-dnssec');
            $this->line('');
            $this->line('Example: chzone example.com --ttl=7200 --test');

            return self::FAILURE;
        }

        // Prepare options
        $options = [
            'test' => $this->option('test'),
            'provider' => $vnode,
        ];

        // Dry run preview
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would update zone:');
            $this->line('  VNode: '.$vnode);
            $this->line('  Zone: '.$zoneName);
            $this->line('  Updates: '.json_encode($updates, JSON_PRETTY_PRINT));
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Update the zone
        $this->newLine();
        $this->line("🔧 Updating DNS Zone: <fg=yellow>{$zoneName}</> on <fg=cyan>{$vnode}</>");

        $result = $this->zoneService->updateZone(
            identifier: $zoneName,
            updates: $updates,
            options: $options
        );

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Error: {$result['error']}");
            }

            return self::FAILURE;
        }

        $zone = $result['zone'];
        $changes = $result['changes'] ?? [];

        // Display changes
        if (! empty($changes)) {
            $this->newLine();
            $this->info('Changes:');

            foreach ($changes as $field => $change) {
                $fieldName = ucfirst(str_replace('_', ' ', $field));
                $old = $this->formatValue($field, $change['old']);
                $new = $this->formatValue($field, $change['new']);

                $this->line("  {$fieldName}: <fg=gray>{$old}</> → <fg=cyan>{$new}</>");
            }

            $this->newLine();
            $this->info("✅ DNS Zone '{$zone->name}' updated successfully");
        } else {
            $this->info('ℹ️ No changes made (values already set)');
        }

        // Show connection test results
        if (isset($result['connection_test'])) {
            $this->newLine();
            $this->line('🔍 Testing connection...');

            $test = $result['connection_test'];

            if ($test['success']) {
                $this->info('✅ Connection successful');

                if (isset($test['server_info'])) {
                    $this->line("   Server: <fg=cyan>{$test['server_info']}</>");
                }
            } else {
                $this->warn('⚠️ Connection test failed');
                $this->line("   Error: {$test['message']}");
            }
        }

        // Show DNSSEC status if changed
        if (isset($updates['dnssec_enabled'])) {
            $this->newLine();
            if ($updates['dnssec_enabled']) {
                $this->info('🔐 DNSSEC enabled');
                $this->line('   Run: shzone '.$zone->id.' --with-dnssec (to view DS records)');
            } else {
                $this->warn('⚠️ DNSSEC disabled');
                $this->line('   Remember to remove DS records from parent zone');
            }
        }

        // Show next steps
        if (! empty($changes)) {
            $this->newLine();
            $this->line('💡 Next steps:');
            $this->line("   - View zone: shzone {$zone->id} --detailed");

            if (! $this->option('test')) {
                $this->line("   - Test connection: chzone {$zone->id} --test");
            }

            if (isset($updates['ttl'])) {
                $this->line('   - TTL changes may take up to 24h to propagate globally');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Format value for display
     */
    protected function formatValue(string $field, mixed $value): string
    {
        return match ($field) {
            'active', 'dnssec_enabled' => $value ? 'Enabled' : 'Disabled',
            'masters' => is_array($value) ? implode(', ', $value) : (string) $value,
            'ttl' => $value.'s',
            default => is_null($value) ? 'Not set' : (string) $value,
        };
    }
}
