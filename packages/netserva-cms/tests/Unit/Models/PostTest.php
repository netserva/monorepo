<?php

declare(strict_types=1);

use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

it('can create a post', function () {
    $post = Post::factory()->create([
        'title' => 'Test Post',
    ]);

    expect($post)->toBeInstanceOf(Post::class)
        ->and($post->title)->toBe('Test Post')
        ->and($post->slug)->toBe('test-post');
});

it('generates slug from title automatically', function () {
    $post = Post::factory()->create([
        'title' => 'My Awesome Blog Post',
    ]);

    expect($post->slug)->toBe('my-awesome-blog-post');
});

it('can have categories', function () {
    $post = Post::factory()->create();
    $categories = Category::factory()->count(3)->create();

    $post->categories()->sync($categories->pluck('id'));

    expect($post->fresh()->categories)->toHaveCount(3);
});

it('can have tags', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory()->count(5)->create();

    $post->tags()->attach($tags->pluck('id'));

    expect($post->tags)->toHaveCount(5);
});

it('calculates word count automatically', function () {
    $content = str_repeat('word ', 100);
    $post = Post::factory()->create(['content' => $content]);

    expect($post->word_count)->toBeGreaterThan(0);
});

it('calculates reading time correctly', function () {
    $content = str_repeat('word ', 1000); // 1000 words
    $post = Post::factory()->create(['content' => $content]);

    $readingTime = $post->getReadingTime();

    expect($readingTime)->toBeInt()
        ->and($readingTime)->toBeGreaterThan(0);
});

it('scopes published posts correctly', function () {
    Post::factory()->count(5)->published()->create();
    Post::factory()->count(3)->unpublished()->create();

    $publishedPosts = Post::published()->get();

    expect($publishedPosts)->toHaveCount(5);
});

it('can filter posts by category', function () {
    $category = Category::factory()->create();
    $posts = Post::factory()->count(3)->create();
    $posts->each(fn ($post) => $post->categories()->attach($category));

    $otherPosts = Post::factory()->count(2)->create();

    $categoryPosts = Post::query()
        ->whereHas('categories', function ($q) use ($category) {
            $q->where('cms_categories.id', $category->id);
        })
        ->get();

    expect($categoryPosts)->toHaveCount(3);
});

it('can filter posts by tag', function () {
    $tag = Tag::factory()->create();
    $posts = Post::factory()->count(4)->create();
    $posts->each(fn ($post) => $post->tags()->attach($tag));

    $otherPosts = Post::factory()->count(2)->create();

    $tagPosts = Post::query()
        ->whereHas('tags', function ($q) use ($tag) {
            $q->where('cms_tags.id', $tag->id);
        })
        ->get();

    expect($tagPosts)->toHaveCount(4);
});

it('soft deletes posts', function () {
    $post = Post::factory()->create();
    $post->delete();

    expect(Post::count())->toBe(0)
        ->and(Post::withTrashed()->count())->toBe(1);
});

it('uses slug as route key', function () {
    $post = Post::factory()->create(['slug' => 'my-post']);

    expect($post->getRouteKeyName())->toBe('slug');
});

it('stores meta information', function () {
    $post = Post::factory()->create([
        'meta_title' => 'SEO Title',
        'meta_description' => 'SEO Description',
        'meta_keywords' => 'blog, post, seo',
    ]);

    expect($post->meta_title)->toBe('SEO Title')
        ->and($post->meta_description)->toBe('SEO Description')
        ->and($post->meta_keywords)->toBe('blog, post, seo');
});
