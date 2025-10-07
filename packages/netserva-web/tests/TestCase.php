<?php

namespace NetServa\Web\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for Web tests
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
     * Create test virtual host
     */
    protected function createTestVhost(array $attributes = []): array
    {
        return array_merge([
            'domain' => 'example.com',
            'uid' => 1001,
            'gid' => 1001,
            'php_version' => '8.4',
        ], $attributes);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
