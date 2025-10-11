<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsZoneManagementService;

use function Laravel\Prompts\confirm;

/**
 * Delete DNS Zone Command
 *
 * Delete DNS zone from provider
 * Follows NetServa CRUD pattern: delzone (not "dns:zone:delete")
 *
 * Usage: delzone <zone> [options]
 * Example: delzone example.com
 * Example: delzone 1 --cascade         # Delete zone and all records
 * Example: delzone old.com --force     # Force deletion even if has records
 */
class DeleteZoneCommand extends Command
{
    protected $signature = 'delzone
        {zone : Zone ID or name}
        {--cascade : Delete all records in the zone}
        {--force : Force deletion without confirmation}
        {--skip-remote : Skip remote deletion (local only)}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete DNS zone (NetServa CRUD pattern)';

    protected DnsZoneManagementService $zoneService;

    public function __construct(DnsZoneManagementService $zoneService)
    {
        parent::__construct();
        $this->zoneService = $zoneService;
    }

    public function handle(): int
    {
        $identifier = $this->argument('zone');

        // First, get zone details to show user what will be deleted
        $showResult = $this->zoneService->showZone($identifier, ['with_records' => true]);

        if (! $showResult['success']) {
            $this->error("❌ {$showResult['message']}");

            return self::FAILURE;
        }

        $zone = $showResult['zone'];
        $provider = $showResult['provider'];
        $recordsCount = $showResult['records_count'] ?? 0;

        // Display zone information
        $this->newLine();
        $this->line("🗑️  Deleting DNS Zone: <fg=yellow>{$zone->name}</>");
        $this->line(str_repeat('─', 60));
        $this->line("ID: <fg=cyan>{$zone->id}</>");
        $this->line("Provider: <fg=cyan>{$provider->name}</>");
        $this->line("Kind: <fg=cyan>{$zone->kind}</>");
        $this->line("Records: <fg=cyan>{$recordsCount}</>");

        if ($zone->dnssec_enabled) {
            $this->line("DNSSEC: <fg=yellow>Enabled</> ⚠️");
        }

        // Check for records
        $hasRecords = $recordsCount > 0;

        if ($hasRecords) {
            $this->newLine();
            $this->warn("⚠️  This zone has {$recordsCount} record(s)");

            if (! $this->option('cascade') && ! $this->option('force')) {
                $this->line('');
                $this->line('Options:');
                $this->line('  --cascade : Delete zone and all records');
                $this->line('  --force   : Force deletion (keeps records orphaned)');
                $this->line('');
                $this->error('❌ Cannot delete zone with records');
                $this->line('   Use --cascade to delete all records, or --force to override');

                return self::FAILURE;
            }

            if ($this->option('cascade')) {
                $this->line("<fg=red>⚠️  All {$recordsCount} record(s) will be deleted!</>");
            }
        }

        // DNSSEC warning
        if ($zone->dnssec_enabled) {
            $this->newLine();
            $this->warn('⚠️  DNSSEC is enabled for this zone');
            $this->line('   Remember to remove DS records from parent zone');
        }

        // Dry run check
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would delete:');
            $this->line('  Zone: ' . $zone->name);
            $this->line('  Records: ' . ($this->option('cascade') ? $recordsCount : 0));
            $this->line('  Remote: ' . ($this->option('skip-remote') ? 'No' : 'Yes'));

            return self::SUCCESS;
        }

        // Confirmation (unless forced)
        if (! $this->option('force')) {
            $this->newLine();

            $message = $hasRecords && $this->option('cascade')
                ? "Delete zone '{$zone->name}' and all {$recordsCount} record(s)?"
                : "Delete zone '{$zone->name}'?";

            $confirmed = confirm(
                label: $message,
                default: false,
                hint: 'This action cannot be undone'
            );

            if (! $confirmed) {
                $this->newLine();
                $this->info('✅ Deletion cancelled');

                return self::SUCCESS;
            }
        }

        // Prepare deletion options
        $options = [
            'cascade' => $this->option('cascade'),
            'force' => $this->option('force'),
            'skip_remote' => $this->option('skip-remote'),
        ];

        // Delete the zone
        $this->newLine();
        $this->line('🗑️  Deleting zone...');

        $result = $this->zoneService->deleteZone(
            identifier: $identifier,
            options: $options
        );

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Error: {$result['error']}");
            }

            if (isset($result['hint'])) {
                $this->line("   Hint: {$result['hint']}");
            }

            return self::FAILURE;
        }

        $zoneName = $result['zone']->name;
        $recordsDeleted = $result['records_deleted'] ?? 0;

        $this->newLine();
        $this->info("✅ DNS Zone '{$zoneName}' deleted successfully");

        if ($recordsDeleted > 0) {
            $this->line("   Records deleted: <fg=cyan>{$recordsDeleted}</>");
        }

        if ($this->option('skip-remote')) {
            $this->newLine();
            $this->warn('⚠️  Zone deleted locally only');
            $this->line('   Remote zone still exists on provider');
        }

        // DNSSEC reminder
        if ($zone->dnssec_enabled) {
            $this->newLine();
            $this->warn('⚠️  Remember to:');
            $this->line('   - Remove DS records from parent zone');
            $this->line('   - Wait for TTL expiration before reusing domain');
        }

        // Show next steps
        $this->newLine();
        $this->line('💡 Next steps:');
        $this->line('   - View remaining zones: shzone --provider=' . $provider->id);
        $this->line('   - Create new zone: addzone <domain> ' . $provider->id);

        return self::SUCCESS;
    }
}
