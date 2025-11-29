<?php

namespace NetServa\Core\Console\Commands;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\warning;

/**
 * Import VHost Data Command - NetServa 3.0 Database Migration
 *
 * Imports existing var/{vsite}/{vnode}/{vhost} files into the database
 * for the database-first architecture transition.
 */
class ImportVHostDataCommand extends BaseNetServaCommand
{
    protected $signature = 'import:vhosts
                           {path? : Path to var directory (defaults to ~/.ns/var)}
                           {--dry-run : Show what would be imported without making changes}
                           {--force : Overwrite existing database records}
                           {--vsite= : Import only specific vsite}
                           {--vnode= : Import only specific vnode}
                           {--interactive : Interactive mode with confirmations}';

    protected $description = 'Import VHost configurations from var/ files to database (NetServa 3.0 Migration)';

    protected array $expectedVariables = [
        'ADMIN', 'AHOST', 'AMAIL', 'ANAME', 'APASS', 'A_GID', 'A_UID',
        'BPATH', 'CIMAP', 'CSMTP', 'C_DNS', 'C_FPM', 'C_SQL', 'C_SSL', 'C_WEB',
        'DBMYS', 'DBSQL', 'DHOST', 'DNAME', 'DPASS', 'DPATH', 'DPORT', 'DTYPE', 'DUSER',
        'EPASS', 'EXMYS', 'EXSQL', 'HDOMN', 'HNAME', 'IP4_0',
        'MHOST', 'MPATH', 'OSMIR', 'OSREL', 'OSTYP',
        'SQCMD', 'SQDNS', 'TAREA', 'TCITY', 'UPASS', 'UPATH', 'UUSER',
        'U_GID', 'U_SHL', 'U_UID', 'VHOST', 'VPATH', 'VUSER', 'V_PHP',
        'WPASS', 'WPATH', 'WPUSR', 'WUGID',
    ];

    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    protected int $errorCount = 0;

    protected array $errors = [];

    public function handle(): int
    {
        return $this->executeWithContext(function () {
            $varPath = $this->argument('path') ?: env('HOME').'/.ns/var';

            if (! File::exists($varPath)) {
                $this->error("❌ Var directory not found: {$varPath}");

                return 1;
            }

            info('🚀 NetServa VHost Data Import - Database Migration Tool');

            if ($this->option('interactive')) {
                $this->line('');
                $this->line('This will import VHost configurations from:');
                $this->line("  📁 Source: <fg=cyan>{$varPath}</fg=cyan>");
                $this->line('  🗄️  Target: Database (fleet_vhosts table)');
                $this->line('');

                if (! confirm('Proceed with import?', true)) {
                    info('Import cancelled');

                    return 0;
                }
            }

            $this->line('');
            $this->line('🔍 Scanning var directory structure...');

            $vhostFiles = $this->scanVHostFiles($varPath);

            if (empty($vhostFiles)) {
                warning('No VHost files found to import');

                return 0;
            }

            $count = count($vhostFiles);
            $this->info("Found <fg=green>{$count}</fg=green> VHost files to process");

            if ($this->option('dry-run')) {
                return $this->performDryRun($vhostFiles);
            }

            return $this->performImport($vhostFiles);
        });
    }

    /**
     * Scan var directory for VHost files
     */
    protected function scanVHostFiles(string $varPath): array
    {
        $vhostFiles = [];
        $vsiteFilter = $this->option('vsite');
        $vnodeFilter = $this->option('vnode');

        foreach (File::directories($varPath) as $vsiteDir) {
            $vsiteName = basename($vsiteDir);

            // Skip if vsite filter provided and doesn't match
            if ($vsiteFilter && $vsiteName !== $vsiteFilter) {
                continue;
            }

            foreach (File::directories($vsiteDir) as $vnodeDir) {
                $vnodeName = basename($vnodeDir);

                // Skip if vnode filter provided and doesn't match
                if ($vnodeFilter && $vnodeName !== $vnodeFilter) {
                    continue;
                }

                foreach (File::files($vnodeDir) as $vhostFile) {
                    $vhostName = basename($vhostFile->getPathname());

                    // Skip .conf files and other non-vhost files
                    if (str_ends_with($vhostName, '.conf') || str_starts_with($vhostName, '.')) {
                        continue;
                    }

                    $vhostFiles[] = [
                        'vsite' => $vsiteName,
                        'vnode' => $vnodeName,
                        'vhost' => $vhostName,
                        'path' => $vhostFile->getPathname(),
                    ];
                }
            }
        }

        return $vhostFiles;
    }

