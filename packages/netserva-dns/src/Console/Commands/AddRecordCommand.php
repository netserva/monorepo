<?php

namespace NetServa\Dns\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Dns\Services\DnsRecordManagementService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Add DNS Record Command
 *
 * Creates a new DNS record in a zone
 * Follows NetServa CRUD pattern: addrec <vnode> <type> <name> <zone> <content> [options]
 *
 * Usage: addrec <vnode> <type> <name> <zone> <content> [options]
 * Example: addrec ns1gc A www goldcoast.org 192.168.1.100
 * Example: addrec ns1gc MX @ goldcoast.org mail.goldcoast.org --priority=10
 * Example: addrec ns1gc A mail goldcoast.org 192.168.1.50 --auto-ptr
 */
class AddRecordCommand extends Command
{
    protected $signature = 'addrec
        {vnode? : VNode identifier (DNS provider)}
        {type? : Record type (A, AAAA, CNAME, MX, TXT, PTR, NS, SRV, etc.)}
        {name? : Record name (e.g., "www", "@", "mail")}
        {zone? : Zone name}
        {content? : Record content (IP, hostname, text, etc.)}
        {--ttl= : TTL in seconds (default: zone TTL)}
        {--priority= : Priority for MX/SRV records}
        {--disabled : Create record as disabled}
        {--comment= : Record comment/description}
        {--auto-ptr : Auto-create PTR record for A/AAAA (FCrDNS)}
        {--auto-create-ptr-zone : Auto-create PTR zone if missing}
        {--allow-duplicate : Allow duplicate records}
        {--dry-run : Show what would be created without creating}';

    protected $description = 'Add a new DNS record (NetServa CRUD pattern)';

    protected DnsRecordManagementService $recordService;

    public function __construct(DnsRecordManagementService $recordService)
    {
        parent::__construct();
        $this->recordService = $recordService;
    }

