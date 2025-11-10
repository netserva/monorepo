<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Cms\Services\CmsImportService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

/**
 * CMS Import Command
 *
 * Imports CMS content and media from an exported ZIP file
 */
class ImportCommand extends Command
{
    protected $signature = 'cms:import
                            {file : Path to the CMS export ZIP file}
                            {--dry-run : Preview import without making changes}
                            {--skip-media : Skip importing media files}
                            {--conflict-strategy= : How to handle slug conflicts (rename|skip|overwrite)}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Import CMS content and media from a ZIP file';

    public function handle(CmsImportService $importService): int
    {
        $filePath = $this->argument('file');

        // Check if file exists
        if (! file_exists($filePath)) {
            $this->components->error("Import file not found: {$filePath}");

            return self::FAILURE;
        }

        // Validate import file
        $this->components->info('Validating import file...');

        try {
            $validation = spin(
                fn () => $importService->validate($filePath),
                'Validating import file...'
            );
        } catch (\Exception $e) {
            $this->components->error('Validation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->components->success('Import file is valid!');
        $this->components->info('');

        // Display manifest
        if (! empty($validation['manifest'])) {
            $this->displayManifest($validation['manifest']);
        }

        // Display conflicts
        if (! empty($validation['conflicts'])) {
            $this->displayConflicts($validation['conflicts']);
        }

        // Determine conflict strategy
        $conflictStrategy = $this->option('conflict-strategy');

        if (! $conflictStrategy && ! empty($validation['conflicts']) && ! $this->option('dry-run')) {
            $conflictStrategy = select(
                label: 'How should slug conflicts be handled?',
                options: [
                    'rename' => 'Rename imported content (add -imported suffix)',
                    'skip' => 'Skip conflicting content',
                    'overwrite' => 'Overwrite existing content',
                ],
                default: 'rename'
            );
        } else {
            $conflictStrategy = $conflictStrategy ?: 'rename';
        }

        // Confirm import
        if (! $this->option('dry-run') && ! $this->option('force')) {
            warning('This will import content into your database!');

            if (! confirm('Continue with import?', default: false)) {
                info('Import cancelled.');

                return self::SUCCESS;
            }
        }

        // Run import
        $this->components->info('');
        $this->components->info($this->option('dry-run') ? 'Running dry-run preview...' : 'Importing CMS content...');

        try {
            $result = spin(
                fn () => $importService->import(
                    $filePath,
                    $conflictStrategy,
                    $this->option('skip-media'),
                    $this->option('dry-run')
                ),
                'Processing import...'
            );
        } catch (\Exception $e) {
            $this->components->error('Import failed: '.$e->getMessage());

            return self::FAILURE;
        }

        // Display results
        $this->components->info('');

        if ($this->option('dry-run')) {
            $this->components->info('Dry-run completed! No changes were made.');
        } else {
            $this->components->success('CMS import completed successfully!');
        }

        $this->components->info('');

        $this->components->info('Import statistics:');
        $this->components->twoColumnDetail('  Pages', (string) $result['stats']['pages']);
        $this->components->twoColumnDetail('  Posts', (string) $result['stats']['posts']);
        $this->components->twoColumnDetail('  Categories', (string) $result['stats']['categories']);
        $this->components->twoColumnDetail('  Tags', (string) $result['stats']['tags']);
        $this->components->twoColumnDetail('  Menus', (string) $result['stats']['menus']);

        if (! $this->option('skip-media')) {
            $this->components->twoColumnDetail('  Media files', (string) $result['stats']['media_files']);
        }

        if (! empty($result['stats']['conflicts'])) {
            $this->components->info('');
            $this->components->warn('Conflicts handled: '.count($result['stats']['conflicts']));

            foreach ($result['stats']['conflicts'] as $conflict) {
                if (isset($conflict['new_slug'])) {
                    $this->components->twoColumnDetail(
                        "  {$conflict['original_slug']}",
                        "→ {$conflict['new_slug']}"
                    );
                } else {
                    $this->components->twoColumnDetail(
                        "  {$conflict['slug']}",
                        $conflict['action']
                    );
                }
            }
        }

        if ($this->option('dry-run')) {
            $this->components->info('');
            info('Run without --dry-run to perform the actual import.');
        }

        return self::SUCCESS;
    }

    protected function displayManifest(array $manifest): void
    {
        $this->components->info('Export details:');
        $this->components->twoColumnDetail('  Export date', $manifest['export_date'] ?? 'Unknown');
        $this->components->twoColumnDetail('  Laravel version', $manifest['laravel_version'] ?? 'Unknown');
        $this->components->twoColumnDetail('  CMS version', $manifest['cms_version'] ?? 'Unknown');

        if (! empty($manifest['stats'])) {
            $this->components->info('');
            $this->components->info('Content summary:');
            $this->components->twoColumnDetail('  Pages', (string) ($manifest['stats']['pages'] ?? 0));
            $this->components->twoColumnDetail('  Posts', (string) ($manifest['stats']['posts'] ?? 0));
            $this->components->twoColumnDetail('  Categories', (string) ($manifest['stats']['categories'] ?? 0));
            $this->components->twoColumnDetail('  Tags', (string) ($manifest['stats']['tags'] ?? 0));
            $this->components->twoColumnDetail('  Menus', (string) ($manifest['stats']['menus'] ?? 0));
            $this->components->twoColumnDetail('  Media files', (string) ($manifest['stats']['media_files'] ?? 0));
        }

        $this->components->info('');
    }

    protected function displayConflicts(array $conflicts): void
    {
        warning('Found '.count($conflicts).' potential conflicts:');

        foreach ($conflicts as $conflict) {
            $this->components->warn("  [{$conflict['type']}] {$conflict['message']}");
        }

        $this->components->info('');
    }
}
