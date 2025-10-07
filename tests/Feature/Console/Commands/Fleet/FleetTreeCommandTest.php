<?php

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    // Create test venue
    $venueId = DB::table('fleet_venues')->insertGetId([
        'name' => 'test-venue',
        'slug' => 'test-venue',
        'provider' => 'test',
        'location' => 'test-location',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create test vsite
    $vsiteId = DB::table('fleet_vsites')->insertGetId([
        'name' => 'test-vsite',
        'slug' => 'test-vsite',
        'venue_id' => $venueId,
        'provider' => 'test',
        'technology' => 'docker',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create test vnode
    $vnodeId = DB::table('fleet_vnodes')->insertGetId([
        'name' => 'test-vnode',
        'slug' => 'test-vnode',
        'vsite_id' => $vsiteId,
        'role' => 'compute',
        'ip_address' => '192.168.1.100',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Create test vhost
    $vhostId = DB::table('fleet_vhosts')->insertGetId([
        'vnode_id' => $vnodeId,
        'domain' => 'test.example.com',
        'slug' => 'test-example-com',
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

});

it('displays full tree with colors and emojis', function () {
    $this->artisan('fleet:tree --venue=test-venue')
        ->assertSuccessful()
        ->expectsOutputToContain('test-venue')
        ->expectsOutputToContain('test-vsite')
        ->expectsOutputToContain('test-vnode')
        ->expectsOutputToContain('test.example.com');
});

it('displays simple tree without colors or emojis', function () {
    $this->artisan('fleet:tree --simple --venue=test-venue')
        ->assertSuccessful()
        ->expectsOutputToContain('test-venue')
        ->expectsOutputToContain('test-vsite')
        ->expectsOutputToContain('test-vnode')
        ->expectsOutputToContain('test.example.com');
});

it('displays statistics when requested', function () {
    $this->artisan('fleet:tree --venue=test-venue --stats')
        ->assertSuccessful()
        ->expectsOutputToContain('Fleet Statistics');
});

it('filters by venue', function () {
    $this->artisan('fleet:tree --venue=test-venue')
        ->assertSuccessful()
        ->expectsOutputToContain('test-venue');
});

it('filters by vsite', function () {
    $this->artisan('fleet:tree --vsite=test-vsite')
        ->assertSuccessful()
        ->expectsOutputToContain('test-vsite');
});

it('filters by vnode', function () {
    $this->artisan('fleet:tree --vnode=test-vnode')
        ->assertSuccessful()
        ->expectsOutputToContain('test-vnode');
});

it('shows summary counts in simple mode', function () {
    $this->artisan('fleet:tree --simple --venue=test-venue')
        ->assertSuccessful()
        ->expectsOutputToContain('1 venues')
        ->expectsOutputToContain('1 vsites')
        ->expectsOutputToContain('1 vnodes')
        ->expectsOutputToContain('1 vhosts');
});
