<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsRecordManagementService;

/**
 * Change DNS Record Command
 *
 * Update DNS record configuration
 * Follows NetServa CRUD pattern: chrec <vnode> <record> [options]
 *
 * Usage: chrec <vnode> <record> [options]
 * Example: chrec ns1gc 123 --content=192.168.1.200
 * Example: chrec ns1gc 456 --ttl=7200 --update-ptr
 */
class ChangeRecordCommand extends Command
{
    protected $signature = 'chrec
        {vnode : VNode identifier (DNS provider)}
        {record : Record ID}
        {--content= : Update record content}
        {--ttl= : Update TTL}
        {--priority= : Update priority (MX/SRV)}
        {--comment= : Update comment}
        {--enable : Enable record}
        {--disable : Disable record}
        {--update-ptr : Update related PTR record (for A/AAAA)}
        {--dry-run : Show what would change without changing}';

    protected $description = 'Change DNS record configuration (NetServa CRUD pattern)';

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

        $updates = [];

        if ($this->option('content')) {
            $updates['content'] = $this->option('content');
        }

        if ($this->option('ttl')) {
            $updates['ttl'] = (int) $this->option('ttl');
        }

        if ($this->option('priority')) {
            $updates['priority'] = (int) $this->option('priority');
        }

        if ($this->option('comment')) {
            $updates['comment'] = $this->option('comment');
        }

        if ($this->option('enable')) {
            $updates['disabled'] = false;
        } elseif ($this->option('disable')) {
            $updates['disabled'] = true;
        }

        if (empty($updates)) {
            $this->error('❌ No updates specified');
            $this->line('');
            $this->line('Available options:');
            $this->line('  --content, --ttl, --priority, --comment');
            $this->line('  --enable, --disable, --update-ptr');
            $this->line('');
            $this->line('Example: chrec 123 --content=192.168.1.200');

            return self::FAILURE;
        }

        $options = [
            'update_ptr' => $this->option('update-ptr'),
            'provider' => $vnode,
        ];

        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('🔍 Dry run - no changes will be made');
            $this->line('');
            $this->line('Would update record:');
            $this->line('  VNode: '.$vnode);
            $this->line('  Record ID: '.$recordId);
            $this->line('  Updates: '.json_encode($updates, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("🔧 Updating DNS Record: <fg=yellow>{$recordId}</> on <fg=cyan>{$vnode}</>");

        $result = $this->recordService->updateRecord(
            identifier: $recordId,
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

        $record = $result['record'];
        $changes = $result['changes'] ?? [];

        if (! empty($changes)) {
            $this->newLine();
            $this->info('Changes:');

            foreach ($changes as $field => $change) {
                $fieldName = ucfirst(str_replace('_', ' ', $field));
                $old = $change['old'] ?? 'Not set';
                $new = $change['new'];
                $this->line("  {$fieldName}: <fg=gray>{$old}</> → <fg=cyan>{$new}</>");
            }

            $this->newLine();
            $this->info('✅ DNS Record updated successfully');

            if (isset($changes['ptr_updated'])) {
                $this->newLine();
                $this->info('🔄 Related PTR record updated');
            }
        } else {
            $this->info('ℹ️ No changes made (values already set)');
        }

        $this->newLine();
        $this->line('💡 Next steps:');
        $this->line("   - View record: shrec {$record->id}");
        $this->line("   - View zone: shzone {$record->zone->id}");

        return self::SUCCESS;
    }
}
