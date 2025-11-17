<?php

namespace NetServa\Fleet\Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVenue;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;
use Tests\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup for Fleet tests
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
     * Seed complete test fleet infrastructure
     */
    protected function seedTestFleet(): array
    {
        $venue = FleetVenue::factory()->create([
            'name' => 'test-datacenter',
            'location' => 'Test Location',
        ]);

        $vsite = FleetVsite::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'test-vsite',
            'provider' => 'local',
        ]);

        $vnode = FleetVnode::factory()->create([
            'vsite_id' => $vsite->id,
            'name' => 'test-vnode',
            'technology' => 'proxmox',
        ]);

        $sshHost = SshHost::factory()->create([
            'name' => 'test-host',
            'hostname' => 'test.example.com',
        ]);

        $vhost = FleetVhost::factory()->create([
            'vnode_id' => $vnode->id,
            'ssh_host_id' => $sshHost->id,
            'domain' => 'test.example.com',
        ]);

        return [
            'venue' => $venue,
            'vsite' => $vsite,
            'vnode' => $vnode,
            'vhost' => $vhost,
            'ssh_host' => $sshHost,
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
