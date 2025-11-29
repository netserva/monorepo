<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NetServa\Core\Models\VhostConfiguration;
use NetServa\Core\Services\NetServaConfigurationService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class MigrateVhostConfigsCommand extends Command
{
    protected $signature = 'migrate:vhost-configs
                           {--dry-run : Show what would be migrated without making changes}
                           {--force : Skip confirmation prompts}
                           {--backup : Create backup of var directory before migration}';

    protected $description = 'Migrate vhost configuration files from var/ directory to database';

    private NetServaConfigurationService $configService;

    private array $migrationStats = [
        'vnodes_found' => 0,
        'vhosts_found' => 0,
        'migrated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    public function __construct(NetServaConfigurationService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle(): int
    {
        try {
            $this->info('🚀 NetServa Vhost Configuration Migration Tool');
            $this->line(str_repeat('=', 60));
            $this->newLine();

            // Check prerequisites
            if (! $this->checkPrerequisites()) {
                return 1;
            }

            // Analyze what would be migrated
            $configFiles = $this->analyzeVhostConfigurations();

            if (empty($configFiles)) {
                $this->components->warn('No vhost configuration files found to migrate');

                return 0;
            }

            // Show migration plan
            $this->showMigrationPlan($configFiles);

            // Create backup if requested
            if ($this->option('backup')) {
                $this->createBackup();
            }

            // Confirm migration
            if (! $this->option('force') && ! $this->option('dry-run')) {
                if (! confirm('Proceed with vhost configuration migration?', true)) {
                    $this->components->info('Migration cancelled');

                    return 0;
                }
            }

            // Perform migration
            if ($this->option('dry-run')) {
                $this->performDryRun($configFiles);
            } else {
                $this->performMigration($configFiles);
            }

            $this->showMigrationSummary();

            return 0;

        } catch (\Exception $e) {
            $this->components->error("❌ Migration failed: {$e->getMessage()}");

            return 1;
        }
    }

    protected function checkPrerequisites(): bool
    {
        // Check if var directory exists
        $varPath = config('netserva-cli.paths.ns').'/var';
        if (! $varPath || ! File::isDirectory($varPath)) {
            $this->components->error('❌ NetServa var directory not found or not configured');
            $this->line('   Please ensure NSVAR environment variable is set correctly');
            $this->line("   Current path: {$varPath}");

            return false;
        }

        // Check database connection
        try {
            DB::connection()->getPdo();
            $this->components->info('✅ Database connection verified');
        } catch (\Exception $e) {
            $this->components->error("❌ Database connection failed: {$e->getMessage()}");

            return false;
        }

        // Check if VhostConfiguration table exists
        if (! $this->hasVhostConfigurationTable()) {
            $this->components->error('❌ VhostConfiguration table does not exist');
            $this->line('   Please run migrations first: php artisan migrate');

            return false;
        }

        $this->components->info('✅ Prerequisites check passed');
        $this->newLine();

        return true;
    }

    protected function hasVhostConfigurationTable(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('vhost_configurations');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function analyzeVhostConfigurations(): array
    {
        $varPath = config('netserva-cli.paths.ns').'/var';
        $configFiles = [];

        $this->components->info('🔍 Analyzing vhost configurations...');

        // Find all vnode directories
        $vnodeDirs = File::directories($varPath);
        $this->migrationStats['vnodes_found'] = count($vnodeDirs);

        foreach ($vnodeDirs as $vnodeDir) {
            $vnode = basename($vnodeDir);

            // Find all vhost configuration files in this vnode
            $vhostFiles = File::files($vnodeDir);

            foreach ($vhostFiles as $file) {
                $vhost = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                // Skip files that don't look like domain configurations
                if (! $this->isValidVhostFile($file->getPathname())) {
                    continue;
                }

                $configFiles[] = [
                    'vnode' => $vnode,
                    'vhost' => $vhost,
                    'filepath' => $file->getPathname(),
                    'size' => $file->getSize(),
                    'modified' => filemtime($file->getPathname()),
                ];

                $this->migrationStats['vhosts_found']++;
            }
        }

        return $configFiles;
    }

    protected function isValidVhostFile(string $filepath): bool
    {
        // Check if file contains typical vhost configuration variables
        $content = File::get($filepath);

        // Must contain core NetServa variables (VNODE can be interpolated from directory)
        $requiredVars = ['VHOST=', 'VPATH=', 'ADMIN='];

        foreach ($requiredVars as $var) {
            if (! str_contains($content, $var)) {
                return false;
            }
        }

        return true;
    }

    protected function showMigrationPlan(array $configFiles): void
    {
        $this->components->info('📋 Migration Plan:');
        $this->newLine();

        // Group by vnode for display
        $byVnode = [];
        foreach ($configFiles as $config) {
            $byVnode[$config['vnode']][] = $config;
        }

        $tableData = [];
        foreach ($byVnode as $vnode => $vhosts) {
            $tableData[] = [
                'vnode' => $vnode,
                'vhost_count' => count($vhosts),
                'total_size' => $this->formatBytes(array_sum(array_column($vhosts, 'size'))),
                'sample_vhosts' => implode(', ', array_slice(array_column($vhosts, 'vhost'), 0, 3)).
                                 (count($vhosts) > 3 ? '...' : ''),
            ];
        }

        table(
            headers: ['VNode', 'VHost Count', 'Total Size', 'Sample VHosts'],
            rows: $tableData
        );

        $this->newLine();
        $this->line("📊 Total: {$this->migrationStats['vnodes_found']} vnodes, {$this->migrationStats['vhosts_found']} vhosts");
        $this->newLine();
    }

    protected function createBackup(): void
    {
        $this->components->info('💾 Creating backup of var directory...');

        $varPath = config('netserva-cli.paths.ns').'/var';
        $backupPath = config('netserva-cli.paths.nsbak', dirname($varPath).'/bak');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = "{$backupPath}/var_backup_{$timestamp}";

        if (! File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        // Copy entire var directory
        File::copyDirectory($varPath, $backupDir);

        $this->components->info("✅ Backup created: {$backupDir}");
        $this->newLine();
    }

    protected function performDryRun(array $configFiles): void
    {
        $this->components->info('🔍 DRY RUN: Vhost Configuration Migration');
        $this->newLine();

        foreach ($configFiles as $config) {
            $this->line("Would migrate: {$config['vnode']}/{$config['vhost']}");

            // Parse configuration to show what would be migrated
            $envVars = $this->parseConfigurationFile($config['filepath']);

            if (! empty($envVars)) {
                $this->line('   Variables: '.count($envVars).' found');
                $vhost = $envVars['VHOST'] ?? 'unknown';
                $admin = $envVars['ADMIN'] ?? 'unknown';
                $this->line("   Sample: VHOST={$vhost}, ADMIN={$admin}");
            }

            $this->migrationStats['migrated']++;
        }

        $this->newLine();
        $this->line('🔍 DRY RUN COMPLETE - No actual changes made');
    }

    protected function performMigration(array $configFiles): void
    {
        $this->components->info('🚀 Performing vhost configuration migration...');
        $this->newLine();

        progress(
            label: 'Migrating configurations...',
            steps: $configFiles,
            callback: function ($config, $progress) {
                $progress->label("Migrating {$config['vnode']}/{$config['vhost']}");

                try {
                    $this->migrateConfigurationFile($config);
                    $this->migrationStats['migrated']++;
                } catch (\Exception $e) {
                    $this->components->error("❌ Failed to migrate {$config['vnode']}/{$config['vhost']}: {$e->getMessage()}");
                    $this->migrationStats['errors']++;
                }
            },
            hint: 'This may take a few minutes for large configurations'
        );
    }

    protected function parseConfigurationFile(string $filepath): array
    {
        $content = File::get($filepath);
        $envVars = [];

        // Parse shell variable assignments
        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            $line = trim($line);

            // Skip comments and empty lines
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Match variable assignments (VAR=value or VAR='value' or VAR="value")
            if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                $varName = $matches[1];
                $value = $matches[2];

                // Remove quotes
                $value = trim($value, '"\'');

                $envVars[$varName] = $value;
            }
        }

        // Interpolate VNODE from directory structure if not present
        if (! isset($envVars['VNODE'])) {
            $pathParts = explode('/', $filepath);
            $vnode = $pathParts[count($pathParts) - 2]; // Parent directory name
            $envVars['VNODE'] = $vnode;
        }

        return $envVars;
    }

    protected function migrateConfigurationFile(array $config): void
    {
        $envVars = $this->parseConfigurationFile($config['filepath']);

        if (empty($envVars)) {
            throw new \Exception('No valid environment variables found in configuration file');
        }

        // Validate required variables (VNODE is now interpolated if missing)
        $requiredVars = ['VHOST', 'ADMIN'];
        foreach ($requiredVars as $var) {
            if (! isset($envVars[$var])) {
                throw new \Exception("Required variable {$var} not found");
            }
        }

        // Check if configuration already exists
        $existing = VhostConfiguration::where('vnode', $config['vnode'])
            ->where('vhost', $config['vhost'])
            ->first();

        if ($existing) {
            $this->migrationStats['skipped']++;

            return;
        }

        // Create new configuration record
        VhostConfiguration::create([
            'vnode' => $config['vnode'],
            'vhost' => $config['vhost'],
            'filepath' => $config['filepath'],
            'variables' => $envVars,
            'migrated_at' => now(),
            'file_modified_at' => date('Y-m-d H:i:s', $config['modified']),
            'checksum' => md5_file($config['filepath']),
        ]);
    }

    protected function showMigrationSummary(): void
    {
        $this->newLine();
        $this->components->info('📊 Migration Summary:');

        $tableData = [
            ['VNodes Found', $this->migrationStats['vnodes_found']],
            ['VHosts Found', $this->migrationStats['vhosts_found']],
            ['Successfully Migrated', $this->migrationStats['migrated']],
            ['Skipped (Already Exists)', $this->migrationStats['skipped']],
            ['Errors', $this->migrationStats['errors']],
        ];

        table(
            headers: ['Metric', 'Count'],
            rows: $tableData
        );

        if ($this->migrationStats['migrated'] > 0) {
            $this->newLine();
            $this->components->info('🎉 Migration completed successfully!');
            $this->line('📝 Configuration data is now available in the VhostConfiguration model');
            $this->line('🔍 Use the ns-web plugin to manage virtual hosts through the web interface');
        }

        if ($this->migrationStats['errors'] > 0) {
            $this->newLine();
            warning('⚠️ Some configurations failed to migrate. Check the error messages above.');
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2).' '.$units[$unitIndex];
    }
}
