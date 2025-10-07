<?php

namespace NetServa\Mail\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for Mail tests
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
     * Create test mail domain
     */
    protected function createTestMailDomain(array $attributes = []): array
    {
        return array_merge([
            'domain' => 'mail.example.com',
            'uid' => 1000,
            'gid' => 1000,
            'active' => true,
        ], $attributes);
    }

    /**
     * Create test mailbox
     */
    protected function createTestMailbox(array $attributes = []): array
    {
        return array_merge([
            'username' => 'user@example.com',
            'domain' => 'example.com',
            'quota' => 500000000,
            'active' => true,
        ], $attributes);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
