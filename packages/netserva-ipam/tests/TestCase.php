<?php

namespace NetServa\Ipam\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for IPAM tests
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
     * Create test IP network
     */
    protected function createTestNetwork(array $attributes = []): array
    {
        return array_merge([
            'network' => '192.168.1.0/24',
            'name' => 'Test Network',
            'vlan_id' => null,
        ], $attributes);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
