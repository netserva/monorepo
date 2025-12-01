<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Restore a database from snapshot
 *
 * Works with SQLite and MySQL databases.
 * Restores from storage/app/backups/
 */
class DbRestoreCommand extends Command
{
    protected $signature = 'db:restore
                            {name? : Snapshot name to restore (use "latest" for most recent)}
                            {--connection= : Database connection to use}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore database from a snapshot';

    protected string $backupPath;

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups');
    }

    public function handle(): int
    {
        $connection = $this->option('connection') ?? config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        // Find the snapshot file
        $name = $this->argument('name');

        if (! $name) {
            $this->components->error('Please specify a snapshot name or "latest"');
            $this->call('db:list');

            return self::FAILURE;
        }

        $filepath = $this->findSnapshot($name, $connection);

        if (! $filepath) {
            $this->components->error("Snapshot not found: {$name}");
            $this->call('db:list');

            return self::FAILURE;
        }

        $this->components->info("Restoring from: {$filepath}");

        // Confirm unless --force
        if (! $this->option('force')) {
            $this->components->warn('This will OVERWRITE all current data!');

            if (! $this->confirm('Are you sure you want to restore this snapshot?')) {
                $this->components->info('Restore cancelled');

                return self::SUCCESS;
            }
        }

        try {
            // Create a pre-restore backup first
            $this->components->task('Creating pre-restore backup', function () {
                $this->callSilent('db:snapshot', ['name' => 'pre-restore_'.now()->format('Y-m-d_His')]);

                return true;
            });

            $result = match ($driver) {
                'sqlite' => $this->restoreSqlite($connection, $filepath),
                'mysql' => $this->restoreMysql($connection, $filepath),
                'pgsql' => $this->restorePostgres($connection, $filepath),
                default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
            };

            if ($result) {
                $this->components->success('Database restored successfully!');
                $this->showTableSummary($connection);

                return self::SUCCESS;
            }

            $this->components->error('Restore failed');

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->components->error("Restore failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function findSnapshot(string $name, string $connection): ?string
    {
        if (! File::isDirectory($this->backupPath)) {
            return null;
        }

        // Handle "latest" keyword
        if ($name === 'latest') {
            $files = collect(File::files($this->backupPath))
                ->filter(fn ($file) => str_ends_with($file->getFilename(), "_{$connection}.sql")
                    || str_ends_with($file->getFilename(), "_{$connection}.sqlite"))
                ->sortByDesc(fn ($file) => $file->getMTime());

            return $files->first()?->getPathname();
        }

        // Try exact match with connection suffix
        $sqlPath = "{$this->backupPath}/{$name}_{$connection}.sql";
        if (File::exists($sqlPath)) {
            return $sqlPath;
        }

        // Try sqlite binary backup
        $sqlitePath = "{$this->backupPath}/{$name}_{$connection}.sqlite";
        if (File::exists($sqlitePath)) {
            return $sqlitePath;
        }

        // Try without connection suffix (legacy)
        $legacyPath = "{$this->backupPath}/{$name}.sql";
        if (File::exists($legacyPath)) {
            return $legacyPath;
        }

        return null;
    }

    protected function restoreSqlite(string $connection, string $filepath): bool
    {
        $database = config("database.connections.{$connection}.database");

        if ($database === ':memory:') {
            $this->components->warn('Cannot restore to in-memory database');

            return false;
        }

        // Handle binary SQLite backup
        if (str_ends_with($filepath, '.sqlite')) {
            DB::connection($connection)->disconnect();
            File::copy($filepath, $database);
            DB::connection($connection)->reconnect();

            return true;
        }

        // Handle SQL dump restore
        // First, clear existing database
        DB::connection($connection)->disconnect();

        if (File::exists($database)) {
            File::delete($database);
        }

        // Create fresh database file
        File::put($database, '');

        // Import SQL dump
        $command = sprintf(
            'sqlite3 %s < %s 2>&1',
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        DB::connection($connection)->reconnect();

        return $returnCode === 0;
    }

    protected function restoreMysql(string $connection, string $filepath): bool
    {
        $config = config("database.connections.{$connection}");

        // Drop and recreate database
        $dropCommand = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s -e "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s;" 2>&1',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg($config['username'] ?? 'root'),
            escapeshellarg($config['password'] ?? ''),
            $config['database'],
            $config['database']
        );

        exec($dropCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            return false;
        }

        // Import dump
        $importCommand = sprintf(
            'mysql --host=%s --port=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg($config['username'] ?? 'root'),
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['database']),
            escapeshellarg($filepath)
        );

        exec($importCommand, $output, $returnCode);

        return $returnCode === 0;
    }

    protected function restorePostgres(string $connection, string $filepath): bool
    {
        $config = config("database.connections.{$connection}");

        // Drop and recreate database
        $dropCommand = sprintf(
            'PGPASSWORD=%s psql --host=%s --port=%s --username=%s -c "DROP DATABASE IF EXISTS %s; CREATE DATABASE %s;" 2>&1',
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 5432)),
            escapeshellarg($config['username'] ?? 'postgres'),
            $config['database'],
            $config['database']
        );

        exec($dropCommand, $output, $returnCode);

        if ($returnCode !== 0) {
            return false;
        }

        // Import dump
        $importCommand = sprintf(
            'PGPASSWORD=%s psql --host=%s --port=%s --username=%s %s < %s 2>&1',
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 5432)),
            escapeshellarg($config['username'] ?? 'postgres'),
            escapeshellarg($config['database']),
            escapeshellarg($filepath)
        );

        exec($importCommand, $output, $returnCode);

        return $returnCode === 0;
    }

    protected function showTableSummary(string $connection): void
    {
        $keyTables = ['dns_zones', 'dns_records', 'wireguard_servers', 'wireguard_peers',
            'fleet_vnodes', 'fleet_vhosts', 'ip_networks', 'ssh_hosts'];

        $this->newLine();
        $this->components->info('Restored data:');

        foreach ($keyTables as $table) {
            try {
                $count = DB::connection($connection)->table($table)->count();
                if ($count > 0) {
                    $this->components->twoColumnDetail("  {$table}", (string) $count);
                }
            } catch (\Exception $e) {
                // Table doesn't exist, skip
            }
        }
    }
}
