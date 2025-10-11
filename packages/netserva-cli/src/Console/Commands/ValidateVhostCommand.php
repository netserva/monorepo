<?php

namespace NetServa\Cli\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cli\Services\VhostValidationService;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

use function Laravel\Prompts\table;

/**
 * Validate VHost Command
 *
 * Validates discovered vhosts for NetServa 3.0 compliance
 *
 * Usage:
 *   php artisan validate:vhost <vnode> <vhost>
 *   php artisan validate:vhost markc wp.goldcoast.org
 *   php artisan validate:vhost --all-discovered
 */
class ValidateVhostCommand extends Command
{
    protected $signature = 'validate:vhost
                          {vnode? : VNode name}
                          {vhost? : VHost domain}
                          {--all-discovered : Validate all discovered vhosts}
                          {--store : Store validation results in database}
                          {--detailed : Show detailed validation output}';

    protected $description = 'Validate vhost configuration for NetServa 3.0 compliance';

    protected VhostValidationService $validationService;

    public function __construct(VhostValidationService $validationService)
    {
        parent::__construct();
        $this->validationService = $validationService;
    }

    public function handle(): int
    {
        if ($this->option('all-discovered')) {
            return $this->validateAllDiscovered();
        }

        $vnodeName = $this->argument('vnode');
        $vhostDomain = $this->argument('vhost');

        if (!$vnodeName || !$vhostDomain) {
            $this->error('Please provide vnode and vhost arguments, or use --all-discovered flag');
            $this->line('');
            $this->line('Usage:');
            $this->line('  php artisan validate:vhost <vnode> <vhost>');
            $this->line('  php artisan validate:vhost markc wp.goldcoast.org');
            $this->line('  php artisan validate:vhost --all-discovered');

            return 1;
        }

        return $this->validateSingleVhost($vnodeName, $vhostDomain);
    }

    /**
     * Validate a single vhost
     */
    protected function validateSingleVhost(string $vnodeName, string $vhostDomain): int
    {
        $this->info("🔍 Validating VHost: {$vhostDomain} on {$vnodeName}");

        // Find vnode
        $vnode = FleetVNode::where('name', $vnodeName)->first();
        if (!$vnode) {
            $this->error("❌ VNode not found: {$vnodeName}");

            return 1;
        }

        // Find vhost
        $vhost = FleetVHost::where('domain', $vhostDomain)
            ->where('vnode_id', $vnode->id)
            ->first();

        if (!$vhost) {
            $this->error("❌ VHost not found: {$vhostDomain}");

            return 1;
        }

        // Run validation
        $result = $this->validationService->validateVhost($vhost);

        if (!$result['success']) {
            $this->error("❌ Validation failed: ".$result['error']);

            return 1;
        }

        // Display results
        $this->displayValidationResults($vhost, $result);

        // Store results if requested
        if ($this->option('store')) {
            $this->validationService->updateVhostValidation($vhost, $result);
            $this->info('✅ Validation results stored in database');
        }

        // Return appropriate exit code
        return match ($result['status']) {
            'passed', 'passed_with_warnings' => 0,
            default => 1,
        };
    }

