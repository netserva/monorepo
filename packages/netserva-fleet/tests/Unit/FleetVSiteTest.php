<?php

use NetServa\Fleet\Models\FleetVnode;
use NetServa\Fleet\Models\FleetVsite;

uses()
    ->group('unit', 'fleet', 'vsite', 'priority-2');

it('can create a vsite with required fields', function () {
    $vsite = FleetVsite::create([
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
    $vsite = FleetVsite::create([
        'name' => 'BinaryLane Sydney VPS',
        'provider' => 'binarylane',
        'technology' => 'vps',
    ]);

    expect($vsite->slug)->toBe('binarylane-sydney-vps');
});

it('can determine provider technology combination', function () {
    $vsite = FleetVsite::create([
        'name' => 'local-proxmox',
        'provider' => 'local',
        'technology' => 'proxmox',
    ]);

    expect($vsite->getProviderTech())->toBe('Local Proxmox');
});

it('can check capabilities', function () {
    $vsite = FleetVsite::create([
        'name' => 'test-site',
        'provider' => 'local',
        'technology' => 'incus',
        'capabilities' => ['containers', 'vms', 'snapshots'],
    ]);

    expect($vsite->hasCapability('containers'))->toBeTrue()
        ->and($vsite->hasCapability('clustering'))->toBeFalse();
});

it('can identify local vs cloud infrastructure', function () {
    $localVsite = FleetVsite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    $cloudVsite = FleetVsite::create([
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

    $vsite = FleetVsite::createFromMapping('binarylane-proxmox-sydney', $mapping);

    expect($vsite)
        ->name->toBe('binarylane-proxmox-sydney')
        ->provider->toBe('binarylane')
        ->technology->toBe('proxmox')
        ->location->toBe('sydney')
        ->capabilities->toBeArray();
});

it('enforces unique constraint on provider-technology-location', function () {
    FleetVsite::create([
        'name' => 'test-1',
        'provider' => 'local',
        'technology' => 'incus',
        'location' => 'workstation',
    ]);

    expect(fn () => FleetVsite::create([
        'name' => 'test-2',
        'provider' => 'local',
        'technology' => 'incus',
        'location' => 'workstation',
    ]))->toThrow(Exception::class);
});

it('can have multiple vnodes', function () {
    $vsite = FleetVsite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    FleetVnode::create([
        'name' => 'node1',
        'vsite_id' => $vsite->id,
    ]);

    FleetVnode::create([
        'name' => 'node2',
        'vsite_id' => $vsite->id,
    ]);

    expect($vsite->vnodes)->toHaveCount(2)
        ->and($vsite->getNodeCountAttribute())->toBe(2);
});

it('can filter by provider and technology', function () {
    FleetVsite::create([
        'name' => 'local-incus',
        'provider' => 'local',
        'technology' => 'incus',
    ]);

    FleetVsite::create([
        'name' => 'binarylane-vps',
        'provider' => 'binarylane',
        'technology' => 'vps',
    ]);

    $localSites = FleetVsite::byProvider('local')->get();
    $incusSites = FleetVsite::byTechnology('incus')->get();

    expect($localSites)->toHaveCount(1)
        ->and($incusSites)->toHaveCount(1)
        ->and($localSites->first()->name)->toBe('local-incus')
        ->and($incusSites->first()->name)->toBe('local-incus');
});
