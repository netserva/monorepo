<?php

namespace NetServa\Core\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for CLI tests
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

        // Default exec response
        $mock->shouldReceive('exec')->andReturn('');

        return $mock;
    }

    /**
     * Create a test SSH host model
     */
    protected function createTestSshHost(array $attributes = []): \NetServa\Core\Models\SshHost
    {
        return \NetServa\Core\Models\SshHost::factory()->create(array_merge([
            'name' => 'test-server',
            'hostname' => 'test.example.com',
            'port' => 22,
            'username' => 'root',
        ], $attributes));
    }

    /**
     * Mock successful SSH command execution
     */
    protected function mockSshExec(string $command, string $output = '', int $exitCode = 0): void
    {
        // Can be extended with Process::fake() or SSH facade mocking
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
