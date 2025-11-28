<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetServa\Core\Models\VConf;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'netserva-cli', 'vhost-management', 'crud', 'priority-1');

it('displays help information', function () {
    $this->artisan('shvhost --help')
        ->expectsOutput('Description:')
        ->expectsOutput('Show virtual host information (NetServa CRUD pattern)')
        ->assertExitCode(0);
});

it('shows all vhosts when database is empty', function () {
    $this->artisan('shvhost')
        ->expectsOutputToContain('No VHosts found in database')
        ->expectsOutputToContain('💡 Run: php artisan fleet:discover')
        ->assertExitCode(0);
});

it('shows all vhosts across all vnodes', function () {
    // Create test data
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode1 = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);
    $vnode2 = FleetVnode::factory()->create(['name' => 'prod', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create(['domain' => 'test1.com', 'vnode_id' => $vnode1->id, 'is_active' => true]);
    FleetVhost::factory()->create(['domain' => 'test2.com', 'vnode_id' => $vnode1->id, 'is_active' => true]);
    FleetVhost::factory()->create(['domain' => 'prod1.com', 'vnode_id' => $vnode2->id, 'is_active' => false]);

    $this->artisan('shvhost')
        ->expectsOutputToContain('📋 All VHosts:')
        ->expectsOutputToContain('markc')
        ->expectsOutputToContain('(2 vhosts)')
        ->expectsOutputToContain('test1.com')
        ->expectsOutputToContain('test2.com')
        ->expectsOutputToContain('prod')
        ->expectsOutputToContain('(1 vhosts)')
        ->expectsOutputToContain('prod1.com')
        ->assertExitCode(0);
});

it('lists vhosts for specific vnode', function () {
    // Create test data
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'is_active' => true,
        'services' => ['nginx', 'php-fpm', 'mysql'],
        'last_discovered_at' => now()->subHours(2),
    ]);

    FleetVhost::factory()->create([
        'domain' => 'test.com',
        'vnode_id' => $vnode->id,
        'is_active' => false,
        'services' => ['nginx'],
        'last_discovered_at' => now()->subDays(1),
    ]);

    $this->artisan('shvhost markc --list')
        ->expectsOutputToContain('📋 VHosts on server: markc')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('✅ Active')
        ->expectsOutputToContain('test.com')
        ->expectsOutputToContain('❌ Inactive')
        ->assertExitCode(0);
});

it('lists vhosts for vnode without --list flag', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create(['domain' => 'example.com', 'vnode_id' => $vnode->id]);

    $this->artisan('shvhost markc')
        ->expectsOutputToContain('📋 VHosts on server: markc')
        ->expectsOutputToContain('example.com')
        ->assertExitCode(0);
});

it('shows message when vnode has no vhosts', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    FleetVnode::factory()->create(['name' => 'empty', 'vsite_id' => $vsite->id]);

    $this->artisan('shvhost empty --list')
        ->expectsOutputToContain('No vhosts found in database')
        ->expectsOutputToContain('💡 Run: php artisan fleet:discover --vnode=empty')
        ->assertExitCode(0);
});

it('shows specific vhost details', function () {
    // Create test data
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);
    $vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'is_active' => true,
        'services' => ['nginx', 'php-fpm', 'mysql'],
    ]);

    // Add vconfs (database-first!)
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'UUSER', 'value' => 'u1001']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'U_UID', 'value' => '1001']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'U_GID', 'value' => '1001']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'WUGID', 'value' => 'nginx']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'UPATH', 'value' => '/srv/example.com']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'WPATH', 'value' => '/srv/example.com/web']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'MPATH', 'value' => '/srv/example.com/msg']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DNAME', 'value' => 'sysadm']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DUSER', 'value' => 'sysadm']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DTYPE', 'value' => 'sqlite']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DHOST', 'value' => 'localhost']);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DPORT', 'value' => '3306']);

    $this->artisan('shvhost markc example.com')
        ->expectsOutputToContain('📋 VHost Details: example.com on markc')
        ->expectsOutputToContain('🖥️  Basic Information:')
        ->expectsOutputToContain('Domain: example.com')
        ->expectsOutputToContain('User: u1001')
        ->expectsOutputToContain('UID: 1001')
        ->expectsOutputToContain('Group: nginx')
        ->expectsOutputToContain('GID: 1001')
        ->expectsOutputToContain('Status: Active')
        ->expectsOutputToContain('📁 Paths:')
        ->expectsOutputToContain('User path: /srv/example.com')
        ->expectsOutputToContain('Web path: /srv/example.com/web')
        ->expectsOutputToContain('Mail path: /srv/example.com/msg')
        ->expectsOutputToContain('🗄️  Database:')
        ->expectsOutputToContain('Name: sysadm')
        ->expectsOutputToContain('User: sysadm')
        ->expectsOutputToContain('Type: sqlite')
        ->expectsOutputToContain('Host: localhost:3306')
        ->expectsOutputToContain('⚙️  Services:')
        ->expectsOutputToContain('nginx, php-fpm, mysql')
        ->assertExitCode(0);
});

it('shows config details with --config flag', function () {
    // Create test data
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);
    $vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
    ]);

    // Add some vconfs including sensitive ones
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'VHOST', 'value' => 'example.com', 'is_sensitive' => false]);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'VNODE', 'value' => 'markc', 'is_sensitive' => false]);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'DPASS', 'value' => 'secret123', 'is_sensitive' => true]);
    VConf::factory()->create(['fleet_vhost_id' => $vhost->id, 'name' => 'UPASS', 'value' => 'userpass456', 'is_sensitive' => true]);

    $this->artisan('shvhost markc example.com --config')
        ->expectsOutputToContain('⚙️  Environment Variables:')
        ->expectsOutputToContain('VHOST')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('DPASS')
        ->expectsOutputToContain('***') // Password should be masked
        ->expectsOutputToContain('UPASS')
        ->assertExitCode(0);
});

it('handles missing vhost gracefully', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    $this->artisan('shvhost markc nonexistent.com')
        ->expectsOutput('❌ VHost nonexistent.com not found on markc')
        ->expectsOutputToContain('💡 Run: php artisan fleet:discover --vnode=markc')
        ->assertExitCode(1);
});

it('handles missing vnode gracefully when listing', function () {
    $this->artisan('shvhost nonexistent --list')
        ->expectsOutput('❌ VNODE required for listing vhosts')
        ->assertExitCode(1);
});

it('shows active/inactive status correctly', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    // Active vhost
    $activeVhost = FleetVhost::factory()->create([
        'domain' => 'active.com',
        'vnode_id' => $vnode->id,
        'is_active' => true,
    ]);
    VConf::factory()->create(['fleet_vhost_id' => $activeVhost->id, 'name' => 'UUSER', 'value' => 'u1001']);

    // Inactive vhost
    $inactiveVhost = FleetVhost::factory()->create([
        'domain' => 'inactive.com',
        'vnode_id' => $vnode->id,
        'is_active' => false,
    ]);
    VConf::factory()->create(['fleet_vhost_id' => $inactiveVhost->id, 'name' => 'UUSER', 'value' => 'u1002']);

    $this->artisan('shvhost markc active.com')
        ->expectsOutputToContain('Status: Active')
        ->assertExitCode(0);

    $this->artisan('shvhost markc inactive.com')
        ->expectsOutputToContain('Status: Inactive')
        ->assertExitCode(0);
});
