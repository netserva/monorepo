<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Export database to JSON (database-agnostic)
 *
 * Uses Eloquent to export data as JSON, works across SQLite/MySQL/PostgreSQL.
 * Exports to ~/.netserva/backups/db/ for emergency recovery.
 */
class DbExportCommand extends Command
{
    protected $signature = 'db:export
                            {--name= : Export filename (defaults to timestamp)}';

    protected $description = 'Export database to JSON (works across SQLite/MySQL)';

    protected string $exportPath;

    /**
     * Tables to exclude from export (Laravel internal tables)
     */
    protected array $excludedTables = [
        'migrations',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_reset_tokens',
        'sessions',
        'personal_access_tokens',
        'pulse_aggregates',
        'pulse_entries',
        'pulse_values',
        'telescope_entries',
        'telescope_entries_tags',
        'telescope_monitoring',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->exportPath = ($_SERVER['HOME'] ?? getenv('HOME')).'/.netserva/backups/db';
    }

    public function handle(): int
    {
        $quiet = $this->option('quiet');  // Laravel's built-in option

        // Ensure export directory exists
        if (! File::isDirectory($this->exportPath)) {
            @mkdir($this->exportPath, 0700, true);
        }

        $name = $this->option('name') ?? now()->format('Y-m-d_His');
        $filename = "{$name}_export.json";
        $filepath = "{$this->exportPath}/{$filename}";

        if (! $quiet) {
            $this->components->info("Exporting database to: {$filename}");
        }

        $export = [
            'meta' => [
                'version' => '1.0',
                'exported_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'laravel_version' => app()->version(),
            ],
            'tables' => [],
        ];

        $totalRecords = 0;

        // Dynamically get all tables from database schema
        $allTables = DB::getSchemaBuilder()->getTables();

        // Filter out excluded tables and sort by name for consistency
        $tablesToExport = collect($allTables)
            ->map(fn($table) => $table['name'])
            ->reject(fn($tableName) => in_array($tableName, $this->excludedTables))
            ->reject(fn($tableName) => str_starts_with($tableName, 'sqlite_')) // SQLite internal
            ->sort()
            ->values();

        foreach ($tablesToExport as $tableName) {
            try {
                // Special handling for VPass: decrypt passwords for portable backup
                if ($tableName === 'vpass' && class_exists(\NetServa\Core\Models\VPass::class)) {
                    $records = \NetServa\Core\Models\VPass::all();
                    $count = $records->count();

                    if ($count > 0) {
                        $export['tables'][$tableName] = $records->map(function ($record) {
                            $data = $record->toArray();
                            // Decrypt password to plain text for APP_KEY portability
                            $data['password'] = $record->getSecret();

                            return $data;
                        })->toArray();

                        $totalRecords += $count;

                        if (! $quiet) {
                            $this->components->twoColumnDetail($tableName, (string) $count);
                        }
                    }

                    continue;
                }

                // Standard export for all other tables
                $records = DB::table($tableName)->get();
                $count = $records->count();

                if ($count > 0) {
                    // Convert to array
                    $export['tables'][$tableName] = $records->map(function ($record) {
                        return (array) $record;
                    })->toArray();

                    $totalRecords += $count;

                    if (! $quiet) {
                        $this->components->twoColumnDetail($tableName, (string) $count);
                    }
                }
            } catch (\Exception $e) {
                if (! $quiet) {
                    $this->components->warn("Skipping {$tableName}: {$e->getMessage()}");
                }
            }
        }

        // Write JSON file
        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        File::put($filepath, $json);

        $size = $this->formatBytes(File::size($filepath));

        if (! $quiet) {
            $this->newLine();
            $this->components->success("Exported {$totalRecords} records ({$size})");
            $this->components->info("File: {$filepath}");
            $this->newLine();
            $this->components->info('Restore with: php artisan db:import '.basename($filepath));
        }

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
