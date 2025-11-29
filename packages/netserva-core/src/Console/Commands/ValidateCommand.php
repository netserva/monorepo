<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\VhostRepairService;
use NetServa\Core\Services\VhostValidationService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

/**
 * Validate VHost Command
 *
 * Validates vhost configuration for NetServa 3.0 compliance
 * Follows NetServa CRUD pattern: validate [vnode] [vhost]
 *
 * Usage:
 *   php artisan validate markc                       # All vhosts on vnode
 *   php artisan validate markc example.com           # Specific vhost
 *   php artisan validate --all-discovered            # All discovered vhosts
 *   php artisan validate markc example.com --repair  # Validate and repair
 */
class ValidateCommand extends Command
{
    protected $signature = 'validate
                          {vnode? : VNode name (validates all vhosts on vnode if vhost omitted)}
                          {vhost? : VHost domain (validates specific vhost)}
                          {--all-discovered : Validate all discovered vhosts across all vnodes}
                          {--store : Store validation results in database}
                          {--all : Show detailed validation output}
                          {--repair : Repair detected issues}
                          {--dry-run : Show repair plan without executing}
                          {--force : Skip confirmation prompts}';

    protected $description = 'Validate vhost configuration (NetServa CRUD pattern)';

    protected VhostValidationService $validationService;

    protected VhostRepairService $repairService;

    public function __construct(
        VhostValidationService $validationService,
        VhostRepairService $repairService
    ) {
        parent::__construct();
        $this->validationService = $validationService;
        $this->repairService = $repairService;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $vhostDomain = $this->argument('vhost');

        // Validate all discovered vhosts
        if ($this->option('all-discovered')) {
            return $this->validateAllDiscovered();
        }

        // Require vnode argument
        if (! $vnodeName) {
            $this->error('Please provide vnode argument or use --all-discovered flag');
            $this->line('');
            $this->line('Usage:');
            $this->line('  php artisan validate markc                     # All vhosts on vnode');
            $this->line('  php artisan validate markc example.com         # Specific vhost');
            $this->line('  php artisan validate --all-discovered          # All discovered vhosts');

            return 1;
        }

        // Validate specific vhost
        if ($vhostDomain) {
            return $this->validateSingleVhost($vnodeName, $vhostDomain);
        }

        // Validate all vhosts on vnode
        return $this->validateVnodeVhosts($vnodeName);
    }

