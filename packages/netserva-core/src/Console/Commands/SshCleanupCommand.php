<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\warning;

/**
 * SSH Host Cleanup Command
 *
 * Validates SSH host configurations against VNode/VHost infrastructure
 * Enforces one-to-one mapping: every ~/.ssh/hosts/* must map to a VNode or VHost
 */
class SshCleanupCommand extends Command
{
    protected $signature = 'ssh:cleanup
                            {--test : Test connectivity without removing anything}
                            {--remove-orphaned : Remove SSH hosts with no VNode/VHost mapping}
                            {--remove-unreachable : Remove unreachable SSH hosts}
                            {--create-missing : Create VNodes/VHosts for unmapped SSH hosts}
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Validate SSH hosts and enforce VNode/VHost one-to-one mapping';

    protected array $filesystemHosts = [];

    protected array $unreachable = [];

    protected array $orphanedSshHosts = [];

    protected array $vnodesMissingSSH = [];

    protected array $vhostsMissingSSH = [];

    protected array $validMappings = [];

    public function handle(): int
    {
        info('🔍 NetServa SSH Host Cleanup & Validation');
        info('Enforcing one-to-one mapping: SSH hosts ↔ VNodes/VHosts');
        $this->newLine();

        // Get all SSH host files from filesystem
        $this->filesystemHosts = $this->getFilesystemHosts();
        info('Found '.count($this->filesystemHosts).' SSH host files in ~/.ssh/hosts/');

        // Validate all SSH hosts and classify them
        $this->validateAndClassifyHosts();

        // Check for VNodes/VHosts missing SSH configs
        $this->checkForMissingSSH();

        // Display results
        $this->displayResults();

        // Offer cleanup options
        if (! $this->option('test') && ! $this->option('dry-run')) {
            return $this->offerCleanupOptions();
        }

        return self::SUCCESS;
    }

    /**
     * Get all SSH host files from filesystem
     */
    protected function getFilesystemHosts(): array
    {
        $homeDir = env('HOME') ?: (function_exists('posix_getpwuid') ? posix_getpwuid(posix_getuid())['dir'] : '/home/'.get_current_user());
        $sshHostsDir = $homeDir.'/.ssh/hosts';

        if (! File::exists($sshHostsDir)) {
            warning("SSH hosts directory not found: {$sshHostsDir}");

            return [];
        }

        $hosts = [];
        $files = File::files($sshHostsDir);

        foreach ($files as $file) {
            $filename = $file->getFilename();

            // Skip config files and hidden files
            if (in_array($filename, ['config', '.', '..']) || str_starts_with($filename, '.')) {
                continue;
            }

            $hosts[$filename] = [
                'name' => $filename,
                'path' => $file->getPathname(),
                'config' => $this->parseSSHConfig($file->getPathname()),
            ];
        }

        return $hosts;
    }

    /**
     * Parse SSH config file
     */
    protected function parseSSHConfig(string $filePath): array
    {
        $content = File::get($filePath);
        $lines = explode("\n", $content);

        $config = [
            'hostname' => null,
            'port' => 22,
            'user' => 'root',
        ];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $parts = preg_split('/\s+/', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $directive = strtolower($parts[0]);
            $value = $parts[1];

            match ($directive) {
                'hostname' => $config['hostname'] = $value,
                'port' => $config['port'] = (int) $value,
                'user' => $config['user'] = $value,
                default => null,
            };
        }

        return $config;
    }

    /**
     * Validate and classify all SSH hosts
     */
    protected function validateAndClassifyHosts(): void
    {
        info('🔍 Validating and classifying SSH hosts...');
        $this->newLine();

        foreach ($this->filesystemHosts as $hostName => $hostData) {
            $config = $hostData['config'];

            // Check if config is valid
            if (! $config['hostname']) {
                warning("⚠️  Skipping {$hostName} - invalid config (no hostname)");

                continue;
            }

            // Test connectivity
            if (! $this->testConnectivity($config)) {
                $this->unreachable[] = [
                    'name' => $hostName,
                    'hostname' => $config['hostname'],
                    'user' => $config['user'],
                    'port' => $config['port'],
                ];

                continue;
            }

            // Determine if this is a VHost or VNode
            $classification = $this->classifyHost($hostName, $config);

            if ($classification['type'] === 'vhost') {
                // Check if VHost exists in database
                $vhost = FleetVHost::where('domain', $hostName)->first();
                if ($vhost) {
                    $this->validMappings[] = [
                        'type' => 'VHost',
                        'name' => $hostName,
                        'ssh_host' => $hostName,
                        'ns_version' => $classification['ns_version'],
                        'status' => '✅ Mapped',
                    ];
                } else {
                    $this->orphanedSshHosts[] = [
                        'type' => 'VHost',
                        'name' => $hostName,
                        'hostname' => $config['hostname'],
                        'ns_version' => $classification['ns_version'],
                        'action' => 'Create VHost',
                    ];
                }
            } else {
                // VNode
                $vnode = FleetVNode::where('name', $hostName)->first();
                if ($vnode) {
                    $this->validMappings[] = [
                        'type' => 'VNode',
                        'name' => $hostName,
                        'ssh_host' => $hostName,
                        'ns_version' => 'N/A',
                        'status' => '✅ Mapped',
                    ];
                } else {
                    $this->orphanedSshHosts[] = [
                        'type' => 'VNode',
                        'name' => $hostName,
                        'hostname' => $config['hostname'],
                        'ns_version' => 'N/A',
                        'action' => 'Create VNode',
                    ];
                }
            }
        }
    }

