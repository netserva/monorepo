<?php

use NetServa\Wg\Models\WireguardPeer;
use NetServa\Wg\Models\WireguardServer;

uses()
    ->group('unit', 'models', 'wireguard-server', 'priority-1');

it('can create a wireguard server', function () {
    $server = WireguardServer::factory()->create([
        'name' => 'main-vpn-server',
        'network_cidr' => '10.0.1.0/24',
        'server_ip' => '10.0.1.1',
        'listen_port' => 51820,
        'status' => 'active',
        'is_active' => true,
    ]);

    expect($server)->toBeInstanceOf(WireguardServer::class)
        ->and($server->name)->toBe('main-vpn-server')
        ->and($server->network_cidr)->toBe('10.0.1.0/24')
        ->and($server->server_ip)->toBeValidIpv4()
        ->and($server->listen_port)->toBe(51820)
        ->and($server->status)->toBe('active')
        ->and($server->is_active)->toBeTrue()
        ->and($server->exists)->toBeTrue();
});

it('has default values', function () {
    $server = new WireguardServer;

    expect($server->status)->toBe('draft')
        ->and($server->is_active)->toBeTrue()
        ->and($server->listen_port)->toBe(51820);
});

it('has many peers relationship', function () {
    $server = createTestWireguardServer();

    WireguardPeer::factory()->count(3)->create([
        'wireguard_server_id' => $server->id,
    ]);

    expect($server->peers)->toHaveCount(3)
        ->and($server->peers->first())->toBeInstanceOf(WireguardPeer::class);
});

it('has active peers relationship', function () {
    $server = createTestWireguardServer();

    WireguardPeer::factory()->count(2)->create([
        'wireguard_server_id' => $server->id,
        'is_active' => true,
    ]);

    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'is_active' => false,
    ]);

    expect($server->activePeers)->toHaveCount(2)
        ->and($server->activePeers->first()->is_active)->toBeTrue();
});

it('can get next available IP address', function () {
    $server = WireguardServer::factory()->create([
        'network_cidr' => '10.0.1.0/24',
        'server_ip' => '10.0.1.1',
    ]);

    // Create some peers with allocated IPs
    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'allocated_ip' => '10.0.1.2',
    ]);

    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'allocated_ip' => '10.0.1.3',
    ]);

    $nextIp = $server->getNextAvailableIp();

    expect($nextIp)->toBe('10.0.1.4');
});

it('handles IP allocation correctly when some IPs are skipped', function () {
    $server = WireguardServer::factory()->create([
        'network_cidr' => '10.0.1.0/24',
        'server_ip' => '10.0.1.1',
    ]);

    // Create peers with non-sequential IPs
    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'allocated_ip' => '10.0.1.2',
    ]);

    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'allocated_ip' => '10.0.1.5',
    ]);

    $nextIp = $server->getNextAvailableIp();

    expect($nextIp)->toBe('10.0.1.3'); // Should fill gap first
});

it('throws exception when no IPs available', function () {
    $server = WireguardServer::factory()->create([
        'network_cidr' => '10.0.1.0/30', // Only 2 host IPs available
        'server_ip' => '10.0.1.1',
    ]);

    // Allocate both available IPs
    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'allocated_ip' => '10.0.1.2',
    ]);

    expect(fn () => $server->getNextAvailableIp())
        ->toThrow(\Exception::class, 'No available IP addresses in network');
});

it('can generate server configuration', function () {
    $server = WireguardServer::factory()->create([
        'private_key' => 'test-private-key-12345678901234567890123=',
        'network_cidr' => '10.0.1.0/24',
        'listen_port' => 51820,
    ]);

    // Create active peers
    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'public_key' => 'peer1-public-key-123456789012345678901234=',
        'allocated_ip' => '10.0.1.2',
        'is_active' => true,
    ]);

    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'public_key' => 'peer2-public-key-123456789012345678901234=',
        'allocated_ip' => '10.0.1.3',
        'is_active' => true,
    ]);

    // Create inactive peer (should not be included)
    WireguardPeer::factory()->create([
        'wireguard_server_id' => $server->id,
        'public_key' => 'inactive-peer-key-123456789012345678901=',
        'allocated_ip' => '10.0.1.4',
        'is_active' => false,
    ]);

    $config = $server->generateServerConfig();

    expect($config)->toContain('[Interface]')
        ->and($config)->toContain('PrivateKey = test-private-key-12345678901234567890123=')
        ->and($config)->toContain('Address = 10.0.1.0/24')
        ->and($config)->toContain('ListenPort = 51820')
        ->and($config)->toContain('[Peer]')
        ->and($config)->toContain('PublicKey = peer1-public-key-123456789012345678901234=')
        ->and($config)->toContain('AllowedIPs = 10.0.1.2/32')
        ->and($config)->toContain('PublicKey = peer2-public-key-123456789012345678901234=')
        ->and($config)->toContain('AllowedIPs = 10.0.1.3/32')
        ->and($config)->not->toContain('inactive-peer-key'); // Inactive peer not included
});

it('generates minimal config with no peers', function () {
    $server = WireguardServer::factory()->create([
        'private_key' => 'test-private-key-12345678901234567890123=',
        'network_cidr' => '10.0.1.0/24',
        'listen_port' => 51820,
    ]);

    $config = $server->generateServerConfig();

    expect($config)->toContain('[Interface]')
        ->and($config)->toContain('PrivateKey = test-private-key-12345678901234567890123=')
        ->and($config)->toContain('Address = 10.0.1.0/24')
        ->and($config)->toContain('ListenPort = 51820')
        ->and($config)->not->toContain('[Peer]');
});

it('casts is_active to boolean', function () {
    $server = WireguardServer::factory()->create(['is_active' => 1]);

    expect($server->is_active)->toBeTrue();

    $server->update(['is_active' => 0]);

    expect($server->fresh()->is_active)->toBeFalse();
});

it('validates network CIDR format', function () {
    $server = WireguardServer::factory()->create(['network_cidr' => '192.168.1.0/24']);

    expect($server->network_cidr)->toMatch('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\/\d{1,2}$/');
});

it('handles different network sizes correctly', function () {
    // Test /29 network (6 host IPs)
    $server = WireguardServer::factory()->create([
        'network_cidr' => '192.168.1.0/29',
        'server_ip' => '192.168.1.1',
    ]);

    expect($server->getNextAvailableIp())->toBe('192.168.1.2');

    // Test /16 network (65,534 host IPs)
    $largeServer = WireguardServer::factory()->create([
        'network_cidr' => '10.0.0.0/16',
        'server_ip' => '10.0.0.1',
    ]);

    expect($largeServer->getNextAvailableIp())->toBe('10.0.0.2');
});

it('avoids server IP when allocating peer IPs', function () {
    $server = WireguardServer::factory()->create([
        'network_cidr' => '10.0.1.0/30', // Only 2 host IPs: .1 and .2
        'server_ip' => '10.0.1.2', // Server uses .2
    ]);

    // Next available should be .1, not .2 (which is server IP)
    expect($server->getNextAvailableIp())->toBe('10.0.1.1');
});

it('handles factory creation with default values', function () {
    $server = WireguardServer::factory()->create();

    expect($server->status)->toBe('draft')
        ->and($server->is_active)->toBeTrue()
        ->and($server->listen_port)->toBe(51820)
        ->and($server->name)->not->toBeNull()
        ->and($server->network_cidr)->not->toBeNull();
});
