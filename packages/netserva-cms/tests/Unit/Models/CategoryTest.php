<?php

declare(strict_types=1);

use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;

it('can create a category', function () {
    $category = Category::factory()->create([
        'name' => 'Test Category',
    ]);

    expect($category)->toBeInstanceOf(Category::class)
        ->and($category->name)->toBe('Test Category')
        ->and($category->slug)->toBe('test-category');
});

it('generates slug from name automatically', function () {
    $category = Category::factory()->create([
        'name' => 'My Awesome Category',
    ]);

    expect($category->slug)->toBe('my-awesome-category');
});

it('can have different types', function () {
    $postCategory = Category::factory()->post()->create();
    $portfolioCategory = Category::factory()->portfolio()->create();
    $newsCategory = Category::factory()->news()->create();
    $docsCategory = Category::factory()->docs()->create();

    expect($postCategory->type)->toBe('post')
        ->and($portfolioCategory->type)->toBe('portfolio')
        ->and($newsCategory->type)->toBe('news')
        ->and($docsCategory->type)->toBe('docs');
});

it('scopes categories by type correctly', function () {
    Category::factory()->count(3)->post()->create();
    Category::factory()->count(2)->portfolio()->create();

    $postCategories = Category::ofType('post')->get();

    expect($postCategories)->toHaveCount(3);
});

it('can have many posts', function () {
    $category = Category::factory()->create();
    $posts = Post::factory()->count(5)->create();

    $posts->each(fn ($post) => $post->categories()->attach($category));

    $category->load('posts');

    expect($category->posts)->toHaveCount(5);
});
