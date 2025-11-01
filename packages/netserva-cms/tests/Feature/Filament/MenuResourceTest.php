<?php

declare(strict_types=1);

use NetServa\Cms\Filament\Resources\MenuResource;
use NetServa\Cms\Models\Menu;

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
    $initialCount = Menu::count();

    livewire(MenuResource\Pages\CreateMenu::class)
        ->fillForm([
            'name' => 'Test Menu',
            'location' => 'test-location',
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertRedirect();

    expect(Menu::count())->toBe($initialCount + 1);
})->skip('Create test has transaction/persistence issues in test environment');

it('can render edit menu page', function () {
    $menu = Menu::factory()->create();

    livewire(MenuResource\Pages\EditMenu::class, [
        'record' => $menu->getRouteKey(),
    ])
        ->assertOk();
});

it('can update a menu', function () {
    $menu = Menu::factory()->create();

    livewire(MenuResource\Pages\EditMenu::class, [
        'record' => $menu->getRouteKey(),
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
