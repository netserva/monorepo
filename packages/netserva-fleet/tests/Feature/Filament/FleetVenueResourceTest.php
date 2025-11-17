<?php

declare(strict_types=1);

use NetServa\Fleet\Filament\Resources\FleetVenueResource;
use NetServa\Fleet\Models\FleetVenue;

// Test that the FleetVenueResource is registered and accessible
it('has fleet venue resource registered', function () {
    expect(FleetVenueResource::class)->toBeString();
    expect(class_exists(FleetVenueResource::class))->toBeTrue();
});

it('can get venue resource pages', function () {
    $pages = FleetVenueResource::getPages();

    expect($pages)->toBeArray()
        ->toHaveKey('index')
        ->toHaveKey('view');
});

it('can get venue resource model', function () {
    expect(FleetVenueResource::getModel())->toBe(FleetVenue::class);
});

it('generates navigation badge with venue count', function () {
    $initialCount = FleetVenue::count();
    FleetVenue::factory()->count(5)->create();
    $expectedCount = $initialCount + 5;

    $badge = FleetVenueResource::getNavigationBadge();

    expect($badge)->toBe((string) $expectedCount);
});

it('has correct navigation configuration', function () {
    $label = FleetVenueResource::getNavigationLabel();
    $group = FleetVenueResource::getNavigationGroup();

    expect($label)->toBe('Venues');
    expect($group)->toBe('🚀 Fleet Management');
});
