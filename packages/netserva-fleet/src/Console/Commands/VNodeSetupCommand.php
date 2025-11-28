<?php

namespace NetServa\Fleet\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVnode;

/**
 * VNode Setup Command
 *
 * Initializes a remote VNode with required NetServa infrastructure:
 * - Creates SQLite database structure
 * - Installs required packages (sqlite3, etc.)
 * - Sets up directory structure
 * - Configures basic services
 */
class VNodeSetupCommand extends Command
{
    protected $signature = 'vnode:setup
                          {vnode : VNode name to initialize}
                          {--force : Force setup even if already initialized}
                          {--skip-packages : Skip package installation}
                          {--skip-database : Skip database initialization}';

    protected $description = 'Initialize a VNode with required NetServa infrastructure';

    protected RemoteExecutionService $remoteExecution;

    public function __construct(RemoteExecutionService $remoteExecution)
    {
        parent::__construct();
        $this->remoteExecution = $remoteExecution;
    }

    public function handle(): int
    {
        $vnodeName = $this->argument('vnode');
        $force = $this->option('force');

        $this->info("🔧 Initializing VNode: {$vnodeName}");

        // Find VNode
        $vnode = FleetVnode::where('name', $vnodeName)->with('sshHost')->first();

        if (! $vnode) {
            $this->error("VNode not found: {$vnodeName}");
            $this->line("Run 'php artisan addfleet {$vnodeName}' first");

            return 1;
        }

        if (! $vnode->hasSshAccess()) {
            $this->error("No SSH access configured for VNode: {$vnodeName}");

            return 1;
        }

        try {
            // Check if already initialized
            if (! $force && $this->isAlreadyInitialized($vnode)) {
                $this->warn("VNode {$vnodeName} appears to be already initialized.");
                $this->line('Use --force to reinitialize');

                return 0;
            }

            // Step 1: Install required packages
            if (! $this->option('skip-packages')) {
                $this->newLine();
                $this->line('📦 Installing required packages...');
                $this->installRequiredPackages($vnode);
            }

            // Step 2: Create directory structure
            $this->newLine();
            $this->line('📁 Creating directory structure...');
            $this->createDirectoryStructure($vnode);

            // Step 3: Initialize SQLite database
            if (! $this->option('skip-database')) {
                $this->newLine();
                $this->line('🗄️  Initializing SQLite database...');
                $this->initializeSqliteDatabase($vnode);
            }

            // Step 4: Set permissions
            $this->newLine();
            $this->line('🔒 Setting permissions...');
            $this->setPermissions($vnode);

            $this->newLine();
            $this->info("✅ VNode {$vnodeName} initialized successfully!");

            return 0;

        } catch (Exception $e) {
            $this->error("Setup failed: {$e->getMessage()}");

            return 1;
        }
    }

    /**
     * Check if VNode is already initialized
     */
    protected function isAlreadyInitialized(FleetVnode $vnode): bool
    {
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            'test -f /var/lib/sqlite/sysadm/sysadm.db && echo "initialized"'
        );

        return $result['success'] && trim($result['output']) === 'initialized';
    }

    /**
     * Install required packages
     */
    protected function installRequiredPackages(FleetVnode $vnode): void
    {
        $this->line('  → Detecting package manager...');

        // Detect package manager
        $result = $this->remoteExecution->executeAsRoot(
            $vnode->name,
            'command -v apt-get && echo "apt" || (command -v apk && echo "apk" || echo "unknown")'
        );

        $packageManager = trim($result['output']);

        if ($packageManager === 'unknown') {
            $this->warn('  ⚠ Unknown package manager, skipping package installation');

            return;
        }

        $this->line("  → Using package manager: {$packageManager}");

        // Install packages based on package manager
        $installCmd = match ($packageManager) {
            'apt' => 'apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y sqlite3',
            'apk' => 'apk add --no-cache sqlite',
            default => null,
        };

        if ($installCmd) {
            $this->line('  → Installing sqlite3...');
            $result = $this->remoteExecution->executeAsRoot($vnode->name, $installCmd);

            if ($result['success']) {
                $this->info('  ✅ Packages installed');
            } else {
                $this->warn('  ⚠ Package installation failed, continuing...');
            }
        }
    }

    /**
     * Create directory structure
     */
    protected function createDirectoryStructure(FleetVnode $vnode): void
    {
        $script = <<<'BASH'
        # Create NetServa directory structure
        mkdir -p /var/lib/sqlite/sysadm
        mkdir -p /srv
        mkdir -p /home/backups
        mkdir -p /etc/ssl/le

        echo "Directories created"
        BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            asRoot: true
        );

        if ($result['success']) {
            $this->info('  ✅ Directory structure created');
        } else {
            throw new Exception('Failed to create directory structure: '.$result['error']);
        }
    }

    /**
     * Initialize SQLite database with vhosts table
     */
    protected function initializeSqliteDatabase(FleetVnode $vnode): void
    {
        $script = "#!/bin/bash\n";
        $script .= "DBPATH=\"/var/lib/sqlite/sysadm/sysadm.db\"\n";
        $script .= "if [[ ! -f \"\$DBPATH\" ]]; then\n";
        $script .= "    sqlite3 \"\$DBPATH\" <<'SQLEOF'\n";
        $script .= "CREATE TABLE IF NOT EXISTS vhosts (\n";
        $script .= "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
        $script .= "    active INTEGER DEFAULT 1,\n";
        $script .= "    created DATETIME DEFAULT CURRENT_TIMESTAMP,\n";
        $script .= "    domain TEXT UNIQUE NOT NULL,\n";
        $script .= "    gid INTEGER NOT NULL,\n";
        $script .= "    uid INTEGER NOT NULL,\n";
        $script .= "    uname TEXT NOT NULL,\n";
        $script .= "    updated DATETIME DEFAULT CURRENT_TIMESTAMP\n";
        $script .= ");\n";
        $script .= "CREATE INDEX IF NOT EXISTS idx_vhosts_domain ON vhosts(domain);\n";
        $script .= "CREATE INDEX IF NOT EXISTS idx_vhosts_uid ON vhosts(uid);\n";
        $script .= "CREATE INDEX IF NOT EXISTS idx_vhosts_active ON vhosts(active);\n";
        $script .= "SELECT 'Database initialized successfully' as status;\n";
        $script .= "SQLEOF\n";
        $script .= "    echo \"✅ Database created and initialized\"\n";
        $script .= "else\n";
        $script .= "    echo \"ℹ Database already exists\"\n";
        $script .= "fi\n";

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            asRoot: true
        );

        if ($result['success']) {
            $this->info('  ✅ SQLite database initialized');
            $this->line('     '.trim($result['output']));
        } else {
            throw new Exception('Failed to initialize database: '.$result['error']);
        }
    }

    /**
     * Set permissions on created directories
     */
    protected function setPermissions(FleetVnode $vnode): void
    {
        $script = <<<'BASH'
        # Set ownership and permissions
        chown -R sysadm:sysadm /var/lib/sqlite
        chmod 755 /var/lib/sqlite
        chmod 755 /var/lib/sqlite/sysadm
        chmod 644 /var/lib/sqlite/sysadm/*.db 2>/dev/null || true

        chmod 755 /srv
        chmod 755 /home/backups
        chmod 755 /etc/ssl/le

        echo "Permissions set"
        BASH;

        $result = $this->remoteExecution->executeScript(
            host: $vnode->name,
            script: $script,
            asRoot: true
        );

        if ($result['success']) {
            $this->info('  ✅ Permissions configured');
        } else {
            $this->warn('  ⚠ Permission setting failed (non-critical)');
        }
    }
}
