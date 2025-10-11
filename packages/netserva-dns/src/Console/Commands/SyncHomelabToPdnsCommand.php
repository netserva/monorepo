<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsZone;

/**
 * Sync Homelab Laravel DB to gw PowerDNS
 *
 * One-way sync from Laravel homelab provider to PowerDNS on gw
 * Used to ensure gw PowerDNS matches the source-of-truth in Laravel
 */
class SyncHomelabToPdnsCommand extends Command
{
    protected $signature = 'dns:sync-homelab-to-pdns
        {--dry-run : Show what would be changed without applying}
        {--force : Skip confirmation prompts}';

    protected $description = 'Sync homelab DNS provider records from Laravel DB to gw PowerDNS';

    public function handle(): int
    {
        $this->info('🔄 DNS Sync: Homelab Laravel DB → gw PowerDNS');
        $this->newLine();

        // Get homelab provider
        $homelab = DnsProvider::where('name', 'homelab')->first();

        if (!$homelab) {
            $this->error('❌ Homelab provider not found in database');
            return self::FAILURE;
        }

        $this->line("Provider: <fg=cyan>{$homelab->name}</> (ID: {$homelab->id})");
        $this->line("Type: <fg=cyan>{$homelab->type}</>");
        $this->newLine();

        // Get all zones for homelab provider
        $zones = $homelab->zones()->with('records')->get();

        if ($zones->isEmpty()) {
            $this->warn('⚠️ No zones found for homelab provider');
            return self::SUCCESS;
        }

        $this->info("Found {$zones->count()} zones to sync:");
        foreach ($zones as $zone) {
            $this->line("  • {$zone->name} ({$zone->records->count()} records)");
        }
        $this->newLine();

        // Confirm unless --force
        if (!$this->option('dry-run') && !$this->option('force')) {
            if (!$this->confirm('Proceed with sync to gw PowerDNS?')) {
                $this->warn('Sync cancelled');
                return self::SUCCESS;
            }
            $this->newLine();
        }

        $stats = [
            'zones_added' => 0,
            'zones_updated' => 0,
            'records_added' => 0,
            'records_updated' => 0,
            'records_deleted' => 0,
        ];

        // Sync each zone
        foreach ($zones as $zone) {
            $this->line("📍 Processing zone: <fg=yellow>{$zone->name}</>");

            $zoneStats = $this->syncZone($zone, $this->option('dry-run'));

            $stats['zones_added'] += $zoneStats['zone_added'] ? 1 : 0;
            $stats['zones_updated'] += $zoneStats['zone_updated'] ? 1 : 0;
            $stats['records_added'] += $zoneStats['records_added'];
            $stats['records_updated'] += $zoneStats['records_updated'];
            $stats['records_deleted'] += $zoneStats['records_deleted'];

            if ($this->option('dry-run')) {
                $this->line("  <fg=gray>[DRY RUN]</> Would add/update: {$zoneStats['records_added']} records");
            } else {
                $this->line("  ✅ Synced: +{$zoneStats['records_added']} ~{$zoneStats['records_updated']} -{$zoneStats['records_deleted']}");
            }
        }

        // Summary
        $this->newLine();
        $this->info('📊 Sync Summary:');
        $this->line("  Zones processed: <fg=cyan>{$zones->count()}</>");
        $this->line("  Records added: <fg=green>{$stats['records_added']}</>");
        $this->line("  Records updated: <fg=yellow>{$stats['records_updated']}</>");
        $this->line("  Records deleted: <fg=red>{$stats['records_deleted']}</>");

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->warn('🔍 DRY RUN - No changes were made');
            $this->line('Run without --dry-run to apply changes');
        } else {
            $this->newLine();
            $this->info('✅ Sync complete!');
        }

        return self::SUCCESS;
    }

    /**
     * Sync a single zone to PowerDNS
     */
    protected function syncZone(DnsZone $zone, bool $dryRun): array
    {
        $stats = [
            'zone_added' => false,
            'zone_updated' => false,
            'records_added' => 0,
            'records_updated' => 0,
            'records_deleted' => 0,
        ];

        if ($dryRun) {
            // In dry-run, just count records
            $stats['records_added'] = $zone->records->count();
            return $stats;
        }

        // Check if zone exists in PowerDNS
        $zoneExistsCommand = "sudo sqlite3 /etc/powerdns/pdns.sqlite3 \"SELECT COUNT(*) FROM domains WHERE name = '{$zone->name}';\"";
        $zoneExists = (int) trim($this->runRemoteCommand($zoneExistsCommand));

        if ($zoneExists === 0) {
            // Add zone
            $this->runRemoteCommand(
                "sudo sqlite3 /etc/powerdns/pdns.sqlite3 \"INSERT INTO domains (name, type) VALUES ('{$zone->name}', 'NATIVE');\""
            );
            $stats['zone_added'] = true;
        }

        // Get domain_id
        $domainIdCommand = "sudo sqlite3 /etc/powerdns/pdns.sqlite3 \"SELECT id FROM domains WHERE name = '{$zone->name}';\"";
        $domainId = (int) trim($this->runRemoteCommand($domainIdCommand));

        // Clear existing records for this zone (full replace strategy)
        $this->runRemoteCommand(
            "sudo sqlite3 /etc/powerdns/pdns.sqlite3 \"DELETE FROM records WHERE domain_id = {$domainId};\""
        );

        // Add all records from Laravel DB
        foreach ($zone->records as $record) {
            $name = $this->escapeSQL($record->name);
            $type = $this->escapeSQL($record->type);
            $content = $this->escapeSQL($record->content);
            $ttl = $record->ttl ?? 300;
            $prio = $record->priority ?? 0;

            $insertSQL = "INSERT INTO records (domain_id, name, type, content, ttl, prio) VALUES ({$domainId}, '{$name}', '{$type}', '{$content}', {$ttl}, {$prio});";

            $this->runRemoteCommand(
                "sudo sqlite3 /etc/powerdns/pdns.sqlite3 \"{$insertSQL}\""
            );

            $stats['records_added']++;
        }

        return $stats;
    }

    /**
     * Run command on remote gw server
     */
    protected function runRemoteCommand(string $command): string
    {
        $fullCommand = "ssh gw \"{$command}\"";
        return shell_exec($fullCommand) ?? '';
    }

    /**
     * Escape SQL string
     */
    protected function escapeSQL(string $value): string
    {
        return str_replace("'", "''", $value);
    }
}
