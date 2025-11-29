<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use NetServa\Core\Models\VhostConfiguration;
use NetServa\Core\Services\NetServaConfigurationService;

uses()
    ->group('feature', 'commands', 'netserva-cli', 'migration', 'vhost-configs', 'priority-1');

beforeEach(function () {
    $this->configService = $this->mock(NetServaConfigurationService::class);

    // Create temporary directory structure for testing
    $this->tempDir = sys_get_temp_dir().'/netserva_test_'.uniqid();
    $this->tempVarDir = $this->tempDir.'/var';

    // Create test vnode directories
    mkdir($this->tempVarDir.'/ns1', 0755, true);
    mkdir($this->tempVarDir.'/motd', 0755, true);
    mkdir($this->tempVarDir.'/mgo', 0755, true);

    // Override config to use test directory
    config(['netserva-cli.paths.nsvar' => $this->tempVarDir]);
});

afterEach(function () {
    // Clean up test directory
    if (File::exists($this->tempDir)) {
        File::deleteDirectory($this->tempDir);
    }
});

it('displays help information', function () {
    $this->artisan('migrate:vhost-configs --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Migrate vhost configuration files from var/ directory to database')
        ->assertExitCode(0);
});

it('checks prerequisites and fails if var directory does not exist', function () {
    config(['netserva-cli.paths.nsvar' => '/nonexistent/path']);

    $this->artisan('migrate:vhost-configs')
        ->expectsOutput('❌ NetServa var directory not found or not configured')
        ->assertExitCode(1);
});

it('reports no configurations found when var directory is empty', function () {
    $this->artisan('migrate:vhost-configs')
        ->expectsOutput('⚠️ No vhost configuration files found to migrate')
        ->assertExitCode(0);
});

it('analyzes and displays migration plan for vhost configurations', function () {
    // Create test configuration files
    $this->createTestVhostConfig('ns1', 'example.com');
    $this->createTestVhostConfig('motd', 'motd.com');
    $this->createTestVhostConfig('mgo', 'goldcoast.org');

    $this->artisan('migrate:vhost-configs --dry-run')
        ->expectsOutput('🔍 Analyzing vhost configurations...')
        ->expectsOutput('📋 Migration Plan:')
        ->expectsOutput('🔍 DRY RUN: Vhost Configuration Migration')
        ->expectsOutput('Would migrate: ns1/example.com')
        ->expectsOutput('Would migrate: motd/motd.com')
        ->expectsOutput('Would migrate: mgo/goldcoast.org')
        ->assertExitCode(0);
});

it('performs actual migration of vhost configurations', function () {
    // Create test configuration files
    $this->createTestVhostConfig('ns1', 'example.com');
    $this->createTestVhostConfig('motd', 'motd.com');

    $this->artisan('migrate:vhost-configs --force')
        ->expectsOutput('🚀 Performing vhost configuration migration...')
        ->expectsOutput('📊 Migration Summary:')
        ->expectsOutput('🎉 Migration completed successfully!')
        ->assertExitCode(0);

    // Verify configurations were created in database
    expect(VhostConfiguration::count())->toBe(2);

    $config1 = VhostConfiguration::where('vnode', 'ns1')->where('vhost', 'example.com')->first();
    expect($config1)->not->toBeNull();
    expect($config1->getVariable('VHOST'))->toBe('example.com');
    expect($config1->getVariable('VNODE'))->toBe('ns1');
    expect($config1->getVariable('ADMIN'))->toBe('sysadm');

    $config2 = VhostConfiguration::where('vnode', 'motd')->where('vhost', 'motd.com')->first();
    expect($config2)->not->toBeNull();
    expect($config2->getVariable('VHOST'))->toBe('motd.com');
    expect($config2->getVariable('VNODE'))->toBe('motd');
});

it('skips configurations that already exist in database', function () {
    // Create test configuration file
    $this->createTestVhostConfig('ns1', 'example.com');

    // Create existing database record
    VhostConfiguration::create([
        'vnode' => 'ns1',
        'vhost' => 'example.com',
        'filepath' => $this->tempVarDir.'/ns1/example.com',
        'variables' => ['VHOST' => 'example.com', 'VNODE' => 'ns1', 'ADMIN' => 'sysadm'],
        'migrated_at' => now(),
        'checksum' => 'existing',
    ]);

    $this->artisan('migrate:vhost-configs --force')
        ->expectsOutput('📊 Migration Summary:')
        ->assertExitCode(0);

    // Should still have only 1 record
    expect(VhostConfiguration::count())->toBe(1);
});

it('handles invalid configuration files gracefully', function () {
    // Create invalid configuration file (missing required variables)
    File::put($this->tempVarDir.'/ns1/invalid.com', "# Invalid config\nSOME_VAR='value'\n");

    $this->artisan('migrate:vhost-configs --dry-run')
        ->expectsOutput('📊 Total: 3 vnodes, 0 vhosts')
        ->assertExitCode(0);
});

it('validates required variables in configuration files', function () {
    // Create configuration file missing required variable
    $configContent = "VHOST='example.com'\nVNODE='ns1'\n# Missing ADMIN";
    File::put($this->tempVarDir.'/ns1/example.com', $configContent);

    $this->artisan('migrate:vhost-configs --force')
        ->expectsOutput('❌ Failed to migrate ns1/example.com')
        ->assertExitCode(0);

    expect(VhostConfiguration::count())->toBe(0);
});

