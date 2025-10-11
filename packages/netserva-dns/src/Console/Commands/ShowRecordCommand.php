<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsRecordManagementService;

/**
 * Show DNS Record Command
 *
 * Display DNS record information
 * Follows NetServa CRUD pattern: shrec (not "dns:record:show")
 *
 * Usage: shrec [identifier] [options]
 * Example: shrec                       # List all records
 * Example: shrec 123                   # Show specific record by ID
 * Example: shrec example.com           # List all records for zone (name-based)
 * Example: shrec --type=A --active     # Filter records
 */
class ShowRecordCommand extends Command
{
    protected $signature = 'shrec
        {identifier? : Record ID or zone name/ID (shows all if omitted)}
        {--type= : Filter by record type (A, AAAA, MX, etc.)}
        {--active : Show only active records}
        {--inactive : Show only inactive/disabled records}
        {--search= : Search in name or content}
        {--content= : Filter by content}
        {--with-ptr : Show related PTR record (for A/AAAA)}
        {--with-forward : Show related forward record (for PTR)}
        {--sync : Sync from remote before showing}
        {--json : Output as JSON}
        {--all : Show detailed information (timestamps, metadata)}';

    protected $description = 'Show DNS records (NetServa CRUD pattern)';

    protected DnsRecordManagementService $recordService;

    public function __construct(DnsRecordManagementService $recordService)
    {
        parent::__construct();
        $this->recordService = $recordService;
    }

    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        if ($identifier) {
            // Try to determine if this is a record ID or zone identifier
            // Numeric-only = record ID, otherwise treat as zone
            if (is_numeric($identifier) && $identifier < 1000) {
                // Likely a record ID (low numbers)
                return $this->showSingleRecord($identifier);
            } else {
                // Treat as zone name/ID - list records for this zone
                return $this->listRecordsForZone($identifier);
            }
        }

