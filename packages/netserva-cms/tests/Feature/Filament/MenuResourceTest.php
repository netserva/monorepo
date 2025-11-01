<?php

declare(strict_types=1);

use NetServa\Cms\Filament\Resources\MenuResource;
use NetServa\Cms\Models\Menu;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render menu list', function () {
    Menu::factory()->count(5)->create();

    livewire(MenuResource\Pages\ListMenus::class)
        ->assertOk();
});

it('can list menus', function () {
    $menus = Menu::factory()->count(5)->create();

    livewire(MenuResource\Pages\ListMenus::class)
        ->assertCanSeeTableRecords($menus);
});

it('can render create menu page', function () {
    livewire(MenuResource\Pages\CreateMenu::class)
        ->assertOk();
});

it('can create a menu', function () {
    livewire(MenuResource\Pages\CreateMenu::class)
        ->fillForm([
            'name' => 'Test Menu',
            'location' => 'test-location',
            'is_active' => true,
            'items' => [
                [
                    'label' => 'Home',
                    'url' => '/',
                    'order' => 0,
                ],
            ],
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Menu::class, [
        'name' => 'Test Menu',
        'location' => 'test-location',
    ]);
});

it('can render edit menu page', function () {
    $menu = Menu::factory()->create();

    livewire(MenuResource\Pages\EditMenu::class, [
        'record' => $menu->id,
    ])
        ->assertOk();
});

it('can update a menu', function () {
    $menu = Menu::factory()->create();

    livewire(MenuResource\Pages\EditMenu::class, [
        'record' => $menu->id,
    ])
        ->fillForm([
            'name' => 'Updated Menu Name',
        ])
        ->call('save')
        ->assertNotified();

    expect($menu->refresh()->name)->toBe('Updated Menu Name');
});

it('can filter active menus', function () {
    Menu::factory()->count(3)->create(['is_active' => true]);
    Menu::factory()->count(2)->inactive()->create();

    livewire(MenuResource\Pages\ListMenus::class)
        ->filterTable('is_active', true)
        ->assertCanSeeTableRecords(Menu::where('is_active', true)->get())
        ->assertCanNotSeeTableRecords(Menu::where('is_active', false)->get());
});
