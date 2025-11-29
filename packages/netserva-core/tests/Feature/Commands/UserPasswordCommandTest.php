<?php

use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'user-management', 'password', 'priority-2');

beforeEach(function () {
    $this->configService = $this->mock(NetServaConfigurationService::class);
    $this->remoteService = $this->mock(RemoteExecutionService::class);
});

it('displays help information', function () {
    $this->artisan('user:password --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Change user password for mail accounts')
        ->assertExitCode(0);
});

it('changes password with all parameters provided', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', 'doveadm pw -s SHA512-CRYPT -p \'SecurePassword123\'')
        ->andReturn('{SHA512-CRYPT}$6$abcdef$hashedpassword');

    $this->artisan('user:password test-server admin@example.com SecurePassword123')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('✅ Password changed successfully')
        ->assertExitCode(0);
});

it('prompts for missing parameters', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', 'doveadm pw -s SHA512-CRYPT -p \'SecurePassword123\'')
        ->andReturn('{SHA512-CRYPT}$6$abcdef$hashedpassword');

    $this->artisan('user:password')
        ->expectsQuestion('Select server node', 'test-server')
        ->expectsQuestion('Enter user email address', 'admin@example.com')
        ->expectsQuestion('Enter new password', 'SecurePassword123')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('✅ Password changed successfully')
        ->assertExitCode(0);
});

it('generates secure password when requested', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->andReturn('{SHA512-CRYPT}$6$abcdef$hashedpassword');

    $this->artisan('user:password test-server admin@example.com --generate')
        ->expectsOutput('🎲 Generated secure password:')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('✅ Password changed successfully')
        ->assertExitCode(0);
});

it('validates password strength requirements', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com weak')
        ->expectsOutput('❌ Password must be at least 12 characters long')
        ->assertExitCode(1);
});

it('validates email format', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:password test-server invalid-email SecurePassword123')
        ->expectsOutput('❌ Password change failed:')
        ->assertExitCode(1);
});

it('handles email that does not exist', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn([]);

    $this->artisan('user:password test-server nonexistent@example.com SecurePassword123')
        ->expectsOutput('❌ Email \'nonexistent@example.com\' does not exist on test-server')
        ->assertExitCode(1);
});

it('supports dry-run mode', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com SecurePassword123 --dry-run')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('🔍 DRY RUN: Password Change Operations')
        ->expectsOutput('Would perform the following operations:')
        ->assertExitCode(0);
});

it('allows password change cancellation', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com SecurePassword123')
        ->expectsConfirmation('Proceed with password change?', 'no')
        ->expectsOutput('Password change cancelled')
        ->assertExitCode(0);
});

it('validates password has uppercase letters', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com lowercase123')
        ->expectsOutput('❌ Password must contain uppercase, lowercase, and numeric characters')
        ->assertExitCode(1);
});

it('validates password has lowercase letters', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com UPPERCASE123')
        ->expectsOutput('❌ Password must contain uppercase, lowercase, and numeric characters')
        ->assertExitCode(1);
});

it('validates password has numbers', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->artisan('user:password test-server admin@example.com NoNumbersHere')
        ->expectsOutput('❌ Password must contain uppercase, lowercase, and numeric characters')
        ->assertExitCode(1);
});

it('handles SSH connection errors gracefully', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->andThrow(new Exception('SSH connection failed'));

    $this->artisan('user:password test-server admin@example.com SecurePassword123')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('❌ Password change failed: Failed to generate password hash: SSH connection failed')
        ->assertExitCode(1);
});

it('handles no SSH hosts available', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn([]);

    $this->artisan('user:password')
        ->expectsOutput('❌ Password change failed: No SSH hosts available')
        ->assertExitCode(1);
});

it('creates config file entry if user not found in existing file', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->configService
        ->shouldReceive('getVhostConfiguration')
        ->with('test-server', 'example.com')
        ->andReturn(['domain' => 'example.com']);

    $this->remoteService
        ->shouldReceive('executeCommand')
        ->with('test-server', 'doveadm pw -s SHA512-CRYPT -p \'SecurePassword123\'')
        ->andReturn('{SHA512-CRYPT}$6$abcdef$hashedpassword');

    // Create a temporary config file without the user
    $tempConfigPath = tempnam(sys_get_temp_dir(), 'test_config');
    file_put_contents($tempConfigPath, "Mail\n=========\n\nUsername: other@example.com\nPassword: otherpass\n");

    config(['netserva.paths.var' => dirname($tempConfigPath)]);
    rename($tempConfigPath, dirname($tempConfigPath).'/test-server/example.com.conf');

    $this->artisan('user:password test-server admin@example.com SecurePassword123')
        ->expectsConfirmation('Proceed with password change?', 'yes')
        ->expectsOutput('📝 Added new user entry to config')
        ->expectsOutput('✅ Password changed successfully')
        ->assertExitCode(0);

    // Cleanup
    @unlink(dirname($tempConfigPath).'/test-server/example.com.conf');
    @rmdir(dirname($tempConfigPath).'/test-server');
});
