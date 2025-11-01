<?php

declare(strict_types=1);

use NetServa\Cms\Models\Page;

it('can create a page', function () {
    $page = Page::factory()->create([
        'title' => 'Test Page',
    ]);

    expect($page)->toBeInstanceOf(Page::class)
        ->and($page->title)->toBe('Test Page')
        ->and($page->slug)->toBe('test-page');
});

it('generates slug from title automatically', function () {
    $page = Page::factory()->create([
        'title' => 'My Awesome Page Title',
    ]);

    expect($page->slug)->toBe('my-awesome-page-title');
});

it('can create published page', function () {
    $page = Page::factory()->published()->create();

    expect($page->is_published)->toBeTrue()
        ->and($page->published_at)->not->toBeNull();
});

it('can create unpublished page', function () {
    $page = Page::factory()->unpublished()->create();

    expect($page->is_published)->toBeFalse()
        ->and($page->published_at)->toBeNull();
});

it('can have a parent page', function () {
    $parent = Page::factory()->create(['title' => 'Parent']);
    $child = Page::factory()->create([
        'title' => 'Child',
        'parent_id' => $parent->id,
    ]);

    expect($child->parent)->toBeInstanceOf(Page::class)
        ->and($child->parent->id)->toBe($parent->id)
        ->and($parent->children)->toHaveCount(1);
});

it('can have multiple children', function () {
    $parent = Page::factory()->create();
    $children = Page::factory()->count(3)->create(['parent_id' => $parent->id]);

    expect($parent->children)->toHaveCount(3);
});

it('scopes published pages correctly', function () {
    Page::factory()->count(3)->published()->create();
    Page::factory()->count(2)->unpublished()->create();

    $publishedPages = Page::published()->get();

    expect($publishedPages)->toHaveCount(3);
});

it('scopes root pages correctly', function () {
    Page::factory()->count(2)->create(['parent_id' => null]);
    $parent = Page::factory()->create();
    Page::factory()->count(3)->create(['parent_id' => $parent->id]);

    $rootPages = Page::rootPages()->get();

    expect($rootPages)->toHaveCount(3); // 2 without parent + 1 parent
});

it('uses slug as route key', function () {
    $page = Page::factory()->create(['slug' => 'my-page']);

    expect($page->getRouteKeyName())->toBe('slug');
});

it('can generate different templates', function () {
    $homepage = Page::factory()->homepage()->create();
    $service = Page::factory()->service()->create();
    $pricing = Page::factory()->pricing()->create();

    expect($homepage->template)->toBe('homepage')
        ->and($service->template)->toBe('service')
        ->and($pricing->template)->toBe('pricing');
});

it('stores meta information', function () {
    $page = Page::factory()->create([
        'meta_title' => 'SEO Title',
        'meta_description' => 'SEO Description',
        'meta_keywords' => 'seo, keywords',
    ]);

    expect($page->meta_title)->toBe('SEO Title')
        ->and($page->meta_description)->toBe('SEO Description')
        ->and($page->meta_keywords)->toBe('seo, keywords');
});

it('soft deletes pages', function () {
    $page = Page::factory()->create();
    $page->delete();

    expect(Page::count())->toBe(0)
        ->and(Page::withTrashed()->count())->toBe(1);
});
