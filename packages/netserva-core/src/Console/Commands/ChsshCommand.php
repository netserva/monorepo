<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;

/**
 * Change SSH Host Command (NetServa 3.0 CRUD: UPDATE)
 *
 * Updates SSH host configuration
 */
class ChsshCommand extends Command
{
    protected $signature = 'chssh
                            {host : SSH host identifier to update}
                            {--hostname= : Update hostname or IP address}
                            {--user= : Update SSH username}
                            {--port= : Update SSH port}
                            {--key= : Update path to SSH private key}
                            {--active= : Set active status (1=active, 0=inactive)}
                            {--test : Test SSH connection after update}';

    protected $description = 'Change SSH host configuration (NetServa 3.0 CRUD: Update)';

    public function handle(): int
    {
        $host = $this->argument('host');

        $sshHost = SshHost::where('host', $host)->first();

        if (! $sshHost) {
            $this->error("SSH host not found: {$host}");
            $this->info('Available SSH hosts: '.SshHost::pluck('host')->implode(', '));

            return Command::FAILURE;
        }

        $updated = false;
        $changes = [];

        if ($hostname = $this->option('hostname')) {
            $oldHostname = $sshHost->hostname;
            $sshHost->hostname = $hostname;
            $changes[] = "hostname: {$oldHostname} → {$hostname}";
            $updated = true;
        }

        if ($user = $this->option('user')) {
            $oldUser = $sshHost->user;
            $sshHost->user = $user;
            $changes[] = "user: {$oldUser} → {$user}";
            $updated = true;
        }

        if ($port = $this->option('port')) {
            $oldPort = $sshHost->port;
            $sshHost->port = (int) $port;
            $changes[] = "port: {$oldPort} → {$port}";
            $updated = true;
        }

        if ($this->option('key') !== null) {
            $key = $this->option('key');
            $oldKey = $sshHost->key ?? 'Default';
            $sshHost->key = $key ?: null;
            $changes[] = "key: {$oldKey} → ".($key ?: 'Default');
            $updated = true;
        }

        if ($this->option('active') !== null) {
            $active = (bool) $this->option('active');
            $oldStatus = $sshHost->active ? 'active' : 'inactive';
            $newStatus = $active ? 'active' : 'inactive';
            $sshHost->active = $active;
            $changes[] = "status: {$oldStatus} → {$newStatus}";
            $updated = true;
        }

        if (! $updated) {
            $this->warn('No changes specified. Use --hostname, --user, --port, --key, or --active options.');

            return Command::FAILURE;
        }

        $sshHost->save();

        $this->info('✓ SSH host updated successfully');
        $this->newLine();
        $this->line('Changes applied:');
        foreach ($changes as $change) {
            $this->line("  • {$change}");
        }

        // Test connection if requested
        if ($this->option('test')) {
            $this->newLine();
            $this->info('🔌 Testing SSH connection...');

            $result = $this->testSshConnection($sshHost);

            if ($result['success']) {
                $this->info('✓ SSH connection successful');
            } else {
                $this->error("✗ SSH connection failed: {$result['error']}");
                $this->warn('Please verify SSH access:');
                $this->line("  ssh {$sshHost->user}@{$sshHost->hostname} -p {$sshHost->port}");
            }
        }

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
