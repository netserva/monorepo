<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use Ns\Setup\Models\SetupTemplate;
use Ns\Setup\Services\SetupService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;

class SetupCommand extends Command
{
    protected $signature = 'ns:setup 
                            {action? : Setup action (list, deploy, status, seed)}
                            {template? : Template name or ID}
                            {host? : Target server hostname}
                            {--config= : JSON configuration overrides}
                            {--dry-run : Show what would be done without executing}
                            {--force : Skip confirmation prompts}';

    protected $description = 'NetServa server setup operations - deploy templates and manage components';

    public function handle(SetupService $setupService): int
    {
        $action = $this->argument('action');

        // Interactive action selection if not provided
        if (! $action) {
            $action = select(
                'What setup operation would you like to perform?',
                [
                    'list' => 'List available templates',
                    'deploy' => 'Deploy template to server',
                    'status' => 'Check setup status',
                    'seed' => 'Seed default templates and components',
                ],
                'list'
            );
        }

        return match ($action) {
            'list' => $this->handleList($setupService),
            'deploy' => $this->handleDeploy($setupService),
            'status' => $this->handleStatus($setupService),
            'seed' => $this->handleSeed($setupService),
            default => $this->error("Unknown action: {$action}") ?: 1
        };
    }

    protected function handleList(SetupService $setupService): int
    {
        $templates = SetupTemplate::active()->ordered()->get();

        if ($templates->isEmpty()) {
            $this->warn('No setup templates found. Run "ns:setup seed" to create default templates.');

            return 1;
        }

        $this->info('📋 Available Setup Templates');
        $this->line(str_repeat('─', 60));

        $tableData = $templates->map(function ($template) {
            return [
                'Name' => $template->name,
                'Display Name' => $template->display_name,
                'Category' => $template->category,
                'Components' => implode(', ', $template->components ?? []),
                'OS Support' => implode(', ', $template->supported_os ?? []),
            ];
        })->toArray();

        table(
            ['Name', 'Display Name', 'Category', 'Components', 'OS Support'],
            $tableData
        );

        return 0;
    }

    protected function handleDeploy(SetupService $setupService): int
    {
        // Get template
        $templateName = $this->argument('template');
        if (! $templateName) {
            $templates = SetupTemplate::active()->ordered()->get();

            if ($templates->isEmpty()) {
                $this->error('No templates available. Run "ns:setup seed" first.');

                return 1;
            }

            $templateName = select(
                'Which template would you like to deploy?',
                $templates->pluck('display_name', 'name')->toArray()
            );
        }

        $template = SetupTemplate::where('name', $templateName)->first();
        if (! $template) {
            $this->error("Template '{$templateName}' not found");

            return 1;
        }

        // Get target host
        $host = $this->argument('host');
        if (! $host) {
            $hosts = SshHost::where('is_active', true)->get();

            if ($hosts->isEmpty()) {
                $this->error('No SSH hosts available. Configure hosts first.');

                return 1;
            }

            $host = select(
                'Which server would you like to deploy to?',
                $hosts->pluck('description', 'host')->toArray()
            );
        }

        // Get configuration
        $config = [];
        if ($configJson = $this->option('config')) {
            $config = json_decode($configJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON configuration provided');

                return 1;
            }
        }

        // Confirmation
        if (! $this->option('force')) {
            $this->info('📋 Deployment Summary');
            $this->line("Template: {$template->display_name}");
            $this->line("Target: {$host}");
            $this->line('Components: '.implode(', ', $template->components ?? []));

            if (! confirm("Deploy '{$template->display_name}' to {$host}?", true)) {
                $this->info('❌ Deployment cancelled');

                return 0;
            }
        }

        // Execute deployment
        if ($this->option('dry-run')) {
            $this->info('🔍 DRY RUN: Would deploy template with following configuration:');
            $this->line(json_encode([
                'template' => $template->name,
                'host' => $host,
                'config' => $config,
            ], JSON_PRETTY_PRINT));

            return 0;
        }

        try {
            $this->info("🚀 Starting deployment of '{$template->display_name}' to {$host}...");

            $job = $setupService->deployTemplate($template, $host, $config);

            $this->info("✅ Deployment job created: {$job->job_id}");
            $this->line('You can monitor progress in the web interface or check logs.');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Deployment failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function handleStatus(SetupService $setupService): int
    {
        $stats = $setupService->getStatistics();

        $this->info('📊 Setup System Status');
        $this->line(str_repeat('─', 40));

        $this->line("📋 Templates: {$stats['templates']}");
        $this->line("🧩 Components: {$stats['components']}");
        $this->line("📝 Total Jobs: {$stats['total_jobs']}");
        $this->line("🔄 Running Jobs: {$stats['running_jobs']}");
        $this->line("✅ Completed Jobs: {$stats['completed_jobs']}");
        $this->line("❌ Failed Jobs: {$stats['failed_jobs']}");

        if ($stats['running_jobs'] > 0) {
            $this->newLine();
            $this->warn("⚠️  {$stats['running_jobs']} job(s) currently running");
        }

        return 0;
    }

    protected function handleSeed(SetupService $setupService): int
    {
        if (! $this->option('force')) {
            $confirmed = confirm(
                'This will create/update default templates and components. Continue?',
                true
            );

            if (! $confirmed) {
                $this->info('❌ Seeding cancelled');

                return 0;
            }
        }

        try {
            $this->info('🌱 Seeding default setup templates and components...');

            progress(
                label: 'Seeding defaults...',
                steps: [
                    'Creating components' => fn () => $setupService->seedDefaults(),
                ]
            );

            $this->info('✅ Default templates and components seeded successfully!');
            $this->line('You can now use "ns:setup list" to see available templates.');

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Seeding failed: {$e->getMessage()}");

            return 1;
        }
    }
}
