<?php

declare(strict_types=1);

use NetServa\Cms\Filament\Resources\PostResource;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

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
    $newPost = Post::factory()->make();
    $category = Category::factory()->create();

    livewire(PostResource\Pages\CreatePost::class)
        ->fillForm([
            'title' => $newPost->title,
            'content' => $newPost->content,
            'excerpt' => $newPost->excerpt,
            'is_published' => true,
            'categories' => [$category->id],
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Post::class, [
        'title' => $newPost->title,
    ]);
});

it('can render edit post page', function () {
    $post = Post::factory()->create();

    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->assertOk();
});

it('can update a post', function () {
    $post = Post::factory()->create();
    $newData = Post::factory()->make();

    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->fillForm([
            'title' => $newData->title,
        ])
        ->call('save')
        ->assertNotified();

    expect($post->refresh())
        ->title->toBe($newData->title);
});

it('can attach categories to a post', function () {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(3)->create();

    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->fillForm([
            'categories' => $categories->pluck('id')->toArray(),
        ])
        ->call('save')
        ->assertNotified();

    expect($post->fresh()->categories)->toHaveCount(3);
});

it('can attach tags to a post', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory()->count(5)->create();

    livewire(PostResource\Pages\EditPost::class, [
        'record' => $post->getRouteKey(),
    ])
        ->fillForm([
            'tags' => $tags->pluck('id')->toArray(),
        ])
        ->call('save');

    expect($post->refresh()->tags)->toHaveCount(5);
});

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
