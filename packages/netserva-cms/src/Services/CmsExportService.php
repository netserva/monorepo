<?php

declare(strict_types=1);

namespace NetServa\Cms\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class CmsExportService
{
    protected array $cmsTables = [
        'cms_categories',
        'cms_tags',
        'cms_pages',
        'cms_posts',
        'cms_category_post',
        'cms_post_tag',
        'cms_menus',
    ];

    protected string $tempDir;

    protected array $stats = [
        'pages' => 0,
        'posts' => 0,
        'categories' => 0,
        'tags' => 0,
        'menus' => 0,
        'media_files' => 0,
    ];

    public function __construct()
    {
        $this->tempDir = storage_path('app/cms-export-'.time());
        File::makeDirectory($this->tempDir, 0755, true);
    }

    /**
     * Export all CMS content to a ZIP file
     */
    public function export(string $outputPath, bool $includeDrafts = false, bool $includeDeleted = false): array
    {
        // Gather statistics
        $this->gatherStats($includeDrafts, $includeDeleted);

        // Export to JSON
        $jsonFile = $this->exportJson($includeDrafts, $includeDeleted);

        // Export media files
        $mediaFile = $this->exportMedia();

        // Create ZIP package
        $this->createZipPackage($outputPath, $jsonFile, $mediaFile);

        // Cleanup temp directory
        File::deleteDirectory($this->tempDir);

        return [
            'output_path' => $outputPath,
            'stats' => $this->stats,
            'size' => File::size($outputPath),
        ];
    }

    protected function gatherStats(bool $includeDrafts, bool $includeDeleted): void
    {
        $this->stats['pages'] = $this->countRecords('cms_pages', $includeDrafts, $includeDeleted);
        $this->stats['posts'] = $this->countRecords('cms_posts', $includeDrafts, $includeDeleted);
        $this->stats['categories'] = DB::table('cms_categories')->count();
        $this->stats['tags'] = DB::table('cms_tags')->count();
        $this->stats['menus'] = DB::table('cms_menus')->count();

        // Count media files
        if (Schema::hasTable('media')) {
            $this->stats['media_files'] = DB::table('media')
                ->where('model_type', 'like', 'NetServa\\Cms\\%')
                ->count();
        }
    }

    protected function countRecords(string $table, bool $includeDrafts, bool $includeDeleted): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        if (! $includeDrafts) {
            $query->where('is_published', true);
        }

        if (! $includeDeleted) {
            $query->whereNull('deleted_at');
        }

        return $query->count();
    }

    protected function exportJson(bool $includeDrafts, bool $includeDeleted): string
    {
        $jsonFile = $this->tempDir.'/cms_export.json';

        $data = [
            'manifest' => [
                'export_date' => now()->toIso8601String(),
                'laravel_version' => app()->version(),
                'cms_version' => '1.0.0',
                'database_driver' => DB::getDriverName(),
                'stats' => $this->stats,
            ],
        ];

        // Export each table
        foreach ($this->cmsTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $query = DB::table($table);

            // Apply filters for pages and posts
            if (in_array($table, ['cms_pages', 'cms_posts'])) {
                if (! $includeDrafts) {
                    $query->where('is_published', true);
                }
                if (! $includeDeleted) {
                    $query->whereNull('deleted_at');
                }
            }

            $records = $query->get()->map(fn ($record) => (array) $record)->toArray();
            $data[$table] = $records;
        }

        // Export media records
        if (Schema::hasTable('media')) {
            $mediaRecords = DB::table('media')
                ->where('model_type', 'like', 'NetServa\\Cms\\%')
                ->get()
                ->map(fn ($record) => (array) $record)
                ->toArray();

            $data['media'] = $mediaRecords;
        }

        File::put($jsonFile, json_encode($data, JSON_PRETTY_PRINT));

        return $jsonFile;
    }

    protected function exportMedia(): ?string
    {
        if (! Schema::hasTable('media')) {
            return null;
        }

        $disk = config('cms.media_disk', 'public');
        $storage = Storage::disk($disk);

        $mediaFiles = DB::table('media')
            ->where('model_type', 'like', 'NetServa\\Cms\\%')
            ->get();

        if ($mediaFiles->isEmpty()) {
            return null;
        }

        // Create media directory in temp
        $mediaDir = $this->tempDir.'/media';
        File::makeDirectory($mediaDir, 0755, true);

        foreach ($mediaFiles as $media) {
            $sourcePath = $media->id.'/'.$media->file_name;

            if ($storage->exists($sourcePath)) {
                $targetPath = $mediaDir.'/'.$media->id;
                File::makeDirectory($targetPath, 0755, true);
                File::copy(
                    $storage->path($sourcePath),
                    $targetPath.'/'.$media->file_name
                );

                // Copy conversions directory if exists
                $conversionsPath = $media->id.'/conversions';
                if ($storage->exists($conversionsPath)) {
                    $targetConversionsPath = $targetPath.'/conversions';
                    File::makeDirectory($targetConversionsPath, 0755, true);

                    foreach ($storage->files($conversionsPath) as $file) {
                        File::copy(
                            $storage->path($file),
                            $targetConversionsPath.'/'.basename($file)
                        );
                    }
                }

                // Copy responsive-images directory if exists
                $responsivePath = $media->id.'/responsive-images';
                if ($storage->exists($responsivePath)) {
                    $targetResponsivePath = $targetPath.'/responsive-images';
                    File::makeDirectory($targetResponsivePath, 0755, true);

                    foreach ($storage->files($responsivePath) as $file) {
                        File::copy(
                            $storage->path($file),
                            $targetResponsivePath.'/'.basename($file)
                        );
                    }
                }
            }
        }

        // Create tar.gz archive
        $tarFile = $this->tempDir.'/cms_media.tar.gz';
        $cwd = getcwd();
        chdir($this->tempDir);
        exec('tar -czf cms_media.tar.gz media/', $output, $exitCode);
        chdir($cwd);

        if ($exitCode !== 0) {
            return null;
        }

        // Remove media directory after archiving
        File::deleteDirectory($mediaDir);

        return $tarFile;
    }

    protected function createZipPackage(string $outputPath, string $jsonFile, ?string $mediaFile): void
    {
        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create ZIP archive: {$outputPath}");
        }

        $zip->addFile($jsonFile, 'cms_export.json');

        if ($mediaFile && File::exists($mediaFile)) {
            $zip->addFile($mediaFile, 'cms_media.tar.gz');
        }

        $zip->close();
    }
}
