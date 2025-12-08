<?php

declare(strict_types=1);

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Models\VPass;
use NetServa\Dns\Models\DnsProvider;
use NetServa\Dns\Models\DnsRecord;
use NetServa\Dns\Models\DnsZone;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

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
     * Tables to export in dependency order (parents before children)
     */
    protected array $tables = [
        // Core
        'vpass' => VPass::class,
        'ssh_hosts' => SshHost::class,

        // Fleet (order matters for FKs)
        'fleet_vsites' => FleetVsite::class,
        'fleet_vnodes' => FleetVnode::class,
        'fleet_vhosts' => FleetVhost::class,

        // DNS
        'dns_providers' => DnsProvider::class,
        'dns_zones' => DnsZone::class,
        'dns_records' => DnsRecord::class,
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

        foreach ($this->tables as $tableName => $modelClass) {
            if (! class_exists($modelClass)) {
                if (! $quiet) {
                    $this->components->warn("Skipping {$tableName}: Model not found");
                }

                continue;
            }

            try {
                $records = $modelClass::all();
                $count = $records->count();

                if ($count > 0) {
                    // For VPass, decrypt passwords for plain text backup
                    if ($modelClass === VPass::class) {
                        $export['tables'][$tableName] = $records->map(function ($record) {
                            $data = $record->toArray();
                            $data['password'] = $record->getSecret(); // Decrypt

                            return $data;
                        })->toArray();
                    } else {
                        $export['tables'][$tableName] = $records->toArray();
                    }

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
