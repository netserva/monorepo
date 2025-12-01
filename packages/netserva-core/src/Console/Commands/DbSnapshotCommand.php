<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Create a database snapshot (SQL dump)
 *
 * Works with SQLite and MySQL databases.
 * Snapshots are stored in storage/app/backups/
 */
class DbSnapshotCommand extends Command
{
    protected $signature = 'db:snapshot
                            {name? : Optional name for the snapshot (defaults to timestamp)}
                            {--connection= : Database connection to use}';

    protected $description = 'Create a database snapshot (SQL dump) for backup/restore';

    protected string $backupPath;

    public function __construct()
    {
        parent::__construct();
        $this->backupPath = storage_path('app/backups');
    }

    public function handle(): int
    {
        // Ensure backup directory exists
        if (! File::isDirectory($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }

        $connection = $this->option('connection') ?? config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        $name = $this->argument('name') ?? now()->format('Y-m-d_His');
        $filename = "{$name}_{$connection}.sql";
        $filepath = "{$this->backupPath}/{$filename}";

        $this->components->info("Creating snapshot: {$filename}");

        try {
            $result = match ($driver) {
                'sqlite' => $this->snapshotSqlite($connection, $filepath),
                'mysql' => $this->snapshotMysql($connection, $filepath),
                'pgsql' => $this->snapshotPostgres($connection, $filepath),
                default => throw new \RuntimeException("Unsupported database driver: {$driver}"),
            };

            if ($result) {
                $size = $this->formatBytes(File::size($filepath));
                $this->components->success("Snapshot created: {$filepath} ({$size})");

                // Show table counts
                $this->showTableSummary($connection);

                return self::SUCCESS;
            }

            $this->components->error('Failed to create snapshot');

            return self::FAILURE;

        } catch (\Exception $e) {
            $this->components->error("Snapshot failed: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    protected function snapshotSqlite(string $connection, string $filepath): bool
    {
        $database = config("database.connections.{$connection}.database");

        if ($database === ':memory:') {
            $this->components->warn('Cannot snapshot in-memory database');

            return false;
        }

        if (! File::exists($database)) {
            $this->components->error("Database file not found: {$database}");

            return false;
        }

        // Use sqlite3 .dump command for complete SQL dump
        $command = sprintf(
            'sqlite3 %s .dump > %s 2>&1',
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            // Fallback: copy the file directly
            $this->components->warn('sqlite3 dump failed, copying database file directly');
            $binaryPath = str_replace('.sql', '.sqlite', $filepath);
            File::copy($database, $binaryPath);
            $this->components->info("Binary copy saved to: {$binaryPath}");

            return true;
        }

        return true;
    }

    protected function snapshotMysql(string $connection, string $filepath): bool
    {
        $config = config("database.connections.{$connection}");

        $command = sprintf(
            'mysqldump --host=%s --port=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 3306)),
            escapeshellarg($config['username'] ?? 'root'),
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['database']),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    protected function snapshotPostgres(string $connection, string $filepath): bool
    {
        $config = config("database.connections.{$connection}");

        $command = sprintf(
            'PGPASSWORD=%s pg_dump --host=%s --port=%s --username=%s %s > %s 2>&1',
            escapeshellarg($config['password'] ?? ''),
            escapeshellarg($config['host'] ?? '127.0.0.1'),
            escapeshellarg((string) ($config['port'] ?? 5432)),
            escapeshellarg($config['username'] ?? 'postgres'),
            escapeshellarg($config['database']),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        return $returnCode === 0;
    }

    protected function showTableSummary(string $connection): void
    {
        $driver = config("database.connections.{$connection}.driver");

        $tables = match ($driver) {
            'sqlite' => DB::connection($connection)
                ->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"),
            'mysql' => DB::connection($connection)->select('SHOW TABLES'),
            'pgsql' => DB::connection($connection)
                ->select("SELECT tablename as name FROM pg_tables WHERE schemaname = 'public'"),
            default => [],
        };

        $this->components->twoColumnDetail('Tables backed up', (string) count($tables));

        // Show row counts for key tables
        $keyTables = ['dns_zones', 'dns_records', 'wireguard_servers', 'wireguard_peers',
            'fleet_vnodes', 'fleet_vhosts', 'ip_networks', 'ssh_hosts'];

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

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $factor = floor((strlen((string) $bytes) - 1) / 3);

        return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
    }
}