    public function handle(): int
    {
        // Get vnode (provider)
        $vnode = $this->argument('vnode');
        if (! $vnode) {
            $vnode = text(
                label: 'VNode (DNS Provider)',
                placeholder: 'ns1gc',
                required: true,
                hint: 'Use "shdns" to list available providers'
            );
        }

        // Get record type
        $type = $this->argument('type');
        if (! $type) {
            $type = select(
                label: 'Record type',
                options: [
                    'A' => 'A (IPv4 address)',
                    'AAAA' => 'AAAA (IPv6 address)',
                    'CNAME' => 'CNAME (Canonical name)',
                    'MX' => 'MX (Mail exchange)',
                    'TXT' => 'TXT (Text record)',
                    'PTR' => 'PTR (Pointer/reverse DNS)',
                    'NS' => 'NS (Name server)',
                    'SRV' => 'SRV (Service record)',
                ],
                default: 'A'
            );
        }

        $type = strtoupper($type);

        // Get record name
        $name = $this->argument('name');
        if (! $name) {
            $name = text(
                label: 'Record name',
                placeholder: 'www (or @ for root/apex)',
                required: true,
                hint: 'Use @ for zone apex, or subdomain name'
            );
        }

        // Get zone
        $zone = $this->argument('zone');
        if (! $zone) {
            $zone = text(
                label: 'DNS Zone',
                placeholder: 'Zone ID or name (e.g., example.com)',
                required: true,
                hint: 'Use "shzone" to list available zones'
            );
        }

        // Get content
        $content = $this->argument('content');
        if (! $content) {
            $placeholder = match ($type) {
                'A' => '192.168.1.100',
                'AAAA' => '2001:db8::1',
                'CNAME' => 'example.com',
                'MX' => 'mail.example.com',
                'TXT' => 'v=spf1 include:_spf.example.com ~all',
                'PTR' => 'mail.example.com.',
                'NS' => 'ns1.example.com.',
                'SRV' => '10 60 5060 sipserver.example.com.',
                default => 'Record content'
            };

            $content = text(
                label: 'Record content',
                placeholder: $placeholder,
                required: true,
                hint: $this->getContentHint($type)
            );
        }

        // Get priority for MX/SRV records
        $priority = $this->option('priority');
        if (in_array($type, ['MX', 'SRV']) && ! $priority) {
            $priority = text(
                label: 'Priority',
                placeholder: '10',
                required: true,
                hint: 'Lower value = higher priority'
            );
        }

        // Auto-PTR confirmation for A/AAAA records
        $autoPTR = $this->option('auto-ptr');
        if (! $autoPTR && in_array($type, ['A', 'AAAA'])) {
            $autoPTR = confirm(
                label: 'Create automatic PTR record (reverse DNS)?',
                default: false,
                hint: 'Recommended for mail servers (FCrDNS)'
            );
        }

        // Build options
        $options = [
            'ttl' => $this->option('ttl') ? (int) $this->option('ttl') : null,
            'priority' => $priority ? (int) $priority : 0,
            'disabled' => $this->option('disabled'),
            'comment' => $this->option('comment'),
            'auto_ptr' => $autoPTR,
            'auto_create_ptr_zone' => $this->option('auto-create-ptr-zone'),
            'allow_duplicate' => $this->option('allow-duplicate'),
            'provider' => $vnode,
        ];

        // Show what we're about to create
        $this->newLine();
        $this->line("🚀 Creating DNS Record: <fg=yellow>{$type} {$name}</>");
        $this->line("   VNode: <fg=cyan>{$vnode}</>");
        $this->line("   Zone: <fg=cyan>{$zone}</>");
        $this->line("   Content: <fg=cyan>{$content}</>");

        if ($options['ttl']) {
            $this->line("   TTL: <fg=cyan>{$options['ttl']}</>");
        }

        if (in_array($type, ['MX', 'SRV'])) {
            $this->line("   Priority: <fg=cyan>{$options['priority']}</>");
        }

        if ($options['auto_ptr']) {
            $this->line('   Auto-PTR: <fg=green>Yes</> (FCrDNS enabled)');
        }

        $this->line('   Status: <fg='.($options['disabled'] ? 'red>Disabled' : 'green>Active').'</>');

        // Dry run check
        if ($this->option('dry-run')) {
            $this->newLine();
            $this->info('✅ Dry run complete - no changes made');
            $this->line('');
            $this->line('Would create record with:');
            $this->line('  Type: '.$type);
            $this->line('  Name: '.$name);
            $this->line('  Zone: '.$zone);
            $this->line('  Content: '.$content);
            $this->line('  Options: '.json_encode($options, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->newLine();

        // Create the record
        $result = $this->recordService->createRecord(
            type: $type,
            name: $name,
            zoneId: $zone,
            content: $content,
            options: $options
        );

        if (! $result['success']) {
            $this->error('❌ Failed to create DNS record');
            $this->line("   Error: {$result['message']}");

            if (isset($result['error'])) {
                $this->line("   Details: {$result['error']}");
            }

            return self::FAILURE;
        }

        $record = $result['record'];
        $zoneInfo = $result['zone'];

        $this->info('✅ DNS Record created successfully');
        $this->line("   ID: <fg=yellow>{$record->id}</>");
        $this->line("   Type: <fg=cyan>{$record->type}</>");
        $this->line("   Name: <fg=cyan>{$record->name}</>");
        $this->line("   Content: <fg=cyan>{$record->content}</>");
        $this->line("   TTL: <fg=cyan>{$record->ttl}</>");
        $this->line("   Zone: <fg=cyan>{$zoneInfo->name}</>");

        // Show PTR record if created
        if (isset($result['ptr_record'])) {
            $this->newLine();
            $ptrResult = $result['ptr_record'];

            if ($ptrResult['success']) {
                $ptrRecord = $ptrResult['record'];
                $this->info('🔄 Auto-PTR record created (FCrDNS)');
                $this->line("   PTR ID: <fg=yellow>{$ptrRecord->id}</>");
                $this->line("   PTR Name: <fg=cyan>{$ptrRecord->name}</>");
                $this->line("   PTR Zone: <fg=cyan>{$ptrRecord->zone->name}</>");
                $this->line("   Points to: <fg=cyan>{$ptrRecord->content}</>");
            } else {
                $this->warn('⚠️ Auto-PTR creation failed');
                $this->line("   Error: {$ptrResult['message']}");

                if (str_contains($ptrResult['message'], 'does not exist')) {
                    $this->line('');
                    $this->line('💡 Try again with: --auto-create-ptr-zone');
                }
            }
        }

        // Show next steps
        $this->newLine();
        $this->info('💡 Next steps:');
        $this->line("   - View record: shrec {$record->id}");
        $this->line("   - View all records: shrec {$zoneInfo->name}");
        $this->line("   - Update record: chrec {$record->id} --ttl=7200");
        $this->line("   - Delete record: delrec {$record->id}");

        if ($options['auto_ptr'] && isset($ptrResult) && $ptrResult['success']) {
            $this->line('');
            $this->line('✅ FCrDNS configured - forward and reverse DNS match');
            $this->line('   This is required for many mail servers');
        }

        return self::SUCCESS;
    }

    /**
     * Get content hint for record type
     */
    protected function getContentHint(string $type): string
    {
        return match ($type) {
            'A' => 'IPv4 address (e.g., 192.168.1.100)',
            'AAAA' => 'IPv6 address (e.g., 2001:db8::1)',
            'CNAME' => 'Target hostname (e.g., example.com)',
            'MX' => 'Mail server hostname (e.g., mail.example.com)',
            'TXT' => 'Text content (SPF, DKIM, verification, etc.)',
            'PTR' => 'Reverse DNS target (FQDN with trailing dot)',
            'NS' => 'Name server hostname (e.g., ns1.example.com.)',
            'SRV' => 'Priority Weight Port Target (e.g., 10 60 5060 sipserver.example.com.)',
            default => 'Record content value'
        };
    }
}
