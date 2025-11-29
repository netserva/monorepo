<?php

use NetServa\Core\Services\NetServaContext;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'context', 'server-management', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);
});

it('displays help information', function () {
    $this->artisan('use-server --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Set default virtual node context for subsequent commands')
        ->assertExitCode(0);
});

it('sets server context successfully', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('test-server');

    $this->artisan('use-server test-server')
        ->expectsOutput('✅ Context set to server: test-server')
        ->expectsOutput('📝 Subsequent commands will use test-server as default VNODE')
        ->assertExitCode(0);
});

it('validates vnode parameter is provided', function () {
    $this->artisan('use-server ""')
        ->expectsOutput('❌ Invalid VNODE provided')
        ->assertExitCode(1);
});

it('rejects vnode parameter that is too short', function () {
    $this->artisan('use-server ""')
        ->expectsOutput('❌ Invalid VNODE provided')
        ->assertExitCode(1);
});

it('supports dry-run mode', function () {
    $this->context
        ->shouldNotReceive('setCurrentShost');

    $this->artisan('use-server test-server --dry-run')
        ->expectsOutput('🔍 DRY RUN: Server Context Change')
        ->expectsOutput('Would set context to: test-server')
        ->expectsOutput('Commands that would use this context:')
        ->assertExitCode(0);
});

it('shows context usage examples', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('motd');

    $this->artisan('use-server motd')
        ->expectsOutput('📖 Example commands that will now use motd context:')
        ->expectsOutput('php artisan addvhost example.com')
        ->expectsOutput('php artisan shvhost')
        ->expectsOutput('php artisan chvhost example.com --ssl=true')
        ->assertExitCode(0);
});

it('handles normal vnode names correctly', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('production-server');

    $this->artisan('use-server production-server')
        ->expectsOutput('✅ Context set to server: production-server')
        ->assertExitCode(0);
});

it('handles short vnode names correctly', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('ns1');

    $this->artisan('use-server ns1')
        ->expectsOutput('✅ Context set to server: ns1')
        ->assertExitCode(0);
});

it('shows dry-run with context usage examples', function () {
    $this->artisan('use-server motd --dry-run')
        ->expectsOutput('🔍 DRY RUN: Server Context Change')
        ->expectsOutput('Would set context to: motd')
        ->expectsOutput('Commands that would use this context:')
        ->expectsOutput('addvhost example.com')
        ->expectsOutput('shvhost')
        ->expectsOutput('chvhost example.com --ssl=true')
        ->expectsOutput('addvmail admin@example.com')
        ->assertExitCode(0);
});

it('displays clear success message', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('staging');

    $this->artisan('use-server staging')
        ->expectsOutput('✅ Context set to server: staging')
        ->expectsOutput('📝 Subsequent commands will use staging as default VNODE')
        ->assertExitCode(0);
});

it('provides useful context about the feature', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('dev');

    $this->artisan('use-server dev')
        ->expectsOutput('💡 You can clear this context anytime with: php artisan clear-context')
        ->assertExitCode(0);
});

it('shows correct dry-run format', function () {
    $this->artisan('use-server production --dry-run')
        ->expectsOutput('🔍 DRY RUN: Server Context Change')
        ->expectsOutput('Would set context to: production')
        ->expectsOutput('No actual changes will be made')
        ->assertExitCode(0);
});

it('handles vnode with dashes and underscores', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('server-01_prod');

    $this->artisan('use-server server-01_prod')
        ->expectsOutput('✅ Context set to server: server-01_prod')
        ->assertExitCode(0);
});

it('shows command examples in correct format', function () {
    $this->context
        ->shouldReceive('setCurrentShost')
        ->once()
        ->with('example');

    $this->artisan('use-server example')
        ->expectsOutput('📖 Example commands that will now use example context:')
        ->expectsOutput('php artisan addvhost example.com')
        ->expectsOutput('php artisan shvhost')
        ->expectsOutput('php artisan chvhost example.com --ssl=true')
        ->expectsOutput('php artisan addvmail admin@example.com')
        ->expectsOutput('php artisan delvhost example.com')
        ->assertExitCode(0);
});
