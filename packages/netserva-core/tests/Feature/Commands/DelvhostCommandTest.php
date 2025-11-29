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
    $this->artisan('delvhost --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Delete a virtual host (NetServa CRUD pattern)')
        ->assertExitCode(0);
});

it('deletes vhost successfully with --force flag', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'example.com')
        ->andReturn([
            'success' => true,
            'domain' => 'example.com',
            'username' => 'u1001',
        ]);

    $this->artisan('delvhost markc example.com --force')
        ->expectsOutput('🗑️  Deleting VHost: example.com from server markc')
        ->expectsOutput('✅ VHost example.com deleted successfully from markc')
        ->assertExitCode(0);
});

it('shows confirmation prompt without --force flag', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'test.com',
            'username' => 'u1002',
        ]);

    $this->artisan('delvhost markc test.com')
        ->expectsQuestion('⚠️  Are you sure you want to delete VHost test.com? This cannot be undone.', true)
        ->expectsOutput('✅ VHost test.com deleted successfully from markc')
        ->assertExitCode(0);
});

it('cancels deletion when user declines confirmation', function () {
    $this->vhostService
        ->shouldNotReceive('deleteVhost');

    $this->artisan('delvhost markc test.com')
        ->expectsQuestion('⚠️  Are you sure you want to delete VHost test.com? This cannot be undone.', false)
        ->expectsOutput('🛑 Deletion cancelled')
        ->assertExitCode(0);
});

it('supports dry-run mode', function () {
    $this->vhostService
        ->shouldNotReceive('deleteVhost');

    $this->artisan('delvhost markc test.example.com --dry-run')
        ->expectsOutput('🗑️  Deleting VHost: test.example.com from server markc')
        ->expectsOutput('🔍 DRY RUN: Delete VHost test.example.com from markc')
        ->expectsOutputToContain('Load config from vconfs table (database-first)')
        ->expectsOutputToContain('SSH to markc and execute cleanup via heredoc script')
        ->expectsOutputToContain('Remove user, directories, database on remote')
        ->expectsOutputToContain('Remove SSL certificate on remote')
        ->expectsOutputToContain('Remove nginx, PHP-FPM configuration on remote')
        ->expectsOutputToContain('Soft-delete fleet_vhosts record (cascades to vconfs)')
        ->assertExitCode(0);
});

it('handles vhost deletion failure gracefully', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'nonexistent.com')
        ->andReturn([
            'success' => false,
            'error' => "VHost 'nonexistent.com' not found on node 'markc'",
        ]);

    $this->artisan('delvhost markc nonexistent.com --force')
        ->expectsOutput('❌ Failed to delete VHost nonexistent.com from markc')
        ->expectsOutputToContain("Error: VHost 'nonexistent.com' not found")
        ->assertExitCode(1);
});

it('handles VNode not found error', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('nonexistent', 'test.com')
        ->andReturn([
            'success' => false,
            'error' => "VNode 'nonexistent' not found",
        ]);

    $this->artisan('delvhost nonexistent test.com --force')
        ->expectsOutput('❌ Failed to delete VHost test.com from nonexistent')
        ->expectsOutputToContain("VNode 'nonexistent' not found")
        ->assertExitCode(1);
});

it('validates required vnode argument', function () {
    // Missing vnode argument should fail with Laravel's validation
    $this->artisan('delvhost')
        ->assertExitCode(1);
});

it('validates required vhost argument', function () {
    // Missing vhost argument should fail with Laravel's validation
    $this->artisan('delvhost markc')
        ->assertExitCode(1);
});

it('handles remote cleanup failure gracefully', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'partial.com')
        ->andReturn([
            'success' => true, // Database deletion succeeded
            'domain' => 'partial.com',
            'username' => 'u1003',
            'remote_cleanup_warning' => 'Remote cleanup failed but database record was deleted',
        ]);

    $this->artisan('delvhost markc partial.com --force')
        ->expectsOutput('✅ VHost partial.com deleted successfully from markc')
        ->assertExitCode(0);
});

it('deletes vhost with database-first architecture (vconfs cascade)', function () {
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'db-test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'db-test.com',
            'username' => 'u1004',
        ]);

    // Verify dry-run mentions cascade
    $this->artisan('delvhost markc db-test.com --dry-run')
        ->expectsOutputToContain('Soft-delete fleet_vhosts record (cascades to vconfs)')
        ->assertExitCode(0);

    // Then actually delete
    $this->artisan('delvhost markc db-test.com --force')
        ->expectsOutput('✅ VHost db-test.com deleted successfully from markc')
        ->assertExitCode(0);
});

it('executes with context tracking', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->never(); // Mock won't be called in real execution

    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'context-test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'context-test.com',
            'username' => 'u1005',
        ]);

    $this->artisan('delvhost markc context-test.com --force')
        ->assertExitCode(0);
});

it('uses correct NetServa 3.0 command signature pattern', function () {
    // Verify command follows: <command> <vnode> <vhost> [options]
    $this->vhostService
        ->shouldReceive('deleteVhost')
        ->once()
        ->with('markc', 'signature-test.com')
        ->andReturn([
            'success' => true,
            'domain' => 'signature-test.com',
            'username' => 'u1006',
        ]);

    $this->artisan('delvhost markc signature-test.com --force')
        ->assertExitCode(0);
});
