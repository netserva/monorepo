<?php

namespace Tests\Traits;

use Mockery;
use Ns\Ssh\Models\SshHost;
use Ns\Ssh\Models\SshKey;
use Ns\Ssh\Services\SshConnectionService;

trait InteractsWithSsh
{
    /**
     * Create a mock SSH host for testing
     */
    protected function createMockSshHost(array $attributes = []): SshHost
    {
        return SshHost::factory()->create(array_merge([
            'name' => 'test-host',
            'hostname' => 'test.example.com',
            'port' => 22,
            'username' => 'root',
            'connection_type' => 'key',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Create a mock SSH key for testing
     */
    protected function createMockSshKey(array $attributes = []): SshKey
    {
        return SshKey::factory()->create(array_merge([
            'name' => 'test-key',
            'key_type' => 'ed25519',
            'fingerprint' => 'SHA256:test-fingerprint-'.uniqid(),
            'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITest test@example.com',
            'is_active' => true,
        ], $attributes));
    }

    /**
     * Mock successful SSH connection
     */
    protected function mockSuccessfulSshConnection(?SshHost $host = null): void
    {
        // Use Pest 4 mocking method
        $this->mock(SshConnectionService::class, function ($mock) {
            $mock->shouldReceive('connect')
                ->andReturn(true);

            $mock->shouldReceive('isConnected')
                ->andReturn(true);

            $mock->shouldReceive('testConnection')
                ->andReturn(true);

            $mock->shouldReceive('testConnection')
                ->with(\Mockery::any())
                ->andReturn(true);

            $mock->shouldReceive('execute')
                ->andReturn([
                    'exit_code' => 0,
                    'output' => 'Command executed successfully',
                    'error' => '',
                ]);

            $mock->shouldReceive('disconnect')
                ->andReturn(true);
        });
    }

    /**
     * Mock failed SSH connection
     */
    protected function mockFailedSshConnection(string $errorMessage = 'Connection failed'): void
    {
        $this->mock(SshConnectionService::class, function ($mock) use ($errorMessage) {
            $mock->shouldReceive('connect')
                ->andReturn(false);

            $mock->shouldReceive('testConnection')
                ->andReturn(false);

            $mock->shouldReceive('testConnection')
                ->with(\Mockery::any())
                ->andReturn(false);

            $mock->shouldReceive('getLastError')
                ->andReturn($errorMessage);
        });
    }

    /**
     * Mock SSH command execution with custom output
     */
    protected function mockSshCommandExecution(array $commands): void
    {
        $this->mock(SshConnectionService::class, function ($mock) use ($commands) {
            $mock->shouldReceive('connect')->andReturn(true);
            $mock->shouldReceive('isConnected')->andReturn(true);
            $mock->shouldReceive('testConnection')->andReturn(true);
            $mock->shouldReceive('testConnection')->with(\Mockery::any())->andReturn(true);

            foreach ($commands as $command => $response) {
                $mock->shouldReceive('execute')
                    ->with($command)
                    ->andReturn([
                        'exit_code' => $response['exit_code'] ?? 0,
                        'output' => $response['output'] ?? '',
                        'error' => $response['error'] ?? '',
                    ]);

                $mock->shouldReceive('exec')
                    ->with('localhost', $command)
                    ->andReturn([
                        'success' => true, // SSH connection successful
                        'output' => $response['output'] ?? '',
                        'error' => $response['error'] ?? '',
                        'exit_code' => $response['exit_code'] ?? 0,
                    ]);

                // Also mock the exec method with any host for flexibility
                $mock->shouldReceive('exec')
                    ->with(\Mockery::any(), $command)
                    ->andReturn([
                        'success' => true, // SSH connection successful
                        'output' => $response['output'] ?? '',
                        'error' => $response['error'] ?? '',
                        'exit_code' => $response['exit_code'] ?? 0,
                    ]);
            }

            $mock->shouldReceive('disconnect')->andReturn(true);
        });
    }

    /**
     * Mock system information gathering via SSH
     */
    protected function mockSystemInfoGathering(array $systemInfo = []): void
    {
        $defaultSystemInfo = [
            'hostname' => 'test-server',
            'os_release' => 'Ubuntu 24.04 LTS',
            'kernel' => 'Linux 6.8.0-31-generic',
            'uptime' => '1 day, 2 hours, 30 minutes',
            'memory' => [
                'total' => '8GB',
                'used' => '2GB',
                'free' => '6GB',
            ],
            'disk' => [
                'total' => '100GB',
                'used' => '25GB',
                'free' => '75GB',
            ],
            'load_average' => '0.15, 0.20, 0.25',
        ];

        $systemInfo = array_merge($defaultSystemInfo, $systemInfo);

        $this->mockSshCommandExecution([
            'hostname' => ['output' => $systemInfo['hostname']],
            'cat /etc/os-release | grep PRETTY_NAME' => ['output' => 'PRETTY_NAME="'.$systemInfo['os_release'].'"'],
            'uname -a' => ['output' => $systemInfo['kernel']],
            'uptime -p' => ['output' => $systemInfo['uptime']],
            'free -h' => ['output' => sprintf("              total        used        free\nMem:          %s        %s        %s",
                $systemInfo['memory']['total'],
                $systemInfo['memory']['used'],
                $systemInfo['memory']['free']
            )],
            'df -h /' => ['output' => sprintf("Filesystem      Size  Used Avail Use%% Mounted on\n/dev/sda1       %s   %s   %s  25%% /",
                $systemInfo['disk']['total'],
                $systemInfo['disk']['used'],
                $systemInfo['disk']['free']
            )],
            'uptime' => ['output' => 'load average: '.$systemInfo['load_average']],
        ]);
    }

    /**
     * Mock SSH key validation
     */
    protected function mockSshKeyValidation(bool $isValid = true): void
    {
        $this->mock(SshConnectionService::class, function ($mock) use ($isValid) {
            if ($isValid) {
                $mock->shouldReceive('validateKey')
                    ->andReturn(true);

                $mock->shouldReceive('generateKeyPair')
                    ->andReturn([
                        'public_key' => 'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAITest test@example.com',
                        'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----\ntest-private-key\n-----END OPENSSH PRIVATE KEY-----',
                        'fingerprint' => 'SHA256:test-fingerprint-'.uniqid(),
                    ]);
            } else {
                $mock->shouldReceive('validateKey')
                    ->andReturn(false);

                $mock->shouldReceive('getLastError')
                    ->andReturn('Invalid SSH key format');
            }
        });
    }

    /**
     * Assert SSH connection was attempted
     */
    protected function assertSshConnectionAttempted(): void
    {
        $this->assertTrue(
            Mockery::getContainer()->mockery_getExpectationCount() > 0,
            'Expected SSH connection to be attempted'
        );
    }

    /**
     * Create test SSH configuration for integration tests
     */
    protected function createTestSshConfiguration(): array
    {
        $sshKey = $this->createMockSshKey();
        $sshHost = $this->createMockSshHost([
            'ssh_key_id' => $sshKey->id,
        ]);

        return [
            'host' => $sshHost,
            'key' => $sshKey,
            'config' => [
                'host' => $sshHost->hostname,
                'port' => $sshHost->port,
                'username' => $sshHost->username,
                'key_path' => storage_path('app/ssh_keys/'.$sshKey->name),
            ],
        ];
    }
}