    /**
     * Validate all discovered vhosts
     */
    protected function validateAllDiscovered(): int
    {
        $this->info('🔍 Validating all discovered vhosts');

        $vhosts = FleetVHost::where('migration_status', 'discovered')
            ->with('vnode')
            ->get();

        if ($vhosts->isEmpty()) {
            $this->warn('No discovered vhosts found');

            return 0;
        }

        $this->info("Found {$vhosts->count()} discovered vhosts");
        $this->newLine();

        $results = [];
        $successCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($vhosts as $vhost) {
            $this->line("Validating: {$vhost->domain} on {$vhost->vnode->name}...");

            $result = $this->validationService->validateVhost($vhost);

            if ($result['success']) {
                $status = $result['status'];
                $summary = $result['summary'];

                $results[] = [
                    'VHost' => $vhost->domain,
                    'VNode' => $vhost->vnode->name,
                    'Status' => $this->formatStatus($status),
                    'Passed' => $summary['passed'],
                    'Warnings' => $summary['warnings'],
                    'Errors' => $summary['errors'],
                ];

                // Update counts
                match ($status) {
                    'passed' => $successCount++,
                    'passed_with_warnings' => $warningCount++,
                    default => $errorCount++,
                };

                // Store results if requested
                if ($this->option('store')) {
                    $this->validationService->updateVhostValidation($vhost, $result);
                }
            } else {
                $results[] = [
                    'VHost' => $vhost->domain,
                    'VNode' => $vhost->vnode->name,
                    'Status' => '❌ Failed',
                    'Passed' => 0,
                    'Warnings' => 0,
                    'Errors' => 1,
                ];
                $errorCount++;
            }
        }

        $this->newLine();
        $this->table(
            ['VHost', 'VNode', 'Status', 'Passed', 'Warnings', 'Errors'],
            $results
        );

        $this->newLine();
        $this->info('📊 Validation Summary:');
        $this->line("  • Total: {$vhosts->count()}");
        $this->line("  • Passed: {$successCount}");
        $this->line("  • Warnings: {$warningCount}");
        $this->line("  • Errors: {$errorCount}");

        if ($this->option('store')) {
            $this->newLine();
            $this->info('✅ Validation results stored in database');
        }

        return $errorCount > 0 ? 1 : 0;
    }

    /**
     * Display detailed validation results
     */
    protected function displayValidationResults(FleetVHost $vhost, array $result): void
    {
        $status = $result['status'];
        $summary = $result['summary'];

        $this->newLine();
        $this->line("📊 Validation Results for {$vhost->domain}:");
        $this->newLine();

        // Display status
        $statusDisplay = $this->formatStatus($status);
        $this->line("Status: {$statusDisplay}");
        $this->newLine();

        // Display summary
        $this->line("Summary:");
        $this->line("  • Total Checks: {$summary['total_checks']}");
        $this->line("  • Passed: ✅ {$summary['passed']}");
        $this->line("  • Warnings: ⚠️  {$summary['warnings']}");
        $this->line("  • Errors: ❌ {$summary['errors']}");
        if ($summary['critical'] > 0) {
            $this->line("  • Critical: 🚨 {$summary['critical']}");
        }

        // Display passed checks if detailed
        if ($this->option('detailed') && !empty($result['passed'])) {
            $this->newLine();
            $this->line('✅ Passed Checks:');
            foreach ($result['passed'] as $pass) {
                $this->line("  • [{$pass['category']}] {$pass['check']}");
            }
        }

        // Display warnings
        if (!empty($result['warnings'])) {
            $this->newLine();
            $this->line('⚠️  Warnings:');
            foreach ($result['warnings'] as $warning) {
                $this->line("  • [{$warning['category']}] {$warning['message']}");
                if (isset($warning['expected']) && $this->option('detailed')) {
                    $this->line("    Expected: {$warning['expected']}");
                    $this->line("    Actual: {$warning['actual']}");
                }
            }
        }

        // Display errors/issues
        if (!empty($result['issues'])) {
            $this->newLine();
            $this->line('❌ Issues:');
            foreach ($result['issues'] as $issue) {
                $severity = $issue['severity'] === 'critical' ? '🚨' : '❌';
                $this->line("  {$severity} [{$issue['category']}] {$issue['message']}");
                if (isset($issue['expected'])) {
                    $this->line("    Expected: {$issue['expected']}");
                    $this->line("    Actual: {$issue['actual']}");
                }
            }
        }

        $this->newLine();
    }

    /**
     * Format validation status with emoji
     */
    protected function formatStatus(string $status): string
    {
        return match ($status) {
            'passed' => '✅ Passed',
            'passed_with_warnings' => '⚠️  Passed (with warnings)',
            'needs_fixes' => '❌ Needs Fixes',
            'failed' => '🚨 Failed',
            default => '❓ Unknown',
        };
    }
}
