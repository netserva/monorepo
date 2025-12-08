<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Import database from JSON (database-agnostic)
 *
 * Uses Eloquent to import data from JSON, works across SQLite/MySQL/PostgreSQL.
 * Imports from ~/.netserva/backups/db/
 */
class DbImportCommand extends Command
{
    protected $signature = 'db:import
                            {file : JSON export file to import}
                            {--force : Skip confirmation prompt}
                            {--truncate : Truncate tables before import (default: merge/update)}';

    protected $description = 'Import database from JSON export (works across SQLite/MySQL)';

    protected string $importPath;

    public function __construct()
    {
        parent::__construct();
        $this->importPath = ($_SERVER['HOME'] ?? getenv('HOME')).'/.netserva/backups/db';
    }

    public function handle(): int
    {
        $file = $this->argument('file');

        // Handle relative paths - check import directory first
        if (! str_starts_with($file, '/')) {
            $fullPath = "{$this->importPath}/{$file}";
            if (File::exists($fullPath)) {
                $file = $fullPath;
            }
        }

        // Expand ~ to home directory
        if (str_starts_with($file, '~/')) {
            $file = ($_SERVER['HOME'] ?? getenv('HOME')).substr($file, 1);
        }

        if (! File::exists($file)) {
            $this->components->error("File not found: {$file}");
            $this->newLine();
            $this->components->info('Available exports:');
            $this->listExports();

            return self::FAILURE;
        }

        // Read and parse JSON
        $json = File::get($file);
        $export = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->components->error('Invalid JSON: '.json_last_error_msg());

            return self::FAILURE;
        }

        // Validate export structure
        if (! isset($export['meta']) || ! isset($export['tables'])) {
            $this->components->error('Invalid export format: missing meta or tables');

            return self::FAILURE;
        }

        $this->components->info('Import file: '.basename($file));
        $this->components->twoColumnDetail('Exported at', $export['meta']['exported_at'] ?? 'Unknown');
        $this->components->twoColumnDetail('Version', $export['meta']['version'] ?? 'Unknown');
        $this->newLine();

        // Show what will be imported
        $this->components->info('Tables to import:');
        $totalRecords = 0;
        foreach ($export['tables'] as $table => $records) {
            $count = count($records);
            $totalRecords += $count;
            $this->components->twoColumnDetail($table, (string) $count);
        }
        $this->newLine();

        // Confirm unless --force
        if (! $this->option('force')) {
            $action = $this->option('truncate') ? 'REPLACE' : 'MERGE with';
            $this->components->warn("This will {$action} existing data!");

            if (! $this->confirm('Continue with import?')) {
                $this->components->info('Import cancelled');

                return self::SUCCESS;
            }
        }

        // Create pre-import backup
        $this->components->task('Creating pre-import backup', function () {
            $this->callSilent('db:export', ['--name' => 'pre-import_'.now()->format('Y-m-d_His'), '--quiet' => true]);

            return true;
        });

        // Import tables in order
        $imported = 0;
        $errors = 0;