    /**
     * Classify host as VHost or VNode
     */
    protected function classifyHost(string $hostName, array $config): array
    {
        // Check for NetServa directory structure
        $checks = [
            '/srv' => '3.0',      // NS 3.0
            '/var/ns' => '2.0',   // NS 2.0
            '/home/u' => '1.0',   // NS 1.0
        ];

        foreach ($checks as $dir => $version) {
            if ($this->remoteDirectoryExists($config, $dir)) {
                return [
                    'type' => 'vhost',
                    'ns_version' => $version,
                ];
            }
        }

        return [
            'type' => 'vnode',
            'ns_version' => null,
        ];
    }

    /**
     * Check if directory exists on remote host
     */
    protected function remoteDirectoryExists(array $config, string $dir): bool
    {
        $command = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s -p %d "test -d %s" 2>/dev/null',
            $config['user'],
            $config['hostname'],
            $config['port'],
            $dir
        );

        $result = Process::run($command);

        return $result->successful();
    }

    /**
     * Test SSH connectivity
     */
    protected function testConnectivity(array $config): bool
    {
        $command = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s -p %d true 2>/dev/null',
            $config['user'],
            $config['hostname'],
            $config['port']
        );

        $result = Process::run($command);

        return $result->successful();
    }

    /**
     * Check for VNodes/VHosts missing SSH configs
     */
    protected function checkForMissingSSH(): void
    {
        // Check VNodes
        $vnodes = FleetVNode::all();
        foreach ($vnodes as $vnode) {
            if (! isset($this->filesystemHosts[$vnode->name])) {
                $this->vnodesMissingSSH[] = [
                    'name' => $vnode->name,
                    'vsite' => $vnode->vsite->name ?? 'N/A',
                    'action' => 'Create SSH config',
                ];
            }
        }

        // Check VHosts
        $vhosts = FleetVHost::all();
        foreach ($vhosts as $vhost) {
            if (! isset($this->filesystemHosts[$vhost->domain])) {
                $this->vhostsMissingSSH[] = [
                    'domain' => $vhost->domain,
                    'vnode' => $vhost->vnode->name ?? 'N/A',
                    'action' => 'Create SSH config',
                ];
            }
        }
    }

    /**
     * Display validation results
     */
    protected function displayResults(): void
    {
        $this->newLine();

        // Valid mappings
        if (! empty($this->validMappings)) {
            info('✅ Valid SSH Host Mappings ('.count($this->validMappings).')');
            table(
                ['Type', 'Name', 'SSH Host', 'NS Version', 'Status'],
                collect($this->validMappings)->map(fn ($item) => [
                    $item['type'],
                    $item['name'],
                    $item['ssh_host'],
                    $item['ns_version'],
                    $item['status'],
                ])->toArray()
            );
            $this->newLine();
        }

        // Orphaned SSH hosts (no VNode/VHost mapping)
        if (! empty($this->orphanedSshHosts)) {
            warning('⚠️  Orphaned SSH Hosts ('.count($this->orphanedSshHosts).')');
            warning('These SSH hosts have no corresponding VNode/VHost in database');
            table(
                ['Type', 'Name', 'Hostname', 'NS Version', 'Action'],
                collect($this->orphanedSshHosts)->map(fn ($item) => [
                    $item['type'],
                    $item['name'],
                    $item['hostname'],
                    $item['ns_version'],
                    $item['action'],
                ])->toArray()
            );
            $this->newLine();
        }

        // Unreachable hosts
        if (! empty($this->unreachable)) {
            error('❌ Unreachable SSH Hosts ('.count($this->unreachable).')');
            table(
                ['Name', 'Hostname', 'User', 'Port'],
                collect($this->unreachable)->map(fn ($item) => [
                    $item['name'],
                    $item['hostname'],
                    $item['user'],
                    $item['port'],
                ])->toArray()
            );
            $this->newLine();
        }

        // VNodes missing SSH
        if (! empty($this->vnodesMissingSSH)) {
            warning('⚠️  VNodes Missing SSH Config ('.count($this->vnodesMissingSSH).')');
            table(
                ['VNode', 'VSite', 'Action'],
                collect($this->vnodesMissingSSH)->map(fn ($item) => [
                    $item['name'],
                    $item['vsite'],
                    $item['action'],
                ])->toArray()
            );
            $this->newLine();
        }

        // VHosts missing SSH
        if (! empty($this->vhostsMissingSSH)) {
            warning('⚠️  VHosts Missing SSH Config ('.count($this->vhostsMissingSSH).')');
            table(
                ['VHost', 'VNode', 'Action'],
                collect($this->vhostsMissingSSH)->map(fn ($item) => [
                    $item['domain'],
                    $item['vnode'],
                    $item['action'],
                ])->toArray()
            );
            $this->newLine();
        }

        // Summary
        $total = count($this->filesystemHosts);
        $valid = count($this->validMappings);
        $orphaned = count($this->orphanedSshHosts);
        $unreachable = count($this->unreachable);

        info('📊 Summary:');
        info("   Total SSH hosts: {$total}");
        info("   Valid mappings: {$valid}");
        info("   Orphaned: {$orphaned}");
        info("   Unreachable: {$unreachable}");
        info('   VNodes missing SSH: '.count($this->vnodesMissingSSH));
        info('   VHosts missing SSH: '.count($this->vhostsMissingSSH));
    }

    /**
     * Offer cleanup options
     */
    protected function offerCleanupOptions(): int
    {
        if (empty($this->orphanedSshHosts) &&
            empty($this->unreachable) &&
            empty($this->vnodesMissingSSH) &&
            empty($this->vhostsMissingSSH)) {
            info('✅ All SSH hosts are properly mapped!');

            return self::SUCCESS;
        }

        $this->newLine();
        info('🔧 Cleanup Options:');

        $actions = [];

        if (! empty($this->orphanedSshHosts)) {
            $actions['create_vnodes_vhosts'] = 'Create '.count($this->orphanedSshHosts).' VNode(s)/VHost(s) for orphaned SSH hosts';
            $actions['remove_orphaned'] = 'Remove '.count($this->orphanedSshHosts).' orphaned SSH host file(s)';
        }

        if (! empty($this->unreachable)) {
            $actions['remove_unreachable'] = 'Remove '.count($this->unreachable).' unreachable SSH host file(s)';
        }

        if (! empty($this->vnodesMissingSSH) || ! empty($this->vhostsMissingSSH)) {
            $total = count($this->vnodesMissingSSH) + count($this->vhostsMissingSSH);
            $actions['create_ssh_configs'] = "Create {$total} missing SSH config file(s)";
        }

        $actions['do_nothing'] = 'Do nothing (exit)';

        $action = select(
            label: 'What would you like to do?',
            options: $actions
        );

        return match ($action) {
            'create_vnodes_vhosts' => $this->createVNodesVHosts(),
            'remove_orphaned' => $this->removeOrphanedSSH(),
            'remove_unreachable' => $this->removeUnreachableSSH(),
            'create_ssh_configs' => $this->createMissingSSHConfigs(),
            default => self::SUCCESS,
        };
    }

    /**
     * Create VNodes/VHosts for orphaned SSH hosts
     */
    protected function createVNodesVHosts(): int
    {
        warning('This feature requires integration with fleet:discover commands');
        warning('Please run: php artisan fleet:discover to create missing VNodes/VHosts');

        return self::SUCCESS;
    }

    /**
     * Remove orphaned SSH host files
     */
    protected function removeOrphanedSSH(): int
    {
        if (empty($this->orphanedSshHosts)) {
            return self::SUCCESS;
        }

        if (! confirm('Remove '.count($this->orphanedSshHosts).' orphaned SSH host file(s)?', default: false)) {
            warning('Operation cancelled.');

            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($this->orphanedSshHosts as $host) {
            $filePath = $this->filesystemHosts[$host['name']]['path'];
            try {
                unlink($filePath);
                $removed++;
                info("Removed: {$host['name']}");
            } catch (\Exception $e) {
                error("Failed to remove {$host['name']}: {$e->getMessage()}");
            }
        }

        info("✅ Removed {$removed} orphaned SSH host file(s)");

        return self::SUCCESS;
    }

    /**
     * Remove unreachable SSH host files
     */
    protected function removeUnreachableSSH(): int
    {
        if (empty($this->unreachable)) {
            return self::SUCCESS;
        }

        if (! confirm('Remove '.count($this->unreachable).' unreachable SSH host file(s)?', default: false)) {
            warning('Operation cancelled.');

            return self::SUCCESS;
        }

        $removed = 0;
        foreach ($this->unreachable as $host) {
            $filePath = $this->filesystemHosts[$host['name']]['path'];
            try {
                unlink($filePath);
                $removed++;
                info("Removed: {$host['name']}");
            } catch (\Exception $e) {
                error("Failed to remove {$host['name']}: {$e->getMessage()}");
            }
        }

        info("✅ Removed {$removed} unreachable SSH host file(s)");

        return self::SUCCESS;
    }

    /**
     * Create missing SSH config files
     */
    protected function createMissingSSHConfigs(): int
    {
        warning('This feature requires SSH configuration generation');
        warning('SSH configs should be created through VNode/VHost discovery process');

        return self::SUCCESS;
    }
}
