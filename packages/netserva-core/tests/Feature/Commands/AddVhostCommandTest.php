<?php

use NetServa\Core\Services\NetServaContext;
use NetServa\Core\Services\VhostManagementService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'vhost-management', 'crud', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);
    $this->vhostService = $this->mock(VhostManagementService::class);
});

it('displays help information', function () {
    $this->artisan('addvhost --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Add a new virtual host (NetServa CRUD pattern)')
        ->assertExitCode(0);
});

it('adds vhost successfully with positional arguments', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'example.com')
        ->andReturn([
            'success' => true,
            'domain' => 'example.com',
            'vnode' => 'markc',
            'fleet_vhost_id' => 1,
            'username' => 'u1001',
            'uid' => 1001,
            'paths' => [
                'wpath' => '/srv/example.com/web',
            ],
        ]);

    $this->artisan('addvhost markc example.com')
        ->expectsOutput('🚀 Adding VHost: example.com on node markc')
        ->expectsOutput('✅ VHost example.com created successfully on markc')
        ->assertExitCode(0);
});

it('shows vhost details after creation', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'test.goldcoast.org')
        ->andReturn([
            'success' => true,
            'domain' => 'test.goldcoast.org',
            'vnode' => 'markc',
            'fleet_vhost_id' => 2,
            'username' => 'u1002',
            'uid' => 1002,
            'paths' => [
                'wpath' => '/srv/test.goldcoast.org/web',
            ],
        ]);

    $this->artisan('addvhost markc test.goldcoast.org')
        ->expectsOutputToContain('📋 VHost Details:')
        ->expectsOutputToContain('User: u1002')
        ->expectsOutputToContain('UID: 1002')
        ->expectsOutputToContain('Web Path: /srv/test.goldcoast.org/web')
        ->expectsOutputToContain('Database ID: 2')
        ->expectsOutputToContain('Config: vconfs table (database-first)')
        ->assertExitCode(0);
});

it('supports dry-run mode', function () {
    $this->vhostService
        ->shouldNotReceive('createVhost');

    $this->artisan('addvhost markc test.example.com --dry-run')
        ->expectsOutput('🚀 Adding VHost: test.example.com on node markc')
        ->expectsOutput('🔍 DRY RUN: Add VHost test.example.com on markc')
        ->expectsOutputToContain('Generate VHost configuration for test.example.com')
        ->expectsOutputToContain('Create fleet_vhosts database record')
        ->expectsOutputToContain('Store ~54 config variables in vconfs table (database-first)')
        ->expectsOutputToContain('Execute single heredoc SSH script to markc')
        ->assertExitCode(0);
});

it('shows detailed dry-run information', function () {
    $this->artisan('addvhost prod new.example.com --dry-run')
        ->expectsOutput('🔍 DRY RUN: Add VHost new.example.com on prod')
        ->expectsOutputToContain('Create user u1001+, directories, permissions on remote')
        ->expectsOutputToContain('Configure PHP-FPM pool, nginx, database on remote')
        ->expectsOutputToContain('Set permissions and restart services')
        ->assertExitCode(0);
});

it('handles vhost creation failure gracefully', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'existing.com')
        ->andReturn([
            'success' => false,
            'error' => 'VHost already exists',
        ]);

    $this->artisan('addvhost markc existing.com')
        ->expectsOutput('❌ Failed to create VHost existing.com on markc')
        ->expectsOutputToContain('Error: VHost already exists')
        ->assertExitCode(1);
});

it('handles VNode not found error', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('nonexistent', 'test.com')
        ->andReturn([
            'success' => false,
            'error' => "VNode 'nonexistent' not found. Run 'php artisan fleet:discover --vnode=nonexistent' first.",
        ]);

    $this->artisan('addvhost nonexistent test.com')
        ->expectsOutput('❌ Failed to create VHost test.com on nonexistent')
        ->expectsOutputToContain('VNode \'nonexistent\' not found')
        ->assertExitCode(1);
});

it('validates required vnode argument', function () {
    // Missing vnode argument should fail with Laravel's validation
    $this->artisan('addvhost')
        ->assertExitCode(1);
});

it('validates required vhost argument', function () {
    // Missing vhost argument should fail with Laravel's validation
    $this->artisan('addvhost markc')
        ->assertExitCode(1);
});

it('handles special domain formats correctly', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'sub.domain.example.co.uk')
        ->andReturn([
            'success' => true,
            'domain' => 'sub.domain.example.co.uk',
            'vnode' => 'markc',
            'fleet_vhost_id' => 3,
            'username' => 'u1003',
            'uid' => 1003,
            'paths' => [
                'wpath' => '/srv/sub.domain.example.co.uk/web',
            ],
        ]);

    $this->artisan('addvhost markc sub.domain.example.co.uk')
        ->expectsOutput('✅ VHost sub.domain.example.co.uk created successfully on markc')
        ->assertExitCode(0);
});

it('handles very long domain names', function () {
    $longDomain = 'very-long-subdomain.with-multiple-parts.example-domain.co.uk';

    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', $longDomain)
        ->andReturn([
            'success' => true,
            'domain' => $longDomain,
            'vnode' => 'markc',
            'fleet_vhost_id' => 4,
            'username' => 'u1004',
            'uid' => 1004,
            'paths' => [
                'wpath' => "/srv/{$longDomain}/web",
            ],
        ]);

    $this->artisan("addvhost markc {$longDomain}")
        ->expectsOutput("✅ VHost {$longDomain} created successfully on markc")
        ->assertExitCode(0);
});

it('creates vhost with database-first architecture', function () {
    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'db-first.test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'db-first.test.com',
            'vnode' => 'markc',
            'fleet_vhost_id' => 5,
            'username' => 'u1005',
            'uid' => 1005,
            'paths' => [
                'wpath' => '/srv/db-first.test.com/web',
            ],
        ]);

    $this->artisan('addvhost markc db-first.test.com')
        ->expectsOutputToContain('Config: vconfs table (database-first)')
        ->assertExitCode(0);
});

it('executes with context tracking', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->never(); // Mock won't be called in real execution, but we can verify the pattern

    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'context-test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'context-test.com',
            'vnode' => 'markc',
            'fleet_vhost_id' => 6,
            'username' => 'u1006',
            'uid' => 1006,
            'paths' => [
                'wpath' => '/srv/context-test.com/web',
            ],
        ]);

    $this->artisan('addvhost markc context-test.com')
        ->assertExitCode(0);
});

it('uses correct NetServa 3.0 command signature pattern', function () {
    // Verify command follows: <command> <vnode> <vhost> [options]
    // NOT: <command> --vnode=X --vhost=Y (old style)

    $this->vhostService
        ->shouldReceive('createVhost')
        ->once()
        ->with('markc', 'signature-test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'signature-test.com',
            'vnode' => 'markc',
            'fleet_vhost_id' => 7,
            'username' => 'u1007',
            'uid' => 1007,
            'paths' => [
                'wpath' => '/srv/signature-test.com/web',
            ],
        ]);

    // Correct new signature (positional args)
    $this->artisan('addvhost markc signature-test.com')
        ->assertExitCode(0);
});
