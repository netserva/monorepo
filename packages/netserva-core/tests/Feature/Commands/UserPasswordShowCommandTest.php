<?php

use Illuminate\Support\Facades\File;
use NetServa\Core\Services\NetServaConfigurationService;
use NetServa\Core\Services\RemoteExecutionService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'user-management', 'password', 'priority-2');

beforeEach(function () {
    $this->configService = $this->mock(NetServaConfigurationService::class);
    $this->remoteService = $this->mock(RemoteExecutionService::class);
});

it('displays help information', function () {
    $this->artisan('user:password:show --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Display user password from local configuration file')
        ->assertExitCode(0);
});

it('shows password with all parameters provided', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: SecurePassword123\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('🔑 User Password Display Tool')
        ->expectsOutput('🔑 Password for admin@example.com:')
        ->expectsOutput('SecurePassword123')
        ->expectsOutput('📊 Password Analysis:')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('prompts for missing parameters', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: SecurePassword123\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show')
        ->expectsQuestion('Select server node', 'test-server')
        ->expectsQuestion('Enter user email address', 'admin@example.com')
        ->expectsOutput('🔑 Password for admin@example.com:')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('handles password not found', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file without the user
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: other@example.com\nPassword: OtherPassword123\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('❌ Password for \'admin@example.com\' not found')
        ->assertExitCode(1);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('handles missing config file', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    config(['netserva.paths.var' => '/tmp/nonexistent']);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('❌ Password retrieval failed: Config file not found: /tmp/nonexistent/test-server/example.com.conf')
        ->assertExitCode(1);
});

it('supports masked password output', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: SecurePassword123\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com --mask')
        ->expectsOutput('Se*************23')
        ->expectsOutput('Password is masked for security')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('analyzes password strength correctly', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file with a strong password
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: VerySecurePassword123!\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('📊 Password Analysis:')
        ->expectsOutput('Length: 22 characters ✅')
        ->expectsOutput('Uppercase: ✅')
        ->expectsOutput('Lowercase: ✅')
        ->expectsOutput('Numbers: ✅')
        ->expectsOutput('Special chars: ✅')
        ->expectsOutput('Strength: 🟢 Very Strong')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('warns about weak passwords', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file with a weak password
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: weak\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('Length: 4 characters ❌')
        ->expectsOutput('Strength: 🔴 Weak')
        ->expectsOutput('⚠️ Consider updating to a stronger password')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('validates email format', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    $this->artisan('user:password:show test-server invalid-email')
        ->expectsOutput('❌ Password retrieval failed:')
        ->assertExitCode(1);
});

it('handles multiple users in config file', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file with multiple users
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\n";
    $configContent .= "Username: admin@example.com\nPassword: AdminPassword123\n\n";
    $configContent .= "Username: user@example.com\nPassword: UserPassword456\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server user@example.com')
        ->expectsOutput('UserPassword456')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('handles config file with sections', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file with multiple sections
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Database\n========\nhost=localhost\n\n";
    $configContent .= "Mail\n=========\n\n";
    $configContent .= "Username: admin@example.com\nPassword: AdminPassword123\n\n";
    $configContent .= "Web\n===\nserver=nginx\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com')
        ->expectsOutput('AdminPassword123')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});

it('handles no SSH hosts available', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn([]);

    $this->artisan('user:password:show')
        ->expectsOutput('❌ Password retrieval failed: No SSH hosts available')
        ->assertExitCode(1);
});

it('masks short passwords correctly', function () {
    $this->remoteService
        ->shouldReceive('getAvailableHosts')
        ->andReturn(['test-server' => 'test-server']);

    // Create a temporary config file with a short password
    $tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    mkdir($tempDir.'/test-server', 0755, true);

    $configContent = "Mail\n=========\n\nUsername: admin@example.com\nPassword: abc\n\n";
    file_put_contents($tempDir.'/test-server/example.com.conf', $configContent);

    config(['netserva.paths.var' => $tempDir]);

    $this->artisan('user:password:show test-server admin@example.com --mask')
        ->expectsOutput('***')
        ->assertExitCode(0);

    // Cleanup
    unlink($tempDir.'/test-server/example.com.conf');
    rmdir($tempDir.'/test-server');
    rmdir($tempDir);
});