        // Disable FK checks for import (enables cross-database portability)
        $driver = config('database.default');
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');
        }

        // For MySQL with truncate: TRUNCATE tables BEFORE transaction (DDL causes implicit commit)
        // TRUNCATE resets auto_increment which is essential for preserving original IDs
        if ($driver === 'mysql' && $this->option('truncate')) {
            // Truncate in reverse order (children before parents) to avoid FK issues
            $tableNames = array_keys($export['tables']);
            foreach (array_reverse($tableNames) as $tableName) {
                try {
                    DB::statement("TRUNCATE TABLE {$tableName}");
                } catch (\Exception $e) {
                    $this->components->warn("Could not truncate {$tableName}: {$e->getMessage()}");
                }
            }
        }

        DB::beginTransaction();

        try {
            // Import all tables from JSON file dynamically
            foreach ($export['tables'] as $tableName => $records) {
                // Check if table exists in current database schema
                if (! DB::getSchemaBuilder()->hasTable($tableName)) {
                    $this->components->warn("Skipping {$tableName}: Table does not exist in current schema");
                    continue;
                }

                // Special handling for VPass: re-encrypt passwords with new APP_KEY
                if ($tableName === 'vpass' && class_exists(\NetServa\Core\Models\VPass::class)) {
                    // Clear table if requested
                    if ($this->option('truncate')) {
                        if ($driver === 'sqlite') {
                            DB::table($tableName)->delete();
                            DB::statement("DELETE FROM sqlite_sequence WHERE name='{$tableName}'");
                        }
                    }

                    $tableImported = 0;
                    $tableErrors = 0;

                    foreach ($records as $record) {
                        try {
                            // Use Eloquent to re-encrypt password with new APP_KEY
                            \NetServa\Core\Models\VPass::create($record);
                            $tableImported++;
                        } catch (\Exception $e) {
                            $tableErrors++;
                            $recordId = $record['id'] ?? 'unknown';
                            $this->components->warn("  ↳ Record ID {$recordId}: {$e->getMessage()}");
                        }
                    }

                    $imported += $tableImported;
                    $errors += $tableErrors;
                    $this->components->twoColumnDetail($tableName, "{$tableImported} imported".($tableErrors ? " ({$tableErrors} errors)" : ''));

                    continue;
                }

                // Clear table if requested - for SQLite use DELETE (MySQL already TRUNCATED above)
                if ($this->option('truncate') && $driver === 'sqlite') {
                    DB::table($tableName)->delete();
                    // Reset SQLite autoincrement
                    DB::statement("DELETE FROM sqlite_sequence WHERE name='{$tableName}'");
                }

                // Get target table columns
                $targetColumns = DB::getSchemaBuilder()->getColumnListing($tableName);

                // Filter and prepare records for batch insert
                $filteredRecords = [];
                foreach ($records as $record) {
                    // Filter to only columns that exist in target table
                    $filteredRecord = array_intersect_key($record, array_flip($targetColumns));
                    // Remove timestamps for clean insert (let DB set them)
                    unset($filteredRecord['created_at'], $filteredRecord['updated_at']);
                    // Convert field types for database compatibility
                    foreach ($filteredRecord as $key => $value) {
                        if (is_array($value)) {
                            // JSON-encode arrays/objects
                            $filteredRecord[$key] = json_encode($value);
                        } elseif (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                            // Convert ISO 8601 datetime to MySQL format (Y-m-d H:i:s)
                            try {
                                $filteredRecord[$key] = \Carbon\Carbon::parse($value)->format('Y-m-d H:i:s');
                            } catch (\Exception $e) {
                                // Leave as-is if parsing fails
                            }
                        }
                    }
                    $filteredRecords[] = $filteredRecord;
                }

                // Batch insert/upsert for performance (100 records at a time)
                $chunks = array_chunk($filteredRecords, 100);
                $tableImported = 0;
                $tableErrors = 0;

                foreach ($chunks as $chunk) {
                    try {
                        if ($this->option('truncate')) {
                            // Fresh import - use insert to preserve original IDs
                            DB::table($tableName)->insert($chunk);
                            $tableImported += count($chunk);
                        } else {
                            // Merge/update - use upsert
                            DB::table($tableName)->upsert($chunk, ['id']);
                            $tableImported += count($chunk);
                        }
                    } catch (\Exception $e) {
                        // Fall back to individual inserts for this chunk
                        foreach ($chunk as $record) {
                            try {
                                if ($this->option('truncate')) {
                                    DB::table($tableName)->insert($record);
                                } else {
                                    DB::table($tableName)->upsert([$record], ['id']);
                                }
                                $tableImported++;
                            } catch (\Exception $e2) {
                                $tableErrors++;
                                // Log detailed error for debugging
                                $recordId = $record['id'] ?? 'unknown';
                                $this->components->warn("  ↳ Record ID {$recordId}: {$e2->getMessage()}");
                            }
                        }
                    }
                }

                $imported += $tableImported;
                $errors += $tableErrors;
                $this->components->twoColumnDetail($tableName, "{$tableImported} imported".($tableErrors ? " ({$tableErrors} errors)" : ''));
            }

            DB::commit();

            // Reset auto_increment for MySQL after commit (DDL causes implicit commit, can't be in transaction)
            if ($driver === 'mysql' && $this->option('truncate')) {
                foreach ($export['tables'] as $tableName => $records) {
                    $maxId = collect($records)->max('id') ?? 0;
                    if ($maxId > 0) {
                        try {
                            DB::statement("ALTER TABLE {$tableName} AUTO_INCREMENT = ".($maxId + 1));
                        } catch (\Exception $e) {
                            // Ignore errors (table might not have auto_increment)
                        }
                    }
                }
            }

            // Re-enable FK checks
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=ON');
            }

            $this->newLine();
            $this->components->success("Imported {$imported} records ({$errors} errors)");

            return $errors > 0 ? self::FAILURE : self::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();

            // Re-enable FK checks even on failure
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=ON');
            }

            $this->components->error("Import failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function listExports(): void
    {
        if (! File::isDirectory($this->importPath)) {
            $this->line('  No exports found');

            return;
        }

        $files = collect(File::files($this->importPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '_export.json'))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->take(10);

        if ($files->isEmpty()) {
            $this->line('  No exports found');

            return;
        }

        foreach ($files as $file) {
            $this->line('  '.$file->getFilename());
        }
    }
}
