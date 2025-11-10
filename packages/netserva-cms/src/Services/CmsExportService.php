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

        // Export SQL
        $sqlFile = $this->exportSql($includeDrafts, $includeDeleted);

        // Export media files
        $mediaFile = $this->exportMedia();

        // Create manifest
        $manifestFile = $this->createManifest();

        // Create ZIP package
        $this->createZipPackage($outputPath, $sqlFile, $mediaFile, $manifestFile);

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

    protected function exportSql(bool $includeDrafts, bool $includeDeleted): string
    {
        $sqlFile = $this->tempDir.'/cms_export.sql';
        $sql = "-- NetServa CMS Export\n";
        $sql .= '-- Generated: '.now()->toDateTimeString()."\n";
        $sql .= '-- Database: '.DB::connection()->getDatabaseName()."\n\n";

        foreach ($this->cmsTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $sql .= "-- Table: {$table}\n";

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

            $records = $query->get();

            foreach ($records as $record) {
                $values = [];
                foreach ((array) $record as $column => $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        // Use str_replace to double single quotes for SQL escaping
                        $escaped = str_replace("'", "''", $value);
                        $values[] = "'".$escaped."'";
                    }
                }

                $columns = implode(', ', array_keys((array) $record));
                $valuesStr = implode(', ', $values);
                $sql .= "INSERT INTO {$table} ({$columns}) VALUES ({$valuesStr});\n";
            }

            $sql .= "\n";
        }

        // Export media records
        if (Schema::hasTable('media')) {
            $sql .= "-- Table: media\n";
            $mediaRecords = DB::table('media')
                ->where('model_type', 'like', 'NetServa\\Cms\\%')
                ->get();

            foreach ($mediaRecords as $record) {
                $values = [];
                foreach ((array) $record as $column => $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        // Use str_replace to double single quotes for SQL escaping
                        $escaped = str_replace("'", "''", $value);
                        $values[] = "'".$escaped."'";
                    }
                }

                $columns = implode(', ', array_keys((array) $record));
                $valuesStr = implode(', ', $values);
                $sql .= "INSERT INTO media ({$columns}) VALUES ({$valuesStr});\n";
            }
        }

        File::put($sqlFile, $sql);

        return $sqlFile;
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

    protected function createManifest(): string
    {
        $manifest = [
            'export_date' => now()->toIso8601String(),
            'laravel_version' => app()->version(),
            'cms_version' => '1.0.0', // TODO: Get from package version
            'database_driver' => DB::getDriverName(),
            'stats' => $this->stats,
        ];

        $manifestFile = $this->tempDir.'/manifest.json';
        File::put($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT));

        return $manifestFile;
    }

    protected function createZipPackage(string $outputPath, string $sqlFile, ?string $mediaFile, string $manifestFile): void
    {
        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Failed to create ZIP archive: {$outputPath}");
        }

        $zip->addFile($sqlFile, 'cms_export.sql');
        $zip->addFile($manifestFile, 'manifest.json');

        if ($mediaFile && File::exists($mediaFile)) {
            $zip->addFile($mediaFile, 'cms_media.tar.gz');
        }

        $zip->close();
    }
}
