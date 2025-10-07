<?php

namespace Tests\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

/**
 * Global NetServa testing helpers for shared functionality across all packages
 */
trait NetServaTestHelpers
{
    /**
     * Set up NetServa environment variables for testing
     */
    protected function setUpNetServaEnvironment(): void
    {
        putenv('NS=/home/markc/.ns');
        putenv('NS_TESTING_MODE=true');
        putenv('NS_SSH_MOCK_CONNECTIONS=true');
        putenv('NS_DNS_MOCK_API=true');
    }

    /**
     * Create test vhost configuration environment
     */
    protected function createTestVhostEnvironment(string $vhost = 'test.example.com', string $vnode = 'test'): array
    {
        return [
            'VHOST' => $vhost,
            'VNODE' => $vnode,
            'HNAME' => $vnode,
            'HDOMN' => $vhost,
            'IP4_0' => '192.168.100.10',
            'DNAME' => str_replace('.', '_', $vhost),
            'DTYPE' => 'sqlite',
            'DPATH' => "/tmp/test_{$vnode}",
            'MPATH' => "/srv/{$vhost}/msg",
            'WPATH' => "/srv/{$vhost}/web",
            'UPATH' => "/srv/{$vhost}",
            'VPATH' => '/srv',
            'ADMIN' => 'admin',
            'AMAIL' => "admin@{$vhost}",
            'OSTYP' => 'debian',
            'OSREL' => 'bookworm',
        ];
    }

    /**
     * Mock all external process execution
     */
    protected function mockAllProcesses(): void
    {
        Process::fake([
            // SSH commands
            'ssh *' => Process::result('Connection successful', '', 0),
            'scp *' => Process::result('Transfer complete', '', 0),

            // System commands
            'systemctl *' => Process::result('Active: active (running)', '', 0),
            'service *' => Process::result('Service is running', '', 0),

            // File operations
            'mkdir *' => Process::result('', '', 0),
            'chmod *' => Process::result('', '', 0),
            'chown *' => Process::result('', '', 0),

            // DNS tools
            'dig *' => Process::result('ANSWER SECTION:\ntest.example.com. 300 IN A 192.168.100.10', '', 0),
            'nslookup *' => Process::result('Server: 8.8.8.8\nAddress: 192.168.100.10', '', 0),

            // Default success for any other command
            '*' => Process::result('Command executed successfully', '', 0),
        ]);
    }

    /**
     * Mock HTTP requests for external APIs
     */
    protected function mockExternalApis(): void
    {
        Http::fake([
            // CloudFlare API
            'api.cloudflare.com/*' => Http::response([
                'success' => true,
                'result' => ['id' => 'test-cf-id'],
            ], 200),

            // BinaryLane API
            'api.binarylane.com.au/*' => Http::response([
                'servers' => [['id' => 1, 'name' => 'test-server']],
            ], 200),

            // PowerDNS API
            'localhost:8081/api/v1/*' => Http::response([
                'zone' => 'test.example.com.',
                'records' => [],
            ], 200),

            // Let's Encrypt
            'acme-v02.api.letsencrypt.org/*' => Http::response([
                'status' => 'valid',
            ], 200),

            // Default success
            '*' => Http::response(['status' => 'success'], 200),
        ]);
    }

    /**
     * Clear all caches for clean test state
     */
    protected function clearTestCaches(): void
    {
        Cache::flush();

        // Clear any Laravel caches
        \Illuminate\Support\Facades\Artisan::call('cache:clear');
        \Illuminate\Support\Facades\Artisan::call('config:clear');
        \Illuminate\Support\Facades\Artisan::call('route:clear');
    }

    /**
     * Create a temporary directory for test files
     */
    protected function createTempDirectory(string $prefix = 'netserva_test_'): string
    {
        $path = sys_get_temp_dir().'/'.$prefix.uniqid();

        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Clean up temporary directories after test
     */
    protected function cleanupTempDirectories(): void
    {
        $tempDir = sys_get_temp_dir();
        $pattern = $tempDir.'/netserva_test_*';

        foreach (glob($pattern) as $dir) {
            if (is_dir($dir)) {
                $this->removeDirectory($dir);
            }
        }
    }

    /**
     * Recursively remove directory and contents
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            $path = $dir.'/'.$file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    /**
     * Assert that a command was called with specific arguments
     */
    protected function assertProcessCalled(string $command): void
    {
        Process::assertRan($command);
    }

    /**
     * Generate test SSH key pair for testing
     */
    protected function generateTestSshKeyPair(): array
    {
        return [
            'private_key' => '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABAAAAFwAAAAdzc2gtcn
NhAAAAAwEAAQAAAQEAtest+private+key+content+here
-----END OPENSSH PRIVATE KEY-----',
            'public_key' => 'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCtest+public+key+content+here test@example.com',
            'fingerprint' => 'SHA256:test-fingerprint-'.uniqid(),
        ];
    }
}
