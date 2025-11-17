<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Services\Ipv6PtrConfigurationService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * Fleet IPv6 PTR Configuration Command
 *
 * Automates IPv6 reverse DNS (PTR) configuration for mail servers
 *
 * Usage:
 *   php artisan fleet:ipv6-ptr:configure msg
 *   php artisan fleet:ipv6-ptr:configure msg --dry-run
 *   php artisan fleet:ipv6-ptr:configure msg --test
 */
class FleetIpv6PtrConfigureCommand extends Command
{
    protected $signature = 'fleet:ipv6-ptr:configure
                            {vnode : VNode name (e.g., msg, sca, mrn)}
                            {--mail-ip= : Override detected mail server IPv6 address}
                            {--dns-provider=ns1rn : PowerDNS provider to use}
                            {--skip-postfix : Skip Postfix configuration}
                            {--skip-spf : Skip SPF record updates}
                            {--dry-run : Show what would be done without making changes}
                            {--test : Test configuration after setup}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Configure IPv6 PTR records for mail servers';

    public function __construct(
        protected Ipv6PtrConfigurationService $ipv6PtrService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');

        // Find vnode
        $vnode = FleetVnode::where('name', $vnodeName)->first();

        if (! $vnode) {
            $this->error("VNode '{$vnodeName}' not found");
            $this->line('');
            $this->line('Available vnodes:');
            $vnodes = FleetVnode::orderBy('name')->pluck('name')->take(10);
            foreach ($vnodes as $name) {
                $this->line("  - {$name}");
            }

            return self::FAILURE;
        }

        // Show vnode info
        info("Configuring IPv6 PTR for {$vnode->name}");
        $this->displayVnodeInfo($vnode);

        // Confirm before proceeding (unless --force)
        if (! $this->option('force') && ! $this->option('dry-run')) {
            if (! confirm("Proceed with IPv6 PTR configuration for {$vnode->name}?", true)) {
                info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        // Prepare options
        $options = [
            'mail_ip' => $this->option('mail-ip'),
            'dns_provider' => $this->option('dns-provider'),
            'skip_postfix' => $this->option('skip-postfix'),
            'skip_spf' => $this->option('skip-spf'),
            'dry_run' => $this->option('dry-run'),
        ];

        if ($this->option('dry-run')) {
            warning('DRY RUN MODE - No changes will be made');
            $this->line('');
        }

        // Call service (DRY principle - single source of truth)
        info('Starting IPv6 PTR configuration...');
        $result = $this->ipv6PtrService->configurePtrForVnode($vnode, $options);

        if (! $result['success']) {
            $this->line('');
            $this->error($result['message']);

            if (isset($result['error'])) {
                $this->line('');
                $this->line('Error details:');
                $this->line($result['error']);
            }

            return self::FAILURE;
        }

        // Display results
        $this->line('');
        info($result['message']);
        $this->line('');

        $this->displayConfigurationSummary($result);

        if (isset($result['delegation_instructions'])) {
            $this->line('');
            $this->displayDelegationInstructions($result['delegation_instructions']);
        }

        // Test if requested
        if ($this->option('test')) {
            $this->line('');
            $this->testConfiguration($vnode, $result);
        }

        $this->line('');
        info('IPv6 PTR configuration complete!');

        return self::SUCCESS;
    }

    protected function displayVnodeInfo(FleetVnode $vnode): void
    {
        table(
            ['Property', 'Value'],
            [
                ['Name', $vnode->name],
                ['FQDN', $vnode->fqdn ?? '(not set)'],
                ['Role', $vnode->role],
                ['Environment', $vnode->environment],
                ['VSite', $vnode->vsite->name ?? '(unknown)'],
                ['SSH Access', $vnode->hasSshAccess() ? '✓ Yes' : '✗ No'],
            ]
        );

        $this->line('');
    }

    protected function displayConfigurationSummary(array $result): void
    {
        $network = $result['ipv6_network'];
        $zone = $result['reverse_zone'];
        $record = $result['ptr_record'];
        $mailIp = $result['mail_ip'];

        // Truncate long PTR record name for display
        $ptrName = $record->name;
        if (strlen($ptrName) > 50) {
            $ptrName = substr($ptrName, 0, 20).'...'.substr($ptrName, -27);
        }

        $rows = [
            ['IPv6 Network', '✓ Registered', "{$network->network_address}/{$network->prefix_length}"],
            ['Mail Server IP', '✓ Detected', $mailIp],
            ['Reverse Zone', '✓ Created', $zone->name],
            ['PTR Record', '✓ Created', $ptrName],
        ];

        if ($result['postfix_configured']) {
            $rows[] = ['Postfix', '✓ Configured', 'IPv6 enabled'];
        } else {
            $rows[] = ['Postfix', '○ Skipped', '-'];
        }

        if ($result['spf_updated']) {
            $rows[] = ['SPF Record', '✓ Updated', 'IPv6 added'];
        } else {
            $rows[] = ['SPF Record', '○ Manual', 'See instructions above'];
        }

        table(['Component', 'Status', 'Details'], $rows);

        // Show PTR record details
        $this->line('');
        note("PTR Record Details\n\n".
             "Name:    {$record->name}\n".
             "Type:    PTR\n".
             "Content: {$record->content}\n".
             "TTL:     {$record->ttl}");
    }

    protected function displayDelegationInstructions(array $instructions): void
    {
        note("Hosting Provider Delegation Required\n\n".
             $instructions['provider_instructions']);

        $this->line('');
        warning('⚠ IMPORTANT: Reverse DNS will not work until delegation is complete!');
        $this->line('');
        $this->line('After delegating the reverse zone, verify with:');
        $this->line("  dig +short -x {$instructions['zone']}");
    }

    protected function testConfiguration(FleetVnode $vnode, array $result): void
    {
        info('Testing IPv6 PTR configuration...');
        $this->line('');

        $testResult = $this->ipv6PtrService->testPtrConfiguration($vnode);

        if ($testResult['success']) {
            info('✅ PTR configuration test PASSED');
            $this->line('');
            table(
                ['Test', 'Result'],
                [
                    ['PTR Lookup', $testResult['ptr_record']],
                    ['Expected', $vnode->fqdn],
                    ['Status', '✓ Match'],
                ]
            );
        } else {
            warning('⚠ PTR configuration test FAILED');
            $this->line('');
            $this->error($testResult['message']);

            if (isset($testResult['expected']) && isset($testResult['actual'])) {
                $this->line('');
                table(
                    ['Test', 'Result'],
                    [
                        ['Expected', $testResult['expected']],
                        ['Actual', $testResult['actual'] ?: '(no result)'],
                        ['Status', '✗ Mismatch'],
                    ]
                );
            }

            $this->line('');
            $this->line('This is expected if reverse zone delegation is not complete yet.');
            $this->line('Complete the delegation steps above, then run:');
            $this->line("  php artisan fleet:ipv6-ptr:configure {$vnode->name} --test");
        }
    }
}
