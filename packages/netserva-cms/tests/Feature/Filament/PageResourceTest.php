<?php

declare(strict_types=1);

use NetServa\Cms\Filament\Resources\PageResource;
use NetServa\Cms\Models\Page;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

it('can render page list', function () {
    Page::factory()->count(10)->create();

    livewire(PageResource\Pages\ListPages::class)
        ->assertOk();
});

it('can list pages', function () {
    $pages = Page::factory()->count(10)->create();

    livewire(PageResource\Pages\ListPages::class)
        ->assertCanSeeTableRecords($pages);
});

it('can render create page', function () {
    livewire(PageResource\Pages\CreatePage::class)
        ->assertOk();
});

it('can create a page', function () {
    $newPage = Page::factory()->make();

    livewire(PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title' => $newPage->title,
            'content' => $newPage->content,
            'template' => $newPage->template,
            'is_published' => true,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Page::class, [
        'title' => $newPage->title,
    ]);
});

it('can validate create page input', function () {
    livewire(PageResource\Pages\CreatePage::class)
        ->fillForm([
            'title' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['title' => 'required']);
});

it('can render edit page', function () {
    $page = Page::factory()->create();

    livewire(PageResource\Pages\EditPage::class, [
        'record' => $page->getRouteKey(),
    ])
        ->assertOk();
});

it('can retrieve page data for editing', function () {
    $page = Page::factory()->create();

    livewire(PageResource\Pages\EditPage::class, [
        'record' => $page->getRouteKey(),
    ])
        ->assertSchemaStateSet([
            'title' => $page->title,
            'slug' => $page->slug,
        ]);
});

it('can update a page', function () {
    $page = Page::factory()->create();
    $newTitle = 'Updated Page Title';
    $newSlug = 'updated-page-title';
    $newExcerpt = 'This is an updated excerpt for testing purposes.';

    livewire(PageResource\Pages\EditPage::class, [
        'record' => $page->getRouteKey(),
    ])
        ->fillForm([
            'title' => $newTitle,
            'slug' => $newSlug,
            'excerpt' => $newExcerpt,
        ])
        ->call('save')
        ->assertNotified();

    expect($page->refresh())
        ->title->toBe($newTitle)
        ->slug->toBe($newSlug)
        ->excerpt->toBe($newExcerpt);
});

it('can delete a page', function () {
    $page = Page::factory()->create();

    livewire(PageResource\Pages\EditPage::class, [
        'record' => $page->getRouteKey(),
    ])
        ->callAction('delete')
        ->assertNotified();

    expect(Page::withTrashed()->find($page->id)->trashed())->toBeTrue();
});

it('can filter published pages', function () {
    Page::factory()->count(5)->published()->create();
    Page::factory()->count(3)->unpublished()->create();

    livewire(PageResource\Pages\ListPages::class)
        ->filterTable('is_published', true)
        ->assertCanSeeTableRecords(Page::where('is_published', true)->get())
        ->assertCanNotSeeTableRecords(Page::where('is_published', false)->get());
});

it('can search pages by title', function () {
    $pages = Page::factory()->count(5)->create();
    $specificPage = $pages->first();

    livewire(PageResource\Pages\ListPages::class)
        ->searchTable($specificPage->title)
        ->assertCanSeeTableRecords([$specificPage])
        ->assertCanNotSeeTableRecords($pages->skip(1));
});
