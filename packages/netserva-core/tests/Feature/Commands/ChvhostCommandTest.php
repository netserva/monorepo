<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetServa\Core\Models\VConf;
use NetServa\Core\Services\NetServaContext;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'netserva-cli', 'vhost-management', 'crud', 'priority-1');

beforeEach(function () {
    $this->context = $this->mock(NetServaContext::class);

    // Create test vsite, vnode, and vhost
    $this->vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $this->vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $this->vsite->id]);
    $this->vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $this->vnode->id,
        'is_active' => true,
    ]);

    // Add default vconfs
    VConf::factory()->create(['fleet_vhost_id' => $this->vhost->id, 'name' => 'V_PHP', 'value' => '8.2']);
    VConf::factory()->create(['fleet_vhost_id' => $this->vhost->id, 'name' => 'WPATH', 'value' => '/srv/example.com/web']);
    VConf::factory()->create(['fleet_vhost_id' => $this->vhost->id, 'name' => 'SSL_ENABLED', 'value' => 'false', 'is_sensitive' => false]);
});

it('displays help information', function () {
    $this->artisan('chvhost --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Change/update virtual host configuration (NetServa CRUD pattern)')
        ->assertExitCode(0);
});

it('updates PHP version successfully', function () {
    $this->context
        ->shouldReceive('addToHistory')
        ->never();

    $this->artisan('chvhost markc example.com --php-version=8.4')
        ->expectsOutput('🔧 Updating VHost: example.com on server markc')
        ->expectsOutputToContain('php_version: 8.4')
        ->expectsOutput('✅ VHost example.com updated successfully on markc')
        ->expectsOutputToContain('✓ PHP Version: 8.4')
        ->assertExitCode(0);

    // Verify vconfs table was updated (DATABASE-FIRST!)
    $updatedVconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'V_PHP')
        ->first();
    expect($updatedVconf->value)->toBe('8.4');
});

it('validates PHP version options', function () {
    $this->artisan('chvhost markc example.com --php-version=7.4')
        ->expectsOutput('❌ Invalid PHP version. Valid options: 8.1, 8.2, 8.3, 8.4')
        ->assertExitCode(1);

    // Verify vconfs table was NOT updated
    $vconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'V_PHP')
        ->first();
    expect($vconf->value)->toBe('8.2'); // Still original value
});

it('enables SSL successfully', function () {
    $this->artisan('chvhost markc example.com --ssl=true')
        ->expectsOutput('🔧 Updating VHost: example.com on server markc')
        ->expectsOutputToContain('ssl_enabled: true')
        ->expectsOutput('✅ VHost example.com updated successfully on markc')
        ->expectsOutputToContain('✓ SSL Enabled: true')
        ->assertExitCode(0);

    // Verify vconfs table was updated
    $updatedVconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'SSL_ENABLED')
        ->first();
    expect($updatedVconf->value)->toBe('true');
});

it('disables SSL successfully', function () {
    // First enable SSL
    VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'SSL_ENABLED')
        ->update(['value' => 'true']);

    $this->artisan('chvhost markc example.com --ssl=false')
        ->expectsOutputToContain('ssl_enabled: false')
        ->expectsOutputToContain('✓ SSL Enabled: false')
        ->assertExitCode(0);

    // Verify vconfs table was updated
    $updatedVconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'SSL_ENABLED')
        ->first();
    expect($updatedVconf->value)->toBe('false');
});

it('validates SSL value', function () {
    $this->artisan('chvhost markc example.com --ssl=invalid')
        ->expectsOutput("❌ Invalid SSL value. Use 'true' or 'false'")
        ->assertExitCode(1);
});

it('updates webroot successfully', function () {
    $this->artisan('chvhost markc example.com --webroot=/srv/example.com/public')
        ->expectsOutput('🔧 Updating VHost: example.com on server markc')
        ->expectsOutputToContain('webroot: /srv/example.com/public')
        ->expectsOutput('✅ VHost example.com updated successfully on markc')
        ->expectsOutputToContain('✓ Web Root: /srv/example.com/public')
        ->assertExitCode(0);

    // Verify vconfs table was updated
    $updatedVconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'WPATH')
        ->first();
    expect($updatedVconf->value)->toBe('/srv/example.com/public');
});

it('validates webroot is absolute path', function () {
    $this->artisan('chvhost markc example.com --webroot=relative/path')
        ->expectsOutput('❌ Webroot must be an absolute path (starting with /)')
        ->assertExitCode(1);

    // Verify vconfs table was NOT updated
    $vconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'WPATH')
        ->first();
    expect($vconf->value)->toBe('/srv/example.com/web'); // Still original
});

