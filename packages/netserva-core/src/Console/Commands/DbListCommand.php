<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * List available database snapshots
 */
class DbListCommand extends Command
{
    protected $signature = 'db:list
                            {--connection= : Filter by database connection}';

    protected $description = 'List available database snapshots';

    protected string $backupPath;

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups');
    }

    public function handle(): int
    {
        if (! File::isDirectory($this->backupPath)) {
            $this->components->warn('No snapshots found (backup directory does not exist)');
            $this->components->info('Create one with: php artisan db:snapshot');

            return self::SUCCESS;
        }

        $connection = $this->option('connection');
        $files = collect(File::files($this->backupPath))
            ->filter(function ($file) use ($connection) {
                $name = $file->getFilename();

                // Only show .sql and .sqlite files
                if (! str_ends_with($name, '.sql') && ! str_ends_with($name, '.sqlite')) {
                    return false;
                }

                // Filter by connection if specified
                if ($connection) {
                    return str_contains($name, "_{$connection}.");
                }

                return true;
            })
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        if ($files->isEmpty()) {
            $this->components->warn('No snapshots found');
            $this->components->info('Create one with: php artisan db:snapshot');

            return self::SUCCESS;
        }

        $this->components->info("Snapshots in: {$this->backupPath}");
        $this->newLine();

        $rows = $files->map(function ($file) {
            $name = $file->getFilename();

            // Extract snapshot name (remove connection suffix and extension)
            $snapshotName = preg_replace('/_(sqlite|mysql|pgsql)\.(sql|sqlite)$/', '', $name);

            return [
                'name' => $snapshotName,
                'file' => $name,
                'size' => $this->formatBytes($file->getSize()),
                'created' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        })->toArray();

        $this->table(['Name', 'File', 'Size', 'Created'], $rows);

        $this->newLine();
        $this->components->info('Restore with: php artisan db:restore <name>');
        $this->components->info('Restore latest: php artisan db:restore latest');

        return self::SUCCESS;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
