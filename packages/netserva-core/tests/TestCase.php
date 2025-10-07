<?php

namespace NetServa\Core\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use NetServa\Core\Models\SshHost;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for Core tests
    }

    /**
     * Mock SSH connection for testing
     */
    protected function mockSshConnection(string $hostname = 'test.example.com', array $responses = []): Mockery\MockInterface
    {
        $mock = Mockery::mock('phpseclib3\Net\SSH2');

        $mock->shouldReceive('login')->andReturn(true);
        $mock->shouldReceive('isConnected')->andReturn(true);

        foreach ($responses as $command => $response) {
            $mock->shouldReceive('exec')
                ->with($command)
                ->andReturn($response);
        }

        $mock->shouldReceive('exec')->andReturn('');

        return $mock;
    }

    /**
     * Create a test SSH host
     */
    protected function createTestSshHost(array $attributes = []): SshHost
    {
        return SshHost::factory()->create(array_merge([
            'name' => 'test-server',
            'hostname' => 'test.example.com',
            'port' => 22,
            'username' => 'root',
        ], $attributes));
    }

    /**
     * Seed test infrastructure
     */
    protected function seedTestInfrastructure(): array
    {
        $hosts = [
            SshHost::factory()->create(['name' => 'pve1', 'hostname' => 'pve1.example.com']),
            SshHost::factory()->create(['name' => 'ns1', 'hostname' => 'ns1.example.com']),
        ];

        return [
            'hosts' => $hosts,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
