<?php

namespace NetServa\Dns\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Laravel\Prompts\Progress;
use Laravel\Prompts\table;
use NetServa\Dns\Services\CloudFlareApiException;
use NetServa\Dns\Services\CloudFlareRateLimitException;
use NetServa\Dns\Services\CloudFlareService;
use NetServa\Dns\Services\CloudFlareValidationException;

/**
 * CloudFlare DNS Records Management Command
 *
 * Manage DNS records in CloudFlare zones with comprehensive operations
 */
class CloudFlareRecordsCommand extends Command
{
    protected $signature = 'dns:cloudflare:records
                           {action : Action to perform (list, show, create, update, delete, bulk-import, export)}
                           {zone? : Zone name or ID}
                           {record? : Record name or ID for specific operations}
                           {--type= : DNS record type filter (A, AAAA, CNAME, MX, TXT, etc.)}
                           {--name= : Record name filter}
                           {--content= : Record content for create/update}
                           {--ttl=3600 : Record TTL (time to live)}
                           {--priority= : Record priority (for MX, SRV records)}
                           {--proxied : Enable CloudFlare proxy (orange cloud)}
                           {--format=table : Output format (table, json, dns-zone)}
                           {--file= : File path for import/export operations}
                           {--dry-run : Show what would be done without executing}
                           {--force : Skip confirmation prompts}';

    protected $description = 'Manage CloudFlare DNS records';

    protected CloudFlareService $cloudflare;

    public function __construct(CloudFlareService $cloudflare)
    {
        parent::__construct();
        $this->cloudflare = $cloudflare;
    }

    public function handle(): int
    {
        $action = $this->argument('action');
        $zone = $this->argument('zone');
        $record = $this->argument('record');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - CloudFlare records {$action}".($zone ? " for {$zone}" : ''));
        }

