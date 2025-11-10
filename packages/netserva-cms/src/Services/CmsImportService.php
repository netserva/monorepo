<?php

declare(strict_types=1);

namespace NetServa\Cms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CmsImportService
{
    protected string $tempDir;

    protected array $idMappings = [
        'cms_categories' => [],
        'cms_tags' => [],
        'cms_pages' => [],
        'cms_posts' => [],
        'media' => [],
    ];

    protected array $stats = [
        'pages' => 0,
        'posts' => 0,
        'categories' => 0,
        'tags' => 0,
        'menus' => 0,
        'media_files' => 0,
        'conflicts' => [],
    ];

    protected string $conflictStrategy = 'rename'; // rename, skip, overwrite

    public function __construct()
    {
        $this->tempDir = storage_path('app/cms-import-'.time());
    }

    /**
     * Import CMS content from a ZIP file
     */
    public function import(
        string $zipPath,
        string $conflictStrategy = 'rename',
        bool $skipMedia = false,
        bool $dryRun = false
    ): array {
        $this->conflictStrategy = $conflictStrategy;

        // Extract ZIP
        $this->extractZip($zipPath);

        // Read and import JSON
        $data = $this->readJson();
        $manifest = $data['manifest'] ?? [];

        // Import data
        $this->importJson($data, $dryRun);

        // Import media files
        if (! $skipMedia && File::exists($this->tempDir.'/cms_media.tar.gz')) {
            $this->importMedia($dryRun);
        }

        // Cleanup
        if (! $dryRun) {
            File::deleteDirectory($this->tempDir);
        }

        return [
            'manifest' => $manifest,
            'stats' => $this->stats,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Validate import file without actually importing
     */
    public function validate(string $zipPath): array
    {
        // Check if file exists
        if (! File::exists($zipPath)) {
            throw new \RuntimeException("Import file not found: {$zipPath}");
        }

        // Check if file is a valid ZIP
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Invalid ZIP file: {$zipPath}");
        }

        // Check required files
        if ($zip->locateName('cms_export.json') === false) {
            $zip->close();
            throw new \RuntimeException('Invalid CMS export: missing cms_export.json');
        }

        $zip->close();

        // Extract and read JSON
        $this->extractZip($zipPath);
        $data = $this->readJson();
        $manifest = $data['manifest'] ?? [];

        // Check for potential conflicts
        $conflicts = $this->detectConflicts($data);

        return [
            'valid' => true,
            'manifest' => $manifest,
            'conflicts' => $conflicts,
        ];
    }

    protected function extractZip(string $zipPath): void
    {
        // Clean up existing temp directory if it exists
        if (File::exists($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }

        File::makeDirectory($this->tempDir, 0755, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException("Failed to open ZIP file: {$zipPath}");
        }

        $zip->extractTo($this->tempDir);
        $zip->close();
    }

    protected function readJson(): array
    {
        $jsonPath = $this->tempDir.'/cms_export.json';

        if (! File::exists($jsonPath)) {
            throw new \RuntimeException('CMS export JSON file not found');
        }

        $data = json_decode(File::get($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in export file: '.json_last_error_msg());
        }

        return $data;
    }

    protected function detectConflicts(array $data): array
    {
        $conflicts = [];

        // Check for page slug conflicts
        if (isset($data['cms_pages']) && Schema::hasTable('cms_pages')) {
            $existingSlugs = DB::table('cms_pages')->pluck('slug')->toArray();
            foreach ($data['cms_pages'] as $page) {
                if (isset($page['slug']) && in_array($page['slug'], $existingSlugs)) {
                    $conflicts[] = [
                        'type' => 'page',
                        'slug' => $page['slug'],
                        'message' => "Page with slug '{$page['slug']}' already exists",
                    ];
                }
            }
        }

        // Check for post slug conflicts
        if (isset($data['cms_posts']) && Schema::hasTable('cms_posts')) {
            $existingSlugs = DB::table('cms_posts')->pluck('slug')->toArray();
            foreach ($data['cms_posts'] as $post) {
                if (isset($post['slug']) && in_array($post['slug'], $existingSlugs)) {
                    $conflicts[] = [
                        'type' => 'post',
                        'slug' => $post['slug'],
                        'message' => "Post with slug '{$post['slug']}' already exists",
                    ];
                }
            }
        }

        return $conflicts;
    }

    protected function importJson(array $data, bool $dryRun): void
    {
        // Order of tables is important for foreign key relationships
        $importOrder = [
            'cms_categories',
            'cms_tags',
            'cms_pages',
            'cms_posts',
            'cms_category_post',
            'cms_post_tag',
            'cms_menus',
            'media',
        ];

        if ($dryRun) {
            // Analyze what would be imported
            foreach ($importOrder as $table) {
                if (isset($data[$table]) && is_array($data[$table])) {
                    $this->stats[str_replace('cms_', '', $table)] = count($data[$table]);
                }
            }

            return;
        }

        // Disable foreign key checks for clean import
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::transaction(function () use ($data, $importOrder) {
                foreach ($importOrder as $table) {
                    if (! isset($data[$table]) || ! is_array($data[$table])) {
                        continue;
                    }

                    foreach ($data[$table] as $record) {
                        $this->importRecord($table, $record);
                    }
                }
            });
        } finally {
            // Re-enable foreign key checks
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            } elseif (DB::getDriverName() === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    protected function importRecord(string $table, array $record): void
    {
        // Handle ID remapping and conflict resolution
        $record = $this->remapIds($table, $record);
        $record = $this->handleConflicts($table, $record);

        // Skip if conflict resolution decided to skip this record
        if ($record === null) {
            return;
        }

        // Handle pivot tables (no ID column)
        if (in_array($table, ['cms_category_post', 'cms_post_tag'])) {
            // Check if this relationship already exists (avoid duplicates after ID remapping)
            $exists = DB::table($table)
                ->where(array_filter($record, fn ($key) => in_array($key, ['category_id', 'post_id', 'tag_id']), ARRAY_FILTER_USE_KEY))
                ->exists();

            if (! $exists) {
                DB::table($table)->insert($record);
            }
        } else {
            // Remove id column to let database auto-increment
            $oldId = $record['id'] ?? null;
            unset($record['id']);

            // Insert record
            $newId = DB::table($table)->insertGetId($record);

            // Store ID mapping
            if ($oldId && in_array($table, ['cms_categories', 'cms_tags', 'cms_pages', 'cms_posts', 'media'])) {
                $this->idMappings[$table][$oldId] = $newId;
            }
        }

        // Update stats
        $this->updateStats($table);
    }

    protected function remapIds(string $table, array $data): array
    {
        // Remap foreign keys
        if (isset($data['parent_id']) && $data['parent_id'] && $table === 'cms_pages') {
            $data['parent_id'] = $this->idMappings['cms_pages'][$data['parent_id']] ?? $data['parent_id'];
        }

        if (isset($data['category_id']) && $data['category_id'] && $table === 'cms_category_post') {
            $data['category_id'] = $this->idMappings['cms_categories'][$data['category_id']] ?? $data['category_id'];
        }

        if (isset($data['post_id']) && $data['post_id']) {
            if ($table === 'cms_category_post' || $table === 'cms_post_tag') {
                $data['post_id'] = $this->idMappings['cms_posts'][$data['post_id']] ?? $data['post_id'];
            }
        }

        if (isset($data['tag_id']) && $data['tag_id'] && $table === 'cms_post_tag') {
            $data['tag_id'] = $this->idMappings['cms_tags'][$data['tag_id']] ?? $data['tag_id'];
        }

        if (isset($data['model_id']) && $data['model_id'] && $table === 'media') {
            $modelType = $data['model_type'] ?? '';
            if (str_contains($modelType, 'Page')) {
                $data['model_id'] = $this->idMappings['cms_pages'][$data['model_id']] ?? $data['model_id'];
            } elseif (str_contains($modelType, 'Post')) {
                $data['model_id'] = $this->idMappings['cms_posts'][$data['model_id']] ?? $data['model_id'];
            }
        }

        return $data;
    }

    protected function handleConflicts(string $table, array $data): array
    {
        // Only check pages and posts for slug conflicts
        if (! in_array($table, ['cms_pages', 'cms_posts'])) {
            return $data;
        }

        if (! isset($data['slug'])) {
            return $data;
        }

        $existingRecord = DB::table($table)->where('slug', $data['slug'])->first();

        if (! $existingRecord) {
            return $data;
        }

        // Handle based on strategy
        if ($this->conflictStrategy === 'skip') {
            $this->stats['conflicts'][] = [
                'table' => $table,
                'slug' => $data['slug'],
                'action' => 'skipped',
            ];

            return null;
        }

        if ($this->conflictStrategy === 'overwrite') {
            DB::table($table)->where('slug', $data['slug'])->delete();
            $this->stats['conflicts'][] = [
                'table' => $table,
                'slug' => $data['slug'],
                'action' => 'overwritten',
            ];

            return $data;
        }

        // Rename strategy (default)
        $originalSlug = $data['slug'];
        $data['slug'] = $this->generateUniqueSlug($table, $originalSlug);

        $this->stats['conflicts'][] = [
            'table' => $table,
            'original_slug' => $originalSlug,
            'new_slug' => $data['slug'],
            'action' => 'renamed',
        ];

        return $data;
    }

    protected function generateUniqueSlug(string $table, string $slug): string
    {
        $counter = 1;
        $newSlug = $slug.'-imported';

        while (DB::table($table)->where('slug', $newSlug)->exists()) {
            $counter++;
            $newSlug = $slug.'-imported-'.$counter;
        }

        return $newSlug;
    }

    protected function updateStats(string $table): void
    {
        $mapping = [
            'cms_pages' => 'pages',
            'cms_posts' => 'posts',
            'cms_categories' => 'categories',
            'cms_tags' => 'tags',
            'cms_menus' => 'menus',
            'media' => 'media_files',
        ];

        if (isset($mapping[$table])) {
            $this->stats[$mapping[$table]]++;
        }
    }

    protected function importMedia(bool $dryRun): void
    {
        $tarFile = $this->tempDir.'/cms_media.tar.gz';

        if (! File::exists($tarFile)) {
            return;
        }

        if ($dryRun) {
            return;
        }

        // Extract tar.gz
        $cwd = getcwd();
        chdir($this->tempDir);
        exec('tar -xzf cms_media.tar.gz', $output, $exitCode);
        chdir($cwd);

        if ($exitCode !== 0) {
            throw new \RuntimeException('Failed to extract media archive');
        }

        // Copy media files to storage
        $disk = config('cms.media_disk', 'public');
        $storage = Storage::disk($disk);

        $mediaDir = $this->tempDir.'/media';
        if (! File::exists($mediaDir)) {
            return;
        }

        $directories = File::directories($mediaDir);

        foreach ($directories as $directory) {
            $oldId = basename($directory);
            $newId = $this->idMappings['media'][$oldId] ?? $oldId;

            $targetDir = (string) $newId;

            // Copy all files from old ID directory to new ID directory
            $files = File::allFiles($directory);
            foreach ($files as $file) {
                $relativePath = str_replace($directory.'/', '', $file->getPathname());
                $targetPath = $targetDir.'/'.$relativePath;

                $storage->put($targetPath, File::get($file->getPathname()));
            }
        }
    }
}
