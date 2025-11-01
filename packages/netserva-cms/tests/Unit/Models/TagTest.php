<?php

declare(strict_types=1);

use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

it('can create a tag', function () {
    $tag = Tag::factory()->create([
        'name' => 'Test Tag',
    ]);

    expect($tag)->toBeInstanceOf(Tag::class)
        ->and($tag->name)->toBe('Test Tag')
        ->and($tag->slug)->toBe('test-tag');
});

it('generates slug from name automatically', function () {
    $tag = Tag::factory()->create([
        'name' => 'My Awesome Tag',
    ]);

    expect($tag->slug)->toBe('my-awesome-tag');
});

it('can have many posts', function () {
    $tag = Tag::factory()->create();
    $posts = Post::factory()->count(10)->create();

    $posts->each(fn ($post) => $post->tags()->attach($tag));

    $tag->load('posts');

    expect($tag->posts)->toHaveCount(10);
});
