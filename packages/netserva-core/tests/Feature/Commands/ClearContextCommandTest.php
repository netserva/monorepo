<?php

use NetServa\Core\Services\NetServaContext;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'context', 'server-management', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);
});

it('displays help information', function () {
    $this->artisan('clear-context --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Clear default SSH host context')
        ->assertExitCode(0);
});

it('clears context successfully when context is set', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('test-server');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Cleared server context (was: test-server)')
        ->expectsOutput('📝 Commands will now require explicit --shost parameter')
        ->assertExitCode(0);
});

it('handles clearing context when no context is set', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn(null);

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Context was already clear')
        ->expectsOutput('📝 Commands require explicit --shost parameter')
        ->assertExitCode(0);
});

it('supports dry-run mode with existing context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('production-server');

    $this->context
        ->shouldNotReceive('clearContext');

    $this->artisan('clear-context --dry-run')
        ->expectsOutput('🔍 DRY RUN: Clear Server Context')
        ->expectsOutput('Would clear current context: production-server')
        ->expectsOutput('After clearing, commands will require explicit --shost')
        ->assertExitCode(0);
});

it('supports dry-run mode with no existing context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn(null);

    $this->context
        ->shouldNotReceive('clearContext');

    $this->artisan('clear-context --dry-run')
        ->expectsOutput('🔍 DRY RUN: Clear Server Context')
        ->expectsOutput('Context is already clear')
        ->assertExitCode(0);
});

it('provides helpful guidance after clearing context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('staging');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('💡 Use "php artisan use-server <server>" to set context again')
        ->assertExitCode(0);
});

it('shows correct message format when context was set', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('motd');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Cleared server context (was: motd)')
        ->expectsOutput('📝 Commands will now require explicit --shost parameter')
        ->assertExitCode(0);
});

it('shows correct message format when context was already clear', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Context was already clear')
        ->expectsOutput('📝 Commands require explicit --shost parameter')
        ->assertExitCode(0);
});

it('handles null context correctly', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn(null);

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Context was already clear')
        ->assertExitCode(0);
});

it('provides command usage examples after clearing', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('example-server');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('📖 Example commands now require explicit --shost:')
        ->expectsOutput('php artisan addvhost example.com --shost=server')
        ->expectsOutput('php artisan shvhost --shost=server')
        ->expectsOutput('php artisan chvhost example.com --shost=server')
        ->assertExitCode(0);
});

it('shows proper dry-run format with context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('dev-server');

    $this->artisan('clear-context --dry-run')
        ->expectsOutput('🔍 DRY RUN: Clear Server Context')
        ->expectsOutput('Would clear current context: dev-server')
        ->expectsOutput('After clearing, commands will require explicit --shost')
        ->expectsOutput('No actual changes will be made')
        ->assertExitCode(0);
});

it('shows proper dry-run format without context', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn(null);

    $this->artisan('clear-context --dry-run')
        ->expectsOutput('🔍 DRY RUN: Clear Server Context')
        ->expectsOutput('Context is already clear')
        ->expectsOutput('No changes needed')
        ->assertExitCode(0);
});

it('handles empty string context like null', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('✅ Context was already clear')
        ->assertExitCode(0);
});

it('provides guidance about use-server command', function () {
    $this->context
        ->shouldReceive('getCurrentShost')
        ->once()
        ->andReturn('prod');

    $this->context
        ->shouldReceive('clearContext')
        ->once();

    $this->artisan('clear-context')
        ->expectsOutput('💡 Use "php artisan use-server <server>" to set context again')
        ->assertExitCode(0);
});
