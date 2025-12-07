<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use NetServa\Core\Models\VPass;
use NetServa\Fleet\Models\FleetVhost;
use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses(RefreshDatabase::class)
    ->group('feature', 'commands', 'netserva-fleet', 'vhost-management', 'crud', 'priority-1');

it('displays help information', function () {
    $this->artisan('shvhost --help')
        ->expectsOutputToContain('Show virtual host information')
        ->assertExitCode(0);
});

it('shows all vhosts when database is empty', function () {
    $this->artisan('shvhost')
        ->expectsOutputToContain('No VHosts found in database')
        ->expectsOutputToContain('addfleet')
        ->assertExitCode(0);
});

it('shows all vhosts across all vnodes', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode1 = FleetVnode::factory()->create(['name' => 'testnode1', 'vsite_id' => $vsite->id]);
    $vnode2 = FleetVnode::factory()->create(['name' => 'testnode2', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create(['domain' => 'test1.example.com', 'vnode_id' => $vnode1->id, 'status' => 'active']);
    FleetVhost::factory()->create(['domain' => 'test2.example.com', 'vnode_id' => $vnode1->id, 'status' => 'active']);
    FleetVhost::factory()->create(['domain' => 'prod1.example.com', 'vnode_id' => $vnode2->id, 'status' => 'inactive']);

    $this->artisan('shvhost')
        ->expectsOutputToContain('All VHosts')
        ->expectsOutputToContain('testnode1')
        ->expectsOutputToContain('testnode2')
        ->expectsOutputToContain('test1.example.com')
        ->expectsOutputToContain('prod1.example.com')
        ->assertExitCode(0);
});

it('lists vhosts for specific vnode', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'status' => 'active',
        'app_type' => 'wordpress',
    ]);

    FleetVhost::factory()->create([
        'domain' => 'test.com',
        'vnode_id' => $vnode->id,
        'status' => 'inactive',
        'app_type' => 'static',
    ]);

    $this->artisan('shvhost markc --list')
        ->expectsOutputToContain('VHosts on server: markc')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('test.com')
        ->assertExitCode(0);
});

it('lists vhosts for vnode without --list flag', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    FleetVhost::factory()->create(['domain' => 'example.com', 'vnode_id' => $vnode->id]);

    $this->artisan('shvhost markc')
        ->expectsOutputToContain('VHosts on server: markc')
        ->expectsOutputToContain('example.com')
        ->assertExitCode(0);
});

it('shows message when vnode has no vhosts', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    FleetVnode::factory()->create(['name' => 'empty', 'vsite_id' => $vsite->id]);

    $this->artisan('shvhost empty --list')
        ->expectsOutputToContain('No vhosts found')
        ->assertExitCode(0);
});

it('shows specific vhost details', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);
    $vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'status' => 'active',
        'uid' => 1001,
        'gid' => 1001,
        'unix_username' => 'u1001',
        'app_type' => 'wordpress',
        'db_name' => 'sysadm',
        'db_user' => 'sysadm',
    ]);

    $this->artisan('shvhost markc example.com')
        ->expectsOutputToContain('example.com')
        ->expectsOutputToContain('active')
        ->assertExitCode(0);
});

it('shows config details with --config flag', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);
    $vhost = FleetVhost::factory()->create([
        'domain' => 'example.com',
        'vnode_id' => $vnode->id,
        'uid' => 1001,
        'gid' => 1001,
        'db_name' => 'sysadm',
        'db_user' => 'sysadm',
    ]);

    // Store credentials in vpass
    VPass::store($vhost, VPass::SERVICE_MYSQL, 'sysadm', 'secret123', 'sysadm');

    $this->artisan('shvhost markc example.com --config')
        ->expectsOutputToContain('example.com')
        ->assertExitCode(0);
});

it('handles missing vhost gracefully', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    $this->artisan('shvhost markc nonexistent.com')
        ->expectsOutputToContain('not found')
        ->assertExitCode(1);
});

it('handles missing vnode gracefully when listing', function () {
    $this->artisan('shvhost --list')
        ->expectsOutputToContain('VNODE required')
        ->assertExitCode(1);
});

it('shows active/inactive status correctly', function () {
    $vsite = FleetVsite::factory()->create(['name' => 'test-site']);
    $vnode = FleetVnode::factory()->create(['name' => 'markc', 'vsite_id' => $vsite->id]);

    // Active vhost
    FleetVhost::factory()->create([
        'domain' => 'active.com',
        'vnode_id' => $vnode->id,
        'status' => 'active',
    ]);

    // Inactive vhost
    FleetVhost::factory()->create([
        'domain' => 'inactive.com',
        'vnode_id' => $vnode->id,
        'status' => 'inactive',
    ]);

    $this->artisan('shvhost markc active.com')
        ->expectsOutputToContain('active')
        ->assertExitCode(0);

    $this->artisan('shvhost markc inactive.com')
        ->expectsOutputToContain('inactive')
        ->assertExitCode(0);
});