it('creates backup when backup option is specified', function () {
    $this->createTestVhostConfig('ns1', 'example.com');

    $backupDir = dirname($this->tempVarDir).'/bak';
    config(['netserva-cli.paths.nsbak' => $backupDir]);

    $this->artisan('migrate:vhost-configs --backup --force')
        ->expectsOutput('💾 Creating backup of var directory...')
        ->expectsOutput('✅ Backup created:')
        ->assertExitCode(0);

    // Check backup was created
    expect(File::exists($backupDir))->toBeTrue();
    $backupDirs = File::directories($backupDir);
    expect(count($backupDirs))->toBeGreaterThan(0);
});

it('handles database connection errors gracefully', function () {
    // Mock database connection failure
    DB::shouldReceive('connection->getPdo')
        ->andThrow(new Exception('Database connection failed'));

    $this->artisan('migrate:vhost-configs')
        ->expectsOutput('❌ Database connection failed: Database connection failed')
        ->assertExitCode(1);
});

it('shows proper migration statistics', function () {
    // Create multiple configurations across different vnodes
    $this->createTestVhostConfig('ns1', 'example.com');
    $this->createTestVhostConfig('ns1', 'test.com');
    $this->createTestVhostConfig('motd', 'motd.com');
    $this->createTestVhostConfig('mgo', 'goldcoast.org');

    $this->artisan('migrate:vhost-configs --force')
        ->expectsOutput('📊 Total: 3 vnodes, 4 vhosts')
        ->expectsOutput('📊 Migration Summary:')
        ->assertExitCode(0);

    expect(VhostConfiguration::count())->toBe(4);
});

it('parses environment variables correctly from config files', function () {
    // Create configuration with various variable formats
    $configContent = <<<'EOL'
# NetServa vhost configuration
VHOST='example.com'
VNODE="ns1"
ADMIN=sysadm
AMAIL="admin@example.com"
APASS='SecurePassword123'
A_UID=1001
VPATH="/srv"
EOL;

    File::put($this->tempVarDir.'/ns1/example.com', $configContent);

    $this->artisan('migrate:vhost-configs --force')
        ->assertExitCode(0);

    $config = VhostConfiguration::where('vnode', 'ns1')->where('vhost', 'example.com')->first();
    expect($config->getVariable('VHOST'))->toBe('example.com');
    expect($config->getVariable('VNODE'))->toBe('ns1');
    expect($config->getVariable('ADMIN'))->toBe('sysadm');
    expect($config->getVariable('AMAIL'))->toBe('admin@example.com');
    expect($config->getVariable('APASS'))->toBe('SecurePassword123');
    expect($config->getVariable('A_UID'))->toBe('1001');
    expect($config->getVariable('VPATH'))->toBe('/srv');
});

it('prompts for confirmation when not using force or dry-run', function () {
    $this->createTestVhostConfig('ns1', 'example.com');

    $this->artisan('migrate:vhost-configs')
        ->expectsConfirmation('Proceed with vhost configuration migration?', 'no')
        ->expectsOutput('Migration cancelled')
        ->assertExitCode(0);

    expect(VhostConfiguration::count())->toBe(0);
});

it('shows detailed vhost information in migration plan', function () {
    $this->createTestVhostConfig('ns1', 'example.com', [
        'ADMIN' => 'testadmin',
        'AMAIL' => 'admin@example.com',
    ]);

    $this->artisan('migrate:vhost-configs --dry-run')
        ->expectsOutput('Sample: VHOST=example.com, ADMIN=testadmin')
        ->assertExitCode(0);
});

it('handles files with different extensions correctly', function () {
    // Create files with different extensions - should only process valid domain-like names
    File::put($this->tempVarDir.'/ns1/example.com', $this->getValidConfigContent('example.com', 'ns1'));
    File::put($this->tempVarDir.'/ns1/README.txt', 'This is a readme file');
    File::put($this->tempVarDir.'/ns1/config.json', '{"some": "json"}');

    $this->artisan('migrate:vhost-configs --dry-run')
        ->expectsOutput('📊 Total: 3 vnodes, 1 vhosts')
        ->assertExitCode(0);
});

// Helper method to create test vhost configuration
function createTestVhostConfig(string $vnode, string $vhost, array $extraVars = []): void
{
    $configContent = $this->getValidConfigContent($vhost, $vnode, $extraVars);
    File::put($this->tempVarDir."/{$vnode}/{$vhost}", $configContent);
}

// Helper method to generate valid configuration content
function getValidConfigContent(string $vhost, string $vnode, array $extraVars = []): string
{
    $defaultVars = [
        'VHOST' => $vhost,
        'VNODE' => $vnode,
        'ADMIN' => 'sysadm',
        'VPATH' => '/srv',
        'AMAIL' => "admin@{$vhost}",
        'UPATH' => "/srv/{$vhost}",
        'WPATH' => "/srv/{$vhost}/web",
        'MPATH' => "/srv/{$vhost}/msg",
    ];

    $vars = array_merge($defaultVars, $extraVars);
    $content = "# NetServa vhost configuration for {$vhost}\n";

    foreach ($vars as $name => $value) {
        $content .= "{$name}='{$value}'\n";
    }

    return $content;
}