it('updates multiple options at once', function () {
    $this->artisan('chvhost markc example.com --php-version=8.4 --ssl=true --webroot=/srv/example.com/app')
        ->expectsOutputToContain('php_version: 8.4')
        ->expectsOutputToContain('ssl_enabled: true')
        ->expectsOutputToContain('webroot: /srv/example.com/app')
        ->expectsOutputToContain('✓ PHP Version: 8.4')
        ->expectsOutputToContain('✓ SSL Enabled: true')
        ->expectsOutputToContain('✓ Web Root: /srv/example.com/app')
        ->assertExitCode(0);

    // Verify all vconfs were updated
    expect($this->vhost->fresh()->getEnvVar('V_PHP'))->toBe('8.4');
    expect($this->vhost->fresh()->getEnvVar('SSL_ENABLED'))->toBe('true');
    expect($this->vhost->fresh()->getEnvVar('WPATH'))->toBe('/srv/example.com/app');
});

it('creates backup before changes when --backup flag is used', function () {
    $this->artisan('chvhost markc example.com --php-version=8.4 --backup')
        ->expectsOutputToContain('📦 Backup created in database (vconfs table)')
        ->expectsOutput('✅ VHost example.com updated successfully on markc')
        ->assertExitCode(0);

    // Verify backup metadata exists
    $this->vhost->refresh();
    expect($this->vhost->legacy_config)->toHaveKey('last_backup');
    expect($this->vhost->legacy_config['last_backup']['backup_type'])->toBe('pre_chvhost_update');
});

it('supports dry-run mode', function () {
    $this->artisan('chvhost markc example.com --php-version=8.4 --dry-run')
        ->expectsOutput('🔧 Updating VHost: example.com on server markc')
        ->expectsOutput('🔍 DRY RUN: Update VHost example.com on markc')
        ->expectsOutputToContain("Load current vhost from FleetVhost model (ID: {$this->vhost->id})")
        ->expectsOutputToContain('Load environment variables from vconfs table (database-first)')
        ->expectsOutputToContain('Update vconfs table with new values via FleetVhost::setEnvVar()')
        ->expectsOutputToContain('SSH to markc and apply changes via RemoteExecutionService heredoc')
        ->assertExitCode(0);

    // Verify NO changes were made to vconfs table
    $vconf = VConf::where('fleet_vhost_id', $this->vhost->id)
        ->where('name', 'V_PHP')
        ->first();
    expect($vconf->value)->toBe('8.2'); // Still original value
});

it('requires at least one option to be specified', function () {
    $this->artisan('chvhost markc example.com')
        ->expectsOutput('❌ No changes specified. Use --help to see available options')
        ->assertExitCode(1);
});

it('handles missing vhost gracefully', function () {
    $this->artisan('chvhost markc nonexistent.com --php-version=8.4')
        ->expectsOutput('❌ VHost nonexistent.com not found on markc')
        ->expectsOutputToContain("Use 'addvhost markc nonexistent.com' to create it first")
        ->assertExitCode(1);
});

it('handles missing vnode gracefully', function () {
    $this->artisan('chvhost nonexistent example.com --php-version=8.4')
        ->expectsOutput('❌ VNode nonexistent not found in database')
        ->expectsOutputToContain('Run: php artisan fleet:discover --vnode=nonexistent')
        ->assertExitCode(1);
});

it('validates required vnode argument', function () {
    // Missing vnode argument should fail with Laravel's validation
    $this->artisan('chvhost')
        ->assertExitCode(1);
});

it('validates required vhost argument', function () {
    // Missing vhost argument should fail with Laravel's validation
    $this->artisan('chvhost markc')
        ->assertExitCode(1);
});

it('uses database-first architecture exclusively', function () {
    // This test verifies that we're using vconfs table, not files
    $this->artisan('chvhost markc example.com --php-version=8.4 --dry-run')
        ->expectsOutputToContain('vconfs table (database-first)')
        ->expectsOutputToContain('FleetVhost::setEnvVar()')
        ->assertExitCode(0);
});

it('shows all valid PHP versions in error message', function () {
    $this->artisan('chvhost markc example.com --php-version=9.0')
        ->expectsOutputToContain('8.1, 8.2, 8.3, 8.4')
        ->assertExitCode(1);
});

it('uses correct NetServa 3.0 command signature pattern', function () {
    // Verify command follows: <command> <vnode> <vhost> [options]
    $this->artisan('chvhost markc example.com --php-version=8.4')
        ->expectsOutput('✅ VHost example.com updated successfully on markc')
        ->assertExitCode(0);
});