    /**
     * Perform dry run showing what would be imported
     */
    protected function performDryRun(array $vhostFiles): int
    {
        $this->line('');
        $this->line('<fg=yellow>🔍 DRY RUN MODE - No changes will be made</fg=yellow>');
        $this->line('');

        $progress = progress(label: 'Analyzing VHost files', steps: count($vhostFiles));

        foreach ($vhostFiles as $vhostData) {
            $progress->advance();

            $config = $this->parseVHostFile($vhostData['path']);
            if (! $config) {
                $this->errorCount++;

                continue;
            }

            $exists = $this->vhostExistsInDatabase($vhostData['vnode'], $vhostData['vhost']);

            $status = $exists ? '<fg=yellow>EXISTS</fg=yellow>' : '<fg=green>NEW</fg=green>';
            $this->line("  {$status} {$vhostData['vsite']}/{$vhostData['vnode']}/{$vhostData['vhost']}");

            if ($exists) {
                $this->skippedCount++;
            } else {
                $this->importedCount++;
            }
        }

        $progress->finish();

        $this->displaySummary(true);

        return 0;
    }

    /**
     * Perform actual import
     */
    protected function performImport(array $vhostFiles): int
    {
        $this->line('');
        $this->line('📥 Starting import process...');
        $this->line('');

        $progress = progress(label: 'Importing VHost configurations', steps: count($vhostFiles));

        foreach ($vhostFiles as $vhostData) {
            $progress->advance();

            try {
                $this->importVHost($vhostData);
            } catch (Exception $e) {
                $this->errors[] = "Error importing {$vhostData['vsite']}/{$vhostData['vnode']}/{$vhostData['vhost']}: {$e->getMessage()}";
                $this->errorCount++;
            }
        }

        $progress->finish();

        $this->displaySummary(false);
        $this->displayErrors();

        return $this->errorCount > 0 ? 1 : 0;
    }

    /**
     * Import a single VHost file
     */
    protected function importVHost(array $vhostData): void
    {
        $config = $this->parseVHostFile($vhostData['path']);
        if (! $config) {
            throw new Exception("Failed to parse VHost file: {$vhostData['path']}");
        }

        // Check if VHost already exists
        $existingVHost = $this->findExistingVHost($vhostData['vnode'], $vhostData['vhost']);

        if ($existingVHost && ! $this->option('force')) {
            $this->skippedCount++;

            return;
        }

        // Ensure VSite exists
        $vsite = $this->findOrCreateVSite($vhostData['vsite']);

        // Ensure VNode exists
        $vnode = $this->findOrCreateVNode($vhostData['vnode'], $vsite);

        // Create or update VHost
        if ($existingVHost) {
            $existingVHost->update([
                'environment_vars' => $config,
                'status' => 'active',
                'is_active' => true,
                'description' => 'Updated from var file import',
            ]);
        } else {
            FleetVhost::create([
                'domain' => $vhostData['vhost'],
                'vnode_id' => $vnode->id,
                'status' => 'active',
                'is_active' => true,
                'description' => 'Imported from var file',
                'environment_vars' => $config,
            ]);
        }

        $this->importedCount++;

        Log::info('VHost imported successfully', [
            'vsite' => $vhostData['vsite'],
            'vnode' => $vhostData['vnode'],
            'vhost' => $vhostData['vhost'],
            'variables_count' => count($config),
        ]);
    }

