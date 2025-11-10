<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsRecordManagementService;

use function Laravel\Prompts\confirm;

/**
 * Delete DNS Record Command
 *
 * Delete DNS record from zone
 * Follows NetServa CRUD pattern: delrec <vnode> <record> [options]
 *
 * Usage: delrec <vnode> <record> [options]
 * Example: delrec ns1gc 123                  # Delete by ID
 * Example: delrec ns1gc 456 --delete-ptr     # Also delete related PTR
 */
class DeleteRecordCommand extends Command
{
    protected $signature = 'delrec
        {vnode : VNode identifier (DNS provider)}
        {record : Record ID}
        {--delete-ptr : Also delete related PTR record (for A/AAAA)}
        {--force : Force deletion without confirmation}
        {--skip-remote : Skip remote deletion (local only)}
        {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Delete DNS record (NetServa CRUD pattern)';

    protected DnsRecordManagementService $recordService;

    public function __construct(DnsRecordManagementService $recordService)
    {
        parent::__construct();
        $this->recordService = $recordService;
    }

    public function handle(): int
    {
        $vnode = $this->argument('vnode');
        $recordId = $this->argument('record');

        $showResult = $this->recordService->showRecord($recordId, ['with_ptr' => true, 'provider' => $vnode]);

        if (! $showResult['success']) {
            $this->error("❌ {$showResult['message']}");

            // Show hint if multiple records found
            if (isset($showResult['hint'])) {
                $this->newLine();
                $this->line($showResult['hint']);
            }

            return self::FAILURE;
        }

        $record = $showResult['record'];
        $zone = $showResult['zone'];
        $hasPTR = isset($showResult['ptr_record']) && $showResult['ptr_record'];

        $this->newLine();
        $this->line("🗑️  Deleting DNS Record: <fg=yellow>{$record->type} {$record->name}</>");
        $this->line(str_repeat('─', 60));
        $this->line("ID: <fg=cyan>{$record->id}</>");
        $this->line("Type: <fg=cyan>{$record->type}</>");
        $this->line("Name: <fg=cyan>{$record->name}</>");
        $this->line("Content: <fg=cyan>{$record->content}</>");
        $this->line("Zone: <fg=cyan>{$zone->name}</>");

        if ($hasPTR && in_array($record->type, ['A', 'AAAA'])) {
            $ptr = $showResult['ptr_record'];
            $this->newLine();
            $this->line("🔄 Related PTR Record: <fg=yellow>{$ptr->name}</>");
            $this->line("   PTR ID: <fg=cyan>{$ptr->id}</>");

            if ($this->option('delete-ptr')) {
                $this->line('   <fg=red>⚠️  PTR record will also be deleted</>');
            } else {
                $this->warn('   ⚠️  PTR record will be orphaned (use --delete-ptr to remove)');
            }
        }

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would delete:');
            $this->line('  Record: '.$record->type.' '.$record->name);
            $this->line('  PTR: '.($this->option('delete-ptr') && $hasPTR ? 'Yes' : 'No'));

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();

            $message = $this->option('delete-ptr') && $hasPTR
                ? "Delete {$record->type} record '{$record->name}' and related PTR?"
                : "Delete {$record->type} record '{$record->name}'?";

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

        $options = [
            'delete_ptr' => $this->option('delete-ptr'),
            'force' => $this->option('force'),
            'skip_remote' => $this->option('skip-remote'),
            'provider' => $vnode,
        ];

        $this->newLine();
        $this->line('🗑️  Deleting record...');

        $result = $this->recordService->deleteRecord(
            identifier: $recordId,
            options: $options
        );

        if (! $result['success']) {
            $this->error("❌ {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Error: {$result['error']}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('✅ DNS Record deleted successfully');
        $this->line("   {$result['message']}");

        if (isset($result['ptr_deleted']) && $result['ptr_deleted']) {
            $this->line('   Related PTR record also deleted');
        }

        if ($this->option('skip-remote')) {
            $this->newLine();
            $this->warn('⚠️  Record deleted locally only');
            $this->line('   Remote record still exists on provider');
        }

        $this->newLine();
        $this->line('💡 Next steps:');
        $this->line("   - View zone records: shrec {$zone->name}");
        $this->line("   - Add new record: addrec <type> <name> {$zone->name} <content>");

        return self::SUCCESS;
    }
}
