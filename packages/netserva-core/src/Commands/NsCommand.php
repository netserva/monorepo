<?php

namespace NetServa\Core\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Core\Services\SshConfigService;
use NetServa\Core\Services\UserManagementService;
use NetServa\Core\Services\VhostConfigService;
use NetServa\Core\Services\VhostManagementService;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\progress;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * NetServa CLI Command
 *
 * Unified command-line interface for all NetServa operations.
 * Single entry point: `php artisan ns`
 */
class NsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'ns {action?} {--host=} {--shost=} {--vhost=} {--dry-run}';

    /**
     * The console command description.
     */
    protected $description = 'NetServa unified CLI with Laravel Prompts interface';

    protected VhostConfigService $vhostConfig;

    protected SshConfigService $sshConfig;

    protected RemoteExecutionService $remoteExecution;

    protected UserManagementService $userManagement;

    protected VhostManagementService $vhostManagement;

    public function __construct(
        VhostConfigService $vhostConfig,
        SshConfigService $sshConfig,
        RemoteExecutionService $remoteExecution,
        UserManagementService $userManagement,
        VhostManagementService $vhostManagement
    ) {
        parent::__construct();
        $this->vhostConfig = $vhostConfig;
        $this->sshConfig = $sshConfig;
        $this->remoteExecution = $remoteExecution;
        $this->userManagement = $userManagement;
        $this->vhostManagement = $vhostManagement;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        // Welcome banner
        $this->showBanner();

        if (! $action) {
            $action = $this->showMainMenu();
        }

        return match ($action) {
            'status' => $this->handleStatus(),
            'user' => $this->handleUser(),
            'vhost' => $this->handleVhost(),
            'ssh' => $this->handleSsh(),
            'mount' => $this->handleMount(),
            'config' => $this->handleConfig(),
            'test' => $this->handleTest(),
            'help' => $this->showHelp(),
            default => $this->handleUnknownAction($action)
        };
    }

    /**
     * Show banner and system info
     */
    protected function showBanner(): void
    {
        info('🚀 NetServa CLI - Infrastructure Management Interface');

        $nsdir = config('netserva-cli.paths.ns');
        $this->line("Working directory: {$nsdir}");

        if ($this->option('dry-run')) {
            warning('🔍 DRY RUN MODE - No changes will be made');
        }

        $this->line('');
    }

    /**
     * Show main menu
     */
    protected function showMainMenu(): string
    {
        return select(
            'What would you like to do?',
            [
                'status' => '📊 Show system status',
                'user' => '👤 User management',
                'vhost' => '🌐 Virtual host management',
                'ssh' => '🔐 SSH configuration',
                'mount' => '💾 Remote filesystem mounting',
                'config' => '⚙️  Configuration management',
                'test' => '🧪 Test connectivity',
                'help' => '❓ Show help',
            ],
            'status'
        );
    }

    /**
     * Handle status command
     */
    protected function handleStatus(): int
    {
        info('🔍 NetServa System Status');

        // Check local directories
        $this->checkLocalDirectories();

        // Check SSH hosts
        $this->checkSshHosts();

        // Check VHost configs
        $this->checkVhostConfigs();

        return 0;
    }

    /**
     * Handle user management
     */
    protected function handleUser(): int
    {
        $action = select(
            'User management action:',
            [
                'add' => 'Add user to remote server',
                'list' => 'List users on remote server',
                'remove' => 'Remove user from remote server',
                'test' => 'Test user access',
            ]
        );

        return match ($action) {
            'add' => $this->addUser(),
            'list' => $this->listUsers(),
            'remove' => $this->removeUser(),
            'test' => $this->testUser(),
            default => 1
        };
    }

    /**
     * Handle virtual host management
     */
    protected function handleVhost(): int
    {
        $action = select(
            'VHost management action:',
            [
                'list' => 'List virtual hosts',
                'show' => 'Show VHost configuration',
                'edit' => 'Edit VHost configuration',
                'create' => 'Create new VHost',
                'delete' => 'Delete VHost',
                'sync' => 'Sync VHost to remote server',
            ]
        );

        return match ($action) {
            'list' => $this->listVhosts(),
            'show' => $this->showVhost(),
            'edit' => $this->editVhost(),
            'create' => $this->createVhost(),
            'delete' => $this->deleteVhost(),
            'sync' => $this->syncVhost(),
            default => 1
        };
    }

    /**
     * Handle SSH configuration
     */
    protected function handleSsh(): int
    {
        $action = select(
            'SSH configuration action:',
            [
                'list' => 'List SSH hosts',
                'test' => 'Test SSH connections',
                'sync' => 'Regenerate runtime config',
                'keys' => 'Manage SSH keys',
                'cleanup' => 'Clean up stale connections',
            ]
        );

        return match ($action) {
            'list' => $this->listSshHosts(),
            'test' => $this->testSshConnections(),
            'sync' => $this->syncSshConfig(),
            'keys' => $this->manageSshKeys(),
            'cleanup' => $this->cleanupSshConnections(),
            default => 1
        };
    }

    /**
     * Handle mount operations
     */
    protected function handleMount(): int
    {
        $action = select(
            'Mount operation:',
            [
                'list' => 'List mounted systems',
                'mount' => 'Mount remote system',
                'unmount' => 'Unmount remote system',
                'remount' => 'Remount remote system',
            ]
        );

        return match ($action) {
            'list' => $this->listMounts(),
            'mount' => $this->mountRemote(),
            'unmount' => $this->unmountRemote(),
            'remount' => $this->remountRemote(),
            default => 1
        };
    }

    /**
     * Handle configuration management
     */
    protected function handleConfig(): int
    {
        $action = select(
            'Configuration management:',
            [
                'validate' => 'Validate configurations',
                'backup' => 'Backup configurations',
                'restore' => 'Restore from backup',
                'export' => 'Export configurations',
                'import' => 'Import configurations',
            ]
        );

        return match ($action) {
            'validate' => $this->validateConfigs(),
            'backup' => $this->backupConfigs(),
            'restore' => $this->restoreConfigs(),
            'export' => $this->exportConfigs(),
            'import' => $this->importConfigs(),
            default => 1
        };
    }

    /**
     * Handle test operations
     */
    protected function handleTest(): int
    {
        $action = select(
            'Test operation:',
            [
                'ssh' => 'Test SSH connectivity',
                'root' => 'Test root access',
                'env' => 'Test environment setup',
                'all' => 'Run all tests',
            ]
        );

        return match ($action) {
            'ssh' => $this->testSsh(),
            'root' => $this->testRoot(),
            'env' => $this->testEnvironment(),
            'all' => $this->testAll(),
            default => 1
        };
    }

    /**
     * Add user to remote server
     */
    protected function addUser(): int
    {
        info('👤 Adding user to remote server');

        // Get server and vhost
        $shost = $this->option('shost') ?: $this->selectServer();
        $vhost = $this->option('vhost') ?: $this->selectVhost($shost);
        $username = $this->option('user') ?: text('Username to add:', validate: fn ($value) => ! empty($value) ? null : 'Username is required');

        if (! $shost || ! $vhost || ! $username) {
            error('❌ Missing required parameters');

            return 1;
        }

        // Get target host for SSH
        $host = $this->option('host') ?: $shost;

        if ($this->option('dry-run')) {
            info("🔍 DRY RUN: Would add user '{$username}' to {$host} with VHost {$shost}/{$vhost}");

            return 0;
        }

        $success = spin(
            fn () => $this->userManagement->addUser($host, $shost, $vhost, $username),
            "Adding user '{$username}' to {$host}..."
        );

        if ($success) {
            info("✅ User '{$username}' added successfully");

            return 0;
        } else {
            error("❌ Failed to add user '{$username}'");

            return 1;
        }
    }

    /**
     * Show help information
     */
    protected function showHelp(): int
    {
        info('📚 NetServa CLI Help');

        $this->line('');
        $this->line('Usage:');
        $this->line('  php artisan ns [action] [options]');
        $this->line('');
        $this->line('Actions:');
        $this->line('  status    Show system status');
        $this->line('  user      User management operations');
        $this->line('  vhost     Virtual host management');
        $this->line('  ssh       SSH configuration management');
        $this->line('  mount     Remote filesystem operations');
        $this->line('  config    Configuration management');
        $this->line('  test      Test connectivity and functionality');
        $this->line('');
        $this->line('Options:');
        $this->line('  --host=HOST     Target SSH host');
        $this->line('  --shost=VNODE   Server hostname');
        $this->line('  --vhost=VHOST   Virtual host name');
        $this->line('  --dry-run       Show what would be done without making changes');
        $this->line('');
        $this->line('Examples:');
        $this->line('  php artisan ns vhost --shost=motd --vhost=test.example.com');
        $this->line('  php artisan ns status');
        $this->line('  php artisan ns ssh --dry-run');

        return 0;
    }

    // Protected helper methods for various operations

    protected function checkLocalDirectories(): void
    {
        $directories = [
            'NSDIR' => config('netserva-cli.paths.ns'),
            'NSVAR' => config('netserva-cli.paths.ns').'/var',
            'NSSSH' => config('netserva-cli.paths.ns').'/ssh',
            'NSRUN' => config('netserva-cli.paths.ns').'/run',
        ];

        $headers = ['Directory', 'Path', 'Status'];
        $rows = [];

        foreach ($directories as $name => $path) {
            $status = is_dir($path) ? '✅ Exists' : '❌ Missing';
            $rows[] = [$name, $path, $status];
        }

        table($headers, $rows);
    }

    protected function checkSshHosts(): void
    {
        $hosts = $this->sshConfig->listHosts();

        if (empty($hosts)) {
            warning('No SSH hosts configured');

            return;
        }

        info('📡 SSH Hosts ({'.count($hosts).'}):');
        foreach ($hosts as $host) {
            $this->line("  • {$host}");
        }
    }

    protected function checkVhostConfigs(): void
    {
        $configs = $this->vhostConfig->list();

        if (empty($configs)) {
            warning('No VHost configurations found');

            return;
        }

        info('🌐 VHost Configurations ('.count($configs).'):');
        foreach ($configs as $config) {
            $this->line("  • {$config}");
        }
    }

    protected function selectServer(): string
    {
        $servers = $this->vhostConfig->getAllServers();

        if (empty($servers)) {
            error('No servers found');

            return '';
        }

        $serverNames = array_map('basename', $servers);

        return select('Select server:', array_combine($serverNames, $serverNames));
    }

    protected function selectVhost(string $shost): string
    {
        $vhosts = $this->vhostConfig->getVhostsForServer($shost);

        if (empty($vhosts)) {
            error("No VHosts found for server: {$shost}");

            return '';
        }

        return select('Select VHost:', array_combine($vhosts, $vhosts));
    }

    protected function handleUnknownAction(string $action): int
    {
        error("❌ Unknown action: {$action}");
        $this->line('Run `php artisan ns help` for available actions.');

        return 1;
    }

    // Placeholder methods for other operations (to be implemented)

    protected function listUsers(): int
    {
        warning('🚧 List users functionality not yet implemented');

        return 0;
    }

    protected function removeUser(): int
    {
        warning('🚧 Remove user functionality not yet implemented');

        return 0;
    }

    protected function testUser(): int
    {
        warning('🚧 Test user functionality not yet implemented');

        return 0;
    }

    protected function listVhosts(): int
    {
        info('🌐 VHost Configurations');
        $configs = $this->vhostConfig->list();

        if (empty($configs)) {
            warning('No VHost configurations found');

            return 0;
        }

        foreach ($configs as $config) {
            $this->line("  • {$config}");
        }

        return 0;
    }

    protected function showVhost(): int
    {
        warning('🚧 Show VHost functionality not yet implemented');

        return 0;
    }

    protected function editVhost(): int
    {
        warning('🚧 Edit VHost functionality not yet implemented');

        return 0;
    }

    protected function createVhost(): int
    {
        info('🌐 Creating new VHost');

        // Get input parameters
        $shost = $this->option('shost') ?: $this->selectServer();
        $vhost = $this->option('vhost') ?: text(
            'Domain name:',
            validate: fn ($value) => filter_var($value, FILTER_VALIDATE_DOMAIN) ? null : 'Please enter a valid domain name'
        );

        if (! $shost || ! $vhost) {
            error('❌ Server hostname and domain are required');

            return 1;
        }

        // Get target host for SSH
        $host = $this->option('host') ?: $shost;

        // Check if VHost already exists
        if ($this->vhostConfig->exists($shost, $vhost)) {
            error("❌ VHost {$vhost} already exists on server {$shost}");

            return 1;
        }

        // Show summary and confirm
        $this->line('');
        info('📋 VHost Creation Summary:');
        $this->line("  Server:   {$shost}");
        $this->line("  Domain:   {$vhost}");
        $this->line('  Username: auto-generated (u100X)');
        $this->line("  SSH Host: {$host}");
        $this->line('');

        if (! $this->option('dry-run') && ! confirm('Create this VHost?', true)) {
            warning('❌ VHost creation cancelled');

            return 0;
        }

        if ($this->option('dry-run')) {
            info("🔍 DRY RUN: Would create VHost '{$vhost}' on {$host} (server: {$shost})");

            return 0;
        }

        // Create VHost with progress indicator
        $result = spin(
            fn () => $this->vhostManagement->createVhost($host, $vhost),
            "Creating VHost '{$vhost}' on {$host}..."
        );

        if ($result['success']) {
            info("✅ VHost '{$vhost}' created successfully");

            // Show next steps
            $this->line('');
            info('🎯 Next Steps:');
            $this->line("  • Add DNS record for {$vhost}");
            $this->line("  • Configure SSL: newssl {$vhost}");
            $this->line("  • Add email: addvmail user@{$vhost}");
            $this->line("  • Install WordPress: addwp {$vhost}");

            return 0;
        } else {
            error("❌ Failed to create VHost '{$vhost}'");
            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $error) {
                    $this->line("  • {$error}");
                }
            }

            return 1;
        }
    }

    protected function deleteVhost(): int
    {
        warning('🚧 Delete VHost functionality not yet implemented');

        return 0;
    }

    protected function syncVhost(): int
    {
        warning('🚧 Sync VHost functionality not yet implemented');

        return 0;
    }

    protected function listSshHosts(): int
    {
        info('🔐 SSH Hosts');
        $hosts = $this->sshConfig->listHosts();

        if (empty($hosts)) {
            warning('No SSH hosts configured');

            return 0;
        }

        foreach ($hosts as $host) {
            $this->line("  • {$host}");
        }

        return 0;
    }

    protected function testSshConnections(): int
    {
        warning('🚧 Test SSH connections functionality not yet implemented');

        return 0;
    }

    protected function syncSshConfig(): int
    {
        info('🔧 Regenerating SSH runtime configuration...');
        $success = $this->sshConfig->regenerateRuntimeConfig();

        if ($success) {
            info('✅ SSH runtime configuration regenerated');

            return 0;
        } else {
            error('❌ Failed to regenerate SSH configuration');

            return 1;
        }
    }

    protected function manageSshKeys(): int
    {
        warning('🚧 Manage SSH keys functionality not yet implemented');

        return 0;
    }

    protected function cleanupSshConnections(): int
    {
        info('🧹 Cleaning up stale SSH connections...');
        $cleaned = $this->sshConfig->cleanupMuxSockets();
        info("✅ Cleaned up {$cleaned} stale connections");

        return 0;
    }

    protected function listMounts(): int
    {
        warning('🚧 List mounts functionality not yet implemented');

        return 0;
    }

    protected function mountRemote(): int
    {
        warning('🚧 Mount remote functionality not yet implemented');

        return 0;
    }

    protected function unmountRemote(): int
    {
        warning('🚧 Unmount remote functionality not yet implemented');

        return 0;
    }

    protected function remountRemote(): int
    {
        warning('🚧 Remount remote functionality not yet implemented');

        return 0;
    }

    protected function validateConfigs(): int
    {
        warning('🚧 Validate configs functionality not yet implemented');

        return 0;
    }

    protected function backupConfigs(): int
    {
        warning('🚧 Backup configs functionality not yet implemented');

        return 0;
    }

    protected function restoreConfigs(): int
    {
        warning('🚧 Restore configs functionality not yet implemented');

        return 0;
    }

    protected function exportConfigs(): int
    {
        warning('🚧 Export configs functionality not yet implemented');

        return 0;
    }

    protected function importConfigs(): int
    {
        warning('🚧 Import configs functionality not yet implemented');

        return 0;
    }

    protected function testSsh(): int
    {
        warning('🚧 Test SSH functionality not yet implemented');

        return 0;
    }

    protected function testRoot(): int
    {
        warning('🚧 Test root functionality not yet implemented');

        return 0;
    }

    protected function testEnvironment(): int
    {
        warning('🚧 Test environment functionality not yet implemented');

        return 0;
    }

    protected function testAll(): int
    {
        warning('🚧 Test all functionality not yet implemented');

        return 0;
    }
}
