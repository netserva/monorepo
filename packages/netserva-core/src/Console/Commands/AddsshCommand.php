<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;

/**
 * Add SSH Host Command (NetServa 3.0 CRUD: CREATE)
 *
 * Creates a new SSH host entry for remote server access
 */
class AddsshCommand extends Command
{
    protected $signature = 'addssh
                            {host : Short host identifier (e.g., web01, markc)}
                            {hostname : Full hostname or IP address}
                            {--user=root : SSH username}
                            {--port=22 : SSH port}
                            {--key= : Path to SSH private key}
                            {--test : Test SSH connection after creating}';

    protected $description = 'Add a new SSH host (NetServa 3.0 CRUD: Create)';

    public function handle(): int
    {
        $host = $this->argument('host');
        $hostname = $this->argument('hostname');
        $user = $this->option('user');
        $port = $this->option('port');
        $key = $this->option('key');
        $test = $this->option('test');

        // Check if SSH host already exists
        $existing = SshHost::where('host', $host)->first();
        if ($existing) {
            $this->error("SSH host '{$host}' already exists.");

            return Command::FAILURE;
        }

        $this->info("Creating SSH host: {$host}");

        // Create SSH host
        $sshHost = SshHost::create([
            'host' => $host,
            'hostname' => $hostname,
            'user' => $user,
            'port' => (int) $port,
            'key' => $key,
            'active' => true,
        ]);

        $this->info('✓ SSH host created successfully');
        $this->newLine();
        $this->line('SSH Host Details:');
        $this->line("  Host: {$sshHost->host}");
        $this->line("  Hostname: {$sshHost->hostname}");
        $this->line("  User: {$sshHost->user}");
        $this->line("  Port: {$sshHost->port}");
        if ($key) {
            $this->line("  Key: {$key}");
        }

        // Test connection if requested
        if ($test) {
            $this->newLine();
            $this->info('🔌 Testing SSH connection...');

            $result = $this->testSshConnection($sshHost);

            if ($result['success']) {
                $this->info('✓ SSH connection successful');
            } else {
                $this->error("✗ SSH connection failed: {$result['error']}");
                $this->warn('Please check SSH key authentication:');
                $this->line("  ssh-copy-id {$user}@{$hostname}");
            }
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line("  1. Test connection: ssh {$user}@{$hostname}");
        $this->line("  2. Create vnode: addvnode <vsite> <name> {$host}");
        $this->line('  3. View SSH hosts: shssh');

        return Command::SUCCESS;
    }

    protected function testSshConnection(SshHost $sshHost): array
    {
        $command = sprintf(
            'ssh -o BatchMode=yes -o ConnectTimeout=5 -o StrictHostKeyChecking=no %s@%s -p %d "echo test" 2>/dev/null',
            $sshHost->user,
            $sshHost->hostname,
            $sshHost->port
        );

        exec($command, $output, $returnCode);

        return [
            'success' => $returnCode === 0 && isset($output[0]) && $output[0] === 'test',
            'error' => $returnCode !== 0 ? 'Connection failed or key authentication not configured' : null,
        ];
    }
}