    /**
     * Validate all vhosts on a vnode
     */
    protected function validateVnodeVhosts(string $vnodeName): int
    {
        // Find vnode
        $vnode = FleetVnode::where('name', $vnodeName)->first();
        if (! $vnode) {
            $this->error("❌ VNode not found: {$vnodeName}");

            return 1;
        }

        $vhosts = FleetVhost::where('vnode_id', $vnode->id)->get();

        if ($vhosts->isEmpty()) {
            $this->warn("No vhosts found on vnode: {$vnodeName}");

            return 0;
        }

        $this->info("🔍 Validating {$vhosts->count()} vhosts on {$vnodeName}");
        $this->newLine();

        $results = [];
        $successCount = 0;
        $warningCount = 0;
        $errorCount = 0;

        foreach ($vhosts as $vhost) {
            $this->line("Validating: {$vhost->domain}...");

            $result = $this->validationService->validateVhost($vhost);

            if ($result['success']) {
                $status = $result['status'];
                $summary = $result['summary'];

                $results[] = [
                    'VHost' => $vhost->domain,
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
            ['VHost', 'Status', 'Passed', 'Warnings', 'Errors'],
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
     * Validate a single vhost
     */
    protected function validateSingleVhost(string $vnodeName, string $vhostDomain): int
    {
        $this->info("🔍 Validating VHost: {$vhostDomain} on {$vnodeName}");

        // Find vnode
        $vnode = FleetVnode::where('name', $vnodeName)->first();
        if (! $vnode) {
            $this->error("❌ VNode not found: {$vnodeName}");

            return 1;
        }

        // Find vhost
        $vhost = FleetVhost::where('domain', $vhostDomain)
            ->where('vnode_id', $vnode->id)
            ->first();

        if (! $vhost) {
            $this->error("❌ VHost not found: {$vhostDomain}");

            return 1;
        }

        // Run validation
        $result = $this->validationService->validateVhost($vhost);

        if (! $result['success']) {
            $this->error('❌ Validation failed: '.$result['error']);

            return 1;
        }

        // Display results
        $this->displayValidationResults($vhost, $result);

        // Repair if requested
        if ($this->option('repair') || $this->option('dry-run')) {
            $this->newLine();
            $repairResult = $this->performRepair($vhost, $result);

            if (! $repairResult['success']) {
                $errorMsg = $repairResult['error'] ?? implode(', ', $repairResult['errors'] ?? ['Unknown error']);
                $this->error('❌ Repair failed: '.$errorMsg);

                return 1;
            }

            // Re-validate after repair (unless dry-run)
            if ($this->option('repair') && ! $this->option('dry-run')) {
                $this->newLine();
                $this->info('🔄 Re-validating after repair...');

                // Refresh vhost model to get updated vconfs
                $vhost->refresh();
                $vhost->load('vconfs');

                $revalidationResult = $this->validationService->validateVhost($vhost);
                $this->displayValidationResults($vhost, $revalidationResult);
            }
        }

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

        $vhosts = FleetVhost::where('migration_status', 'discovered')
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

                // Display detailed results if --all flag is set
                if ($this->option('all')) {
                    $this->displayValidationResults($vhost, $result);
                }

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

        // Only show summary table if --all flag is NOT set
        if (! $this->option('all')) {
            $this->newLine();
            $this->table(
                ['VHost', 'VNode', 'Status', 'Passed', 'Warnings', 'Errors'],
                $results
            );
        }

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
    protected function displayValidationResults(FleetVhost $vhost, array $result): void
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
        $this->line('Summary:');
        $this->line("  • Total Checks: {$summary['total_checks']}");
        $this->line("  • Passed: ✅ {$summary['passed']}");
        $this->line("  • Warnings: ⚠️  {$summary['warnings']}");
        $this->line("  • Errors: ❌ {$summary['errors']}");
        if ($summary['critical'] > 0) {
            $this->line("  • Critical: 🚨 {$summary['critical']}");
        }

        // Display passed checks if --all
        if ($this->option('all') && ! empty($result['passed'])) {
            $this->newLine();
            $this->line('✅ Passed Checks:');
            foreach ($result['passed'] as $pass) {
                $this->line("  • [{$pass['category']}] {$pass['check']}");
            }
        }

        // Display warnings
        if (! empty($result['warnings'])) {
            $this->newLine();
            $this->line('⚠️  Warnings:');
            foreach ($result['warnings'] as $warning) {
                $this->line("  • [{$warning['category']}] {$warning['message']}");
                if (isset($warning['expected']) && $this->option('all')) {
                    $this->line("    Expected: {$warning['expected']}");
                    $this->line("    Actual: {$warning['actual']}");
                }
            }
        }

        // Display errors/issues
        if (! empty($result['issues'])) {
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

    /**
     * Perform repair on vhost
     */
    protected function performRepair(FleetVhost $vhost, array $validationResult): array
    {
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🔧 Repair Plan (dry-run):');
        } else {
            // Confirm before repair unless --force
            if (! $this->option('force')) {
                $issueCount = count($validationResult['issues'] ?? []);
                $warningCount = count($validationResult['warnings'] ?? []);
                $totalIssues = $issueCount + $warningCount;

                if (! $this->confirm("Repair {$totalIssues} issues on {$vhost->domain}?", true)) {
                    return [
                        'success' => false,
                        'error' => 'Repair cancelled by user',
                    ];
                }
            }

            $this->info('🔧 Repairing vhost...');
        }

        // Execute repair
        $repairResult = $this->repairService->repairVhost(
            vhost: $vhost,
            validationResult: $validationResult,
            dryRun: $isDryRun
        );

        if (! $repairResult['success']) {
            return $repairResult;
        }

        // Display repair results
        if ($isDryRun && isset($repairResult['repairs_planned'])) {
            foreach ($repairResult['repairs_planned'] as $repair) {
                $this->line("  • [{$repair['type']}] {$repair['action']}: {$repair['message']}");
            }
        } elseif (isset($repairResult['repairs'])) {
            foreach ($repairResult['repairs'] as $repair) {
                $status = $repair['status'] === 'completed' ? '✅' : '❌';
                $this->line("  {$status} [{$repair['type']}] {$repair['action']}: {$repair['message']}");

                if (isset($repair['error'])) {
                    $this->line("     Error: {$repair['error']}");
                }
            }

            if (! empty($repairResult['errors'])) {
                $this->newLine();
                $this->warn('Some repairs failed:');
                foreach ($repairResult['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            } else {
                $this->newLine();
                $this->info('✅ All repairs completed successfully');
            }
        }

        return $repairResult;
    }
}
