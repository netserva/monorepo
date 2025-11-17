<?php

namespace NetServa\Fleet\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

/**
 * Change/Update Fleet Command (NetServa 3.0 CRUD: UPDATE)
 *
 * ⚠️  DEPRECATED: This command is for legacy var/ directory migration only.
 * NetServa 3.0 uses database-first architecture - no var/ directory needed.
 *
 * For normal operations, use:
 * - addfleet <vnode>  : Discover and register infrastructure via SSH
 * - addvenue/addvsite/addvnode : Create infrastructure manually
 */
class ChfleetCommand extends Command
{
    protected $signature = 'chfleet
                          {vnode? : Sync specific vnode only (optional)}
                          {--dry-run : Show what would be synced without making changes}
                          {--force : Overwrite existing data}';

    protected $description = '[DEPRECATED] Legacy var/ directory migration tool (use addfleet instead)';

    protected array $vnodeToVsiteMap;

    protected bool $dryRun = false;

    protected bool $force = false;

    public function handle(): int
    {
        // Display deprecation warning
        $this->warn('⚠️  DEPRECATED: This command is for legacy var/ directory migration only.');
        $this->warn('NetServa 3.0 uses database-first architecture - no ~/.ns/var directory needed.');
        $this->newLine();
        $this->line('For normal operations, use:');
        $this->line('  • addfleet <vnode>     : Discover infrastructure via SSH');
        $this->line('  • addvenue/addvsite/addvnode : Create infrastructure manually');
        $this->newLine();

        $this->dryRun = $this->option('dry-run');
        $this->force = $this->option('force');
        $this->vnodeToVsiteMap = config('fleet.vsites.vnode_to_vsite_mappings', []);
        $specificVnode = $this->argument('vnode');

        $this->info('🔄 Starting Legacy Fleet Sync from var/ directory');

        if ($this->dryRun) {
            $this->warn('📋 DRY RUN MODE - No changes will be made');
        }

        // Sync infrastructure hierarchy
        $stats = [
            'vsites' => 0,
            'vnodes' => 0,
            'vhosts' => 0,
            'errors' => [],
        ];

        try {
            $this->importFromVarDirectory($stats);
            $this->linkSshHosts($stats);

            $this->displayResults($stats);

            return 0;
        } catch (\Exception $e) {
            $this->error("Sync failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Import from var/ directory structure (3-tier: vsite/vnode/vhost)
     */
    protected function importFromVarDirectory(array &$stats): void
    {
        $varBase = config('fleet.import.var_base_path', '~/.ns/var');
        $varBase = str_replace('~', env('HOME'), $varBase);

        if (! is_dir($varBase)) {
            $this->error("Var directory not found: {$varBase}");
            $this->newLine();
            $this->warn('NetServa 3.0 does NOT use the var/ directory structure.');
            $this->line('This command is only for migrating from NetServa 2.x legacy installations.');
            $this->newLine();
            $this->line('To set up your fleet in NetServa 3.0:');
            $this->line('  1. Create SSH hosts:    addssh <host> <hostname>');
            $this->line('  2. Discover infrastructure: addfleet <vnode>');
            $this->line('  3. Or create manually:  addvenue → addvsite → addvnode');
            throw new \Exception("Legacy var/ directory not found: {$varBase}");
        }

        $this->info("📁 Scanning legacy var directory: {$varBase}");

        $vsiteDirs = glob("{$varBase}/*", GLOB_ONLYDIR);
        $specificVnode = $this->argument('vnode');

        foreach ($vsiteDirs as $vsiteDir) {
            $vsiteName = basename($vsiteDir);

            // Skip .claude and other hidden directories
            if (str_starts_with($vsiteName, '.')) {
                continue;
            }

            $this->line("  🏢 Processing VSite: {$vsiteName}");

            // Get VSite data from the directory name
            $vsiteData = $this->parseVSiteFromName($vsiteName);

            // Create or get VSite
            $vsite = $this->createOrGetVSite($vsiteData, $stats);

            // Process VNodes within this VSite
            $vnodeDirs = glob("{$vsiteDir}/*", GLOB_ONLYDIR);

            foreach ($vnodeDirs as $vnodeDir) {
                $vnodeName = basename($vnodeDir);

                // Skip if specific vnode requested and this isn't it
                if ($specificVnode && $vnodeName !== $specificVnode) {
                    continue;
                }

                $this->line("    🔍 Processing vnode: {$vnodeName}");

                try {
                    $this->importVNodeWithVSite($vnodeName, $vnodeDir, $vsite, $stats);
                } catch (\Exception $e) {
                    $error = "Failed to import vnode {$vnodeName}: {$e->getMessage()}";
                    $stats['errors'][] = $error;
                    $this->warn("      ⚠️  {$error}");
                }
            }
        }
    }

    /**
     * Import a specific VNode and its VHosts
     */
    protected function importVNode(string $vnodeName, string $vnodeDir, array &$stats): void
    {
        // Determine VSite from vnode name or directory structure
        $vsiteData = $this->guessVSite($vnodeName);

        if (! $vsiteData) {
            throw new \Exception("Could not determine VSite for vnode: {$vnodeName}");
        }

        // Create or get VSite
        $vsite = $this->createOrGetVSite($vsiteData, $stats);

        // Create or get VNode
        $vnode = $this->createOrGetVNode($vnodeName, $vsite, $stats);

        // Import VHosts from files in vnode directory
        $this->importVHosts($vnode, $vnodeDir, $stats);
    }

    /**
     * Parse VSite data from directory name
     */
    protected function parseVSiteFromName(string $vsiteName): array
    {
        // Parse vsite name format: provider-technology-location
        $parts = explode('-', $vsiteName);

        if (count($parts) >= 2) {
            $provider = $parts[0];
            $technology = $parts[1];
            $location = count($parts) > 2 ? implode('-', array_slice($parts, 2)) : null;
        } else {
            // Fallback for unknown format
            $provider = 'customer';
            $technology = 'hardware';
            $location = null;
        }

        return [
            'name' => $vsiteName,
            'provider' => $provider,
            'technology' => $technology,
            'location' => $location,
        ];
    }

    /**
     * Import a specific VNode with existing VSite and its VHosts
     */
    protected function importVNodeWithVSite(string $vnodeName, string $vnodeDir, FleetVsite $vsite, array &$stats): void
    {
        // Create or get VNode
        $vnode = $this->createOrGetVNode($vnodeName, $vsite, $stats);

        // Import VHosts from files in vnode directory
        $this->importVHosts($vnode, $vnodeDir, $stats);
    }

    /**
     * Guess VSite from vnode name using explicit mappings (legacy method)
     */
    protected function guessVSite(string $vnodeName): ?array
    {
        // Direct mapping lookup
        if (isset($this->vnodeToVsiteMap[$vnodeName])) {
            return array_merge($this->vnodeToVsiteMap[$vnodeName], [
                'name' => $this->generateVSiteName($this->vnodeToVsiteMap[$vnodeName]),
            ]);
        }

        // Fallback heuristics
        if (str_starts_with($vnodeName, 'mgo')) {
            return [
                'name' => 'local-incus',
                'provider' => 'local',
                'technology' => 'incus',
                'location' => 'workstation',
            ];
        }

        if (in_array($vnodeName, ['nsorg', 'ns2'])) {
            return [
                'name' => 'binarylane-sydney',
                'provider' => 'binarylane',
                'technology' => 'vps',
                'location' => 'sydney',
            ];
        }

        // Default fallback
        return [
            'name' => 'unknown-infrastructure',
            'provider' => 'customer',
            'technology' => 'hardware',
            'location' => null,
        ];
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
     * Create or get VSite
     */
    protected function createOrGetVSite(array $vsiteData, array &$stats): FleetVsite
    {
        $name = $vsiteData['name'];

        if ($this->dryRun) {
            $this->line("    📦 Would create/update VSite: {$name}");
            // Return mock object for dry run
            $vsite = new FleetVsite($vsiteData);
            $vsite->id = 999;

            return $vsite;
        }

        $vsite = FleetVsite::where('name', $name)->first();

        if ($vsite) {
            if ($this->force) {
                $vsite->update($vsiteData);
                $this->line("    📦 Updated VSite: {$name}");
            } else {
                $this->line("    📦 Using existing VSite: {$name}");
            }
        } else {
            $capabilities = FleetVsite::getDefaultCapabilities($vsiteData['technology']);
            $vsiteData['capabilities'] = $capabilities;

            $vsite = FleetVsite::create($vsiteData);
            $stats['vsites']++;
            $this->line("    📦 Created VSite: {$name}");
        }

        return $vsite;
    }

    /**
     * Create or get VNode
     */
    protected function createOrGetVNode(string $vnodeName, FleetVsite $vsite, array &$stats): FleetVnode
    {
        if ($this->dryRun) {
            $this->line("      🖥️  Would create/update VNode: {$vnodeName}");
            // Return mock object for dry run
            $vnode = new FleetVnode(['name' => $vnodeName, 'vsite_id' => $vsite->id]);
            $vnode->id = 999;
            $vnode->vsite = $vsite;

            return $vnode;
        }

        $vnode = FleetVnode::where('name', $vnodeName)->first();

        $vnodeData = [
            'name' => $vnodeName,
            'vsite_id' => $vsite->id,
            'role' => $this->guessVNodeRole($vnodeName),
            'environment' => $this->guessEnvironment($vnodeName),
        ];

        if ($vnode) {
            if ($this->force) {
                $vnode->update($vnodeData);
                $this->line("      🖥️  Updated VNode: {$vnodeName}");
            } else {
                $this->line("      🖥️  Using existing VNode: {$vnodeName}");
            }
        } else {
            $vnode = FleetVnode::create($vnodeData);
            $stats['vnodes']++;
            $this->line("      🖥️  Created VNode: {$vnodeName}");
        }

        return $vnode;
    }

    /**
     * Guess VNode role from name
     */
    protected function guessVNodeRole(string $vnodeName): string
    {
        if (str_contains($vnodeName, 'router') || str_contains($vnodeName, 'gateway')) {
            return 'network';
        }

        if (str_contains($vnodeName, 'storage') || str_contains($vnodeName, 'nas')) {
            return 'storage';
        }

        if (str_contains($vnodeName, 'proxy') || str_contains($vnodeName, 'haproxy')) {
            return 'mixed';
        }

        return 'compute';
    }

    /**
     * Guess environment from vnode name
     */
    protected function guessEnvironment(string $vnodeName): string
    {
        if (str_contains($vnodeName, 'dev') || str_contains($vnodeName, 'test')) {
            return 'development';
        }

        if (str_contains($vnodeName, 'staging') || str_contains($vnodeName, 'stage')) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Import VHosts from vnode directory
     */
    protected function importVHosts(FleetVnode $vnode, string $vnodeDir, array &$stats): void
    {
        $vhostFiles = glob("{$vnodeDir}/*");
        $excludePatterns = config('fleet.import.exclude_patterns', []);

        foreach ($vhostFiles as $vhostFile) {
            if (! is_file($vhostFile)) {
                continue;
            }

            $domain = basename($vhostFile);

            // Check exclude patterns
            $skip = false;
            foreach ($excludePatterns as $pattern) {
                if (fnmatch($pattern, $domain)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            try {
                $this->importVHost($vnode, $domain, $vhostFile, $stats);
            } catch (\Exception $e) {
                $error = "Failed to import vhost {$domain}: {$e->getMessage()}";
                $stats['errors'][] = $error;
                $this->warn("        ⚠️  {$error}");
            }
        }
    }

    /**
     * Import a specific VHost
     */
    protected function importVHost(FleetVnode $vnode, string $domain, string $vhostFile, array &$stats): void
    {
        if ($this->dryRun) {
            $this->line("        💻 Would import VHost: {$domain}");

            return;
        }

        $vhost = FleetVhost::where('vnode_id', $vnode->id)
            ->where('domain', $domain)
            ->first();

        $vhostData = [
            'domain' => $domain,
            'vnode_id' => $vnode->id,
            'var_file_path' => $vhostFile,
            'var_file_modified_at' => Carbon::createFromTimestamp(filemtime($vhostFile)),
            'instance_type' => $this->guessInstanceType($vnode->vsite->technology),
        ];

        if ($vhost) {
            if ($this->force || $vhost->isVarFileNewer()) {
                $vhost->update($vhostData);
                $vhost->loadEnvironmentVars();
                $this->line("        💻 Updated VHost: {$domain}");
            } else {
                $this->line("        💻 Skipped VHost (up to date): {$domain}");
            }
        } else {
            $vhost = FleetVhost::create($vhostData);
            $vhost->loadEnvironmentVars();
            $stats['vhosts']++;
            $this->line("        💻 Created VHost: {$domain}");
        }
    }

    /**
     * Guess instance type from technology
     */
    protected function guessInstanceType(string $technology): ?string
    {
        return match ($technology) {
            'incus' => 'ct',
            'proxmox' => 'vm',
            'docker' => 'docker',
            default => null,
        };
    }

    /**
     * Link SSH hosts to VNodes
     */
    protected function linkSshHosts(array &$stats): void
    {
        $this->info('🔗 Linking SSH hosts to VNodes');

        $sshHosts = SshHost::active()->get();
        $linkedCount = 0;

        foreach ($sshHosts as $sshHost) {
            $vnode = FleetVnode::where('name', $sshHost->host)->first();

            if ($vnode && ! $vnode->ssh_host_id) {
                if (! $this->dryRun) {
                    $vnode->ssh_host_id = $sshHost->id;
                    $vnode->save();
                }

                $linkedCount++;
                $this->line("  🔗 Linked SSH host {$sshHost->host} to VNode {$vnode->name}");
            }
        }

        if ($linkedCount === 0) {
            $this->line('  ℹ️  No new SSH host links created');
        }
    }

    /**
     * Display import results
     */
    protected function displayResults(array $stats): void
    {
        $this->newLine();
        $this->info('📊 Import Results:');
        $this->table(
            ['Type', 'Count'],
            [
                ['VSites', $stats['vsites']],
                ['VNodes', $stats['vnodes']],
                ['VHosts', $stats['vhosts']],
                ['Errors', count($stats['errors'])],
            ]
        );

        if (! empty($stats['errors'])) {
            $this->newLine();
            $this->error('❌ Errors encountered:');
            foreach ($stats['errors'] as $error) {
                $this->line("  • {$error}");
            }
        }

        if ($this->dryRun) {
            $this->newLine();
            $this->warn('💡 This was a dry run. Run without --dry-run to apply changes.');
        } else {
            $this->newLine();
            $this->info('✅ Import completed successfully!');
        }
    }
}
