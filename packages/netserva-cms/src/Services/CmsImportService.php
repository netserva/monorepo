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

        // Read manifest
        $manifest = $this->readManifest();

        // Parse and import SQL
        $this->importSql($dryRun);

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
        $requiredFiles = ['manifest.json', 'cms_export.sql'];
        foreach ($requiredFiles as $file) {
            if ($zip->locateName($file) === false) {
                $zip->close();
                throw new \RuntimeException("Invalid CMS export: missing {$file}");
            }
        }

        $zip->close();

        // Extract and read manifest
        $this->extractZip($zipPath);
        $manifest = $this->readManifest();

        // Check for potential conflicts
        $conflicts = $this->detectConflicts();

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

    protected function readManifest(): array
    {
        $manifestPath = $this->tempDir.'/manifest.json';

        if (! File::exists($manifestPath)) {
            return [];
        }

        return json_decode(File::get($manifestPath), true);
    }

    protected function detectConflicts(): array
    {
        $conflicts = [];
        $sqlPath = $this->tempDir.'/cms_export.sql';

        if (! File::exists($sqlPath)) {
            return $conflicts;
        }

        $sql = File::get($sqlPath);

        // Extract slugs from INSERT statements
        preg_match_all("/INSERT INTO cms_pages.*?slug.*?'([^']+)'/", $sql, $pageMatches);
        preg_match_all("/INSERT INTO cms_posts.*?slug.*?'([^']+)'/", $sql, $postMatches);

        // Check for existing slugs
        if (Schema::hasTable('cms_pages')) {
            $existingSlugs = DB::table('cms_pages')->pluck('slug')->toArray();
            foreach ($pageMatches[1] ?? [] as $slug) {
                if (in_array($slug, $existingSlugs)) {
                    $conflicts[] = [
                        'type' => 'page',
                        'slug' => $slug,
                        'message' => "Page with slug '{$slug}' already exists",
                    ];
                }
            }
        }

        if (Schema::hasTable('cms_posts')) {
            $existingSlugs = DB::table('cms_posts')->pluck('slug')->toArray();
            foreach ($postMatches[1] ?? [] as $slug) {
                if (in_array($slug, $existingSlugs)) {
                    $conflicts[] = [
                        'type' => 'post',
                        'slug' => $slug,
                        'message' => "Post with slug '{$slug}' already exists",
                    ];
                }
            }
        }

        return $conflicts;
    }

    protected function importSql(bool $dryRun): void
    {
        $sqlPath = $this->tempDir.'/cms_export.sql';

        if (! File::exists($sqlPath)) {
            throw new \RuntimeException('SQL export file not found');
        }

        $sql = File::get($sqlPath);
        $statements = $this->parseSqlStatements($sql);

        if ($dryRun) {
            $this->analyzeStatements($statements);

            return;
        }

        // Disable foreign key checks (must be done outside transaction for SQLite)
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        } elseif (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            DB::transaction(function () use ($statements) {
                foreach ($statements as $statement) {
                    $this->executeStatement($statement);
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

    protected function parseSqlStatements(string $sql): array
    {
        $statements = [];
        $lines = explode("\n", $sql);
        $currentStatement = '';
        $inString = false;
        $prevChar = '';

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Skip comments when not building a statement
            if (empty($currentStatement) && (empty($trimmed) || str_starts_with($trimmed, '--'))) {
                continue;
            }

            // Start of a new INSERT statement
            if (str_starts_with($trimmed, 'INSERT INTO')) {
                $currentStatement = $line;
                $inString = false;
                $prevChar = '';

                // Check if this is a single-line statement
                // Count quotes to determine if we're ending outside a string
                $quoteCount = 0;
                for ($i = 0; $i < strlen($line); $i++) {
                    if ($line[$i] === "'" && ($i == 0 || $line[$i - 1] !== "'")) {
                        $quoteCount++;
                    }
                }
                $inString = ($quoteCount % 2) === 1;

                if (str_ends_with($trimmed, ');') && ! $inString) {
                    $statements[] = $currentStatement;
                    $currentStatement = '';
                }

                continue;
            }

            // Continuation of multi-line statement
            if (! empty($currentStatement)) {
                $currentStatement .= "\n".$line;

                // Track string state for this line
                for ($i = 0; $i < strlen($line); $i++) {
                    $char = $line[$i];
                    if ($char === "'") {
                        // Check for doubled quotes (SQL escape)
                        if ($i + 1 < strlen($line) && $line[$i + 1] === "'") {
                            $i++; // Skip the escaped quote

                            continue;
                        }
                        $inString = ! $inString;
                    }
                }

                // Check if this line ends the statement (only if we're outside a string)
                if (str_ends_with($trimmed, ');') && ! $inString) {
                    $statements[] = $currentStatement;
                    $currentStatement = '';
                    $inString = false;
                }
            }
        }

        return $statements;
    }

    protected function analyzeStatements(array $statements): void
    {
        foreach ($statements as $statement) {
            if (preg_match('/INSERT INTO (cms_\w+)/', $statement, $matches)) {
                $table = $matches[1];

                if (in_array($table, ['cms_pages', 'cms_posts', 'cms_categories', 'cms_tags', 'cms_menus'])) {
                    $key = str_replace('cms_', '', $table);
                    if (isset($this->stats[$key])) {
                        $this->stats[$key]++;
                    }
                }
            } elseif (preg_match('/INSERT INTO media/', $statement)) {
                $this->stats['media_files']++;
            }
        }
    }

    protected function executeStatement(string $statement): void
    {
        // Parse INSERT statement (with DOTALL flag for multi-line content)
        if (! preg_match('/INSERT INTO (\w+) \(([^)]+)\) VALUES \((.+)\);/s', $statement, $matches)) {
            return;
        }

        [$_, $table, $columnsStr, $valuesStr] = $matches;

        // Clean up multi-line values string
        $valuesStr = trim($valuesStr);

        $columns = array_map('trim', explode(',', $columnsStr));
        $values = $this->parseValues($valuesStr);

        // Validate column/value count match
        if (count($columns) !== count($values)) {
            throw new \RuntimeException(
                "Column/value mismatch in {$table}: ".
                count($columns).' columns vs '.count($values).' values'
            );
        }

        // Create associative array
        $data = array_combine($columns, $values);

        // Handle ID remapping and conflict resolution
        $data = $this->remapIds($table, $data);
        $data = $this->handleConflicts($table, $data);

        // Skip if conflict resolution decided to skip this record
        if ($data === null) {
            return;
        }

        // Handle pivot tables (no ID column)
        if (in_array($table, ['cms_category_post', 'cms_post_tag'])) {
            // Check if this relationship already exists (avoid duplicates after ID remapping)
            $exists = DB::table($table)
                ->where(array_filter($data, fn ($key) => in_array($key, ['category_id', 'post_id', 'tag_id']), ARRAY_FILTER_USE_KEY))
                ->exists();

            if (! $exists) {
                DB::table($table)->insert($data);
            }
        } else {
            // Remove id column to let database auto-increment
            $oldId = $data['id'] ?? null;
            unset($data['id']);

            // Insert record
            $newId = DB::table($table)->insertGetId($data);

            // Store ID mapping
            if ($oldId && in_array($table, ['cms_categories', 'cms_tags', 'cms_pages', 'cms_posts', 'media'])) {
                $this->idMappings[$table][$oldId] = $newId;
            }
        }

        // Update stats
        $this->updateStats($table);
    }

    protected function parseValues(string $valuesStr): array
    {
        $values = [];
        $current = '';
        $inString = false;

        for ($i = 0; $i < strlen($valuesStr); $i++) {
            $char = $valuesStr[$i];

            if ($char === "'") {
                // Check if this is a doubled single quote (escape sequence in SQL)
                if ($inString && isset($valuesStr[$i + 1]) && $valuesStr[$i + 1] === "'") {
                    $current .= "'"; // Add single quote to content
                    $i++; // Skip the next quote

                    continue;
                }

                // Toggle string state
                $inString = ! $inString;

                continue;
            }

            if ($char === ',' && ! $inString) {
                $values[] = $this->normalizeValue(trim($current));
                $current = '';

                continue;
            }

            $current .= $char;
        }

        if (! empty($current)) {
            $values[] = $this->normalizeValue(trim($current));
        }

        return $values;
    }

    protected function normalizeValue(string $value): mixed
    {
        if ($value === 'NULL') {
            return null;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        // Return as-is (quotes already handled, SQL escapes already unescaped)
        return $value;
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
