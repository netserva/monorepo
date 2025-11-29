<?php

use Illuminate\Support\Facades\Log;
use NetServa\Core\Services\RemoteExecutionService;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;

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
        ->assertExitCode(1);
});

it('fixes permissions for single vhost', function () {
    // Create VNode and VHost in database
    $vnode = FleetVnode::factory()->create(['name' => 'markc']);
    $vhost = FleetVhost::factory()->create([
        'vnode_id' => $vnode->id,
        'domain' => 'example.com',
        'environment_vars' => [
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/web/app/public',
            'MPATH' => '/srv/example.com/var/msg',
            'UUSER' => 'u1001',
            'U_UID' => '1001',
            'U_GID' => '1001',
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
                && str_contains($script, 'chown')
                && str_contains($script, 'Base permissions applied')
                && $asRoot === true
                && $args[0] === '/srv/example.com'  // UPATH
                && $args[1] === '/srv/example.com/web/app/public'  // WPATH
                && $args[3] === 'u1001'  // UUSER
                && $args[4] === '1001'  // U_UID
                && $args[6] === 'www-data'  // WUGID
                && $args[7] === 'example.com';  // domain
        })
        ->andReturn([
            'success' => true,
            'output' => "✓ Base permissions applied (750/640)\n✓ UPATH root: 755 (chroot-safe)\n✓ web/app: 02750 (setgid on dirs)\n✅ Permissions fixed successfully for example.com",
            'return_code' => 0,
        ]);

    $this->artisan('chperms markc example.com')
        ->expectsOutputToContain('Fixing permissions')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('Base permissions applied')
        ->assertExitCode(0);
});

it('shows dry run output', function () {
    // Create VNode and VHost in database
    $vnode = FleetVnode::factory()->create(['name' => 'markc']);
    $vhost = FleetVhost::factory()->create([
        'vnode_id' => $vnode->id,
        'domain' => 'example.com',
        'environment_vars' => [
            'UPATH' => '/srv/example.com',
            'WPATH' => '/srv/example.com/web/app/public',
            'MPATH' => '/srv/example.com/var/msg',
            'UUSER' => 'u1001',
            'U_UID' => '1001',
            'U_GID' => '1001',
            'WUGID' => 'www-data',
        ],
    ]);

    $this->artisan('chperms markc example.com --dry-run')
        ->expectsOutputToContain('🔍 DRY RUN: Fix permissions for example.com on markc')
        ->assertExitCode(0);
});

it('handles vhost not found', function () {
    // Create VNode but no VHost
    FleetVnode::factory()->create(['name' => 'markc']);

    $this->artisan('chperms markc nonexistent.com')
        ->expectsOutputToContain('❌ VHost nonexistent.com not found on markc')
        ->expectsOutputToContain('💡 Run: php artisan fleet:discover --vnode=markc')
        ->assertExitCode(1);
});
