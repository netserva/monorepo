<?php

declare(strict_types=1);

namespace NetServa\Cms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use NetServa\Cms\Models\Category;
use NetServa\Cms\Models\Post;
use NetServa\Cms\Models\Tag;

/**
 * Frontend Post/Blog Controller
 *
 * CRITICAL: NO NetServa dependencies - completely standalone
 */
class PostController
{
    /**
     * Display all published posts (blog archive)
     */
    public function index(Request $request): View
    {
        $query = Post::published()
            ->with(['categories', 'tags'])
            ->latest('published_at');

        // Filter by category if provided
        if ($request->has('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        // Filter by tag if provided
        if ($request->has('tag')) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->where('slug', $request->input('tag'));
            });
        }

        // Search if query provided
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(config('netserva-cms.blog.posts_per_page', 12));

        return view('netserva-cms::posts.index', [
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
        ]);
    }

    /**
     * Display a single post by slug
     */
    public function show(string $slug): View
    {
        $post = Post::published()
            ->where('slug', $slug)
            ->with(['categories', 'tags'])
            ->firstOrFail();

        // Get related posts (same categories, exclude current)
        $relatedPosts = Post::published()
            ->where('id', '!=', $post->id)
            ->whereHas('categories', function ($query) use ($post) {
                $query->whereIn('cms_categories.id', $post->categories->pluck('id'));
            })
            ->limit(3)
            ->latest('published_at')
            ->get();

        return view('netserva-cms::posts.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
        ]);
    }

    /**
     * Display posts by category
     */
    public function category(string $slug): View
    {
        $category = Category::where('slug', $slug)
            ->firstOrFail();

        $posts = Post::published()
            ->whereHas('categories', function ($query) use ($category) {
                $query->where('cms_categories.id', $category->id);
            })
            ->with(['categories', 'tags'])
            ->latest('published_at')
            ->paginate(config('netserva-cms.blog.posts_per_page', 12));

        return view('netserva-cms::posts.category', [
            'category' => $category,
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
        ]);
    }

    /**
     * Display posts by tag
     */
    public function tag(string $slug): View
    {
        $tag = Tag::where('slug', $slug)
            ->firstOrFail();

        $posts = Post::published()
            ->whereHas('tags', function ($query) use ($tag) {
                $query->where('cms_tags.id', $tag->id);
            })
            ->with(['categories', 'tags'])
            ->latest('published_at')
            ->paginate(config('netserva-cms.blog.posts_per_page', 12));

        return view('netserva-cms::posts.tag', [
            'tag' => $tag,
            'posts' => $posts,
            'categories' => Category::withCount('posts')->get(),
            'tags' => Tag::withCount('posts')->get(),
        ]);
    }
}
