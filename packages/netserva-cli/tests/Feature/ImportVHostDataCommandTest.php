<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses(RefreshDatabase::class);

afterEach(function () {
    // Clean up test files
    if (File::exists('/tmp/test_var')) {
        File::deleteDirectory('/tmp/test_var');
    }
});

it('can import vhost data from var files', function () {
    // Create test var directory structure
    createTestVarStructure();

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
        '--dry-run' => false,
    ])
        ->expectsOutput('🚀 NetServa VHost Data Import - Database Migration Tool')
        ->expectsOutput('✅ Database import completed successfully!')
        ->assertExitCode(0);

    // Verify database records were created
    expect(FleetVsite::count())->toBe(1);
    expect(FleetVnode::count())->toBe(1);
    expect(FleetVhost::count())->toBe(2);

    // Check specific VHost data
    $vhost = FleetVhost::where('domain', 'example.com')->first();
    expect($vhost)->not->toBeNull();
    expect($vhost->vnode->name)->toBe('web01');
    expect($vhost->vnode->vsite->name)->toBe('local-test');
    expect($vhost->environment_vars)->toHaveKey('VHOST', 'example.com');
    expect($vhost->environment_vars)->toHaveKey('UUSER', 'u1001');
    expect($vhost->environment_vars)->toHaveKey('WPATH', '/srv/example.com/web');
});

it('performs dry run without making changes', function () {
    createTestVarStructure();

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
        '--dry-run' => true,
    ])
        ->expectsOutput('🔍 DRY RUN MODE - No changes will be made')
        ->assertExitCode(0);

    // Verify no database records were created
    expect(FleetVsite::count())->toBe(0);
    expect(FleetVnode::count())->toBe(0);
    expect(FleetVhost::count())->toBe(0);
});

it('can filter by vsite', function () {
    createTestVarStructure();

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
        '--vsite' => 'local-test',
    ])
        ->assertExitCode(0);

    expect(FleetVsite::where('name', 'local-test')->count())->toBe(1);
    expect(FleetVhost::count())->toBe(2);
});

it('can filter by vnode', function () {
    createTestVarStructure();

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
        '--vnode' => 'web01',
    ])
        ->assertExitCode(0);

    expect(FleetVnode::where('name', 'web01')->count())->toBe(1);
    expect(FleetVhost::count())->toBe(2);
});

it('skips existing vhosts without force flag', function () {
    createTestVarStructure();

    // Create existing VHost
    $vsite = FleetVsite::create([
        'name' => 'local-test',
        'provider' => 'local',
        'technology' => 'lxc',
        'is_active' => true,
    ]);
    $vnode = FleetVnode::create([
        'name' => 'web01',
        'vsite_id' => $vsite->id,
        'is_active' => true,
        'ip_address' => '192.168.1.100',
        'ssh_host_id' => null,
    ]);
    FleetVhost::create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'status' => 'active',
        'is_active' => true,
        'environment_vars' => ['VHOST' => 'example.com'],
    ]);

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
    ])
        ->assertExitCode(0);

    // Should have 1 existing + 1 new VHost
    expect(FleetVhost::count())->toBe(2);
});

it('overwrites existing vhosts with force flag', function () {
    createTestVarStructure();

    // Create existing VHost with different data
    $vsite = FleetVsite::create([
        'name' => 'local-test',
        'provider' => 'local',
        'technology' => 'lxc',
        'is_active' => true,
    ]);
    $vnode = FleetVnode::create([
        'name' => 'web01',
        'vsite_id' => $vsite->id,
        'is_active' => true,
        'ip_address' => '192.168.1.100',
        'ssh_host_id' => null,
    ]);
    $existingVHost = FleetVhost::create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'status' => 'inactive',
        'is_active' => false,
        'environment_vars' => ['VHOST' => 'example.com', 'OLD_VAR' => 'old_value'],
    ]);

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
        '--force' => true,
    ])
        ->assertExitCode(0);

    // Check that existing VHost was updated
    $existingVHost->refresh();
    expect($existingVHost->status)->toBe('active');
    expect($existingVHost->is_active)->toBe(true);
    expect($existingVHost->environment_vars)->toHaveKey('UUSER', 'u1001');
    expect($existingVHost->environment_vars)->not->toHaveKey('OLD_VAR');
});

