<?php

declare(strict_types=1);

use NetServa\Cms\Models\Menu;

it('can create a menu', function () {
    $menu = Menu::factory()->create([
        'name' => 'Test Menu',
        'location' => 'test-location',
    ]);

    expect($menu)->toBeInstanceOf(Menu::class)
        ->and($menu->name)->toBe('Test Menu')
        ->and($menu->location)->toBe('test-location');
});

it('stores menu items as json', function () {
    $items = [
        ['label' => 'Home', 'url' => '/', 'order' => 0],
        ['label' => 'About', 'url' => '/about', 'order' => 1],
    ];

    $menu = Menu::factory()->create(['items' => $items]);

    expect($menu->items)->toBeArray()
        ->and($menu->items)->toHaveCount(2);
});

it('scopes active menus correctly', function () {
    Menu::factory()->count(3)->create(['is_active' => true]);
    Menu::factory()->count(2)->inactive()->create();

    $activeMenus = Menu::active()->get();

    expect($activeMenus)->toHaveCount(3);
});

it('can filter by location', function () {
    Menu::factory()->header()->create();
    Menu::factory()->footer()->create();
    Menu::factory()->create(['location' => 'sidebar']);

    $headerMenu = Menu::byLocation('header')->first();

    expect($headerMenu)->not->toBeNull()
        ->and($headerMenu->location)->toBe('header');
});

it('can get menu by location helper', function () {
    $menu = Menu::factory()->header()->create();

    $found = Menu::getByLocation('header');

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($menu->id);
});

it('returns menu items as hierarchical array', function () {
    $menu = Menu::factory()->create();

    $items = $menu->getMenuItems();

    expect($items)->toBeArray()
        ->and($items)->toHaveCount(5); // Default factory creates 5 items
});
