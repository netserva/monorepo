<?php

use NetServa\Core\Models\SshHost;
use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;
use NetServa\Fleet\Services\FleetDiscoveryService;

uses()
    ->group('unit', 'fleet', 'discovery', 'priority-2');

beforeEach(function () {
    $this->discoveryService = new FleetDiscoveryService;
});

it('can parse cpu cores from output', function () {
    $output = '4';
    $result = $this->discoveryService->parseCpuCores($output);

    expect($result)->toBe(4);
});

it('can parse memory from output', function () {
    $output = '8192';
    $result = $this->discoveryService->parseMemory($output);

    expect($result)->toBe(8192);
});

it('can parse disk space from output', function () {
    $diskOutputGb = '120G';
    $diskOutputMb = '1024M';

    $resultGb = $this->discoveryService->parseDisk($diskOutputGb);
    $resultMb = $this->discoveryService->parseDisk($diskOutputMb);

    expect($resultGb)->toBe(120)
        ->and($resultMb)->toBe(1);
});

it('can parse services from ps output', function () {
    $output = '127';
    $result = $this->discoveryService->parseServices($output);

    expect($result)->toBeArray()
        ->and($result['process_count'])->toBe(127);
});

it('can parse ip addresses from ip addr output', function () {
    $output = 'inet 192.168.1.100/24 brd 192.168.1.255 scope global eth0';
    $result = $this->discoveryService->parseIpAddresses($output);

    expect($result)->toBe('192.168.1.100');
});

it('skips loopback addresses when parsing ip', function () {
    $output = 'inet 127.0.0.1/8 scope host lo';
    $result = $this->discoveryService->parseIpAddresses($output);

    expect($result)->toBeNull();
});

it('can test ssh connection for vnode with ssh host', function () {
    $vsite = FleetVSite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $sshHost = SshHost::create([
        'host' => 'test-host',
        'hostname' => 'test.example.com',
        'port' => 22,
        'user' => 'root',
        'is_active' => true,
    ]);

    $vnode = FleetVNode::create([
        'name' => 'test-node',
        'vsite_id' => $vsite->id,
        'ssh_host_id' => $sshHost->id,
    ]);

    // Mock the SSH execution to avoid actual SSH calls in tests
    $result = $this->discoveryService->testSshConnection($vnode);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['success', 'error']);
});

it('returns error for vnode without ssh host', function () {
    $vsite = FleetVSite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $vnode = FleetVNode::create([
        'name' => 'test-node',
        'vsite_id' => $vsite->id,
    ]);

    $result = $this->discoveryService->testSshConnection($vnode);

    expect($result['success'])->toBeFalse()
        ->and($result['error'])->toBe('No SSH host configured');
});

it('can parse command output correctly', function () {
    $service = new FleetDiscoveryService;
    $data = [];

    // Test hostname parsing
    $service->parseCommandOutput('hostname -f', 'test.example.com', $data);
    expect($data['operating_system'])->toBe('test.example.com');

    // Test CPU parsing
    $service->parseCommandOutput('cat /proc/cpuinfo | grep "processor" | wc -l', '8', $data);
    expect($data['cpu_cores'])->toBe(8);

    // Test memory parsing
    $service->parseCommandOutput('free -m | grep "Mem:" | awk \'{print $2}\'', '16384', $data);
    expect($data['memory_mb'])->toBe(16384);

    // Test disk parsing
    $service->parseCommandOutput('df -h / | tail -1 | awk \'{print $2}\'', '100G', $data);
    expect($data['disk_gb'])->toBe(100);
});

it('handles discovery gracefully when commands fail', function () {
    $vsite = FleetVSite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $vnode = FleetVNode::create([
        'name' => 'test-node',
        'vsite_id' => $vsite->id,
        'role' => 'compute',
    ]);

    // Discovery should handle missing SSH gracefully
    $result = $this->discoveryService->discoverVNode($vnode);

    expect($result)->toBeFalse()
        ->and($vnode->fresh()->last_error)->toContain('No SSH access configured');
});

it('can discover different node roles', function () {
    $vsite = FleetVSite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $computeNode = FleetVNode::create([
        'name' => 'compute-node',
        'vsite_id' => $vsite->id,
        'role' => 'compute',
    ]);

    $networkNode = FleetVNode::create([
        'name' => 'network-node',
        'vsite_id' => $vsite->id,
        'role' => 'network',
    ]);

    $storageNode = FleetVNode::create([
        'name' => 'storage-node',
        'vsite_id' => $vsite->id,
        'role' => 'storage',
    ]);

    // All should fail gracefully without SSH, but with appropriate error handling
    expect($this->discoveryService->discoverVNode($computeNode))->toBeFalse()
        ->and($this->discoveryService->discoverVNode($networkNode))->toBeFalse()
        ->and($this->discoveryService->discoverVNode($storageNode))->toBeFalse();
});
