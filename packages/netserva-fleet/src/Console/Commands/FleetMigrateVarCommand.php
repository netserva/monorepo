<?php

namespace NetServa\Fleet\Console\Commands;

use Illuminate\Console\Command;

/**
 * Fleet Var Directory Migration Command
 *
 * Migrates var/ directory from 2-tier (vnode/vhost) to 3-tier (vsite/vnode/vhost) structure
 */
class FleetMigrateVarCommand extends Command
{
    protected $signature = 'fleet:migrate-var
                          {--dry-run : Show what would be migrated without making changes}
                          {--backup : Create backup of current structure}';

    protected $description = 'Migrate var/ directory from 2-tier to 3-tier structure (vsite/vnode/vhost)';

    protected array $vnodeToVsiteMap;

    protected bool $dryRun = false;

    protected bool $backup = false;

    protected string $varBase;

    protected string $backupDir;

    public function handle(): int
    {
        $this->dryRun = $this->option('dry-run');
        $this->backup = $this->option('backup');
        $this->vnodeToVsiteMap = config('fleet.vsites.vnode_to_vsite_mappings', []);

        $this->varBase = config('fleet.import.var_base_path', '~/.ns/var');
        $this->varBase = str_replace('~', env('HOME'), $this->varBase);

        $this->info('🔄 Starting NetServa Var Directory Migration');
        $this->info("📁 Source: {$this->varBase}");

        if ($this->dryRun) {
            $this->warn('📋 DRY RUN MODE - No changes will be made');
        }

        if (! is_dir($this->varBase)) {
            $this->error("Var directory not found: {$this->varBase}");

            return 1;
        }

        try {
            // Step 1: Analyze current structure
            $analysis = $this->analyzeCurrentStructure();

            // Step 2: Create backup if requested
            if ($this->backup && ! $this->dryRun) {
                $this->createBackup();
            }

            // Step 3: Create new structure
            $this->createNewStructure($analysis);

            // Step 4: Show results
            $this->showResults($analysis);

            return 0;
        } catch (\Exception $e) {
            $this->error("Migration failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Analyze current var/ directory structure
     */
    protected function analyzeCurrentStructure(): array
    {
        $this->info('🔍 Analyzing current var/ directory structure...');

        $analysis = [
            'vnodes' => [],
            'vsites' => [],
            'total_vhosts' => 0,
            'unmapped_vnodes' => [],
        ];

        $vnodeDirs = glob("{$this->varBase}/*", GLOB_ONLYDIR);

        foreach ($vnodeDirs as $vnodeDir) {
            $vnodeName = basename($vnodeDir);

            // Skip if not a vnode directory
            if (str_starts_with($vnodeName, '.')) {
                continue;
            }

            $vhostFiles = glob("{$vnodeDir}/*");
            $vhostCount = count(array_filter($vhostFiles, 'is_file'));

            // Determine vsite for this vnode
            $vsiteMapping = $this->determineVSiteForVNode($vnodeName);

            if ($vsiteMapping) {
                $vsiteName = $this->generateVSiteName($vsiteMapping);

                if (! isset($analysis['vsites'][$vsiteName])) {
                    $analysis['vsites'][$vsiteName] = [
                        'mapping' => $vsiteMapping,
                        'vnodes' => [],
                        'total_vhosts' => 0,
                    ];
                }

                $analysis['vsites'][$vsiteName]['vnodes'][] = $vnodeName;
                $analysis['vsites'][$vsiteName]['total_vhosts'] += $vhostCount;
            } else {
                $analysis['unmapped_vnodes'][] = $vnodeName;
            }

            $analysis['vnodes'][$vnodeName] = [
                'vsite' => $vsiteMapping ? $this->generateVSiteName($vsiteMapping) : 'unmapped',
                'vhost_count' => $vhostCount,
                'path' => $vnodeDir,
            ];

            $analysis['total_vhosts'] += $vhostCount;

            $this->line("  📂 {$vnodeName}: {$vhostCount} vhosts → ".
                      ($vsiteMapping ? $this->generateVSiteName($vsiteMapping) : 'unmapped'));
        }

        return $analysis;
    }

    /**
     * Create backup of current structure
     */
    protected function createBackup(): void
    {
        $timestamp = date('Y-m-d_H-i-s');
        $this->backupDir = "{$this->varBase}_backup_{$timestamp}";

        $this->info("💾 Creating backup: {$this->backupDir}");

        if (! mkdir($this->backupDir, 0755, true)) {
            throw new \Exception("Failed to create backup directory: {$this->backupDir}");
        }

        // Copy current structure to backup
        $command = "cp -r {$this->varBase}/* {$this->backupDir}/";
        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception('Failed to create backup');
        }

        $this->info('✅ Backup created successfully');
    }

    /**
     * Create new 3-tier structure
     */
    protected function createNewStructure(array $analysis): void
    {
        $this->info('🏗️ Creating new 3-tier directory structure...');

        foreach ($analysis['vsites'] as $vsiteName => $vsiteData) {
            $vsiteDir = "{$this->varBase}/{$vsiteName}";

            $this->line("  📦 Creating VSite: {$vsiteName}");

            if (! $this->dryRun) {
                if (! is_dir($vsiteDir) && ! mkdir($vsiteDir, 0755, true)) {
                    throw new \Exception("Failed to create vsite directory: {$vsiteDir}");
                }
            }

            foreach ($vsiteData['vnodes'] as $vnodeName) {
                $this->migrateVNode($vnodeName, $vsiteDir, $analysis['vnodes'][$vnodeName]);
            }
        }

        // Handle unmapped vnodes
        if (! empty($analysis['unmapped_vnodes'])) {
            $this->createUnknownVSite($analysis);
        }
    }

    /**
     * Migrate a specific vnode to its vsite directory
     */
    protected function migrateVNode(string $vnodeName, string $vsiteDir, array $vnodeData): void
    {
        $oldPath = $vnodeData['path'];
        $newPath = "{$vsiteDir}/{$vnodeName}";

        $this->line("    🖥️  Migrating VNode: {$vnodeName} ({$vnodeData['vhost_count']} vhosts)");

        if ($this->dryRun) {
            $this->line("      📁 Would move: {$oldPath} → {$newPath}");

            return;
        }

        // Move the vnode directory to the new location
        if (! rename($oldPath, $newPath)) {
            throw new \Exception("Failed to move vnode directory: {$oldPath} → {$newPath}");
        }

        $this->line("      ✅ Moved: {$vnodeName}");
    }

    /**
     * Create unknown vsite for unmapped vnodes
     */
    protected function createUnknownVSite(array $analysis): void
    {
        if (empty($analysis['unmapped_vnodes'])) {
            return;
        }

        $unknownVsiteDir = "{$this->varBase}/unknown-infrastructure";

        $this->line('  📦 Creating VSite for unmapped vnodes: unknown-infrastructure');

        if (! $this->dryRun) {
            if (! is_dir($unknownVsiteDir) && ! mkdir($unknownVsiteDir, 0755, true)) {
                throw new \Exception("Failed to create unknown vsite directory: {$unknownVsiteDir}");
            }
        }

        foreach ($analysis['unmapped_vnodes'] as $vnodeName) {
            $vnodeData = $analysis['vnodes'][$vnodeName];
            $this->migrateVNode($vnodeName, $unknownVsiteDir, $vnodeData);
        }
    }

    /**
     * Determine VSite for a vnode
     */
    protected function determineVSiteForVNode(string $vnodeName): ?array
    {
        // Direct mapping lookup
        if (isset($this->vnodeToVsiteMap[$vnodeName])) {
            return $this->vnodeToVsiteMap[$vnodeName];
        }

        // Pattern-based heuristics for unmapped vnodes
        if (str_starts_with($vnodeName, 'mgo')) {
            return ['provider' => 'local', 'technology' => 'incus', 'location' => 'workstation'];
        }

        if (in_array($vnodeName, ['nsorg', 'ns2'])) {
            return ['provider' => 'binarylane', 'technology' => 'vps', 'location' => 'sydney'];
        }

        if (str_contains($vnodeName, 'gc') || str_contains($vnodeName, 'goldcoast')) {
            return ['provider' => 'local', 'technology' => 'proxmox', 'location' => 'homelab'];
        }

        // If no mapping found, return null (will go to unknown-infrastructure)
        return null;
    }

    /**
     * Generate VSite name from mapping
     */
    protected function generateVSiteName(array $mapping): string
    {
        $parts = [$mapping['provider'], $mapping['technology']];

        if (! empty($mapping['location'])) {
            $parts[] = $mapping['location'];
        }

        return implode('-', $parts);
    }

    /**
     * Show migration results
     */
    protected function showResults(array $analysis): void
    {
        $this->newLine();
        $this->info('📊 Migration Results:');

        $this->table(
            ['Metric', 'Count'],
            [
                ['VSites Created', count($analysis['vsites']) + (empty($analysis['unmapped_vnodes']) ? 0 : 1)],
                ['VNodes Migrated', count($analysis['vnodes'])],
                ['VHosts Preserved', $analysis['total_vhosts']],
                ['Unmapped VNodes', count($analysis['unmapped_vnodes'])],
            ]
        );

        if (! empty($analysis['vsites'])) {
            $this->newLine();
            $this->info('📦 VSite Structure:');

            foreach ($analysis['vsites'] as $vsiteName => $vsiteData) {
                $this->line("  {$vsiteName}:");
                $this->line("    Provider: {$vsiteData['mapping']['provider']}");
                $this->line("    Technology: {$vsiteData['mapping']['technology']}");
                if (! empty($vsiteData['mapping']['location'])) {
                    $this->line("    Location: {$vsiteData['mapping']['location']}");
                }
                $this->line('    VNodes: '.implode(', ', $vsiteData['vnodes']));
                $this->line("    VHosts: {$vsiteData['total_vhosts']}");
                $this->newLine();
            }
        }

        if (! empty($analysis['unmapped_vnodes'])) {
            $this->newLine();
            $this->warn('⚠️  Unmapped VNodes (moved to unknown-infrastructure):');
            foreach ($analysis['unmapped_vnodes'] as $vnode) {
                $this->line("  • {$vnode}");
            }
        }

        if ($this->backup && ! $this->dryRun) {
            $this->newLine();
            $this->info("💾 Backup created at: {$this->backupDir}");
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('💡 This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('✅ Migration completed successfully!');
            $this->info('📁 New structure: var/vsite/vnode/vhost');
            $this->info('🔄 You can now run: php artisan fleet:import');
        }
    }
}
