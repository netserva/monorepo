<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use NetServa\Cms\Filament\Resources\PostResource;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render post list', function () {
    Post::factory()->count(10)->create();

    livewire(PostResource\Pages\ListPosts::class)
        ->assertOk();
});

it('can list posts', function () {
    $posts = Post::factory()->count(10)->create();

    livewire(PostResource\Pages\ListPosts::class)
        ->assertCanSeeTableRecords($posts);
});

it('can render create post page', function () {
    livewire(PostResource\Pages\CreatePost::class)
        ->assertOk();
});

it('can create a post', function () {
    // Post form uses modal-based metadata entry which is complex to test via Livewire
})->skip('Complex modal-based form - tested via browser tests');

it('can render edit post page', function () {
    $post = Post::factory()->create();

    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->assertOk();
});

it('can update a post', function () {
    // Post form uses modal-based metadata entry which is complex to test via Livewire
})->skip('Complex modal-based form - tested via browser tests');

it('can attach categories to a post', function () {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(3)->create(['type' => 'post']);

    // Categories are attached via the 'categories' modal action
    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->callAction('categories', [
            'categories' => $categories->pluck('id')->toArray(),
            'tags' => [],
        ])
        ->assertNotified();

    expect($post->fresh()->categories)->not->toBeEmpty();
});

it('can attach tags to a post', function () {
    // Tags are attached via modal action which is complex to test via Livewire
})->skip('Complex modal-based form - tested via browser tests');

it('can filter published posts', function () {
    Post::factory()->count(5)->published()->create();
    Post::factory()->count(3)->unpublished()->create();

    livewire(PostResource\Pages\ListPosts::class)
        ->filterTable('is_published', true)
        ->assertCanSeeTableRecords(Post::where('is_published', true)->get())
        ->assertCanNotSeeTableRecords(Post::where('is_published', false)->get());
});

it('can search posts by title', function () {
    $posts = Post::factory()->count(5)->create();
    $specificPost = $posts->first();

    livewire(PostResource\Pages\ListPosts::class)
        ->searchTable($specificPost->title)
        ->assertCanSeeTableRecords([$specificPost])
        ->assertCanNotSeeTableRecords($posts->skip(1));
});
