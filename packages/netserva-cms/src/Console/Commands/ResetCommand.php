<?php

declare(strict_types=1);

namespace NetServa\Cms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\warning;

/**
 * CMS Reset Command
 *
 * Clears all CMS content and media files to prepare for fresh import
 */
class ResetCommand extends Command
{
    protected $signature = 'cms:reset
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clear all CMS content and media files';

    protected array $cmsTables = [
        'cms_post_tag',
        'cms_category_post',
        'cms_tags',
        'cms_categories',
        'cms_posts',
        'cms_pages',
        'cms_menus',
    ];

    public function handle(): int
    {
        // Warning message
        warning('This will permanently delete ALL CMS content and media files!');
        $this->components->bulletList([
            'All blog posts and pages',
            'All categories and tags',
            'All menus',
            'All media files (images, documents)',
        ]);

        // Confirm action
        if (! $this->option('force')) {
            if (! confirm('Are you sure you want to reset the CMS?', default: false)) {
                info('Reset cancelled.');

                return self::SUCCESS;
            }

            // Double confirmation for safety
            if (! confirm('This action cannot be undone. Continue?', default: false)) {
                info('Reset cancelled.');

                return self::SUCCESS;
            }
        }

        $this->components->info('Resetting CMS...');

        // Step 1: Delete media files
        $this->deleteMediaFiles();

        // Step 2: Truncate CMS tables
        $this->truncateTables();

        // Step 3: Delete media records
        $this->deleteMediaRecords();

        $this->components->info('');
        $this->components->success('CMS reset successfully!');
        $this->components->info('The CMS is now empty and ready for fresh content.');

        return self::SUCCESS;
    }

    protected function deleteMediaFiles(): void
    {
        $disk = config('cms.media_disk', 'public');

        $this->components->task('Deleting media files', function () use ($disk) {
            try {
                $storage = Storage::disk($disk);

                // Get all directories in the media storage
                $directories = $storage->directories();

                foreach ($directories as $directory) {
                    // Only delete numeric directories (media library uses ID-based folders)
                    if (is_numeric(basename($directory))) {
                        $storage->deleteDirectory($directory);
                    }
                }

                return true;
            } catch (\Exception $e) {
                $this->components->warn("Failed to delete media files: {$e->getMessage()}");

                return false;
            }
        });
    }

    protected function truncateTables(): void
    {
        foreach ($this->cmsTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $this->components->task("Clearing {$table}", function () use ($table) {
                try {
                    // Disable foreign key checks temporarily
                    if (DB::getDriverName() === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=0');
                    }

                    DB::table($table)->truncate();

                    if (DB::getDriverName() === 'mysql') {
                        DB::statement('SET FOREIGN_KEY_CHECKS=1');
                    }

                    return true;
                } catch (\Exception $e) {
                    $this->components->warn("Failed to clear {$table}: {$e->getMessage()}");

                    return false;
                }
            });
        }
    }

    protected function deleteMediaRecords(): void
    {
        if (! Schema::hasTable('media')) {
            return;
        }

        $this->components->task('Clearing media records', function () {
            try {
                // Delete only CMS-related media records
                DB::table('media')
                    ->where('model_type', 'like', 'NetServa\\Cms\\%')
                    ->delete();

                return true;
            } catch (\Exception $e) {
                $this->components->warn("Failed to clear media records: {$e->getMessage()}");

                return false;
            }
        });
    }
}
