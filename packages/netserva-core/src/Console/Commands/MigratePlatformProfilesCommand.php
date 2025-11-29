<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NetServa\Core\Models\PlatformProfile;
use NetServa\Core\Services\NetServaConfigurationService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

class MigratePlatformProfilesCommand extends Command
{
    protected $signature = 'migrate:platform-profiles
                           {--dry-run : Show what would be migrated without making changes}
                           {--force : Skip confirmation prompts}
                           {--backup : Create backup of etc directory before migration}
                           {--type= : Migrate only specific profile type (provider|server|host|vhost)}';

    protected $description = 'Migrate platform profile documentation from etc/ directory to database';

    private NetServaConfigurationService $configService;

    private array $migrationStats = [
        'providers_found' => 0,
        'servers_found' => 0,
        'hosts_found' => 0,
        'vhosts_found' => 0,
        'total_found' => 0,
        'migrated' => 0,
        'skipped' => 0,
        'errors' => 0,
    ];

    private array $profileTypes = [
        'providers' => 'provider',
        'servers' => 'server',
        'hosts' => 'host',
        'vhosts' => 'vhost',
    ];

    public function __construct(NetServaConfigurationService $configService)
    {
        parent::__construct();
        $this->configService = $configService;
    }

    public function handle(): int
    {
        try {
            $this->info('🚀 NetServa Platform Profiles Migration Tool');
            $this->line(str_repeat('=', 60));
            $this->newLine();

            // Check prerequisites
            if (! $this->checkPrerequisites()) {
                return 1;
            }

            // Analyze what would be migrated
            $profileFiles = $this->analyzePlatformProfiles();

            if (empty($profileFiles)) {
                $this->components->warn('No platform profile files found to migrate');

                return 0;
            }

            // Show migration plan
            $this->showMigrationPlan($profileFiles);

            // Create backup if requested
            if ($this->option('backup')) {
                $this->createBackup();
            }

            // Confirm migration
            if (! $this->option('force') && ! $this->option('dry-run')) {
                if (! confirm('Proceed with platform profiles migration?', true)) {
                    $this->components->info('Migration cancelled');

                    return 0;
                }
            }

            // Perform migration
            if ($this->option('dry-run')) {
                $this->performDryRun($profileFiles);
            } else {
                $this->performMigration($profileFiles);
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
        // Check if etc directory exists
        $etcPath = config('netserva-cli.paths.ns').'/etc';
        if (! $etcPath || ! File::isDirectory($etcPath)) {
            $this->components->error('❌ NetServa etc directory not found or not configured');
            $this->line('   Please ensure NSETC environment variable is set correctly');
            $this->line("   Current path: {$etcPath}");

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

        // Check if PlatformProfile table exists
        if (! $this->hasPlatformProfileTable()) {
            $this->components->error('❌ PlatformProfile table does not exist');
            $this->line('   Please run migrations first: php artisan migrate');

            return false;
        }

        $this->components->info('✅ Prerequisites check passed');
        $this->newLine();

        return true;
    }

    protected function hasPlatformProfileTable(): bool
    {
        try {
            return DB::getSchemaBuilder()->hasTable('platform_profiles');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function analyzePlatformProfiles(): array
    {
        $etcPath = config('netserva-cli.paths.ns').'/etc';
        $profileFiles = [];
        $typeFilter = $this->option('type');

        $this->components->info('🔍 Analyzing platform profiles...');

        foreach ($this->profileTypes as $directory => $type) {
            // Skip if type filter is specified and doesn't match
            if ($typeFilter && $type !== $typeFilter) {
                continue;
            }

            $typePath = $etcPath.'/'.$directory;
            if (! File::isDirectory($typePath)) {
                continue;
            }

            // Find all markdown files in this type directory
            $files = File::files($typePath);

            foreach ($files as $file) {
                if (pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'md') {
                    $profileName = pathinfo($file->getFilename(), PATHINFO_FILENAME);

                    $profileFiles[] = [
                        'type' => $type,
                        'name' => $profileName,
                        'filepath' => $file->getPathname(),
                        'size' => $file->getSize(),
                        'modified' => filemtime($file->getPathname()),
                    ];

                    $this->migrationStats[$directory.'_found']++;
                    $this->migrationStats['total_found']++;
                }
            }
        }

        return $profileFiles;
    }

    protected function showMigrationPlan(array $profileFiles): void
    {
        $this->components->info('📋 Migration Plan:');
        $this->newLine();

        // Group by type for display
        $byType = [];
        foreach ($profileFiles as $profile) {
            $byType[$profile['type']][] = $profile;
        }

        $tableData = [];
        foreach ($byType as $type => $profiles) {
            $tableData[] = [
                'type' => ucfirst($type),
                'count' => count($profiles),
                'total_size' => $this->formatBytes(array_sum(array_column($profiles, 'size'))),
                'sample_names' => implode(', ', array_slice(array_column($profiles, 'name'), 0, 3)).
                                (count($profiles) > 3 ? '...' : ''),
            ];
        }

        table(
            headers: ['Type', 'Count', 'Total Size', 'Sample Names'],
            rows: $tableData
        );

        $this->newLine();
        $this->line("📊 Total: {$this->migrationStats['total_found']} profile files");
        $this->newLine();
    }

    protected function createBackup(): void
    {
        $this->components->info('💾 Creating backup of etc directory...');

        $etcPath = config('netserva-cli.paths.ns').'/etc';
        $backupPath = config('netserva-cli.paths.nsbak', dirname($etcPath).'/bak');
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupDir = "{$backupPath}/etc_backup_{$timestamp}";

        if (! File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        // Copy entire etc directory
        File::copyDirectory($etcPath, $backupDir);

        $this->components->info("✅ Backup created: {$backupDir}");
        $this->newLine();
    }

    protected function performDryRun(array $profileFiles): void
    {
        $this->components->info('🔍 DRY RUN: Platform Profiles Migration');
        $this->newLine();

        foreach ($profileFiles as $profile) {
            $this->line("Would migrate: {$profile['type']}/{$profile['name']}");

            // Parse content to show what would be extracted
            $content = File::get($profile['filepath']);
            $tempProfile = new PlatformProfile(['content' => $content]);

            $title = $tempProfile->extractTitle();
            $description = $tempProfile->extractDescription();
            $tags = $tempProfile->extractTags();

            $this->line('   Title: '.($title ?: 'Not found'));
            $this->line('   Description: '.(substr($description ?: 'Not found', 0, 60).'...'));
            $this->line('   Tags: '.count($tags).' found ('.implode(', ', array_slice($tags, 0, 3)).'...)');

            $this->migrationStats['migrated']++;
        }

        $this->newLine();
        $this->line('🔍 DRY RUN COMPLETE - No actual changes made');
    }

    protected function performMigration(array $profileFiles): void
    {
        $this->components->info('🚀 Performing platform profiles migration...');
        $this->newLine();

        progress(
            label: 'Migrating profiles...',
            steps: $profileFiles,
            callback: function ($profile, $progress) {
                $progress->label("Migrating {$profile['type']}/{$profile['name']}");

                try {
                    $this->migrateProfileFile($profile);
                    $this->migrationStats['migrated']++;
                } catch (\Exception $e) {
                    $this->components->error("❌ Failed to migrate {$profile['type']}/{$profile['name']}: {$e->getMessage()}");
                    $this->migrationStats['errors']++;
                }
            },
            hint: 'This may take a few minutes for large profile collections'
        );
    }

    protected function migrateProfileFile(array $profile): void
    {
        if (! File::exists($profile['filepath'])) {
            throw new \Exception('Profile file not found');
        }

        $content = File::get($profile['filepath']);

        // Check if profile already exists
        $existing = PlatformProfile::where('profile_type', $profile['type'])
            ->where('profile_name', $profile['name'])
            ->first();

        if ($existing) {
            $this->migrationStats['skipped']++;

            return;
        }

        // Create new profile
        $platformProfile = new PlatformProfile([
            'profile_type' => $profile['type'],
            'profile_name' => $profile['name'],
            'filepath' => $profile['filepath'],
            'content' => $content,
            'migrated_at' => now(),
            'file_modified_at' => date('Y-m-d H:i:s', $profile['modified']),
            'checksum' => md5($content),
        ]);

        // Extract metadata from content
        $platformProfile->title = $platformProfile->extractTitle();
        $platformProfile->description = $platformProfile->extractDescription();
        $platformProfile->metadata = $platformProfile->extractMetadata();
        $platformProfile->tags = $platformProfile->extractTags();

        // Determine category based on content and type
        $platformProfile->category = $this->determineCategory($platformProfile);
        $platformProfile->status = 'active';

        $platformProfile->save();
    }

    protected function determineCategory(PlatformProfile $profile): string
    {
        // Base category on profile type
        $category = $profile->profile_type;

        // Further categorize based on content
        $content = strtolower($profile->content);
        $metadata = $profile->metadata ?? [];

        if ($profile->profile_type === 'provider') {
            if (str_contains($content, 'cloud') || str_contains($content, 'vps')) {
                $category = 'cloud-provider';
            } elseif (str_contains($content, 'dedicated') || str_contains($content, 'bare metal')) {
                $category = 'hosting-provider';
            }
        } elseif ($profile->profile_type === 'server') {
            if (str_contains($content, 'production') || str_contains($content, 'live')) {
                $category = 'production-server';
            } elseif (str_contains($content, 'development') || str_contains($content, 'test')) {
                $category = 'development-server';
            } elseif (str_contains($content, 'container') || str_contains($content, 'lxc')) {
                $category = 'container-server';
            }
        }

        return $category;
    }

    protected function showMigrationSummary(): void
    {
        $this->newLine();
        $this->components->info('📊 Migration Summary:');

        $tableData = [
            ['Providers Found', $this->migrationStats['providers_found']],
            ['Servers Found', $this->migrationStats['servers_found']],
            ['Hosts Found', $this->migrationStats['hosts_found']],
            ['VHosts Found', $this->migrationStats['vhosts_found']],
            ['Total Found', $this->migrationStats['total_found']],
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
            $this->line('📝 Platform profile data is now available in the PlatformProfile model');
            $this->line('🔍 Use the web interface to browse and manage platform profiles');
        }

        if ($this->migrationStats['errors'] > 0) {
            $this->newLine();
            warning('⚠️ Some profiles failed to migrate. Check the error messages above.');
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
