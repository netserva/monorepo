<?php

use Illuminate\Support\Facades\File;
use NetServa\Core\Services\SshConfigService;

uses()
    ->group('unit', 'services', 'ssh-config-service', 'priority-1');

beforeEach(function () {
    $this->service = new SshConfigService;
});

it('can instantiate ssh config service', function () {
    expect($this->service)->toBeInstanceOf(SshConfigService::class);
});

it('creates required directories on instantiation', function () {
    $service = new SshConfigService;

    // Mock the file operations to test directory creation
    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('ensureDirectoryExists')->times(4);

    expect($service)->toBeInstanceOf(SshConfigService::class);
});

it('can get host config path', function () {
    $hostConfigPath = $this->service->getHostConfigPath('test-server');

    expect($hostConfigPath)->toContain('.ssh/hosts/test-server')
        ->and($hostConfigPath)->toBeString();
});

it('throws exception when host config not found', function () {
    File::shouldReceive('exists')->andReturn(false);

    expect(fn () => $this->service->loadHostConfig('nonexistent-host'))
        ->toThrow(\Exception::class, 'SSH host config not found: nonexistent-host');
});

it('can load existing host config', function () {
    $configContent = "Host test-server\n    HostName test.example.com\n    User admin\n    Port 22\n";

    File::shouldReceive('exists')->andReturn(true);
    File::shouldReceive('get')->andReturn($configContent);

    $config = $this->service->loadHostConfig('test-server');

    expect($config)->toBeArray()
        ->and($config)->toHaveKey('host')
        ->and($config['host'])->toBe('test-server');
});

it('can save host config', function () {
    $config = [
        'host' => 'test-server',
        'hostname' => 'test.example.com',
        'user' => 'admin',
        'port' => 22,
    ];

    File::shouldReceive('ensureDirectoryExists')->once();
    File::shouldReceive('put')->once()->andReturn(true);

    $result = $this->service->saveHostConfig('test-server', $config);

    expect($result)->toBeTrue();
});

it('can generate ssh config format', function () {
    $config = [
        'host' => 'test-server',
        'hostname' => 'test.example.com',
        'user' => 'admin',
        'port' => 22,
        'identityfile' => '~/.ssh/keys/test-server',
    ];

    $formatted = $this->service->formatConfig($config);

    expect($formatted)->toContain('Host test-server')
        ->and($formatted)->toContain('HostName test.example.com')
        ->and($formatted)->toContain('User admin')
        ->and($formatted)->toContain('Port 22')
        ->and($formatted)->toContain('IdentityFile ~/.ssh/keys/test-server');
});

it('can list all configured hosts', function () {
    File::shouldReceive('glob')->andReturn([
        '/home/user/.ssh/hosts/server1',
        '/home/user/.ssh/hosts/server2',
        '/home/user/.ssh/hosts/server3',
    ]);

    $hosts = $this->service->listHosts();

    expect($hosts)->toBeArray()
        ->and($hosts)->toHaveCount(3)
        ->and($hosts)->toContain('server1')
        ->and($hosts)->toContain('server2')
        ->and($hosts)->toContain('server3');
});

it('can validate ssh config', function () {
    $validConfig = [
        'host' => 'test-server',
        'hostname' => 'test.example.com',
        'user' => 'admin',
    ];

    $invalidConfig = [
        'hostname' => 'test.example.com',
        // Missing required 'host' field
    ];

    expect($this->service->validateConfig($validConfig))->toBeTrue();
    expect($this->service->validateConfig($invalidConfig))->toBeFalse();
});

it('can backup ssh configuration', function () {
    File::shouldReceive('copy')->once()->andReturn(true);
    File::shouldReceive('exists')->andReturn(true);

    $result = $this->service->backupConfig('test-server');

    expect($result)->toBeTrue();
});

it('can restore ssh configuration from backup', function () {
    File::shouldReceive('copy')->once()->andReturn(true);
    File::shouldReceive('exists')->andReturn(true);

    $result = $this->service->restoreConfig('test-server', '2024-01-01_12-00-00');

    expect($result)->toBeTrue();
});

it('handles ssh key generation', function () {
    $keyConfig = [
        'name' => 'test-key',
        'type' => 'ed25519',
        'comment' => 'test@example.com',
    ];

    // Mock process execution for ssh-keygen
    $this->mockProcessSuccess([
        'ssh-keygen -t ed25519 -f ~/.ssh/keys/test-key -C "test@example.com" -N ""' => 'Key generated successfully',
    ]);

    $result = $this->service->generateKey($keyConfig);

    expect($result)->toBeTrue();
});