it('handles missing var directory gracefully', function () {
    $this->artisan('import:vhosts', [
        'path' => '/tmp/nonexistent',
    ])
        ->expectsOutput('❌ Var directory not found: /tmp/nonexistent')
        ->assertExitCode(1);
});

it('handles malformed vhost files gracefully', function () {
    // Create directory with malformed file
    File::makeDirectory('/tmp/test_var/local-test/web01', 0755, true);
    File::put('/tmp/test_var/local-test/web01/malformed.com', 'invalid content without variables');

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
    ])
        ->assertExitCode(0);

    // Should not create any VHost records
    expect(FleetVhost::count())->toBe(0);
});

it('validates required environment variables', function () {
    // Create file missing required variables
    File::makeDirectory('/tmp/test_var/local-test/web01', 0755, true);
    File::put('/tmp/test_var/local-test/web01/incomplete.com', 'VHOST=incomplete.com');

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
    ])
        ->assertExitCode(0);

    // Should not create VHost record due to missing required variables
    expect(FleetVhost::count())->toBe(0);
});

it('normalizes environment variables to expected set', function () {
    createTestVarStructure();

    $this->artisan('import:vhosts', [
        'path' => '/tmp/test_var',
    ])
        ->assertExitCode(0);

    $vhost = FleetVhost::where('domain', 'example.com')->first();

    // Check that all 53 expected variables are present
    $expectedVars = [
        'ADMIN', 'AHOST', 'AMAIL', 'ANAME', 'APASS', 'A_GID', 'A_UID',
        'BPATH', 'CIMAP', 'CSMTP', 'C_DNS', 'C_FPM', 'C_SQL', 'C_SSL', 'C_WEB',
        'DBMYS', 'DBSQL', 'DHOST', 'DNAME', 'DPASS', 'DPATH', 'DPORT', 'DTYPE', 'DUSER',
        'EPASS', 'EXMYS', 'EXSQL', 'HDOMN', 'HNAME', 'IP4_0',
        'MHOST', 'MPATH', 'OSMIR', 'OSREL', 'OSTYP',
        'VNODE', 'SQCMD', 'SQDNS', 'TAREA', 'TCITY', 'UPASS', 'UPATH', 'UUSER',
        'U_GID', 'U_SHL', 'U_UID', 'VHOST', 'VPATH', 'VUSER', 'V_PHP',
        'WPASS', 'WPATH', 'WPUSR', 'WUGID',
    ];

    foreach ($expectedVars as $var) {
        expect($vhost->environment_vars)->toHaveKey($var);
    }

    expect(count($vhost->environment_vars))->toBe(53);
});

/**
 * Helper function to create test var directory structure
 */
function createTestVarStructure(): void
{
    // Clean up any existing test directory first
    if (File::exists('/tmp/test_var')) {
        File::deleteDirectory('/tmp/test_var');
    }

    // Create test directory structure
    File::makeDirectory('/tmp/test_var/local-test/web01', 0755, true);

    // Create example.com vhost file
    $exampleComConfig = <<<'EOF'
VHOST=example.com
VNODE=web01
UUSER=u1001
WUGID=www-data
UPATH=/srv/example.com
WPATH=/srv/example.com/web
MPATH=/srv/example.com/msg
V_PHP=8.4
ADMIN=admin@example.com
DHOST=localhost
DNAME=example_com
DUSER=u1001
DPASS=secure_password_123
IP4_0=192.168.1.100
OSTYP=debian
OSREL=trixie
VPATH=/srv
EOF;

    File::put('/tmp/test_var/local-test/web01/example.com', $exampleComConfig);

    // Create test.org vhost file
    $testOrgConfig = <<<'EOF'
VHOST=test.org
VNODE=web01
UUSER=u1002
WUGID=www-data
UPATH=/srv/test.org
WPATH=/srv/test.org/web
MPATH=/srv/test.org/msg
V_PHP=8.4
ADMIN=admin@test.org
DHOST=localhost
DNAME=test_org
DUSER=u1002
DPASS=another_password_456
IP4_0=192.168.1.100
OSTYP=debian
OSREL=trixie
VPATH=/srv
EOF;

    File::put('/tmp/test_var/local-test/web01/test.org', $testOrgConfig);

    // Create a .conf file that should be ignored
    File::put('/tmp/test_var/local-test/web01/example.com.conf', 'This should be ignored');
}
