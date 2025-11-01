<?php

declare(strict_types=1);

use NetServa\Cms\Models\Page;

it('displays the homepage', function () {
    $homepage = Page::factory()->homepage()->published()->create();

    $response = $this->get('/');

    $response->assertOk()
        ->assertSee($homepage->title)
        ->assertViewIs('netserva-cms::pages.templates.homepage')
        ->assertViewHas('page', fn ($page) => $page->id === $homepage->id);
});

it('displays a page by slug', function () {
    $page = Page::factory()->published()->create(['slug' => 'about']);

    $response = $this->get('/about');

    $response->assertOk()
        ->assertSee($page->title)
        ->assertViewHas('page', fn ($p) => $p->id === $page->id);
});

it('displays nested pages correctly', function () {
    $parent = Page::factory()->published()->create(['slug' => 'services']);
    $child = Page::factory()->published()->create([
        'slug' => 'web-development',
        'parent_id' => $parent->id,
    ]);

    $response = $this->get('/services/web-development');

    $response->assertOk()
        ->assertSee($child->title)
        ->assertViewHas('page', fn ($p) => $p->id === $child->id)
        ->assertViewHas('parent', fn ($p) => $p->id === $parent->id);
});

it('returns 404 for unpublished pages', function () {
    $page = Page::factory()->unpublished()->create(['slug' => 'draft']);

    $response = $this->get('/draft');

    $response->assertNotFound();
});

it('returns 404 for non-existent pages', function () {
    $response = $this->get('/non-existent-page');

    $response->assertNotFound();
});

it('uses the correct template for service pages', function () {
    $page = Page::factory()->service()->published()->create(['slug' => 'hosting']);

    $response = $this->get('/hosting');

    $response->assertOk()
        ->assertViewIs('netserva-cms::pages.templates.service');
});

it('uses the correct template for pricing pages', function () {
    $page = Page::factory()->pricing()->published()->create();

    $response = $this->get('/pricing');

    $response->assertOk()
        ->assertViewIs('netserva-cms::pages.templates.pricing');
});

it('displays page content', function () {
    $page = Page::factory()->published()->create([
        'content' => '<p>This is test content</p>',
    ]);

    $response = $this->get('/'.$page->slug);

    $response->assertOk()
        ->assertSee('This is test content', false);
});

it('displays page excerpt when available', function () {
    $page = Page::factory()->published()->create([
        'excerpt' => 'This is a test excerpt',
    ]);

    $response = $this->get('/'.$page->slug);

    $response->assertOk()
        ->assertSee('This is a test excerpt');
});
