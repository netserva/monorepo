<?php

use NetServa\Core\Models\VhostConfiguration;

uses()
    ->group('unit', 'models', 'vhost-configuration', 'priority-1');

it('can create a vhost configuration', function () {
    $config = VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'test-server',
        'configuration' => [
            'VHOST' => 'test.example.com',
            'VNODE' => 'test-server',
            'IP4_0' => '192.168.100.10',
            'DNAME' => 'test_example_com',
        ],
    ]);

    expect($config)->toBeInstanceOf(VhostConfiguration::class)
        ->and($config->vhost)->toBe('test.example.com')
        ->and($config->vnode)->toBe('test-server')
        ->and($config->configuration)->toBeArray()
        ->and($config->exists)->toBeTrue();
});

it('validates vhost format', function () {
    $config = VhostConfiguration::factory()->make([
        'vhost' => 'test.example.com',
    ]);

    expect($config->vhost)->toBeValidVhost();
});

it('casts configuration as array', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'ADMIN' => 'admin',
            'IP4_0' => '192.168.100.10',
        ],
    ]);

    expect($config->configuration)->toBeArray()
        ->and($config->configuration['VHOST'])->toBe('test.example.com')
        ->and($config->configuration['ADMIN'])->toBe('admin');
});

it('can get configuration value', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'IP4_0' => '192.168.100.10',
            'ADMIN' => 'admin@test.example.com',
        ],
    ]);

    expect($config->getConfigValue('VHOST'))->toBe('test.example.com')
        ->and($config->getConfigValue('IP4_0'))->toBe('192.168.100.10')
        ->and($config->getConfigValue('NONEXISTENT'))->toBeNull()
        ->and($config->getConfigValue('MISSING', 'default'))->toBe('default');
});

it('can set configuration value', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => ['VHOST' => 'test.example.com'],
    ]);

    $config->setConfigValue('IP4_0', '192.168.100.20');
    $config->setConfigValue('ADMIN', 'admin@test.example.com');

    expect($config->configuration['IP4_0'])->toBe('192.168.100.20')
        ->and($config->configuration['ADMIN'])->toBe('admin@test.example.com');
});

it('can merge configuration values', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'ADMIN' => 'old-admin@test.com',
        ],
    ]);

    $config->mergeConfiguration([
        'IP4_0' => '192.168.100.30',
        'ADMIN' => 'new-admin@test.example.com',
        'DTYPE' => 'sqlite',
    ]);

    expect($config->configuration['VHOST'])->toBe('test.example.com') // unchanged
        ->and($config->configuration['ADMIN'])->toBe('new-admin@test.example.com') // updated
        ->and($config->configuration['IP4_0'])->toBe('192.168.100.30') // new
        ->and($config->configuration['DTYPE'])->toBe('sqlite'); // new
});

it('can find configurations by vhost', function () {
    VhostConfiguration::factory()->create(['vhost' => 'example.com']);
    VhostConfiguration::factory()->create(['vhost' => 'test.org']);
    VhostConfiguration::factory()->create(['vhost' => 'example.com']);

    $configs = VhostConfiguration::byVhost('example.com')->get();

    expect($configs)->toHaveCount(2)
        ->and($configs->first()->vhost)->toBe('example.com');
});

it('can find configurations by vnode', function () {
    VhostConfiguration::factory()->create(['vnode' => 'server1']);
    VhostConfiguration::factory()->create(['vnode' => 'server2']);
    VhostConfiguration::factory()->create(['vnode' => 'server1']);

    $configs = VhostConfiguration::byVnode('server1')->get();

    expect($configs)->toHaveCount(2)
        ->and($configs->first()->vnode)->toBe('server1');
});

it('prevents duplicate vhost-vnode combinations', function () {
    VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'server1',
    ]);

    expect(fn () => VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'server1',
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('allows same vhost on different vnodes', function () {
    $config1 = VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'server1',
    ]);

    $config2 = VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'server2',
    ]);

    expect($config1->exists)->toBeTrue()
        ->and($config2->exists)->toBeTrue()
        ->and($config1->vhost)->toBe($config2->vhost)
        ->and($config1->vnode)->not->toBe($config2->vnode);
});

it('can export configuration as environment file', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'VNODE' => 'test-server',
            'IP4_0' => '192.168.100.10',
            'ADMIN' => 'admin@test.example.com',
        ],
    ]);

    $envContent = $config->toEnvironmentFile();

    expect($envContent)->toContain('VHOST="test.example.com"')
        ->and($envContent)->toContain('VNODE="test-server"')
        ->and($envContent)->toContain('IP4_0="192.168.100.10"')
        ->and($envContent)->toContain('ADMIN="admin@test.example.com"');
});

it('sorts configuration keys alphabetically in export', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'ZTEST' => 'last',
            'ATEST' => 'first',
            'MTEST' => 'middle',
        ],
    ]);

    $envContent = $config->toEnvironmentFile();
    $lines = explode("\n", trim($envContent));

    expect($lines[0])->toContain('ATEST')
        ->and($lines[1])->toContain('MTEST')
        ->and($lines[2])->toContain('ZTEST');
});

it('can validate required configuration keys', function () {
    $validConfig = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'VNODE' => 'test-server',
            'IP4_0' => '192.168.100.10',
            'ADMIN' => 'admin@test.example.com',
        ],
    ]);

    $invalidConfig = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            // Missing required keys
        ],
    ]);

    expect($validConfig->isValid())->toBeTrue()
        ->and($invalidConfig->isValid())->toBeFalse();
});

it('can get missing required configuration keys', function () {
    $config = VhostConfiguration::factory()->create([
        'configuration' => [
            'VHOST' => 'test.example.com',
            'ADMIN' => 'admin@test.example.com',
            // Missing VNODE, IP4_0
        ],
    ]);

    $missing = $config->getMissingRequiredKeys();

    expect($missing)->toContain('VNODE')
        ->and($missing)->toContain('IP4_0')
        ->and($missing)->not->toContain('VHOST');
});

it('can clone configuration to new vnode', function () {
    $original = VhostConfiguration::factory()->create([
        'vhost' => 'test.example.com',
        'vnode' => 'server1',
        'configuration' => [
            'VHOST' => 'test.example.com',
            'VNODE' => 'server1',
            'IP4_0' => '192.168.100.10',
        ],
    ]);

    $cloned = $original->cloneToVnode('server2', ['IP4_0' => '192.168.100.20']);

    expect($cloned)->toBeInstanceOf(VhostConfiguration::class)
        ->and($cloned->vhost)->toBe('test.example.com')
        ->and($cloned->vnode)->toBe('server2')
        ->and($cloned->configuration['VNODE'])->toBe('server2')
        ->and($cloned->configuration['IP4_0'])->toBe('192.168.100.20')
        ->and($cloned->id)->not->toBe($original->id);
});
