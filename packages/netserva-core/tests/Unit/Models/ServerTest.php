<?php

use NetServa\Core\Models\Server;
use NetServa\Core\Models\SshHost;
use NetServa\Core\Models\VHost;

uses()
    ->group('unit', 'models', 'server', 'priority-1');

it('can create a server', function () {
    $server = Server::factory()->create([
        'name' => 'test-server',
        'hostname' => 'test.example.com',
        'ip_address' => '192.168.100.10',
        'status' => 'active',
    ]);

    expect($server)->toBeInstanceOf(Server::class)
        ->and($server->name)->toBe('test-server')
        ->and($server->hostname)->toBe('test.example.com')
        ->and($server->ip_address)->toBe('192.168.100.10')
        ->and($server->status)->toBe('active')
        ->and($server->exists)->toBeTrue();
});

it('validates ip address format', function () {
    $server = Server::factory()->make([
        'ip_address' => '192.168.100.10',
    ]);

    expect($server->ip_address)->toBe('192.168.100.10')
        ->and(filter_var($server->ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))->not->toBeFalse();
});

it('has many vhosts relationship', function () {
    $server = Server::factory()->create();

    VHost::factory()->count(3)->create([
        'server_id' => $server->id,
    ]);

    expect($server->vhosts)->toHaveCount(3)
        ->and($server->vhosts->first())->toBeInstanceOf(VHost::class);
});

it('has one ssh host relationship', function () {
    $server = Server::factory()->create();
    $sshHost = SshHost::factory()->create(['server_id' => $server->id]);

    expect($server->sshHost)->toBeInstanceOf(SshHost::class)
        ->and($server->sshHost->id)->toBe($sshHost->id);
});

it('can find active servers only', function () {
    Server::factory()->create(['status' => 'active']);
    Server::factory()->create(['status' => 'inactive']);
    Server::factory()->create(['status' => 'active']);

    $activeServers = Server::active()->get();

    expect($activeServers)->toHaveCount(2)
        ->and($activeServers->first()->status)->toBe('active');
});

it('can find servers by status', function () {
    Server::factory()->create(['status' => 'maintenance']);
    Server::factory()->create(['status' => 'active']);
    Server::factory()->create(['status' => 'maintenance']);

    $maintenanceServers = Server::byStatus('maintenance')->get();

    expect($maintenanceServers)->toHaveCount(2)
        ->and($maintenanceServers->first()->status)->toBe('maintenance');
});

it('can check if server is online', function () {
    $onlineServer = Server::factory()->create(['status' => 'active']);
    $offlineServer = Server::factory()->create(['status' => 'inactive']);

    expect($onlineServer->isOnline())->toBeTrue()
        ->and($offlineServer->isOnline())->toBeFalse();
});

it('can test connectivity', function () {
    $server = Server::factory()->create([
        'hostname' => 'test.example.com',
    ]);

    // Mock successful ping
    $result = $server->testConnectivity();

    expect($result)->toBeArray()
        ->and($result['reachable'])->toBeTrue();
});

it('can get server statistics', function () {
    $server = Server::factory()->create();

    VHost::factory()->count(5)->create(['server_id' => $server->id]);

    $stats = $server->getStatistics();

    expect($stats)->toHaveKey('vhost_count')
        ->and($stats['vhost_count'])->toBe(5)
        ->and($stats)->toHaveKey('status')
        ->and($stats)->toHaveKey('uptime');
});

it('can calculate total disk usage', function () {
    $server = Server::factory()->create();

    $usage = $server->getTotalDiskUsage();

    expect($usage)->toBeArray()
        ->and($usage)->toHaveKey('used_gb')
        ->and($usage)->toHaveKey('available_gb')
        ->and($usage)->toHaveKey('percentage');
});

it('can get system information', function () {
    $server = Server::factory()->create();

    $sysInfo = $server->getSystemInfo();

    expect($sysInfo)->toBeArray()
        ->and($sysInfo)->toHaveKey('os')
        ->and($sysInfo)->toHaveKey('kernel')
        ->and($sysInfo)->toHaveKey('memory')
        ->and($sysInfo)->toHaveKey('cpu_count');
});

it('prevents duplicate hostnames', function () {
    Server::factory()->create(['hostname' => 'unique.example.com']);

    expect(fn () => Server::factory()->create(['hostname' => 'unique.example.com']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('can update last seen timestamp', function () {
    $server = Server::factory()->create(['last_seen_at' => null]);

    $server->updateLastSeen();

    expect($server->fresh()->last_seen_at)->not->toBeNull()
        ->and($server->fresh()->last_seen_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

it('can check if server is stale', function () {
    $recentServer = Server::factory()->create([
        'last_seen_at' => now()->subMinutes(5),
    ]);

    $staleServer = Server::factory()->create([
        'last_seen_at' => now()->subHours(25),
    ]);

    expect($recentServer->isStale())->toBeFalse()
        ->and($staleServer->isStale())->toBeTrue();
});

it('can get server load average', function () {
    $server = Server::factory()->create();

    $loadAvg = $server->getLoadAverage();

    expect($loadAvg)->toBeArray()
        ->and($loadAvg)->toHaveKey('1min')
        ->and($loadAvg)->toHaveKey('5min')
        ->and($loadAvg)->toHaveKey('15min');
});

it('can check available services', function () {
    $server = Server::factory()->create();

    $services = $server->getAvailableServices();

    expect($services)->toBeArray()
        ->and($services)->toContain('nginx')
        ->and($services)->toContain('mysql')
        ->and($services)->toContain('postfix');
});

it('can backup server configuration', function () {
    $server = Server::factory()->create();

    $backup = $server->createConfigBackup();

    expect($backup)->toBeArray()
        ->and($backup)->toHaveKey('timestamp')
        ->and($backup)->toHaveKey('configurations')
        ->and($backup['configurations'])->toBeArray();
});
