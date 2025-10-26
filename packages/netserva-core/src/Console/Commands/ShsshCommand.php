<?php

namespace NetServa\Core\Console\Commands;

use Illuminate\Console\Command;
use NetServa\Core\Models\SshHost;

/**
 * Show SSH Host Command (NetServa 3.0 CRUD: READ)
 *
 * Displays SSH host information
 */
class ShsshCommand extends Command
{
    protected $signature = 'shssh
                            {host? : Specific SSH host to display}
                            {--format=table : Output format (table, json, csv)}
                            {--test : Test SSH connections}';

    protected $description = 'Show SSH host information (NetServa 3.0 CRUD: Read)';

    public function handle(): int
    {
        $host = $this->argument('host');
        $format = $this->option('format');
        $test = $this->option('test');

        if ($host) {
            return $this->showSingleHost($host, $format, $test);
        }

        return $this->showAllHosts($format, $test);
    }

    protected function showSingleHost(string $host, string $format, bool $test): int
    {
        $sshHost = SshHost::where('host', $host)->first();

        if (! $sshHost) {
            $this->error("SSH host not found: {$host}");

            return Command::FAILURE;
        }

        if ($format === 'json') {
            $this->line(json_encode($sshHost->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("SSH Host: {$sshHost->host}");
        $this->newLine();

        $data = [
            ['Property', 'Value'],
            ['Host', $sshHost->host],
            ['Hostname', $sshHost->hostname],
            ['User', $sshHost->user],
            ['Port', $sshHost->port],
            ['Key', $sshHost->key ?? 'Default (~/.ssh/id_rsa)'],
            ['Status', $sshHost->active ? 'Active' : 'Inactive'],
            ['Created', $sshHost->created_at?->format('Y-m-d H:i:s')],
        ];

        if ($test) {
            $result = $this->testSshConnection($sshHost);
            $data[] = ['Connection', $result['success'] ? '✓ Connected' : '✗ Failed'];
            if (! $result['success'] && $result['error']) {
                $data[] = ['Error', $result['error']];
            }
        }

        $this->table($data[0], array_slice($data, 1));

        return Command::SUCCESS;
    }

    protected function showAllHosts(string $format, bool $test): int
    {
        $sshHosts = SshHost::orderBy('host')->get();

        if ($sshHosts->isEmpty()) {
            $this->warn('No SSH hosts found.');
            $this->info('Create an SSH host: addssh <host> <hostname>');

            return Command::SUCCESS;
        }

        if ($format === 'json') {
            $this->line(json_encode($sshHosts->toArray(), JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        if ($format === 'csv') {
            $this->line('Host,Hostname,User,Port,Status');
            foreach ($sshHosts as $sshHost) {
                $this->line(sprintf(
                    '%s,%s,%s,%d,%s',
                    $sshHost->host,
                    $sshHost->hostname,
                    $sshHost->user,
                    $sshHost->port,
                    $sshHost->active ? 'active' : 'inactive'
                ));
            }

            return Command::SUCCESS;
        }

        // Table format
        $data = [];
        foreach ($sshHosts as $sshHost) {
            $row = [
                'Host' => $sshHost->host,
                'Hostname' => $sshHost->hostname,
                'User' => $sshHost->user,
                'Port' => $sshHost->port,
                'Status' => $sshHost->active ? '✓ Active' : '✗ Inactive',
            ];

            if ($test) {
                $result = $this->testSshConnection($sshHost);
                $row['Connection'] = $result['success'] ? '✓' : '✗';
            }

            $data[] = $row;
        }

        $headers = array_keys($data[0]);
        $this->table($headers, $data);
        $this->newLine();
        $this->info('Total: '.$sshHosts->count().' SSH hosts');

        if ($test) {
            $connected = collect($data)->where('Connection', '✓')->count();
            $this->info("Connected: {$connected}/{$sshHosts->count()}");
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
            'error' => $returnCode !== 0 ? 'Connection failed' : null,
        ];
    }
}