    /**
     * Parse VHost file and extract environment variables
     */
    protected function parseVHostFile(string $filePath): ?array
    {
        try {
            if (! File::exists($filePath) || ! File::isReadable($filePath)) {
                return null;
            }

            $content = File::get($filePath);
            $config = [];

            // Parse shell variable assignments
            foreach (explode("\n", $content) as $line) {
                $line = trim($line);

                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Parse variable assignments (VAR=value or VAR="value")
                if (preg_match('/^([A-Z_][A-Z0-9_]*)=(.*)$/', $line, $matches)) {
                    $varName = $matches[1];
                    $varValue = $matches[2];

                    // Remove quotes if present
                    $varValue = trim($varValue, '"\'');

                    $config[$varName] = $varValue;
                }
            }

            // Validate we have essential variables
            $required = ['VHOST', 'UUSER', 'WUGID'];
            foreach ($required as $var) {
                if (empty($config[$var])) {
                    Log::warning("Missing required variable {$var} in {$filePath}");

                    return null;
                }
            }

            // Ensure all expected variables are present (fill with empty string if missing)
            $normalized = [];
            foreach ($this->expectedVariables as $var) {
                $normalized[$var] = $config[$var] ?? '';
            }

            return $normalized;

        } catch (Exception $e) {
            Log::error("Failed to parse VHost file: {$filePath}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if VHost exists in database
     */
    protected function vhostExistsInDatabase(string $vnodeName, string $vhostDomain): bool
    {
        return FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $vnodeName))
            ->where('domain', $vhostDomain)
            ->exists();
    }

    /**
     * Find existing VHost in database
     */
    protected function findExistingVHost(string $vnodeName, string $vhostDomain): ?FleetVhost
    {
        return FleetVhost::whereHas('vnode', fn ($q) => $q->where('name', $vnodeName))
            ->where('domain', $vhostDomain)
            ->first();
    }

    /**
     * Find or create VSite
     */
    protected function findOrCreateVSite(string $vsiteName): FleetVsite
    {
        return FleetVsite::firstOrCreate(
            ['name' => $vsiteName],
            [
                'provider' => 'local', // Default to local for imports
                'technology' => 'lxc', // Default technology
                'description' => 'Auto-created during VHost import',
                'is_active' => true,
            ]
        );
    }

    /**
     * Find or create VNode
     */
    protected function findOrCreateVNode(string $vnodeName, FleetVsite $vsite): FleetVnode
    {
        return FleetVnode::firstOrCreate(
            [
                'name' => $vnodeName,
                'vsite_id' => $vsite->id,
            ],
            [
                'description' => 'Auto-created during VHost import',
                'is_active' => true,
                'ip_address' => '192.168.1.100', // Default, should be updated
                'ssh_host_id' => null, // Will be configured separately
            ]
        );
    }

    /**
     * Display import summary
     */
    protected function displaySummary(bool $isDryRun): void
    {
        $this->line('');
        $this->line('📊 Import Summary:');
        $this->line('');

        $mode = $isDryRun ? 'DRY RUN' : 'ACTUAL IMPORT';
        $this->line("  Mode: <fg=cyan>{$mode}</fg=cyan>");

        if ($this->importedCount > 0) {
            $label = $isDryRun ? 'Would import' : 'Imported';
            $this->line("  <fg=green>✓</fg=green> {$label}: <fg=green>{$this->importedCount}</fg=green>");
        }

        if ($this->skippedCount > 0) {
            $label = $isDryRun ? 'Would skip (exists)' : 'Skipped (exists)';
            $this->line("  <fg=yellow>⚠</fg=yellow> {$label}: <fg=yellow>{$this->skippedCount}</fg=yellow>");
        }

        if ($this->errorCount > 0) {
            $this->line("  <fg=red>✗</fg=red> Errors: <fg=red>{$this->errorCount}</fg=red>");
        }

        $total = $this->importedCount + $this->skippedCount + $this->errorCount;
        $this->line("  📋 Total processed: {$total}");
        $this->line('');

        if (! $isDryRun && $this->importedCount > 0) {
            $this->info('✅ Database import completed successfully!');
            $this->line('');
            $this->line('💡 Next steps:');
            $this->line('  • Test the chperms command with smart resolution');
            $this->line('  • Verify VHost data in Filament web interface');
            $this->line('  • Consider backing up and removing var/ files');
        }
    }

    /**
     * Display errors if any occurred
     */
    protected function displayErrors(): void
    {
        if (empty($this->errors)) {
            return;
        }

        $this->line('');
        $this->line('<fg=red>❌ Errors encountered during import:</fg=red>');
        $this->line('');

        foreach ($this->errors as $error) {
            $this->line("  • {$error}");
        }
    }
}
