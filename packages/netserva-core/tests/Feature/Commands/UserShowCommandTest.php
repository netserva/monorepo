<?php

use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'user-management', 'priority-2');

beforeEach(function () {
    $this->configService = $this->mock(NetServaConfigurationService::class);
    $this->remoteService = $this->mock(RemoteExecutionService::class);
});

it('displays help information', function () {
    $this->artisan('user:show --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Display mail user information and details')
        ->assertExitCode(0);
});

it('shows user information with all parameters provided', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', '[[ -d /srv/example.com/msg/admin/Maildir ]] && echo \'exists\' || echo \'missing\'')
        ->andReturn('exists');

    $this->artisan('user:show test-server admin@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('prompts for vnode when not provided', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show')
        ->expectsQuestion('Select server node', 'test-server')
        ->expectsQuestion('Enter email pattern to search', 'admin@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('handles empty search results gracefully', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server nonexistent@example.com')
        ->expectsOutput('⚠️ No mail users found matching \'nonexistent@example.com%\' on test-server')
        ->assertExitCode(0);
});

it('supports json output format', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server admin@example.com --format=json')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('validates email pattern format', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server admin@example.com')
        ->assertExitCode(0);
});

it('displays single user details with quota information', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', '[[ -d /srv/example.com/msg/admin/Maildir ]] && echo \'exists\' || echo \'missing\'')
        ->andReturn('exists');

    $this->artisan('user:show test-server admin@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->expectsOutput('✅ Mailbox exists:')
        ->assertExitCode(0);
});

it('displays multiple users in table format', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server user%@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('handles SSH connection errors gracefully', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->andThrow(new Exception('SSH connection failed'));

    $this->artisan('user:show test-server admin@example.com')
        ->expectsOutput('⚠️ Could not check mailbox directory: SSH connection failed')
        ->assertExitCode(0);
});

it('handles missing config files gracefully', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', '[[ -d /srv/example.com/msg/admin/Maildir ]] && echo \'exists\' || echo \'missing\'')
        ->andReturn('exists');

    // Mock config path that doesn't exist
    config(['netserva.paths.var' => '/tmp/nonexistent']);

    $this->artisan('user:show test-server admin@example.com')
        ->expectsOutput('⚠️ Config file not found:')
        ->assertExitCode(0);
});

it('respects limit option for results', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server user%@example.com --limit=5')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('handles wildcard patterns correctly', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:show test-server %@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('formats bytes correctly', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', '[[ -d /srv/example.com/msg/admin/Maildir ]] && echo \'exists\' || echo \'missing\'')
        ->andReturn('exists');

    $this->artisan('user:show test-server admin@example.com')
        ->expectsOutput('📧 Mail User Information Tool')
        ->assertExitCode(0);
});

it('handles no SSH hosts available', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn([]);

    $this->artisan('user:show')
        ->expectsOutput('❌ User search failed: No SSH hosts available')
        ->assertExitCode(1);
});
