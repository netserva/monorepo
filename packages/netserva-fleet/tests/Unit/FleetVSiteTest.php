<?php

use NetServa\Fleet\Models\FleetVNode;
use NetServa\Fleet\Models\FleetVSite;

uses()
    ->group('unit', 'fleet', 'vsite', 'priority-2');

it('can create a vsite with required fields', function () {
    $vsite = FleetVSite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
        'location' => 'workstation',
    ]);

    expect($vsite)
        ->name->toBe('local-incus')
        ->provider->toBe('local')
        ->technology->toBe('incus')
        ->location->toBe('workstation')
        ->is_active->toBeTrue()
        ->status->toBe('active');
});

it('auto-generates slug from name', function () {
    $vsite = FleetVSite::create([
        'name' => 'BinaryLane Sydney VPS',
        'provider' => 'binarylane',
        'technology' => 'vps',
    ]);

    expect($vsite->slug)->toBe('binarylane-sydney-vps');
});

it('can determine provider technology combination', function () {
    $vsite = FleetVSite::create([
        'name' => 'local-proxmox',
        'provider' => 'local',
        'technology' => 'proxmox',
    ]);

    expect($vsite->getProviderTech())->toBe('Local Proxmox');
});

it('can check capabilities', function () {
    $vsite = FleetVSite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
        'capabilities' => ['containers', 'vms', 'snapshots'],
    ]);

    expect($vsite->hasCapability('containers'))->toBeTrue()
        ->and($vsite->hasCapability('clustering'))->toBeFalse();
});

it('can identify local vs cloud infrastructure', function () {
    $localVsite = FleetVSite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $cloudVsite = FleetVSite::create([
        'name' => 'binarylane-vps',
        'provider' => 'binarylane',
        'technology' => 'vps',
    ]);

    expect($localVsite->isLocal())->toBeTrue()
        ->and($localVsite->isCloud())->toBeFalse()
        ->and($cloudVsite->isLocal())->toBeFalse()
        ->and($cloudVsite->isCloud())->toBeTrue();
});

it('can create vsite from mapping', function () {
    $mapping = [
        'provider' => 'binarylane',
        'technology' => 'proxmox',
        'location' => 'sydney',
    ];

    $vsite = FleetVSite::createFromMapping('binarylane-proxmox-sydney', $mapping);

    expect($vsite)
        ->name->toBe('binarylane-proxmox-sydney')
        ->provider->toBe('binarylane')
        ->technology->toBe('proxmox')
        ->location->toBe('sydney')
        ->capabilities->toBeArray();
});

it('enforces unique constraint on provider-technology-location', function () {
    FleetVSite::create([
        'name' => 'test-1',
        'provider' => 'local',
        'technology' => 'incus',
        'location' => 'workstation',
    ]);

    expect(fn () => FleetVSite::create([
        'name' => 'test-2',
        'provider' => 'local',
        'technology' => 'incus',
        'location' => 'workstation',
    ]))->toThrow(Exception::class);
});

it('can have multiple vnodes', function () {
    $vsite = FleetVSite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    FleetVNode::create([
        'name' => 'node1',
        'vsite_id' => $vsite->id,
    ]);

    FleetVNode::create([
        'name' => 'node2',
        'vsite_id' => $vsite->id,
    ]);

    expect($vsite->vnodes)->toHaveCount(2)
        ->and($vsite->getNodeCountAttribute())->toBe(2);
});

it('can filter by provider and technology', function () {
    FleetVSite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    FleetVSite::create([
        'name' => 'binarylane-vps',
        'provider' => 'binarylane',
        'technology' => 'vps',
    ]);

    $localSites = FleetVSite::byProvider('local')->get();
    $incusSites = FleetVSite::byTechnology('incus')->get();

    expect($localSites)->toHaveCount(1)
        ->and($incusSites)->toHaveCount(1)
        ->and($localSites->first()->name)->toBe('local-incus')
        ->and($incusSites->first()->name)->toBe('local-incus');
});
