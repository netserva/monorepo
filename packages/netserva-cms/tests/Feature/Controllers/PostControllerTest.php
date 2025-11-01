<?php

declare(strict_types=1);

use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

it('displays blog index page', function () {
    Post::factory()->count(5)->published()->create();

    $response = $this->get('/blog');

    $response->assertOk()
        ->assertViewIs('netserva-cms::posts.index')
        ->assertViewHas('posts');
});

it('displays only published posts on index', function () {
    Post::factory()->count(3)->published()->create();
    Post::factory()->count(2)->unpublished()->create();

    $response = $this->get('/blog');

    $response->assertOk();

    $posts = $response->viewData('posts');
    expect($posts)->toHaveCount(3);
});

it('paginates blog posts', function () {
    Post::factory()->count(15)->published()->create();

    $response = $this->get('/blog');

    $response->assertOk();

    $posts = $response->viewData('posts');
    expect($posts)->toHaveCount(12); // Default pagination from config
});

it('displays single post by slug', function () {
    $post = Post::factory()->published()->create(['slug' => 'test-post']);

    $response = $this->get('/blog/test-post');

    $response->assertOk()
        ->assertViewIs('netserva-cms::posts.show')
        ->assertSee($post->title)
        ->assertViewHas('post', fn ($p) => $p->id === $post->id);
});

it('returns 404 for unpublished posts', function () {
    $post = Post::factory()->unpublished()->create(['slug' => 'draft']);

    $response = $this->get('/blog/draft');

    $response->assertNotFound();
});

it('displays post content', function () {
    $post = Post::factory()->published()->create([
        'content' => '<p>This is test post content</p>',
    ]);

    $response = $this->get('/blog/'.$post->slug);

    $response->assertOk()
        ->assertSee('This is test post content', false);
});

it('displays related posts', function () {
    $category = Category::factory()->create();

    $mainPost = Post::factory()->published()->create();
    $mainPost->categories()->attach($category);

    $relatedPosts = Post::factory()->count(3)->published()->create();
    $relatedPosts->each(fn ($post) => $post->categories()->attach($category));

    $response = $this->get('/blog/'.$mainPost->slug);

    $response->assertOk()
        ->assertViewHas('relatedPosts');
});

it('filters posts by category', function () {
    $category = Category::factory()->create(['slug' => 'news']);
    $posts = Post::factory()->count(3)->published()->create();
    $posts->each(fn ($post) => $post->categories()->attach($category));

    Post::factory()->count(2)->published()->create(); // Other posts

    $response = $this->get('/blog/category/news');

    $response->assertOk()
        ->assertViewIs('netserva-cms::posts.category')
        ->assertViewHas('category', fn ($c) => $c->id === $category->id);

    $categoryPosts = $response->viewData('posts');
    expect($categoryPosts)->toHaveCount(3);
});

it('filters posts by tag', function () {
    $tag = Tag::factory()->create(['slug' => 'laravel']);
    $posts = Post::factory()->count(4)->published()->create();
    $posts->each(fn ($post) => $post->tags()->attach($tag));

    Post::factory()->count(2)->published()->create(); // Other posts

    $response = $this->get('/blog/tag/laravel');

    $response->assertOk()
        ->assertViewIs('netserva-cms::posts.tag')
        ->assertViewHas('tag', fn ($t) => $t->id === $tag->id);

    $tagPosts = $response->viewData('posts');
    expect($tagPosts)->toHaveCount(4);
});

it('searches posts by query', function () {
    Post::factory()->published()->create([
        'title' => 'Laravel Testing Best Practices',
    ]);
    Post::factory()->published()->create([
        'title' => 'PHP Performance Tips',
    ]);

    $response = $this->get('/blog?search=Laravel');

    $response->assertOk();

    $posts = $response->viewData('posts');
    expect($posts)->toHaveCount(1);
});

it('filters posts by category via query parameter', function () {
    $category = Category::factory()->create(['slug' => 'tutorials']);
    $post = Post::factory()->published()->create();
    $post->categories()->attach($category);

    Post::factory()->count(2)->published()->create();

    $response = $this->get('/blog?category=tutorials');

    $response->assertOk();

    $posts = $response->viewData('posts');
    expect($posts)->toHaveCount(1);
});

it('filters posts by tag via query parameter', function () {
    $tag = Tag::factory()->create(['slug' => 'testing']);
    $post = Post::factory()->published()->create();
    $post->tags()->attach($tag);

    Post::factory()->count(2)->published()->create();

    $response = $this->get('/blog?tag=testing');

    $response->assertOk();

    $posts = $response->viewData('posts');
    expect($posts)->toHaveCount(1);
});
