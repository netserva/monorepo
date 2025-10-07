<?php

use Illuminate\Support\Facades\Log;
use NetServa\Cli\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVHost;
use NetServa\Fleet\Models\FleetVNode;

/**
 * ChpermsCommand Tests
 *
 * Tests the database-first, heredoc-based SSH execution architecture
 */
beforeEach(function () {
    Log::shouldReceive('info', 'error', 'debug')->byDefault();
});

afterEach(function () {
    Mockery::close();
});

it('requires vhost argument when not using all flag', function () {
    $this->artisan('chperms markc')
        ->expectsOutputToContain('VHOST required')
        ->expectsOutputToContain('chperms <vnode> <vhost>')
        ->expectsOutputToContain('chperms <vnode> --all')
        ->assertExitCode(1);
});

it('fixes permissions for single vhost', function () {
    // Create VNode and VHost in database
    $vnode = FleetVNode::factory()->create(['name' => 'markc']);
    $vhost = FleetVHost::factory()->create([
        'vnode_id' => $vnode->id,
        'domain' => 'example.com',
        'environment_vars' => [
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/web',
            'MPATH' => '/srv/example.com/msg',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
        ],
    ]);

    // Mock RemoteExecutionService
    $remoteExecutionService = Mockery::mock(RemoteExecutionService::class);
    $this->app->instance(RemoteExecutionService::class, $remoteExecutionService);

    // Expect executeScript() to be called with heredoc script
    $remoteExecutionService->shouldReceive('executeScript')
        ->once()
        ->withArgs(function ($host, $script, $args, $asRoot) {
            return $host === 'markc'
                && str_contains($script, 'set -euo pipefail')
                && str_contains($script, 'chown -R')
                && $asRoot === true
                && $args[0] === '/srv/example.com'  // UPATH
                && $args[3] === 'u1001';  // UUSER
        })
        ->andReturn([
            'success' => true,
            'output' => "✓ Fixed user home: /srv/example.com\n✓ Fixed web directory: /srv/example.com/web\nPermissions fixed successfully",
            'return_code' => 0,
        ]);

    $this->artisan('chperms markc example.com')
        ->expectsOutputToContain('🔧 Fixing permissions for VHost')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('markc')
        ->expectsOutputToContain('✅ Permissions fixed for example.com on markc')
        ->expectsOutputToContain('✓ Fixed user home')
        ->expectsOutputToContain('✓ Fixed web directory')
        ->assertExitCode(0);
});

it('shows dry run output', function () {
    // Create VNode and VHost in database
    $vnode = FleetVNode::factory()->create(['name' => 'markc']);
    $vhost = FleetVHost::factory()->create([
        'vnode_id' => $vnode->id,
        'domain' => 'example.com',
        'environment_vars' => [
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/web',
            'MPATH' => '/srv/example.com/msg',
            'UUSER' => 'u1001',
            'WUGID' => 'www-data',
        ],
    ]);

    $this->artisan('chperms markc example.com --dry-run')
        ->expectsOutputToContain('🔍 DRY RUN: Fix permissions for example.com on markc')
        ->assertExitCode(0);
});

it('handles vhost not found', function () {
    // Create VNode but no VHost
    FleetVNode::factory()->create(['name' => 'markc']);

    $this->artisan('chperms markc nonexistent.com')
        ->expectsOutputToContain('❌ VHost nonexistent.com not found on markc')
        ->expectsOutputToContain('💡 Run: php artisan fleet:discover --vnode=markc')
        ->assertExitCode(1);
});