        try {
            // Test CloudFlare connection first
            $connectionTest = $this->cloudflare->testConnection();
            if (! $connectionTest['success']) {
                $this->error('❌ CloudFlare connection failed: '.$connectionTest['message']);

                return self::FAILURE;
            }

            return match ($action) {
                'list' => $this->listRecords($zone),
                'show' => $this->showRecord($zone, $record),
                'create' => $this->createRecord($zone, $isDryRun),
                'update' => $this->updateRecord($zone, $record, $isDryRun),
                'delete' => $this->deleteRecord($zone, $record, $isDryRun),
                'bulk-import' => $this->bulkImportRecords($zone, $isDryRun),
                'export' => $this->exportRecords($zone),
                default => $this->error("❌ Unknown action: {$action}") ?: self::FAILURE
            };

        } catch (CloudFlareValidationException $e) {
            $this->error('❌ Validation error: '.$e->getMessage());

            return self::FAILURE;
        } catch (CloudFlareRateLimitException $e) {
            $this->error('❌ CloudFlare rate limit exceeded: '.$e->getMessage());
            $this->warn('💡 Please wait before retrying');

            return self::FAILURE;
        } catch (CloudFlareApiException $e) {
            $this->error('❌ CloudFlare API error: '.$e->getMessage());
            Log::error('CloudFlare records command failed', [
                'action' => $action,
                'zone' => $zone,
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        } catch (Exception $e) {
            $this->error('❌ Command failed: '.$e->getMessage());
            Log::error('CloudFlare records command exception', [
                'action' => $action,
                'zone' => $zone,
                'record' => $record,
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    protected function listRecords(?string $zone): int
    {
        if (! $zone) {
            $zones = $this->cloudflare->listZones();
            $zoneOptions = array_map(fn ($z) => $z['name'], $zones);

            $zone = select(
                label: 'Select zone to list records:',
                options: $zoneOptions,
                required: true
            );
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        $this->info("📋 Listing DNS records for zone: {$zoneData['name']}");

        // Build query parameters
        $params = [];
        if ($typeFilter = $this->option('type')) {
            $params['type'] = strtoupper($typeFilter);
        }
        if ($nameFilter = $this->option('name')) {
            $params['name'] = $nameFilter;
        }

        $records = $this->cloudflare->listDnsRecords($zoneData['id'], $params);

        if (empty($records)) {
            $this->warn('⚠️ No DNS records found');

            return self::SUCCESS;
        }

        $this->info('📋 Found '.count($records).' DNS records');

        $format = $this->option('format');

        if ($format === 'json') {
            $this->line(json_encode($records, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        if ($format === 'dns-zone') {
            $this->outputDnsZoneFormat($records, $zoneData['name']);

            return self::SUCCESS;
        }

        // Table format
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                $record['name'] ?? 'N/A',
                $record['type'] ?? 'N/A',
                $this->truncateContent($record['content'] ?? 'N/A', 40),
                $record['ttl'] ?? 'Auto',
                isset($record['proxied']) ? ($record['proxied'] ? '🟠' : '⚪') : 'N/A',
                substr($record['id'] ?? 'N/A', 0, 8).'...',
            ];
        }

        table(
            ['Name', 'Type', 'Content', 'TTL', 'Proxy', 'ID'],
            $rows
        );

        return self::SUCCESS;
    }

    protected function showRecord(?string $zone, ?string $record): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        if (! $record) {
            $records = $this->cloudflare->listDnsRecords($zoneData['id']);
            $recordOptions = [];
            foreach ($records as $r) {
                $recordOptions[$r['id']] = "{$r['name']} ({$r['type']})";
            }

            $recordId = select(
                label: 'Select record to show:',
                options: $recordOptions,
                required: true
            );

            $record = $recordId;
        }

        $this->info('🔍 Getting record details...');

        $recordData = $this->cloudflare->getDnsRecord($zoneData['id'], $record);

        if (! $recordData) {
            $this->error('❌ Record not found');

            return self::FAILURE;
        }

        if ($this->option('format') === 'json') {
            $this->line(json_encode($recordData, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('📋 DNS Record Details');

        $details = [
            ['Property', 'Value'],
            ['Record ID', $recordData['id'] ?? 'N/A'],
            ['Name', $recordData['name'] ?? 'N/A'],
            ['Type', $recordData['type'] ?? 'N/A'],
            ['Content', $recordData['content'] ?? 'N/A'],
            ['TTL', $recordData['ttl'] === 1 ? 'Auto' : ($recordData['ttl'] ?? 'N/A')],
            ['Priority', $recordData['priority'] ?? 'N/A'],
            ['Proxied', isset($recordData['proxied']) ? ($recordData['proxied'] ? 'Yes (🟠)' : 'No (⚪)') : 'N/A'],
            ['Created', isset($recordData['created_on']) ? date('Y-m-d H:i:s', strtotime($recordData['created_on'])) : 'N/A'],
            ['Modified', isset($recordData['modified_on']) ? date('Y-m-d H:i:s', strtotime($recordData['modified_on'])) : 'N/A'],
        ];

        table(['Property', 'Value'], array_slice($details, 1));

        return self::SUCCESS;
    }

    protected function createRecord(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        $this->info("🆕 Creating DNS record for zone: {$zoneData['name']}");

        // Collect record data
        $recordData = $this->collectRecordData($zoneData['name']);

        if ($isDryRun) {
            $this->info('🔍 DRY RUN - Would create record:');
            $this->displayRecordSummary($recordData);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->info('📋 Record Summary:');
            $this->displayRecordSummary($recordData);

            if (! confirm('Create this DNS record?', true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $progress = new Progress('Creating DNS record...', 2);

        try {
            $progress->label('Creating record in CloudFlare...');
            $result = $this->cloudflare->createDnsRecord($zoneData['id'], $recordData);
            $progress->advance();

            $progress->label('Verifying record creation...');
            $progress->advance();

            $progress->finish();

            $this->info('✅ DNS record created successfully');
            $this->info("🆔 Record ID: {$result['id']}");
            $this->info("📛 Record Name: {$result['name']}");

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function updateRecord(?string $zone, ?string $record, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        if (! $record) {
            $record = $this->selectRecord($zoneData['id']);
        }

        $currentRecord = $this->cloudflare->getDnsRecord($zoneData['id'], $record);
        if (! $currentRecord) {
            $this->error('❌ Record not found');

            return self::FAILURE;
        }

        $this->info("✏️ Updating DNS record: {$currentRecord['name']} ({$currentRecord['type']})");

        // Show current record
        $this->info('📋 Current Record:');
        $this->displayRecordSummary($currentRecord);

        // Collect new record data with current values as defaults
        $recordData = $this->collectRecordData($zoneData['name'], $currentRecord);

        if ($isDryRun) {
            $this->info('🔍 DRY RUN - Would update record to:');
            $this->displayRecordSummary($recordData);

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->info('📋 Updated Record:');
            $this->displayRecordSummary($recordData);

            if (! confirm('Update this DNS record?', true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $progress = new Progress('Updating DNS record...', 2);

        try {
            $progress->label('Updating record in CloudFlare...');
            $result = $this->cloudflare->updateDnsRecord($zoneData['id'], $record, $recordData);
            $progress->advance();

            $progress->label('Verifying record update...');
            $progress->advance();

            $progress->finish();

            $this->info('✅ DNS record updated successfully');
            $this->info("📛 Record Name: {$result['name']}");

            return self::SUCCESS;

        } catch (Exception $e) {
            $progress->finish();
            throw $e;
        }
    }

    protected function deleteRecord(?string $zone, ?string $record, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        if (! $record) {
            $record = $this->selectRecord($zoneData['id']);
        }

        $recordData = $this->cloudflare->getDnsRecord($zoneData['id'], $record);
        if (! $recordData) {
            $this->error('❌ Record not found');

            return self::FAILURE;
        }

        $this->error('⚠️  DNS Record Deletion Summary');
        $this->displayRecordSummary($recordData);

        if ($isDryRun) {
            $this->info("🔍 DRY RUN - Would delete record: {$recordData['name']}");

            return self::SUCCESS;
        }

        $this->warn('⚠️  This will permanently delete the DNS record!');

        if (! $this->option('force')) {
            if (! confirm("Delete DNS record '{$recordData['name']}' ({$recordData['type']})?", false)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $this->info("🗑️ Deleting DNS record: {$recordData['name']}");

        $result = $this->cloudflare->deleteDnsRecord($zoneData['id'], $record);

        if ($result) {
            $this->info('✅ DNS record deleted successfully');
        } else {
            $this->error('❌ Failed to delete DNS record');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function bulkImportRecords(?string $zone, bool $isDryRun): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        $file = $this->option('file') ?? text(
            label: 'Enter path to DNS records file (JSON or CSV):',
            placeholder: '/path/to/records.json',
            required: true
        );

        if (! file_exists($file)) {
            $this->error("❌ File not found: {$file}");

            return self::FAILURE;
        }

        $this->info("📂 Importing DNS records from: {$file}");

        $records = $this->parseImportFile($file);

        if (empty($records)) {
            $this->error('❌ No valid records found in file');

            return self::FAILURE;
        }

        $this->info('📋 Found '.count($records).' records to import');

        if ($isDryRun) {
            $this->info('🔍 DRY RUN - Would import '.count($records).' records');
            foreach ($records as $i => $record) {
                $this->line('  '.($i + 1).". {$record['name']} ({$record['type']}) -> {$record['content']}");
            }

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! confirm('Import '.count($records).' DNS records?', true)) {
                $this->info('⚠️ Operation cancelled');

                return self::SUCCESS;
            }
        }

        $progress = new Progress('Importing DNS records...', count($records));
        $successCount = 0;
        $errorCount = 0;

        foreach ($records as $record) {
            try {
                $progress->label("Creating {$record['name']} ({$record['type']})...");
                $this->cloudflare->createDnsRecord($zoneData['id'], $record);
                $successCount++;
            } catch (Exception $e) {
                $this->warn("Failed to create {$record['name']}: ".$e->getMessage());
                $errorCount++;
            }
            $progress->advance();
        }

        $progress->finish();

        $this->info("✅ Import completed: {$successCount} success, {$errorCount} errors");

        return self::SUCCESS;
    }

    protected function exportRecords(?string $zone): int
    {
        if (! $zone) {
            $zone = $this->selectZone();
        }

        $zoneData = $this->getZoneData($zone);
        if (! $zoneData) {
            return self::FAILURE;
        }

        $format = select(
            label: 'Export format:',
            options: [
                'json' => 'JSON',
                'csv' => 'CSV',
                'dns-zone' => 'DNS Zone File',
            ]
        );

        $file = $this->option('file') ?? text(
            label: 'Enter output file path:',
            placeholder: "/tmp/{$zoneData['name']}.{$format}",
            default: "/tmp/{$zoneData['name']}.{$format}"
        );

        $this->info("📤 Exporting DNS records for zone: {$zoneData['name']}");

        $records = $this->cloudflare->listDnsRecords($zoneData['id']);

        if (empty($records)) {
            $this->warn('⚠️ No DNS records found to export');

            return self::SUCCESS;
        }

        $content = match ($format) {
            'json' => json_encode($records, JSON_PRETTY_PRINT),
            'csv' => $this->recordsToCsv($records),
            'dns-zone' => $this->recordsToDnsZone($records, $zoneData['name'])
        };

        file_put_contents($file, $content);

        $this->info('✅ Exported '.count($records)." records to: {$file}");

        return self::SUCCESS;
    }

    // Helper methods

    protected function getZoneData(string $zone): ?array
    {
        $zoneData = $this->cloudflare->getZoneByName($zone) ?? $this->cloudflare->getZone($zone);

        if (! $zoneData) {
            $this->error("❌ Zone '{$zone}' not found");

            return null;
        }

        return $zoneData;
    }

    protected function selectZone(): string
    {
        $zones = $this->cloudflare->listZones();
        $zoneOptions = array_map(fn ($z) => $z['name'], $zones);

        return select(
            label: 'Select zone:',
            options: $zoneOptions,
            required: true
        );
    }

    protected function selectRecord(string $zoneId): string
    {
        $records = $this->cloudflare->listDnsRecords($zoneId);
        $recordOptions = [];
        foreach ($records as $r) {
            $recordOptions[$r['id']] = "{$r['name']} ({$r['type']}) -> {$this->truncateContent($r['content'], 30)}";
        }

        return select(
            label: 'Select record:',
            options: $recordOptions,
            required: true
        );
    }

    protected function collectRecordData(string $zoneName, ?array $current = null): array
    {
        $type = $this->option('type') ?? select(
            label: 'Record type:',
            options: ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'PTR', 'NS', 'CAA'],
            default: $current['type'] ?? 'A'
        );

        $name = text(
            label: 'Record name:',
            placeholder: $zoneName,
            default: $current['name'] ?? '',
            required: true
        );

        $content = $this->option('content') ?? text(
            label: 'Record content:',
            placeholder: $type === 'A' ? '192.168.1.1' : 'example.com',
            default: $current['content'] ?? '',
            required: true
        );

        $ttl = $this->option('ttl') ?? (int) text(
            label: 'TTL (seconds, 1 for auto):',
            default: (string) ($current['ttl'] ?? 3600),
            validate: fn ($value) => is_numeric($value) && $value > 0 ? null : 'TTL must be a positive number'
        );

        $data = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl,
        ];

        // Add priority for MX and SRV records
        if (in_array($type, ['MX', 'SRV'])) {
            $data['priority'] = $this->option('priority') ?? (int) text(
                label: 'Priority:',
                default: (string) ($current['priority'] ?? 10),
                validate: fn ($value) => is_numeric($value) && $value >= 0 ? null : 'Priority must be a non-negative number'
            );
        }

        // Add proxied option for supported types
        if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
            $data['proxied'] = $this->option('proxied') ?? confirm(
                'Enable CloudFlare proxy (orange cloud)?',
                $current['proxied'] ?? false
            );
        }

        return $data;
    }

    protected function displayRecordSummary(array $record): void
    {
        $rows = [
            ['Name', $record['name'] ?? 'N/A'],
            ['Type', $record['type'] ?? 'N/A'],
            ['Content', $record['content'] ?? 'N/A'],
            ['TTL', ($record['ttl'] ?? 0) === 1 ? 'Auto' : ($record['ttl'] ?? 'N/A')],
        ];

        if (isset($record['priority'])) {
            $rows[] = ['Priority', $record['priority']];
        }

        if (isset($record['proxied'])) {
            $rows[] = ['Proxied', $record['proxied'] ? 'Yes (🟠)' : 'No (⚪)'];
        }

        table(['Property', 'Value'], $rows);
    }

    protected function truncateContent(string $content, int $length): string
    {
        return strlen($content) > $length ? substr($content, 0, $length).'...' : $content;
    }

    protected function outputDnsZoneFormat(array $records, string $zoneName): void
    {
        $this->line(';');
        $this->line("; DNS Zone file for {$zoneName}");
        $this->line('; Generated by NetServa Platform - '.date('Y-m-d H:i:s'));
        $this->line(';');
        $this->line();

        foreach ($records as $record) {
            $name = str_replace(".{$zoneName}", '', $record['name']);
            if ($name === $zoneName) {
                $name = '@';
            }

            $line = sprintf(
                '%-30s %-6s %-6s %s',
                $name,
                $record['ttl'] === 1 ? '' : $record['ttl'],
                $record['type'],
                $record['content']
            );

            if (isset($record['priority'])) {
                $line = sprintf(
                    '%-30s %-6s %-6s %-6s %s',
                    $name,
                    $record['ttl'] === 1 ? '' : $record['ttl'],
                    $record['type'],
                    $record['priority'],
                    $record['content']
                );
            }

            $this->line($line);
        }
    }

    protected function parseImportFile(string $file): array
    {
        $content = file_get_contents($file);
        $extension = pathinfo($file, PATHINFO_EXTENSION);

        return match (strtolower($extension)) {
            'json' => json_decode($content, true) ?: [],
            'csv' => $this->parseCsvRecords($content),
            default => []
        };
    }

    protected function parseCsvRecords(string $content): array
    {
        $lines = array_filter(explode("\n", trim($content)));
        $records = [];

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            } // Skip header

            $fields = str_getcsv($line);
            if (count($fields) >= 4) {
                $records[] = [
                    'name' => $fields[0],
                    'type' => $fields[1],
                    'content' => $fields[2],
                    'ttl' => (int) ($fields[3] ?: 3600),
                    'priority' => isset($fields[4]) ? (int) $fields[4] : null,
                ];
            }
        }

        return array_filter($records, fn ($r) => ! empty($r['name']) && ! empty($r['type']) && ! empty($r['content']));
    }

    protected function recordsToCsv(array $records): string
    {
        $csv = "name,type,content,ttl,priority,proxied\n";

        foreach ($records as $record) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s\n",
                $record['name'] ?? '',
                $record['type'] ?? '',
                $record['content'] ?? '',
                $record['ttl'] ?? '',
                $record['priority'] ?? '',
                isset($record['proxied']) ? ($record['proxied'] ? 'true' : 'false') : ''
            );
        }

        return $csv;
    }

    protected function recordsToDnsZone(array $records, string $zoneName): string
    {
        ob_start();
        $this->outputDnsZoneFormat($records, $zoneName);

        return ob_get_clean();
    }
}