        return $this->listRecords();
    }

    protected function showSingleRecord(string $identifier): int
    {
        $options = [
            'with_ptr' => $this->option('with-ptr'),
            'with_forward' => $this->option('with-forward'),
            'sync' => $this->option('sync'),
        ];

        $result = $this->recordService->showRecord($identifier, $options);

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            return self::FAILURE;
        }

        $record = $result['record'];
        $zone = $result['zone'];

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("📝 DNS Record: <fg=yellow>{$record->type} {$record->name}</>");
        $this->line(str_repeat('─', 60));
        $this->line("ID: <fg=cyan>{$record->id}</>");
        $this->line("Type: <fg=cyan>{$record->type}</>");
        $this->line("Name: <fg=cyan>{$record->name}</>");
        $this->line("Content: <fg=cyan>{$record->content}</>");
        $this->line("TTL: <fg=cyan>{$record->ttl}s</>");

        if ($record->requiresPriority()) {
            $this->line("Priority: <fg=cyan>{$record->priority}</>");
        }

        $statusColor = $record->isActive() ? 'green' : 'red';
        $statusText = $record->isActive() ? 'Active' : 'Disabled';
        $this->line("Status: <fg={$statusColor}>{$statusText}</>");

        $this->newLine();
        $this->line("Zone: <fg=cyan>{$zone->name}</> (ID: {$zone->id})");
        $this->line("Provider: <fg=cyan>{$result['provider']->name}</>");

        if ($record->comment) {
            $this->newLine();
            $this->line("Comment: <fg=gray>{$record->comment}</>");
        }

        if (isset($result['ptr_record']) && $result['ptr_record']) {
            $ptr = $result['ptr_record'];
            $this->newLine();
            $this->info('🔄 Related PTR Record (Reverse DNS):');
            $this->line("   PTR ID: <fg=cyan>{$ptr->id}</>");
            $this->line("   PTR Name: <fg=cyan>{$ptr->name}</>");
            $this->line("   Points to: <fg=cyan>{$ptr->content}</>");
            $this->line('   ✅ FCrDNS configured');
        }

        if (isset($result['forward_record']) && $result['forward_record']) {
            $forward = $result['forward_record'];
            $this->newLine();
            $this->info('🔄 Related Forward Record:');
            $this->line("   {$forward->type} ID: <fg=cyan>{$forward->id}</>");
            $this->line("   Name: <fg=cyan>{$forward->name}</>");
            $this->line("   IP: <fg=cyan>{$forward->content}</>");
        }

        if ($this->option('all')) {
            $this->newLine();
            $this->line("🕐 Timestamps:");
            $this->line("   Created: <fg=gray>{$record->created_at}</>");
            $this->line("   Updated: <fg=gray>{$record->updated_at}</>");
        }

        $this->newLine();
        $this->line("💡 Available actions:");
        $this->line("   - Update: chrec {$record->id} --content=<new-value>");
        $this->line("   - Delete: delrec {$record->id}");
        $this->line("   - View zone: shzone {$zone->id}");

        return self::SUCCESS;
    }

    protected function listRecords(array $filters = []): int
    {
        // Merge filters from options if not already set
        if (!isset($filters['type']) && $this->option('type')) {
            $filters['type'] = $this->option('type');
        }

        if (!isset($filters['active']) && $this->option('active')) {
            $filters['active'] = true;
        } elseif (!isset($filters['active']) && $this->option('inactive')) {
            $filters['active'] = false;
        }

        if (!isset($filters['search']) && $this->option('search')) {
            $filters['search'] = $this->option('search');
        }

        if (!isset($filters['content']) && $this->option('content')) {
            $filters['content'] = $this->option('content');
        }

        $records = $this->recordService->listRecords($filters);

        if ($records->isEmpty()) {
            $this->newLine();
            $this->warn('⚠️ No DNS records found');

            if (! empty($filters)) {
                $this->line('');
                $this->line('Active filters:');
                foreach ($filters as $key => $value) {
                    $displayValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                    $this->line("  {$key}: {$displayValue}");
                }
            }

            $this->newLine();
            $this->line('💡 Create your first record:');
            $this->line('   addrec A www <zone> 192.168.1.100');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($records->toJson(JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        // Check if we're viewing a single zone (all records have same zone)
        $singleZone = $records->first()?->zone;
        $isSingleZoneView = isset($filters['zone']) && $records->every(fn($r) => $r->zone->id === $singleZone->id);

        // Only show header/separator when viewing all records or with --all flag
        if (!$isSingleZoneView || $this->option('all')) {
            $this->newLine();
            $this->line('📝 DNS Records');
            $this->line(str_repeat('─', 120));
        }

        $rows = [];
        foreach ($records as $record) {
            $status = $record->isActive() ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $priority = $record->requiresPriority() ? $record->priority : '-';

            // Clean up display
            $name = rtrim($record->name, '.');
            $zone = rtrim($record->zone->name, '.');

            // Simplify SOA content - show only nameserver and serial
            $content = $record->content;
            if ($record->type === 'SOA') {
                $parts = explode(' ', $content);
                $content = ($parts[0] ?? '') . ' ' . ($parts[2] ?? '');
            }

            $row = [
                $record->id,
                $record->type,
                $name,
                $content,
                $record->ttl . 's',
                $priority,
            ];

            // Only include zone column when viewing multiple zones or with --all
            if (!$isSingleZoneView || $this->option('all')) {
                $row[] = $zone;
            }

            $row[] = $status;
            $rows[] = $row;
        }

        // Headers depend on whether we're showing zone column
        $headers = ['ID', 'Type', 'Name', 'Content', 'TTL', 'Pri'];
        if (!$isSingleZoneView || $this->option('all')) {
            $headers[] = 'Zone';
        }
        $headers[] = 'Active';

        $this->table($headers, $rows);

        // Only show footer when viewing all records or with --all flag
        if (!$isSingleZoneView || $this->option('all')) {
            $this->newLine();
            $this->line("Total records: <fg=cyan>{$records->count()}</>");

            $activeCount = $records->filter(fn($r) => $r->isActive())->count();
            $this->line("Active: <fg=green>{$activeCount}</>");

            if (! $this->option('all')) {
                $this->newLine();
                $this->line('💡 For detailed view: shrec <record-id> --all');
            }
        }

        return self::SUCCESS;
    }

    /**
     * List records for a specific zone (zone name/ID provided as argument)
     */
    protected function listRecordsForZone(string $zoneIdentifier): int
    {
        $filters = ['zone' => $zoneIdentifier];

        // Apply additional filters if provided
        if ($this->option('type')) {
            $filters['type'] = $this->option('type');
        }

        if ($this->option('active')) {
            $filters['active'] = true;
        } elseif ($this->option('inactive')) {
            $filters['active'] = false;
        }

        if ($this->option('search')) {
            $filters['search'] = $this->option('search');
        }

        if ($this->option('content')) {
            $filters['content'] = $this->option('content');
        }

        return $this->listRecords($filters);
    }
}
